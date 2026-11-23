-- =============================================================================
-- TASTE ALCHEMY · 5 vistas
-- Ninguna vista contiene lógica de negocio crítica; son proyecciones de lectura
-- para las páginas públicas y los paneles. El SQL de escritura vive en repos.
-- =============================================================================

SET NAMES utf8mb4;

DROP VIEW IF EXISTS v_recipe_catalog;
DROP VIEW IF EXISTS v_lot_public;
DROP VIEW IF EXISTS v_open_requests;
DROP VIEW IF EXISTS v_venue_trust;
DROP VIEW IF EXISTS v_artisan_scorecard;

-- 1. Catálogo público de recetas con su versión vigente y métricas de uso.
CREATE VIEW v_recipe_catalog AS
SELECT
  r.id,
  r.slug,
  r.title,
  r.style,
  r.description,
  r.tags_json,
  r.fork_count,
  r.created_at,
  r.updated_at,
  u.id            AS author_id,
  u.display_name  AS author_name,
  ap.studio_name,
  ap.slug         AS artisan_slug,
  ap.territory,
  rv.id           AS version_id,
  rv.version_no,
  rv.abv,
  rv.brix,
  rv.final_volume_ml,
  rv.created_at   AS version_created_at,
  (SELECT COUNT(*) FROM recipe_versions x WHERE x.recipe_id = r.id) AS version_count,
  (SELECT COUNT(*) FROM lots l WHERE l.recipe_version_id IN
      (SELECT x2.id FROM recipe_versions x2 WHERE x2.recipe_id = r.id)) AS lot_count,
  (SELECT ROUND(AVG(l2.coherence_score), 1) FROM lots l2 WHERE l2.recipe_version_id IN
      (SELECT x3.id FROM recipe_versions x3 WHERE x3.recipe_id = r.id)) AS avg_coherence
FROM recipes r
JOIN users u              ON u.id = r.author_id
LEFT JOIN artisan_profiles ap ON ap.user_id = r.author_id
LEFT JOIN recipe_versions rv  ON rv.id = r.current_version_id
WHERE r.visibility = 'publica';

-- 2. Lotes públicos: lo que un local puede ver antes de pedir.
CREATE VIEW v_lot_public AS
SELECT
  l.id,
  l.code,
  l.barrel_ref,
  l.territory,
  l.status,
  l.initial_volume_l,
  l.current_volume_l,
  l.initial_abv,
  l.declared_abv,
  l.started_at,
  l.bottled_at,
  l.coherence_score,
  l.last_verdict_at,
  l.units_total,
  l.units_reserved,
  l.units_sold,
  (l.units_total - l.units_reserved - l.units_sold) AS units_available,
  l.unit_ml,
  l.price_per_unit,
  l.is_last_of_barrel,
  l.recipe_version_id,
  rv.recipe_id,
  rv.version_no,
  r.title        AS recipe_title,
  r.slug         AS recipe_slug,
  u.id           AS artisan_id,
  u.display_name AS artisan_name,
  ap.studio_name,
  ap.slug        AS artisan_slug,
  (SELECT COUNT(*) FROM lot_readings lr WHERE lr.lot_id = l.id) AS reading_count,
  (SELECT ROUND(AVG(rv2.score_total), 2) FROM reviews rv2
      WHERE rv2.lot_id = l.id AND rv2.status = 'publicada') AS review_avg,
  (SELECT COUNT(*) FROM reviews rv3
      WHERE rv3.lot_id = l.id AND rv3.kind = 'local' AND rv3.status = 'publicada') AS venue_review_count
FROM lots l
JOIN recipe_versions rv ON rv.id = l.recipe_version_id
JOIN recipes r          ON r.id = rv.recipe_id
JOIN users u            ON u.id = l.artisan_id
LEFT JOIN artisan_profiles ap ON ap.user_id = l.artisan_id
WHERE l.status NOT IN ('descartado','bloqueado');

-- 3. Peticiones abiertas por territorio con su número de propuestas.
CREATE VIEW v_open_requests AS
SELECT
  rq.id,
  rq.title,
  rq.style,
  rq.description,
  rq.territory,
  rq.units_wanted,
  rq.abv_min,
  rq.abv_max,
  rq.target_date,
  rq.exclusivity_requested,
  rq.status,
  rq.expires_at,
  rq.created_at,
  vp.user_id    AS venue_id,
  vp.venue_name,
  vp.slug       AS venue_slug,
  vp.license_status,
  (SELECT COUNT(*) FROM proposals p
     WHERE p.request_id = rq.id AND p.status = 'pendiente') AS pending_proposals,
  (SELECT COUNT(*) FROM proposals p2
     WHERE p2.request_id = rq.id) AS total_proposals
FROM requests rq
JOIN venue_profiles vp ON vp.user_id = rq.venue_id;

-- 4. Confianza del local: licencia, comisiones cerradas y valoraciones emitidas.
CREATE VIEW v_venue_trust AS
SELECT
  vp.user_id,
  vp.venue_name,
  vp.slug,
  vp.territory,
  vp.license_status,
  vp.verified_at,
  u.display_name,
  u.status AS user_status,
  (SELECT COUNT(*) FROM commissions c
     WHERE c.venue_id = vp.user_id AND c.status = 'completada') AS completed_commissions,
  (SELECT COUNT(*) FROM commissions c2
     WHERE c2.venue_id = vp.user_id AND c2.status IN ('abierta','en_ejecucion')) AS open_commissions,
  (SELECT COUNT(*) FROM reviews rv
     WHERE rv.author_id = vp.user_id AND rv.kind = 'local' AND rv.status = 'publicada') AS reviews_written,
  (SELECT COUNT(*) FROM verification_requests vr
     WHERE vr.venue_id = vp.user_id AND vr.status = 'pendiente') AS pending_verifications
FROM venue_profiles vp
JOIN users u ON u.id = vp.user_id;

-- 5. Ficha del artesano: recetas, coherencia media y ventas completadas.
CREATE VIEW v_artisan_scorecard AS
SELECT
  u.id AS artisan_id,
  u.display_name,
  ap.studio_name,
  ap.slug,
  ap.territory,
  ap.founded_year,
  (SELECT COUNT(*) FROM recipes r
     WHERE r.author_id = u.id AND r.visibility = 'publica') AS public_recipes,
  (SELECT COUNT(*) FROM lots l WHERE l.artisan_id = u.id) AS lot_count,
  (SELECT ROUND(AVG(l2.coherence_score), 1) FROM lots l2
     WHERE l2.artisan_id = u.id AND l2.coherence_score > 0) AS avg_coherence,
  (SELECT COUNT(*) FROM commissions c
     WHERE c.artisan_id = u.id AND c.status = 'completada') AS completed_commissions,
  (SELECT COALESCE(SUM(c2.units), 0) FROM commissions c2
     WHERE c2.artisan_id = u.id AND c2.status = 'completada') AS units_sold,
  (SELECT COUNT(*) FROM proposals p
     WHERE p.artisan_id = u.id AND p.status = 'pendiente') AS pending_proposals,
  (SELECT ROUND(AVG(rv.score_total), 2) FROM reviews rv
     JOIN lots l3 ON l3.id = rv.lot_id
     WHERE l3.artisan_id = u.id AND rv.kind = 'local' AND rv.status = 'publicada') AS avg_review
FROM users u
JOIN artisan_profiles ap ON ap.user_id = u.id;
