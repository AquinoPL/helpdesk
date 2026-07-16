-- ============================================================
-- PARCHE: corrige los procedures create_ticket y login_user
-- Ejecutar en la base de datos "helpdesk".
-- ============================================================

USE helpdesk;

-- ------------------------------------------------------------
-- 1. create_ticket: agrega p_office_id (6 argumentos en total)
-- ------------------------------------------------------------

DROP PROCEDURE IF EXISTS create_ticket;

DELIMITER $$
CREATE PROCEDURE create_ticket(
  IN  p_user_id     INT,
  IN  p_category    VARCHAR(50),
  IN  p_title       VARCHAR(200),
  IN  p_description TEXT,
  IN  p_office_id   INT,
  OUT p_new_id      INT
)
BEGIN
  DECLARE v_prefix CHAR(6);

  INSERT INTO tickets (user_id, category, title, description, office_id)
  VALUES (p_user_id, p_category, p_title, p_description, p_office_id);

  SET v_prefix = DATE_FORMAT(NOW(), '%Y%m');
  SELECT MAX(id) INTO p_new_id
  FROM tickets
  WHERE CAST(id AS CHAR) LIKE CONCAT(v_prefix, '%');

  INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
  VALUES (p_new_id, 'Pendiente', NULL, 'Ticket creado');
END$$
DELIMITER ;

-- ------------------------------------------------------------
-- 2. login_user: agrega office_id al resultado
-- ------------------------------------------------------------

DROP PROCEDURE IF EXISTS login_user;

DELIMITER $$
CREATE PROCEDURE login_user(
  IN p_dni      VARCHAR(20),
  IN p_password VARCHAR(255)
)
BEGIN
  SELECT t.id, t.role AS role, t.first_name, t.last_name, t.office_id
  FROM trabajadores t
  WHERE t.dni = p_dni AND t.password = p_password

  UNION

  SELECT u.id, 'usuario' AS role, u.first_name, u.last_name, u.office_id
  FROM usuarios u
  WHERE u.dni = p_dni AND u.password = p_password;
END$$
DELIMITER ;
