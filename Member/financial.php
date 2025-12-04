<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Ensure only administrators can access
if (($_SESSION['role'] ?? '') !== 'member') {
    header('Location: ../login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Member';

// Auto-create financial_transactions table if it doesn't exist
try {
    $pdo->query("SELECT 1 FROM financial_transactions LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist → CREATE IT + insert sample data
    $sql = "
    CREATE TABLE financial_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source VARCHAR(100) NOT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        type ENUM('income','expense') DEFAULT 'income',
        category VARCHAR(50) DEFAULT 'offering',
        transaction_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    INSERT INTO financial_transactions (source, description, amount, type, category, transaction_date) VALUES
    ('San Roque Chapel', 'Love Offering', 560.00, 'income', 'offering', CURDATE()),
    ('San Jose Parish', 'Offering', 360.00, 'income', 'offering', CURDATE()),
    ('San Michel Chapel', 'Tithes', 650.00, 'income', 'tithes', CURDATE()),
    ('San Roque Chapel', 'Offering', 575.00, 'income', 'offering', CURDATE()),
    ('San Roque Chapel', 'Love Offering', 560.00, 'income', 'offering', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
    ('San Roque Chapel', 'Love Offering', 560.00, 'income', 'offering', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
    ('San Roque Chapel', 'Love Offering', 560.00, 'income', 'offering', DATE_SUB(CURDATE(), INTERVAL 1 DAY));
    ";

    $pdo->exec($sql);
}

// Calculate financial metrics
$balance = $pdo->query("
    SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as balance
    FROM financial_transactions
")->fetch()['balance'] ?? 0;

$income = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM financial_transactions
    WHERE type = 'income'
")->fetch()['total'] ?? 0;

$expenses = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM financial_transactions
    WHERE type = 'expense'
")->fetch()['total'] ?? 0;

// Get monthly income for chart (last 6 months)
$monthly_income = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    $month_name = date('M', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM financial_transactions
        WHERE type = 'income' 
        AND transaction_date >= ? AND transaction_date <= ?
    ");
    $stmt->execute([$month_start, $month_end]);
    $monthly_income[$month_name] = $stmt->fetch()['total'] ?? 0;
}

// Get recent transactions grouped by date
$transactions = $pdo->query("
    SELECT source, description, amount, type, transaction_date, DATE_FORMAT(transaction_date, '%W') as day_name
    FROM financial_transactions
    ORDER BY transaction_date DESC, id DESC
    LIMIT 50
")->fetchAll();

// Group transactions by date
$grouped_transactions = [];
foreach ($transactions as $t) {
    $date_key = $t['transaction_date'];
    if ($date_key == date('Y-m-d')) {
        $date_label = 'Today';
    } else {
        $date_label = date('F j, Y', strtotime($date_key)) . ', ' . date('D', strtotime($date_key));
    }
    
    if (!isset($grouped_transactions[$date_label])) {
        $grouped_transactions[$date_label] = [];
    }
    $grouped_transactions[$date_label][] = $t;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Financial</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --green: #059669;
            --green-dark: #047857;
            --light-green: #f0fdf4;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }

        .top-header {
            position: fixed; top: 0; left: 0; right: 0; height: 80px;
            background: white; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header-left { display: flex; align-items: center; gap: 22px; }
        .header-left img { height: 58px; }
        .header-left .priest-img { width: 54px; height: 54px; border-radius: 50%; border: 3px solid var(--green); object-fit: cover; }

        .header-search { flex: 1; max-width: 500px; margin: 0 40px; }
        .search-box { position: relative; }
        .search-box input {
            width: 100%; padding: 14px 48px 14px 48px; border: 2px solid #e2e8f0;
            border-radius: 16px; background: #f8fafc; font-size: 15px; transition: all 0.3s;
        }
        .search-box input:focus { border-color: var(--green); background: white; box-shadow: 0 8px 25px rgba(5,150,105,0.15); outline: none; }
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--green); font-size: 18px; }
        .search-mic { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 18px; cursor: pointer; }

        .header-right { display: flex; align-items: center; gap: 20px; }
        .notification-bell { position: relative; font-size: 23px; color: #64748b; cursor: pointer; }
        .notification-bell .badge { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 10px; width: 19px; height: 19px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .user-profile { display: flex; align-items: center; gap: 12px; background: var(--light-green); padding: 10px 18px; border-radius: 14px; border: 1px solid #d1fae5; cursor: pointer; }
        .user-profile:hover { background: #d1fae5; }
        .user-profile img { width: 44px; height: 44px; border-radius: 50%; border: 2px solid var(--green); }
        .user-profile span { font-weight: 600; color: #065f46; }

        .main-layout { display: flex; margin-top: 80px; min-height: calc(100vh - 80px); }
        .sidebar { width: 260px; background: white; border-right: 1px solid #e2e8f0; position: fixed; top: 80px; bottom: 0; overflow-y: auto; z-index: 999; }
        .sidebar .logo { padding: 30px 20px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .sidebar .logo img { height: 60px; }
        .nav-menu { margin-top: 20px; }
        .nav-item { padding: 16px 24px; display: flex; align-items: center; gap: 14px; color: #64748b; cursor: pointer; transition: 0.3s; }
        .nav-item:hover, .nav-item.active { background: var(--light-green); color: var(--green); border-left: 5px solid var(--green); }
        .nav-item i { font-size: 21px; width: 30px; }
        .nav-divider { border-top: 1px solid #e2e8f0; margin: 10px 0; }

        .content-area { margin-left: 260px; padding: 40px; flex: 1; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 32px; font-weight: 700; color: #1e293b; }
        .page-dots { display: flex; gap: 8px; }
        .page-dot { width: 8px; height: 8px; border-radius: 50%; background: #cbd5e1; cursor: pointer; }
        .page-dot.active { background: var(--green); }

        /* Overview Cards */
        .overview-section { }
        .section-title { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 20px; }
        .overview-cards { display: grid; grid-template-columns: 1fr; gap: 16px; }
        .overview-card {
            border-radius: 20px; padding: 24px; color: white;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12); position: relative; overflow: hidden;
        }
        .overview-card::before {
            content: ''; position: absolute; top: -50%; right: -20%;
            width: 150px; height: 150px; border-radius: 50%;
            background: rgba(255,255,255,0.2); opacity: 0.3;
        }
        .overview-card.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .overview-card.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .overview-card.green { background: linear-gradient(135deg, #10b981, #059669); }
        .card-icon { font-size: 32px; margin-bottom: 16px; opacity: 0.9; }
        .card-label { font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
        .card-amount { font-size: 28px; font-weight: 700; margin-bottom: 4px; }
        .card-percentage { font-size: 12px; opacity: 0.8; }

        /* Analytics Section */
        .analytics-section {
            background: white; border-radius: 20px; padding: 28px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08); margin-bottom: 30px;
        }
        .analytics-header { margin-bottom: 24px; }
        .analytics-title { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .analytics-subtitle { font-size: 16px; color: #64748b; }
        .chart-container { position: relative; height: 300px; }

        /* Transaction History */
        .transaction-section {
            background: white; border-radius: 20px; padding: 28px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        .transaction-group { margin-bottom: 32px; }
        .transaction-group:last-child { margin-bottom: 0; }
        .transaction-date { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }
        .transaction-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 0; border-bottom: 1px solid #f1f5f9;
        }
        .transaction-item:last-child { border-bottom: none; }
        .transaction-info { flex: 1; }
        .transaction-source { font-size: 15px; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
        .transaction-desc { font-size: 14px; color: #64748b; }
        .transaction-amount {
            font-size: 16px; font-weight: 700; color: var(--green);
        }

        /* Layout Grid */
        .top-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .overview-section {
            grid-column: 1;
        }
        .analytics-section {
            grid-column: 2;
        }
        .transaction-section {
            max-width: 900px;
        }

        @media (max-width: 1200px) {
            .top-grid { grid-template-columns: 1fr; }
            .overview-cards { grid-template-columns: 1fr; }
            .content-area { padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo" class="logo-img">
            <h3 class="parish-name">San Jose Parish Laligan</h3 >
        </div>
        <style>
                .parish-name {
                    font-size: 22px;
                    color: #065f46;
                    font-weight: 700;
                }
        </style>
        <div class="header-search">
            <form action="search.php" method="GET" style="width:100%; max-width:500px;">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="q" placeholder="Search announcements, members, appointments..." required>
                </div>
            </form>
        </div>

        
        <div class="header-right">
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="badge">3</span>
            </div>
            <div class="user-profile">
                <span><?= htmlspecialchars($user_fullname) ?></span>
                <img src="https://via.placeholder.com/44/059669/ffffff?text=<?= substr($user_fullname,0,1) ?>" alt="User">
                <i class="fas fa-caret-down dropdown-arrow"></i>
            </div>
        </div>
    </div>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        <!-- SIDEBAR -->
        <div class="sidebar">
            
            <div class="nav-menu">
                <a href="dashboard.php" style="text-decoration:none; color:inherit;"><div class="nav-item"><i class="fas fa-th-large"></i> Dashboard</div></a>
                <a href="announcements.php" style="text-decoration:none; color:inherit;"><div class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</div></a>
                <a href="calendar.php" style="text-decoration:none; color:inherit;"><div class="nav-item"><i class="fas fa-calendar-alt"></i> Calendar</div></a>
                <a href="appointments.php" style="text-decoration:none; color:inherit;"><div class="nav-item"><i class="fas fa-calendar-check"></i> Appointments</div></a>
                <a href="financial.php" style="text-decoration:none; color:inherit;"><div class="nav-item active"><i class="fas fa-coins"></i> Financial</div></a>
                <div class="nav-divider"></div>
                <a href="profile.php" style="text-decoration:none; color:inherit;"><div class="nav-item"><i class="fas fa-user"></i> My Profile</div></a>
                <a href="support.php" style="text-decoration:none; color:inherit;"><div class="nav-item"><i class="fas fa-cog"></i> Help & Support</div></a>
            </div>
        </div>

        <!-- CONTENT AREA -->
        <div class="content-area">
            <div class="page-header">
                <h1 class="page-title">Financial</h1>
                <div class="page-dots">
                    <div class="page-dot active"></div>
                    <div class="page-dot"></div>
                    <div class="page-dot"></div>
                </div>
            </div>

            <!-- Top Grid: Overview and Analytics -->
            <div class="top-grid">
                <!-- Overview Section -->
                <div class="overview-section">
                    <h2 class="section-title">Overview</h2>
                    <div class="overview-cards">
                        <div class="overview-card blue">
                            <div class="card-icon"><i class="fas fa-briefcase"></i></div>
                            <div class="card-label">Balance</div>
                            <div class="card-amount">₱<?= number_format($balance, 2, '.', ',') ?></div>
                            <div class="card-percentage">+12.5%</div>
                        </div>
                        <div class="overview-card purple">
                            <div class="card-icon"><i class="fas fa-coins"></i></div>
                            <div class="card-label">Income</div>
                            <div class="card-amount">₱<?= number_format($income, 2, '.', ',') ?></div>
                            <div class="card-percentage">+8.2%</div>
                        </div>
                        <div class="overview-card green">
                            <div class="card-icon"><i class="fas fa-shopping-cart"></i></div>
                            <div class="card-label">Expenses</div>
                            <div class="card-amount">₱<?= number_format($expenses, 2, '.', ',') ?></div>
                            <div class="card-percentage">-3.1%</div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Section -->
                <div class="analytics-section">
                    <div class="analytics-header">
                        <div class="analytics-title">Analytics</div>
                        <div class="analytics-subtitle">Income ₱<?= number_format($income, 2, '.', ',') ?></div>
                    </div>
                    <div class="chart-container">
                        <canvas id="incomeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Transaction History Section -->
            <div class="transaction-section">
                    <h2 class="section-title">Transaction History</h2>
                    <?php if (empty($grouped_transactions)): ?>
                        <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                            <i class="fas fa-receipt" style="font-size:48px; margin-bottom:16px; opacity:0.3;"></i>
                            <p>No transactions found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($grouped_transactions as $date_label => $date_transactions): ?>
                            <div class="transaction-group">
                                <div class="transaction-date"><?= htmlspecialchars($date_label) ?></div>
                                <?php foreach ($date_transactions as $t): ?>
                                    <div class="transaction-item">
                                        <div class="transaction-info">
                                            <div class="transaction-source"><?= htmlspecialchars($t['source']) ?></div>
                                            <div class="transaction-desc"><?= htmlspecialchars($t['description']) ?></div>
                                        </div>
                                        <div class="transaction-amount">
                                            <?= $t['type'] === 'income' ? '+' : '-' ?>₱<?= number_format($t['amount'], 2, '.', ',') ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Income Chart
        const ctx = document.getElementById('incomeChart').getContext('2d');
        const incomeChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($monthly_income)) ?>,
                datasets: [{
                    label: 'Income',
                    data: <?= json_encode(array_values($monthly_income)) ?>,
                    backgroundColor: 'rgba(139, 92, 246, 0.6)',
                    borderColor: 'rgba(139, 92, 246, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>

