<?php
$flash = pull_flash();
$user = selected_user();
$pharmacy = pharmacy_details();
$canViewAlerts = can_access_page('alerts');
$expiryAlerts = $canViewAlerts ? count(expiring_batches(int_setting('expiry_alert_days', 90), current_branch_id())) : 0;
$expiredAlerts = $canViewAlerts ? count(expired_batches(current_branch_id())) : 0;
$lowStockAlerts = $canViewAlerts ? count(low_stock_items(current_branch_id())) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pharmacy['name']) ?> | <?= h(page_title()) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="page-<?= h(current_page()) ?>">
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div>
            <div class="brand-block">
                <div class="brand-badge">
                    <?php if ($pharmacy['logo_path'] !== ''): ?>
                        <img src="<?= h($pharmacy['logo_path']) ?>" alt="Pharmacy logo" class="brand-logo">
                    <?php else: ?>
                        Rx
                    <?php endif; ?>
                </div>
                <div>
                    <h1><?= h($pharmacy['name']) ?></h1>
                    <p><?= h($pharmacy['motto']) ?></p>
                </div>
            </div>
            <div class="sidebar-summary">
                <div>
                    <strong><?= $expiryAlerts ?></strong>
                    <span>Expiring soon</span>
                </div>
                <div>
                    <strong><?= $expiredAlerts ?></strong>
                    <span>Expired batches</span>
                </div>
                <div>
                    <strong><?= $lowStockAlerts ?></strong>
                    <span>Low stock alerts</span>
                </div>
            </div>
            <nav class="nav">
                <?php foreach (nav_groups() as $item): ?>
                    <a class="<?= is_active($item['page']) ?>" href="index.php?page=<?= h($item['page']) ?>"><?= h($item['label']) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="profile-card">
            <strong><?= h($user['name']) ?></strong>
            <span><?= h($user['role']) ?></span>
            <small><?= h($user['email']) ?></small>
            <small><?= h($user['branch_name'] ?? 'Main Branch') ?></small>
            <a class="change-password-link" href="index.php?page=change-password">Change Password</a>
            <a class="logout-link" href="index.php?logout=1">Sign out</a>
        </div>
    </aside>

    <main class="main-panel">
        <header class="topbar">
            <div class="topbar-title">
                <button class="menu-toggle" id="menuToggle" type="button" aria-controls="sidebar" aria-expanded="false">Menu</button>
                <div>
                    <p class="eyebrow">Production Pharmacy System</p>
                    <h2><?= h(page_title()) ?></h2>
                </div>
            </div>
            <div class="topbar-actions">
                <div class="today-chip"><?= h(date('D, d M Y')) ?></div>
                <?php if ($canViewAlerts): ?>
                    <div class="top-alerts">
                        <a class="chip" href="index.php?page=alerts">Alerts <?= $expiryAlerts + $expiredAlerts + $lowStockAlerts ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endif; ?>
