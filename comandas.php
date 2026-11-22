<?php
declare(strict_types=1);

$liquidatedPage = isset($showLiquidatedOnly) && $showLiquidatedOnly === true;
unset($showLiquidatedOnly);

require __DIR__ . '/app.php';
require __DIR__ . '/liquidation_helpers.php';

$pageTitle = $liquidatedPage ? 'Liquidado' : 'Comandas';
$activePage = $liquidatedPage ? 'liquidado' : 'comandas';
$pageUrl = $liquidatedPage ? 'liquidado.php' : 'comandas.php';
$sales = [];
$liquidationDays = [];
$saleDays = [];
$liquidationOverview = ['net_profit' => '0.00', 'delivery_losses' => '0.00'];
$statusLabels = [
    'sold' => 'Vendido',
    'cancelled' => 'Cancelado',
    'returned_paid_delivery' => 'Devuelto - pagó domicilio',
    'returned_unpaid_delivery' => 'Devuelto - no pagó domicilio',
];
$statusClasses = [
    'sold' => 'status-sold',
    'cancelled' => 'status-cancelled',
    'returned_paid_delivery' => 'status-returned',
    'returned_unpaid_delivery' => 'status-returned',
];
$liquidationLabels = [
    'pending' => 'Pendiente por liquidar',
    'liquidated' => 'Liquidada',
];
$liquidationClasses = [
    'pending' => 'status-pending-settlement',
    'liquidated' => 'status-liquidated',
];
$filterOptions = [
    'buyer_name' => 'Nombre del comprador',
    'date' => 'Fecha',
    'number' => 'Número de comanda',
    'status' => 'Estado de venta',
    'liquidation_status' => 'Estado de liquidación',
    'neighborhood' => 'Barrio',
    'phone' => 'Celular',
];
$filterBy = trim((string) ($_GET['filter_by'] ?? ''));
$filterValue = trim((string) ($_GET['filter_value'] ?? ''));
$filterError = '';
$filterClause = '';
$filterParams = [];

if ($filterBy !== '' || $filterValue !== '') {
    if (!isset($filterOptions[$filterBy])) {
        $filterError = 'Selecciona un criterio de búsqueda válido.';
    } elseif ($filterValue === '') {
        $filterError = 'Escribe o selecciona un valor para buscar.';
    } else {
        switch ($filterBy) {
            case 'buyer_name':
                if (strlen($filterValue) > 600) {
                    $filterError = 'El nombre de búsqueda es demasiado largo.';
                } else {
                    $filterClause = 'buyer_name LIKE ?';
                    $filterParams[] = '%' . $filterValue . '%';
                }
                break;
            case 'date':
                $date = DateTime::createFromFormat('!Y-m-d', $filterValue);
                $dateErrors = DateTime::getLastErrors();
                if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
                    $date->format('Y-m-d') !== $filterValue) {
                    $filterError = 'Escribe una fecha válida con el formato AAAA-MM-DD.';
                } else {
                    $filterClause = 'created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)';
                    $filterParams = [$filterValue, $filterValue];
                }
                break;
            case 'number':
                $saleId = filter_var($filterValue, FILTER_VALIDATE_INT);
                if ($saleId === false || $saleId < 1) {
                    $filterError = 'El número de comanda debe ser un entero positivo.';
                } else {
                    $filterClause = 'id = ?';
                    $filterParams[] = $saleId;
                }
                break;
            case 'status':
                $status = array_search($filterValue, $statusLabels, true);
                if ($status === false && isset($statusLabels[$filterValue])) {
                    $status = $filterValue;
                }
                if ($status === false) {
                    $filterError = 'Selecciona un estado de la lista.';
                } else {
                    $filterClause = 'status = ?';
                    $filterParams[] = $status;
                }
                break;
            case 'liquidation_status':
                $liquidationStatus = array_search($filterValue, $liquidationLabels, true);
                if ($liquidationStatus === false && isset($liquidationLabels[$filterValue])) {
                    $liquidationStatus = $filterValue;
                }
                if ($liquidationStatus === false) {
                    $filterError = 'Selecciona un estado de liquidación válido.';
                } else {
                    $filterClause = 'liquidation_status = ?';
                    $filterParams[] = $liquidationStatus;
                }
                break;
            case 'neighborhood':
                if (strlen($filterValue) > 600) {
                    $filterError = 'El barrio de búsqueda es demasiado largo.';
                } else {
                    $filterClause = 'neighborhood LIKE ?';
                    $filterParams[] = '%' . $filterValue . '%';
                }
                break;
            case 'phone':
                if (strlen($filterValue) > 30) {
                    $filterError = 'El celular de búsqueda no puede superar 30 caracteres.';
                } else {
                    $filterClause = 'buyer_phone LIKE ?';
                    $filterParams[] = '%' . $filterValue . '%';
                }
                break;
        }
    }
}

if ($pdo) {
    try {
        if ($liquidatedPage) {
            $liquidationOverview = $pdo->query(
                "SELECT
                    COALESCE(SUM(CASE WHEN status = 'sold' THEN net_profit WHEN status = 'returned_unpaid_delivery' THEN -delivery_cost ELSE 0 END), 0) AS net_profit,
                    COALESCE(SUM(CASE WHEN status = 'returned_unpaid_delivery' THEN delivery_cost ELSE 0 END), 0) AS delivery_losses
                FROM sales
                WHERE liquidation_status = 'liquidated'"
            )->fetch();
        }
        if ($filterError === '') {
            $query = 'SELECT * FROM sales';
            $whereClauses = [];
            $queryParams = [];
            if ($liquidatedPage) {
                $whereClauses[] = 'liquidation_status = ?';
                $queryParams[] = 'liquidated';
            }
            if ($filterClause !== '') {
                $whereClauses[] = $filterClause;
                $queryParams = array_merge($queryParams, $filterParams);
            }
            if ($whereClauses) {
                $query .= ' WHERE ' . implode(' AND ', $whereClauses);
            }
            $query .= $liquidatedPage
                ? ' ORDER BY liquidated_at DESC, created_at DESC, id DESC LIMIT 1000'
                : ' ORDER BY created_at DESC, id DESC LIMIT 1000';
            $saleQuery = $pdo->prepare($query);
            $saleQuery->execute($queryParams);
            $sales = $saleQuery->fetchAll();
            if ($sales) {
                $saleIds = array_map(static function ($sale) {
                    return (int) $sale['id'];
                }, $sales);
                $placeholders = implode(',', array_fill(0, count($saleIds), '?'));
                $items = $pdo->prepare(
                    'SELECT sale_id, brand, model, size, ramoneado, quantity, unit_price FROM sale_items WHERE sale_id IN (' . $placeholders . ') ORDER BY sale_id DESC, id'
                );
                $items->execute($saleIds);
                $salesById = [];
                foreach ($sales as &$sale) {
                    $sale['items_summary'] = [];
                    $sale['pair_details'] = [];
                    $salesById[(int) $sale['id']] = &$sale;
                }
                unset($sale);

                foreach ($items->fetchAll() as $item) {
                    $saleId = (int) $item['sale_id'];
                    if (!isset($salesById[$saleId])) {
                        continue;
                    }
                    $key = $item['brand'] . "\0" . $item['model'] . "\0" . $item['unit_price'];
                    if (!isset($salesById[$saleId]['items_summary'][$key])) {
                        $salesById[$saleId]['items_summary'][$key] = [
                            'brand' => $item['brand'],
                            'model' => $item['model'],
                            'unit_price' => $item['unit_price'],
                            'quantity' => 0,
                            'subtotal' => 0,
                        ];
                    }
                    $salesById[$saleId]['items_summary'][$key]['quantity'] += (int) $item['quantity'];
                    $salesById[$saleId]['items_summary'][$key]['subtotal'] +=
                        (int) $item['quantity'] * money_to_cents((string) $item['unit_price']);
                    $salesById[$saleId]['pair_details'][] = $item;
                }
                foreach ($sales as &$sale) {
                    $sale['items_summary'] = array_values($sale['items_summary']);
                }
                unset($sale);
            }

            if ($liquidatedPage) {
                foreach ($sales as $sale) {
                    $day = $sale['liquidated_at'] === null
                        ? 'undated'
                        : date('Y-m-d', strtotime($sale['liquidated_at']));
                    if (!isset($liquidationDays[$day])) {
                        $liquidationDays[$day] = [
                            'order_count' => 0,
                            'pair_count' => 0,
                            'collected_cents' => 0,
                            'net_profit_cents' => 0,
                        ];
                    }
                    $liquidationDays[$day]['order_count']++;
                    if ($sale['status'] === 'sold') {
                        $liquidationDays[$day]['collected_cents'] += money_to_cents((string) $sale['total_collected']);
                        $liquidationDays[$day]['net_profit_cents'] += money_to_cents((string) $sale['net_profit']);
                        foreach ($sale['items_summary'] as $item) {
                            $liquidationDays[$day]['pair_count'] += (int) $item['quantity'];
                        }
                    } elseif ($sale['status'] === 'returned_unpaid_delivery') {
                        $liquidationDays[$day]['net_profit_cents'] -= money_to_cents((string) $sale['delivery_cost']);
                    }
                }
            } else {
                foreach ($sales as $sale) {
                    $day = date('Y-m-d', strtotime($sale['created_at']));
                    if (!isset($saleDays[$day])) {
                        $saleDays[$day] = ['order_count' => 0];
                    }
                    $saleDays[$day]['order_count']++;
                }
            }
        }
    } catch (PDOException $exception) {
        $databaseError = $exception->getMessage();
    }
}

require __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow"><?= $liquidatedPage ? 'LIQUIDACIONES' : 'HISTORIAL' ?></p>
        <h1><?= e($pageTitle) ?></h1>
        <p><?= $liquidatedPage ? 'Comandas liquidadas. También permanecen en la página Comandas.' : 'Consulta el detalle, domicilio, totales y estado de liquidación de cada venta.' ?></p>
    </div>
    <a class="button button-primary" href="crear_comanda.php">+ Crear comanda</a>
</section>

<?php if ($liquidatedPage): ?>
    <section class="stats-grid liquidation-summary">
        <article class="stat-card">
            <span>Ganancia neta acumulada</span>
            <strong><?= e(format_liquidation_money(money_to_cents((string) $liquidationOverview['net_profit']))) ?></strong>
        </article>
        <article class="stat-card">
            <span>Pérdidas por devoluciones sin domicilio</span>
            <strong><?= e(format_liquidation_money(-money_to_cents((string) $liquidationOverview['delivery_losses']))) ?></strong>
        </article>
    </section>
<?php endif; ?>

<section class="panel sale-filter-panel">
    <form action="<?= e($pageUrl) ?>" method="get" class="sale-filter-form">
        <label for="sale-filter-by">Filtrar por
            <select id="sale-filter-by" name="filter_by" required>
                <option value="">Selecciona un criterio</option>
                <?php foreach ($filterOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $filterBy === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="sale-filter-value">Valor
            <input id="sale-filter-value" type="<?= $filterBy === 'date' ? 'date' : 'text' ?>" name="filter_value"<?= $filterBy === 'date' ? '' : ' list="sale-filter-statuses"' ?> value="<?= e($filterValue) ?>" maxlength="600" placeholder="Elige un criterio primero" required>
            <datalist id="sale-filter-statuses">
                <?php foreach ($statusLabels as $label): ?>
                    <option value="<?= e($label) ?>">
                <?php endforeach; ?>
                <?php foreach ($liquidationLabels as $label): ?>
                    <option value="<?= e($label) ?>">
                <?php endforeach; ?>
            </datalist>
        </label>
        <div class="sale-filter-actions">
            <button class="button button-primary" type="submit">Buscar</button>
            <?php if ($filterBy !== '' || $filterValue !== ''): ?>
                <a class="text-link" href="<?= e($pageUrl) ?>">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>
    <p class="form-hint">Puedes filtrar por nombre, fecha (selecciona el día en el calendario), número de comanda, estado de venta o liquidación, barrio o celular. Se muestra un máximo de 1000 comandas.</p>
</section>

<?php if ($filterError !== ''): ?>
    <div class="notice notice-error" role="alert"><?= e($filterError) ?></div>
<?php endif; ?>

<?php if (!$sales): ?>
    <section class="panel">
        <p class="empty-state">
            <?php if ($filterBy !== '' || $filterValue !== ''): ?>
                <?= $liquidatedPage ? 'No se encontraron comandas liquidadas con ese filtro.' : 'No se encontraron comandas con ese filtro.' ?>
            <?php elseif ($liquidatedPage): ?>
                Todavía no hay comandas liquidadas.
            <?php else: ?>
                Todavía no hay comandas registradas.
            <?php endif; ?>
        </p>
    </section>
<?php else: ?>
    <div class="sale-day-list">
        <?php $previousDay = null; $dayListOpen = false; ?>
        <?php foreach ($sales as $sale): ?>
            <?php
            $saleStatus = $sale['status'];
            $statusLabel = $statusLabels[$saleStatus] ?? 'Estado desconocido';
            $statusClass = $statusClasses[$saleStatus] ?? 'status-cancelled';
            $liquidationStatus = $sale['liquidation_status'];
            $liquidationLabel = $liquidationLabels[$liquidationStatus] ?? 'Estado desconocido';
            $liquidationClass = $liquidationClasses[$liquidationStatus] ?? 'status-pending-settlement';
            if ($saleStatus === 'sold') {
                $effectiveProfit = (string) $sale['net_profit'];
            } elseif ($saleStatus === 'returned_unpaid_delivery') {
                $effectiveProfit = cents_to_decimal(-money_to_cents((string) $sale['delivery_cost']));
            } else {
                $effectiveProfit = '0.00';
            }
            ?>
            <?php
            $day = $liquidatedPage
                ? ($sale['liquidated_at'] === null ? 'undated' : date('Y-m-d', strtotime($sale['liquidated_at'])))
                : date('Y-m-d', strtotime($sale['created_at']));
            ?>
            <?php if ($previousDay !== $day): ?>
                <?php if ($dayListOpen): ?></div></section><?php endif; ?>
                <?php $daySummary = $liquidatedPage ? $liquidationDays[$day] : $saleDays[$day]; ?>
                <section class="sale-day-group">
                    <header class="sale-day-header">
                        <h2>
                            <?php if ($liquidatedPage): ?>
                                <?= $day === 'undated' ? 'Fecha de liquidación no registrada' : 'Liquidación del ' . e(date('d/m/Y', strtotime($day))) ?>
                            <?php else: ?>
                                Comandas del <?= e(date('d/m/Y', strtotime($day))) ?>
                            <?php endif; ?>
                        </h2>
                        <div class="sale-day-stats">
                            <?php if ($liquidatedPage): ?>
                                <span><?= (int) $daySummary['order_count'] ?> pedidos</span>
                                <span><?= (int) $daySummary['pair_count'] ?> pares vendidos</span>
                                <span>Recaudado: <?= e(format_liquidation_money($daySummary['collected_cents'])) ?></span>
                                <strong>Ganancia neta: <?= e(format_liquidation_money($daySummary['net_profit_cents'])) ?></strong>
                            <?php else: ?>
                                <span><?= (int) $daySummary['order_count'] ?> comandas</span>
                            <?php endif; ?>
                        </div>
                    </header>
                    <div class="invoice-list">
                <?php $previousDay = $day; $dayListOpen = true; ?>
            <?php endif; ?>
            <article class="invoice-card">
                <div class="invoice-header">
                    <div>
                        <p class="eyebrow">COMANDA #<?= (int) $sale['id'] ?></p>
                        <h2><?= e(date('d/m/Y H:i', strtotime($sale['created_at']))) ?></h2>
                        <span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                        <span class="status-badge <?= e($liquidationClass) ?>"><?= e($liquidationLabel) ?></span>
                    </div>
                    <div class="invoice-actions">
                        <form action="actions.php" method="post" class="status-form">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="update_sale_status">
                            <input type="hidden" name="sale_id" value="<?= (int) $sale['id'] ?>">
                            <label class="status-select-label">Estado
                                <select name="status" aria-label="Estado de la comanda #<?= (int) $sale['id'] ?>">
                                    <?php foreach ($statusLabels as $value => $label): ?>
                                        <option value="<?= e($value) ?>"<?= $saleStatus === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="button button-outline button-small" type="submit">Guardar estado</button>
                        </form>
                        <form action="actions.php" method="post" class="status-form">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="update_liquidation_status">
                            <input type="hidden" name="sale_id" value="<?= (int) $sale['id'] ?>">
                            <input type="hidden" name="return_page" value="<?= e($pageUrl) ?>">
                            <label class="status-select-label">Estado de liquidación
                                <select name="liquidation_status" aria-label="Estado de liquidación de la comanda #<?= (int) $sale['id'] ?>">
                                    <?php foreach ($liquidationLabels as $value => $label): ?>
                                        <option value="<?= e($value) ?>"<?= $liquidationStatus === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="button button-outline button-small" type="submit">Guardar liquidación</button>
                        </form>
                        <form action="actions.php" method="post" onsubmit="return confirm('¿Eliminar esta comanda? Esta acción no se puede deshacer.');">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete_sale">
                            <input type="hidden" name="sale_id" value="<?= (int) $sale['id'] ?>">
                            <button class="text-link danger-link" type="submit">Eliminar</button>
                        </form>
                    </div>
                </div>
                <?php if ($saleStatus !== 'sold'): ?>
                    <p class="status-note">
                        <?php if ($saleStatus === 'returned_unpaid_delivery'): ?>
                            No se cuenta como venta; se descuenta el costo real del domicilio de la ganancia.
                        <?php else: ?>
                            Esta comanda no suma ingresos ni ganancia a las estadísticas.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <div class="delivery-details">
                    <span><strong>Comprador:</strong> <?= $sale['buyer_name'] !== '' ? e($sale['buyer_name']) : '—' ?></span>
                    <span><strong>Celular:</strong> <?= $sale['buyer_phone'] !== '' ? e($sale['buyer_phone']) : '—' ?></span>
                    <span><strong>Dirección:</strong> <?= e($sale['address']) ?></span>
                    <span><strong>Barrio:</strong> <?= e($sale['neighborhood']) ?></span>
                </div>
                <div class="table-wrap">
                    <table class="invoice-table">
                        <thead><tr><th>Cantidad</th><th>Modelo</th><th>Precio</th></tr></thead>
                        <tbody>
                        <?php foreach ($sale['items_summary'] as $item): ?>
                            <tr>
                                <td><?= (int) $item['quantity'] ?></td>
                                <td>
                                    <strong><?= e(trim($item['brand'] . ' ' . $item['model'])) ?></strong>
                                    <small class="pair-notes">
                                        <?php
                                        $notes = [];
                                        foreach ($sale['pair_details'] as $detail) {
                                            if ($detail['brand'] === $item['brand'] && $detail['model'] === $item['model'] && $detail['unit_price'] === $item['unit_price']) {
                                                $notes[] = 'Talla ' . $detail['size'] . ' · Ramoneado: ' . ((int) $detail['ramoneado'] === 1 ? 'Sí' : 'No');
                                            }
                                        }
                                        echo e(implode(' | ', $notes));
                                        ?>
                                    </small>
                                </td>
                                <td>$<?= e($item['unit_price']) ?> c/u <small>Subtotal: $<?= e(cents_to_decimal($item['subtotal'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="invoice-totals">
                    <div><span>Total de venta</span><strong>$<?= e($sale['total']) ?></strong></div>
                    <div><span>Domicilio cobrado</span><strong>$<?= e($sale['delivery_fee']) ?></strong></div>
                    <div><span>Total de venta + domicilio</span><strong>$<?= e(cents_to_decimal(money_to_cents((string) $sale['total']) + money_to_cents((string) $sale['delivery_fee']))) ?></strong></div>
                    <div><span>Total cobrado</span><strong>$<?= e($sale['total_collected']) ?></strong></div>
                    <div><span>Costo real del domicilio</span><strong>$<?= e($sale['delivery_cost']) ?></strong></div>
                    <div class="invoice-profit"><span>Ganancia neta</span><strong>$<?= e($effectiveProfit) ?></strong></div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($dayListOpen): ?></div></section><?php endif; ?>
    </div>
    <p class="form-hint page-end-note">Mostrando <?= count($sales) ?> comandas (máximo 1000).</p>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
