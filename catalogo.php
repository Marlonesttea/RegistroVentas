<?php
declare(strict_types=1);

require __DIR__ . '/app.php';

$pageTitle = 'Catálogo';
$activePage = 'catalogo';
$products = [];
$editProduct = null;
$editPhotos = [];

if ($pdo) {
    try {
        $products = $pdo->query(
            'SELECT p.*, (SELECT path FROM product_photos WHERE product_id = p.id ORDER BY id LIMIT 1) AS cover_photo, (SELECT COUNT(*) FROM product_photos WHERE product_id = p.id) AS photo_count FROM products p ORDER BY p.created_at DESC, p.id DESC'
        )->fetchAll();
        $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
        if ($editId) {
            $statement = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $statement->execute([$editId]);
            $editProduct = $statement->fetch() ?: null;
            if ($editProduct) {
                $photoStatement = $pdo->prepare('SELECT id, path FROM product_photos WHERE product_id = ? ORDER BY id');
                $photoStatement->execute([$editId]);
                $editPhotos = $photoStatement->fetchAll();
            }
        }
    } catch (PDOException $exception) {
        $databaseError = $exception->getMessage();
    }
}

require __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">MODELOS</p><h1>Catálogo</h1><p>Administra los modelos, sus fotos y precios de venta.</p></div>
    <a class="button button-primary" href="#formulario-modelo"><?= $editProduct ? 'Editar modelo' : '+ Agregar modelo' ?></a>
</section>

<section class="panel" id="formulario-modelo">
    <div class="section-heading">
        <div><p class="eyebrow">INVENTARIO</p><h2><?= $editProduct ? 'Editar modelo' : 'Agregar modelo' ?></h2></div>
        <?php if ($editProduct): ?><a class="text-link" href="catalogo.php#formulario-modelo">Cancelar</a><?php endif; ?>
    </div>
    <form action="actions.php" method="post" enctype="multipart/form-data" class="stack-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="id" value="<?= (int) ($editProduct['id'] ?? 0) ?>">
        <div class="field-row">
            <label>Marca <span class="optional">(opcional)</span>
                <input type="text" name="brand" maxlength="100" value="<?= e($editProduct['brand'] ?? '') ?>" placeholder="Ej. Marca">
            </label>
            <label>Nombre del modelo *
                <input type="text" name="model" maxlength="150" value="<?= e($editProduct['model'] ?? '') ?>" required placeholder="Ej. Modelo clásico">
            </label>
        </div>
        <div class="field-row">
            <label>Costo de compra por par *
                <input type="number" name="cost_price" min="0" max="99999999.99" step="0.01" value="<?= e($editProduct['cost_price'] ?? '') ?>" required placeholder="0.00">
            </label>
            <label>Precio de venta por par *
                <input type="number" name="sale_price" min="0.01" max="99999999.99" step="0.01" value="<?= e($editProduct['sale_price'] ?? '') ?>" required placeholder="0.00">
            </label>
        </div>
        <label>Descripción <span class="optional">(opcional)</span>
            <textarea name="description" rows="2" maxlength="1000" placeholder="Color u otros detalles"><?= e($editProduct['description'] ?? '') ?></textarea>
        </label>
        <label>Fotos <span class="optional">(hasta 8 por vez, máximo 5 MB cada una)</span>
            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
        </label>
        <button class="button button-primary" type="submit"><?= $editProduct ? 'Guardar cambios' : 'Agregar modelo' ?></button>
    </form>
    <?php if ($editPhotos): ?>
        <div class="photo-list">
            <?php foreach ($editPhotos as $photo): ?>
                <div class="photo-item">
                    <img src="<?= e($photo['path']) ?>" alt="Foto del modelo">
                    <form action="actions.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="delete_photo">
                        <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                        <button class="text-link danger-link" type="submit">Eliminar foto</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="section-heading">
        <div><p class="eyebrow">MODELOS REGISTRADOS</p><h2>Zapatos <span class="count"><?= count($products) ?></span></h2></div>
    </div>
    <?php if (!$products): ?>
        <p class="empty-state">Todavía no hay modelos. Agrega el primero desde el formulario.</p>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <article class="product-card">
                    <?php if ($product['cover_photo']): ?>
                        <img class="product-card-image" src="<?= e($product['cover_photo']) ?>" alt="<?= e(trim($product['brand'] . ' ' . $product['model'])) ?>">
                    <?php else: ?>
                        <div class="product-card-image placeholder">Sin foto</div>
                    <?php endif; ?>
                    <div class="product-card-body">
                        <p class="eyebrow"><?= e($product['brand'] ?: 'Modelo') ?></p>
                        <h3><?= e($product['model']) ?></h3>
                        <?php if ($product['description'] !== ''): ?><p class="product-description"><?= e($product['description']) ?></p><?php endif; ?>
                        <strong class="product-price">$<?= e($product['sale_price']) ?></strong>
                        <span class="photo-count"><?= (int) $product['photo_count'] ?> fotos</span>
                        <div class="actions">
                            <a class="text-link" href="catalogo.php?edit=<?= (int) $product['id'] ?>#formulario-modelo">Editar</a>
                            <form action="actions.php" method="post" onsubmit="return confirm('¿Eliminar este modelo? Las ventas anteriores se conservarán.');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                <button class="text-link danger-link" type="submit">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
