<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_supplier') {
        execute_query(
            'INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                trim($_POST['supplier_name'] ?? ''),
                trim($_POST['contact_person'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['address'] ?? ''),
                now(),
            ]
        );
        flash('success', 'Supplier added.');
        redirect('index.php?page=purchases');
    }

    if ($action === 'create_purchase') {
        $branchId = (int) ($_POST['branch_id'] ?? current_branch_id());
        $quantity = (int) $_POST['quantity'];
        $unitCost = (float) $_POST['unit_cost'];
        $lineTotal = $quantity * $unitCost;

        execute_query(
            'INSERT INTO purchases (purchase_number, branch_id, supplier_id, supplier_invoice_number, purchase_date, received_date, status, subtotal, total, notes, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'PO-' . date('Ymd-His'),
                $branchId,
                (int) $_POST['supplier_id'],
                trim($_POST['supplier_invoice_number'] ?? ''),
                $_POST['purchase_date'] ?? today(),
                null,
                'Draft',
                $lineTotal,
                $lineTotal,
                trim($_POST['notes'] ?? ''),
                selected_user()['name'],
                now(),
            ]
        );
        $purchaseId = (int) database()->lastInsertId();
        execute_query(
            'INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, batch_number, expiry_date, received_quantity, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $purchaseId,
                (int) $_POST['product_id'],
                $quantity,
                $unitCost,
                trim($_POST['batch_number'] ?? ''),
                $_POST['expiry_date'] ?? today(),
                0,
                $lineTotal,
            ]
        );
        log_activity('Created purchase order ' . $purchaseId);
        flash('success', 'Purchase order created.');
        redirect('index.php?page=purchases');
    }

    if ($action === 'receive_purchase') {
        $item = fetch_one(
            'SELECT pi.*, p.branch_id, p.supplier_id, s.supplier_name
             FROM purchase_items pi
             JOIN purchases p ON p.id = pi.purchase_id
             JOIN suppliers s ON s.id = p.supplier_id
             WHERE pi.id = ?',
            [(int) $_POST['purchase_item_id']]
        );
        if ($item) {
            $batchId = create_or_update_branch_batch(
                (int) $item['product_id'],
                (int) $item['branch_id'],
                $item['batch_number'],
                $item['expiry_date'],
                ((int) $item['quantity']) - ((int) $item['received_quantity']),
                $item['supplier_name']
            );
            execute_query(
                'UPDATE purchase_items SET received_quantity = quantity WHERE id = ?',
                [$item['id']]
            );
            execute_query(
                'UPDATE purchases SET status = "Received", received_date = ? WHERE id = ?',
                [today(), $item['purchase_id']]
            );
            record_stock_movement((int) $item['product_id'], (int) $item['branch_id'], $batchId, 'purchase_receive', (int) $item['quantity'], 'Purchase receive');
            flash('success', 'Stock received and batch recorded.');
        }
        redirect('index.php?page=purchases');
    }
}

$branches = branches_list();
$suppliers = suppliers_list();
$products = products_with_stock();
$purchases = fetch_all(
    'SELECT pu.*, br.name AS branch_name, sp.supplier_name
     FROM purchases pu
     JOIN branches br ON br.id = pu.branch_id
     JOIN suppliers sp ON sp.id = pu.supplier_id
     ORDER BY pu.created_at DESC'
);
$purchaseItems = fetch_all(
    'SELECT pi.*, pu.purchase_number, pu.branch_id, br.name AS branch_name, pr.name AS product_name
     FROM purchase_items pi
     JOIN purchases pu ON pu.id = pi.purchase_id
     JOIN products pr ON pr.id = pi.product_id
     JOIN branches br ON br.id = pu.branch_id
     ORDER BY pi.id DESC'
);
?>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Create Purchase</h3>
            <span>Supplier invoice and batch entry</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="create_purchase">
            <label>
                <span>Branch</span>
                <select name="branch_id">
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>"><?= h($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Supplier</span>
                <select name="supplier_id" required>
                    <option value="">Select supplier</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= (int) $supplier['id'] ?>"><?= h($supplier['supplier_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Supplier Invoice Number</span>
                <input name="supplier_invoice_number">
            </label>
            <label>
                <span>Purchase Date</span>
                <input type="date" name="purchase_date" value="<?= h(today()) ?>">
            </label>
            <label>
                <span>Product</span>
                <select name="product_id" required>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"><?= h($product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Quantity</span>
                <input type="number" name="quantity" min="1" value="1" required>
            </label>
            <label>
                <span>Unit Cost</span>
                <input type="number" step="0.01" name="unit_cost" required>
            </label>
            <label>
                <span>Batch Number</span>
                <input name="batch_number" required>
            </label>
            <label>
                <span>Expiry Date</span>
                <input type="date" name="expiry_date" required>
            </label>
            <label class="full-width">
                <span>Notes</span>
                <textarea name="notes" rows="3"></textarea>
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Create Purchase</button>
            </div>
        </form>
    </article>

    <article class="card stack">
        <div class="section-head">
            <h3>Add Supplier</h3>
            <span>Supplier master data</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="create_supplier">
            <label>
                <span>Supplier Name</span>
                <input name="supplier_name" required>
            </label>
            <label>
                <span>Contact Person</span>
                <input name="contact_person">
            </label>
            <label>
                <span>Phone</span>
                <input name="phone">
            </label>
            <label>
                <span>Email</span>
                <input name="email" type="email">
            </label>
            <label class="full-width">
                <span>Address</span>
                <input name="address">
            </label>
            <div class="form-actions full-width">
                <button class="btn-secondary" type="submit">Add Supplier</button>
            </div>
        </form>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Purchase Orders</h3>
        <span><?= count($purchases) ?> orders</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Purchase No.</th>
                <th>Branch</th>
                <th>Supplier</th>
                <th>Invoice</th>
                <th>Status</th>
                <th>Total</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($purchases as $purchase): ?>
                <tr>
                    <td><?= h($purchase['purchase_number']) ?></td>
                    <td><?= h($purchase['branch_name']) ?></td>
                    <td><?= h($purchase['supplier_name']) ?></td>
                    <td><?= h($purchase['supplier_invoice_number']) ?></td>
                    <td><?= h($purchase['status']) ?></td>
                    <td><?= money($purchase['total']) ?></td>
                    <td><?= h($purchase['purchase_date']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="section-head">
        <h3>Receive Stock</h3>
        <span>Convert purchase items to live stock</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Purchase</th>
                <th>Product</th>
                <th>Branch</th>
                <th>Batch</th>
                <th>Expiry</th>
                <th>Ordered</th>
                <th>Received</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($purchaseItems as $item): ?>
                <tr>
                    <td><?= h($item['purchase_number']) ?></td>
                    <td><?= h($item['product_name']) ?></td>
                    <td><?= h($item['branch_name']) ?></td>
                    <td><?= h($item['batch_number']) ?></td>
                    <td><?= h($item['expiry_date']) ?></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td><?= (int) $item['received_quantity'] ?></td>
                    <td>
                        <?php if ((int) $item['received_quantity'] < (int) $item['quantity']): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="receive_purchase">
                                <input type="hidden" name="purchase_item_id" value="<?= (int) $item['id'] ?>">
                                <button class="btn-secondary" type="submit">Receive Stock</button>
                            </form>
                        <?php else: ?>
                            Received
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
