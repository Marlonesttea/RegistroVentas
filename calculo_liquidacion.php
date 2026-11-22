<?php
declare(strict_types=1);

require __DIR__ . '/app.php';
require __DIR__ . '/liquidation_helpers.php';

$pageTitle = 'Cálculo liquidación';
$activePage = 'calculo-liquidacion';
$pendingSales = [];
$selectedSaleIds = [];
$preview = null;
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$pdo) {
            throw new RuntimeException('No se pudo conectar con la base de datos.');
        }
        if ((string) ($_POST['action'] ?? '') !== 'calculate_liquidation') {
            throw new RuntimeException('La operación solicitada no es válida.');
        }
        $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            throw new RuntimeException('La sesión del formulario venció. Actualiza la página e inténtalo de nuevo.');
        }

        $selectedSaleIds = parse_liquidation_sale_ids($_POST['sale_ids'] ?? null);
        $previewSales = load_pending_liquidation_sales($pdo, $selectedSaleIds);
        $preview = calculate_liquidation_summary($previewSales);
    } catch (RuntimeException $exception) {
        $formError = $exception->getMessage();
    } catch (PDOException $exception) {
        $databaseError = $exception->getMessage();
    }
}

if ($pdo) {
    try {
        $pendingSales = load_pending_liquidation_sales($pdo);
    } catch (PDOException $exception) {
        $databaseError = $exception->getMessage();
    }
}

$saleStatusLabels = [
    'sold' => 'Vendido',
    'cancelled' => 'Cancelado',
    'returned_paid_delivery' => 'Devuelto - pagó domicilio',
    'returned_unpaid_delivery' => 'Devuelto - no pagó domicilio',
];

require __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">CIERRE DE CUENTAS</p>
        <h1>Cálculo liquidación</h1>
        <p>Selecciona hasta 1000 comandas pendientes para incluirlas en este cálculo.</p>
    </div>
</section>

<?php if ($formError !== ''): ?>
    <div class="notice notice-error" role="alert"><?= e($formError) ?></div>
<?php endif; ?>

<?php if (!$pendingSales): ?>
    <section class="panel"><p class="empty-state">No hay comandas pendientes por liquidar.</p></section>
<?php else: ?>
    <form action="calculo_liquidacion.php" method="post" class="panel liquidation-selection-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="calculate_liquidation">
        <div class="table-wrap">
            <table class="selection-table">
                <thead>
                    <tr><th>Seleccionar</th><th>Comanda</th><th>Fecha</th><th>Comprador</th><th>Celular</th><th>Zapatos y cantidad</th><th>Estado de venta</th></tr>
                </thead>
                <tbody>
                <?php foreach ($pendingSales as $sale): ?>
                    <tr>
                        <td><input class="sale-checkbox" type="checkbox" name="sale_ids[]" value="<?= (int) $sale['id'] ?>" aria-label="Seleccionar comanda #<?= (int) $sale['id'] ?>"></td>
                        <td>#<?= (int) $sale['id'] ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($sale['created_at']))) ?></td>
                        <td><?= $sale['buyer_name'] !== '' ? e($sale['buyer_name']) : '—' ?></td>
                        <td><?= $sale['buyer_phone'] !== '' ? e($sale['buyer_phone']) : '—' ?></td>
                        <td>
                            <?php if ($sale['items_summary']): ?>
                                <div class="order-shoes-list">
                                    <?php foreach ($sale['items_summary'] as $item): ?>
                                        <span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= e($saleStatusLabels[$sale['status']] ?? 'Estado desconocido') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="button button-primary" type="submit">Calcular</button>
    </form>
<?php endif; ?>

<?php if ($preview !== null): ?>
    <div class="liquidation-modal-backdrop">
        <section class="liquidation-modal" role="dialog" aria-modal="true" aria-labelledby="liquidation-preview-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">RESUMEN</p>
                    <h2 id="liquidation-preview-title">Cálculo de liquidación</h2>
                </div>
                <a class="text-link" href="calculo_liquidacion.php" aria-label="Cerrar cálculo">Cerrar</a>
            </div>
            <div class="table-wrap">
                <table class="invoice-table liquidation-preview-table">
                    <thead>
                        <tr><th>Pedido</th><th>Celular</th><th>Zapatos y cantidad</th><th>Lo cobrado, incluido domicilio</th><th>Venta sin domicilio</th><th>Pérdida por devolución sin pago</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($preview['rows'] as $sale): ?>
                        <tr>
                            <td>
                                <strong>Comanda #<?= (int) $sale['id'] ?></strong>
                                <small><?= e($saleStatusLabels[$sale['status']] ?? 'Estado desconocido') ?></small>
                            </td>
                            <td><?= $sale['buyer_phone'] !== '' ? e($sale['buyer_phone']) : '—' ?></td>
                            <td>
                                <?php if ($sale['items_summary']): ?>
                                    <div class="order-shoes-list">
                                        <?php foreach ($sale['items_summary'] as $item): ?>
                                            <span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= e(format_liquidation_money($sale['collected_cents'])) ?></td>
                            <td><?= e(format_liquidation_money($sale['sales_cents'])) ?></td>
                            <td><?= $sale['delivery_loss_cents'] > 0 ? '-$' . e(cents_to_decimal($sale['delivery_loss_cents'])) : '$0.00' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="liquidation-preview-totals">
                <div><span>Pedidos seleccionados</span><strong><?= (int) $preview['order_count'] ?></strong></div>
                <div><span>Pares vendidos</span><strong><?= (int) $preview['pair_count'] ?></strong></div>
                <div><span>Total cobrado</span><strong><?= e(format_liquidation_money($preview['collected_cents'])) ?></strong></div>
                <div><span>Ventas sin domicilio</span><strong><?= e(format_liquidation_money($preview['sales_cents'])) ?></strong></div>
                <div><span>Pérdidas por devoluciones</span><strong><?= e(format_liquidation_money(-$preview['delivery_losses_cents'])) ?></strong></div>
                <div class="liquidation-net-total"><span>Ganancia neta de esta liquidación</span><strong><?= e(format_liquidation_money($preview['net_profit_cents'])) ?></strong></div>
            </div>
            <form action="actions.php" method="post" class="liquidation-confirm-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="finalize_liquidation">
                <?php foreach ($selectedSaleIds as $saleId): ?>
                    <input type="hidden" name="sale_ids[]" value="<?= (int) $saleId ?>">
                <?php endforeach; ?>
                <button class="button button-primary" type="submit">Aceptar y liquidar</button>
                <a class="button button-outline" href="calculo_liquidacion.php">Cancelar</a>
            </form>
        </section>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
