<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $attachmentPath = '';
    if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
        $fileName = 'message-' . date('YmdHis') . '-' . basename($_FILES['attachment']['name']);
        $target = __DIR__ . '/../uploads/' . $fileName;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
            $attachmentPath = 'uploads/' . $fileName;
        }
    }

    $audienceType = trim($_POST['audience_type'] ?? 'individual');
    if (!can_publish_notifications() && in_array($audienceType, ['broadcast', 'branch'], true)) {
        $audienceType = 'individual';
    }

    execute_query(
        'INSERT INTO messages (title, message_body, sender_user_id, sender_name, audience_type, branch_id, recipient_user_id, attachment_path, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            trim($_POST['title'] ?? ''),
            trim($_POST['message_body'] ?? ''),
            (int) selected_user()['id'],
            selected_user()['name'],
            $audienceType,
            $_POST['branch_id'] ? (int) $_POST['branch_id'] : null,
            $_POST['recipient_user_id'] ? (int) $_POST['recipient_user_id'] : null,
            $attachmentPath,
            now(),
        ]
    );
    flash('success', 'Message sent.');
    redirect('index.php?page=messages');
}

$inbox = user_messages();
$sent = sent_messages();
$selectedMessageId = isset($_GET['message_id']) ? (int) $_GET['message_id'] : 0;
if ($selectedMessageId > 0) {
    mark_message_read($selectedMessageId);
    $inbox = user_messages();
}
$branches = visible_branches_for_user();
$users = users_list();
?>
<section class="grid two-col">
    <article class="card">
        <div class="section-head"><h3>Send Message</h3><span>Broadcast, branch, or individual</span></div>
        <form method="post" class="form-grid" enctype="multipart/form-data">
            <label><span>Title</span><input name="title" required></label>
            <label><span>Receivers</span>
                <select name="audience_type">
                    <?php if (can_publish_notifications()): ?>
                    <option value="broadcast">Broadcast</option>
                    <option value="branch">Branch</option>
                    <?php endif; ?>
                    <option value="individual">Individual</option>
                </select>
            </label>
            <label><span>Branch</span><select name="branch_id"><option value="">None</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>"><?= h($branch['name']) ?></option><?php endforeach; ?></select></label>
            <label><span>Individual User</span><select name="recipient_user_id"><option value="">None</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= h($user['full_name']) ?> (<?= h($user['role']) ?>)</option><?php endforeach; ?></select></label>
            <label class="full-width"><span>Message Body</span><textarea name="message_body" rows="4" required></textarea></label>
            <label class="full-width"><span>Attachment</span><input type="file" name="attachment"></label>
            <div class="form-actions full-width"><button class="btn-primary" type="submit">Send Message</button></div>
        </form>
    </article>

    <article class="card">
        <div class="section-head"><h3>Incoming Messages</h3><span><?= count($inbox) ?> messages</span></div>
        <div class="list-table">
            <?php foreach (array_slice($inbox, 0, 10) as $message): ?>
                <a class="list-row" href="index.php?page=messages&message_id=<?= (int) $message['id'] ?>">
                    <div><strong><?= h($message['title']) ?></strong><small><?= h($message['sender_name']) ?> | <?= h($message['created_at']) ?></small></div>
                    <span><?= !empty($message['is_read']) ? 'Read' : 'New' ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="card">
    <div class="section-head"><h3>Inbox</h3><span>Messages and attachments</span></div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Title</th><th>Body</th><th>Sender</th><th>Audience</th><th>Status</th><th>Attachment</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($inbox as $message): ?>
                <tr><td><a class="text-link" href="index.php?page=messages&message_id=<?= (int) $message['id'] ?>"><?= h($message['title']) ?></a></td><td><?= h($message['message_body']) ?></td><td><?= h($message['sender_name']) ?></td><td><?= h($message['audience_type']) ?></td><td><?= !empty($message['is_read']) ? 'Read' : 'Unread' ?></td><td><?= !empty($message['attachment_path']) ? '<a class="text-link" href="' . h($message['attachment_path']) . '" target="_blank">Open</a>' : '-' ?></td><td><?= h($message['created_at']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="section-head"><h3>Sent Messages</h3><span><?= count($sent) ?> sent</span></div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Title</th><th>Body</th><th>Receiver</th><th>Audience</th><th>Attachment</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($sent as $message): ?>
                <tr><td><?= h($message['title']) ?></td><td><?= h($message['message_body']) ?></td><td><?= h($message['recipient_name'] ?: ($message['branch_name'] ?: 'All users')) ?></td><td><?= h($message['audience_type']) ?></td><td><?= !empty($message['attachment_path']) ? '<a class="text-link" href="' . h($message['attachment_path']) . '" target="_blank">Open</a>' : '-' ?></td><td><?= h($message['created_at']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
