-- =========================================
-- DATABASE
-- =========================================
CREATE DATABASE db_support
    WITH
    OWNER = postgres
    ENCODING = 'UTF8'
    LC_COLLATE = 'Spanish_Peru.1252'
    LC_CTYPE = 'Spanish_Peru.1252';

-- =========================================
-- ENUMS
-- =========================================
CREATE TYPE user_role AS ENUM ('admin', 'tecnico');

CREATE TYPE ticket_status AS ENUM (
    'Pendiente',
    'En camino',
    'En proceso',
    'Atendido',
    'Rechazado'
);

CREATE TYPE ticket_category AS ENUM (
    'Instalacion',
    'Software',
    'Hardware',
    'Internet',
    'Otro'
);

-- =========================================
-- OFICINAS
-- =========================================
CREATE TABLE oficina (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(150),
    location_detail TEXT
);

-- =========================================
-- USUARIOS (QUIENES CREAN TICKETS)
-- =========================================
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    dni VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    office_id INT,
    password TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (office_id) REFERENCES oficina(id)
);

-- =========================================
-- TRABAJADORES (ADMIN + TECNICOS)
-- =========================================
CREATE TABLE trabajadores (
    id SERIAL PRIMARY KEY,
    role user_role NOT NULL,
    dni VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    office_id INT,
    password TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (office_id) REFERENCES oficina(id)
);

-- =========================================
-- TICKETS (TABLA PRINCIPAL)
-- =========================================
CREATE TABLE tickets (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    technician_id INT,
    category ticket_category,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    status ticket_status NOT NULL DEFAULT 'Pendiente',
    attended_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES usuarios(id),
    FOREIGN KEY (technician_id) REFERENCES trabajadores(id)
);

-- =========================================
-- ARCHIVOS DEL TICKET
-- =========================================
CREATE TABLE ticket_files (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL,
    file_path TEXT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);

-- =========================================
-- HISTORIAL DE CAMBIOS
-- =========================================
CREATE TABLE ticket_history (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL,
    status ticket_status NOT NULL,
    comment TEXT,
    changed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES trabajadores(id)
);

-- =========================================
-- INDICES (RENDIMIENTO)
-- =========================================
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_user ON tickets(user_id);
CREATE INDEX idx_tickets_technician ON tickets(technician_id);