<?php
declare(strict_types=1);

require __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

$action = (string) ($_POST['action'] ?? '');
$redirectPage = in_array($action, ['save_product', 'delete_product', 'delete_photo'], true)
    ? 'catalogo.php'
    : (in_array($action, ['create_sale', 'update_sale_status', 'delete_sale'], true) ? 'comandas.php' : 'index.php');

try {
    if (!$pdo) {
        throw new RuntimeException('No se pudo conectar con la base de datos.');
    }
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        throw new RuntimeException('La sesión del formulario venció. Actualiza la página e inténtalo de nuevo.');
    }

    if ($action === 'save_product') {
        $id = (int) ($_POST['id'] ?? 0);
        $brand = trim((string) ($_POST['brand'] ?? ''));
        $model = trim((string) ($_POST['model'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $cost = money_to_cents((string) ($_POST['cost_price'] ?? ''));
        $price = money_to_cents((string) ($_POST['sale_price'] ?? ''));

        if ($model === '' || $cost > 9999999999 || $price <= 0 || $price > 9999999999) {
            throw new RuntimeException('El nombre del modelo y un precio de venta mayor que cero son obligatorios.');
        }

        $newPhotos = prepare_photo_uploads($_FILES['photos'] ?? []);
        try {
            $pdo->beginTransaction();
            if ($id > 0) {
                $statement = $pdo->prepare(
                    'UPDATE products SET brand = ?, model = ?, description = ?, cost_price = ?, sale_price = ? WHERE id = ?'
                );
                $statement->execute([$brand, $model, $description, cents_to_decimal($cost), cents_to_decimal($price), $id]);
                if ($statement->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT id FROM products WHERE id = ?');
                    $exists->execute([$id]);
                    if (!$exists->fetch()) {
                        throw new RuntimeException('El modelo que intentas editar ya no existe.');
                    }
                }
                $message = 'Modelo actualizado correctamente.';
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO products (brand, model, description, cost_price, sale_price) VALUES (?, ?, ?, ?, ?)'
                );
                $statement->execute([$brand, $model, $description, cents_to_decimal($cost), cents_to_decimal($price)]);
                $id = (int) $pdo->lastInsertId();
                $message = 'Modelo agregado correctamente.';
            }

            if ($newPhotos) {
                $photoStatement = $pdo->prepare('INSERT INTO product_photos (product_id, path) VALUES (?, ?)');
                foreach ($newPhotos as $path) {
                    $photoStatement->execute([$id, $path]);
                }
            }
            $pdo->commit();
            set_flash('success', $message);
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            remove_photo_files($newPhotos);
            if ($exception->getCode() === '23000') {
                throw new RuntimeException('Ya existe un registro con esa marca y ese modelo.');
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            remove_photo_files($newPhotos);
            throw $exception;
        }
    } elseif ($action === 'delete_product') {
        $id = (int) ($_POST['id'] ?? 0);
        $statement = $pdo->prepare('SELECT path FROM product_photos WHERE product_id = ?');
        $statement->execute([$id]);
        $photos = $statement->fetchAll(PDO::FETCH_COLUMN);
        $delete = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $delete->execute([$id]);
        if ($delete->rowCount() === 0) {
            throw new RuntimeException('El modelo ya no existe.');
        }
        remove_photo_files($photos);
        set_flash('success', 'Modelo eliminado. Las ventas anteriores se conservaron.');
    } elseif ($action === 'delete_photo') {
        $photoId = (int) ($_POST['photo_id'] ?? 0);
        $statement = $pdo->prepare('SELECT path FROM product_photos WHERE id = ?');
        $statement->execute([$photoId]);
        $path = $statement->fetchColumn();
        if ($path === false) {
            throw new RuntimeException('La foto ya no existe.');
        }
        $delete = $pdo->prepare('DELETE FROM product_photos WHERE id = ?');
        $delete->execute([$photoId]);
        remove_photo_files([$path]);
        set_flash('success', 'Foto eliminada.');
    } elseif ($action === 'create_sale') {
        $pairCount = filter_var($_POST['pair_count'] ?? null, FILTER_VALIDATE_INT);
        $productIds = $_POST['pair_product_id'] ?? [];
        $sizes = $_POST['pair_size'] ?? [];
        $ramoneadoValues = $_POST['pair_ramoneado'] ?? [];
        if ($pairCount === false || $pairCount < 1 || $pairCount > 30) {
            throw new RuntimeException('La cantidad debe estar entre 1 y 30 pares.');
        }
        if (!is_array($productIds) || !is_array($sizes) || !is_array($ramoneadoValues) ||
            count($productIds) !== $pairCount || count($sizes) !== $pairCount || count($ramoneadoValues) !== $pairCount) {
            throw new RuntimeException('Completa modelo, talla y Ramoneado para cada par.');
        }

        $address = trim((string) ($_POST['address'] ?? ''));
        $neighborhood = trim((string) ($_POST['neighborhood'] ?? ''));
        $buyerName = trim((string) ($_POST['buyer_name'] ?? ''));
        $buyerPhone = trim((string) ($_POST['buyer_phone'] ?? ''));
        $phoneDigits = preg_replace('/\D+/', '', $buyerPhone);
        if ($address === '' || $neighborhood === '') {
            throw new RuntimeException('La dirección y el barrio son obligatorios.');
        }
        if ($buyerName === '' || $buyerPhone === '' || strlen($buyerPhone) > 30 ||
            !preg_match('/^[0-9+().\-\s]+$/', $buyerPhone) || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 20) {
            throw new RuntimeException('Escribe el nombre del comprador y un celular válido.');
        }

        $deliveryFee = money_to_cents((string) ($_POST['delivery_fee'] ?? '0'));
        $deliveryCost = money_to_cents((string) ($_POST['delivery_cost'] ?? '0'));
        if ($deliveryFee > 9999999999 || $deliveryCost > 9999999999) {
            throw new RuntimeException('El valor del domicilio no puede superar 99,999,999.99.');
        }
        $collectedInput = trim((string) ($_POST['total_collected'] ?? ''));
        $totalCollectedInput = $collectedInput === '' ? null : money_to_cents($collectedInput);
        $pairs = [];
        $products = [];
        $pdo->beginTransaction();
        try {
            $selectProduct = $pdo->prepare('SELECT id, brand, model, cost_price, sale_price FROM products WHERE id = ? FOR UPDATE');
            for ($index = 0; $index < $pairCount; $index++) {
                $productId = filter_var($productIds[$index] ?? null, FILTER_VALIDATE_INT);
                $size = (string) ($sizes[$index] ?? '');
                $ramoneado = (string) ($ramoneadoValues[$index] ?? '');
                if ($productId === false || $productId < 1 || !in_array($size, ['34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44'], true) ||
                    !in_array($ramoneado, ['0', '1'], true)) {
                    throw new RuntimeException('Revisa el modelo, la talla y el dato de Ramoneado de cada par.');
                }

                if (!isset($products[$productId])) {
                    $selectProduct->execute([$productId]);
                    $product = $selectProduct->fetch();
                    if (!$product) {
                        throw new RuntimeException('Uno de los modelos seleccionados ya no existe.');
                    }
                    $products[$productId] = $product;
                }
                $pairs[] = [
                    'product' => $products[$productId],
                    'size' => $size,
                    'ramoneado' => (int) $ramoneado,
                ];
            }

            $subtotalCents = 0;
            $costCents = 0;
            foreach ($pairs as $pair) {
                $subtotalCents += money_to_cents((string) $pair['product']['sale_price']);
                $costCents += money_to_cents((string) $pair['product']['cost_price']);
            }
            $calculatedTotal = $subtotalCents + $deliveryFee;
            $totalCollected = $totalCollectedInput === null ? $calculatedTotal : $totalCollectedInput;
            if ($totalCollected <= 0) {
                throw new RuntimeException('El total cobrado debe ser mayor que cero.');
            }

            $grossProfit = $subtotalCents - $costCents;
            $netProfit = $totalCollected - $costCents - $deliveryCost;
            $insertSale = $pdo->prepare(
                'INSERT INTO sales (total, gross_profit, delivery_fee, delivery_cost, total_collected, net_profit, buyer_name, buyer_phone, address, neighborhood) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insertSale->execute([
                cents_to_decimal($subtotalCents),
                cents_to_decimal($grossProfit),
                cents_to_decimal($deliveryFee),
                cents_to_decimal($deliveryCost),
                cents_to_decimal($totalCollected),
                cents_to_decimal($netProfit),
                $buyerName,
                $buyerPhone,
                $address,
                $neighborhood,
            ]);
            $saleId = (int) $pdo->lastInsertId();
            $insertItem = $pdo->prepare(
                'INSERT INTO sale_items (sale_id, product_id, brand, model, size, ramoneado, quantity, unit_cost, unit_price) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)'
            );
            foreach ($pairs as $pair) {
                $product = $pair['product'];
                $insertItem->execute([
                    $saleId,
                    $product['id'],
                    $product['brand'],
                    $product['model'],
                    $pair['size'],
                    $pair['ramoneado'],
                    $product['cost_price'],
                    $product['sale_price'],
                ]);
            }
            $pdo->commit();
            set_flash('success', 'Comanda registrada. Total cobrado: $' . cents_to_decimal($totalCollected) . ' | Ganancia neta: $' . cents_to_decimal($netProfit) . '.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    } elseif ($action === 'update_sale_status') {
        $saleId = filter_var($_POST['sale_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        $statusLabels = [
            'sold' => 'Vendido',
            'cancelled' => 'Cancelado',
            'returned_paid_delivery' => 'Devuelto - pagó domicilio',
            'returned_unpaid_delivery' => 'Devuelto - no pagó domicilio',
        ];
        if ($saleId === false || $saleId < 1 || !isset($statusLabels[$status])) {
            throw new RuntimeException('Selecciona un estado válido para la comanda.');
        }
        $update = $pdo->prepare('UPDATE sales SET status = ? WHERE id = ?');
        $update->execute([$status, $saleId]);
        if ($update->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT id FROM sales WHERE id = ?');
            $exists->execute([$saleId]);
            if (!$exists->fetch()) {
                throw new RuntimeException('La comanda ya no existe.');
            }
        }
        set_flash('success', 'Estado actualizado: ' . $statusLabels[$status] . '.');
    } elseif ($action === 'delete_sale') {
        $saleId = (int) ($_POST['sale_id'] ?? 0);
        $delete = $pdo->prepare('DELETE FROM sales WHERE id = ?');
        $delete->execute([$saleId]);
        if ($delete->rowCount() === 0) {
            throw new RuntimeException('La venta ya no existe.');
        }
        set_flash('success', 'Venta eliminada.');
    } else {
        throw new RuntimeException('La operación solicitada no es válida.');
    }
} catch (Throwable $exception) {
    set_flash('error', $exception->getMessage());
}

redirect_to($redirectPage);
