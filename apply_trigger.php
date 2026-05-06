<?php
require 'config/database.php';

$sql = "
CREATE OR REPLACE FUNCTION generate_ticket_id()
RETURNS TRIGGER AS $$
DECLARE
    prefix_ym INT;
    max_id INT;
BEGIN
    -- Obtenemos el año y mes actual en formato YYYYMM (ej: 202604)
    prefix_ym := to_char(CURRENT_TIMESTAMP, 'YYYYMM')::INT;
    
    -- Buscamos el ticket_id máximo que empiece con este prefijo.
    SELECT MAX(id) INTO max_id
    FROM tickets
    WHERE id::TEXT LIKE (prefix_ym::TEXT || '%');
    
    IF max_id IS NULL THEN
        NEW.id := (prefix_ym::TEXT || '001')::INT;
    ELSE
        NEW.id := max_id + 1;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_generate_ticket_id ON tickets;

CREATE TRIGGER trigger_generate_ticket_id
BEFORE INSERT ON tickets
FOR EACH ROW
EXECUTE FUNCTION generate_ticket_id();
";

try {
    $conn->exec($sql);
    echo "Trigger created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating trigger: " . $e->getMessage() . "\n";
}
?>
