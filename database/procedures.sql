-- =============================================================================
-- TASTE ALCHEMY · 6 procedimientos almacenados
-- El núcleo del proyecto: coherencia física verificable y el mecanismo de
-- tiraje transaccional (reserva de unidades + comisión + factura).
-- =============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS sp_coherencia_lote;
DROP PROCEDURE IF EXISTS sp_registrar_lectura;
DROP PROCEDURE IF EXISTS sp_aceptar_propuesta;
DROP PROCEDURE IF EXISTS sp_emitir_factura;
DROP PROCEDURE IF EXISTS sp_cerrar_consignacion;
DROP PROCEDURE IF EXISTS sp_metricas_plataforma;

DELIMITER $$

-- -----------------------------------------------------------------------------
-- 1. sp_coherencia_lote
-- Evalúa cuatro comprobaciones físicas independientes y devuelve 0..100.
--   A) Evaporación:  esperado = v_inicial · (1 - k · años)
--   B) Trayectoria:  esperado = abv_inicial - k2 · años (+ penalización si sube)
--   C) Cadencia:     ¿hay lecturas repartidas a lo largo de todo el periodo?
--   D) Declaración:  ¿el ABV declarado coincide con la última lectura?
-- El veredicto siempre es explicable: se devuelve el desglose completo en JSON.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_coherencia_lote(
  IN p_lot_id BIGINT UNSIGNED,
  OUT p_score TINYINT,
  OUT p_breakdown JSON
)
BEGIN
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_initial_volume DECIMAL(12,3) DEFAULT 0;
  DECLARE v_initial_abv DECIMAL(6,2) DEFAULT 0;
  DECLARE v_declared_abv DECIMAL(6,2) DEFAULT 0;
  DECLARE v_started_at DATE;
  DECLARE v_status VARCHAR(20) DEFAULT 'en_maceracion';
  DECLARE v_bottled_at DATE DEFAULT NULL;
  DECLARE v_reference_date DATE;
  DECLARE v_reading_count INT DEFAULT 0;
  DECLARE v_first_date DATE;
  DECLARE v_last_date DATE;
  DECLARE v_last_volume DECIMAL(12,3) DEFAULT 0;
  DECLARE v_last_abv DECIMAL(6,2) DEFAULT 0;
  DECLARE v_span_days INT DEFAULT 0;
  DECLARE v_max_gap INT DEFAULT 0;
  DECLARE v_years DECIMAL(10,4) DEFAULT 0;
  DECLARE v_expected_volume DECIMAL(12,3) DEFAULT 0;
  DECLARE v_expected_abv DECIMAL(6,2) DEFAULT 0;
  DECLARE v_evap_dev DECIMAL(10,5) DEFAULT 0;
  DECLARE v_abv_dev DECIMAL(10,5) DEFAULT 0;
  DECLARE v_decl_dev DECIMAL(10,5) DEFAULT 0;
  DECLARE v_evap_score DECIMAL(6,2) DEFAULT 0;
  DECLARE v_abv_score DECIMAL(6,2) DEFAULT 0;
  DECLARE v_cadence_score DECIMAL(6,2) DEFAULT 0;
  DECLARE v_decl_score DECIMAL(6,2) DEFAULT 0;
  DECLARE v_total TINYINT DEFAULT 0;
  DECLARE v_abv_increases INT DEFAULT 0;
  DECLARE v_count_factor DECIMAL(6,3) DEFAULT 0;
  DECLARE v_gap_factor DECIMAL(6,3) DEFAULT 0;
  DECLARE v_expected_readings INT DEFAULT 1;
  DECLARE v_breakdown_json JSON;

  DECLARE v_k_evap DECIMAL(8,5) DEFAULT 0.025;
  DECLARE v_k_abv DECIMAL(8,5) DEFAULT 0.35;
  DECLARE v_evap_tol DECIMAL(8,5) DEFAULT 0.15;
  DECLARE v_abv_tol DECIMAL(8,5) DEFAULT 4.0;
  DECLARE v_decl_tol DECIMAL(8,5) DEFAULT 0.5;
  DECLARE v_per_year DECIMAL(8,2) DEFAULT 4;
  DECLARE v_w_evap DECIMAL(5,3) DEFAULT 0.35;
  DECLARE v_w_abv DECIMAL(5,3) DEFAULT 0.25;
  DECLARE v_w_cad DECIMAL(5,3) DEFAULT 0.20;
  DECLARE v_w_decl DECIMAL(5,3) DEFAULT 0.20;
  DECLARE v_min_age_days INT DEFAULT 30;
  DECLARE v_now DATETIME DEFAULT NOW();

  SELECT COUNT(*) INTO v_exists FROM lots WHERE id = p_lot_id;
  IF v_exists = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LOTE_NO_EXISTE: no hay lote con ese identificador';
  END IF;

  SELECT initial_volume_l, initial_abv, declared_abv, started_at, status, bottled_at
    INTO v_initial_volume, v_initial_abv, v_declared_abv, v_started_at, v_status, v_bottled_at
    FROM lots WHERE id = p_lot_id;

  SELECT CAST(setting_value AS DECIMAL(8,5)) INTO v_k_evap
    FROM settings WHERE setting_key = 'coherence.evaporation_k';
  SELECT CAST(setting_value AS DECIMAL(8,5)) INTO v_k_abv
    FROM settings WHERE setting_key = 'coherence.abv_k';
  SELECT CAST(setting_value AS DECIMAL(8,5)) INTO v_evap_tol
    FROM settings WHERE setting_key = 'coherence.evap_tolerance';
  SELECT CAST(setting_value AS DECIMAL(8,5)) INTO v_abv_tol
    FROM settings WHERE setting_key = 'coherence.abv_tolerance';
  SELECT CAST(setting_value AS DECIMAL(8,5)) INTO v_decl_tol
    FROM settings WHERE setting_key = 'coherence.declaration_tolerance';
  SELECT CAST(setting_value AS DECIMAL(8,5)) INTO v_per_year
    FROM settings WHERE setting_key = 'coherence.cadence_per_year';
  SELECT CAST(setting_value AS DECIMAL(5,3)) INTO v_w_evap
    FROM settings WHERE setting_key = 'coherence.weight_evaporation';
  SELECT CAST(setting_value AS DECIMAL(5,3)) INTO v_w_abv
    FROM settings WHERE setting_key = 'coherence.weight_abv';
  SELECT CAST(setting_value AS DECIMAL(5,3)) INTO v_w_cad
    FROM settings WHERE setting_key = 'coherence.weight_cadence';
  SELECT CAST(setting_value AS DECIMAL(5,3)) INTO v_w_decl
    FROM settings WHERE setting_key = 'coherence.weight_declaration';
  SELECT CAST(setting_value AS UNSIGNED) INTO v_min_age_days
    FROM settings WHERE setting_key = 'trust.min_lot_age_days';

  SET v_k_evap = COALESCE(v_k_evap, 0.025);
  SET v_k_abv = COALESCE(v_k_abv, 0.35);
  SET v_evap_tol = NULLIF(COALESCE(v_evap_tol, 0.15), 0);
  SET v_abv_tol = NULLIF(COALESCE(v_abv_tol, 4.0), 0);
  SET v_decl_tol = NULLIF(COALESCE(v_decl_tol, 0.5), 0);
  SET v_per_year = COALESCE(v_per_year, 4);
  SET v_w_evap = COALESCE(v_w_evap, 0.35);
  SET v_w_abv = COALESCE(v_w_abv, 0.25);
  SET v_w_cad = COALESCE(v_w_cad, 0.20);
  SET v_w_decl = COALESCE(v_w_decl, 0.20);

  SELECT COUNT(*) INTO v_reading_count FROM lot_readings WHERE lot_id = p_lot_id;

  IF v_reading_count = 0 THEN
    SET v_total = 0;
    SET v_breakdown_json = JSON_OBJECT(
      'lot_id', p_lot_id,
      'score', 0,
      'computed_at', DATE_FORMAT(v_now, '%Y-%m-%dT%H:%i:%s'),
      'verdict', 'sin_datos',
      'checks', JSON_ARRAY(),
      'explanation', 'El lote no tiene lecturas: no hay evidencia física que verificar.'
    );
  ELSE
    SELECT volume_l, abv, taken_at
      INTO v_last_volume, v_last_abv, v_last_date
      FROM lot_readings
      WHERE lot_id = p_lot_id
      ORDER BY taken_at DESC, id DESC
      LIMIT 1;

    SELECT MIN(taken_at), MAX(taken_at)
      INTO v_first_date, v_last_date
      FROM lot_readings WHERE lot_id = p_lot_id;

    SET v_span_days = GREATEST(0, DATEDIFF(v_last_date, v_first_date));
    -- El periodo que se juzga no termina en la última lectura: si el lote sigue
    -- vivo, el silencio también es evidencia. Un lote embotellado se juzga hasta
    -- su embotellado; uno en reposo, hasta hoy.
    SET v_reference_date = CASE
      WHEN v_status IN ('embotellado','conservado')
        THEN GREATEST(COALESCE(v_bottled_at, v_last_date), v_last_date)
      ELSE CURDATE()
    END;
    SET v_span_days = GREATEST(0, DATEDIFF(v_reference_date, v_first_date));
    SET v_years = GREATEST(0, DATEDIFF(v_reference_date, v_started_at) / 365.25);

    SELECT COALESCE(MAX(g), 0) INTO v_max_gap
    FROM (
      SELECT DATEDIFF(taken_at, LAG(taken_at) OVER (ORDER BY taken_at, id)) AS g
      FROM lot_readings
      WHERE lot_id = p_lot_id
    ) q;
    SET v_max_gap = GREATEST(v_max_gap, DATEDIFF(v_reference_date, v_last_date));

    -- A) Evaporación
    SET v_expected_volume = v_initial_volume * (1 - v_k_evap * v_years);
    IF v_expected_volume <= 0 THEN SET v_expected_volume = 0.001; END IF;
    SET v_evap_dev = ABS(v_last_volume - v_expected_volume) / v_expected_volume;
    SET v_evap_score = GREATEST(0, LEAST(100, ROUND(100 * (1 - v_evap_dev / v_evap_tol), 2)));

    -- B) Trayectoria de graduación
    SET v_expected_abv = GREATEST(0, v_initial_abv - v_k_abv * v_years);
    SET v_abv_dev = ABS(v_last_abv - v_expected_abv);
    SET v_abv_score = GREATEST(0, LEAST(100, ROUND(100 * (1 - v_abv_dev / v_abv_tol), 2)));

    SELECT COUNT(*) INTO v_abv_increases
    FROM (
      SELECT abv, LAG(abv) OVER (ORDER BY taken_at, id) AS prev_abv
      FROM lot_readings WHERE lot_id = p_lot_id
    ) q2
    WHERE q2.prev_abv IS NOT NULL AND q2.abv > q2.prev_abv + 0.5;

    IF v_abv_increases > 0 THEN
      SET v_abv_score = GREATEST(0, v_abv_score - LEAST(30, v_abv_increases * 5));
    END IF;

    -- C) Cadencia de lecturas (lecturas repartidas a lo largo del periodo)
    SET v_expected_readings = GREATEST(1, CEIL(v_years * v_per_year));
    SET v_count_factor = LEAST(1, v_reading_count / v_expected_readings);
    IF v_span_days <= 0 THEN
      SET v_gap_factor = 0.5;
    ELSE
      SET v_gap_factor = GREATEST(0, 1 - (v_max_gap / (v_span_days + 1)));
    END IF;
    SET v_cadence_score = ROUND(100 * (0.6 * v_count_factor + 0.4 * v_gap_factor), 2);

    -- D) Declaración
    SET v_decl_dev = ABS(v_declared_abv - v_last_abv);
    SET v_decl_score = GREATEST(0, LEAST(100, ROUND(100 * (1 - v_decl_dev / v_decl_tol), 2)));

    SET v_total = ROUND(
      v_w_evap * v_evap_score +
      v_w_abv  * v_abv_score  +
      v_w_cad  * v_cadence_score +
      v_w_decl * v_decl_score
    );

    SET v_breakdown_json = JSON_OBJECT(
      'lot_id', p_lot_id,
      'score', v_total,
      'computed_at', DATE_FORMAT(v_now, '%Y-%m-%dT%H:%i:%s'),
      'verdict', CASE WHEN v_total >= 85 THEN 'solido' WHEN v_total >= 70 THEN 'plausible' WHEN v_total >= 45 THEN 'dudoso' ELSE 'incoherente' END,
      'physics', JSON_OBJECT(
        'years', ROUND(v_years, 3),
        'initial_volume_l', v_initial_volume,
        'latest_volume_l', v_last_volume,
        'expected_volume_l', ROUND(v_expected_volume, 3),
        'initial_abv', v_initial_abv,
        'latest_abv', v_last_abv,
        'expected_abv', ROUND(v_expected_abv, 2),
        'declared_abv', v_declared_abv,
        'reading_count', v_reading_count,
        'first_reading', v_first_date,
        'last_reading', v_last_date,
        'span_days', v_span_days,
        'max_gap_days', v_max_gap
      ),
      'checks', JSON_ARRAY(
        JSON_OBJECT(
          'code', 'evaporation',
          'label', 'Evaporación de barrica',
          'score', ROUND(v_evap_score),
          'weight', v_w_evap,
          'expected', ROUND(v_expected_volume, 2),
          'actual', v_last_volume,
          'deviation', ROUND(v_evap_dev * 100, 2),
          'unit', '% volumen',
          'status', CASE WHEN v_evap_score >= 70 THEN 'ok' WHEN v_evap_score >= 40 THEN 'warn' ELSE 'bad' END,
          'explanation', CONCAT('La pérdida real es ', ROUND(v_evap_dev * 100, 2), ' %, frente al ', ROUND(v_k_evap * 100, 2), ' % anual esperado durante ', ROUND(v_years, 2), ' años.')
        ),
        JSON_OBJECT(
          'code', 'abv',
          'label', 'Trayectoria de graduación',
          'score', ROUND(v_abv_score),
          'weight', v_w_abv,
          'expected', ROUND(v_expected_abv, 2),
          'actual', v_last_abv,
          'deviation', ROUND(v_abv_dev, 2),
          'unit', 'puntos ABV',
          'status', CASE WHEN v_abv_score >= 70 THEN 'ok' WHEN v_abv_score >= 40 THEN 'warn' ELSE 'bad' END,
          'explanation', CONCAT('La graduación medida se desvía ', ROUND(v_abv_dev, 2), ' puntos de la trayectoria esperada', IF(v_abv_increases > 0, CONCAT('; además hay ', v_abv_increases, ' repuntes anómalos.'), '.'))
        ),
        JSON_OBJECT(
          'code', 'cadence',
          'label', 'Cadencia de lecturas',
          'score', ROUND(v_cadence_score),
          'weight', v_w_cad,
          'expected', v_expected_readings,
          'actual', v_reading_count,
          'deviation', v_max_gap,
          'unit', 'lecturas / hueco máximo en días',
          'status', CASE WHEN v_cadence_score >= 70 THEN 'ok' WHEN v_cadence_score >= 40 THEN 'warn' ELSE 'bad' END,
          'explanation', CONCAT(v_reading_count, ' lecturas repartidas en ', v_span_days, ' días; hueco máximo de ', v_max_gap, ' días. Se esperaban al menos ', v_expected_readings, ' lecturas.')
        ),
        JSON_OBJECT(
          'code', 'declaration',
          'label', 'Declaración frente a última lectura',
          'score', ROUND(v_decl_score),
          'weight', v_w_decl,
          'expected', v_last_abv,
          'actual', v_declared_abv,
          'deviation', ROUND(v_decl_dev, 2),
          'unit', 'puntos ABV',
          'status', CASE WHEN v_decl_score >= 70 THEN 'ok' WHEN v_decl_score >= 40 THEN 'warn' ELSE 'bad' END,
          'explanation', CONCAT('El ABV declarado (', v_declared_abv, ' %) y el de la última lectura (', v_last_abv, ' %) difieren ', ROUND(v_decl_dev, 2), ' puntos.')
        )
      ),
      'explanation', CONCAT('Coherencia ', v_total, '/100: ', v_reading_count, ' lecturas durante ', ROUND(v_years, 2), ' años. La evidencia física es la única credencial que no se puede fabricar.')
    );
  END IF;

  UPDATE lots
     SET coherence_score = v_total, last_verdict_at = v_now
   WHERE id = p_lot_id;

  INSERT INTO lot_verdicts
    (lot_id, score, evaporation_score, abv_score, cadence_score, declaration_score, breakdown_json, computed_at)
  VALUES
    (p_lot_id, v_total, ROUND(v_evap_score), ROUND(v_abv_score), ROUND(v_cadence_score), ROUND(v_decl_score), v_breakdown_json, v_now);

  SET p_score = v_total;
  SET p_breakdown = v_breakdown_json;
END$$

-- -----------------------------------------------------------------------------
-- 2. sp_registrar_lectura
-- Valida y registra una lectura, sincroniza el lote y recalcula la coherencia.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_registrar_lectura(
  IN p_lot_id BIGINT UNSIGNED,
  IN p_volume_l DECIMAL(12,3),
  IN p_abv DECIMAL(6,2),
  IN p_temperature_c DECIMAL(5,2),
  IN p_taken_at DATE,
  IN p_recorded_by BIGINT UNSIGNED,
  OUT p_score TINYINT,
  OUT p_breakdown JSON
)
BEGIN
  DECLARE v_status VARCHAR(20);
  DECLARE v_started DATE;
  DECLARE v_owner BIGINT UNSIGNED;
  DECLARE v_exists INT DEFAULT 0;

  SELECT COUNT(*) INTO v_exists FROM lots WHERE id = p_lot_id;
  IF v_exists = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LOTE_NO_EXISTE: no hay lote con ese identificador';
  END IF;

  SELECT status, started_at, artisan_id INTO v_status, v_started, v_owner
    FROM lots WHERE id = p_lot_id;

  IF v_status IN ('descartado','bloqueado') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LOTE_NO_ACTIVO: no admite lecturas';
  END IF;

  IF p_taken_at IS NULL OR p_taken_at < v_started THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FECHA_INVALIDA: la lectura es anterior al inicio del lote';
  END IF;

  IF p_taken_at > CURDATE() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FECHA_FUTURA: no se pueden registrar lecturas futuras';
  END IF;

  IF p_volume_l IS NULL OR p_volume_l <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VOLUMEN_INVALIDO: el volumen debe ser positivo';
  END IF;

  IF p_abv IS NULL OR p_abv <= 0 OR p_abv > 90 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ABV_INVALIDO: la graduación debe estar entre 0 y 90';
  END IF;

  INSERT INTO lot_readings (lot_id, taken_at, volume_l, abv, temperature_c, recorded_by)
  VALUES (p_lot_id, p_taken_at, p_volume_l, p_abv, p_temperature_c, p_recorded_by);

  UPDATE lots
     SET current_volume_l = p_volume_l,
         declared_abv = p_abv,
         status = CASE
                    WHEN status = 'en_maceracion'
                     AND DATEDIFF(p_taken_at, started_at) >= 30 THEN 'en_reposo'
                    ELSE status
                  END
   WHERE id = p_lot_id;

  CALL sp_coherencia_lote(p_lot_id, p_score, p_breakdown);
END$$

-- -----------------------------------------------------------------------------
-- 3. sp_aceptar_propuesta
-- EL MECANISMO DE TIRAJE. Una sola transacción: bloqueo de la fila del lote,
-- reserva de unidades, cambio de estado de la petición, comisión, exclusividad,
-- consignación o factura. Ocurre todo, o no ocurre nada.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_aceptar_propuesta(
  IN p_proposal_id BIGINT UNSIGNED,
  IN p_venue_id BIGINT UNSIGNED,
  IN p_mode VARCHAR(20),
  OUT p_commission_id BIGINT UNSIGNED,
  OUT p_message VARCHAR(255)
)
BEGIN
  DECLARE v_prop_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_req_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_lot_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_license VARCHAR(20) DEFAULT NULL;
  DECLARE v_prop_mode VARCHAR(20) DEFAULT NULL;
  DECLARE v_request_id BIGINT UNSIGNED;
  DECLARE v_lot_id BIGINT UNSIGNED;
  DECLARE v_artisan_id BIGINT UNSIGNED;
  DECLARE v_venue_owner BIGINT UNSIGNED;
  DECLARE v_units INT DEFAULT 0;
  DECLARE v_price DECIMAL(10,2) DEFAULT 0;
  DECLARE v_units_total INT DEFAULT 0;
  DECLARE v_units_reserved INT DEFAULT 0;
  DECLARE v_units_sold INT DEFAULT 0;
  DECLARE v_available INT DEFAULT 0;
  DECLARE v_territory VARCHAR(120);
  DECLARE v_target DATE;
  DECLARE v_excl_req TINYINT DEFAULT 0;
  DECLARE v_excl_prop TINYINT DEFAULT 0;
  DECLARE v_excl_granted TINYINT DEFAULT 0;
  DECLARE v_conflict INT DEFAULT 0;
  DECLARE v_invoice BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_days INT DEFAULT 90;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  SET p_commission_id = NULL;
  SET p_message = 'KO';

  IF p_mode NOT IN ('venta','consignacion') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'MODO_INVALIDO: sólo venta o consignación';
  END IF;

  START TRANSACTION;

  SELECT p.status, p.request_id, p.lot_id, p.artisan_id, p.units, p.price_per_unit,
         p.mode, p.exclusivity,
         rq.venue_id, rq.status, rq.territory, rq.target_date, rq.exclusivity_requested,
         l.status, l.units_total, l.units_reserved, l.units_sold
    INTO v_prop_status, v_request_id, v_lot_id, v_artisan_id, v_units, v_price,
         v_prop_mode, v_excl_prop,
         v_venue_owner, v_req_status, v_territory, v_target, v_excl_req,
         v_lot_status, v_units_total, v_units_reserved, v_units_sold
    FROM proposals p
    JOIN requests rq ON rq.id = p.request_id
    JOIN lots l      ON l.id = p.lot_id
    WHERE p.id = p_proposal_id
    FOR UPDATE;

  IF v_prop_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PROPUESTA_NO_EXISTE: no hay propuesta con ese identificador';
  END IF;

  IF v_venue_owner <> p_venue_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NO_AUTORIZADO: la petición pertenece a otro local';
  END IF;

  IF v_prop_status <> 'pendiente' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PROPUESTA_NO_DISPONIBLE: ya fue aceptada, rechazada o retirada';
  END IF;

  IF v_req_status <> 'abierta' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PETICION_NO_ABIERTA: la petición ya no admite propuestas';
  END IF;

  IF v_lot_status IN ('descartado','bloqueado','en_maceracion') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LOTE_NO_DISPONIBLE: el lote no puede servirse todavía';
  END IF;

  SELECT license_status INTO v_license FROM venue_profiles WHERE user_id = p_venue_id;
  IF v_license IS NULL OR v_license <> 'verificada' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LICENCIA_NO_VERIFICADA: el local no está verificado';
  END IF;

  IF v_prop_mode <> 'ambas' AND v_prop_mode <> p_mode THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'MODO_NO_OFRECIDO: el artesano no ofreció ese modo';
  END IF;

  SET v_available = v_units_total - v_units_reserved - v_units_sold;
  IF v_units > v_available THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SIN_UNIDADES: el lote no tiene unidades suficientes';
  END IF;

  -- Reserva atómica condicionada: el UPDATE también protege contra carreras.
  UPDATE lots
     SET units_reserved = units_reserved + v_units
   WHERE id = v_lot_id
     AND (units_total - units_reserved - units_sold) >= v_units;

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SIN_UNIDADES: otro local se ha adelantado';
  END IF;

  UPDATE proposals SET status = 'aceptada'
   WHERE id = p_proposal_id AND status = 'pendiente';
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PROPUESTA_NO_DISPONIBLE: ya fue aceptada';
  END IF;

  UPDATE proposals SET status = 'rechazada'
   WHERE request_id = v_request_id AND id <> p_proposal_id AND status = 'pendiente';

  UPDATE requests SET status = 'en_comision' WHERE id = v_request_id;

  -- Exclusividad territorial: se concede sólo si no hay otra activa sobre el
  -- mismo lote y territorio. Nunca a dos locales a la vez.
  IF v_excl_req = 1 OR v_excl_prop = 1 THEN
    SELECT COUNT(*) INTO v_conflict
      FROM exclusivities
     WHERE lot_id = v_lot_id
       AND territory = v_territory
       AND status = 'activa'
       AND (ends_on IS NULL OR ends_on >= CURDATE());
    IF v_conflict = 0 THEN
      SET v_excl_granted = 1;
    END IF;
  END IF;

  INSERT INTO commissions
    (request_id, proposal_id, lot_id, venue_id, artisan_id, units, unit_price,
     total_amount, mode, status, exclusivity_granted, created_at)
  VALUES
    (v_request_id, p_proposal_id, v_lot_id, p_venue_id, v_artisan_id, v_units, v_price,
     ROUND(v_units * v_price, 2), p_mode, 'abierta', v_excl_granted, NOW());

  SET p_commission_id = LAST_INSERT_ID();

  IF v_excl_granted = 1 THEN
    INSERT INTO exclusivities
      (lot_id, venue_id, commission_id, territory, starts_on, ends_on, status)
    VALUES
      (v_lot_id, p_venue_id, p_commission_id, v_territory,
       CURDATE(), DATE_ADD(COALESCE(v_target, CURDATE()), INTERVAL 12 MONTH), 'activa');
  END IF;

  IF p_mode = 'consignacion' THEN
    SELECT CAST(setting_value AS UNSIGNED) INTO v_days
      FROM settings WHERE setting_key = 'consignment.default_days';
    SET v_days = COALESCE(v_days, 90);
    INSERT INTO consignments (commission_id, units, due_date, status)
    VALUES (p_commission_id, v_units,
            DATE_ADD(COALESCE(v_target, CURDATE()), INTERVAL v_days DAY), 'abierta');
  ELSE
    CALL sp_emitir_factura(p_commission_id, 'venta', v_invoice);
  END IF;

  COMMIT;
  SET p_message = 'OK';
END$$

-- -----------------------------------------------------------------------------
-- 4. sp_emitir_factura
-- Factura entre los dos NIF, IVA configurable, numeración correlativa.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_emitir_factura(
  IN p_commission_id BIGINT UNSIGNED,
  IN p_kind VARCHAR(20),
  OUT p_invoice_id BIGINT UNSIGNED
)
BEGIN
  DECLARE v_base DECIMAL(12,2) DEFAULT 0;
  DECLARE v_rate DECIMAL(5,2) DEFAULT 21;
  DECLARE v_vat DECIMAL(12,2) DEFAULT 0;
  DECLARE v_total DECIMAL(12,2) DEFAULT 0;
  DECLARE v_issuer_nif VARCHAR(20) DEFAULT NULL;
  DECLARE v_recipient_nif VARCHAR(20) DEFAULT NULL;
  DECLARE v_prefix VARCHAR(20) DEFAULT 'FAC';
  DECLARE v_temp_number VARCHAR(64);

  SELECT c.total_amount, ua.nif, uv.nif
    INTO v_base, v_issuer_nif, v_recipient_nif
    FROM commissions c
    JOIN users ua ON ua.id = c.artisan_id
    JOIN users uv ON uv.id = c.venue_id
    WHERE c.id = p_commission_id;

  IF v_base IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'COMISION_NO_EXISTE: no se puede facturar';
  END IF;

  IF v_issuer_nif IS NULL OR v_recipient_nif IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NIF_FALTANTE: ambos operadores necesitan NIF para facturar';
  END IF;

  SELECT CAST(setting_value AS DECIMAL(5,2)) INTO v_rate
    FROM settings WHERE setting_key = 'billing.vat_rate';
  SET v_rate = COALESCE(v_rate, 21);

  SELECT setting_value INTO v_prefix
    FROM settings WHERE setting_key = 'billing.invoice_prefix';
  SET v_prefix = COALESCE(v_prefix, 'FAC');

  SET v_vat = ROUND(v_base * v_rate / 100, 2);
  SET v_total = v_base + v_vat;
  SET v_temp_number = CONCAT('TMP-', UUID());

  INSERT INTO invoices
    (commission_id, number, issuer_nif, recipient_nif, base_amount, vat_rate,
     vat_amount, total_amount, kind, issued_at)
  VALUES
    (p_commission_id, v_temp_number, v_issuer_nif, v_recipient_nif, v_base, v_rate,
     v_vat, v_total, p_kind, NOW());

  SET p_invoice_id = LAST_INSERT_ID();

  UPDATE invoices
     SET number = CONCAT(v_prefix, '-', YEAR(NOW()), '-', LPAD(p_invoice_id, 6, '0'))
   WHERE id = p_invoice_id;
END$$

-- -----------------------------------------------------------------------------
-- 5. sp_cerrar_consignacion
-- Cierra la consignación: unidades vendidas, devueltas, comisión completada y
-- factura entre los dos NIF por lo efectivamente vendido.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_cerrar_consignacion(
  IN p_consignment_id BIGINT UNSIGNED,
  IN p_sold INT,
  IN p_returned INT,
  OUT p_invoice_id BIGINT UNSIGNED,
  OUT p_message VARCHAR(255)
)
BEGIN
  DECLARE v_cs_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_units INT DEFAULT 0;
  DECLARE v_commission_id BIGINT UNSIGNED;
  DECLARE v_lot_id BIGINT UNSIGNED;
  DECLARE v_unit_price DECIMAL(10,2) DEFAULT 0;
  DECLARE v_comm_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_reserved INT DEFAULT 0;
  DECLARE v_invoice BIGINT UNSIGNED DEFAULT NULL;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  SET p_invoice_id = NULL;
  SET p_message = 'KO';

  IF p_sold IS NULL OR p_returned IS NULL OR p_sold < 0 OR p_returned < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CUENTAS_INCOHERENTES: las cantidades no pueden ser negativas';
  END IF;

  START TRANSACTION;

  SELECT cs.status, cs.units, cs.commission_id, c.lot_id, c.unit_price, c.status, l.units_reserved
    INTO v_cs_status, v_units, v_commission_id, v_lot_id, v_unit_price, v_comm_status, v_reserved
    FROM consignments cs
    JOIN commissions c ON c.id = cs.commission_id
    JOIN lots l        ON l.id = c.lot_id
    WHERE cs.id = p_consignment_id
    FOR UPDATE;

  IF v_cs_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CONSIGNACION_NO_EXISTE: no hay consignación con ese identificador';
  END IF;

  IF v_cs_status <> 'abierta' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CONSIGNACION_NO_ABIERTA: ya fue cerrada o anulada';
  END IF;

  IF v_comm_status <> 'abierta' AND v_comm_status <> 'en_ejecucion' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'COMISION_NO_ABIERTA: la comisión ya no admite cierre';
  END IF;

  IF p_sold + p_returned <> v_units THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CUENTAS_INCOHERENTES: vendidas + devueltas debe igualar las unidades consignadas';
  END IF;

  UPDATE lots
     SET units_reserved = units_reserved - v_units,
         units_sold = units_sold + p_sold
   WHERE id = v_lot_id
     AND units_reserved >= v_units;

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RESERVA_INCOHERENTE: el lote no tiene esas unidades reservadas';
  END IF;

  UPDATE consignments
     SET sold_units = p_sold,
         returned_units = p_returned,
         status = 'cerrada',
         closed_at = NOW()
   WHERE id = p_consignment_id;

  UPDATE commissions
     SET status = 'completada',
         completed_at = NOW(),
         total_amount = ROUND(p_sold * v_unit_price, 2)
   WHERE id = v_commission_id;

  IF p_sold > 0 THEN
    CALL sp_emitir_factura(v_commission_id, 'consignacion', v_invoice);
    SET p_invoice_id = v_invoice;
  END IF;

  COMMIT;
  SET p_message = 'OK';
END$$

-- -----------------------------------------------------------------------------
-- 6. sp_metricas_plataforma
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_metricas_plataforma(OUT p_json JSON)
BEGIN
  DECLARE v_users INT DEFAULT 0;
  DECLARE v_artisans INT DEFAULT 0;
  DECLARE v_venues INT DEFAULT 0;
  DECLARE v_verified INT DEFAULT 0;
  DECLARE v_recipes INT DEFAULT 0;
  DECLARE v_versions INT DEFAULT 0;
  DECLARE v_lots INT DEFAULT 0;
  DECLARE v_avg_coh DECIMAL(6,2) DEFAULT 0;
  DECLARE v_open_requests INT DEFAULT 0;
  DECLARE v_proposals INT DEFAULT 0;
  DECLARE v_commissions INT DEFAULT 0;
  DECLARE v_completed INT DEFAULT 0;
  DECLARE v_gmv DECIMAL(14,2) DEFAULT 0;
  DECLARE v_units_sold INT DEFAULT 0;
  DECLARE v_reviews INT DEFAULT 0;
  DECLARE v_pending_verif INT DEFAULT 0;
  DECLARE v_open_reports INT DEFAULT 0;
  DECLARE v_consignments_open INT DEFAULT 0;

  SELECT COUNT(*) INTO v_users FROM users WHERE status = 'activo';
  SELECT COUNT(*) INTO v_artisans FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.code = 'artesano';
  SELECT COUNT(*) INTO v_venues FROM venue_profiles;
  SELECT COUNT(*) INTO v_verified FROM venue_profiles WHERE license_status = 'verificada';
  SELECT COUNT(*) INTO v_recipes FROM recipes WHERE visibility = 'publica';
  SELECT COUNT(*) INTO v_versions FROM recipe_versions;
  SELECT COUNT(*) INTO v_lots FROM lots;
  SELECT COALESCE(ROUND(AVG(coherence_score), 2), 0) INTO v_avg_coh FROM lots WHERE coherence_score > 0;
  SELECT COUNT(*) INTO v_open_requests FROM requests WHERE status = 'abierta';
  SELECT COUNT(*) INTO v_proposals FROM proposals;
  SELECT COUNT(*) INTO v_commissions FROM commissions;
  SELECT COUNT(*) INTO v_completed FROM commissions WHERE status = 'completada';
  SELECT COALESCE(SUM(total_amount), 0) INTO v_gmv FROM commissions WHERE status = 'completada';
  SELECT COALESCE(SUM(units), 0) INTO v_units_sold FROM commissions WHERE status = 'completada';
  SELECT COUNT(*) INTO v_reviews FROM reviews WHERE status = 'publicada';
  SELECT COUNT(*) INTO v_pending_verif FROM verification_requests WHERE status = 'pendiente';
  SELECT COUNT(*) INTO v_open_reports FROM moderation_reports WHERE status IN ('abierto','en_revision');
  SELECT COUNT(*) INTO v_consignments_open FROM consignments WHERE status = 'abierta';

  SET p_json = JSON_OBJECT(
    'users', v_users,
    'artisans', v_artisans,
    'venues', v_venues,
    'venues_verified', v_verified,
    'recipes', v_recipes,
    'recipe_versions', v_versions,
    'lots', v_lots,
    'avg_coherence', v_avg_coh,
    'open_requests', v_open_requests,
    'proposals', v_proposals,
    'commissions', v_commissions,
    'commissions_completed', v_completed,
    'gmv', v_gmv,
    'units_sold', v_units_sold,
    'reviews', v_reviews,
    'pending_verifications', v_pending_verif,
    'open_reports', v_open_reports,
    'open_consignments', v_consignments_open
  );
END$$

DELIMITER ;
