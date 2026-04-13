CREATE OR REPLACE FUNCTION create_usuario(
    p_dni VARCHAR,
    p_first_name VARCHAR,
    p_last_name VARCHAR,
    p_email VARCHAR,
    p_phone VARCHAR,
    p_office_id INT,
    p_password TEXT
)
RETURNS VOID AS $$
BEGIN
    INSERT INTO usuarios(dni, first_name, last_name, email, phone, office_id, password)
    VALUES (p_dni, p_first_name, p_last_name, p_email, p_phone, p_office_id, p_password);
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION create_trabajador(
    p_role VARCHAR,
    p_dni VARCHAR,
    p_first_name VARCHAR,
    p_last_name VARCHAR,
    p_email VARCHAR,
    p_phone VARCHAR,
    p_office_id INT,
    p_password TEXT
)
RETURNS VOID AS $$
BEGIN
    INSERT INTO trabajadores(role, dni, first_name, last_name, email, phone, office_id, password)
    VALUES (p_role, p_dni, p_first_name, p_last_name, p_email, p_phone, p_office_id, p_password);
END;
$$ LANGUAGE plpgsql;


CREATE OR REPLACE FUNCTION create_ticket(
    p_user_id INT,
    p_category VARCHAR,
    p_title VARCHAR,
    p_description TEXT
)
RETURNS INT AS $$
DECLARE
    new_ticket_id INT;
BEGIN
    -- Crear ticket (ya queda en Pendiente por defecto)
    INSERT INTO tickets(user_id, category, title, description)
    VALUES (p_user_id, p_category::ticket_category, p_title, p_description)
    RETURNING id INTO new_ticket_id;

    -- Historial (lo hace un admin por defecto o sistema → usamos NULL o 1 si tienes admin fijo)
    INSERT INTO ticket_history(ticket_id, status, changed_by, comment)
    VALUES (new_ticket_id, 'Pendiente', NULL, 'Ticket creado');

    RETURN new_ticket_id;
END;
$$ LANGUAGE plpgsql;


CREATE OR REPLACE FUNCTION asignar_tecnico(
    p_ticket_id INT,
    p_technician_id INT,
    p_admin_id INT
)
RETURNS VOID AS $$
BEGIN
    -- Actualizar ticket
    UPDATE tickets
    SET 
        technician_id = p_technician_id,
        status = 'En camino'
    WHERE id = p_ticket_id;

    -- Historial
    INSERT INTO ticket_history(ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, 'En camino', p_admin_id, 'Técnico asignado');
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION update_ticket_status(
    p_ticket_id INT,
    p_technician_id INT,
    p_status VARCHAR,
    p_comment TEXT
)
RETURNS VOID AS $$
BEGIN
    -- Actualizar ticket
    UPDATE tickets
    SET 
        status = p_status::ticket_status,
        attended_at = CASE 
            WHEN p_status = 'Atendido' THEN NOW()
            ELSE attended_at
        END
    WHERE id = p_ticket_id
      AND technician_id = p_technician_id;

    -- Historial
    INSERT INTO ticket_history(ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, p_status::ticket_status, p_technician_id, p_comment);
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION login_user(
    p_dni VARCHAR,
    p_password TEXT
)
RETURNS TABLE (
    id INT,
    role VARCHAR,
    first_name VARCHAR,
    last_name VARCHAR
) AS $$
BEGIN
    RETURN QUERY

    -- TRABAJADORES (admin / tecnico)
    SELECT 
        t.id,
        t.role::VARCHAR,
        t.first_name,
        t.last_name
    FROM trabajadores t
    WHERE t.dni = p_dni
      AND t.password = p_password

    UNION

    -- USUARIOS (clientes)
    SELECT 
        u.id,
        'usuario' AS role,
        u.first_name,
        u.last_name
    FROM usuarios u
    WHERE u.dni = p_dni
      AND u.password = p_password;

END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION search_office(
    p_name VARCHAR
)
RETURNS TABLE (
    id INT,
    name VARCHAR,
    location VARCHAR,
    location_detail TEXT
) AS $$
BEGIN
    RETURN QUERY
    SELECT o.id, o.name, o.location, o.location_detail
    FROM oficina o
    WHERE o.name ILIKE '%' || p_name || '%';
END;
$$ LANGUAGE plpgsql;