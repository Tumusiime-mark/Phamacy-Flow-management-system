<?php
$period = $_GET['period'] ?? 'daily';
$rangeSql = match ($period) {
    'weekly' => 'date("now", "-6 day")',
    'monthly' => 'date("now", "-29 day")',
    default => 'date("now")',
};

$salesSummary = fetch_one(
    "SELECT COUNT(*) AS invoices,
            COALESCE(SUM(total), 0) AS sales_total,
            COALESCE(SUM(paid_amount), 0) AS paid_total,
            COALESCE(SUM(balance), 0) AS balance_total
     FROM sales
     WHERE date(created_at) >= {$rangeSql}"
);
$costSummary = fetch_one(
    "SELECT COALESCE(SUM(si.quantity * p.cost_price), 0) AS cost_total
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     JOIN products p ON p.id = si.product_id
     WHERE date(s.created_at) >= {$rangeSql}"
);
$purchaseSummary = fetch_one(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(total), 0) AS purchase_total
     FROM purchases
     WHERE date(created_at) >= {$rangeSql}"
);

$salesTotal = (float) ($salesSummary['sales_total'] ?? 0);
$costTotal = (float) ($costSummary['cost_total'] ?? 0);
$purchaseTotal = (float) ($purchaseSummary['purchase_total'] ?? 0);
$grossProfit = $salesTotal - $costTotal;
$profitLoss = $salesTotal - $purchaseTotal;

$fastMoving = fetch_all(
    "SELECT p.name, SUM(si.quantity) AS sold_qty, SUM(si.total) AS sales_value
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     JOIN products p ON p.id = si.product_id
     WHERE date(s.created_at) >= {$rangeSql}
     GROUP BY p.id
     ORDER BY sold_qty DESC
     LIMIT 8"
);
$slowMoving = array_reverse($fastMoving);
$stockReport = fetch_all(
    'SELECT p.name,
            COALESCE(SUM(b.quantity), 0) AS stock_qty,
            p.cost_price,
            p.unit_price,
            COALESCE(SUM(b.quantity), 0) * p.cost_price AS stock_cost,
            COALESCE(SUM(b.quantity), 0) * p.unit_price AS stock_retail
     FROM products p
     LEFT JOIN batches b ON b.product_id = p.id
     GROUP BY p.id
     ORDER BY stock_qty ASC, p.name'
);
$expiryReport = fetch_all(
    'SELECT p.name, b.batch_number, b.expiry_date, b.quantity, br.name AS branch_name
     FROM batches b
     JOIN products p ON p.id = b.product_id
     LEFT JOIN branches br ON br.id = b.branch_id
     ORDER BY b.expiry_date ASC'
);
$supplierReport = fetch_all(
    "SELECT s.supplier_name,
            COUNT(p.id) AS purchase_count,
            COALESCE(SUM(p.total), 0) AS supplied_value
     FROM suppliers s
     LEFT JOIN purchases p ON p.supplier_id = s.id
     GROUP BY s.id
     ORDER BY supplied_value DESC, s.supplier_name"
);
$purchaseReport = fetch_all(
    "SELECT p.purchase_number, s.supplier_name, b.name AS branch_name, p.status, p.total, p.purchase_date
     FROM purchases p
     JOIN suppliers s ON s.id = p.supplier_id
     JOIN branches b ON b.id = p.branch_id
     WHERE date(p.created_at) >= {$rangeSql}
     ORDER BY p.created_at DESC
     LIMIT 20"
);
$customerReport = fetch_all(
    "SELECT pa.full_name,
            pa.phone,
            pa.address,
            COUNT(s.id) AS invoice_count,
            COALESCE(SUM(s.total), 0) AS billed_total
     FROM patients pa
     LEFT JOIN sales s ON s.patient_id = pa.id
     GROUP BY pa.id
     ORDER BY billed_total DESC, pa.full_name"
);
?>

<section class="report-filter-bar">
    <a class="chip <?= $period === 'daily' ? '' : 'muted' ?>" href="index.php?page=reports&period=daily">Daily</a>
    <a class="chip <?= $period === 'weekly' ? '' : 'muted' ?>" href="index.php?page=reports&period=weekly">Weekly</a>
    <a class="chip <?= $period === 'monthly' ? '' : 'muted' ?>" href="index.php?page=reports&period=monthly">Monthly</a>
    <button class="btn-secondary" type="button" onclick="window.print()">Print Report</button>
</section>

<section class="grid metrics-grid">
    <article class="card metric-card highlight">
        <span>Sales</span>
        <strong><?= money($salesTotal) ?></strong>
        <small><?= ucfirst($period) ?> sales report</small>
    </article>
    <article class="card metric-card">
        <span>Gross Profit</span>
        <strong><?= money($grossProfit) ?></strong>
        <small>Sales minus cost of sold drugs</small>
    </article>
    <article class="card metric-card">
        <span>Profit / Loss</span>
        <strong><?= money($profitLoss) ?></strong>
        <small>Sales compared to purchases</small>
    </article>
    <article class="card metric-card">
        <span>Outstanding Balances</span>
        <strong><?= money((float) ($salesSummary['balance_total'] ?? 0)) ?></strong>
        <small>Customer balances</small>
    </article>
</section>

<section class="grid dashboard-grid">
    <article class="card">
        <div class="section-head">
            <h3>Sales Summary</h3>
            <span><?= (int) ($salesSummary['invoices'] ?? 0) ?> invoices</span>
        </div>
        <div class="list-table">
            <div class="list-row"><strong>Paid Sales</strong><span><?= money((float) ($salesSummary['paid_total'] ?? 0)) ?></span></div>
            <div class="list-row"><strong>Purchase Value</strong><span><?= money($purchaseTotal) ?></span></div>
            <div class="list-row"><strong>Orders Raised</strong><span><?= (int) ($purchaseSummary['orders'] ?? 0) ?></span></div>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Fast Moving Drugs</h3>
            <span>Top sellers</span>
        </div>
        <div class="list-table">
            <?php foreach ($fastMoving as $item): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($item['name']) ?></strong>
                        <small><?= (int) $item['sold_qty'] ?> units sold</small>
                    </div>
                    <span><?= money($item['sales_value']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Slow Moving Drugs</h3>
            <span>Watch inventory aging</span>
        </div>
        <div class="list-table">
            <?php foreach ($slowMoving as $item): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($item['name']) ?></strong>
                        <small><?= (int) $item['sold_qty'] ?> units sold</small>
                    </div>
                    <span><?= money($item['sales_value']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Supplier Report</h3>
            <span>Purchase contribution</span>
        </div>
        <div class="list-table">
            <?php foreach (array_slice($supplierReport, 0, 6) as $item): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($item['supplier_name']) ?></strong>
                        <small><?= (int) $item['purchase_count'] ?> purchase orders</small>
                    </div>
                    <span><?= money($item['supplied_value']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card wide">
        <div class="section-head">
            <h3>Stock Report</h3>
            <span>Live stock valuation</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Drug</th>
                    <th>Stock Qty</th>
                    <th>Cost Value</th>
                    <th>Retail Value</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stockReport as $item): ?>
                    <tr>
                        <td><?= h($item['name']) ?></td>
                        <td><?= (int) $item['stock_qty'] ?></td>
                        <td><?= money($item['stock_cost']) ?></td>
                        <td><?= money($item['stock_retail']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="card wide">
        <div class="section-head">
            <h3>Expiry Report</h3>
            <span>Batches by expiry date</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Drug</th>
                    <th>Batch</th>
                    <th>Branch</th>
                    <th>Expiry</th>
                    <th>Qty</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($expiryReport as $item): ?>
                    <tr>
                        <td><?= h($item['name']) ?></td>
                        <td><?= h($item['batch_number']) ?></td>
                        <td><?= h($item['branch_name'] ?: 'Main Branch') ?></td>
                        <td><?= h($item['expiry_date']) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="card wide">
        <div class="section-head">
            <h3>Purchase Report</h3>
            <span>Recent purchasing activity</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Purchase No.</th>
                    <th>Supplier</th>
                    <th>Branch</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($purchaseReport as $item): ?>
                    <tr>
                        <td><?= h($item['purchase_number']) ?></td>
                        <td><?= h($item['supplier_name']) ?></td>
                        <td><?= h($item['branch_name']) ?></td>
                        <td><?= h($item['status']) ?></td>
                        <td><?= money($item['total']) ?></td>
                        <td><?= h($item['purchase_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="card wide">
        <div class="section-head">
            <h3>Customer Report</h3>
            <span>Customer value summary</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Invoices</th>
                    <th>Total Sales</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($customerReport as $item): ?>
                    <tr>
                        <td><?= h($item['full_name']) ?></td>
                        <td><?= h($item['phone']) ?></td>
                        <td><?= h($item['address']) ?></td>
                        <td><?= (int) $item['invoice_count'] ?></td>
                        <td><?= money($item['billed_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
