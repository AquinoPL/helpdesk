INSERT INTO oficina (name, location, location_detail) VALUES
('Gerencia General', 'Edificio Principal', 'Piso 1'),
('Recursos Humanos', 'Edificio Administrativo', 'Piso 2'),
('Contabilidad', 'Edificio Financiero', 'Piso 3'),
('Tecnologías de la Información', 'Edificio TI', 'Piso 2 - Oficina 201'),
('Logística', 'Almacén Central', 'Zona Industrial');

INSERT INTO usuarios (dni, first_name, last_name, email, phone, office_id, password) VALUES
('70000005', 'Maria', 'Lopez', 'maria@empresa.com', '987654325', 2, '123456'),
('70000006', 'Pedro', 'Gomez', 'pedro@empresa.com', '987654326', 3, '123456'),
('70000007', 'Lucia', 'Torres', 'lucia@empresa.com', '987654327', 5, '123456'),
('70000008', 'Jose', 'Vargas', 'jose@empresa.com', '987654328', 2, '123456');

INSERT INTO trabajadores (role, dni, first_name, last_name, email, phone, office_id, password) VALUES
-- ADMIN
('admin', '70000001', 'Carlos', 'Ramirez', 'admin@empresa.com', '987654321', 1, '123456'),

-- TECNICOS
('tecnico', '70000002', 'Luis', 'Quispe', 'luis.ti@empresa.com', '987654322', 4, '123456'),
('tecnico', '70000003', 'Ana', 'Flores', 'ana.ti@empresa.com', '987654323', 4, '123456'),
('tecnico', '70000004', 'Jorge', 'Perez', 'jorge.ti@empresa.com', '987654324', 4, '123456');

INSERT INTO tickets (user_id, technician_id, category, title, description, status, attended_at) VALUES
-- Ticket 1 (Atendido)
(1, 2, 'Software', 'Error en sistema contable', 'No puedo acceder al sistema contable', 'Atendido', NOW()),

-- Ticket 2 (En proceso)
(2, 3, 'Hardware', 'PC no enciende', 'El equipo no responde al presionar el botón', 'En proceso', NULL),

-- Ticket 3 (Pendiente)
(3, NULL, 'Internet', 'Sin conexión', 'No hay acceso a internet en mi área', 'Pendiente', NULL),

-- Ticket 4 (Rechazado)
(4, 4, 'Instalacion', 'Instalar impresora', 'Necesito instalar impresora nueva', 'Rechazado', NOW()),

-- Ticket 5 (En proceso - asignamos 1 técnico)
(1, 2, 'Software', 'Office no funciona', 'Word se cierra automáticamente', 'En proceso', NULL);


INSERT INTO ticket_files (ticket_id, file_path) VALUES
(1, 'uploads/ticket1_img1.jpg'),
(1, 'uploads/ticket1_img2.jpg'),
(2, 'uploads/ticket2_img1.jpg'),
(3, 'uploads/ticket3_img1.jpg'),
(4, 'uploads/ticket4_img1.jpg'),
(5, 'uploads/ticket5_img1.jpg');

INSERT INTO ticket_history (ticket_id, status, changed_by, comment) VALUES
-- Ticket 1
(1, 'Pendiente', 1, 'Ticket creado'),
(1, 'En proceso', 1, 'Asignado a técnico'),
(1, 'Atendido', 2, 'Solucionado'),

-- Ticket 2
(2, 'Pendiente', 1, 'Ticket creado'),
(2, 'En proceso', 1, 'Asignado a técnico'),

-- Ticket 3
(3, 'Pendiente', 1, 'Ticket creado'),

-- Ticket 4
(4, 'Pendiente', 1, 'Ticket creado'),
(4, 'Rechazado', 1, 'Solicitud fuera de alcance'),

-- Ticket 5
(5, 'Pendiente', 1, 'Ticket creado'),
(5, 'En proceso', 1, 'Asignado a técnico');