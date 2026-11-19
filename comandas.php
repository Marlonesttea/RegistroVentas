<?php
declare(strict_types=1);

require __DIR__ . '/app.php';

$pageTitle = 'Comandas';
$activePage = 'comandas';
$sales = [];
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
$filterOptions = [
    'buyer_name' => 'Nombre del comprador',
    'date' => 'Fecha',
    'number' => 'Número de comanda',
    'status' => 'Estado de venta',
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

if ($pdo && $filterError === '') {
    try {
        $query = 'SELECT * FROM sales';
        if ($filterClause !== '') {
            $query .= ' WHERE ' . $filterClause;
        }
        $query .= ' ORDER BY created_at DESC, id DESC LIMIT 1000';
        $saleQuery = $pdo->prepare($query);
        $saleQuery->execute($filterParams);
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
    } catch (PDOException $exception) {
        $databaseError = $exception->getMessage();
    }
}

require __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">HISTORIAL</p><h1>Comandas</h1><p>Consulta el detalle, domicilio, totales y ganancia de cada venta.</p></div>
    <a class="button button-primary" href="crear_comanda.php">+ Crear comanda</a>
</section>

<section class="panel sale-filter-panel">
    <form action="comandas.php" method="get" class="sale-filter-form">
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
            </datalist>
        </label>
        <div class="sale-filter-actions">
            <button class="button button-primary" type="submit">Buscar</button>
            <?php if ($filterBy !== '' || $filterValue !== ''): ?>
                <a class="text-link" href="comandas.php">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>
    <p class="form-hint">Puedes filtrar por nombre, fecha (selecciona el día en el calendario), número de comanda, estado, barrio o celular. Se muestra un máximo de 1000 comandas.</p>
</section>

<?php if ($filterError !== ''): ?>
    <div class="notice notice-error" role="alert"><?= e($filterError) ?></div>
<?php endif; ?>

<?php if (!$sales): ?>
    <section class="panel">
        <p class="empty-state">
            <?php if ($filterBy !== '' || $filterValue !== ''): ?>
                No se encontraron comandas con ese filtro.
            <?php else: ?>
                Todavía no hay comandas registradas.
            <?php endif; ?>
        </p>
    </section>
<?php else: ?>
    <div class="invoice-list">
        <?php foreach ($sales as $sale): ?>
            <?php
            $saleStatus = $sale['status'];
            $statusLabel = $statusLabels[$saleStatus] ?? 'Estado desconocido';
            $statusClass = $statusClasses[$saleStatus] ?? 'status-cancelled';
            if ($saleStatus === 'sold') {
                $effectiveProfit = (string) $sale['net_profit'];
            } elseif ($saleStatus === 'returned_unpaid_delivery') {
                $effectiveProfit = cents_to_decimal(-money_to_cents((string) $sale['delivery_cost']));
            } else {
                $effectiveProfit = '0.00';
            }
            ?>
            <article class="invoice-card">
                <div class="invoice-header">
                    <div>
                        <p class="eyebrow">COMANDA #<?= (int) $sale['id'] ?></p>
                        <h2><?= e(date('d/m/Y H:i', strtotime($sale['created_at']))) ?></h2>
                        <span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
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
    </div>
    <p class="form-hint page-end-note">Mostrando <?= count($sales) ?> comandas (máximo 1000).</p>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
