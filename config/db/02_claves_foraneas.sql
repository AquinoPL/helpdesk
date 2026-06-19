-- ============================================================
-- PASO 2: CLAVES FORÁNEAS (RELACIONES ENTRE TABLAS)
-- Ejecutar después de 01_crear_base_de_datos.sql
-- ============================================================

USE helpdesk;

-- ------------------------------------------------------------
-- usuarios → oficina
-- ------------------------------------------------------------
ALTER TABLE usuarios
    ADD CONSTRAINT fk_usuarios_oficina
        FOREIGN KEY (office_id) REFERENCES oficina(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;


-- ------------------------------------------------------------
-- trabajadores → oficina
-- ------------------------------------------------------------
ALTER TABLE trabajadores
    ADD CONSTRAINT fk_trabajadores_oficina
        FOREIGN KEY (office_id) REFERENCES oficina(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;


-- ------------------------------------------------------------
-- tickets → usuarios  (quién reportó el ticket)
-- ------------------------------------------------------------
ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_usuario
        FOREIGN KEY (user_id) REFERENCES usuarios(id);


-- ------------------------------------------------------------
-- tickets → trabajadores  (técnico asignado)
-- ------------------------------------------------------------
ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_tecnico
        FOREIGN KEY (technician_id) REFERENCES trabajadores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;


-- ------------------------------------------------------------
-- tickets → oficina  (oficina del ticket)
-- ------------------------------------------------------------
ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_oficina
        FOREIGN KEY (office_id) REFERENCES oficina(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;


-- ------------------------------------------------------------
-- ticket_history → tickets  (historial pertenece a un ticket)
-- ------------------------------------------------------------
ALTER TABLE ticket_history
    ADD CONSTRAINT fk_th_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE;


-- ------------------------------------------------------------
-- ticket_history → trabajadores  (quién cambió el estado)
-- ------------------------------------------------------------
ALTER TABLE ticket_history
    ADD CONSTRAINT fk_th_trabajador
        FOREIGN KEY (changed_by) REFERENCES trabajadores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;


-- ------------------------------------------------------------
-- ticket_files → tickets  (archivos adjuntos de un ticket)
-- ------------------------------------------------------------
ALTER TABLE ticket_files
    ADD CONSTRAINT fk_tf_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE;


-- ============================================================
-- FIN PASO 2
-- Siguiente: ejecutar 03_triggers_y_procedimientos.sql
-- ============================================================
