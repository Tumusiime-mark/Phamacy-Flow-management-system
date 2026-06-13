<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_product') {
        $id = (int) ($_POST['id'] ?? 0);
        $payload = [
            trim($_POST['name'] ?? ''),
            $_POST['category_id'] ?: null,
            $_POST['brand_id'] ?: null,
            trim($_POST['dosage'] ?? ''),
            trim($_POST['unit_type'] ?? ''),
            trim($_POST['barcode'] ?? ''),
            (float) ($_POST['cost_price'] ?? 0),
            (float) ($_POST['unit_price'] ?? 0),
            (float) ($_POST['wholesale_price'] ?? 0),
            trim($_POST['manufacturer'] ?? ''),
            trim($_POST['storage_condition'] ?? ''),
            trim($_POST['sell_mode'] ?? 'bulk_only'),
            (int) ($_POST['minimum_stock_level'] ?? 0),
            max(1, (int) ($_POST['bulk_conversion'] ?? 1)),
            isset($_POST['controlled_drug']) ? 1 : 0,
        ];

        if ($id > 0) {
            execute_query(
                'UPDATE products SET
                 name = ?, category_id = ?, brand_id = ?, dosage = ?, unit_type = ?, barcode = ?,
                 cost_price = ?, unit_price = ?, wholesale_price = ?, manufacturer = ?, storage_condition = ?, sell_mode = ?,
                 minimum_stock_level = ?, bulk_conversion = ?, controlled_drug = ?
                 WHERE id = ?',
                array_merge($payload, [$id])
            );
            log_activity('Edited product ' . $payload[0]);
            flash('success', 'Product updated successfully.');
        } else {
            execute_query(
                'INSERT INTO products
                (name, category_id, brand_id, dosage, unit_type, barcode, cost_price, unit_price, wholesale_price, manufacturer, storage_condition, sell_mode, minimum_stock_level, bulk_conversion, controlled_drug, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge($payload, [now()])
            );
            log_activity('Added new product ' . $payload[0]);
            flash('success', 'Product created successfully.');
        }

        redirect('index.php?page=products');
    }

    if ($action === 'delete_product') {
        $id = (int) ($_POST['id'] ?? 0);
        $product = fetch_one('SELECT name FROM products WHERE id = ?', [$id]);
        execute_query('DELETE FROM products WHERE id = ?', [$id]);
        log_activity('Deleted product ' . ($product['name'] ?? 'Unknown'));
        flash('success', 'Product deleted successfully.');
        redirect('index.php?page=products');
    }

    if ($action === 'add_category') {
        execute_query('INSERT INTO categories (name) VALUES (?)', [trim($_POST['category_name'] ?? '')]);
        log_activity('Added product category ' . trim($_POST['category_name'] ?? ''));
        flash('success', 'Category added.');
        redirect('index.php?page=products');
    }

    if ($action === 'add_brand') {
        execute_query('INSERT INTO brands (name) VALUES (?)', [trim($_POST['brand_name'] ?? '')]);
        log_activity('Added brand ' . trim($_POST['brand_name'] ?? ''));
        flash('success', 'Brand added.');
        redirect('index.php?page=products');
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $editing = fetch_one('SELECT * FROM products WHERE id = ?', [(int) $_GET['edit']]);
}

$products = products_with_stock();
$categories = categories();
$brands = brands();
?>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3><?= $editing ? 'Edit Drug / Product' : 'Add Drug / Product' ?></h3>
            <span>Product master data</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <label>
                <span>Drug / Product Name</span>
                <input name="name" required value="<?= h($editing['name'] ?? '') ?>">
            </label>
            <label>
                <span>Drug Category</span>
                <select name="category_id">
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= ((string) ($editing['category_id'] ?? '') === (string) $category['id']) ? 'selected' : '' ?>>
                            <?= h($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Brand Name</span>
                <select name="brand_id">
                    <option value="">Select brand</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= (int) $brand['id'] ?>" <?= ((string) ($editing['brand_id'] ?? '') === (string) $brand['id']) ? 'selected' : '' ?>>
                            <?= h($brand['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Dosage</span>
                <input name="dosage" value="<?= h($editing['dosage'] ?? '') ?>" placeholder="500mg">
            </label>
            <label>
                <span>Units</span>
                <select name="unit_type" required>
                    <?php foreach (['Box', 'Strip', 'Tablet', 'Capsule', 'Pack'] as $unit): ?>
                        <option value="<?= h($unit) ?>" <?= (($editing['unit_type'] ?? '') === $unit) ? 'selected' : '' ?>>
                            <?= h($unit) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Barcode</span>
                <input name="barcode" value="<?= h($editing['barcode'] ?? '') ?>">
            </label>
            <label>
                <span>Cost Price</span>
                <input type="number" step="0.01" name="cost_price" value="<?= h((string) ($editing['cost_price'] ?? '')) ?>" required>
            </label>
            <label>
                <span>Unit Price</span>
                <input type="number" step="0.01" name="unit_price" value="<?= h((string) ($editing['unit_price'] ?? '')) ?>" required>
            </label>
            <label>
                <span>Wholesale Price</span>
                <input type="number" step="0.01" name="wholesale_price" value="<?= h((string) ($editing['wholesale_price'] ?? $editing['unit_price'] ?? '')) ?>" required>
            </label>
            <label>
                <span>Manufacturer Details</span>
                <input name="manufacturer" value="<?= h($editing['manufacturer'] ?? '') ?>">
            </label>
            <label>
                <span>Storage Condition</span>
                <input name="storage_condition" value="<?= h($editing['storage_condition'] ?? '') ?>">
            </label>
            <label>
                <span>Sell Option</span>
                <select name="sell_mode">
                    <option value="bulk_only" <?= (($editing['sell_mode'] ?? '') === 'bulk_only') ? 'selected' : '' ?>>Bulk only</option>
                    <option value="piece_only" <?= (($editing['sell_mode'] ?? '') === 'piece_only') ? 'selected' : '' ?>>Pieces only</option>
                    <option value="bulk_and_piece" <?= (($editing['sell_mode'] ?? '') === 'bulk_and_piece') ? 'selected' : '' ?>>Bulk and pieces</option>
                </select>
            </label>
            <label>
                <span>Minimum Stock Level</span>
                <input type="number" name="minimum_stock_level" value="<?= h((string) ($editing['minimum_stock_level'] ?? 10)) ?>">
            </label>
            <label>
                <span>Bulk Conversion</span>
                <input type="number" name="bulk_conversion" value="<?= h((string) ($editing['bulk_conversion'] ?? 1)) ?>">
            </label>
            <label class="checkbox-row full-width">
                <input type="checkbox" name="controlled_drug" value="1" <?= !empty($editing['controlled_drug']) ? 'checked' : '' ?>>
                <span>Controlled drug validation required</span>
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit"><?= $editing ? 'Update Product' : 'Add Product' ?></button>
                <?php if ($editing): ?>
                    <a class="btn-secondary" href="index.php?page=products">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </article>

    <article class="card stack">
        <div class="section-head">
            <h3>Categories & Brands</h3>
            <span>Quick setup</span>
        </div>
        <form method="post" class="inline-form">
            <input type="hidden" name="action" value="add_category">
            <input name="category_name" placeholder="New category" required>
            <button class="btn-secondary" type="submit">Add Category</button>
        </form>
        <div class="chips-wrap">
            <?php foreach ($categories as $category): ?>
                <span class="chip"><?= h($category['name']) ?></span>
            <?php endforeach; ?>
        </div>
        <form method="post" class="inline-form">
            <input type="hidden" name="action" value="add_brand">
            <input name="brand_name" placeholder="New brand" required>
            <button class="btn-secondary" type="submit">Add Brand</button>
        </form>
        <div class="chips-wrap">
            <?php foreach ($brands as $brand): ?>
                <span class="chip muted"><?= h($brand['name']) ?></span>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Drug / Product Catalog</h3>
        <span><?= count($products) ?> products</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Brand</th>
                <th>Dosage</th>
                <th>Units</th>
                <th>Stock</th>
                <th>Nearest Expiry</th>
                <th>Price</th>
                <th>Wholesale</th>
                <th>Control</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= h($product['name']) ?></td>
                    <td><?= h($product['category_name']) ?></td>
                    <td><?= h($product['brand_name']) ?></td>
                    <td><?= h($product['dosage']) ?></td>
                    <td><?= h($product['unit_type']) ?></td>
                    <td><?= (int) $product['total_stock'] ?></td>
                    <td><?= h($product['nearest_expiry'] ?: '-') ?></td>
                    <td><?= money($product['unit_price']) ?></td>
                    <td><?= money($product['wholesale_price'] ?? $product['unit_price']) ?></td>
                    <td><?= !empty($product['controlled_drug']) ? 'Controlled' : 'Standard' ?></td>
                    <td class="action-cell">
                        <a class="text-link" href="index.php?page=products&edit=<?= (int) $product['id'] ?>">Edit</a>
                        <form method="post" onsubmit="return confirm('Delete this product?');">
                            <input type="hidden" name="action" value="delete_product">
                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                            <button class="text-link danger-text" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
