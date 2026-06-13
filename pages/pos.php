<?php
$currentBranchId = current_branch_id();
$currentBranch = current_branch();
$products = products_with_stock($currentBranchId);
$patients = patients_list();
$categories = categories();
$pharmacy = pharmacy_details();
$defaultTaxRate = (float) setting('tax_rate', '0');
$receiptMessage = setting('receipt_message', 'Thank you my Dear Customer');
$prescriptions = fetch_all(
    'SELECT pr.*, pt.full_name
     FROM prescriptions pr
     JOIN patients pt ON pt.id = pr.patient_id
     WHERE pr.status IN ("Open", "Partially Dispensed")
     ORDER BY pr.created_at DESC'
);

if (!isset($_SESSION['pos_sale_mode'])) {
    $_SESSION['pos_sale_mode'] = 'Retail';
}

$saleMode = in_array($_SESSION['pos_sale_mode'], ['Retail', 'Wholesale'], true) ? $_SESSION['pos_sale_mode'] : 'Retail';

$applyCartPricing = static function (array $cart, string $mode): array {
    foreach ($cart as $key => $item) {
        $retail = (float) ($item['retail_price'] ?? $item['unit_price'] ?? 0);
        $wholesale = (float) ($item['wholesale_price'] ?? $retail);
        if (empty($item['price_overridden'])) {
            $cart[$key]['unit_price'] = $mode === 'Wholesale' ? $wholesale : $retail;
        }
        $cart[$key]['sale_mode'] = $mode;
    }
    return $cart;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    $cart = cart();

    if ($action === 'set_sale_mode') {
        $saleMode = ($_POST['sale_mode'] ?? 'Retail') === 'Wholesale' ? 'Wholesale' : 'Retail';
        $_SESSION['pos_sale_mode'] = $saleMode;
        set_cart($applyCartPricing($cart, $saleMode));
        flash('success', $saleMode . ' sale mode selected.');
        redirect('index.php?page=pos');
    }

    if ($action === 'add_to_cart') {
        $productId = (int) $_POST['product_id'];
        $product = first_available_batch($productId, $currentBranchId);

        if (!$product) {
            flash('error', 'This product is out of stock in your branch.');
            redirect('index.php?page=pos');
        }

        if (($product['expiry_status'] ?? '') === 'Expired') {
            flash('error', 'Expired drug blocked. This batch cannot be sold.');
            redirect('index.php?page=pos');
        }

        $key = (string) $productId;
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        $retailPrice = (float) ($product['unit_price'] ?? 0);
        $wholesalePrice = (float) ($product['wholesale_price'] ?? $retailPrice);
        if (!isset($cart[$key])) {
            $cart[$key] = [
                'product_id' => $productId,
                'name' => $product['product_name'],
                'batch_id' => $product['id'],
                'batch_number' => $product['batch_number'],
                'expiry_date' => $product['expiry_date'],
                'expiry_status' => $product['expiry_status'],
                'quantity' => 0,
                'unit_price' => $saleMode === 'Wholesale' ? $wholesalePrice : $retailPrice,
                'retail_price' => $retailPrice,
                'wholesale_price' => $wholesalePrice,
                'max_qty' => (int) $product['quantity'],
                'controlled_drug' => (int) $product['controlled_drug'],
                'price_overridden' => 0,
                'sale_mode' => $saleMode,
            ];
        }
        $cart[$key]['quantity'] = min($cart[$key]['quantity'] + $qty, $cart[$key]['max_qty']);
        set_cart($cart);
        flash('success', ($product['expiry_status'] === 'Expiring Soon' ? 'Soon expiring notification: ' : '') . 'Item added to cart.');
        redirect('index.php?page=pos');
    }

    if ($action === 'remove_cart_item') {
        unset($cart[(string) (int) $_POST['product_id']]);
        set_cart($cart);
        flash('success', 'Item removed from cart.');
        redirect('index.php?page=pos');
    }

    if ($action === 'update_cart_item') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $unitPrice = max(0, (float) ($_POST['unit_price'] ?? 0));
        $key = (string) $productId;
        if (isset($cart[$key])) {
            $cart[$key]['quantity'] = min($quantity, (int) $cart[$key]['max_qty']);
            if ($unitPrice > 0) {
                $cart[$key]['unit_price'] = $unitPrice;
                $cart[$key]['price_overridden'] = 1;
            }
            set_cart($cart);
            flash('success', 'Cart item updated.');
        }
        redirect('index.php?page=pos');
    }

    if ($action === 'checkout') {
        if (empty($cart)) {
            flash('error', 'Cart is empty.');
            redirect('index.php?page=pos');
        }

        $saleMode = ($_POST['sale_mode'] ?? 'Retail') === 'Wholesale' ? 'Wholesale' : 'Retail';
        $_SESSION['pos_sale_mode'] = $saleMode;
        $cart = $applyCartPricing($cart, $saleMode);

        $hasControlledDrug = false;
        $subtotal = 0.0;
        foreach ($cart as $item) {
            if (strtotime($item['expiry_date']) < strtotime(today())) {
                flash('error', 'Expired drug blocking is active. Remove expired items before checkout.');
                redirect('index.php?page=pos');
            }
            $subtotal += $item['quantity'] * $item['unit_price'];
            if (!empty($item['controlled_drug'])) {
                $hasControlledDrug = true;
            }
        }

        $prescriptionId = $_POST['prescription_id'] ?: null;
        if ($hasControlledDrug && !$prescriptionId) {
            flash('error', 'Controlled drugs require a linked prescription before checkout.');
            redirect('index.php?page=pos');
        }

        $selectedPatientId = $_POST['patient_id'] ?: null;
        $selectedPatient = $selectedPatientId ? fetch_one('SELECT * FROM patients WHERE id = ?', [(int) $selectedPatientId]) : null;
        $customerType = $saleMode === 'Wholesale' ? 'Clinic' : 'Client';
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $customerAddress = trim($_POST['customer_address'] ?? '');
        if ($selectedPatient) {
            $customerName = $customerName !== '' ? $customerName : $selectedPatient['full_name'];
            $customerPhone = $customerPhone !== '' ? $customerPhone : ($selectedPatient['phone'] ?? '');
            $customerAddress = $customerAddress !== '' ? $customerAddress : ($selectedPatient['address'] ?? '');
        }
        if ($customerName === '') {
            $customerName = $saleMode === 'Wholesale' ? 'Clinic Customer' : 'Walk-in customer';
        }

        $discount = max(0, (float) ($_POST['discount'] ?? 0));
        $tax = max(0, (float) ($_POST['tax'] ?? 0));
        $paidAmount = max(0, (float) ($_POST['paid_amount'] ?? 0));
        $paymentMode = trim($_POST['payment_method_primary'] ?? 'Cash');
        $total = max(0, $subtotal - $discount + $tax);
        $balance = max(0, $total - $paidAmount);
        $status = $balance > 0 ? 'Partially Paid' : 'Paid';
        $invoiceNumber = 'INV-' . date('Ymd-His');
        $serialNumber = next_sale_serial_number();

        execute_query(
            'INSERT INTO sales (invoice_number, serial_number, patient_id, branch_id, prescription_id, sale_type, customer_type, customer_name, customer_phone, customer_address, subtotal, discount, tax, total, paid_amount, balance, payment_methods, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $invoiceNumber,
                $serialNumber,
                $selectedPatientId,
                $currentBranchId,
                $prescriptionId,
                $saleMode,
                $customerType,
                $customerName,
                $customerPhone,
                $customerAddress,
                $subtotal,
                $discount,
                $tax,
                $total,
                $paidAmount,
                $balance,
                $paymentMode,
                $status,
                selected_user()['name'],
                now(),
            ]
        );
        $saleId = (int) database()->lastInsertId();

        foreach ($cart as $item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];
            execute_query(
                'INSERT INTO sale_items (sale_id, product_id, batch_id, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?, ?)',
                [$saleId, $item['product_id'], $item['batch_id'], $item['quantity'], $item['unit_price'], $lineTotal]
            );
            execute_query('UPDATE batches SET quantity = quantity - ? WHERE id = ?', [$item['quantity'], $item['batch_id']]);
            record_stock_movement($item['product_id'], $currentBranchId, $item['batch_id'], 'sale', -$item['quantity'], $saleMode . ' POS checkout ' . $invoiceNumber);
        }

        if (in_array($paymentMode, ['MTN MoMo', 'Airtel Money'], true)) {
            $providerCode = $paymentMode === 'MTN MoMo' ? 'mtn' : 'airtel';
            execute_query(
                'INSERT INTO payment_transactions (sale_id, branch_id, provider, phone_number, amount, external_reference, provider_reference, status, verification_message, payload_snapshot, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $saleId,
                    $currentBranchId,
                    $paymentMode,
                    trim($_POST['mobile_number'] ?? ''),
                    $paidAmount,
                    strtoupper($providerCode) . '-REQ-' . date('YmdHis'),
                    strtoupper($providerCode) . '-VERIFY-' . rand(1000, 9999),
                    'Pending',
                    'Awaiting provider verification',
                    json_encode([
                        'provider' => $providerCode,
                        'amount' => $paidAmount,
                        'phone' => trim($_POST['mobile_number'] ?? ''),
                        'mode' => 'demo-integration',
                    ]),
                    selected_user()['name'],
                    now(),
                ]
            );
        }

        if ($prescriptionId) {
            execute_query('UPDATE prescriptions SET status = "Partially Dispensed" WHERE id = ?', [$prescriptionId]);
        }

        audit_log('sales', 'checkout', 'Processed sale', 'sale', $saleId, ['invoice_number' => $invoiceNumber, 'serial_number' => $serialNumber, 'total' => $total, 'sale_type' => $saleMode]);
        log_activity('Processed ' . strtolower($saleMode) . ' sale ' . $invoiceNumber . ' at ' . ($currentBranch['name'] ?? 'current branch'));
        clear_cart();

        if ($paidAmount <= 0) {
            flash('error', 'No payment entered. Customer has a balance of ' . money($balance) . '.');
        } elseif ($paidAmount < $total) {
            flash('error', 'Customer paid less. Remaining balance is ' . money($balance) . '.');
        } else {
            flash('success', 'Payment recorded successfully.');
        }

        $redirectUrl = 'index.php?page=pos&invoice=' . $saleId;
        if (isset($_POST['generate_invoice'])) {
            $redirectUrl .= '&doc=invoice';
        }
        if (in_array($paymentMode, ['MTN MoMo', 'Airtel Money'], true)) {
            $redirectUrl = 'index.php?page=payments&date_from=' . urlencode(current_month_start()) . '&date_to=' . urlencode(current_month_end());
        } elseif ($paidAmount > 0) {
            $redirectUrl .= '&autoprint=1';
        }
        redirect($redirectUrl);
    }
}

$cart = $applyCartPricing(cart(), $saleMode);
set_cart($cart);
$subtotal = 0.0;
foreach ($cart as $item) {
    $subtotal += $item['quantity'] * $item['unit_price'];
}
$defaultTaxAmount = $subtotal * ($defaultTaxRate / 100);

$documentType = ($_GET['doc'] ?? 'receipt') === 'invoice' ? 'invoice' : 'receipt';
$invoice = isset($_GET['invoice']) ? fetch_one(
    'SELECT s.*, p.full_name AS patient_name, p.phone AS patient_phone, p.address AS patient_address, br.name AS branch_name
     FROM sales s
     LEFT JOIN patients p ON p.id = s.patient_id
     LEFT JOIN branches br ON br.id = s.branch_id
     WHERE s.id = ?',
    [(int) $_GET['invoice']]
) : null;
$invoiceItems = $invoice ? fetch_all(
    'SELECT si.*, pr.name FROM sale_items si JOIN products pr ON pr.id = si.product_id WHERE si.sale_id = ?',
    [(int) $_GET['invoice']]
) : [];

$receiptCustomerName = trim((string) ($invoice['customer_name'] ?? '')) !== '' ? $invoice['customer_name'] : (($invoice['patient_name'] ?? '') !== '' ? $invoice['patient_name'] : 'Walk-in customer');
$receiptCustomerPhone = trim((string) ($invoice['customer_phone'] ?? '')) !== '' ? $invoice['customer_phone'] : (($invoice['patient_phone'] ?? '') !== '' ? $invoice['patient_phone'] : '-');
$receiptCustomerAddress = trim((string) ($invoice['customer_address'] ?? '')) !== '' ? $invoice['customer_address'] : (($invoice['patient_address'] ?? '') !== '' ? $invoice['patient_address'] : '-');
?>

<section class="pos-layout">
    <article class="pos-register pos-simple">
        <div class="pos-cart-column">
            <div class="pos-panel-header">
                <div>
                    <h3>Current Cart</h3>
                    <small><?= h($currentBranch['name'] ?? 'Main Branch') ?></small>
                </div>
                <form method="post" class="sale-mode-form">
                    <input type="hidden" name="action" value="set_sale_mode">
                    <select name="sale_mode" onchange="this.form.submit()">
                        <option value="Retail" <?= $saleMode === 'Retail' ? 'selected' : '' ?>>Retail Sale</option>
                        <option value="Wholesale" <?= $saleMode === 'Wholesale' ? 'selected' : '' ?>>Wholesale Sale
                        </option>
                    </select>
                </form>
            </div>

            <div class="pos-cart-board">
                <div class="pos-order-lines">
                    <?php if (!$cart): ?>
                    <div class="pos-empty-cart">No items yet</div>
                    <?php endif; ?>
                    <?php foreach ($cart as $item): ?>
                    <?php $line = $item['quantity'] * $item['unit_price']; ?>
                    <div class="pos-line-row">
                        <div class="pos-line-name">
                            <div>
                                <strong><?= h($item['name']) ?></strong>
                                <small>
                                    <?= h($item['sale_mode'] ?? $saleMode) ?> | Batch <?= h($item['batch_number']) ?> |
                                    Expiry <?= h($item['expiry_date']) ?>
                                    <?php if (($item['expiry_status'] ?? '') === 'Expiring Soon'): ?> | Soon
                                    expiring<?php endif; ?>
                                    <?php if (!empty($item['controlled_drug'])): ?> | Controlled<?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <div class="pos-line-meta">
                            <form method="post" class="pos-inline-update">
                                <input type="hidden" name="action" value="update_cart_item">
                                <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                <input class="pos-inline-qty" type="number" name="quantity"
                                    value="<?= (int) $item['quantity'] ?>" min="1" max="<?= (int) $item['max_qty'] ?>">
                                <input class="pos-inline-price" type="number" step="0.01" min="0" name="unit_price"
                                    value="<?= h((string) $item['unit_price']) ?>">
                                <button class="pos-small-button" type="submit">Edit</button>
                            </form>
                            <strong><?= money($line) ?></strong>
                            <form method="post">
                                <input type="hidden" name="action" value="remove_cart_item">
                                <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                <button class="pos-remove-button" type="submit">x</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="pos-summary-block">
                    <div class="pos-summary-row">
                        <span>Sale Type</span>
                        <span><?= h($saleMode) ?></span>
                    </div>
                    <div class="pos-summary-row">
                        <span>Taxes</span>
                        <span id="posTaxPreview"><?= money($defaultTaxAmount) ?></span>
                    </div>
                    <div class="pos-summary-row">
                        <span>Discount</span>
                        <span id="posDiscountPreview"><?= money(0) ?></span>
                    </div>
                    <div class="pos-summary-row total">
                        <span>Total</span>
                        <strong id="posTotalPreview"
                            data-subtotal="<?= h((string) $subtotal) ?>"><?= money($subtotal + $defaultTaxAmount) ?></strong>
                    </div>
                </div>
            </div>

            <form method="post" class="pos-checkout-form">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="sale_mode" value="<?= h($saleMode) ?>">
                <div class="pos-payment-fields">
                    <label>
                        <span>Customer Type</span>
                        <input value="<?= $saleMode === 'Wholesale' ? 'Clinic' : 'Client' ?>" readonly>
                    </label>
                    <label>
                        <span><?= $saleMode === 'Wholesale' ? 'Clinic Name' : 'Customer Name' ?></span>
                        <input name="customer_name"
                            placeholder="<?= $saleMode === 'Wholesale' ? 'Enter clinic name' : 'Enter client name' ?>">
                    </label>
                    <label>
                        <span>Registered Customer</span>
                        <select name="patient_id">
                            <option value="">Walk-in / external</option>
                            <?php foreach ($patients as $patient): ?>
                            <option value="<?= (int) $patient['id'] ?>"><?= h($patient['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Prescription</span>
                        <select name="prescription_id">
                            <option value="">None</option>
                            <?php foreach ($prescriptions as $prescription): ?>
                            <option value="<?= (int) $prescription['id'] ?>">
                                <?= h($prescription['prescription_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Phone</span>
                        <input name="customer_phone" placeholder="07XXXXXXXX">
                    </label>
                    <label>
                        <span>Address</span>
                        <input name="customer_address" placeholder="Customer or clinic address">
                    </label>
                    <label>
                        <span>Discount</span>
                        <input type="number" step="0.01" min="0" name="discount" id="posDiscountInput" value="0">
                    </label>
                    <label>
                        <span>Tax</span>
                        <input type="number" step="0.01" min="0" name="tax" id="posTaxInput"
                            value="<?= h((string) $defaultTaxAmount) ?>">
                    </label>
                    <label>
                        <span>Payment Mode</span>
                        <select name="payment_method_primary" id="posPaymentMode">
                            <option>Cash</option>
                            <option>MTN MoMo</option>
                            <option>Airtel Money</option>
                        </select>
                    </label>
                    <label id="posMobileField" hidden>
                        <span>Mobile Number</span>
                        <input type="text" name="mobile_number" placeholder="077XXXXXXXX">
                    </label>
                    <label>
                        <span>Amount Paid</span>
                        <input type="number" step="0.01" min="0" name="paid_amount" id="posPaidAmount"
                            value="<?= h((string) ($subtotal + $defaultTaxAmount)) ?>">
                    </label>
                    <label class="checkbox-row full-width">
                        <input type="checkbox" name="generate_invoice" value="1">
                        <span>Generate invoice after checkout</span>
                    </label>
                </div>

                <div class="pos-status-card" id="posPaymentStatus">
                    <strong>Customer is fully paid.</strong>
                    <span>No balance remaining.</span>
                </div>

                <button class="pos-payment-button" type="submit">Complete Sale</button>
            </form>
        </div>

        <div class="pos-products-column">
            <div class="pos-products-topbar simple">
                <div class="pos-search-shell">
                    <span class="pos-search-icon">Q</span>
                    <input class="pos-search-field" id="productSearch" type="search" placeholder="Search products...">
                </div>
                <nav class="pos-quick-nav">
                    <?php foreach ([
                        ['page' => 'dashboard', 'label' => 'Dashboard'],
                        ['page' => 'products', 'label' => 'Products'],
                        ['page' => 'inventory', 'label' => 'Inventory'],
                        ['page' => 'patients', 'label' => 'Customers'],
                        ['page' => 'payments', 'label' => 'Payments'],
                    ] as $quickLink): ?>
                    <?php if (can_access_page($quickLink['page'])): ?>
                    <a href="index.php?page=<?= h($quickLink['page']) ?>"><?= h($quickLink['label']) ?></a>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="pos-category-row">
                <button class="pos-category-chip active" type="button" data-category-filter="">All</button>
                <?php foreach ($categories as $index => $category): ?>
                <button class="pos-category-chip <?= $index === 0 ? 'coral' : ($index === 1 ? 'violet' : 'sky') ?>"
                    type="button" data-category-filter="<?= h(strtolower($category['name'])) ?>">
                    <?= h($category['name']) ?>
                </button>
                <?php endforeach; ?>
                <select id="categoryFilter" class="pos-hidden-select">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?= h(strtolower($category['name'])) ?>"><?= h($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pos-product-wall" id="productGrid">
                <?php foreach ($products as $product): ?>
                <?php
                    $locations = branch_stock_locations((int) $product['id']);
                    $batch = first_available_batch((int) $product['id'], $currentBranchId);
                    $note = '';
                    $disabled = (int) $product['total_stock'] === 0;
                    if ($batch && ($batch['expiry_status'] ?? '') === 'Expired') {
                        $disabled = true;
                        $note = 'Expired batch blocked';
                    } elseif ($batch && ($batch['expiry_status'] ?? '') === 'Expiring Soon') {
                        $note = 'Soon expiring batch available';
                    }
                    ?>
                <form method="post" class="pos-product-card"
                    data-search="<?= h(strtolower($product['name'] . ' ' . ($product['brand_name'] ?? '') . ' ' . ($product['barcode'] ?? '') . ' ' . ($product['dosage'] ?? ''))) ?>"
                    data-category="<?= h(strtolower((string) $product['category_name'])) ?>"
                    data-barcode="<?= h((string) $product['barcode']) ?>">
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <div class="pos-card-art">
                        <div class="pos-card-symbol"><?= strtoupper(substr((string) $product['name'], 0, 1)) ?></div>
                    </div>
                    <div class="pos-card-body">
                        <h4><?= h($product['name']) ?></h4>
                        <p><?= h($product['dosage']) ?> | <?= h($product['unit_type']) ?></p>
                        <small>Retail <?= money($product['unit_price']) ?></small>
                        <small>Wholesale <?= money($product['wholesale_price'] ?? $product['unit_price']) ?> | Stock
                            <?= (int) $product['total_stock'] ?></small>
                    </div>
                    <?php if (!empty($product['controlled_drug'])): ?>
                    <small class="pos-card-note">Prescription required</small>
                    <?php endif; ?>
                    <?php if ($note !== ''): ?>
                    <small class="pos-card-note"><?= h($note) ?></small>
                    <?php endif; ?>
                    <?php if ((int) $product['total_stock'] === 0): ?>
                    <small class="pos-card-note">
                        Available at:
                        <?php
                                $availableBranches = array_filter($locations, static fn(array $location): bool => (int) $location['stock'] > 0);
                                echo h(implode(', ', array_map(static fn(array $location): string => $location['branch_name'] . ' (' . $location['stock'] . ')', $availableBranches)));
                                ?>
                    </small>
                    <?php endif; ?>
                    <div class="pos-card-actions">
                        <input class="pos-qty-input" type="number" name="quantity" value="1" min="1"
                            max="<?= max(1, (int) $product['total_stock']) ?>">
                        <button class="pos-add-button" type="submit" <?= $disabled ? 'disabled' : '' ?>>Add</button>
                    </div>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
    </article>
</section>

<?php if ($invoice): ?>
<section class="card receipt-card">
    <div class="section-head">
        <h3><?= $documentType === 'invoice' ? 'Tax Invoice' : 'Payment Receipt' ?></h3>
        <div class="form-actions">
            <a class="btn-secondary"
                href="index.php?page=pos&invoice=<?= (int) $invoice['id'] ?>&doc=receipt">Receipt</a>
            <a class="btn-secondary"
                href="index.php?page=pos&invoice=<?= (int) $invoice['id'] ?>&doc=invoice">Invoice</a>
            <button class="btn-secondary" type="button" data-print-receipt="receiptArea"
                data-print-title="<?= h($documentType === 'invoice' ? 'Invoice' : 'Receipt') ?>">Print
                <?= $documentType === 'invoice' ? 'Invoice' : 'Receipt' ?></button>
            <button class="btn-primary" type="button" data-download-receipt="receiptArea"
                data-document-title="<?= h($documentType === 'invoice' ? 'invoice' : 'receipt') ?>">Download
                <?= ucfirst($documentType) ?></button>
        </div>
    </div>
    <div class="receipt <?= $documentType === 'invoice' ? 'receipt-invoice' : 'receipt-payment' ?>" id="receiptArea"
        data-autoprint="<?= isset($_GET['autoprint']) ? '1' : '0' ?>">
        <div class="receipt-brand">
            <?php if ($pharmacy['logo_path'] !== ''): ?>
            <img src="<?= h($pharmacy['logo_path']) ?>" alt="Pharmacy logo" class="receipt-logo">
            <?php endif; ?>
            <h2><?= h($pharmacy['name']) ?></h2>
            <p><?= h($pharmacy['motto']) ?></p>
            <p><?= h($pharmacy['address']) ?></p>
            <p><?= h($pharmacy['telephone']) ?> | <?= h($pharmacy['email']) ?></p>
        </div>

        <div class="receipt-serial">
            <strong>Serial Tracker: <?= h($invoice['serial_number'] ?: $invoice['invoice_number']) ?></strong>
        </div>

        <div class="receipt-header receipt-header-split">
            <div class="receipt-meta">
                <p><strong><?= $documentType === 'invoice' ? 'Invoice' : 'Receipt' ?> No:</strong>
                    <?= h($invoice['invoice_number']) ?></p>
                <p><strong>Date:</strong> <?= h($invoice['created_at']) ?></p>
                <p><strong>Branch:</strong> <?= h($invoice['branch_name'] ?: 'Main Branch') ?></p>
            </div>
            <div class="receipt-meta">
                <p><strong>Sale Type:</strong> <?= h($invoice['sale_type'] ?: 'Retail') ?></p>
                <p><strong>Customer Type:</strong> <?= h($invoice['customer_type'] ?: 'Client') ?></p>
                <p><strong>Payment:</strong> <?= h($invoice['payment_methods']) ?></p>
            </div>
        </div>

        <div class="receipt-customer receipt-customer-box">
            <p><strong>Customer:</strong> <?= h($receiptCustomerName) ?></p>
            <p><strong>Phone:</strong> <?= h($receiptCustomerPhone ?: '-') ?></p>
            <p><strong>Address:</strong> <?= h($receiptCustomerAddress ?: '-') ?></p>
        </div>

        <table class="receipt-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="align-center">Qty</th>
                    <th class="align-right">Unit Price</th>
                    <th class="align-right">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceItems as $item): ?>
                <tr>
                    <td><?= h($item['name']) ?></td>
                    <td class="align-center"><?= (int) $item['quantity'] ?></td>
                    <td class="align-right"><?= money($item['unit_price']) ?></td>
                    <td class="align-right"><?= money($item['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="receipt-totals">
            <span>Subtotal: <?= money($invoice['subtotal']) ?></span>
            <span>Discount: <?= money($invoice['discount']) ?></span>
            <span>Tax Breakdown: <?= money($invoice['tax']) ?></span>
            <strong>Total: <?= money($invoice['total']) ?></strong>
            <span>Paid: <?= money($invoice['paid_amount']) ?></span>
            <span>Balance: <?= money($invoice['balance']) ?></span>
            <span>Status: <?= h($invoice['status']) ?></span>
            <?php if ((float) $invoice['balance'] > 0): ?>
            <span>Customer has a balance of <?= money($invoice['balance']) ?></span>
            <?php endif; ?>
        </div>

        <div class="receipt-footer-message">
            <?= h($receiptMessage) ?>
        </div>
    </div>
</section>
<?php endif; ?>