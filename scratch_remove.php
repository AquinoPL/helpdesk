<?php
$lines = file('c:\Users\HP-AQUINO\Desktop\SISTEMA\helpdesk\index.php');
$newLines = [];
$skip = false;
foreach ($lines as $i => $line) {
    if (strpos($line, '<!-- Botón Menú Móvil Técnico -->') !== false) {
        $skip = true;
    }
    if ($skip && strpos($line, '<?php') !== false && strpos($line, '// TCKTS PENDIENTES SIN ASIGNAR') !== false) {
        $skip = false;
        // Keep this line
    }
    if (!$skip) {
        $newLines[] = $line;
    }
}
file_put_contents('c:\Users\HP-AQUINO\Desktop\SISTEMA\helpdesk\index.php', implode("", $newLines));
echo "Removed offcanvas from index.php\n";
