<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'sales_return') {
        $saleItem = fetch_one(
            'SELECT si.*, s.id AS sale_record_id, s.patient_id, s.branch_id
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             WHERE si.id = ?',
            [(int) $_POST['sale_item_id']]
        );
        if ($saleItem) {
            $returnQty = min((int) $_POST['quantity'], (int) $saleItem['quantity']);
            $refund = $returnQty * (float) $saleItem['unit_price'];
            execute_query(
                'INSERT INTO sales_returns (sale_id, branch_id, patient_id, refund_amount, reason, status, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int) $saleItem['sale_record_id'],
                    (int) $saleItem['branch_id'],
                    $saleItem['patient_id'],
                    $refund,
                    trim($_POST['reason'] ?? ''),
                    'Returned',
                    selected_user()['name'],
                    now(),
                ]
            );
            $salesReturnId = (int) database()->lastInsertId();
            execute_query(
                'INSERT INTO sales_return_items (sales_return_id, sale_item_id, quantity, line_refund) VALUES (?, ?, ?, ?)',
                [$salesReturnId, (int) $_POST['sale_item_id'], $returnQty, $refund]
            );
            execute_query('UPDATE batches SET quantity = quantity + ? WHERE id = ?', [$returnQty, $saleItem['batch_id']]);
            record_stock_movement((int) $saleItem['product_id'], (int) $saleItem['branch_id'], (int) $saleItem['batch_id'], 'sales_return', $returnQty, 'Customer sales return');
            flash('success', 'Sales return recorded and stock adjusted.');
        }
        redirect('index.php?page=returns');
    }

    if ($action === 'purchase_return') {
        $purchaseItem = fetch_one(
            'SELECT pi.*, pu.branch_id
             FROM purchase_items pi
             JOIN purchases pu ON pu.id = pi.purchase_id
             WHERE pi.id = ?',
            [(int) $_POST['purchase_item_id']]
        );
        if ($purchaseItem) {
            $returnQty = min((int) $_POST['quantity'], (int) $purchaseItem['received_quantity']);
            execute_query(
                'INSERT INTO purchase_returns (purchase_id, purchase_item_id, branch_id, quantity, reason, status, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int) $purchaseItem['purchase_id'],
                    (int) $_POST['purchase_item_id'],
                    (int) $purchaseItem['branch_id'],
                    $returnQty,
                    trim($_POST['reason'] ?? ''),
                    'Returned',
                    selected_user()['name'],
                    now(),
                ]
            );
            $batch = fetch_one(
                'SELECT id FROM batches WHERE product_id = ? AND branch_id = ? AND batch_number = ? ORDER BY id DESC LIMIT 1',
                [(int) $purchaseItem['product_id'], (int) $purchaseItem['branch_id'], $purchaseItem['batch_number']]
            );
            if ($batch) {
                execute_query('UPDATE batches SET quantity = quantity - ? WHERE id = ?', [$returnQty, $batch['id']]);
                record_stock_movement((int) $purchaseItem['product_id'], (int) $purchaseItem['branch_id'], (int) $batch['id'], 'purchase_return', -$returnQty, 'Returned to supplier');
            }
            flash('success', 'Purchase return recorded.');
        }
        redirect('index.php?page=returns');
    }
}

$saleItems = fetch_all(
    'SELECT si.*, s.invoice_number, s.branch_id, pr.name AS product_name, pt.full_name
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     JOIN products pr ON pr.id = si.product_id
     LEFT JOIN patients pt ON pt.id = s.patient_id
     ORDER BY si.id DESC'
);
$purchaseItems = fetch_all(
    'SELECT pi.*, pu.purchase_number, br.name AS branch_name, pr.name AS product_name
     FROM purchase_items pi
     JOIN purchases pu ON pu.id = pi.purchase_id
     JOIN branches br ON br.id = pu.branch_id
     JOIN products pr ON pr.id = pi.product_id
     WHERE pi.received_quantity > 0
     ORDER BY pi.id DESC'
);
$salesReturns = fetch_all(
    'SELECT sr.*, s.invoice_number FROM sales_returns sr JOIN sales s ON s.id = sr.sale_id ORDER BY sr.id DESC LIMIT 10'
);
$purchaseReturns = fetch_all(
    'SELECT prt.*, pu.purchase_number FROM purchase_returns prt JOIN purchases pu ON pu.id = prt.purchase_id ORDER BY prt.id DESC LIMIT 10'
);
?>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Sales Return</h3>
            <span>Customer return section</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="sales_return">
            <label class="full-width">
                <span>Sale Item</span>
                <select name="sale_item_id" required>
                    <option value="">Select sale item</option>
                    <?php foreach ($saleItems as $item): ?>
                        <option value="<?= (int) $item['id'] ?>"><?= h($item['invoice_number']) ?> - <?= h($item['product_name']) ?> - Qty <?= (int) $item['quantity'] ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Return Quantity</span>
                <input type="number" name="quantity" min="1" value="1" required>
            </label>
            <label class="full-width">
                <span>Reason</span>
                <textarea name="reason" rows="3"></textarea>
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Record Sales Return</button>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Purchase Return</h3>
            <span>Return to supplier</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="purchase_return">
            <label class="full-width">
                <span>Purchase Item</span>
                <select name="purchase_item_id" required>
                    <option value="">Select purchase item</option>
                    <?php foreach ($purchaseItems as $item): ?>
                        <option value="<?= (int) $item['id'] ?>"><?= h($item['purchase_number']) ?> - <?= h($item['product_name']) ?> - <?= h($item['branch_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Return Quantity</span>
                <input type="number" name="quantity" min="1" value="1" required>
            </label>
            <label class="full-width">
                <span>Reason</span>
                <textarea name="reason" rows="3"></textarea>
            </label>
            <div class="form-actions full-width">
                <button class="btn-secondary" type="submit">Record Purchase Return</button>
            </div>
        </form>
    </article>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Sales Returns Log</h3>
            <span>Latest 10</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Invoice</th><th>Refund</th><th>Reason</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($salesReturns as $return): ?>
                    <tr>
                        <td><?= h($return['invoice_number']) ?></td>
                        <td><?= money($return['refund_amount']) ?></td>
                        <td><?= h($return['reason']) ?></td>
                        <td><?= h($return['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Purchase Returns Log</h3>
            <span>Latest 10</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Purchase</th><th>Qty</th><th>Reason</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($purchaseReturns as $return): ?>
                    <tr>
                        <td><?= h($return['purchase_number']) ?></td>
                        <td><?= (int) $return['quantity'] ?></td>
                        <td><?= h($return['reason']) ?></td>
                        <td><?= h($return['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
