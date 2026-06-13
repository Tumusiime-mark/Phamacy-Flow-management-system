<?php
$expenseDateFrom = request_date('expense_from', current_month_start());
$expenseDateTo = request_date('expense_to', current_month_end());
$expensePeriod = isset($_GET['expense_period']) ? trim((string) $_GET['expense_period']) : 'monthly';
if (!in_array($expensePeriod, ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'], true)) {
    $expensePeriod = 'monthly';
}
$export = $_GET['export'] ?? '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!can_manage_finance()) {
        flash('error', 'You do not have permission to manage expenditures.');
        redirect('index.php?page=expenditures');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'save_expenditure') {
        $amount = (float) ($_POST['amount'] ?? 0);
        $category = trim($_POST['category'] ?? 'General');
        $description = trim($_POST['description'] ?? '');
        $responsible = trim($_POST['responsible'] ?? selected_user()['name']);
        $branchId = (int) ($_POST['branch_id'] ?? current_branch_id());
        $expenseDate = request_date('expense_date', today());
        $periodType = trim($_POST['period_type'] ?? 'Daily');

        execute_query(
            'INSERT INTO expenditures (amount, category, description, responsible, branch_id, expense_date, period_type, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$amount, $category, $description, $responsible, $branchId, $expenseDate, $periodType, selected_user()['name'], now()]
        );

        audit_log('finance', 'create', 'Recorded expenditure', 'expenditure', null, ['amount' => $amount, 'category' => $category, 'branch_id' => $branchId]);
        log_activity('Recorded expenditure ' . $category . ' of ' . money($amount));
        flash('success', 'Expenditure recorded.');
        redirect('index.php?page=expenditures');
    }
}

$expenseList = expenditures_list($expenseDateFrom, $expenseDateTo);
$expenseReport = expenditure_report($expensePeriod, $expenseDateFrom, $expenseDateTo);
$expenseTotal = array_sum(array_map(static fn(array $expense): float => (float) $expense['amount'], $expenseList));
$branches = branches_list();

if ($export === 'expenditures' && can_manage_finance()) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="expenditures-' . date('YmdHis') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Category', 'Amount', 'Responsible', 'Branch', 'Period Type', 'Description']);
    foreach ($expenseList as $expense) {
        fputcsv($output, [
            $expense['expense_date'],
            $expense['category'],
            $expense['amount'],
            $expense['responsible'],
            $expense['branch_name'] ?? '',
            $expense['period_type'],
            $expense['description'],
        ]);
    }
    fclose($output);
    exit;
}
?>

<section class="card report-filter-card">
    <div class="section-head">
        <h3>Expenditure Filters</h3>
        <span>Select range and report view</span>
    </div>
    <form method="get" class="form-grid">
        <input type="hidden" name="page" value="expenditures">
        <label>
            <span>Date From</span>
            <input type="date" name="expense_from" value="<?= h($expenseDateFrom) ?>">
        </label>
        <label>
            <span>Date To</span>
            <input type="date" name="expense_to" value="<?= h($expenseDateTo) ?>">
        </label>
        <label>
            <span>View</span>
            <select name="expense_period">
                <?php foreach (['daily', 'weekly', 'monthly', 'quarterly', 'yearly'] as $period): ?>
                    <option value="<?= h($period) ?>" <?= ($expensePeriod === $period) ? 'selected' : '' ?>><?= ucfirst($period) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="form-actions full-width">
            <button class="btn-primary" type="submit">Apply</button>
            <a class="btn-secondary" href="index.php?page=expenditures&export=expenditures&expense_from=<?= h($expenseDateFrom) ?>&expense_to=<?= h($expenseDateTo) ?>">Export CSV</a>
        </div>
    </form>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Expenditure Summary</h3>
            <span>Total within selected range</span>
        </div>
        <div class="list-table">
            <div class="list-row">
                <strong>Total</strong>
                <span><?= money($expenseTotal) ?></span>
            </div>
            <?php foreach ($expenseReport as $group): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($group['period_label']) ?></strong>
                    </div>
                    <span><?= money((float) $group['total_amount']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Add Expenditure</h3>
            <span>Only responsible finance roles can record expenses</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_expenditure">
            <label>
                <span>Amount</span>
                <input name="amount" type="number" step="0.01" min="0" required>
            </label>
            <label>
                <span>Category</span>
                <input name="category" required placeholder="Example: Supplies, Utilities">
            </label>
            <label>
                <span>Responsible</span>
                <input name="responsible" value="<?= h(selected_user()['name']) ?>">
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
                <span>Date</span>
                <input name="expense_date" type="date" value="<?= h(today()) ?>" required>
            </label>
            <label>
                <span>Period Type</span>
                <select name="period_type">
                    <?php foreach (['Daily', 'Weekly', 'Monthly', 'Quarterly', 'Yearly'] as $period): ?>
                        <option value="<?= h($period) ?>"><?= h($period) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="full-width">
                <span>Description</span>
                <textarea name="description" rows="4"></textarea>
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Record Expense</button>
            </div>
        </form>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Expense Details</h3>
        <span>Expense ledger for the selected date range</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Responsible</th>
                <th>Branch</th>
                <th>Period</th>
                <th>Description</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($expenseList as $expense): ?>
                <tr>
                    <td><?= h($expense['expense_date']) ?></td>
                    <td><?= h($expense['category']) ?></td>
                    <td><?= money((float) $expense['amount']) ?></td>
                    <td><?= h($expense['responsible']) ?></td>
                    <td><?= h($expense['branch_name'] ?? '') ?></td>
                    <td><?= h($expense['period_type']) ?></td>
                    <td><?= h($expense['description']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($expenseList) === 0): ?>
                <tr><td colspan="7">No expenditures found for this range.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
