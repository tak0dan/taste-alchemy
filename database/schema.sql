-- =============================================================================
-- TASTE ALCHEMY · esquema relacional
-- MariaDB 10.6 / MySQL 8 · InnoDB · utf8mb4
-- 26 tablas · 5 vistas · 7 disparadores · 6 procedimientos
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS moderation_reports;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS consignments;
DROP TABLE IF EXISTS exclusivities;
DROP TABLE IF EXISTS commissions;
DROP TABLE IF EXISTS proposals;
DROP TABLE IF EXISTS requests;
DROP TABLE IF EXISTS listings;
DROP TABLE IF EXISTS lot_verdicts;
DROP TABLE IF EXISTS lot_readings;
DROP TABLE IF EXISTS lots;
DROP TABLE IF EXISTS recipe_versions;
DROP TABLE IF EXISTS recipes;
DROP TABLE IF EXISTS syrups;
DROP TABLE IF EXISTS extraction_methods;
DROP TABLE IF EXISTS ingredients;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS verification_requests;
DROP TABLE IF EXISTS venue_profiles;
DROP TABLE IF EXISTS artisan_profiles;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1. users
-- La fecha de nacimiento sólo se usa para comprobar mayoría de edad y nunca se
-- muestra. El NIF se enmascara salvo para el titular y el moderador.
-- -----------------------------------------------------------------------------
CREATE TABLE users (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  display_name    VARCHAR(120) NOT NULL,
  birth_date      DATE NULL,
  nif             VARCHAR(20) NULL,
  phone           VARCHAR(30) NULL,
  status          ENUM('activo','suspendido','eliminado') NOT NULL DEFAULT 'activo',
  last_login_at   DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_nif (nif),
  KEY ix_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. roles  (acumulables: un usuario puede tener varios)
-- -----------------------------------------------------------------------------
CREATE TABLE roles (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(30) NOT NULL,
  name        VARCHAR(60) NOT NULL,
  description VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. user_roles
-- -----------------------------------------------------------------------------
CREATE TABLE user_roles (
  user_id    BIGINT UNSIGNED NOT NULL,
  role_id    TINYINT UNSIGNED NOT NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  granted_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (user_id, role_id),
  KEY ix_ur_role (role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. artisan_profiles — el taller
-- -----------------------------------------------------------------------------
CREATE TABLE artisan_profiles (
  user_id      BIGINT UNSIGNED NOT NULL,
  studio_name  VARCHAR(160) NOT NULL,
  slug         VARCHAR(180) NOT NULL,
  territory    VARCHAR(120) NOT NULL,
  bio          TEXT NULL,
  founded_year SMALLINT UNSIGNED NULL,
  website      VARCHAR(200) NULL,
  avatar_path  VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_ap_slug (slug),
  KEY ix_ap_territory (territory),
  CONSTRAINT fk_ap_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. venue_profiles — el local con licencia
-- -----------------------------------------------------------------------------
CREATE TABLE venue_profiles (
  user_id         BIGINT UNSIGNED NOT NULL,
  venue_name      VARCHAR(160) NOT NULL,
  slug            VARCHAR(180) NOT NULL,
  license_number  VARCHAR(80) NULL,
  license_type    VARCHAR(80) NULL,
  license_status  ENUM('pendiente','verificada','rechazada','suspendida') NOT NULL DEFAULT 'pendiente',
  territory       VARCHAR(120) NOT NULL,
  address         VARCHAR(255) NULL,
  verified_at     DATETIME NULL,
  verified_by     BIGINT UNSIGNED NULL,
  document_ref    VARCHAR(255) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_vp_slug (slug),
  KEY ix_vp_license (license_status),
  KEY ix_vp_territory (territory),
  CONSTRAINT fk_vp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_vp_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. verification_requests — cola de moderación
-- -----------------------------------------------------------------------------
CREATE TABLE verification_requests (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  venue_id           BIGINT UNSIGNED NOT NULL,
  kind               ENUM('licencia','nif') NOT NULL DEFAULT 'licencia',
  submitted_nif      VARCHAR(20) NULL,
  submitted_license  VARCHAR(80) NULL,
  document_ref       VARCHAR(255) NULL,
  status             ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  notes              TEXT NULL,
  submitted_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by        BIGINT UNSIGNED NULL,
  reviewed_at        DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_vr_venue (venue_id),
  KEY ix_vr_status (status),
  CONSTRAINT fk_vr_venue FOREIGN KEY (venue_id) REFERENCES venue_profiles (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_vr_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. audit_log — traza de sólo inserción (protegida por disparadores)
-- -----------------------------------------------------------------------------
CREATE TABLE audit_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id    BIGINT UNSIGNED NULL,
  action      VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id   BIGINT UNSIGNED NULL,
  payload_json JSON NULL,
  ip          VARCHAR(45) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_entity (entity_type, entity_id),
  KEY ix_audit_actor (actor_id),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. ingredients — catálogo enriquecido desde API pública de alimentos
-- -----------------------------------------------------------------------------
CREATE TABLE ingredients (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(120) NOT NULL,
  slug              VARCHAR(140) NOT NULL,
  category          ENUM('fruta','flor','especia','hierba','corteza','azucar','otro') NOT NULL DEFAULT 'otro',
  brix              DECIMAL(6,2) NULL,
  sugar_g_per_100   DECIMAL(6,2) NULL,
  water_pct         DECIMAL(6,2) NULL,
  abv_pct           DECIMAL(6,2) NULL,
  source            VARCHAR(80) NOT NULL DEFAULT 'manual',
  external_ref      VARCHAR(190) NULL,
  notes             VARCHAR(255) NULL,
  active            TINYINT(1) NOT NULL DEFAULT 1,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ing_slug (slug),
  KEY ix_ing_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. extraction_methods
-- -----------------------------------------------------------------------------
CREATE TABLE extraction_methods (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(40) NOT NULL,
  name         VARCHAR(120) NOT NULL,
  description  TEXT NULL,
  days         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  loss_pct     DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  instructions TEXT NULL,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ext_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. syrups
-- -----------------------------------------------------------------------------
CREATE TABLE syrups (
  id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code   VARCHAR(40) NOT NULL,
  name   VARCHAR(120) NOT NULL,
  brix   DECIMAL(6,2) NOT NULL DEFAULT 65.00,
  notes  VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_syr_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. recipes — el repositorio público, versionado y forkable
-- -----------------------------------------------------------------------------
CREATE TABLE recipes (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug             VARCHAR(180) NOT NULL,
  author_id        BIGINT UNSIGNED NOT NULL,
  title            VARCHAR(180) NOT NULL,
  style            VARCHAR(80) NOT NULL,
  description      TEXT NULL,
  tags_json        JSON NULL,
  visibility       ENUM('publica','no_listada','privada') NOT NULL DEFAULT 'publica',
  current_version_id BIGINT UNSIGNED NULL,
  forked_from_id   BIGINT UNSIGNED NULL,
  fork_count       INT UNSIGNED NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rec_slug (slug),
  KEY ix_rec_author (author_id),
  KEY ix_rec_style (style),
  KEY ix_rec_visibility (visibility),
  CONSTRAINT fk_rec_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_rec_fork FOREIGN KEY (forked_from_id) REFERENCES recipes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. recipe_versions — cada versión es ejecutable por el motor
-- -----------------------------------------------------------------------------
CREATE TABLE recipe_versions (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipe_id         BIGINT UNSIGNED NOT NULL,
  version_no        INT UNSIGNED NOT NULL,
  body_json         JSON NOT NULL,
  abv               DECIMAL(6,2) NULL,
  brix              DECIMAL(6,2) NULL,
  final_volume_ml   DECIMAL(12,2) NULL,
  notes             TEXT NULL,
  changelog         VARCHAR(255) NULL,
  created_by        BIGINT UNSIGNED NOT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at      DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rv_version (recipe_id, version_no),
  KEY ix_rv_recipe (recipe_id),
  CONSTRAINT fk_rv_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE,
  CONSTRAINT fk_rv_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE recipes
  ADD CONSTRAINT fk_rec_current_version
  FOREIGN KEY (current_version_id) REFERENCES recipe_versions (id) ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- 13. lots — la unidad real del mercado: una barrica concreta
-- -----------------------------------------------------------------------------
CREATE TABLE lots (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code              VARCHAR(60) NOT NULL,
  artisan_id        BIGINT UNSIGNED NOT NULL,
  recipe_version_id BIGINT UNSIGNED NOT NULL,
  barrel_ref        VARCHAR(120) NULL,
  territory         VARCHAR(120) NOT NULL,
  initial_volume_l  DECIMAL(12,3) NOT NULL,
  current_volume_l  DECIMAL(12,3) NOT NULL,
  initial_abv       DECIMAL(6,2) NOT NULL,
  declared_abv      DECIMAL(6,2) NOT NULL,
  started_at        DATE NOT NULL,
  bottled_at        DATE NULL,
  status            ENUM('en_maceracion','en_reposo','embotellado','conservado','descartado','bloqueado') NOT NULL DEFAULT 'en_maceracion',
  coherence_score   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_verdict_at   DATETIME NULL,
  units_total       MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  units_reserved    MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  units_sold        MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  unit_ml           MEDIUMINT UNSIGNED NOT NULL DEFAULT 700,
  price_per_unit    DECIMAL(10,2) NULL,
  is_last_of_barrel TINYINT(1) NOT NULL DEFAULT 0,
  notes             TEXT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lot_code (code),
  KEY ix_lot_artisan (artisan_id),
  KEY ix_lot_status (status),
  KEY ix_lot_coherence (coherence_score),
  CONSTRAINT fk_lot_artisan FOREIGN KEY (artisan_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_lot_version FOREIGN KEY (recipe_version_id) REFERENCES recipe_versions (id) ON DELETE RESTRICT,
  CONSTRAINT ck_lot_units CHECK (units_reserved + units_sold <= units_total),
  CONSTRAINT ck_lot_volume CHECK (current_volume_l >= 0),
  CONSTRAINT ck_lot_abv CHECK (initial_abv BETWEEN 0 AND 90)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 14. lot_readings — el registro que no se puede fabricar
-- -----------------------------------------------------------------------------
CREATE TABLE lot_readings (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lot_id        BIGINT UNSIGNED NOT NULL,
  taken_at      DATE NOT NULL,
  volume_l      DECIMAL(12,3) NOT NULL,
  abv           DECIMAL(6,2) NOT NULL,
  temperature_c DECIMAL(5,2) NULL,
  note          VARCHAR(255) NULL,
  recorded_by   BIGINT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_lr_lot_date (lot_id, taken_at),
  CONSTRAINT fk_lr_lot FOREIGN KEY (lot_id) REFERENCES lots (id) ON DELETE CASCADE,
  CONSTRAINT fk_lr_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 15. lot_verdicts — historial del veredicto de coherencia
-- -----------------------------------------------------------------------------
CREATE TABLE lot_verdicts (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lot_id           BIGINT UNSIGNED NOT NULL,
  score            TINYINT UNSIGNED NOT NULL,
  evaporation_score TINYINT UNSIGNED NOT NULL,
  abv_score        TINYINT UNSIGNED NOT NULL,
  cadence_score    TINYINT UNSIGNED NOT NULL,
  declaration_score TINYINT UNSIGNED NOT NULL,
  breakdown_json   JSON NOT NULL,
  computed_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  computed_by      BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_lv_lot (lot_id, computed_at),
  CONSTRAINT fk_lv_lot FOREIGN KEY (lot_id) REFERENCES lots (id) ON DELETE CASCADE,
  CONSTRAINT fk_lv_user FOREIGN KEY (computed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 16. listings — anuncios; sólo publicables si coherencia >= umbral
-- -----------------------------------------------------------------------------
CREATE TABLE listings (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lot_id              BIGINT UNSIGNED NOT NULL,
  artisan_id          BIGINT UNSIGNED NOT NULL,
  title               VARCHAR(180) NOT NULL,
  description         TEXT NULL,
  units_available     MEDIUMINT UNSIGNED NOT NULL,
  price_per_unit      DECIMAL(10,2) NOT NULL,
  status              ENUM('borrador','publicado','reservado','vendido','agotado','retirado','oculto') NOT NULL DEFAULT 'borrador',
  coherence_at_publish TINYINT UNSIGNED NULL,
  published_at        DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_list_lot (lot_id),
  KEY ix_list_status (status),
  CONSTRAINT fk_list_lot FOREIGN KEY (lot_id) REFERENCES lots (id) ON DELETE CASCADE,
  CONSTRAINT fk_list_artisan FOREIGN KEY (artisan_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 17. requests — la petición del local; la demanda se crea publicando
-- -----------------------------------------------------------------------------
CREATE TABLE requests (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  venue_id             BIGINT UNSIGNED NOT NULL,
  title                VARCHAR(180) NOT NULL,
  style                VARCHAR(80) NOT NULL,
  description          TEXT NULL,
  territory            VARCHAR(120) NOT NULL,
  units_wanted         MEDIUMINT UNSIGNED NOT NULL,
  abv_min              DECIMAL(6,2) NULL,
  abv_max              DECIMAL(6,2) NULL,
  target_date          DATE NOT NULL,
  exclusivity_requested TINYINT(1) NOT NULL DEFAULT 0,
  status               ENUM('borrador','abierta','en_comision','cerrada','expirada','cancelada') NOT NULL DEFAULT 'borrador',
  expires_at           DATE NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_req_venue (venue_id),
  KEY ix_req_status (status),
  KEY ix_req_territory (territory),
  CONSTRAINT fk_req_venue FOREIGN KEY (venue_id) REFERENCES venue_profiles (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 18. proposals — cada propuesta apunta a un lote real
-- -----------------------------------------------------------------------------
CREATE TABLE proposals (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id       BIGINT UNSIGNED NOT NULL,
  lot_id           BIGINT UNSIGNED NOT NULL,
  artisan_id       BIGINT UNSIGNED NOT NULL,
  units            MEDIUMINT UNSIGNED NOT NULL,
  price_per_unit   DECIMAL(10,2) NOT NULL,
  mode             ENUM('venta','consignacion','ambas') NOT NULL DEFAULT 'ambas',
  exclusivity      TINYINT(1) NOT NULL DEFAULT 0,
  exclusivity_fee  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  message          TEXT NULL,
  status           ENUM('pendiente','aceptada','rechazada','retirada') NOT NULL DEFAULT 'pendiente',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prop_request (request_id),
  KEY ix_prop_lot (lot_id),
  KEY ix_prop_status (status),
  CONSTRAINT fk_prop_request FOREIGN KEY (request_id) REFERENCES requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_prop_lot FOREIGN KEY (lot_id) REFERENCES lots (id) ON DELETE CASCADE,
  CONSTRAINT fk_prop_artisan FOREIGN KEY (artisan_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 19. commissions — reserva de unidades, venta o consignación
-- -----------------------------------------------------------------------------
CREATE TABLE commissions (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id         BIGINT UNSIGNED NOT NULL,
  proposal_id        BIGINT UNSIGNED NOT NULL,
  lot_id             BIGINT UNSIGNED NOT NULL,
  venue_id           BIGINT UNSIGNED NOT NULL,
  artisan_id         BIGINT UNSIGNED NOT NULL,
  units              MEDIUMINT UNSIGNED NOT NULL,
  unit_price         DECIMAL(10,2) NOT NULL,
  total_amount       DECIMAL(12,2) NOT NULL,
  mode               ENUM('venta','consignacion') NOT NULL,
  status             ENUM('abierta','en_ejecucion','completada','anulada') NOT NULL DEFAULT 'abierta',
  exclusivity_granted TINYINT(1) NOT NULL DEFAULT 0,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at       DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_comm_proposal (proposal_id),
  KEY ix_comm_venue (venue_id),
  KEY ix_comm_artisan (artisan_id),
  KEY ix_comm_lot (lot_id),
  CONSTRAINT fk_comm_request FOREIGN KEY (request_id) REFERENCES requests (id) ON DELETE RESTRICT,
  CONSTRAINT fk_comm_proposal FOREIGN KEY (proposal_id) REFERENCES proposals (id) ON DELETE RESTRICT,
  CONSTRAINT fk_comm_lot FOREIGN KEY (lot_id) REFERENCES lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_comm_venue FOREIGN KEY (venue_id) REFERENCES venue_profiles (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_comm_artisan FOREIGN KEY (artisan_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 20. exclusivities — nunca dos locales sobre el mismo lote y territorio
-- -----------------------------------------------------------------------------
CREATE TABLE exclusivities (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lot_id        BIGINT UNSIGNED NOT NULL,
  venue_id      BIGINT UNSIGNED NOT NULL,
  commission_id BIGINT UNSIGNED NOT NULL,
  territory     VARCHAR(120) NOT NULL,
  starts_on     DATE NOT NULL,
  ends_on       DATE NULL,
  status        ENUM('activa','expirada','anulada') NOT NULL DEFAULT 'activa',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_excl_commission (commission_id),
  KEY ix_excl_lot (lot_id, territory, status),
  CONSTRAINT fk_excl_lot FOREIGN KEY (lot_id) REFERENCES lots (id) ON DELETE CASCADE,
  CONSTRAINT fk_excl_venue FOREIGN KEY (venue_id) REFERENCES venue_profiles (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_excl_comm FOREIGN KEY (commission_id) REFERENCES commissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 21. consignments — el local no arriesga capital
-- -----------------------------------------------------------------------------
CREATE TABLE consignments (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  commission_id BIGINT UNSIGNED NOT NULL,
  units         MEDIUMINT UNSIGNED NOT NULL,
  sold_units    MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  returned_units MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  due_date      DATE NOT NULL,
  status        ENUM('abierta','vendida','devuelta','cerrada','anulada','en_disputa') NOT NULL DEFAULT 'abierta',
  closed_at     DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cons_commission (commission_id),
  KEY ix_cons_status (status),
  CONSTRAINT fk_cons_comm FOREIGN KEY (commission_id) REFERENCES commissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 22. invoices — factura entre los dos NIF
-- -----------------------------------------------------------------------------
CREATE TABLE invoices (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  commission_id BIGINT UNSIGNED NOT NULL,
  number        VARCHAR(40) NOT NULL,
  issuer_nif    VARCHAR(20) NOT NULL,
  recipient_nif VARCHAR(20) NOT NULL,
  base_amount   DECIMAL(12,2) NOT NULL,
  vat_rate      DECIMAL(5,2) NOT NULL DEFAULT 21.00,
  vat_amount    DECIMAL(12,2) NOT NULL,
  total_amount  DECIMAL(12,2) NOT NULL,
  kind          ENUM('venta','consignacion') NOT NULL DEFAULT 'venta',
  issued_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status        ENUM('emitida','anulada') NOT NULL DEFAULT 'emitida',
  PRIMARY KEY (id),
  UNIQUE KEY uq_inv_number (number),
  KEY ix_inv_commission (commission_id),
  CONSTRAINT fk_inv_comm FOREIGN KEY (commission_id) REFERENCES commissions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 23. reviews — valoración verificada (sólo contraparte, 72 h de reflexión)
-- -----------------------------------------------------------------------------
CREATE TABLE reviews (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  commission_id   BIGINT UNSIGNED NULL,
  author_id       BIGINT UNSIGNED NOT NULL,
  kind            ENUM('local','comisario') NOT NULL DEFAULT 'local',
  lot_id          BIGINT UNSIGNED NOT NULL,
  score_aroma     TINYINT UNSIGNED NULL,
  score_texture   TINYINT UNSIGNED NULL,
  score_balance   TINYINT UNSIGNED NULL,
  score_finish    TINYINT UNSIGNED NULL,
  score_total     DECIMAL(3,1) NULL,
  body            TEXT NOT NULL,
  status          ENUM('publicada','oculta') NOT NULL DEFAULT 'publicada',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at    DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_rev_lot (lot_id),
  KEY ix_rev_commission (commission_id),
  KEY ix_rev_author (author_id),
  CONSTRAINT fk_rev_comm FOREIGN KEY (commission_id) REFERENCES commissions (id) ON DELETE SET NULL,
  CONSTRAINT fk_rev_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_lot FOREIGN KEY (lot_id) REFERENCES lots (id) ON DELETE CASCADE,
  CONSTRAINT ck_rev_scores CHECK (
    (score_aroma IS NULL OR score_aroma BETWEEN 1 AND 10) AND
    (score_texture IS NULL OR score_texture BETWEEN 1 AND 10) AND
    (score_balance IS NULL OR score_balance BETWEEN 1 AND 10) AND
    (score_finish IS NULL OR score_finish BETWEEN 1 AND 10)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 24. moderation_reports
-- -----------------------------------------------------------------------------
CREATE TABLE moderation_reports (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id BIGINT UNSIGNED NULL,
  entity_type ENUM('recipe','lot','listing','review','user') NOT NULL,
  entity_id   BIGINT UNSIGNED NOT NULL,
  reason      VARCHAR(120) NOT NULL,
  details     TEXT NULL,
  status      ENUM('abierto','en_revision','resuelto','descartado') NOT NULL DEFAULT 'abierto',
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  resolution  TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_mr_status (status),
  KEY ix_mr_entity (entity_type, entity_id),
  CONSTRAINT fk_mr_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_mr_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 25. settings — umbrales de confianza y parámetros de plataforma
-- -----------------------------------------------------------------------------
CREATE TABLE settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  label       VARCHAR(160) NOT NULL,
  updated_by  BIGINT UNSIGNED NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_set_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 26. notifications
-- -----------------------------------------------------------------------------
CREATE TABLE notifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  type       VARCHAR(40) NOT NULL,
  title      VARCHAR(180) NOT NULL,
  body       VARCHAR(500) NULL,
  link       VARCHAR(255) NULL,
  read_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notif_user (user_id, read_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Semilla de catálogos mínimos (roles y parámetros)
-- -----------------------------------------------------------------------------
INSERT INTO roles (code, name, description) VALUES
  ('artesano',  'Artesano',  'Publica recetas, registra lotes y propone a peticiones.'),
  ('local',     'Local',     'Bar, restaurante, hotel o bodega con licencia verificada.'),
  ('comisario', 'Comisario', 'Publica catas ponderadas sobre lotes concretos.'),
  ('moderador', 'Moderador', 'Verifica licencias, revisa reportes y aplica sanciones.'),
  ('admin',     'Administrador', 'Gestiona usuarios, catálogos y umbrales.');

INSERT INTO settings (setting_key, setting_value, label) VALUES
  ('coherence.weight_evaporation', '0.35', 'Peso de la comprobación de evaporación'),
  ('coherence.weight_abv',         '0.25', 'Peso de la trayectoria de graduación'),
  ('coherence.weight_cadence',     '0.20', 'Peso de la cadencia de lecturas'),
  ('coherence.weight_declaration', '0.20', 'Peso de la declaración de graduación'),
  ('coherence.evaporation_k',      '0.025', 'Coeficiente de evaporación anual (barrica)'),
  ('coherence.abv_k',              '0.35', 'Pérdida anual esperada de graduación'),
  ('coherence.evap_tolerance',     '0.15', 'Desviación de evaporación tolerada (fracción)'),
  ('coherence.abv_tolerance',      '4.0', 'Desviación de graduación tolerada (puntos)'),
  ('coherence.cadence_per_year',   '4', 'Lecturas mínimas esperadas por año'),
  ('coherence.declaration_tolerance', '0.5', 'Tolerancia de la graduación declarada'),
  ('trust.listing_min_score',      '70', 'Coherencia mínima para publicar un anuncio'),
  ('trust.review_reflection_hours','72', 'Horas de reflexión antes de valorar'),
  ('trust.min_lot_age_days',       '30', 'Antigüedad mínima para considerar cadencia'),
  ('billing.vat_rate',             '21', 'IVA aplicado en factura'),
  ('billing.invoice_prefix',       'FAC', 'Prefijo de numeración de facturas'),
  ('consignment.default_days',     '90', 'Plazo por defecto de consignación'),
  ('platform.name',                'Taste Alchemy', 'Nombre de la plataforma'),
  ('platform.territory',           'Comunitat Valenciana', 'Territorio por defecto');

-- Catálogos de referencia: métodos de extracción y almíbares del motor.
INSERT INTO extraction_methods (code, name, description, days, loss_pct, instructions) VALUES
  ('hot', 'Extracción en caliente',
   'Frascos sellados y baño maría suave, unas 2 horas. La extracción más rápida y potente, con alcohol perdido por evaporación que el cálculo compensa.',
   1, 15.00,
   'Sella los frascos con el alcohol y el fruto y sumérgelos en un baño maría durante unas 2 horas, manteniendo el agua justo por debajo del hervor.'),
  ('hybrid', 'Semana híbrida',
   '3 días de extracción en frío y un calor suave al final. Menos pérdida de alcohol y más aroma final.',
   3, 8.00,
   'Deja los frascos a temperatura fresca, sin luz directa, durante 3 días completos. Al tercer día aplica un calor suave y breve para terminar la extracción.'),
  ('limoncello', 'Método limoncello',
   '7 días completamente en frío, agitando a diario. No se pierde alcohol en la extracción.',
   7, 0.00,
   'Deja los frascos a temperatura fresca durante 7 días completos, agitando enérgicamente una vez al día para homogeneizar la extracción.');

INSERT INTO syrups (code, name, brix, notes) VALUES
  ('homemade', 'Almíbar casero de limoncello', 65.00, 'Agua, azúcar, piel del cítrico y fruto gastado; cocción suave 15–20 minutos.'),
  ('storebought', 'Almíbar de cereza comercial', 60.00, 'Almíbar comercial rehecho con el fruto gastado; cocción 10–15 minutos.');

-- Ingredientes base del taller (enriquecibles con tools/enrich_ingredients.py).
INSERT INTO ingredients (name, slug, category, brix, sugar_g_per_100, water_pct, abv_pct, source, notes) VALUES
  ('Cereza ácida', 'cereza-acida', 'fruta', 14.0, 10.0, 84.0, NULL, 'manual', 'Fruta de temporada de la Montaña de Alicante; base del licor granate.'),
  ('Limón', 'limon', 'fruta', 8.0, 2.5, 89.0, NULL, 'manual', 'Piel sin albedo para el método limoncello.'),
  ('Naranja', 'naranja', 'fruta', 11.0, 8.0, 87.0, NULL, 'manual', 'Piel para almíbares y mezclas cítricas.'),
  ('Hibisco seco', 'hibisco-seco', 'flor', NULL, NULL, 10.0, NULL, 'manual', 'Cálices secos; extracción corta para evitar amargor.'),
  ('Frutos rojos', 'frutos-rojos', 'fruta', 10.0, 6.0, 88.0, NULL, 'manual', 'Mezcla de fresa, frambuesa y mora.'),
  ('Azahar', 'azahar', 'flor', NULL, NULL, 12.0, NULL, 'manual', 'Flor de naranjo; maceración breve y delicada.'),
  ('Hierbabuena', 'hierbabuena', 'hierba', NULL, NULL, 85.0, NULL, 'manual', 'Hoja fresca; infusionar en frío.'),
  ('Romero', 'romero', 'hierba', NULL, NULL, 70.0, NULL, 'manual', 'Puntas frescas; dosis baja para no dominar.'),
  ('Canela en rama', 'canela-en-rama', 'corteza', NULL, NULL, 12.0, NULL, 'manual', 'Casia o ceylán; una rama por litro.'),
  ('Vainilla', 'vainilla', 'especia', NULL, NULL, 25.0, NULL, 'manual', 'Vaina abierta longitudinalmente.'),
  ('Azúcar blanco', 'azucar-blanco', 'azucar', 100.0, 100.0, 0.0, NULL, 'manual', 'Sacarosa pura para almíbares.'),
  ('Vodka neutro 40 %', 'vodka-neutro-40', 'otro', NULL, NULL, 60.0, 40.0, 'manual', 'Alcohol base de maceración; 40 % ABV.');
