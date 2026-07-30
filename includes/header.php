<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Soporte</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- TomSelect CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Forzar TomSelect a abrirse siempre hacia arriba */
        .ts-wrapper.searchable-select .ts-dropdown {
            top: auto !important;
            bottom: 100% !important;
            margin-bottom: 2px !important;
            border-radius: 0.375rem 0.375rem 0 0 !important;
            box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1) !important;
            border-bottom: none !important;
            border-top: 1px solid #ced4da !important;
        }
        /* Limitar a ~7 filas (aprox 260px) y hacer scrollable */
        .ts-wrapper.searchable-select .ts-dropdown .ts-dropdown-content {
            max-height: 260px !important;
            overflow-y: auto !important;
        }
    </style>
    <!-- Theme Script -->
    <script>
        const storedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', storedTheme);
    </script>
</head>
<body class="new-ui">
<?php include 'navbar.php'; ?>
<div class="container fade-in">
