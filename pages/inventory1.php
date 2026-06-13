<?php
$branches = branches_list();

/* ================= SAFE DATE FILTER (FIXED) ================= */
$dateFromRaw = $_GET['date_from'] ?? null;
$dateToRaw   = $_GET['date_to'] ?? null;

$hasDateFilter = !empty($dateFromRaw) && !empty($dateToRaw);

$dateFrom = null;
$dateTo   = null;

if ($hasDateFilter) {
    $dateFrom = request_date('date_from', current_month_start());
    $dateTo   = request_date('date_to', current_month_end());
}

/* ================= DATA ================= */
$products = products_with_stock();

$batches = fetch_all(
    "SELECT b.*, p.name AS product_name, br.name AS branch_name
     FROM batches b
     JOIN products p ON p.id = b.product_id
     LEFT JOIN branches br ON br.id = b.branch_id
     ORDER BY b.expiry_date ASC"
);

/* ================= STOCK MOVEMENTS (FILTERED) ================= */
$movements = [];

if ($hasDateFilter) {
    $movements = fetch_all(
        "SELECT sm.*, 
                p.name AS product_name, 
                b.batch_number, 
                br.name AS branch_name
         FROM stock_movements sm
         JOIN products p ON p.id = sm.product_id
         LEFT JOIN batches b ON b.id = sm.batch_id
         LEFT JOIN branches br ON br.id = sm.branch_id
         WHERE DATE(sm.created_at) BETWEEN ? AND ?
         ORDER BY sm.created_at DESC",
        [$dateFrom, $dateTo]
    );
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>

    <style>
    body {
        font-family: Arial;
        background: #f5f7fb;
        margin: 0;
        padding: 20px;
    }

    .container {
        max-width: 1200px;
        margin: auto;
    }

    .card {
        background: #fff;
        padding: 15px;
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
        margin-bottom: 20px;
    }

    .grid {
        display: grid;
        gap: 15px;
    }

    .two-col {
        grid-template-columns: 1fr 1fr;
    }

    input,
    select {
        width: 100%;
        padding: 8px;
        margin-top: 5px;
    }

    button {
        background: #2563eb;
        color: #fff;
        border: 0;
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
    }

    .table-scroll {
        overflow: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 10px;
        border-bottom: 1px solid #eee;
        text-align: left;
    }

    th {
        background: #f9fafb;
    }

    .badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 12px;
    }

    .in {
        background: #dcfce7;
        color: #166534;
    }

    .out {
        background: #fee2e2;
        color: #991b1b;
    }

    .warn {
        background: #fef3c7;
        color: #92400e;
    }

    .notice {
        padding: 10px;
        background: #fff7ed;
        border-left: 4px solid #f97316;
    }
    </style>
</head>

<body>

    <div class="container">

        <!-- ================= FILTER ================= -->
        <div class="card">
            <h3>Stock History Filter</h3>

            <form method="get">
                <input type="hidden" name="page" value="inventory">

                <label>Date From
                    <input type="date" name="date_from" value="<?= h($dateFromRaw) ?>">
                </label>

                <label>Date To
                    <input type="date" name="date_to" value="<?= h($dateToRaw) ?>">
                </label>

                <button type="submit">Apply Filter</button>
            </form>

            <?php if (!$hasDateFilter): ?>
            <div class="notice">
                ⚠ Select a date range to view stock movement history.
            </div>
            <?php endif; ?>
        </div>

        <!-- ================= FORMS ================= -->
        <div class="grid two-col">

            <!-- STOCK IN -->
            <div class="card">
                <h3>Stock In</h3>

                <form method="post">
                    <input type="hidden" name="action" value="add_batch">

                    <label>Product
                        <select name="product_id">
                            <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Branch
                        <select name="branch_id">
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= h($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <input name="batch_number" placeholder="Batch Number">
                    <input type="number" name="quantity" placeholder="Quantity">
                    <input type="date" name="expiry_date">

                    <button>Add Stock</button>
                </form>
            </div>

            <!-- STOCK OUT -->
            <div class="card">
                <h3>Stock Out / Damage / Expiry</h3>

                <form method="post">
                    <input type="hidden" name="action" value="adjust_stock">

                    <label>Batch
                        <select name="batch_id">
                            <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['id'] ?>">
                                <?= h($b['product_name']) ?> (<?= $b['quantity'] ?> left)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Movement Type
                        <select name="movement_type">
                            <option value="stock_out">Stock Out</option>
                            <option value="damaged">Damage</option>
                            <option value="expired">Expiry</option>
                        </select>
                    </label>

                    <input type="number" name="quantity" placeholder="Quantity">

                    <button>Submit</button>
                </form>
            </div>

        </div>

        <!-- ================= STOCK HISTORY ================= -->
        <div class="card">
            <h3>Stock History</h3>

            <div class="table-scroll">

                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Branch</th>
                            <th>Batch</th>
                            <th>Movement</th>
                            <th>Qty</th>
                            <th>Note</th>
                            <th>User</th>
                            <th>Time</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($hasDateFilter && count($movements)): ?>

                        <?php foreach ($movements as $m): ?>
                        <tr>
                            <td><?= h($m['product_name']) ?></td>
                            <td><?= h($m['branch_name'] ?: 'Main Branch') ?></td>
                            <td><?= h($m['batch_number'] ?: '-') ?></td>

                            <td>
                                <?php
                            $type = $m['movement_type'];
                            $class = in_array($type, ['stock_in']) ? 'in' : 'out';
                            ?>
                                <span class="badge <?= $class ?>">
                                    <?= h($type) ?>
                                </span>
                            </td>

                            <td><?= (int)$m['quantity'] ?></td>
                            <td><?= h($m['note']) ?></td>
                            <td><?= h($m['created_by'] ?? 'System') ?></td>
                            <td><?= h($m['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:20px;">
                                No stock history found for selected date range
                            </td>
                        </tr>
                        <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </div>

    </div>

</body>

</html>