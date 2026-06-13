<?php
$dateFrom = request_date('date_from', current_month_start());
$dateTo = request_date('date_to', current_month_end());
$branchId = has_global_branch_access() ? (isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : null) : current_branch_id();

$whereBranch = $branchId !== null ? ' AND s.branch_id = ?' : '';
$dispensedInvoices = fetch_all(
    'SELECT s.*, COALESCE(s.customer_name, p.full_name) AS customer_label, br.name AS branch_name
     FROM sales s
     LEFT JOIN patients p ON p.id = s.patient_id
     LEFT JOIN branches br ON br.id = s.branch_id
     WHERE s.status = "SERVED"' . $whereBranch . ' AND DATE(s.created_at) BETWEEN ? AND ?
     ORDER BY s.created_at DESC',
    $branchId !== null ? [$branchId, $dateFrom, $dateTo] : [$dateFrom, $dateTo]
);

$invoiceIds = array_map('intval', array_column($dispensedInvoices, 'id'));
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

$dispensedInvoiceMap = [];
foreach ($dispensedInvoices as $invoice) {
    $dispensedInvoiceMap[$invoice['id']] = [
        'invoice_number' => $invoice['invoice_number'],
        'customer' => $invoice['customer_label'],
        'branch' => $invoice['branch_name'] ?: 'Main Branch',
        'total' => $invoice['total'],
        'created_at' => $invoice['created_at'],
        'items' => $invoiceItemsBySale[$invoice['id']] ?? [],
        'allocations' => $allocationMap[$invoice['id']] ?? [],
    ];
}
?>

<section class="card report-filter-card">
    <div class="section-head">
        <h3>Dispensed Invoices</h3>
        <span>View successfully dispensed invoices by date range</span>
    </div>
    <form method="get" class="form-grid">
        <input type="hidden" name="page" value="dispensed-invoices">
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
        </div>
    </form>
</section>

<section class="card">
    <div class="section-head">
        <h3>Dispensed Invoice List</h3>
        <span>Invoices marked as served</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Branch</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dispensedInvoices as $invoice): ?>
                <tr>
                    <td><?= h($invoice['invoice_number']) ?></td>
                    <td><?= h($invoice['customer_label']) ?></td>
                    <td><?= h($invoice['branch_name'] ?: 'Main Branch') ?></td>
                    <td><?= money($invoice['total']) ?></td>
                    <td><?= date('M d, H:i', strtotime($invoice['created_at'])) ?></td>
                    <td>
                        <button class="btn-small btn-secondary" type="button" onclick="openDispensedInvoiceModal(<?= (int) $invoice['id'] ?>)">View</button>
                        <button class="btn-small btn-primary" type="button" onclick="printDispensedInvoice(<?= (int) $invoice['id'] ?>)">Print</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($dispensedInvoices) === 0): ?>
                <tr>
                    <td colspan="6">No dispensed invoices found for this range.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div id="dispensedInvoiceModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Dispensed Invoice Details</h3>
            <button class="modal-close" onclick="closeDispensedInvoiceModal()">&times;</button>
        </div>
        <div class="modal-body" id="dispensedInvoiceContent"></div>
        <div class="modal-footer">
            <button class="btn-primary" type="button" onclick="printDispensedInvoice()">Print Receipt</button>
            <button class="btn-secondary" type="button" onclick="closeDispensedInvoiceModal()">Close</button>
        </div>
    </div>
</div>

<script>
const dispensedInvoiceData = <?= json_encode($dispensedInvoiceMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const formatCurrency = (value) => Number(value || 0).toFixed(2);

function openDispensedInvoiceModal(invoiceId) {
    const invoice = dispensedInvoiceData[invoiceId];
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
            <thead><tr><th>Dispensed Product</th><th>Allocated Batch</th><th>Expiry</th><th>Qty</th></tr></thead>
            <tbody>${invoice.allocations.map(item => `<tr><td>${item.product_name}</td><td>${item.batch_number || '-'}</td><td>${item.expiry_date || '-'}</td><td>${Number(item.quantity || 0)}</td></tr>`).join('')}</tbody>
           </table>`
        : '<div style="margin-top:16px;">No allocation batches recorded.</div>';

    document.getElementById('dispensedInvoiceContent').innerHTML = `
        <div class="invoice-summary">
            <div><strong>Invoice:</strong> ${invoice.invoice_number}</div>
            <div><strong>Customer:</strong> ${invoice.customer}</div>
            <div><strong>Branch:</strong> ${invoice.branch}</div>
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

    document.getElementById('dispensedInvoiceModal').dataset.currentInvoiceId = invoiceId;
    document.getElementById('dispensedInvoiceModal').style.display = 'block';
}

function closeDispensedInvoiceModal() {
    document.getElementById('dispensedInvoiceModal').style.display = 'none';
}

function printDispensedInvoice(invoiceId) {
    const modal = document.getElementById('dispensedInvoiceModal');
    const invoice = invoiceId ? dispensedInvoiceData[invoiceId] : dispensedInvoiceData[modal.dataset.currentInvoiceId];
    const content = document.getElementById('dispensedInvoiceContent');
    if (!content || !invoice) {
        return;
    }
    openPrintPopup(content, `Invoice-${invoice.invoice_number}`);
}
</script>
