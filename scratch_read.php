<?php
$content = file_get_contents('c:\Users\HP-AQUINO\Desktop\SISTEMA\helpdesk\index.php'); 
$pos1 = strpos($content, '<!-- TABLA PENDIENTES SIN ASIGNAR -->'); 
$pos2 = strpos($content, '<!-- TABLA ACTIVOS: TECNICO -->'); 
echo substr($content, $pos1-100, 300); 
echo "\n...\n"; 
echo substr($content, $pos2-100, 300);
