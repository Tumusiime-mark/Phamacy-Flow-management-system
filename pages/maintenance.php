<?php $pharmacy = pharmacy_details(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pharmacy['name']) ?> Maintenance</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">
    <main class="maintenance-shell">
        <section class="maintenance-card">
            <span class="eyebrow">Maintenance Mode</span>
            <h1><?= h($pharmacy['name']) ?></h1>
            <p><?= h(setting('maintenance_message', 'The system is temporarily unavailable.')) ?></p>
            <div class="auth-meta">
                <span><?= h($pharmacy['working_hours']) ?></span>
                <span><?= h($pharmacy['telephone']) ?></span>
                <span><?= h($pharmacy['email']) ?></span>
            </div>
        </section>
    </main>
</body>
</html>
