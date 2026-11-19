<?php
declare(strict_types=1);

require __DIR__ . '/app.php';

$pageTitle = 'Inicio';
$activePage = 'inicio';
$stats = ['sales_count' => 0, 'pair_count' => 0, 'revenue' => '0.00', 'collected' => '0.00', 'profit' => '0.00'];
$monthlyStats = [];

if ($pdo) {
    try {
        $stats = $pdo->query(
            "SELECT COUNT(*) AS sales_count,
                    COALESCE(SUM(CASE WHEN status = 'sold' THEN total ELSE 0 END), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN status = 'sold' THEN total_collected ELSE 0 END), 0) AS collected,
                    COALESCE(SUM(CASE WHEN status = 'sold' THEN net_profit WHEN status = 'returned_unpaid_delivery' THEN -delivery_cost ELSE 0 END), 0) AS profit
             FROM sales"
        )->fetch();
        $stats['pair_count'] = (int) $pdo->query(
            "SELECT COALESCE(SUM(item.quantity), 0) FROM sale_items item JOIN sales sale ON sale.id = item.sale_id WHERE sale.status = 'sold'"
        )->fetchColumn();
        $monthlyStats = $pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    SUM(CASE WHEN status = 'sold' THEN total ELSE 0 END) AS revenue,
                    SUM(CASE WHEN status = 'sold' THEN total_collected ELSE 0 END) AS collected,
                    SUM(CASE WHEN status = 'sold' THEN net_profit WHEN status = 'returned_unpaid_delivery' THEN -delivery_cost ELSE 0 END) AS profit
             FROM sales WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month DESC"
        )->fetchAll();
    } catch (PDOException $exception) {
        $databaseError = $exception->getMessage();
    }
}

require __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">RESUMEN</p><h1>Inicio</h1><p>Consulta tus ventas y ganancias en un solo lugar.</p></div>
    <a class="button button-primary" href="crear_comanda.php">+ Crear comanda</a>
</section>

<section class="stats-grid" aria-label="Resumen general">
    <article class="stat-card"><span>Comandas registradas</span><strong><?= (int) $stats['sales_count'] ?></strong></article>
    <article class="stat-card"><span>Pares vendidos</span><strong><?= (int) $stats['pair_count'] ?></strong></article>
    <article class="stat-card"><span>Total de venta</span><strong>$<?= e($stats['revenue']) ?></strong></article>
    <article class="stat-card"><span>Ganancia neta</span><strong>$<?= e($stats['profit']) ?></strong></article>
</section>

<section class="quick-links">
    <a class="quick-link-card" href="catalogo.php"><span class="quick-link-icon">01</span><span><strong>Catálogo</strong><small>Modelos, precios y fotos</small></span><b aria-hidden="true">→</b></a>
    <a class="quick-link-card" href="crear_comanda.php"><span class="quick-link-icon">02</span><span><strong>Crear comanda</strong><small>Registrar una nueva venta</small></span><b aria-hidden="true">→</b></a>
    <a class="quick-link-card" href="comandas.php"><span class="quick-link-icon">03</span><span><strong>Comandas</strong><small>Ver las ventas registradas</small></span><b aria-hidden="true">→</b></a>
</section>

<section class="panel">
    <div class="section-heading">
        <div><p class="eyebrow">ESTADÍSTICAS</p><h2>Resumen mensual</h2></div>
    </div>
    <?php if (!$monthlyStats): ?>
        <p class="empty-state">Aún no hay ventas en los últimos meses.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Mes</th><th>Total de venta</th><th>Total cobrado</th><th>Ganancia neta</th></tr></thead>
                <tbody>
                <?php foreach ($monthlyStats as $month): ?>
                    <tr>
                        <td><?= e(date('m/Y', strtotime($month['month'] . '-01'))) ?></td>
                        <td>$<?= e($month['revenue']) ?></td>
                        <td>$<?= e($month['collected']) ?></td>
                        <td>$<?= e($month['profit']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p class="form-hint">Ganancia neta = total cobrado − costo de compra de los pares − costo real del domicilio.</p>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
