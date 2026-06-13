<?php
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        if (!empty($_FILES['pharmacy_logo']['tmp_name']) && is_uploaded_file($_FILES['pharmacy_logo']['tmp_name'])) {
            $extension = pathinfo($_FILES['pharmacy_logo']['name'], PATHINFO_EXTENSION) ?: 'png';
            $targetName = 'pharmacy-logo-' . date('YmdHis') . '.' . strtolower($extension);
            $targetPath = __DIR__ . '/../uploads/logos/' . $targetName;
            if (move_uploaded_file($_FILES['pharmacy_logo']['tmp_name'], $targetPath)) {
                save_setting('pharmacy_logo', 'uploads/logos/' . $targetName);
            }
        }

        if (!empty($_FILES['login_background_image']['tmp_name']) && is_uploaded_file($_FILES['login_background_image']['tmp_name'])) {
            $extension = pathinfo($_FILES['login_background_image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $targetName = 'login-background-' . date('YmdHis') . '.' . strtolower($extension);
            $targetPath = __DIR__ . '/../uploads/logos/' . $targetName;
            if (move_uploaded_file($_FILES['login_background_image']['tmp_name'], $targetPath)) {
                save_setting('login_background_image', 'uploads/logos/' . $targetName);
            }
        }

        $pairs = [
            'pharmacy_name' => trim($_POST['pharmacy_name'] ?? APP_NAME),
            'pharmacy_motto' => trim($_POST['pharmacy_motto'] ?? ''),
            'pharmacy_address' => trim($_POST['pharmacy_address'] ?? ''),
            'pharmacy_email' => trim($_POST['pharmacy_email'] ?? ''),
            'pharmacy_telephone' => trim($_POST['pharmacy_telephone'] ?? ''),
            'working_hours' => trim($_POST['working_hours'] ?? ''),
            'tax_rate' => trim((string) ($_POST['tax_rate'] ?? '0')),
            'receipt_message' => trim($_POST['receipt_message'] ?? 'Thank you my Dear Customer'),
            'expiry_alert_days' => trim((string) ($_POST['expiry_alert_days'] ?? '90')),
            'session_timeout_minutes' => trim((string) ($_POST['session_timeout_minutes'] ?? '60')),
            'maintenance_message' => trim($_POST['maintenance_message'] ?? ''),
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
            'backup_auto_enabled' => isset($_POST['backup_auto_enabled']) ? '1' : '0',
        ];

        foreach ($pairs as $key => $value) {
            save_setting($key, $value);
        }

        audit_log('settings', 'update', 'Updated pharmacy settings');
        log_activity('Updated pharmacy settings');
        flash('success', 'Pharmacy settings saved.');
        redirect('index.php?page=settings');
    }
}

$pharmacy = pharmacy_details();
?>
<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Pharmacy Details</h3>
            <span>Admin managed branding and contacts</span>
        </div>
        <form method="post" class="form-grid" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_settings">
            <label>
                <span>Pharmacy Name</span>
                <input name="pharmacy_name" value="<?= h($pharmacy['name']) ?>" required>
            </label>
            <label>
                <span>Motto</span>
                <input name="pharmacy_motto" value="<?= h($pharmacy['motto']) ?>">
            </label>
            <label class="full-width">
                <span>Full Address</span>
                <textarea name="pharmacy_address" rows="3"><?= h($pharmacy['address']) ?></textarea>
            </label>
            <label>
                <span>Email</span>
                <input name="pharmacy_email" type="email" value="<?= h($pharmacy['email']) ?>">
            </label>
            <label>
                <span>Telephone</span>
                <input name="pharmacy_telephone" value="<?= h($pharmacy['telephone']) ?>">
            </label>
            <label>
                <span>Logo</span>
                <input type="file" name="pharmacy_logo" accept="image/*">
            </label>
            <label>
                <span>Login Background</span>
                <input type="file" name="login_background_image" accept="image/*">
            </label>
            <label class="full-width">
                <span>Working Time</span>
                <input name="working_hours" value="<?= h($pharmacy['working_hours']) ?>" placeholder="Mon-Sun: 8:00 AM - 8:00 PM">
            </label>
            <label>
                <span>Default Tax Rate (%)</span>
                <input type="number" step="0.01" min="0" name="tax_rate" value="<?= h(setting('tax_rate', '18')) ?>">
            </label>
            <label>
                <span>Receipt Footer Message</span>
                <input name="receipt_message" value="<?= h(setting('receipt_message', 'Thank you my Dear Customer')) ?>">
            </label>
            <label>
                <span>Expiry Alert Days</span>
                <input type="number" min="1" name="expiry_alert_days" value="<?= h(setting('expiry_alert_days', '90')) ?>">
            </label>
            <label>
                <span>Session Timeout (minutes)</span>
                <input type="number" min="5" name="session_timeout_minutes" value="<?= h(setting('session_timeout_minutes', '60')) ?>">
            </label>
            <label class="checkbox-row">
                <input type="checkbox" name="backup_auto_enabled" value="1" <?= bool_setting('backup_auto_enabled', true) ? 'checked' : '' ?>>
                <span>Enable automatic database backup</span>
            </label>
            <label class="checkbox-row full-width">
                <input type="checkbox" name="maintenance_mode" value="1" <?= bool_setting('maintenance_mode') ? 'checked' : '' ?>>
                <span>Turn system to maintenance mode</span>
            </label>
            <label class="full-width">
                <span>Maintenance Message</span>
                <textarea name="maintenance_message" rows="3"><?= h(setting('maintenance_message', 'The pharmacy system is under maintenance. Please check back shortly.')) ?></textarea>
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Save Settings</button>
            </div>
        </form>
    </article>

    <article class="card stack">
        <div class="section-head">
            <h3>Admin Notes</h3>
            <span>Operational controls</span>
        </div>
        <div class="info-panel">
            <p>Changes here update the brand name, contact details, alerts, taxes, working hours, and maintenance state across the full system.</p>
            <p>Maintenance mode stays accessible to admins so you can test or recover the system without locking yourself out.</p>
            <p>Automatic backup runs once a day when enabled, and you can still trigger manual backups from the security page at any time.</p>
        </div>
    </article>
</section>
