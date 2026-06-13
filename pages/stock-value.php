<?php
$dateFrom = request_date('date_from', current_month_start());
$dateTo = request_date('date_to', current_month_end());
$branchId = has_global_branch_access() ? (isset($_GET['branch_id']) && trim($_GET['branch_id']) !== '' ? (int) $_GET['branch_id'] : null) : current_branch_id();

$branchBatchJoin = $branchId !== null ? ' AND b.branch_id = ?' : '';
$branchBatchParams = $branchId !== null ? [$branchId] : [];
$branchSalesJoin = $branchId !== null ? ' AND s.branch_id = ?' : '';
$branchSalesParams = $branchId !== null ? [$branchId] : [];
$branchMovementJoin = $branchId !== null ? ' AND sm.branch_id = ?' : '';
$branchMovementParams = $branchId !== null ? [$branchId] : [];

$stockValue = fetch_one(
    'SELECT COALESCE(SUM(b.quantity * p.cost_price), 0) AS total_value,
            COALESCE(SUM(b.quantity), 0) AS total_qty
     FROM batches b
     JOIN products p ON p.id = b.product_id
     WHERE b.quantity > 0' . $branchBatchJoin,
    $branchBatchParams
) ?: [];

$stockBreakdown = fetch_all(
    'SELECT p.id, p.name, p.cost_price, p.unit_price, p.minimum_stock_level,
            COALESCE(SUM(b.quantity), 0) AS stock_qty,
            COALESCE(SUM(b.quantity * p.cost_price), 0) AS stock_value
     FROM products p
     LEFT JOIN batches b ON b.product_id = p.id' . $branchBatchJoin . '
     GROUP BY p.id
     ORDER BY stock_value DESC',
    $branchBatchParams
);

$soldSummaryRows = fetch_all(
    'SELECT si.product_id,
            COALESCE(SUM(si.quantity), 0) AS sold_qty,
            COALESCE(SUM(si.total), 0) AS sold_revenue
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     WHERE s.status = "SERVED"' . $branchSalesJoin . '
     GROUP BY si.product_id',
    $branchSalesParams
);

$soldByProduct = [];
foreach ($soldSummaryRows as $soldRow) {
    $soldByProduct[(int) $soldRow['product_id']] = $soldRow;
}

$bigBatches = fetch_all(
    'SELECT b.*, p.name AS product_name, br.name AS branch_name
     FROM batches b
     JOIN products p ON p.id = b.product_id
     LEFT JOIN branches br ON br.id = b.branch_id
     WHERE b.quantity > 0' . $branchBatchJoin . '
     ORDER BY b.quantity DESC
     LIMIT 10',
    $branchBatchParams
);

$lowStockProducts = fetch_all(
    'SELECT p.id, p.name, p.minimum_stock_level,
            COALESCE(SUM(b.quantity), 0) AS stock_qty
     FROM products p
     LEFT JOIN batches b ON b.product_id = p.id' . $branchBatchJoin . '
     GROUP BY p.id
     HAVING stock_qty <= p.minimum_stock_level
     ORDER BY stock_qty ASC
     LIMIT 12',
    $branchBatchParams
);

$expiredLoss = fetch_one(
    'SELECT COALESCE(SUM(b.quantity * p.cost_price), 0) AS value_loss
     FROM batches b
     JOIN products p ON p.id = b.product_id
     WHERE b.quantity > 0 AND DATE(b.expiry_date) < ?' . $branchBatchJoin,
    array_merge([today()], $branchBatchParams)
) ?: [];

$damageLoss = fetch_one(
    'SELECT COALESCE(SUM(ABS(sm.quantity) * p.cost_price), 0) AS value_loss
     FROM stock_movements sm
     JOIN products p ON p.id = sm.product_id
     WHERE sm.movement_type = "damaged" AND sm.quantity < 0' . $branchMovementJoin,
    $branchMovementParams
) ?: [];

$financialSummary = sales_summary_between($dateFrom, $dateTo, $branchId);

if (isset($_GET['export']) && $_GET['export'] === 'stock_value') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock-value-' . date('Ymd-His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Stock Value Report']);
    fputcsv($output, ['Date From', $dateFrom]);
    fputcsv($output, ['Date To', $dateTo]);
    if ($branchId !== null) {
        $branch = fetch_one('SELECT name FROM branches WHERE id = ?', [$branchId]);
        fputcsv($output, ['Branch', $branch['name'] ?? 'Branch ' . $branchId]);
    }
    fputcsv($output, []);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Stock Quantity', $stockValue['total_qty']]);
    fputcsv($output, ['Total Stock Value', $stockValue['total_value']]);
    fputcsv($output, ['Expired Stock Loss', $expiredLoss['value_loss']]);
    fputcsv($output, ['Damaged Stock Loss', $damageLoss['value_loss']]);
    fputcsv($output, ['Sales Total', $financialSummary['sales_total']]);
    fputcsv($output, ['Purchase Total', $financialSummary['purchase_total']]);
    fputcsv($output, ['Gross Profit', $financialSummary['gross_profit']]);
    fputcsv($output, ['Net Profit', $financialSummary['net_profit']]);
    fputcsv($output, []);
    fputcsv($output, ['Product', 'Stock Qty', 'Stock Value', 'Sold Qty', 'Sold Revenue', 'Profit']);
    foreach ($stockBreakdown as $row) {
        $sold = $soldByProduct[(int) $row['id']] ?? ['sold_qty' => 0, 'sold_revenue' => 0];
        $profit = ((float) $sold['sold_revenue'] - ((float) $sold['sold_qty'] * (float) $row['cost_price']));
        fputcsv($output, [
            $row['name'],
            $row['stock_qty'],
            $row['stock_value'],
            $sold['sold_qty'],
            $sold['sold_revenue'],
            $profit,
        ]);
    }
    fclose($output);
    exit;
}
?>

<section class="card report-filter-card">
    <div class="section-head">
        <h3>Stock Value & Financial Summary</h3>
        <span>Remaining capital, stock breakdown, losses and sales standings</span>
    </div>
    <form method="get" class="form-grid">
        <input type="hidden" name="page" value="stock-value">
        <label>
            <span>Date From</span>
            <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
        </label>
        <label>
            <span>Date To</span>
            <input type="date" name="date_to" value="<?= h($dateTo) ?>">
        </label>
        <?php if (has_global_branch_access()): ?>
        <label>
            <span>Branch</span>
            <select name="branch_id">
                <option value="">All branches</option>
                <?php foreach (branches_list() as $branch): ?>
                    <option value="<?= (int) $branch['id'] ?>" <?= isset($_GET['branch_id']) && $_GET['branch_id'] == $branch['id'] ? 'selected' : '' ?>><?= h($branch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <div class="form-actions full-width">
            <button class="btn-primary" type="submit">Apply Filter</button>
            <a class="btn-secondary" href="index.php?page=stock-value&export=stock_value&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?><?= $branchId !== null ? '&branch_id=' . (int) $branchId : '' ?>">Download Summary CSV</a>
        </div>
    </form>
</section>

<section class="grid four-col dashboard-stats">
    <article class="card stat-card border-blue">
        <span>Total Stock Value</span>
        <strong><?= money($stockValue['total_value']) ?></strong>
        <small>Cost-based remaining capital</small>
    </article>
    <article class="card stat-card border-green">
        <span>Total Units on Hand</span>
        <strong><?= (int) $stockValue['total_qty'] ?></strong>
        <small>Available batch quantity</small>
    </article>
    <article class="card stat-card border-orange">
        <span>Expired Stock Loss</span>
        <strong><?= money($expiredLoss['value_loss']) ?></strong>
        <small>Current expired value</small>
    </article>
    <article class="card stat-card border-red">
        <span>Damaged Stock Loss</span>
        <strong><?= money($damageLoss['value_loss']) ?></strong>
        <small>Recorded damage value</small>
    </article>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head"><h3>Financial Standings</h3><span>Sales and purchase metrics</span></div>
        <div class="list-table">
            <div class="list-row"><strong>Sales Total</strong><span><?= money($financialSummary['sales_total']) ?></span></div>
            <div class="list-row"><strong>Purchase Total</strong><span><?= money($financialSummary['purchase_total']) ?></span></div>
            <div class="list-row"><strong>Gross Profit</strong><span><?= money($financialSummary['gross_profit']) ?></span></div>
            <div class="list-row"><strong>Net Profit</strong><span><?= money($financialSummary['net_profit']) ?></span></div>
            <div class="list-row"><strong>Outstanding Balance</strong><span><?= money($financialSummary['balance_total']) ?></span></div>
        </div>
    </article>

    <article class="card">
        <div class="section-head"><h3>Low Stock Products</h3><span>Products below reorder level</span></div>
        <div class="list-table">
            <?php foreach ($lowStockProducts as $product): ?>
            <div class="list-row">
                <div><strong><?= h($product['name']) ?></strong><small>Minimum <?= (int) $product['minimum_stock_level'] ?></small></div>
                <span><?= (int) $product['stock_qty'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (count($lowStockProducts) === 0): ?>
            <div class="list-row"><span>No low stock products.</span></div>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="card">
    <div class="section-head"><h3>Stock Breakdown by Product</h3><span>Remaining stock, sold quantity, and profit</span></div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Product</th><th>Qty</th><th>Value</th><th>Sold</th><th>Revenue</th><th>Profit</th></tr></thead>
            <tbody>
            <?php foreach ($stockBreakdown as $row):
                $sold = $soldByProduct[(int) $row['id']] ?? ['sold_qty' => 0, 'sold_revenue' => 0];
                $profit = ((float) $sold['sold_revenue'] - ((float) $sold['sold_qty'] * (float) $row['cost_price']));
            ?>
                <tr>
                    <td><?= h($row['name']) ?></td>
                    <td><?= (int) $row['stock_qty'] ?></td>
                    <td><?= money($row['stock_value']) ?></td>
                    <td><?= (int) $sold['sold_qty'] ?></td>
                    <td><?= money($sold['sold_revenue']) ?></td>
                    <td><?= money($profit) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head"><h3>Biggest Batches</h3><span>Highest remaining batch quantities</span></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Batch</th><th>Product</th><th>Branch</th><th>Qty</th><th>Expiry</th></tr></thead>
                <tbody>
                <?php foreach ($bigBatches as $batch): ?>
                <tr>
                    <td><?= h($batch['batch_number']) ?></td>
                    <td><?= h($batch['product_name']) ?></td>
                    <td><?= h($batch['branch_name'] ?: 'Main Branch') ?></td>
                    <td><?= (int) $batch['quantity'] ?></td>
                    <td><?= h($batch['expiry_date']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="card">
        <div class="section-head"><h3>Stock Value Summary</h3><span>Batch and loss overview</span></div>
        <div class="list-table">
            <div class="list-row"><strong>Remaining Capital</strong><span><?= money($stockValue['total_value']) ?></span></div>
            <div class="list-row"><strong>Expired Stock Loss</strong><span><?= money($expiredLoss['value_loss']) ?></span></div>
            <div class="list-row"><strong>Damaged Stock Loss</strong><span><?= money($damageLoss['value_loss']) ?></span></div>
            <div class="list-row"><strong>Stock Items in Batches</strong><span><?= (int) $stockValue['total_qty'] ?></span></div>
        </div>
    </article>
</section>
