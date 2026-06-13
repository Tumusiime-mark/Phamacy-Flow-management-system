<?php
$dateFrom = request_date('date_from', current_month_start());
$dateTo = request_date('date_to', current_month_end());

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_mobile_payment') {
        execute_query(
            'INSERT INTO payment_transactions (sale_id, branch_id, provider, phone_number, amount, external_reference, provider_reference, status, verification_message, payload_snapshot, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $_POST['sale_id'] ? (int) $_POST['sale_id'] : null,
                current_branch_id(),
                trim($_POST['provider'] ?? 'MTN MoMo'),
                trim($_POST['phone_number'] ?? ''),
                (float) $_POST['amount'],
                strtoupper(str_replace(' ', '-', trim($_POST['provider'] ?? 'provider'))) . '-REQ-' . date('YmdHis'),
                strtoupper(str_replace(' ', '-', trim($_POST['provider'] ?? 'provider'))) . '-VERIFY-' . rand(1000, 9999),
                'Pending',
                'Payment request created. Awaiting verification.',
                json_encode([
                    'provider' => trim($_POST['provider'] ?? ''),
                    'phone' => trim($_POST['phone_number'] ?? ''),
                    'amount' => (float) $_POST['amount'],
                    'api_key' => trim($_POST['provider'] ?? '') === 'Airtel Money' ? setting('airtel_money_api_key', '') : setting('mtn_collection_api_key', ''),
                    'mode' => 'demo-integration',
                ]),
                selected_user()['name'],
                now(),
            ]
        );
        flash('success', 'Mobile money payment request logged.');
        redirect('index.php?page=payments&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo));
    }

    if ($action === 'verify_payment') {
        $transaction = fetch_one('SELECT * FROM payment_transactions WHERE id = ?', [(int) $_POST['transaction_id']]);
        if ($transaction) {
            $status = ((int) $transaction['id'] % 2 === 0) ? 'Verified' : 'Failed';
            $message = $status === 'Verified'
                ? 'Provider verification succeeded in demo mode.'
                : 'Provider verification failed in demo mode. Retry payment or confirm manually.';
            execute_query(
                'UPDATE payment_transactions SET status = ?, verification_message = ? WHERE id = ?',
                [$status, $message, (int) $transaction['id']]
            );
            log_activity('Verified payment transaction ' . $transaction['external_reference']);
            audit_log('payments', 'verify', 'Verified mobile payment transaction', 'payment_transaction', (int) $transaction['id']);
            flash('success', 'Payment verification updated.');
        }
        redirect('index.php?page=payments&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo));
    }

    if ($action === 'save_mobile_api_settings') {
        require_admin();
        save_setting('mtn_collection_api_key', trim($_POST['mtn_collection_api_key'] ?? ''));
        save_setting('mtn_collection_api_secret', trim($_POST['mtn_collection_api_secret'] ?? ''));
        save_setting('airtel_money_api_key', trim($_POST['airtel_money_api_key'] ?? ''));
        save_setting('airtel_money_api_secret', trim($_POST['airtel_money_api_secret'] ?? ''));
        flash('success', 'Mobile money API settings saved.');
        redirect('index.php?page=payments&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo));
    }

    if ($action === 'close_day_sales') {
        if (!can_manage_finance()) {
            flash('error', 'You do not have permission to close sales.');
            redirect('index.php?page=payments');
        }
        create_finance_closure('Daily', trim($_POST['period_start'] ?? today()), trim($_POST['period_end'] ?? today()), trim($_POST['note'] ?? 'Daily sales close'));
        flash('success', 'Daily sales closed successfully.');
        redirect('index.php?page=payments&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo));
    }

    if ($action === 'close_month_sales') {
        if (!can_manage_finance()) {
            flash('error', 'You do not have permission to close monthly sales.');
            redirect('index.php?page=payments');
        }
        create_finance_closure('Monthly', trim($_POST['period_start'] ?? current_month_start()), trim($_POST['period_end'] ?? current_month_end()), trim($_POST['note'] ?? 'Monthly close'));
        flash('success', 'Monthly accounting close saved.');
        redirect('index.php?page=payments&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo));
    }
}

$sales = fetch_all('SELECT id, invoice_number, total FROM sales ORDER BY id DESC LIMIT 20');
$transactions = fetch_all(
    'SELECT pt.*, s.invoice_number, br.name AS branch_name
     FROM payment_transactions pt
     LEFT JOIN sales s ON s.id = pt.sale_id
     LEFT JOIN branches br ON br.id = pt.branch_id
     WHERE date(pt.created_at) BETWEEN ? AND ?
     ORDER BY pt.id DESC',
    [$dateFrom, $dateTo]
);
$paymentSummary = fetch_one(
    'SELECT COUNT(*) AS transaction_count,
            COALESCE(SUM(amount), 0) AS total_amount
     FROM payment_transactions
     WHERE date(created_at) BETWEEN ? AND ?',
    [$dateFrom, $dateTo]
);
$financeSummary = sales_summary_between($dateFrom, $dateTo);
$closures = finance_closures_list();
?>

<section class="card report-filter-card">
    <div class="section-head">
        <h3>Payments Range</h3>
        <span>Default current month</span>
    </div>
    <form method="get" class="form-grid">
        <input type="hidden" name="page" value="payments">
        <label>
            <span>Date From</span>
            <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
        </label>
        <label>
            <span>Date To</span>
            <input type="date" name="date_to" value="<?= h($dateTo) ?>">
        </label>
        <div class="form-actions full-width">
            <button class="btn-primary" type="submit">Apply Range</button>
        </div>
    </form>
</section>

<section class="grid metrics-grid">
    <article class="card metric-card highlight">
        <span>Mobile Collections</span>
        <strong><?= money((float) ($paymentSummary['total_amount'] ?? 0)) ?></strong>
        <small>Only mobile money tracked here</small>
    </article>
    <article class="card metric-card">
        <span>Sales Total</span>
        <strong><?= money($financeSummary['sales_total']) ?></strong>
        <small>For selected period</small>
    </article>
    <article class="card metric-card">
        <span>Gross Profit</span>
        <strong><?= money($financeSummary['gross_profit']) ?></strong>
        <small>Sales minus cost</small>
    </article>
    <article class="card metric-card">
        <span>Outstanding</span>
        <strong><?= money($financeSummary['balance_total']) ?></strong>
        <small>Uncleared balances</small>
    </article>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Mobile Money Integration</h3>
            <span>MTN MoMo and Airtel Money only</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="create_mobile_payment">
            <label>
                <span>Related Sale</span>
                <select name="sale_id">
                    <option value="">Standalone transaction</option>
                    <?php foreach ($sales as $sale): ?>
                        <option value="<?= (int) $sale['id'] ?>"><?= h($sale['invoice_number']) ?> - <?= money($sale['total']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Provider</span>
                <select name="provider">
                    <option>MTN MoMo</option>
                    <option>Airtel Money</option>
                </select>
            </label>
            <label>
                <span>Phone Number</span>
                <input name="phone_number" required>
            </label>
            <label>
                <span>Amount</span>
                <input type="number" step="0.01" name="amount" required>
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Create Payment Request</button>
            </div>
        </form>
    </article>

    <article class="card stack">
        <div class="section-head">
            <h3>Finance Controls</h3>
            <span>Sales accounting close</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="close_day_sales">
            <label>
                <span>Daily Close Start</span>
                <input type="date" name="period_start" value="<?= h(today()) ?>">
            </label>
            <label>
                <span>Daily Close End</span>
                <input type="date" name="period_end" value="<?= h(today()) ?>">
            </label>
            <label class="full-width">
                <span>Note</span>
                <input name="note" value="Daily sales close">
            </label>
            <div class="form-actions full-width">
                <button class="btn-secondary" type="submit">Close Sales for the Day</button>
            </div>
        </form>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="close_month_sales">
            <label>
                <span>Month Start</span>
                <input type="date" name="period_start" value="<?= h(current_month_start()) ?>">
            </label>
            <label>
                <span>Month End</span>
                <input type="date" name="period_end" value="<?= h(current_month_end()) ?>">
            </label>
            <label class="full-width">
                <span>Note</span>
                <input name="note" value="Monthly close by accountant">
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Close Monthly Accounts</button>
            </div>
        </form>
    </article>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Mobile Money API</h3>
            <span>Save provider credentials</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_mobile_api_settings">
            <label>
                <span>MTN API Key</span>
                <input name="mtn_collection_api_key" value="<?= h(setting('mtn_collection_api_key', '')) ?>">
            </label>
            <label>
                <span>MTN API Secret</span>
                <input name="mtn_collection_api_secret" value="<?= h(setting('mtn_collection_api_secret', '')) ?>">
            </label>
            <label>
                <span>Airtel API Key</span>
                <input name="airtel_money_api_key" value="<?= h(setting('airtel_money_api_key', '')) ?>">
            </label>
            <label>
                <span>Airtel API Secret</span>
                <input name="airtel_money_api_secret" value="<?= h(setting('airtel_money_api_secret', '')) ?>">
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Save API Settings</button>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Recent Closings</h3>
            <span>Finance snapshots</span>
        </div>
        <div class="list-table">
            <?php foreach (array_slice($closures, 0, 6) as $closure): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($closure['period_type']) ?> Close</strong>
                        <small><?= h($closure['period_start']) ?> to <?= h($closure['period_end']) ?> | <?= h($closure['closed_by']) ?></small>
                    </div>
                    <span><?= money($closure['net_profit']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Transaction Log</h3>
        <span><?= count($transactions) ?> mobile payment records</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Provider</th>
                <th>Invoice</th>
                <th>Branch</th>
                <th>Phone</th>
                <th>Amount</th>
                <th>Reference</th>
                <th>Status</th>
                <th>Verification</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td><?= h($transaction['provider']) ?></td>
                    <td><?= h($transaction['invoice_number'] ?: '-') ?></td>
                    <td><?= h($transaction['branch_name'] ?: '-') ?></td>
                    <td><?= h($transaction['phone_number']) ?></td>
                    <td><?= money($transaction['amount']) ?></td>
                    <td><?= h($transaction['external_reference']) ?></td>
                    <td><?= h($transaction['status']) ?></td>
                    <td><?= h($transaction['verification_message']) ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="verify_payment">
                            <input type="hidden" name="transaction_id" value="<?= (int) $transaction['id'] ?>">
                            <button class="btn-secondary" type="submit">Verify</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
