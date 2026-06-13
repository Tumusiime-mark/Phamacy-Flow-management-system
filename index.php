<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$allowedPages = [
    'login',
    'dashboard',
    'alerts',
    'reports',
    'products',
    'inventory',
    'pos',
    'patients',
    'prescriptions',
    'purchases',
    'returns',
    'branches',
    'payments',
    'users',
    'audit',
    'security',
    'settings',
    'expenditures',
    'change-password',
];

$page = current_page();
if (!in_array($page, $allowedPages, true)) {
    $page = is_logged_in() ? 'dashboard' : 'login';
}

if ($page === 'login') {
    require __DIR__ . '/pages/login.php';
    exit;
}

if (isset($_GET['logout'])) {
    logout_user();
    session_start();
    flash('success', 'You have been signed out.');
    redirect('index.php?page=login');
}

require_login();
enforce_maintenance_mode();
require_page_access($page);

ob_start();
$pageFile = __DIR__ . '/pages/' . $page . '.php';
require $pageFile;
$pageContent = ob_get_clean();

require __DIR__ . '/partials/header.php';
echo $pageContent;
require __DIR__ . '/partials/footer.php';
