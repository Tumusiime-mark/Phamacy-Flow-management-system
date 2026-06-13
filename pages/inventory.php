<?php
$branches = branches_list();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_batch') {
        $branchId = (int) ($_POST['branch_id'] ?? current_branch_id());
        execute_query(
            'INSERT INTO batches (product_id, branch_id, batch_number, expiry_date, quantity, initial_quantity, supplier_name, purchase_date, received_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $_POST['product_id'],
                $branchId,
                trim($_POST['batch_number'] ?? ''),
                $_POST['expiry_date'] ?? today(),
                (int) $_POST['quantity'],
                (int) $_POST['quantity'],
                trim($_POST['supplier_name'] ?? ''),
                $_POST['purchase_date'] ?? today(),
                today(),
                now(),
            ]
        );
        $batchId = (int) database()->lastInsertId();
        record_stock_movement((int) $_POST['product_id'], $branchId, $batchId, 'stock_in', (int) $_POST['quantity'], trim($_POST['note'] ?? 'Stock purchase'));
        audit_log('inventory', 'add_batch', 'Added stock batch', 'batch', $batchId, ['batch' => trim($_POST['batch_number'] ?? '')]);
        log_activity('Added stock batch ' . trim($_POST['batch_number'] ?? ''));
        flash('success', 'Batch added and stock updated.');
        redirect('index.php?page=inventory');
    }

    if ($action === 'adjust_stock') {
        $batchId = (int) $_POST['batch_id'];
        $quantity = (int) $_POST['quantity'];
        $movementType = $_POST['movement_type'];
        $signedQty = in_array($movementType, ['stock_out', 'damaged', 'expired'], true) ? -abs($quantity) : abs($quantity);

        $batch = fetch_one('SELECT product_id, branch_id, batch_number, quantity FROM batches WHERE id = ?', [$batchId]);
        if ($batch) {
            execute_query('UPDATE batches SET quantity = MAX(quantity + ?, 0) WHERE id = ?', [$signedQty, $batchId]);
            record_stock_movement((int) $batch['product_id'], (int) $batch['branch_id'], $batchId, $movementType, $signedQty, trim($_POST['note'] ?? ''));
            audit_log('inventory', $movementType, 'Adjusted stock batch', 'batch', $batchId, ['movement' => $movementType, 'quantity' => $signedQty]);
            log_activity('Recorded ' . $movementType . ' on batch ' . ($batch['batch_number'] ?? ''));
            flash('success', 'Stock adjustment saved.');
        }
        redirect('index.php?page=inventory');
    }
}

$products = products_with_stock();
$batches = fetch_all(
    'SELECT b.*, p.name AS product_name, br.name AS branch_name
     FROM batches b
     JOIN products p ON p.id = b.product_id
     LEFT JOIN branches br ON br.id = b.branch_id
     ORDER BY b.expiry_date ASC'
);
$movements = fetch_all(
    'SELECT sm.*, p.name AS product_name, b.batch_number, br.name AS branch_name
     FROM stock_movements sm
     JOIN products p ON p.id = sm.product_id
     LEFT JOIN batches b ON b.id = sm.batch_id
     LEFT JOIN branches br ON br.id = sm.branch_id
     ORDER BY sm.created_at DESC
     LIMIT 20'
);
?>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Stock In / Purchases</h3>
            <span>Multiple batch handling</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="add_batch">
            <label>
                <span>Drug / Product</span>
                <select name="product_id" required>
                    <option value="">Select product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"><?= h($product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Branch</span>
                <select name="branch_id">
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>"><?= h($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Batch Number</span>
                <input name="batch_number" required>
            </label>
            <label>
                <span>Quantity</span>
                <input type="number" name="quantity" required>
            </label>
            <label>
                <span>Expiry Date</span>
                <input type="date" name="expiry_date" required>
            </label>
            <label>
                <span>Purchase Date</span>
                <input type="date" name="purchase_date" value="<?= h(today()) ?>" required>
            </label>
            <label>
                <span>Supplier / Manufacturer</span>
                <input name="supplier_name">
            </label>
            <label class="full-width">
                <span>Note</span>
                <input name="note" placeholder="Stock purchase, supplier delivery, emergency restock...">
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Add Batch</button>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Stock Out / Damage / Expiry</h3>
            <span>Auto stock update</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="adjust_stock">
            <label class="full-width">
                <span>Select Batch</span>
                <select name="batch_id" required>
                    <option value="">Choose batch</option>
                    <?php foreach ($batches as $batch): ?>
                        <option value="<?= (int) $batch['id'] ?>">
                            <?= h($batch['product_name']) ?> - <?= h($batch['batch_number']) ?> (<?= (int) $batch['quantity'] ?> left)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Movement Type</span>
                <select name="movement_type">
                    <option value="stock_out">Stock Out</option>
                    <option value="damaged">Damaged Drugs</option>
                    <option value="expired">Expired Drugs</option>
                    <option value="stock_in">Return to Stock</option>
                </select>
            </label>
            <label>
                <span>Quantity</span>
                <input type="number" name="quantity" required>
            </label>
            <label class="full-width">
                <span>Note</span>
                <input name="note" placeholder="Broken pack, expired shelf item, adjustment reason...">
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Save Adjustment</button>
            </div>
        </form>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Batch Tracking</h3>
        <span>Expiry and stock monitoring</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Product</th>
                <th>Branch</th>
                <th>Batch</th>
                <th>Expiry Date</th>
                <th>Qty</th>
                <th>Supplier</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($batches as $batch): ?>
                <?php
                $status = 'Healthy';
                if (strtotime($batch['expiry_date']) < strtotime(today())) {
                    $status = 'Expired';
                } elseif ((int) $batch['quantity'] === 0) {
                    $status = 'Out of stock';
                } elseif (strtotime($batch['expiry_date']) <= strtotime('+' . int_setting('expiry_alert_days', 90) . ' days')) {
                    $status = 'Expiring soon';
                }
                ?>
                <tr>
                    <td><?= h($batch['product_name']) ?></td>
                    <td><?= h($batch['branch_name'] ?: 'Main Branch') ?></td>
                    <td><?= h($batch['batch_number']) ?></td>
                    <td><?= h($batch['expiry_date']) ?></td>
                    <td><?= (int) $batch['quantity'] ?></td>
                    <td><?= h($batch['supplier_name']) ?></td>
                    <td><?= h($status) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="section-head">
        <h3>Stock History</h3>
        <span>Purchases, stock out, damage, expiry</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Product</th>
                <th>Branch</th>
                <th>Batch</th>
                <th>Movement</th>
                <th>Quantity</th>
                <th>Note</th>
                <th>By</th>
                <th>Time</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($movements as $movement): ?>
                <tr>
                    <td><?= h($movement['product_name']) ?></td>
                    <td><?= h($movement['branch_name'] ?: 'Main Branch') ?></td>
                    <td><?= h($movement['batch_number'] ?: '-') ?></td>
                    <td><?= h($movement['movement_type']) ?></td>
                    <td><?= (int) $movement['quantity'] ?></td>
                    <td><?= h($movement['note']) ?></td>
                    <td><?= h($movement['created_by']) ?></td>
                    <td><?= h($movement['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
