<?php
$branchId = has_global_branch_access() ? (isset($_GET['branch_id']) && trim((string) $_GET['branch_id']) !== '' ? (int) $_GET['branch_id'] : null) : current_branch_id();
$branches = branches_list();
$summary = drug_health_summary($branchId);
$rows = drug_health_tracking_rows($branchId);

if (($_GET['export'] ?? '') === 'drug_health') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="drug-health-safety-' . date('YmdHis') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Drug', 'Brand', 'Dosage', 'Batch', 'Branch', 'Location', 'Mapped', 'Manufacturing Date', 'Expiry Date', 'Qty', 'Supplier', 'Storage Requirement', 'Physical Condition', 'Safety Status', 'Quarantine', 'Recall', 'Note']);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['drug_name'],
            $row['brand_name'],
            $row['dosage'],
            $row['batch_number'],
            $row['branch_name'],
            $row['location'],
            $row['structured_location_code'],
            $row['manufacturing_date'],
            $row['expiry_date'],
            $row['qty'],
            $row['supplier_name'],
            $row['storage_condition'],
            $row['physical_condition'],
            $row['safety_status'],
            $row['quarantine_status'],
            $row['recall_status'],
            $row['recall_note'],
        ]);
    }
    fclose($output);
    exit;
}
?>

<section class="card">
    <div class="section-head">
        <h3>Drug Health and Safety Tracking</h3>
        <span>Batch-level monitoring from receiving to disposal</span>
    </div>
    <p class="page-intro">
        This module tracks each medicine batch using expiry, storage, inspection, quarantine, recall, and dispensing controls so only safe and compliant stock reaches patients.
    </p>
    <form method="get" class="form-grid">
        <input type="hidden" name="page" value="drug-health">
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
            <button class="btn-primary" type="submit">Apply Filter</button>
            <a class="btn-secondary" href="index.php?page=drug-health<?= $branchId !== null ? '&branch_id=' . (int) $branchId : '' ?>&export=drug_health">Export CSV</a>
            <a class="btn-secondary" href="index.php?page=inventory">Open Batch Control</a>
        </div>
    </form>
</section>

<section class="grid metrics-grid">
    <article class="card metric-card"><span>Safe</span><strong><?= (int) ($summary['safe_qty'] ?? 0) ?></strong><small class="status-safe">Green</small></article>
    <article class="card metric-card"><span>Near Expiry</span><strong><?= (int) ($summary['near_expiry_qty'] ?? 0) ?></strong><small class="status-near">Yellow</small></article>
    <article class="card metric-card"><span>Critical Expiry</span><strong><?= (int) ($summary['critical_expiry_qty'] ?? 0) ?></strong><small class="status-critical">Orange</small></article>
    <article class="card metric-card"><span>Expired / Unsafe</span><strong><?= (int) (($summary['expired_qty'] ?? 0) + ($summary['damaged_qty'] ?? 0)) ?></strong><small class="status-expired">Red</small></article>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Lifecycle Controls</h3>
            <span>What the system monitors</span>
        </div>
        <div class="list-table">
            <div class="list-row"><strong>Traceability</strong><span>Drug, batch, supplier, manufacturer, branch, store, rack, shelf, bin</span></div>
            <div class="list-row"><strong>Expiry logic</strong><span>Safe, near expiry, critical expiry, expired</span></div>
            <div class="list-row"><strong>Storage control</strong><span>Room, refrigerated, frozen, controlled, light-sensitive, hazardous</span></div>
            <div class="list-row"><strong>Inspection status</strong><span>Sealed, opened, damaged, quarantined, recalled, returned</span></div>
            <div class="list-row"><strong>Compliance trail</strong><span>Receiving, dispensing, transfer, adjustment, return, recall, destruction</span></div>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Safety Action Summary</h3>
            <span>Blocked stock requiring attention</span>
        </div>
        <div class="list-table">
            <div class="list-row"><strong>Quarantined</strong><span><?= (int) ($summary['quarantined_qty'] ?? 0) ?></span></div>
            <div class="list-row"><strong>Recalled</strong><span><?= (int) ($summary['recalled_qty'] ?? 0) ?></span></div>
            <div class="list-row"><strong>Damaged</strong><span><?= (int) ($summary['damaged_qty'] ?? 0) ?></span></div>
            <div class="list-row"><strong>Expired</strong><span><?= (int) ($summary['expired_qty'] ?? 0) ?></span></div>
            <div class="list-row"><strong>Operational action</strong><span>Use FEFO, quarantine suspect stock, return or destroy unsafe stock</span></div>
        </div>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Batch Tracking and Safety Monitoring</h3>
        <span>Production-level drug oversight</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Branch</th>
                    <th>Batch</th>
                    <th>Location</th>
                    <th>Mapped</th>
                    <th>Expiry Date</th>
                    <th>Qty</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Safety</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <?php
                $status = 'Safe';
                if (strtotime((string) $row['expiry_date']) < strtotime(today())) {
                    $status = 'Expired';
                } elseif (strtotime((string) $row['expiry_date']) <= strtotime('+30 days')) {
                    $status = 'Critical Expiry';
                } elseif (strtotime((string) $row['expiry_date']) <= strtotime('+' . int_setting('expiry_alert_days', 90) . ' days')) {
                    $status = 'Near Expiry';
                }
                ?>
                <tr>
                    <td><?= h($row['drug_name']) ?></td>
                    <td><?= h($row['branch_name']) ?></td>
                    <td><?= h($row['batch_number']) ?></td>
                    <td><?= h($row['location'] ?: '-') ?></td>
                    <td><?= h($row['structured_location_code'] ?: '-') ?></td>
                    <td><?= h($row['expiry_date']) ?></td>
                    <td><?= (int) $row['qty'] ?></td>
                    <td><?= h($row['supplier_name']) ?></td>
                    <td><?= h($status) ?></td>
                    <td><?= h(($row['safety_status'] ?? 'Safe') . ' / ' . ($row['quarantine_status'] ?? 'Clear') . ' / ' . ($row['recall_status'] ?? 'None')) ?></td>
                    <td><a class="text-link" href="index.php?page=inventory&batch_id=<?= (int) $row['id'] ?>">Inspect</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
