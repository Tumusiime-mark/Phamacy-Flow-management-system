<?php
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'manual_backup') {
        $file = perform_database_backup('manual', 'Triggered by admin from security page');
        flash($file ? 'success' : 'error', $file ? 'Database backup created successfully.' : 'Backup creation failed.');
        redirect('index.php?page=security');
    }

    if ($action === 'import_database') {
        if (!empty($_FILES['database_file']['tmp_name']) && is_uploaded_file($_FILES['database_file']['tmp_name'])) {
            $extension = strtolower(pathinfo($_FILES['database_file']['name'], PATHINFO_EXTENSION));
            if (in_array($extension, ['sqlite', 'db'], true)) {
                $targetPath = __DIR__ . '/../data/import-' . date('YmdHis') . '.' . $extension;
                if (move_uploaded_file($_FILES['database_file']['tmp_name'], $targetPath)) {
                    try {
                        import_database_snapshot($targetPath);
                        audit_log('security', 'database_import', 'Imported database snapshot');
                        log_activity('Imported database information');
                        flash('success', 'Database information imported successfully.');
                    } catch (Throwable $exception) {
                        flash('error', 'Database import failed: ' . $exception->getMessage());
                    }
                }
            } else {
                flash('error', 'Please upload a SQLite database file.');
            }
        } else {
            flash('error', 'Please choose a database file to import.');
        }
        redirect('index.php?page=security');
    }

    if ($action === 'clear_saved_data') {
        $confirmation = trim($_POST['reset_confirmation'] ?? '');
        if ($confirmation !== 'CLEAR DATA') {
            flash('error', 'Type CLEAR DATA to confirm full data reset.');
            redirect('index.php?page=security');
        }

        try {
            clear_saved_data_except_admin_users();
            audit_log('security', 'data_reset', 'Cleared all saved data except admin users');
            log_activity('Cleared all saved data except admin users');
            flash('success', 'All saved data cleared. Admin users were preserved.');
        } catch (Throwable $exception) {
            flash('error', 'Could not clear data: ' . $exception->getMessage());
        }
        redirect('index.php?page=security');
    }
}

$monitor = database_monitoring();
$backups = backup_logs();
?>
<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Database Monitoring</h3>
            <span>Health and storage</span>
        </div>
        <div class="list-table">
            <div class="list-row"><strong>Database Path</strong><span><?= h($monitor['path']) ?></span></div>
            <div class="list-row"><strong>Database Size</strong><span><?= h($monitor['size_label']) ?></span></div>
            <div class="list-row"><strong>Last Modified</strong><span><?= h($monitor['modified_at']) ?></span></div>
            <div class="list-row"><strong>Table Count</strong><span><?= (int) $monitor['table_count'] ?></span></div>
            <div class="list-row"><strong>Latest Sale</strong><span><?= h($monitor['latest_sale']) ?></span></div>
            <div class="list-row"><strong>Sale Time</strong><span><?= h($monitor['latest_sale_at']) ?></span></div>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Backup Controls</h3>
            <span>Manual and automatic backup</span>
        </div>
        <form method="post" class="stack">
            <input type="hidden" name="action" value="manual_backup">
            <button class="btn-primary" type="submit">Create Manual Backup</button>
        </form>
        <div class="info-panel">
            <p>Automatic backup is currently <strong><?= bool_setting('backup_auto_enabled', true) ? 'enabled' : 'disabled' ?></strong>.</p>
            <p>Last automatic backup: <?= h(setting('last_auto_backup_at', 'Not yet created')) ?></p>
            <p>Session timeout is set to <?= h(setting('session_timeout_minutes', '60')) ?> minutes, and all login passwords are stored in hashed form.</p>
        </div>
    </article>
</section>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3>Import Database</h3>
            <span>Admin recovery tool</span>
        </div>
        <form method="post" class="form-grid" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_database">
            <label class="full-width">
                <span>Database File</span>
                <input type="file" name="database_file" accept=".sqlite,.db" required>
            </label>
            <div class="form-actions full-width">
                <button class="btn-secondary" type="submit">Import Database Information</button>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Clear Saved Data</h3>
            <span>Preserve admin users only</span>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="clear_saved_data">
            <label class="full-width">
                <span>Confirmation</span>
                <input name="reset_confirmation" placeholder="Type CLEAR DATA">
            </label>
            <div class="info-panel full-width">
                <p>This empties all operational tables and keeps only admin users in the users table.</p>
            </div>
            <div class="form-actions full-width">
                <button class="btn-primary danger-surface" type="submit">Clear All Saved Data</button>
            </div>
        </form>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h3>Backup History</h3>
        <span><?= count($backups) ?> records</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Type</th>
                <th>Status</th>
                <th>File</th>
                <th>Size</th>
                <th>Triggered By</th>
                <th>Time</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($backups as $item): ?>
                <tr>
                    <td><?= h($item['backup_type']) ?></td>
                    <td><?= h($item['status']) ?></td>
                    <td><?= h($item['file_path']) ?></td>
                    <td><?= number_format(((int) $item['file_size']) / 1024, 2) ?> KB</td>
                    <td><?= h($item['triggered_by']) ?></td>
                    <td><?= h($item['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
