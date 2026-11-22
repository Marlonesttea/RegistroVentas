<?php
declare(strict_types=1);

function parse_liquidation_sale_ids($input): array
{
    if (!is_array($input) || count($input) < 1 || count($input) > 1000) {
        throw new RuntimeException('Selecciona entre 1 y 1000 comandas para calcular.');
    }

    $saleIds = [];
    foreach ($input as $value) {
        $saleId = filter_var($value, FILTER_VALIDATE_INT);
        if ($saleId === false || $saleId < 1) {
            throw new RuntimeException('La selección contiene un número de comanda inválido.');
        }
        $saleIds[] = $saleId;
    }

    if (count($saleIds) !== count(array_unique($saleIds))) {
        throw new RuntimeException('La selección contiene comandas repetidas.');
    }

    return $saleIds;
}

function load_pending_liquidation_sales(PDO $pdo, ?array $saleIds = null, bool $forUpdate = false): array
{
    $query = 'SELECT id, status, total, total_collected, delivery_cost, net_profit, buyer_name, buyer_phone, created_at
        FROM sales WHERE liquidation_status = ?';
    $parameters = ['pending'];

    if ($saleIds !== null) {
        if (!$saleIds) {
            throw new RuntimeException('Selecciona al menos una comanda.');
        }
        $query .= ' AND id IN (' . implode(',', array_fill(0, count($saleIds), '?')) . ')';
        $parameters = array_merge($parameters, $saleIds);
    }

    $query .= ' ORDER BY created_at DESC, id DESC';
    if ($forUpdate) {
        $query .= ' FOR UPDATE';
    }

    $statement = $pdo->prepare($query);
    $statement->execute($parameters);
    $sales = $statement->fetchAll();

    if ($saleIds !== null && count($sales) !== count($saleIds)) {
        throw new RuntimeException('Una o más comandas ya fueron liquidadas o no existen. Actualiza la página e inténtalo de nuevo.');
    }
    if (!$sales) {
        return [];
    }

    $ids = array_map(static function ($sale) {
        return (int) $sale['id'];
    }, $sales);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $items = $pdo->prepare(
        'SELECT sale_id, brand, model, SUM(quantity) AS quantity
        FROM sale_items WHERE sale_id IN (' . $placeholders . ')
        GROUP BY sale_id, brand, model ORDER BY sale_id, brand, model'
    );
    $items->execute($ids);
    $itemsBySale = [];
    $pairCounts = [];
    foreach ($items->fetchAll() as $item) {
        $saleId = (int) $item['sale_id'];
        $quantity = (int) $item['quantity'];
        $itemsBySale[$saleId][] = [
            'name' => trim($item['brand'] . ' ' . $item['model']),
            'quantity' => $quantity,
        ];
        $pairCounts[$saleId] = ($pairCounts[$saleId] ?? 0) + $quantity;
    }

    foreach ($sales as &$sale) {
        $sale['items_summary'] = $itemsBySale[(int) $sale['id']] ?? [];
        $sale['pair_count'] = $sale['status'] === 'sold'
            ? ($pairCounts[(int) $sale['id']] ?? 0)
            : 0;
    }
    unset($sale);

    return $sales;
}

function calculate_liquidation_summary(array $sales): array
{
    $summary = [
        'rows' => [],
        'order_count' => count($sales),
        'pair_count' => 0,
        'collected_cents' => 0,
        'sales_cents' => 0,
        'delivery_losses_cents' => 0,
        'net_profit_cents' => 0,
    ];

    foreach ($sales as $sale) {
        $collected = $sale['status'] === 'sold' ? money_to_cents((string) $sale['total_collected']) : 0;
        $salesTotal = $sale['status'] === 'sold' ? money_to_cents((string) $sale['total']) : 0;
        $deliveryLoss = $sale['status'] === 'returned_unpaid_delivery'
            ? money_to_cents((string) $sale['delivery_cost'])
            : 0;
        $netProfit = $sale['status'] === 'sold'
            ? money_to_cents((string) $sale['net_profit'])
            : -$deliveryLoss;
        $pairCount = $sale['status'] === 'sold' ? (int) $sale['pair_count'] : 0;

        $sale['collected_cents'] = $collected;
        $sale['sales_cents'] = $salesTotal;
        $sale['delivery_loss_cents'] = $deliveryLoss;
        $sale['net_profit_cents'] = $netProfit;
        $sale['pair_count'] = $pairCount;
        $summary['rows'][] = $sale;
        $summary['pair_count'] += $pairCount;
        $summary['collected_cents'] += $collected;
        $summary['sales_cents'] += $salesTotal;
        $summary['delivery_losses_cents'] += $deliveryLoss;
        $summary['net_profit_cents'] += $netProfit;
    }

    return $summary;
}

function format_liquidation_money(int $cents): string
{
    $amount = cents_to_decimal(abs($cents));
    return ($cents < 0 ? '-$' : '$') . $amount;
}
