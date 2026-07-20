<?php
$file = 'c:\Users\HP-AQUINO\Desktop\SISTEMA\helpdesk\index.php';
$content = file_get_contents($file);

// Replace the duplicated top part
$search = <<<PHP
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config/database.php';

if (!defined('BASE_URL')) {
    if (strpos(\$_SERVER['SCRIPT_NAME'], '/Soporte-Alianza') !== false) {
        define('BASE_URL', '/Soporte-Alianza');
    } else {
        define('BASE_URL', '');
    }
}

\$is_logged_in = isset(\$_SESSION["user"]);
\$user = \$is_logged_in ? \$_SESSION["user"] : null;
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config/database.php';

if (!defined('BASE_URL')) {
    if (strpos(\$_SERVER['SCRIPT_NAME'], '/Soporte-Alianza') !== false) {
        define('BASE_URL', '/Soporte-Alianza');
    } else {
        define('BASE_URL', '');
    }
}

\$is_logged_in = isset(\$_SESSION["user"]);
\$user = \$is_logged_in ? \$_SESSION["user"] : null;

if (\$is_logged_in && \$user["role"] == "admin") {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit();
}
PHP;

$replace = <<<PHP
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config/database.php';

if (!defined('BASE_URL')) {
    if (strpos(\$_SERVER['SCRIPT_NAME'], '/Soporte-Alianza') !== false) {
        define('BASE_URL', '/Soporte-Alianza');
    } else {
        define('BASE_URL', '');
    }
}

\$is_logged_in = isset(\$_SESSION["user"]);
\$user = \$is_logged_in ? \$_SESSION["user"] : null;

if (\$is_logged_in && \$user["role"] == "admin") {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit();
}
PHP;

$content = str_replace($search, $replace, $content);

file_put_contents($file, $content);
echo "index.php top fixed.\n";
