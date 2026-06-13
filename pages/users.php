<?php
require_admin();

$activityDateFrom = request_date('activity_from', current_month_start());
$activityDateTo = request_date('activity_to', current_month_end());
$export = $_GET['export'] ?? '';
$roleDefinitions = role_definitions();
$rolePermissions = [];
foreach ($roleDefinitions as $definition) {
    $rolePermissions[$definition['label']] = $definition['pages'];
}

function normalize_upload_filename(string $filename): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($filename));
    return substr($name, 0, 200);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_user') {
        $id = (int) ($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'Cashier');
        $adminPasswordWord = trim($_POST['admin_password_word'] ?? '');
        $existingUser = $id > 0 ? fetch_one('SELECT * FROM users WHERE id = ?', [$id]) : null;

        $creatingAdmin = normalized_role($role) === 'admin' && ($id === 0 || normalized_role($existingUser['role'] ?? '') !== 'admin');
        if ($creatingAdmin && $adminPasswordWord !== 'masindi@01') {
            flash('error', 'Admin account creation requires the authorization word.');
            redirect('index.php?page=users' . ($id > 0 ? '&edit=' . $id : ''));
        }

        $photoPath = $existingUser['photo_path'] ?? '';
        if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $photoName = uniqid('user_photo_') . '_' . normalize_upload_filename($_FILES['photo']['name']);
            $targetPhoto = __DIR__ . '/../uploads/users/photos/' . $photoName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPhoto)) {
                $photoPath = 'uploads/users/photos/' . $photoName;
            }
        }

        $existingTranscripts = [];
        if (!empty($existingUser['transcripts'])) {
            $existingTranscripts = json_decode((string) $existingUser['transcripts'], true) ?: [];
        }

        if (!empty($_FILES['transcript_images']['tmp_name']) && is_array($_FILES['transcript_images']['tmp_name'])) {
            foreach ($_FILES['transcript_images']['tmp_name'] as $index => $tmpName) {
                if (empty($tmpName) || !is_uploaded_file($tmpName)) {
                    continue;
                }

                $transcriptName = uniqid('transcript_') . '_' . normalize_upload_filename($_FILES['transcript_images']['name'][$index] ?? 'upload');
                $targetTranscript = __DIR__ . '/../uploads/users/transcripts/' . $transcriptName;
                if (move_uploaded_file($tmpName, $targetTranscript)) {
                    $existingTranscripts[] = 'uploads/users/transcripts/' . $transcriptName;
                }
            }
        }

        $transcriptsJson = json_encode(array_values($existingTranscripts));

        $payload = [
            trim($_POST['full_name'] ?? ''),
            $role,
            trim($_POST['email'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['status'] ?? 'Active'),
            (int) ($_POST['branch_id'] ?? 1),
            trim($_POST['username'] ?? ''),
            trim($_POST['address'] ?? ''),
            trim($_POST['village'] ?? ''),
            trim($_POST['sub_county'] ?? ''),
            trim($_POST['district'] ?? ''),
            trim($_POST['qualification'] ?? ''),
            trim($_POST['primary_school'] ?? ''),
            trim($_POST['secondary_school'] ?? ''),
            trim($_POST['tertiary_school'] ?? ''),
            trim($_POST['university'] ?? ''),
            trim($_POST['marital_status'] ?? ''),
            (int) ($_POST['children_count'] ?? 0),
            $photoPath,
            $transcriptsJson,
        ];

        if ($id > 0) {
            execute_query(
                'UPDATE users SET full_name = ?, role = ?, email = ?, phone = ?, status = ?, branch_id = ?, username = ?, address = ?, village = ?, sub_county = ?, district = ?, qualification = ?, primary_school = ?, secondary_school = ?, tertiary_school = ?, university = ?, marital_status = ?, children_count = ?, photo_path = ?, transcripts = ? WHERE id = ?',
                array_merge($payload, [$id])
            );
            if ($password !== '') {
                execute_query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            audit_log('users', 'update', 'Updated user profile', 'user', $id, ['name' => $payload[0]]);
            log_activity('Updated user ' . $payload[0]);
            flash('success', 'User updated.');
        } else {
            $username = trim($_POST['username'] ?? '') !== '' ? trim($_POST['username']) : strtolower(str_replace(' ', '.', $payload[0]));
            execute_query(
                'INSERT INTO users (full_name, role, email, phone, status, branch_id, username, address, village, sub_county, district, qualification, primary_school, secondary_school, tertiary_school, university, marital_status, children_count, photo_path, transcripts, created_at, password_hash)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge($payload, [now(), password_hash($password !== '' ? $password : 'admin123', PASSWORD_DEFAULT)])
            );
            $userId = (int) database()->lastInsertId();
            audit_log('users', 'create', 'Created user profile', 'user', $userId, ['name' => $payload[0]]);
            log_activity('Created user ' . $payload[0]);
            flash('success', 'User added.');
        }
        redirect('index.php?page=users');
    }

}

$editing = isset($_GET['edit']) ? fetch_one('SELECT * FROM users WHERE id = ?', [(int) $_GET['edit']]) : null;
$users = users_list();
$activityLogs = fetch_all(
    'SELECT * FROM activity_logs
     WHERE date(created_at) BETWEEN ? AND ?
     ORDER BY created_at DESC
     LIMIT 5',
    [$activityDateFrom, $activityDateTo]
);

if ($export === 'activities') {
    $exportLogs = fetch_all(
        'SELECT * FROM activity_logs
         WHERE date(created_at) BETWEEN ? AND ?
         ORDER BY created_at DESC',
        [$activityDateFrom, $activityDateTo]
    );
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="user-activities-' . date('YmdHis') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['User', 'Role', 'Action', 'Time']);
    foreach ($exportLogs as $log) {
        fputcsv($output, [$log['user_name'], $log['role'], $log['action'], $log['created_at']]);
    }
    fclose($output);
    exit;
}

$branches = branches_list();
$transcriptImages = [];
if ($editing && !empty($editing['transcripts'])) {
    $transcriptImages = json_decode((string) $editing['transcripts'], true) ?: [];
}
?>

<section class="grid two-col">
    <article class="card">
        <div class="section-head">
            <h3><?= $editing ? 'Edit User Profile' : 'Add User & Role' ?></h3>
            <span>Secure login enabled</span>
        </div>
        <form method="post" class="form-grid" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">

            <label>
                <span>Full Name</span>
                <input name="full_name" required value="<?= h($editing['full_name'] ?? '') ?>">
            </label>
            <label>
                <span>Role</span>
                <select name="role" id="role-selection">
                    <?php foreach (role_labels() as $role): ?>
                        <option value="<?= h($role) ?>" <?= (($editing['role'] ?? '') === $role) ? 'selected' : '' ?>><?= h($role) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="full-width">
                <span>Role Permissions Summary</span>
                <div id="role-summary" class="text-muted">Select a role to see the permissions.</div>
            </label>
            <label>
                <span>Admin Authorization Word</span>
                <input name="admin_password_word" type="password" placeholder="Required only for Admin role">
            </label>
            <label>
                <span>Username</span>
                <input name="username" required value="<?= h($editing['username'] ?? '') ?>">
            </label>
            <label>
                <span>Email</span>
                <input name="email" type="email" required value="<?= h($editing['email'] ?? '') ?>">
            </label>
            <label>
                <span>Phone</span>
                <input name="phone" value="<?= h($editing['phone'] ?? '') ?>">
            </label>
            <label>
                <span>Branch</span>
                <select name="branch_id">
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>" <?= ((int) ($editing['branch_id'] ?? 1) === (int) $branch['id']) ? 'selected' : '' ?>><?= h($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="full-width">
                <span>Address</span>
                <textarea name="address" rows="3"><?= h($editing['address'] ?? '') ?></textarea>
            </label>
            <label>
                <span>Village</span>
                <input name="village" value="<?= h($editing['village'] ?? '') ?>">
            </label>
            <label>
                <span>Sub-county</span>
                <input name="sub_county" value="<?= h($editing['sub_county'] ?? '') ?>">
            </label>
            <label>
                <span>District</span>
                <input name="district" value="<?= h($editing['district'] ?? '') ?>">
            </label>
            <label>
                <span>Qualification</span>
                <input name="qualification" value="<?= h($editing['qualification'] ?? '') ?>">
            </label>
            <label>
                <span>Primary School</span>
                <input name="primary_school" value="<?= h($editing['primary_school'] ?? '') ?>">
            </label>
            <label>
                <span>Secondary School</span>
                <input name="secondary_school" value="<?= h($editing['secondary_school'] ?? '') ?>">
            </label>
            <label>
                <span>Tertiary School</span>
                <input name="tertiary_school" value="<?= h($editing['tertiary_school'] ?? '') ?>">
            </label>
            <label>
                <span>University</span>
                <input name="university" value="<?= h($editing['university'] ?? '') ?>">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="Active" <?= (($editing['status'] ?? '') === 'Active') ? 'selected' : '' ?>>Active</option>
                    <option value="Suspended" <?= (($editing['status'] ?? '') === 'Suspended') ? 'selected' : '' ?>>Suspended</option>
                </select>
            </label>
            <label>
                <span><?= $editing ? 'Change Password (optional)' : 'Password' ?></span>
                <input name="password" type="password" <?= $editing ? '' : 'required' ?> autocomplete="new-password">
            </label>
            <label>
                <span>Marital Status</span>
                <select name="marital_status">
                    <?php foreach (['Single', 'Married', 'Divorced', 'Widowed'] as $status): ?>
                        <option value="<?= h($status) ?>" <?= (($editing['marital_status'] ?? '') === $status) ? 'selected' : '' ?>><?= h($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Number of Children</span>
                <input name="children_count" type="number" min="0" value="<?= h($editing['children_count'] ?? 0) ?>">
            </label>
            <label>
                <span>Photo</span>
                <input type="file" name="photo" accept="image/*">
            </label>
            <?php if (!empty($editing['photo_path'])): ?>
                <label class="full-width">
                    <span>Current Photo</span>
                    <img src="<?= h($editing['photo_path']) ?>" alt="User photo" style="max-width: 120px; display: block; margin-top: 0.5rem;">
                </label>
            <?php endif; ?>
            <label class="full-width">
                <span>Transcripts</span>
                <input type="file" name="transcript_images[]" accept="image/*,.pdf" multiple>
            </label>
            <?php if (!empty($transcriptImages)): ?>
                <div class="form-group full-width">
                    <span>Uploaded Transcripts</span>
                    <div class="list-table">
                        <?php foreach ($transcriptImages as $path): ?>
                            <div class="list-row">
                                <div><a href="<?= h($path) ?>" target="_blank"><?= h(basename($path)) ?></a></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit"><?= $editing ? 'Update User' : 'Add User' ?></button>
                <?php if ($editing): ?>
                    <a class="btn-secondary" href="index.php?page=users">Cancel</a>
                <?php endif; ?>
                <button type="button" class="btn-secondary" id="print-user-details">Print Profile</button>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="section-head">
            <h3>Recent User Activity</h3>
            <span>Showing 5 latest actions</span>
        </div>

        <form method="get" class="form-grid">
            <input type="hidden" name="page" value="users">
            <label>
                <span>Date From</span>
                <input type="date" name="activity_from" value="<?= h($activityDateFrom) ?>">
            </label>
            <label>
                <span>Date To</span>
                <input type="date" name="activity_to" value="<?= h($activityDateTo) ?>">
            </label>
            <div class="form-actions full-width">
                <button class="btn-primary" type="submit">Apply Range</button>
                <a class="btn-secondary" href="index.php?page=users&export=activities&activity_from=<?= h($activityDateFrom) ?>&activity_to=<?= h($activityDateTo) ?>">Export CSV</a>
            </div>
        </form>

        <div class="list-table">
            <?php if (count($activityLogs) === 0): ?>
                <div class="list-row"><span>No user activity found for this range.</span></div>
            <?php endif; ?>
            <?php foreach ($activityLogs as $log): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($log['user_name']) ?></strong>
                        <small><?= h($log['action']) ?></small>
                    </div>
                    <span><?= h($log['created_at']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>


<section class="card">
    <div class="section-head">
        <h3>System Users</h3>
        <span><?= count($users) ?> team members</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Username</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Branch</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= h($user['full_name']) ?></td>
                    <td><?= h($user['role']) ?></td>
                    <td><?= h($user['username']) ?></td>
                    <td><?= h($user['email']) ?></td>
                    <td><?= h($user['phone']) ?></td>
                    <td><?= h($user['address']) ?></td>
                    <td><?= h($user['branch_name']) ?></td>
                    <td><?= h($user['status']) ?></td>
                    <td><?= h($user['last_login'] ?: 'Never') ?></td>
                    <td><a class="text-link" href="index.php?page=users&edit=<?= (int) $user['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
const rolePermissions = <?= json_encode($rolePermissions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const roleSelection = document.getElementById('role-selection');
const roleSummary = document.getElementById('role-summary');

function updateRoleSummary(selectedRole) {
    if (!roleSummary) {
        return;
    }
    const pages = rolePermissions[selectedRole] || [];
    roleSummary.textContent = pages.length > 0 ? 'This role can access: ' + pages.join(', ') : 'No permissions defined for this role.';
}

if (roleSelection) {
    roleSelection.addEventListener('change', (event) => {
        updateRoleSummary(event.target.value);
    });
    updateRoleSummary(roleSelection.value);
}

const printButton = document.getElementById('print-user-details');
if (printButton) {
    printButton.addEventListener('click', () => window.print());
}
</script>
