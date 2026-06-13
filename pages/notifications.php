<?php

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && can_publish_notifications()) {
    $attachmentPath = '';
    if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
        $fileName = 'notification-' . date('YmdHis') . '-' . basename($_FILES['attachment']['name']);
        $target = __DIR__ . '/../uploads/' . $fileName;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
            $attachmentPath = 'uploads/' . $fileName;
        }
    }

    execute_query(
        'INSERT INTO notifications (title, message_body, sender_name, audience_type, branch_id, user_id, attachment_path, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            trim($_POST['title'] ?? ''),
            trim($_POST['message_body'] ?? ''),
            selected_user()['name'],
            trim($_POST['audience_type'] ?? 'all'),
            $_POST['branch_id'] ? (int) $_POST['branch_id'] : null,
            $_POST['user_id'] ? (int) $_POST['user_id'] : null,
            $attachmentPath,
            'Published',
            now(),
        ]
    );
    flash('success', 'Notification published.');
    redirect('index.php?page=notifications');
}

$notifications = notification_list(has_global_branch_access() ? null : current_branch_id());
$selectedNotificationId = isset($_GET['notification_id']) ? (int) $_GET['notification_id'] : 0;
if ($selectedNotificationId > 0) {
    mark_notification_read($selectedNotificationId);
    $notifications = notification_list(has_global_branch_access() ? null : current_branch_id());
}
$branches = visible_branches_for_user();
$users = users_list();
$selectedNotification = null;
foreach ($notifications as $item) {
    if ((int) $item['id'] === $selectedNotificationId) {
        $selectedNotification = $item;
        break;
    }
}
?>
<section class="grid two-col">
    <?php if (can_publish_notifications()): ?>
    <article class="card">
        <div class="section-head">
            <h3>Post Notification</h3>
            <span>Admin and director broadcast panel</span>
        </div>
        <form method="post" class="form-grid" enctype="multipart/form-data">
            <label><span>Title</span><input name="title" required></label>
            <label><span>Audience</span>
                <select name="audience_type">
                    <option value="all">All users</option>
                    <option value="branch">Specific branch</option>
                    <option value="individual">Individual user</option>
                </select>
            </label>
            <label><span>Branch</span><select name="branch_id"><option value="">None</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>"><?= h($branch['name']) ?></option><?php endforeach; ?></select></label>
            <label><span>Individual User</span><select name="user_id"><option value="">None</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= h($user['full_name']) ?> (<?= h($user['role']) ?>)</option><?php endforeach; ?></select></label>
            <label class="full-width"><span>Message Body</span><textarea name="message_body" rows="4" required></textarea></label>
            <label class="full-width"><span>Attachment</span><input type="file" name="attachment"></label>
            <div class="form-actions full-width"><button class="btn-primary" type="submit">Publish Notification</button></div>
        </form>
    </article>
    <?php endif; ?>

    <article class="card">
        <div class="section-head"><h3>Active Notifications</h3><span><?= count($notifications) ?> records</span></div>
        <div class="list-table">
            <?php foreach (array_slice($notifications, 0, 10) as $item): ?>
                <a class="list-row" href="index.php?page=notifications&notification_id=<?= (int) $item['id'] ?>">
                    <div>
                        <strong><?= h($item['title']) ?></strong>
                        <small><?= h($item['sender_name']) ?> | <?= h($item['created_at']) ?></small>
                    </div>
                    <span><?= !empty($item['is_read']) ? 'Read' : 'New' ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<?php if ($selectedNotification): ?>
<section class="card">
    <div class="section-head"><h3>Notification Details</h3><span><?= !empty($selectedNotification['is_read']) ? 'Read' : 'New' ?></span></div>
    <div class="list-table">
        <div class="list-row"><strong>Title</strong><span><?= h($selectedNotification['title']) ?></span></div>
        <div class="list-row"><strong>Sender</strong><span><?= h($selectedNotification['sender_name']) ?></span></div>
        <div class="list-row"><strong>Audience</strong><span><?= h($selectedNotification['audience_type']) ?></span></div>
        <div class="list-row"><strong>Received</strong><span><?= h($selectedNotification['created_at']) ?></span></div>
    </div>
    <div style="margin-top: 1rem; white-space: pre-wrap;"><?= h($selectedNotification['message_body']) ?></div>
    <?php if (!empty($selectedNotification['attachment_path'])): ?>
        <p style="margin-top: 1rem;"><a class="text-link" href="<?= h($selectedNotification['attachment_path']) ?>" target="_blank">Open attachment</a></p>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
    <div class="section-head"><h3>Notification Log</h3><span>Clickable communication history</span></div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Title</th><th>Body</th><th>Audience</th><th>Sender</th><th>Status</th><th>Attachment</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($notifications as $item): ?>
                <tr><td><a class="text-link" href="index.php?page=notifications&notification_id=<?= (int) $item['id'] ?>"><?= h($item['title']) ?></a></td><td><?= h($item['message_body']) ?></td><td><?= h($item['audience_type']) ?></td><td><?= h($item['sender_name']) ?></td><td><?= !empty($item['is_read']) ? 'Read' : 'Unread' ?></td><td><?= !empty($item['attachment_path']) ? '<a class="text-link" href="' . h($item['attachment_path']) . '" target="_blank">Open</a>' : '-' ?></td><td><?= h($item['created_at']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
