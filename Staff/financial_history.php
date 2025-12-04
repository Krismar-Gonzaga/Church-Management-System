<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['admin', 'staff', 'finance'])) {
    header('Location: login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Staff';

// Check if tables exist
try {
    $pdo->query("SELECT 1 FROM financial_history LIMIT 1");
} catch (Exception $e) {
    die("Please run the setup script first. <a href='setup_financial_tables.php'>Setup Tables</a>");
}

// Get history with user information
$history_stmt = $pdo->prepare("
    SELECT 
        h.*,
        ft.source as transaction_source,
        ft.amount as transaction_amount,
        ft.type as transaction_type,
        u.fullname as performed_by_user,
        DATE_FORMAT(h.performed_at, '%Y-%m-%d %H:%i') as performed_datetime
    FROM financial_history h
    LEFT JOIN financial_transactions ft ON h.transaction_id = ft.id
    LEFT JOIN users u ON h.performed_by = u.id
    ORDER BY h.performed_at DESC
    LIMIT 100
");
$history_stmt->execute();
$history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Financial History</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --green: #059669;
            --green-dark: #047857;
            --light-green: #f0fdf4;
            --blue: #3b82f6;
            --purple: #8b5cf6;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }

        /* Header Styles */
        .top-header {
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            height: 80px;
            background: white; 
            border-bottom: 1px solid #e2e8f0;
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            padding: 0 40px; 
            z-index: 1000; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header-left { 
            display: flex; 
            align-items: center; 
            gap: 22px; 
        }
        .header-left .logo-img {
            height: 58px;
        }
        .parish-name {
            font-size: 22px;
            color: #065f46;
            font-weight: 700;
        }
        .header-search {
            flex: 1;
            margin-right: 50px;
            display: flex;
            justify-content: right;
            padding: 0 30px;
        }
        .search-box {
            position: relative;
            width: 100%;
            max-width: 500px;
        }
        .search-box input {
            width: 100%;
            padding: 14px 50px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 15px;
            background: #f8fafc;
            outline: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .search-box input:focus {
            border-color: #059669;
            background: white;
            box-shadow: 0 8px 25px rgba(5,150,105,0.15);
        }
        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #059669;
            font-size: 18px;
            pointer-events: none;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f0fdf4;
            padding: 10px 18px;
            border-radius: 14px;
            cursor: pointer;
            transition: 0.3s;
            border: 1px solid #d1fae5;
        }
        .user-profile:hover { 
            background: #d1fae5; 
        }
        .user-profile img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid #059669;
        }

        /* Main Layout */
        .main-layout { 
            display: flex; 
            margin-top: 80px; 
            min-height: calc(100vh - 80px); 
        }
        .sidebar { 
            width: 260px; 
            background: white; 
            border-right: 1px solid #e2e8f0; 
            position: fixed; 
            top: 80px; 
            bottom: 0; 
            overflow-y: auto; 
            z-index: 999; 
        }
        .sidebar .logo { 
            padding: 30px 20px; 
            text-align: center; 
            border-bottom: 1px solid #e2e8f0; 
        }
        .sidebar .logo img { 
            height: 60px; 
        }
        .nav-menu { 
            margin-top: 20px; 
        }
        .nav-item { 
            padding: 16px 24px; 
            display: flex; 
            align-items: center; 
            gap: 14px; 
            color: #64748b; 
            cursor: pointer; 
            transition: 0.3s; 
            text-decoration: none; 
            display: block; 
        }
        .nav-item:hover, .nav-item.active { 
            background: #f0fdf4; 
            color: #059669; 
            border-left: 5px solid #059669; 
        }
        .nav-item i { 
            font-size: 21px; 
            width: 30px; 
        }
        .nav-divider { 
            border-top: 1px solid #e2e8f0; 
            margin: 10px 0; 
        }

        .content-area { 
            margin-left: 260px; 
            padding: 40px; 
            flex: 1; 
        }

        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 30px;
            font-size: 14px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .breadcrumb a {
            color: var(--green);
            text-decoration: none;
            transition: color 0.3s;
        }
        .breadcrumb a:hover {
            color: var(--green-dark);
            text-decoration: underline;
        }
        .breadcrumb span.separator {
            color: #94a3b8;
        }

        /* Page Header */
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
        }
        .page-title { 
            font-size: 32px; 
            font-weight: 700; 
            color: #1e293b; 
        }
        .page-actions { 
            display: flex; 
            gap: 12px; 
        }
        .btn { 
            padding: 12px 24px; 
            border-radius: 12px; 
            border: none; 
            font-weight: 600; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            transition: all 0.3s; 
            text-decoration: none;
        }
        .btn-primary { 
            background: var(--green); 
            color: white; 
        }
        .btn-primary:hover { 
            background: var(--green-dark); 
        }
        .btn-secondary { 
            background: #e2e8f0; 
            color: #475569; 
        }
        .btn-secondary:hover { 
            background: #cbd5e1; 
        }

        /* History Section */
        .history-section { 
            background: white; 
            border-radius: 20px; 
            padding: 32px; 
            box-shadow: 0 8px 30px rgba(0,0,0,0.08); 
        }
        .section-title { 
            font-size: 20px; 
            font-weight: 700; 
            color: #1e293b; 
            margin-bottom: 24px; 
        }
        .history-timeline { 
            position: relative; 
        }
        .timeline-item { 
            display: flex; 
            gap: 20px; 
            margin-bottom: 30px; 
            position: relative; 
        }
        .timeline-item:last-child { 
            margin-bottom: 0; 
        }
        .timeline-item::before {
            content: ''; 
            position: absolute; 
            left: 30px; 
            top: 0; 
            bottom: -30px;
            width: 2px; 
            background: #e2e8f0;
        }
        .timeline-item:last-child::before { 
            display: none; 
        }
        .timeline-icon { 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            flex-shrink: 0; 
            z-index: 1; 
        }
        .timeline-icon.create { 
            background: linear-gradient(135deg, #dbeafe, #93c5fd); 
            color: #1e40af; 
        }
        .timeline-icon.update { 
            background: linear-gradient(135deg, #fef3c7, #fcd34d); 
            color: #92400e; 
        }
        .timeline-icon.delete { 
            background: linear-gradient(135deg, #fee2e2, #fca5a5); 
            color: #991b1b; 
        }
        .timeline-content { 
            flex: 1; 
            background: #f8fafc; 
            border-radius: 16px; 
            padding: 20px; 
        }
        .timeline-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 12px; 
        }
        .timeline-action { 
            font-weight: 700; 
            font-size: 16px; 
        }
        .timeline-action.create { 
            color: #1e40af; 
        }
        .timeline-action.update { 
            color: #92400e; 
        }
        .timeline-action.delete { 
            color: #991b1b; 
        }
        .timeline-time { 
            font-size: 14px; 
            color: #64748b; 
        }
        .timeline-user { 
            color: #475569; 
            margin-bottom: 8px; 
            font-size: 14px; 
        }
        .timeline-user strong { 
            color: var(--green); 
        }
        .timeline-details { 
            background: white; 
            border-radius: 12px; 
            padding: 16px; 
            margin-top: 12px; 
            border: 1px solid #e2e8f0; 
        }
        .detail-row { 
            display: flex; 
            margin-bottom: 8px; 
        }
        .detail-row:last-child { 
            margin-bottom: 0; 
        }
        .detail-label { 
            font-weight: 600; 
            color: #475569; 
            width: 120px; 
            flex-shrink: 0; 
        }
        .detail-value { 
            color: #1e293b; 
            flex: 1; 
        }
        .no-history { 
            text-align: center; 
            padding: 60px 20px; 
            color: #94a3b8; 
        }
        .no-history i { 
            font-size: 48px; 
            margin-bottom: 16px; 
            opacity: 0.3; 
        }
        
        /* Filter buttons */
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: var(--green);
            color: white;
            border-color: var(--green);
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo" class="logo-img">
            <h3 class="parish-name">San Jose Parish Laligan</h3>
        </div>
        <div class="header-search">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Search history..." id="historySearch">
            </div>
        </div>
        <div class="header-right">
            <div class="user-profile" onclick="window.location.href='profile.php'">
                <span style="font-weight: 600; color: #065f46;"><?= htmlspecialchars($user_fullname) ?></span>
                <img src="https://via.placeholder.com/44/059669/ffffff?text=<?= substr($user_fullname,0,1) ?>" alt="User">
            </div>
        </div>
    </div>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo">
                <img src="../images/logo.png" alt="SJPL Logo">
            </div>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="announcements.php" class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</a>
                <a href="calendar.php" class="nav-item"><i class="fas fa-calendar"></i> Calendar</a>
                <a href="appointments.php" class="nav-item"><i class="fas fa-clock"></i> Appointments</a>
                <a href="financial.php" class="nav-item"><i class="fas fa-chart-line"></i> Financial Overview</a>
                <a href="financial_management.php" class="nav-item"><i class="fas fa-coins"></i> Financial Management</a>
                <a href="financial_history.php" class="nav-item active"><i class="fas fa-history"></i> History Log</a>
                <a href="profile.php" class="nav-item"><i class="fas fa-user"></i> My Profile</a>
                <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <a href="support.php"><div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div></a>
            </div>
        </div>

        <!-- CONTENT AREA -->
        <div class="content-area">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="separator">›</span>
                <a href="financial.php">Financial Overview</a>
                <span class="separator">›</span>
                <a href="financial_management.php">Financial Management</a>
                <span class="separator">›</span>
                <span>History Log</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Financial History Log</h1>
                <div class="page-actions">
                    <a href="financial_management.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Management
                    </a>
                </div>
            </div>

            <!-- Filter Buttons -->
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterHistory('')">All</button>
                <button class="filter-btn" onclick="filterHistory('create')">Created</button>
                <button class="filter-btn" onclick="filterHistory('update')">Updated</button>
                <button class="filter-btn" onclick="filterHistory('delete')">Deleted</button>
            </div>

            <!-- History Section -->
            <div class="history-section">
                <h2 class="section-title">Transaction History (Last 100 actions)</h2>
                
                <?php if (empty($history)): ?>
                    <div class="no-history">
                        <i class="fas fa-history"></i>
                        <p>No history records found.</p>
                        <p>Start by adding transactions in <a href="financial_management.php">Financial Management</a>.</p>
                    </div>
                <?php else: ?>
                    <div class="history-timeline">
                        <?php foreach ($history as $record): ?>
                            <?php
                            $action_class = strtolower($record['action']);
                            $old_data = !empty($record['old_data']) ? json_decode($record['old_data'], true) : [];
                            $new_data = !empty($record['new_data']) ? json_decode($record['new_data'], true) : [];
                            ?>
                            
                            <div class="timeline-item" data-action="<?= $action_class ?>">
                                <div class="timeline-icon <?= $action_class ?>">
                                    <?php if ($record['action'] == 'CREATE'): ?>
                                        <i class="fas fa-plus" style="font-size: 24px;"></i>
                                    <?php elseif ($record['action'] == 'UPDATE'): ?>
                                        <i class="fas fa-edit" style="font-size: 24px;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-trash" style="font-size: 24px;"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <div class="timeline-action <?= $action_class ?>">
                                            <?= htmlspecialchars($record['action']) ?> Transaction
                                            <?php if (!empty($record['transaction_source'])): ?>
                                                - <?= htmlspecialchars($record['transaction_source']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-time">
                                            <?= htmlspecialchars($record['performed_datetime']) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-user">
                                        Performed by: <strong><?= htmlspecialchars($record['performed_by_user'] ?? 'System') ?></strong>
                                    </div>
                                    
                                    <?php if ($record['action'] == 'CREATE' && !empty($new_data)): ?>
                                        <div class="timeline-details">
                                            <div class="detail-row">
                                                <div class="detail-label">Source:</div>
                                                <div class="detail-value"><?= htmlspecialchars($new_data['source'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Amount:</div>
                                                <div class="detail-value">₱<?= number_format($new_data['amount'] ?? 0, 2, '.', ',') ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Type:</div>
                                                <div class="detail-value"><?= ucfirst(htmlspecialchars($new_data['type'] ?? 'N/A')) ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Category:</div>
                                                <div class="detail-value"><?= ucfirst(htmlspecialchars($new_data['category'] ?? 'N/A')) ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Description:</div>
                                                <div class="detail-value"><?= htmlspecialchars($new_data['description'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    
                                    <?php elseif ($record['action'] == 'UPDATE' && !empty($old_data) && !empty($new_data)): ?>
                                        <div class="timeline-details">
                                            <div class="detail-row">
                                                <div class="detail-label">Source:</div>
                                                <div class="detail-value">
                                                    <span style="text-decoration: line-through; color: #ef4444;">
                                                        <?= htmlspecialchars($old_data['source'] ?? 'N/A') ?>
                                                    </span> 
                                                    → 
                                                    <span style="color: var(--green);">
                                                        <?= htmlspecialchars($new_data['source'] ?? 'N/A') ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Amount:</div>
                                                <div class="detail-value">
                                                    <span style="text-decoration: line-through; color: #ef4444;">
                                                        ₱<?= number_format($old_data['amount'] ?? 0, 2, '.', ',') ?>
                                                    </span> 
                                                    → 
                                                    <span style="color: var(--green);">
                                                        ₱<?= number_format($new_data['amount'] ?? 0, 2, '.', ',') ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Type:</div>
                                                <div class="detail-value">
                                                    <span style="text-decoration: line-through; color: #ef4444;">
                                                        <?= ucfirst(htmlspecialchars($old_data['type'] ?? 'N/A')) ?>
                                                    </span> 
                                                    → 
                                                    <span style="color: var(--green);">
                                                        <?= ucfirst(htmlspecialchars($new_data['type'] ?? 'N/A')) ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Category:</div>
                                                <div class="detail-value">
                                                    <span style="text-decoration: line-through; color: #ef4444;">
                                                        <?= ucfirst(htmlspecialchars($old_data['category'] ?? 'N/A')) ?>
                                                    </span> 
                                                    → 
                                                    <span style="color: var(--green);">
                                                        <?= ucfirst(htmlspecialchars($new_data['category'] ?? 'N/A')) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    
                                    <?php elseif ($record['action'] == 'DELETE' && !empty($old_data)): ?>
                                        <div class="timeline-details">
                                            <div class="detail-row">
                                                <div class="detail-label">Source:</div>
                                                <div class="detail-value"><?= htmlspecialchars($old_data['source'] ?? 'N/A') ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Amount:</div>
                                                <div class="detail-value">₱<?= number_format($old_data['amount'] ?? 0, 2, '.', ',') ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Type:</div>
                                                <div class="detail-value"><?= ucfirst(htmlspecialchars($old_data['type'] ?? 'N/A')) ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Category:</div>
                                                <div class="detail-value"><?= ucfirst(htmlspecialchars($old_data['category'] ?? 'N/A')) ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Description:</div>
                                                <div class="detail-value"><?= htmlspecialchars($old_data['description'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('historySearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.toLowerCase();
                const items = document.querySelectorAll('.timeline-item');
                
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
        });

        // Filter by action type
        function filterHistory(action) {
            const items = document.querySelectorAll('.timeline-item');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            buttons.forEach(btn => {
                if (btn.textContent.toLowerCase().includes(action) || (!action && btn.textContent === 'All')) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            // Filter items
            items.forEach(item => {
                const itemAction = item.dataset.action;
                if (!action || itemAction === action) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Clear search
        document.getElementById('historySearch').addEventListener('input', function(e) {
            if (this.value === '') {
                const items = document.querySelectorAll('.timeline-item');
                items.forEach(item => {
                    item.style.display = 'flex';
                });
            }
        });
    </script>
</body>
</html>