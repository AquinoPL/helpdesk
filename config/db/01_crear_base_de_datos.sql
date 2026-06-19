-- ============================================================
-- PASO 1: CREAR BASE DE DATOS Y TABLAS
-- Ejecutar este archivo primero.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ------------------------------------------------------------
-- Crear y seleccionar la base de datos
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS helpdesk
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE helpdesk;

-- ------------------------------------------------------------
-- Tabla: oficina
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oficina (
    id              INT          NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100) NOT NULL,
    location        VARCHAR(150) NULL,
    location_detail TEXT         NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabla: usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id          INT          NOT NULL AUTO_INCREMENT,
    dni         VARCHAR(20)  NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NULL,
    phone       VARCHAR(20)  NULL,
    office_id   INT          NULL,
    password    TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY usuarios_dni_key   (dni),
    UNIQUE KEY usuarios_email_key (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabla: trabajadores
-- (role: 'admin' o 'tecnico')
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trabajadores (
    id          INT                     NOT NULL AUTO_INCREMENT,
    role        ENUM('admin','tecnico') NOT NULL,
    dni         VARCHAR(20)             NOT NULL,
    first_name  VARCHAR(100)            NOT NULL,
    last_name   VARCHAR(100)            NOT NULL,
    email       VARCHAR(100)            NULL,
    phone       VARCHAR(20)             NULL,
    office_id   INT                     NULL,
    password    TEXT                    NOT NULL,
    created_at  DATETIME                NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active   TINYINT(1)              NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY trabajadores_dni_key   (dni),
    UNIQUE KEY trabajadores_email_key (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabla: tickets
-- (category y status usan ENUM)
-- NOTA: el id NO es AUTO_INCREMENT porque lo genera el trigger.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id            INT          NOT NULL,
    user_id       INT          NOT NULL,
    technician_id INT          NULL,
    office_id     INT          NULL,
    category      ENUM('Instalacion','Software','Hardware','Internet','Otro') NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT         NULL,
    tech_comment  TEXT         NULL,
    status        ENUM('Pendiente','En camino','En proceso','Atendido','Rechazado')
                               NOT NULL DEFAULT 'Pendiente',
    attended_at   DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tickets_status     (status),
    KEY idx_tickets_user       (user_id),
    KEY idx_tickets_technician (technician_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabla: ticket_history
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_history (
    id          INT NOT NULL AUTO_INCREMENT,
    ticket_id   INT NOT NULL,
    status      ENUM('Pendiente','En camino','En proceso','Atendido','Rechazado') NOT NULL,
    comment     TEXT     NULL,
    changed_by  INT      NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabla: ticket_files
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_files (
    id          INT  NOT NULL AUTO_INCREMENT,
    ticket_id   INT  NOT NULL,
    file_path   TEXT NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN PASO 1
-- Siguiente: ejecutar 02_claves_foraneas.sql
-- ============================================================
