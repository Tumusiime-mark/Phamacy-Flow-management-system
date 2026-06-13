<?php
if (is_logged_in()) {
    redirect('index.php?page=dashboard');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login_user($login, $password)) {
        refresh_current_user();
        flash('success', 'Welcome back to the pharmacy system.');
        redirect('index.php?page=dashboard');
    }

    flash('error', 'Invalid username/email or password.');
    redirect('index.php?page=login');
}

$flash = pull_flash();
$pharmacy = pharmacy_details();
$loginBackground = setting('login_background_image', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pharmacy['name']) ?> Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page auth-page-photo"<?= $loginBackground !== '' ? ' style="background-image:url(\'' . h($loginBackground) . '\')"' : '' ?>>
    <main class="auth-visual-shell">
        <section class="auth-glass-card">
            <div class="auth-logo-block">
                <?php if ($pharmacy['logo_path'] !== ''): ?>
                    <img src="<?= h($pharmacy['logo_path']) ?>" alt="Pharmacy logo" class="auth-logo-image">
                <?php else: ?>
                    <div class="auth-logo-fallback">Rx</div>
                <?php endif; ?>
                <h1><?= h($pharmacy['name']) ?></h1>
                <p><?= h($pharmacy['motto']) ?></p>
            </div>

            <div class="auth-clock-panel">
                <strong id="loginGreeting">Good day</strong>
                <span id="loginClock"><?= h(date('h:i A')) ?></span>
            </div>

            <?php if ($flash): ?>
                <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form auth-compact-form">
                <label>
                    <span>Username or Email</span>
                    <input name="login" required autocomplete="username" placeholder="Enter your email">
                </label>
                <label>
                    <span>Password</span>
                    <input name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
                </label>
                <button class="btn-primary auth-submit" type="submit">Login</button>
            </form>

            <div class="auth-footnote">
                <span><?= h($pharmacy['address']) ?></span>
                <span><?= h($pharmacy['telephone']) ?></span>
            </div>
        </section>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>
