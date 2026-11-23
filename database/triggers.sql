-- =============================================================================
-- TASTE ALCHEMY · 7 disparadores
-- Dos reglas que el manifiesto exige que garantice la base de datos y no PHP:
--   · un anuncio sólo se publica si el lote supera el umbral de coherencia;
--   · no se pueden vender/reservar más unidades de las que el lote produjo.
-- Además: la traza de auditoría es inmutable y la valoración del local exige
-- una comisión completada y 72 horas de reflexión.
-- =============================================================================

SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_listings_before_insert;
DROP TRIGGER IF EXISTS trg_listings_before_update;
DROP TRIGGER IF EXISTS trg_lots_units_guard;
DROP TRIGGER IF EXISTS trg_audit_no_update;
DROP TRIGGER IF EXISTS trg_audit_no_delete;
DROP TRIGGER IF EXISTS trg_requests_require_verified;
DROP TRIGGER IF EXISTS trg_reviews_verified;

DELIMITER $$

-- 1. Al insertar un anuncio publicado, comprobar coherencia y disponibilidad.
CREATE TRIGGER trg_listings_before_insert
BEFORE INSERT ON listings
FOR EACH ROW
BEGIN
  DECLARE v_score TINYINT UNSIGNED DEFAULT 0;
  DECLARE v_available INT DEFAULT 0;
  DECLARE v_min TINYINT UNSIGNED DEFAULT 70;

  IF NEW.status = 'publicado' THEN
    SELECT coherence_score,
           (units_total - units_reserved - units_sold)
      INTO v_score, v_available
      FROM lots WHERE id = NEW.lot_id;

    SELECT CAST(setting_value AS UNSIGNED) INTO v_min
      FROM settings WHERE setting_key = 'trust.listing_min_score';

    IF v_score IS NULL OR v_score < v_min THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'COHERENCIA_INSUFICIENTE: el lote no alcanza el umbral para publicarse';
    END IF;

    IF NEW.units_available > v_available THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'UNIDADES_EXCEDIDAS: el anuncio ofrece más unidades de las disponibles';
    END IF;

    SET NEW.coherence_at_publish = v_score;
    SET NEW.published_at = COALESCE(NEW.published_at, NOW());
  END IF;
END$$

-- 2. La misma comprobación al actualizar (no se puede publicar después saltándosela).
CREATE TRIGGER trg_listings_before_update
BEFORE UPDATE ON listings
FOR EACH ROW
BEGIN
  DECLARE v_score TINYINT UNSIGNED DEFAULT 0;
  DECLARE v_available INT DEFAULT 0;
  DECLARE v_min TINYINT UNSIGNED DEFAULT 70;

  IF NEW.status = 'publicado' THEN
    SELECT coherence_score,
           (units_total - units_reserved - units_sold)
      INTO v_score, v_available
      FROM lots WHERE id = NEW.lot_id;

    SELECT CAST(setting_value AS UNSIGNED) INTO v_min
      FROM settings WHERE setting_key = 'trust.listing_min_score';

    IF v_score IS NULL OR v_score < v_min THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'COHERENCIA_INSUFICIENTE: el lote ya no alcanza el umbral';
    END IF;

    IF NEW.units_available > v_available THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'UNIDADES_EXCEDIDAS: el anuncio ofrece más unidades de las disponibles';
    END IF;

    SET NEW.coherence_at_publish = v_score;
    SET NEW.published_at = COALESCE(NEW.published_at, NOW());
  END IF;
END$$

-- 3. Ninguna reserva o venta puede superar las unidades producidas.
CREATE TRIGGER trg_lots_units_guard
BEFORE UPDATE ON lots
FOR EACH ROW
BEGIN
  IF NEW.units_reserved + NEW.units_sold > NEW.units_total THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'UNIDADES_EXCEDIDAS: reservadas + vendidas supera las unidades del lote';
  END IF;
  IF NEW.units_total < OLD.units_sold THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'UNIDADES_EXCEDIDAS: no se puede reducir el lote por debajo de lo vendido';
  END IF;
END$$

-- 4. La traza de auditoría no se puede modificar.
CREATE TRIGGER trg_audit_no_update
BEFORE UPDATE ON audit_log
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'AUDIT_INMUTABLE: la traza de auditoría no admite modificaciones';
END$$

-- 5. La traza de auditoría no se puede borrar.
CREATE TRIGGER trg_audit_no_delete
BEFORE DELETE ON audit_log
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'AUDIT_INMUTABLE: la traza de auditoría no admite borrados';
END$$

-- 6. Sólo un local con licencia verificada puede crear una petición.
CREATE TRIGGER trg_requests_require_verified
BEFORE INSERT ON requests
FOR EACH ROW
BEGIN
  DECLARE v_license VARCHAR(20) DEFAULT NULL;
  DECLARE v_user_status VARCHAR(20) DEFAULT NULL;

  SELECT license_status, u.status
    INTO v_license, v_user_status
    FROM venue_profiles vp
    JOIN users u ON u.id = vp.user_id
    WHERE vp.user_id = NEW.venue_id;

  IF v_license IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'LICENCIA_NO_VERIFICADA: el usuario no tiene perfil de local';
  END IF;

  IF v_license <> 'verificada' OR v_user_status <> 'activo' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'LICENCIA_NO_VERIFICADA: el local debe tener licencia verificada y cuenta activa';
  END IF;
END$$

-- 7. La valoración del local sólo existe si hubo comisión completada, la escribe
--    la contraparte y han pasado 72 horas. La cata del comisario exige rol.
CREATE TRIGGER trg_reviews_verified
BEFORE INSERT ON reviews
FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_venue BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_lot BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_completed DATETIME DEFAULT NULL;
  DECLARE v_hours INT DEFAULT 72;
  DECLARE v_is_curator INT DEFAULT 0;

  SELECT CAST(setting_value AS UNSIGNED) INTO v_hours
    FROM settings WHERE setting_key = 'trust.review_reflection_hours';

  IF NEW.kind = 'local' THEN
    IF NEW.commission_id IS NULL THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'VALORACION_NO_VERIFICADA: falta la comisión de origen';
    END IF;

    SELECT status, venue_id, lot_id, completed_at
      INTO v_status, v_venue, v_lot, v_completed
      FROM commissions WHERE id = NEW.commission_id;

    IF v_status <> 'completada' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'VALORACION_NO_VERIFICADA: la comisión no está completada';
    END IF;

    IF NEW.author_id <> v_venue OR NEW.lot_id <> v_lot THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'VALORACION_NO_VERIFICADA: sólo la contraparte puede valorar su lote';
    END IF;

    IF v_completed IS NULL OR TIMESTAMPDIFF(HOUR, v_completed, NOW()) < v_hours THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'VALORACION_PREMATURA: deben transcurrir 72 horas de reflexión';
    END IF;

    SET NEW.published_at = COALESCE(NEW.published_at, NOW());
  ELSE
    SELECT COUNT(*) INTO v_is_curator
      FROM user_roles ur JOIN roles ro ON ro.id = ur.role_id
      WHERE ur.user_id = NEW.author_id AND ro.code = 'comisario';

    IF v_is_curator = 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'CATA_NO_AUTORIZADA: sólo un comisario puede publicar catas ponderadas';
    END IF;

    SET NEW.published_at = COALESCE(NEW.published_at, NOW());
  END IF;
END$$

DELIMITER ;
