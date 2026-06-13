<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_branch') {
        execute_query(
            'INSERT INTO branches (name, code, address, phone, is_central, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                trim($_POST['name'] ?? ''),
                strtoupper(trim($_POST['code'] ?? '')),
                trim($_POST['address'] ?? ''),
                trim($_POST['phone'] ?? ''),
                isset($_POST['is_central']) ? 1 : 0,
                now(),
            ]
        );
        flash('success', 'Branch added.');
        redirect('index.php?page=branches');
    }

    if ($action === 'transfer_stock') {
        $batch = fetch_one('SELECT * FROM batches WHERE id = ?', [(int) $_POST['from_batch_id']]);
        $qty = min((int) $_POST['quantity'], (int) ($batch['quantity'] ?? 0));
        if ($batch && $qty > 0) {
            execute_query('UPDATE batches SET quantity = quantity - ? WHERE id = ?', [$qty, $batch['id']]);
            $newBatchId = create_or_update_branch_batch((int) $batch['product_id'], (int) $_POST['to_branch_id'], $batch['batch_number'], $batch['expiry_date'], $qty, (string) $batch['supplier_name']);
            execute_query(
                'INSERT INTO stock_transfers (product_id, from_branch_id, to_branch_id, from_batch_id, transfer_quantity, status, requested_by, received_by, note, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int) $batch['product_id'],
                    (int) $batch['branch_id'],
                    (int) $_POST['to_branch_id'],
                    (int) $batch['id'],
                    $qty,
                    'Completed',
                    selected_user()['name'],
                    selected_user()['name'],
                    trim($_POST['note'] ?? ''),
                    now(),
                ]
            );
            record_stock_movement((int) $batch['product_id'], (int) $batch['branch_id'], (int) $batch['id'], 'transfer_out', -$qty, 'Transfer to branch');
            record_stock_movement((int) $batch['product_id'], (int) $_POST['to_branch_id'], $newBatchId, 'transfer_in', $qty, 'Transfer received from branch');
            flash('success', 'Stock transferred successfully.');
        }
        redirect('index.php?page=branches');
    }

    if ($action === 'request_stock') {
        execute_query(
            'INSERT INTO branch_requests (product_id, requested_by_branch_id, source_branch_id, quantity, request_status, note, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $_POST['product_id'],
                (int) $_POST['requested_by_branch_id'],
                $_POST['source_branch_id'] ? (int) $_POST['source_branch_id'] : null,
                (int) $_POST['quantity'],
                'Pending',
                trim($_POST['note'] ?? ''),
                selected_user()['name'],
                now(),
            ]
        );
        flash('success', 'Branch request submitted.');
        redirect('index.php?page=branches');
    }
}

$branches = branches_list();
$products = products_with_stock();
$allStock = all_branch_stock();
$batches = fetch_all(
    'SELECT bt.*, pr.name AS product_name, br.name AS branch_name
     FROM batches bt
     JOIN products pr ON pr.id = bt.product_id
     JOIN branches br ON br.id = bt.branch_id
     WHERE bt.quantity > 0
     ORDER BY pr.name, bt.expiry_date'
);
$transfers = fetch_all(
    'SELECT st.*, pr.name AS product_name, b1.name AS from_branch, b2.name AS to_branch
     FROM stock_transfers st
     JOIN products pr ON pr.id = st.product_id
     JOIN branches b1 ON b1.id = st.from_branch_id
     JOIN branches b2 ON b2.id = st.to_branch_id
     ORDER BY st.id DESC
     LIMIT 15'
);
$requests = fetch_all(
    'SELECT rq.*, pr.name AS product_name, b1.name AS requested_branch, b2.name AS source_branch
     FROM branch_requests rq
     JOIN products pr ON pr.id = rq.product_id
     JOIN branches b1 ON b1.id = rq.requested_by_branch_id
     LEFT JOIN branches b2 ON b2.id = rq.source_branch_id
     ORDER BY rq.id DESC
     LIMIT 15'
);
?>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Multi-Branch Setup</h3>
            <span>Create pharmacy branches</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="create_branch">
            <label>
                <span>Branch Name</span>
                <input name="name" required>
            </label>
            <label>
                <span>Code</span>
                <input name="code" required>
            </label>
            <label>
                <span>Phone</span>
                <input name="phone">
            </label>
            <label>
                <span>Address</span>
                <input name="address">
            </label>
            <label class="checkbox-row full-width">
                <input type="checkbox" name="is_central" value="1">
                <span>Central stock control branch</span>
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Add Branch</button>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Transfer Stock Between Branches</h3>
            <span>Auto-adjust stock</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="transfer_stock">
            <label class="full-width">
                <span>Source Batch</span>
                <select name="from_batch_id" required>
                    <option value="">Select batch</option>
                    <?php foreach ($batches as $batch): ?>
                        <option value="<?= (int) $batch['id'] ?>"><?= h($batch['product_name']) ?> - <?= h($batch['branch_name']) ?> - <?= h($batch['batch_number']) ?> (<?= (int) $batch['quantity'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Destination Branch</span>
                <select name="to_branch_id" required>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>"><?= h($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Quantity</span>
                <input type="number" name="quantity" min="1" value="1" required>
            </label>
            <label class="full-width">
                <span>Note</span>
                <textarea name="note" rows="3"></textarea>
            </label>
            <div class="form-actions full-width">
                <button class="btn-secondary" type="submit">Transfer Stock</button>
            </div>
        </form>
    </article>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Request From Another Branch</h3>
            <span>For stock out branches</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="request_stock">
            <label>
                <span>Product</span>
                <select name="product_id">
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"><?= h($product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Requesting Branch</span>
                <select name="requested_by_branch_id">
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>"><?= h($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Source Branch</span>
                <select name="source_branch_id">
                    <option value="">Any available branch</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>"><?= h($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Quantity</span>
                <input type="number" name="quantity" min="1" value="1">
            </label>
            <label class="full-width">
                <span>Note</span>
                <textarea name="note" rows="3"></textarea>
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Submit Request</button>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Branch Stock Visibility</h3>
            <span>See where medicine is available</span>
        </div>
        <div class="table-scroll slim-table">
            <table>
                <thead><tr><th>Product</th><th>Branch</th><th>Stock</th></tr></thead>
                <tbody>
                <?php foreach ($allStock as $row): ?>
                    <tr>
                        <td><?= h($row['product_name']) ?></td>
                        <td><?= h($row['branch_name']) ?></td>
                        <td><?= (int) $row['stock'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Transfers Log</h3>
            <span>Latest 15</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Product</th><th>From</th><th>To</th><th>Qty</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($transfers as $transfer): ?>
                    <tr>
                        <td><?= h($transfer['product_name']) ?></td>
                        <td><?= h($transfer['from_branch']) ?></td>
                        <td><?= h($transfer['to_branch']) ?></td>
                        <td><?= (int) $transfer['transfer_quantity'] ?></td>
                        <td><?= h($transfer['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Branch Requests</h3>
            <span>Latest 15</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Product</th><th>Requesting</th><th>Source</th><th>Qty</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?= h($request['product_name']) ?></td>
                        <td><?= h($request['requested_branch']) ?></td>
                        <td><?= h($request['source_branch'] ?: 'Any branch') ?></td>
                        <td><?= (int) $request['quantity'] ?></td>
                        <td><?= h($request['request_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
