<?php
$dateFrom = request_date('date_from', current_month_start());
$dateTo   = request_date('date_to', current_month_end());

$user = selected_user(); // ✅ KEEP THIS (IMPORTANT)

$summary = sales_summary_between($dateFrom, $dateTo);

$lowStock = low_stock_items();
$expiringSoon = expiring_batches(int_setting('expiry_alert_days', 90));
$expired = expired_batches();

/* RECENT TRANSACTIONS */
$recentTransactions = fetch_all(
    "SELECT s.*, COALESCE(s.customer_name, p.full_name) AS customer_label
     FROM sales s
     LEFT JOIN patients p ON p.id = s.patient_id
     WHERE DATE(s.created_at) BETWEEN ? AND ?
     ORDER BY s.created_at DESC
     LIMIT 5",
    [$dateFrom, $dateTo]
);

/* SALES GRAPH */
$salesGraph = fetch_all(
    "SELECT DATE(created_at) as date, SUM(total) as total
     FROM sales
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY date ASC",
    [$dateFrom, $dateTo]
);

$showActivityLogs = strcasecmp($user['role'] ?? '', 'Admin') === 0;

$activityLogs = $showActivityLogs
    ? fetch_all(
        "SELECT * FROM activity_logs
         WHERE DATE(created_at) BETWEEN ? AND ?
         ORDER BY created_at DESC
         LIMIT 10",
        [$dateFrom, $dateTo]
    )
    : [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
    body {
        background: #f5f7fb;
        font-family: Arial;
        margin: 0;
        padding: 20px;
    }

    .container {
        max-width: 1200px;
        margin: auto;
    }

    .card {
        background: #fff;
        padding: 15px;
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
    }

    .grid {
        display: grid;
        gap: 15px;
    }

    .metrics-grid {
        grid-template-columns: repeat(4, 1fr);
    }

    .dashboard-grid {
        grid-template-columns: repeat(4, 1fr);
    }

    .metric strong {
        font-size: 22px;
    }

    .metric.blue {
        border-left: 5px solid #3b82f6;
    }

    .metric.green {
        border-left: 5px solid #22c55e;
    }

    .metric.purple {
        border-left: 5px solid #a855f7;
    }

    .metric.dark {
        border-left: 5px solid #111827;
    }

    .list-row {
        display: flex;
        justify-content: space-between;
        padding: 10px;
        border-bottom: 1px solid #eee;
    }

    .list-row:hover {
        background: #f9fafb;
    }

    .pill {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
    }

    .danger {
        background: #fee2e2;
        color: #dc2626;
    }

    .warning {
        background: #fef3c7;
        color: #d97706;
    }

    .price {
        color: #2563eb;
        font-weight: bold;
    }

    canvas {
        max-width: 100%;
    }

    @media(max-width:900px) {

        .metrics-grid,
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>

    <div class="container">

        <!-- ================= FILTER ================= -->
        <div class="card">
            <h3>Dashboard Overview</h3>

            <form method="get">
                <input type="hidden" name="page" value="dashboard">

                <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
                <input type="date" name="date_to" value="<?= h($dateTo) ?>">

                <button>Apply</button>
            </form>
        </div>

        <br>

        <!-- ================= METRICS ================= -->
        <div class="grid metrics-grid">

            <div class="card metric blue">
                <span>Total Sales</span>
                <strong><?= money($summary['sales_total']) ?></strong>
            </div>

            <div class="card metric green">
                <span>Purchases</span>
                <strong><?= money($summary['purchase_total']) ?></strong>
            </div>

            <div class="card metric purple">
                <span>Gross Profit</span>
                <strong><?= money($summary['gross_profit']) ?></strong>
            </div>

            <!-- ✅ FIXED USER DISPLAY -->
            <div class="card metric dark">
                <span>Logged User</span>
                <strong><?= h($user['name']) ?></strong>
                <small><?= h($user['role']) ?></small>
            </div>

        </div>

        <br>

        <!-- ================= SALES CHART ================= -->
        <div class="card">
            <h3>Sales Performance</h3>
            <canvas id="salesChart"></canvas>
        </div>

        <br>

        <!-- ================= ALERTS ================= -->
        <div class="grid" style="grid-template-columns:1fr 1fr;">

            <div class="card">
                <h3>Alerts</h3>

                <div class="list-row">
                    <strong>Low Stock</strong>
                    <span class="pill danger"><?= count($lowStock) ?></span>
                </div>

                <div class="list-row">
                    <strong>Expiring</strong>
                    <span class="pill warning"><?= count($expiringSoon) ?></span>
                </div>

                <div class="list-row">
                    <strong>Expired</strong>
                    <span class="pill danger"><?= count($expired) ?></span>
                </div>
            </div>

            <div class="card">
                <h3>Alerts Chart</h3>
                <canvas id="alertsChart"></canvas>
            </div>

        </div>

        <br>

        <!-- ================= WIDGETS ================= -->
        <div class="grid dashboard-grid">

            <div class="card">
                <h3>Low Stock</h3>
                <?php foreach (array_slice($lowStock, 0, 5) as $i): ?>
                <div class="list-row">
                    <strong><?= h($i['name']) ?></strong>
                    <span class="pill danger"><?= (int)$i['qty'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h3>Expiring Soon</h3>
                <?php foreach (array_slice($expiringSoon, 0, 5) as $i): ?>
                <div class="list-row">
                    <strong><?= h($i['product_name']) ?></strong>
                    <span class="pill warning"><?= h($i['expiry_date']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h3>Recent Sales</h3>
                <?php foreach ($recentTransactions as $t): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($t['invoice_number']) ?></strong>
                        <small><?= h($t['customer_label']) ?></small>
                    </div>
                    <span class="price"><?= money($t['total']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h3>Activity Logs</h3>

                <?php if ($showActivityLogs): ?>
                <?php foreach ($activityLogs as $log): ?>
                <div class="list-row">
                    <div>
                        <strong><?= h($log['user_name']) ?></strong>
                        <small><?= h($log['action']) ?></small>
                    </div>
                    <span><?= h($log['created_at']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p>Admin only access</p>
                <?php endif; ?>
            </div>

        </div>

    </div>

    <!-- ================= CHART JS ================= -->
    <script>
    const salesLabels = <?= json_encode(array_column($salesGraph, 'date')) ?>;
    const salesData = <?= json_encode(array_map('floatval', array_column($salesGraph, 'total'))) ?>;

    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: salesLabels,
            datasets: [{
                label: 'Sales',
                data: salesData,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,0.1)',
                fill: true,
                tension: 0.4
            }]
        }
    });

    new Chart(document.getElementById('alertsChart'), {
        type: 'doughnut',
        data: {
            labels: ['Low Stock', 'Expiring', 'Expired'],
            datasets: [{
                data: [
                    <?= count($lowStock) ?>,
                    <?= count($expiringSoon) ?>,
                    <?= count($expired) ?>
                ],
                backgroundColor: ['#dc2626', '#d97706', '#111827']
            }]
        },
        options: {
            cutout: '60%'
        }
    });
    </script>

</body>

</html>