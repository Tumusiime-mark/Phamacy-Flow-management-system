<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_prescription') {
        $imagePath = '';
        if (!empty($_FILES['prescription_image']['name']) && is_uploaded_file($_FILES['prescription_image']['tmp_name'])) {
            $extension = pathinfo($_FILES['prescription_image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = 'rx-' . date('YmdHis') . '-' . rand(100, 999) . '.' . strtolower($extension);
            $target = __DIR__ . '/../uploads/prescriptions/' . $fileName;
            move_uploaded_file($_FILES['prescription_image']['tmp_name'], $target);
            $imagePath = 'uploads/prescriptions/' . $fileName;
        }

        $productId = (int) ($_POST['product_id'] ?? 0);
        $product = fetch_one('SELECT * FROM products WHERE id = ?', [$productId]);
        $validationStatus = !empty($product['controlled_drug']) && trim($_POST['validation_note'] ?? '') === '' ? 'Needs Review' : 'Approved';
        $validationNote = trim($_POST['validation_note'] ?? '');
        if (!empty($product['controlled_drug']) && $validationNote === '') {
            $validationNote = 'Controlled drug requires pharmacist validation note.';
        }

        execute_query(
            'INSERT INTO prescriptions (prescription_number, patient_id, branch_id, prescriber_name, diagnosis, notes, prescription_date, end_date, image_path, status, controlled_validation_note, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'RX-' . date('Ymd-His'),
                (int) $_POST['patient_id'],
                current_branch_id(),
                trim($_POST['prescriber_name'] ?? ''),
                trim($_POST['diagnosis'] ?? ''),
                trim($_POST['notes'] ?? ''),
                $_POST['prescription_date'] ?? today(),
                $_POST['end_date'] ?? '',
                $imagePath,
                $validationStatus === 'Approved' ? 'Open' : 'Pending Review',
                $validationNote,
                selected_user()['name'],
                now(),
            ]
        );
        $prescriptionId = (int) database()->lastInsertId();
        execute_query(
            'INSERT INTO prescription_items (prescription_id, product_id, quantity, dosage_instruction, validation_status, validation_note)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $prescriptionId,
                $productId,
                (int) $_POST['quantity'],
                trim($_POST['dosage_instruction'] ?? ''),
                $validationStatus,
                $validationNote,
            ]
        );
        log_activity('Recorded prescription RX for patient ' . (string) $_POST['patient_id']);
        flash('success', 'Prescription recorded successfully.');
        redirect('index.php?page=prescriptions');
    }

    if ($action === 'add_prescription_item') {
        $productId = (int) $_POST['product_id'];
        $product = fetch_one('SELECT * FROM products WHERE id = ?', [$productId]);
        $validationStatus = !empty($product['controlled_drug']) && trim($_POST['validation_note'] ?? '') === '' ? 'Needs Review' : 'Approved';
        $validationNote = trim($_POST['validation_note'] ?? '');
        if (!empty($product['controlled_drug']) && $validationNote === '') {
            $validationNote = 'Controlled drug requires pharmacist validation note.';
        }
        execute_query(
            'INSERT INTO prescription_items (prescription_id, product_id, quantity, dosage_instruction, validation_status, validation_note)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                (int) $_POST['prescription_id'],
                $productId,
                (int) $_POST['quantity'],
                trim($_POST['dosage_instruction'] ?? ''),
                $validationStatus,
                $validationNote,
            ]
        );
        if ($validationStatus !== 'Approved') {
            execute_query(
                'UPDATE prescriptions SET status = "Pending Review", controlled_validation_note = ? WHERE id = ?',
                [$validationNote, (int) $_POST['prescription_id']]
            );
        }
        flash('success', 'Prescription item added.');
        redirect('index.php?page=prescriptions');
    }
}

$patients = patients_list();
$products = products_with_stock();
$selectedPrescriptionId = isset($_GET['prescription_id']) ? (int) $_GET['prescription_id'] : 0;
execute_query('UPDATE prescriptions SET status = "Closed" WHERE end_date IS NOT NULL AND end_date <> "" AND date(end_date) < ? AND status <> "Closed"', [today()]);

$prescriptions = fetch_all(
    'SELECT pr.*, pt.full_name AS patient_name
     FROM prescriptions pr
     JOIN patients pt ON pt.id = pr.patient_id
     ORDER BY pr.created_at DESC'
);

$selectedPrescription = $selectedPrescriptionId > 0
    ? fetch_one(
        'SELECT pr.*, pt.full_name AS patient_name
         FROM prescriptions pr
         JOIN patients pt ON pt.id = pr.patient_id
         WHERE pr.id = ?',
        [$selectedPrescriptionId]
    )
    : null;

$prescriptionItems = $selectedPrescription
    ? fetch_all(
        'SELECT pi.*, prd.name AS product_name, rx.prescription_number
         FROM prescription_items pi
         JOIN products prd ON prd.id = pi.product_id
         JOIN prescriptions rx ON rx.id = pi.prescription_id
         WHERE pi.prescription_id = ?
         ORDER BY pi.id ASC',
        [$selectedPrescriptionId]
    )
    : [];
?>

<style>
@media print {
    body * {
        visibility: hidden !important;
    }
    #printable-prescription,
    #printable-prescription * {
        visibility: visible !important;
    }
    #printable-prescription {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        padding: 0;
        margin: 0;
    }
    #printable-prescription .section-head button {
        display: none !important;
    }
}
</style>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Record Prescription</h3>
            <span>Link drugs and upload image</span>
        </div>
        <form method="post" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="action" value="save_prescription">
            <label>
                <span>Patient</span>
                <select name="patient_id" required>
                    <option value="">Select patient</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?= (int) $patient['id'] ?>"><?= h($patient['full_name']) ?> (<?= h($patient['patient_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Prescriber Name</span>
                <input name="prescriber_name" required>
            </label>
            <label>
                <span>Prescription Date</span>
                <input type="date" name="prescription_date" value="<?= h(today()) ?>">
            </label>
            <label>
                <span>End Date</span>
                <input type="date" name="end_date" value="<?= h(today()) ?>">
            </label>
            <label>
                <span>Drug / Product</span>
                <select name="product_id" required>
                    <option value="">Select medicine</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"><?= h($product['name']) ?><?= !empty($product['controlled_drug']) ? ' [Controlled]' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Quantity</span>
                <input type="number" name="quantity" value="1" min="1" required>
            </label>
            <label>
                <span>Prescription Image (optional)</span>
                <input type="file" name="prescription_image" accept="image/*">
            </label>
            <label class="full-width">
                <span>Dosage Instruction</span>
                <input name="dosage_instruction" placeholder="1 tablet twice daily after meals">
            </label>
            <label class="full-width">
                <span>Diagnosis / Notes</span>
                <textarea name="diagnosis" rows="3"></textarea>
            </label>
            <label class="full-width">
                <span>Controlled Drug Validation Note</span>
                <input name="validation_note" placeholder="Required if the selected item is controlled">
            </label>
            <label class="full-width">
                <span>Additional Notes</span>
                <textarea name="notes" rows="3"></textarea>
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Save Prescription</button>
            </div>
        </form>
    </article>

    <article class="card stack">
        <div class="section-head">
            <h3>Add Drug To Existing Prescription</h3>
            <span>Multi-line prescriptions</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="add_prescription_item">
            <label class="full-width">
                <span>Prescription</span>
                <select name="prescription_id" required>
                    <option value="">Select prescription</option>
                    <?php foreach ($prescriptions as $prescription): ?>
                        <option value="<?= (int) $prescription['id'] ?>"><?= h($prescription['prescription_number']) ?> - <?= h($prescription['patient_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Drug / Product</span>
                <select name="product_id" required>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"><?= h($product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Quantity</span>
                <input type="number" name="quantity" value="1" min="1" required>
            </label>
            <label class="full-width">
                <span>Dosage Instruction</span>
                <input name="dosage_instruction">
            </label>
            <label class="full-width">
                <span>Validation Note</span>
                <input name="validation_note">
            </label>
            <div class="form-actions full-width">
                <button class="btn-secondary" type="submit">Add Item</button>
            </div>
        </form>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Prescription Register</h3>
        <span><?= count($prescriptions) ?> prescriptions</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Prescription</th>
                <th>Patient</th>
                <th>Prescriber</th>
                <th>Status</th>
                <th>Image</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($prescriptions as $prescription): ?>
                <tr>
                    <td><a class="text-link" href="index.php?page=prescriptions&prescription_id=<?= (int) $prescription['id'] ?>"><?= h($prescription['prescription_number']) ?></a></td>
                    <td><?= h($prescription['patient_name']) ?></td>
                    <td><?= h($prescription['prescriber_name']) ?></td>
                    <td><?= h($prescription['status']) ?></td>
                    <td>
                        <?php if (!empty($prescription['image_path'])): ?>
                            <a class="text-link" href="<?= h($prescription['image_path']) ?>" target="_blank">View image</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= h($prescription['prescription_date']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($selectedPrescription): ?>
<section class="card" id="printable-prescription">
    <div class="section-head">
        <div>
            <h3>Prescription Items</h3>
            <span>Validation log for <?= h($selectedPrescription['prescription_number']) ?></span>
        </div>
        <button class="btn-secondary" type="button" onclick="window.print()">Print Prescription Slip</button>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Prescription</th>
                <th>Drug</th>
                <th>Qty</th>
                <th>Instructions</th>
                <th>Validation</th>
                <th>Note</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($prescriptionItems as $item): ?>
                <tr>
                    <td><?= h($item['prescription_number']) ?></td>
                    <td><?= h($item['product_name']) ?></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td><?= h($item['dosage_instruction']) ?></td>
                    <td><?= h($item['validation_status']) ?></td>
                    <td><?= h($item['validation_note']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php else: ?>
<section class="card">
    <div class="section-head">
        <h3>Prescription Items</h3>
        <span>Select a prescription to view item details</span>
    </div>
    <div class="card-body">
        <p>No prescription selected. Click a prescription number in the register to view its items and validation log.</p>
    </div>
</section>
<?php endif; ?>
