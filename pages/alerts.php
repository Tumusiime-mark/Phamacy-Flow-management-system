<?php
$days = int_setting('expiry_alert_days', 90);
$lowStock = low_stock_items();
$expiringSoon = expiring_batches($days);
$expired = expired_batches();
?>
<section class="grid metrics-grid">
    <article class="card metric-card highlight">
        <span>Expired Batches</span>
        <strong><?= count($expired) ?></strong>
        <small>Blocked from sale</small>
    </article>
    <article class="card metric-card">
        <span>Expiring Soon</span>
        <strong><?= count($expiringSoon) ?></strong>
        <small>Within <?= $days ?> days</small>
    </article>
    <article class="card metric-card">
        <span>Low Stock</span>
        <strong><?= count($lowStock) ?></strong>
        <small>Needs reorder attention</small>
    </article>
    <article class="card profile-summary">
        <span>Current Branch</span>
        <strong><?= h(selected_user()['branch_name'] ?? 'Main Branch') ?></strong>
        <small>Live monitoring</small>
    </article>
</section>

<section class="grid dashboard-grid">
    <article class="card">
        <div class="section-head">
            <h3>Expired Drugs</h3>
            <span>Sale blocked automatically</span>
        </div>
        <div class="list-table">
            <?php if (!$expired): ?>
                <div class="empty-state">No expired batches found.</div>
            <?php endif; ?>
            <?php foreach (array_slice($expired, 0, 8) as $item): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($item['product_name']) ?></strong>
                        <small>Batch <?= h($item['batch_number']) ?> | <?= h($item['branch_name']) ?></small>
                    </div>
                    <span class="pill danger"><?= h($item['expiry_date']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Soon Expiring</h3>
            <span>Proactive notification list</span>
        </div>
        <div class="list-table">
            <?php if (!$expiringSoon): ?>
                <div class="empty-state">No batches close to expiry.</div>
            <?php endif; ?>
            <?php foreach (array_slice($expiringSoon, 0, 8) as $item): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($item['product_name']) ?></strong>
                        <small>Batch <?= h($item['batch_number']) ?> | Qty <?= (int) $item['quantity'] ?></small>
                    </div>
                    <span class="pill warning"><?= h($item['expiry_date']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card wide">
        <div class="section-head">
            <h3>Low Stock Alerts</h3>
            <span>Fast reorder dashboard</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Drug</th>
                    <th>Available Stock</th>
                    <th>Minimum Level</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($lowStock as $item): ?>
                    <tr>
                        <td><?= h($item['name']) ?></td>
                        <td><?= (int) $item['qty'] ?></td>
                        <td><?= (int) $item['minimum_stock_level'] ?></td>
                        <td><?= (int) $item['qty'] === 0 ? 'Out of stock' : 'Reorder now' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
