<?php
require 'config/database.php';

// Para MariaDB/MySQL el trigger se gestiona desde el archivo SQL.
// Este script aplica el trigger compatible con MariaDB.

$drop_sql = "DROP TRIGGER IF EXISTS trigger_generate_ticket_id";

$trigger_sql = "
CREATE TRIGGER trigger_generate_ticket_id
BEFORE INSERT ON tickets
FOR EACH ROW
BEGIN
    DECLARE v_prefix  INT UNSIGNED;
    DECLARE v_max_id  INT UNSIGNED;

    -- Prefijo: año y mes actuales en formato YYYYMM (ej: 202606)
    SET v_prefix = CAST(DATE_FORMAT(NOW(), '%Y%m') AS UNSIGNED);

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

    -- Guardar el ID generado en una variable de sesión
    SET @last_inserted_ticket_id = NEW.id;
END
";

try {
    $conn->exec($drop_sql);
    $conn->exec($trigger_sql);
    echo "Trigger MariaDB creado exitosamente.\n";
} catch (PDOException $e) {
    echo "Error al crear el trigger: " . $e->getMessage() . "\n";
}
?>
