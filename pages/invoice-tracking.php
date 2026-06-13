<?php
$dateFrom = request_date('date_from', current_month_start());
$dateTo = request_date('date_to', current_month_end());
$statusFilter = trim($_GET['status'] ?? '');
$branchId = has_global_branch_access() ? (isset($_GET['branch_id']) && trim($_GET['branch_id']) !== '' ? (int) $_GET['branch_id'] : null) : current_branch_id();

$allowedStatuses = [
    'PENDING_PAYMENT' => 'Pending Payment',
    'PAID' => 'Paid',
    'ALLOCATED' => 'Allocated',
    'SERVED' => 'Dispensed',
    'CANCELLED' => 'Cancelled',
];

$whereClauses = ['DATE(s.created_at) BETWEEN ? AND ?'];
$params = [$dateFrom, $dateTo];

if ($statusFilter && array_key_exists($statusFilter, $allowedStatuses)) {
    $whereClauses[] = 's.status = ?';
    $params[] = $statusFilter;
}

if ($branchId !== null) {
    $whereClauses[] = 's.branch_id = ?';
    $params[] = $branchId;
}

$whereSql = implode(' AND ', $whereClauses);
$trackedInvoices = fetch_all(
    'SELECT s.*, COALESCE(s.customer_name, p.full_name) AS customer_label, br.name AS branch_name
     FROM sales s
     LEFT JOIN patients p ON p.id = s.patient_id
     LEFT JOIN branches br ON br.id = s.branch_id
     WHERE ' . $whereSql . '
     ORDER BY s.created_at DESC',
    $params
);

$invoiceIds = array_map('intval', array_column($trackedInvoices, 'id'));
$invoiceItemsBySale = [];
$allocationMap = sale_item_allocation_summary($invoiceIds);
if (!empty($invoiceIds)) {
    $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
    $items = fetch_all(
        'SELECT si.*, pr.name AS product_name, b.batch_number, b.expiry_date
         FROM sale_items si
         JOIN products pr ON pr.id = si.product_id
         LEFT JOIN batches b ON b.id = si.batch_id
         WHERE si.sale_id IN (' . $placeholders . ')',
        $invoiceIds
    );
    foreach ($items as $item) {
        $invoiceItemsBySale[(int) $item['sale_id']][] = $item;
    }
}

$trackingData = [];
foreach ($trackedInvoices as $invoice) {
    $trackingData[$invoice['id']] = [
        'invoice_number' => $invoice['invoice_number'],
        'customer' => $invoice['customer_label'],
        'branch' => $invoice['branch_name'] ?: 'Main Branch',
        'total' => $invoice['total'],
        'status' => $allowedStatuses[$invoice['status']] ?? $invoice['status'],
        'raw_status' => $invoice['status'],
        'created_at' => $invoice['created_at'],
        'items' => $invoiceItemsBySale[$invoice['id']] ?? [],
        'allocations' => $allocationMap[$invoice['id']] ?? [],
    ];
}
?>

<section class="card report-filter-card">
    <div class="section-head">
        <h3>Invoice Tracking</h3>
        <span>Track invoice stage and review item details</span>
    </div>
    <form method="get" class="form-grid">
        <input type="hidden" name="page" value="invoice-tracking">
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
        <label>
            <span>Stage</span>
            <select name="status">
                <option value="">All stages</option>
                <?php foreach ($allowedStatuses as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="form-actions full-width">
            <button class="btn-primary" type="submit">Apply Filter</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="section-head">
        <h3>Invoice Stage List</h3>
        <span>Where each invoice is in the workflow</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Branch</th>
                    <th>Total</th>
                    <th>Stage</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trackedInvoices as $invoice): ?>
                <tr>
                    <td><?= h($invoice['invoice_number']) ?></td>
                    <td><?= h($invoice['customer_label']) ?></td>
                    <td><?= h($invoice['branch_name'] ?: 'Main Branch') ?></td>
                    <td><?= money($invoice['total']) ?></td>
                    <td><?= h($allowedStatuses[$invoice['status']] ?? $invoice['status']) ?></td>
                    <td><?= date('M d, H:i', strtotime($invoice['created_at'])) ?></td>
                    <td>
                        <button class="btn-small btn-secondary" type="button" onclick="openInvoiceTrackingModal(<?= (int) $invoice['id'] ?>)">View</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($trackedInvoices) === 0): ?>
                <tr>
                    <td colspan="7">No invoices found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div id="invoiceTrackingModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Invoice Tracking Details</h3>
            <button class="modal-close" onclick="closeInvoiceTrackingModal()">&times;</button>
        </div>
        <div class="modal-body" id="invoiceTrackingContent"></div>
        <div class="modal-footer">
            <button class="btn-primary" type="button" onclick="printInvoiceTracking()">Print Receipt</button>
            <button class="btn-secondary" type="button" onclick="closeInvoiceTrackingModal()">Close</button>
        </div>
    </div>
</div>

<script>
const invoiceTrackingData = <?= json_encode($trackingData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const formatCurrency = (value) => Number(value || 0).toFixed(2);

function openInvoiceTrackingModal(invoiceId) {
    const invoice = invoiceTrackingData[invoiceId];
    if (!invoice) {
        return;
    }

    const rows = invoice.items.length
        ? invoice.items.map(item =>
            `<tr><td>${item.product_name}</td><td>${item.batch_number || '-'}</td><td>${item.expiry_date || '-'}</td><td>${Number(item.quantity || 0)}</td><td>${formatCurrency(item.unit_price)}</td><td>${formatCurrency(item.total)}</td></tr>`
          ).join('')
        : '<tr><td colspan="6">No items found.</td></tr>';
    const allocations = invoice.allocations.length
        ? `<table class="data-table" style="margin-top: 16px; width: 100%;">
            <thead><tr><th>Allocated Product</th><th>Batch</th><th>Expiry</th><th>Allocated Qty</th></tr></thead>
            <tbody>${invoice.allocations.map(item => `<tr><td>${item.product_name}</td><td>${item.batch_number || '-'}</td><td>${item.expiry_date || '-'}</td><td>${Number(item.quantity || 0)}</td></tr>`).join('')}</tbody>
           </table>`
        : '<div style="margin-top:16px;">No batch allocations recorded yet.</div>';

    document.getElementById('invoiceTrackingContent').innerHTML = `
        <div class="invoice-summary">
            <div><strong>Invoice:</strong> ${invoice.invoice_number}</div>
            <div><strong>Customer:</strong> ${invoice.customer}</div>
            <div><strong>Branch:</strong> ${invoice.branch}</div>
            <div><strong>Stage:</strong> ${invoice.status}</div>
            <div><strong>Date:</strong> ${invoice.created_at}</div>
            <div><strong>Total:</strong> ${formatCurrency(invoice.total)}</div>
        </div>
        <table class="data-table" style="margin-top: 16px; width: 100%;">
            <thead>
                <tr><th>Product</th><th>Batch</th><th>Expiry</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
        ${allocations}
    `;

    document.getElementById('invoiceTrackingModal').dataset.currentInvoiceId = invoiceId;
    document.getElementById('invoiceTrackingModal').style.display = 'block';
}

function closeInvoiceTrackingModal() {
    document.getElementById('invoiceTrackingModal').style.display = 'none';
}

function printInvoiceTracking(invoiceId) {
    const modal = document.getElementById('invoiceTrackingModal');
    const invoice = invoiceId ? invoiceTrackingData[invoiceId] : invoiceTrackingData[modal.dataset.currentInvoiceId];
    const content = document.getElementById('invoiceTrackingContent');
    if (!content || !invoice) {
        return;
    }
    openPrintPopup(content, `Invoice-${invoice.invoice_number}`);
}
</script>
