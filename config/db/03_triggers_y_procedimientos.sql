-- ============================================================
-- PASO 3: TRIGGER Y STORED PROCEDURES (VERSIÓN CORREGIDA)
-- Ejecutar después de 02_claves_foraneas.sql
-- ============================================================
-- CLASIFICACIÓN:
--   TRIGGER   → generate_ticket_id  (automático en INSERT)
--   PROCEDURE → todo lo demás (login_user, search_office retornan
--               result sets; el resto ejecutan acciones)
--   FUNCTION  → No se usan aquí: MySQL solo permite funciones que
--               retornan un escalar y no pueden contener DML dentro
--               de un contexto de SELECT estándar sin restricciones.
--               Los casos de este sistema requieren PROCEDURE.
-- ============================================================

USE helpdesk;

-- ============================================================
-- TRIGGER: generate_ticket_id
-- Genera automáticamente el ID del ticket con el formato:
--   YYYYMM + secuencia de 3 dígitos
-- Ejemplo: primer ticket de junio 2026 → 202606001
-- ============================================================

DELIMITER $$

CREATE TRIGGER trigger_generate_ticket_id
BEFORE INSERT ON tickets
FOR EACH ROW
BEGIN
    DECLARE v_prefix  INT UNSIGNED;
    DECLARE v_max_id  INT UNSIGNED;

    -- Prefijo: año y mes actuales en formato YYYYMM (ej: 202606)
    SET v_prefix := CAST(DATE_FORMAT(NOW(), '%Y%m') AS UNSIGNED);

    -- Buscar el mayor ID existente que comience con ese prefijo
    SELECT MAX(id) INTO v_max_id
    FROM tickets
    WHERE id LIKE CONCAT(v_prefix, '%');

    -- Si no existe ninguno en este mes, empezar en 001
    IF v_max_id IS NULL THEN
        SET NEW.id = CAST(CONCAT(v_prefix, '001') AS UNSIGNED);
    ELSE
        SET NEW.id = v_max_id + 1;
    END IF;
END$$

DELIMITER ;


-- ============================================================
-- STORED PROCEDURES
-- ============================================================

DELIMITER $$

-- ------------------------------------------------------------
-- PROCEDURE: login_user
-- Autentica por DNI y contraseña.
-- Retorna un result set con: id, role, first_name, last_name
-- PHP lo llama con: CALL login_user(?, ?)
-- ------------------------------------------------------------
CREATE PROCEDURE login_user(
    IN p_dni      VARCHAR(20),
    IN p_password TEXT
)
BEGIN
    SELECT t.id, CAST(t.role AS CHAR) AS role, t.first_name, t.last_name
    FROM trabajadores t
    WHERE t.dni = p_dni AND t.password = p_password AND t.is_active = 1

    UNION

    SELECT u.id, 'usuario' AS role, u.first_name, u.last_name
    FROM usuarios u
    WHERE u.dni = p_dni AND u.password = p_password AND u.is_active = 1

    LIMIT 1;
END$$


-- ------------------------------------------------------------
-- PROCEDURE: search_office
-- Busca oficinas por nombre (búsqueda parcial, case-insensitive).
-- Retorna un result set con: id, name, location, location_detail
-- PHP lo llama con: CALL search_office(?)
-- ------------------------------------------------------------
CREATE PROCEDURE search_office(
    IN p_name VARCHAR(100)
)
BEGIN
    SELECT o.id, o.name, o.location, o.location_detail
    FROM oficina o
    WHERE o.name LIKE CONCAT('%', p_name, '%');
END$$


-- ------------------------------------------------------------
-- PROCEDURE: create_ticket
-- Crea un ticket e inserta el estado inicial en historial.
-- Retorna el id del nuevo ticket en el parámetro OUT p_ticket_id.
-- PHP lo llama con:
--   $conn->exec("CALL create_ticket(..., @id)");
--   $row = $conn->query("SELECT @id AS ticket_id")->fetch();
-- NOTA: el id lo genera el TRIGGER trigger_generate_ticket_id.
-- ------------------------------------------------------------
CREATE PROCEDURE create_ticket(
    IN  p_user_id      INT,
    IN  p_category     VARCHAR(50),
    IN  p_title        VARCHAR(200),
    IN  p_description  TEXT,
    IN  p_office_id    INT,
    OUT p_ticket_id    INT
)
BEGIN
    INSERT INTO tickets (user_id, category, title, description, office_id)
    VALUES (p_user_id, p_category, p_title, p_description, p_office_id);

    SET p_ticket_id := LAST_INSERT_ID();

    INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, 'Pendiente', NULL, 'Ticket creado');
END$$


-- ------------------------------------------------------------
-- PROCEDURE: asignar_tecnico
-- Asigna un técnico a un ticket y cambia el estado a 'En camino'.
-- PHP lo llama con: CALL asignar_tecnico(?, ?, ?)
-- ------------------------------------------------------------
CREATE PROCEDURE asignar_tecnico(
    IN p_ticket_id      INT,
    IN p_technician_id  INT,
    IN p_admin_id       INT
)
BEGIN
    UPDATE tickets
    SET technician_id = p_technician_id,
        status        = 'En camino'
    WHERE id = p_ticket_id;

    INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, 'En camino', p_admin_id, 'Técnico asignado');
END$$


-- ------------------------------------------------------------
-- PROCEDURE: update_ticket_status
-- Actualiza el estado de un ticket y registra el cambio.
-- PHP lo llama con: CALL update_ticket_status(?, ?, ?, ?)
-- ------------------------------------------------------------
CREATE PROCEDURE update_ticket_status(
    IN p_ticket_id      INT,
    IN p_technician_id  INT,
    IN p_status         VARCHAR(20),
    IN p_comment        TEXT
)
BEGIN
    UPDATE tickets
    SET
        status      = p_status,
        attended_at = CASE
                          WHEN p_status = 'Atendido' THEN NOW()
                          ELSE attended_at
                      END
    WHERE id = p_ticket_id AND technician_id = p_technician_id;

    INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, p_status, p_technician_id, p_comment);
END$$


-- ------------------------------------------------------------
-- PROCEDURE: rechazar_ticket
-- Rechaza un ticket desde el admin (sin técnico obligatorio).
-- PHP lo llama con: CALL rechazar_ticket(?, ?, ?)
-- ------------------------------------------------------------
CREATE PROCEDURE rechazar_ticket(
    IN p_ticket_id  INT,
    IN p_admin_id   INT,
    IN p_comment    TEXT
)
BEGIN
    UPDATE tickets
    SET status = 'Rechazado'
    WHERE id = p_ticket_id;

    INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, 'Rechazado', p_admin_id, p_comment);
END$$


-- ------------------------------------------------------------
-- PROCEDURE: reasignar_tecnico
-- Reasigna un ticket a otro técnico borrando el historial previo
-- (excepto el estado inicial 'Pendiente') y asignando el nuevo.
-- PHP lo llama con: CALL reasignar_tecnico(?, ?, ?)
-- ------------------------------------------------------------
CREATE PROCEDURE reasignar_tecnico(
    IN p_ticket_id      INT,
    IN p_technician_id  INT,
    IN p_admin_id       INT
)
BEGIN
    -- Borrar historial anterior excepto el estado 'Pendiente'
    DELETE FROM ticket_history
    WHERE ticket_id = p_ticket_id AND status != 'Pendiente';

    -- Asignar nuevo técnico
    UPDATE tickets
    SET technician_id = p_technician_id,
        status        = 'En camino'
    WHERE id = p_ticket_id;

    INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, 'En camino', p_admin_id, 'Ticket reasignado a nuevo técnico');
END$$


-- ------------------------------------------------------------
-- PROCEDURE: create_trabajador
-- Registra un nuevo trabajador (admin o técnico).
-- PHP lo llama con: CALL create_trabajador(?, ?, ?, ?, ?, ?, ?, ?)
-- ------------------------------------------------------------
CREATE PROCEDURE create_trabajador(
    IN p_role       VARCHAR(10),
    IN p_dni        VARCHAR(20),
    IN p_first_name VARCHAR(100),
    IN p_last_name  VARCHAR(100),
    IN p_email      VARCHAR(100),
    IN p_phone      VARCHAR(20),
    IN p_office_id  INT,
    IN p_password   TEXT
)
BEGIN
    INSERT INTO trabajadores (role, dni, first_name, last_name, email, phone, office_id, password)
    VALUES (p_role, p_dni, p_first_name, p_last_name, p_email, p_phone, p_office_id, p_password);
END$$


-- ------------------------------------------------------------
-- PROCEDURE: create_usuario
-- Registra un nuevo usuario (cliente/solicitante).
-- PHP lo llama con: CALL create_usuario(?, ?, ?, ?, ?, ?, ?)
-- ------------------------------------------------------------
CREATE PROCEDURE create_usuario(
    IN p_dni        VARCHAR(20),
    IN p_first_name VARCHAR(100),
    IN p_last_name  VARCHAR(100),
    IN p_email      VARCHAR(100),
    IN p_phone      VARCHAR(20),
    IN p_office_id  INT,
    IN p_password   TEXT
)
BEGIN
    INSERT INTO usuarios (dni, first_name, last_name, email, phone, office_id, password)
    VALUES (p_dni, p_first_name, p_last_name, p_email, p_phone, p_office_id, p_password);
END$$

DELIMITER ;

-- ============================================================
-- FIN PASO 3
-- Siguiente: ejecutar 04_datos_iniciales.sql
-- ============================================================
