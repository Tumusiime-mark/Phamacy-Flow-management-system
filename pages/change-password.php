<?php
require_login(); // ✅ allow ALL logged-in users

// ==========================
// HANDLE PASSWORD CHANGE
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $errors = [];

    // Get current user
    $user = fetch_one(
        "SELECT id, full_name, password_hash FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );

    if (!$user) {
        $errors[] = "User session expired. Please login again.";
    } else {

        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = "Current password is incorrect.";
        }

        // Validate new password
        if (strlen($newPassword) < 6) {
            $errors[] = "New password must be at least 6 characters.";
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        }

        if (password_verify($newPassword, $user['password_hash'])) {
            $errors[] = "New password must be different from current password.";
        }
    }

    // If no errors → update password
    if (empty($errors)) {

        execute_query(
            "UPDATE users SET password_hash = ? WHERE id = ?",
            [password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]
        );

        log_activity("Password changed by " . $user['full_name']);

        // ✅ Success popup message
        $_SESSION['toast_success'] = "Your password has been successfully changed.";

        redirect("index.php?page=change-password");
        exit;

    } else {
        flash("error", implode("<br>", $errors));
    }
}
?>

<!-- ==========================
     CHANGE PASSWORD FORM
========================== -->
<section class="card">
    <div class="section-head">
        <h3>Change Password</h3>
        <span>Update your account password securely</span>
    </div>

    <form method="post" class="form-grid">

        <label class="full-width">
            <span>Current Password</span>
            <input type="password" name="current_password" id="cp" required>
        </label>

        <label class="full-width">
            <span>New Password</span>
            <input type="password" name="new_password" id="np" required minlength="6">
        </label>

        <label class="full-width">
            <span>Confirm Password</span>
            <input type="password" name="confirm_password" id="cf" required minlength="6">
        </label>

        <label class="full-width" style="display:flex;gap:10px;align-items:center;">
            <input type="checkbox" onclick="togglePassword()">
            <span>Show Password</span>
        </label>

        <div class="form-actions full-width">
            <button class="btn-primary" type="submit">Change Password</button>
        </div>

    </form>
</section>

<!-- ==========================
     SUCCESS TOAST POPUP
========================== -->
<?php if (!empty($_SESSION['toast_success'])): ?>
<div id="toast" class="toast-success">
    <?= $_SESSION['toast_success']; unset($_SESSION['toast_success']); ?>
</div>
<?php endif; ?>

<!-- ==========================
     STYLES
========================== -->
<style>
.toast-success {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: #fff;
    padding: 14px 18px;
    border-radius: 8px;
    font-size: 14px;
    z-index: 9999;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);

    animation: slideIn 0.4s ease, fadeOut 0.5s ease 3s forwards;
}

@keyframes slideIn {
    from {
        transform: translateX(120%);
        opacity: 0;
    }

    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeOut {
    to {
        opacity: 0;
        transform: translateX(120%);
    }
}
</style>

<!-- ==========================
     JAVASCRIPT
========================== -->
<script>
function togglePassword() {
    ['cp', 'np', 'cf'].forEach(id => {
        const field = document.getElementById(id);
        field.type = (field.type === 'password') ? 'text' : 'password';
    });
}

// Auto remove toast (backup)
setTimeout(() => {
    const toast = document.getElementById('toast');
    if (toast) toast.remove();
}, 3500);
</script>