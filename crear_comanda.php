<?php
declare(strict_types=1);

require __DIR__ . '/app.php';

$pageTitle = 'Crear comanda';
$activePage = 'crear';
$products = [];

if ($pdo) {
    try {
        $products = $pdo->query('SELECT id, brand, model, sale_price FROM products ORDER BY brand, model')->fetchAll();
    } catch (PDOException $exception) {
        $databaseError = $exception->getMessage();
    }
}

require __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">NUEVA VENTA</p><h1>Crear comanda</h1><p>Registra cada par y los datos del domicilio.</p></div>
    <a class="button button-outline" href="comandas.php">Ver comandas</a>
</section>

<?php if (!$products): ?>
    <section class="panel empty-catalog">
        <p class="empty-state">Primero agrega al menos un modelo al catálogo para crear una comanda.</p>
        <a class="button button-primary" href="catalogo.php">Ir al catálogo</a>
    </section>
<?php else: ?>
    <section class="panel order-panel">
        <div class="section-heading">
            <div><p class="eyebrow">DETALLE DEL PEDIDO</p><h2>Datos por cada par</h2></div>
        </div>
        <form action="actions.php" method="post" class="stack-form" id="sale-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create_sale">
            <label class="quantity-count">Cantidad de zapatos en el pedido
                <input id="pair-count" type="number" name="pair_count" min="1" max="30" value="1" required>
            </label>
            <div id="pair-lines">
                <div class="pair-line" data-pair-index="0">
                    <div class="pair-heading"><strong>Par 1</strong></div>
                    <label>Nombre del zapato
                        <select name="pair_product_id[]" class="pair-product" required>
                            <option value="" data-price="0">Selecciona un modelo</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= (int) $product['id'] ?>" data-price="<?= e($product['sale_price']) ?>"><?= e(trim($product['brand'] . ' ' . $product['model'])) ?> · $<?= e($product['sale_price']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <fieldset class="size-field">
                        <legend>Talla</legend>
                        <div class="size-options">
                            <?php for ($size = 34; $size <= 44; $size++): ?>
                                <label class="size-option"><input type="radio" name="pair_size[0]" value="<?= $size ?>" required><span><?= $size ?></span></label>
                            <?php endfor; ?>
                        </div>
                    </fieldset>
                    <label>Ramoneado
                        <select name="pair_ramoneado[]" required>
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>
                    </label>
                </div>
            </div>
            <template id="pair-template">
                <div class="pair-line" data-pair-index="__INDEX__">
                    <div class="pair-heading"><strong>Par __NUMBER__</strong></div>
                    <label>Nombre del zapato
                        <select name="pair_product_id[]" class="pair-product" required>
                            <option value="" data-price="0">Selecciona un modelo</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= (int) $product['id'] ?>" data-price="<?= e($product['sale_price']) ?>"><?= e(trim($product['brand'] . ' ' . $product['model'])) ?> · $<?= e($product['sale_price']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <fieldset class="size-field">
                        <legend>Talla</legend>
                        <div class="size-options">
                            <?php for ($size = 34; $size <= 44; $size++): ?>
                                <label class="size-option"><input type="radio" name="pair_size[__INDEX__]" value="<?= $size ?>" required><span><?= $size ?></span></label>
                            <?php endfor; ?>
                        </div>
                    </fieldset>
                    <label>Ramoneado
                        <select name="pair_ramoneado[]" required>
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>
                    </label>
                </div>
            </template>
            <div class="field-row">
                <label>Lo cobrado del domicilio
                    <input id="delivery-fee" type="number" name="delivery_fee" min="0" max="99999999.99" step="0.01" value="0.00" required>
                </label>
                <label>Costo real del domicilio
                    <input id="delivery-cost" type="number" name="delivery_cost" min="0" max="99999999.99" step="0.01" value="0.00" required>
                </label>
            </div>
            <div class="field-row">
                <label>Nombre de la persona que compra
                    <input type="text" name="buyer_name" maxlength="150" autocomplete="name" required>
                </label>
                <label>Número de celular
                    <input type="tel" name="buyer_phone" maxlength="30" autocomplete="tel" pattern="[0-9+().\-\s]{7,30}" required>
                </label>
            </div>
            <div class="field-row">
                <label>Dirección de domicilio
                    <input type="text" name="address" maxlength="255" required>
                </label>
                <label>Barrio
                    <input type="text" name="neighborhood" maxlength="150" required>
                </label>
            </div>
            <div class="order-totals">
                <div><span>Total de venta</span><strong id="sale-subtotal">$0.00</strong></div>
                <label>Total de venta + domicilio
                    <input id="total-collected" type="number" name="total_collected" min="0.01" max="9999999999.99" step="0.01" value="0.00" required>
                </label>
                <button class="text-link" type="button" id="reset-total">Recalcular total</button>
            </div>
            <p class="form-hint">El total se calcula con el precio de los modelos y el domicilio; puedes ajustarlo. La ganancia resta el costo de compra de cada par y el costo real del domicilio. Ramoneado es solo informativo.</p>
            <button class="button button-primary" type="submit">Guardar comanda</button>
        </form>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
