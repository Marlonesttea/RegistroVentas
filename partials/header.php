<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Registro de zapatos</title>
    <meta name="theme-color" content="#165d59">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Registro de zapatos">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="icons/icon-180.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="app-header">
    <div class="container header-content">
        <a class="brand-link" href="index.php">
            <span class="brand-mark">Z</span>
            <span><strong>Registro de zapatos</strong><small>Control personal</small></span>
        </a>
        <nav class="navbar" aria-label="Navegación principal">
            <a class="nav-link<?= $activePage === 'inicio' ? ' active' : '' ?>" href="index.php">Inicio</a>
            <a class="nav-link<?= $activePage === 'catalogo' ? ' active' : '' ?>" href="catalogo.php">Catálogo</a>
            <a class="nav-link<?= $activePage === 'crear' ? ' active' : '' ?>" href="crear_comanda.php">Crear comanda</a>
            <a class="nav-link<?= $activePage === 'comandas' ? ' active' : '' ?>" href="comandas.php">Comandas</a>
        </nav>
    </div>
</header>
<main class="container page-content">
    <?php if ($databaseError !== ''): ?>
        <section class="notice notice-error">
            <h2>No se pudo cargar la base de datos</h2>
            <p>Verifica que MySQL esté iniciado y que hayas importado <code>schema.sql</code> o ejecutado <code>migration_v2.sql</code>.</p>
            <details><summary>Detalle</summary><code><?= e($databaseError) ?></code></details>
        </section>
    <?php endif; ?>
    <?php if ($flash): ?>
        <div class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>
