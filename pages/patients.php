<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_patient') {
        $id = (int) ($_POST['id'] ?? 0);
        $payload = [
            trim($_POST['patient_code'] ?? ''),
            trim($_POST['full_name'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['address'] ?? ''),
            trim($_POST['next_of_kin'] ?? ''),
            (float) ($_POST['loyalty_discount'] ?? 0),
            trim($_POST['prescription_history'] ?? ''),
        ];

        if ($id > 0) {
            execute_query(
                'UPDATE patients SET patient_code = ?, full_name = ?, phone = ?, email = ?, address = ?, next_of_kin = ?, loyalty_discount = ?, prescription_history = ? WHERE id = ?',
                array_merge($payload, [$id])
            );
            audit_log('customers', 'update', 'Updated patient profile', 'patient', $id, ['name' => $payload[1]]);
            log_activity('Updated patient ' . $payload[1]);
            flash('success', 'Patient updated.');
        } else {
            execute_query(
                'INSERT INTO patients (patient_code, full_name, phone, email, address, next_of_kin, loyalty_discount, prescription_history, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge($payload, [now()])
            );
            audit_log('customers', 'create', 'Registered patient profile', 'patient', (int) database()->lastInsertId(), ['name' => $payload[1]]);
            log_activity('Registered patient ' . $payload[1]);
            flash('success', 'Patient registered.');
        }
        redirect('index.php?page=patients');
    }
}

$editing = isset($_GET['edit']) ? fetch_one('SELECT * FROM patients WHERE id = ?', [(int) $_GET['edit']]) : null;
$patients = fetch_all(
    'SELECT p.*, COUNT(s.id) AS visits, COALESCE(SUM(s.total), 0) AS purchases
     FROM patients p
     LEFT JOIN sales s ON s.patient_id = p.id
     GROUP BY p.id
     ORDER BY p.full_name'
);
?>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3><?= $editing ? 'Edit Patient' : 'Patient Register' ?></h3>
            <span>Customer / patient management</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_patient">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <label>
                <span>Patient Code</span>
                <input name="patient_code" required value="<?= h($editing['patient_code'] ?? 'PT-' . rand(1000, 9999)) ?>">
            </label>
            <label>
                <span>Full Name</span>
                <input name="full_name" required value="<?= h($editing['full_name'] ?? '') ?>">
            </label>
            <label>
                <span>Phone</span>
                <input name="phone" value="<?= h($editing['phone'] ?? '') ?>">
            </label>
            <label>
                <span>Email</span>
                <input name="email" type="email" value="<?= h($editing['email'] ?? '') ?>">
            </label>
            <label class="full-width">
                <span>Full Address</span>
                <textarea name="address" rows="3"><?= h($editing['address'] ?? '') ?></textarea>
            </label>
            <label>
                <span>Next of Kin</span>
                <input name="next_of_kin" value="<?= h($editing['next_of_kin'] ?? '') ?>">
            </label>
            <label>
                <span>Loyalty Discount (%)</span>
                <input type="number" step="0.01" name="loyalty_discount" value="<?= h((string) ($editing['loyalty_discount'] ?? 0)) ?>">
            </label>
            <label class="full-width">
                <span>Prescription History</span>
                <textarea name="prescription_history" rows="4"><?= h($editing['prescription_history'] ?? '') ?></textarea>
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit"><?= $editing ? 'Update Patient' : 'Register Patient' ?></button>
                <?php if ($editing): ?>
                    <a class="btn-secondary" href="index.php?page=patients">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Patient Summary</h3>
            <span>Loyalty and history</span>
        </div>
        <div class="list-table">
            <?php foreach (array_slice($patients, 0, 5) as $patient): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($patient['full_name']) ?></strong>
                        <small><?= h($patient['phone']) ?> | <?= (float) $patient['loyalty_discount'] ?>% loyalty</small>
                    </div>
                    <span><?= money($patient['purchases']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Registered Patients</h3>
        <span><?= count($patients) ?> records</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Contact</th>
                <th>Address</th>
                <th>Next of Kin</th>
                <th>Purchase History</th>
                <th>Visits</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($patients as $patient): ?>
                <tr>
                    <td><?= h($patient['patient_code']) ?></td>
                    <td><?= h($patient['full_name']) ?></td>
                    <td><?= h($patient['phone']) ?><br><small><?= h($patient['email']) ?></small></td>
                    <td><?= h($patient['address']) ?></td>
                    <td><?= h($patient['next_of_kin']) ?></td>
                    <td><?= money($patient['purchases']) ?></td>
                    <td><?= (int) $patient['visits'] ?></td>
                    <td><a class="text-link" href="index.php?page=patients&edit=<?= (int) $patient['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
