<?php
require_page_access('audit');

$dateFrom = request_date('date_from', current_month_start());
$dateTo = request_date('date_to', current_month_end());

$auditLogs = fetch_all(
    'SELECT * FROM audit_logs
     WHERE date(created_at) BETWEEN ? AND ?
     ORDER BY created_at DESC LIMIT 100',
    [$dateFrom, $dateTo]
);
$activityLogs = fetch_all(
    'SELECT * FROM activity_logs
     WHERE date(created_at) BETWEEN ? AND ?
     ORDER BY created_at DESC LIMIT 50',
    [$dateFrom, $dateTo]
);
$salesReturns = fetch_all(
    'SELECT sr.*, s.invoice_number, p.full_name
     FROM sales_returns sr
     LEFT JOIN sales s ON s.id = sr.sale_id
     LEFT JOIN patients p ON p.id = sr.patient_id
     WHERE date(sr.created_at) BETWEEN ? AND ?
     ORDER BY sr.created_at DESC
     LIMIT 30',
    [$dateFrom, $dateTo]
);
?>
<section class="card report-filter-card">
    <div class="section-head">
        <h3>Audit Range</h3>
        <span>Default current month</span>
    </div>
    <form method="get" class="form-grid">
        <input type="hidden" name="page" value="audit">
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

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>User Activity</h3>
            <span>Operational actions</span>
        </div>
        <div class="list-table">
            <?php foreach ($activityLogs as $log): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($log['user_name']) ?></strong>
                        <small><?= h($log['role']) ?> | <?= h($log['action']) ?></small>
                    </div>
                    <span><?= h($log['created_at']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Sales Exchanges</h3>
            <span>Returns and reversals</span>
        </div>
        <div class="list-table">
            <?php if (!$salesReturns): ?>
                <div class="empty-state">No sales exchange records yet.</div>
            <?php endif; ?>
            <?php foreach ($salesReturns as $item): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($item['invoice_number'] ?: 'Manual return') ?></strong>
                        <small><?= h($item['full_name'] ?: 'Walk-in customer') ?> | <?= h($item['reason']) ?></small>
                    </div>
                    <span><?= money($item['refund_amount']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Audit Trail</h3>
        <span><?= h($dateFrom) ?> to <?= h($dateTo) ?></span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Module</th>
                <th>Action</th>
                <th>Summary</th>
                <th>User</th>
                <th>Role</th>
                <th>Time</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($auditLogs as $log): ?>
                <tr>
                    <td><?= h($log['module']) ?></td>
                    <td><?= h($log['action']) ?></td>
                    <td><?= h($log['summary']) ?></td>
                    <td><?= h($log['user_name']) ?></td>
                    <td><?= h($log['role']) ?></td>
                    <td><?= h($log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
