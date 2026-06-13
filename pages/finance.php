<?php
$dateFrom = request_date('date_from', current_month_start());
$dateTo = request_date('date_to', current_month_end());
$branchId = has_global_branch_access() ? (isset($_GET['branch_id']) && trim($_GET['branch_id']) !== '' ? (int) $_GET['branch_id'] : null) : current_branch_id();

$branches = branches_list();
$branchFinances = branch_finances_list($branchId);

$purchaseSummaryParams = [$dateFrom, $dateTo];
$purchaseSummarySql = 'SELECT money_source, COUNT(*) AS count, COALESCE(SUM(total), 0) AS total_amount FROM purchases WHERE DATE(created_at) BETWEEN ? AND ?';
if ($branchId !== null) {
    $purchaseSummarySql .= ' AND branch_id = ?';
    $purchaseSummaryParams[] = $branchId;
}
$purchaseSummary = fetch_all($purchaseSummarySql . ' GROUP BY money_source', $purchaseSummaryParams);
$expenseSummary = fetch_all(
    'SELECT money_source, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total_amount
     FROM expenditures
     WHERE DATE(expense_date) BETWEEN ? AND ?' . ($branchId !== null ? ' AND branch_id = ?' : '') . '
     GROUP BY money_source',
    $branchId !== null ? [$dateFrom, $dateTo, $branchId] : [$dateFrom, $dateTo]
);

$salesMoneyPurchases = 0.0;
$offSystemPurchases = 0.0;
foreach ($purchaseSummary as $row) {
    if (($row['money_source'] ?? '') === 'Sales Money') {
        $salesMoneyPurchases = (float) $row['total_amount'];
    } else {
        $offSystemPurchases += (float) $row['total_amount'];
    }
}
$salesMoneyExpenses = 0.0;
$offSystemExpenses = 0.0;
foreach ($expenseSummary as $row) {
    if (($row['money_source'] ?? '') === 'Sales Money') {
        $salesMoneyExpenses = (float) $row['total_amount'];
    } else {
        $offSystemExpenses += (float) $row['total_amount'];
    }
}

$recentFinancesParams = [$dateFrom, $dateTo];
$recentFinancesSql = 'SELECT p.purchase_number, p.money_source, p.total, p.status, p.purchase_date, br.name AS branch_name, p.created_at
    FROM purchases p
    JOIN branches br ON br.id = p.branch_id
    WHERE DATE(p.created_at) BETWEEN ? AND ?';
if ($branchId !== null) {
    $recentFinancesSql .= ' AND p.branch_id = ?';
    $recentFinancesParams[] = $branchId;
}
$recentFinances = fetch_all($recentFinancesSql . ' ORDER BY p.created_at DESC LIMIT 25', $recentFinancesParams);
$recentExpenseSources = fetch_all(
    'SELECT e.expense_date, e.category, e.amount, e.money_source, br.name AS branch_name, e.created_at
     FROM expenditures e
     LEFT JOIN branches br ON br.id = e.branch_id
     WHERE DATE(e.expense_date) BETWEEN ? AND ?' . ($branchId !== null ? ' AND e.branch_id = ?' : '') . '
     ORDER BY e.created_at DESC LIMIT 20',
    $branchId !== null ? [$dateFrom, $dateTo, $branchId] : [$dateFrom, $dateTo]
);

$recentSalesParams = [$dateFrom, $dateTo];
$recentSalesSql = 'SELECT s.invoice_number, s.total, s.paid_amount, s.balance, s.status, br.name AS branch_name, s.created_at
    FROM sales s
    JOIN branches br ON br.id = s.branch_id
    WHERE DATE(s.created_at) BETWEEN ? AND ?';
if ($branchId !== null) {
    $recentSalesSql .= ' AND s.branch_id = ?';
    $recentSalesParams[] = $branchId;
}
$recentSales = fetch_all($recentSalesSql . ' ORDER BY s.created_at DESC LIMIT 20', $recentSalesParams);

$branchName = $branchId !== null ? (fetch_one('SELECT name FROM branches WHERE id = ?', [$branchId])['name'] ?? 'Branch') : 'All Branches';

$availableMoneyTotal = array_reduce($branchFinances, fn($carry, $item) => $carry + (float) ($item['available_sales_money'] ?? 0), 0.0);
$profitSummary = sales_summary_between($dateFrom, $dateTo, $branchId);
$branchComparison = branch_profitability_report($dateFrom, $dateTo);
?>

<section class="card report-filter-card">
    <div class="section-head">
        <h3>Finance Overview</h3>
        <span>Branch available money and purchase source summary</span>
    </div>
    <form method="get" class="form-grid">
        <input type="hidden" name="page" value="finance">
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
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>" <?= $branchId === (int) $branch['id'] ? 'selected' : '' ?>><?= h($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <div class="form-actions full-width">
            <button class="btn-primary" type="submit">Update</button>
        </div>
    </form>
</section>

<section class="grid metrics-grid">
    <article class="card metric-card highlight">
        <span>Available Sales Money</span>
        <strong><?= money($availableMoneyTotal) ?></strong>
        <small><?= h($branchName) ?></small>
    </article>
    <article class="card metric-card">
        <span>Sales-funded Purchases</span>
        <strong><?= money($salesMoneyPurchases) ?></strong>
        <small>Using available sales money</small>
    </article>
    <article class="card metric-card">
        <span>Off-system Purchases</span>
        <strong><?= money($offSystemPurchases) ?></strong>
        <small>External or cash purchases</small>
    </article>
    <article class="card metric-card">
        <span>Sales-funded Expenses</span>
        <strong><?= money($salesMoneyExpenses) ?></strong>
        <small>Expenses paid directly from branch sales money</small>
    </article>
    <article class="card metric-card">
        <span>Net Profit</span>
        <strong><?= money((float) ($profitSummary['net_profit'] ?? 0)) ?></strong>
        <small>Gross <?= money((float) ($profitSummary['gross_profit'] ?? 0)) ?> | Margin <?= number_format((float) ($profitSummary['profit_margin'] ?? 0), 1) ?>%</small>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Recent Expenditure Source Log</h3>
        <span>Expense funding source and accountability trail</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Branch</th>
                    <th>Source</th>
                    <th>Amount</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentExpenseSources as $record): ?>
                    <tr>
                        <td><?= h($record['expense_date']) ?></td>
                        <td><?= h($record['category']) ?></td>
                        <td><?= h($record['branch_name'] ?: '-') ?></td>
                        <td><?= h($record['money_source'] ?? 'Off-system') ?></td>
                        <td><?= money($record['amount']) ?></td>
                        <td><?= h($record['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentExpenseSources)): ?>
                    <tr><td colspan="6">No expenditure activity found for this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Branch Finance Balances</h3>
            <span>Available sales cash per branch</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Available Sales Money</th>
                        <th>Last Updated</th>
                        <th>Updated By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branchFinances as $finance): ?>
                        <tr>
                            <td><?= h($finance['branch_name']) ?></td>
                            <td><?= money($finance['available_sales_money']) ?></td>
                            <td><?= h($finance['last_updated']) ?></td>
                            <td><?= h($finance['updated_by']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Recent Sales & Purchases</h3>
            <span>Latest finance activity</span>
        </div>
        <div class="list-table">
            <?php foreach (array_slice($recentSales, 0, 8) as $sale): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($sale['invoice_number']) ?></strong>
                        <small><?= h($sale['branch_name']) ?> • <?= h($sale['status']) ?></small>
                    </div>
                    <span><?= money($sale['paid_amount']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (empty($recentSales)): ?>
                <div class="list-row"><em>No sales activity available.</em></div>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Branch Profit Comparison</h3>
        <span>Finance performance across branches</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Branch</th>
                    <th>Sales</th>
                    <th>Expenses</th>
                    <th>Losses</th>
                    <th>Refunds</th>
                    <th>Net Profit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branchComparison as $row): ?>
                    <tr>
                        <td><?= h($row['branch_name']) ?></td>
                        <td><?= money($row['sales_total']) ?></td>
                        <td><?= money($row['expense_total']) ?></td>
                        <td><?= money($row['loss_total']) ?></td>
                        <td><?= money($row['refund_total']) ?></td>
                        <td><?= money($row['net_profit']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="section-head">
        <h3>Recent Purchase Source Log</h3>
        <span>Source and amount for recent purchase records</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Purchase</th>
                    <th>Branch</th>
                    <th>Source</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentFinances as $record): ?>
                    <tr>
                        <td><?= h($record['purchase_number']) ?></td>
                        <td><?= h($record['branch_name']) ?></td>
                        <td><?= h($record['money_source']) ?></td>
                        <td><?= money($record['total']) ?></td>
                        <td><?= h($record['status']) ?></td>
                        <td><?= h($record['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentFinances)): ?>
                    <tr><td colspan="6">No purchase activity found for this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
