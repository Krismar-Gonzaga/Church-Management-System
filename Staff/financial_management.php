<?php
session_start();
require_once '../Database/DBconnection.php';

// Debug: Show errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['admin', 'staff', 'finance'])) {
    header('Location: login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Staff';
// Get user ID - check what session variable stores user ID
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

// Check if tables exist and create if they don't
try {
    $pdo->query("SELECT 1 FROM financial_transactions LIMIT 1");
} catch (Exception $e) {
    // Create tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS financial_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(100) NOT NULL,
            description VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type ENUM('income','expense') DEFAULT 'income',
            category VARCHAR(50) DEFAULT 'offering',
            transaction_date DATE NOT NULL,
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Check if created_by column exists
    $check = $pdo->query("SHOW COLUMNS FROM financial_transactions LIKE 'created_by'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE financial_transactions ADD COLUMN created_by INT AFTER transaction_date");
    }
}

// Create history table if it doesn't exist
try {
    $pdo->query("SELECT 1 FROM financial_history LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS financial_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id INT,
            action ENUM('CREATE','UPDATE','DELETE') NOT NULL,
            old_data TEXT,
            new_data TEXT,
            performed_by INT,
            performed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// Handle CRUD operations
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log POST data
    error_log("POST Data: " . print_r($_POST, true));
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $source = trim($_POST['source'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $type = $_POST['type'] ?? 'income';
        $category = $_POST['category'] ?? 'offering';
        $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
        
        error_log("Validating: source=$source, description=$description, amount=$amount, type=$type, date=$transaction_date");
        
        if (empty($source) || empty($description) || $amount <= 0) {
            $message = 'Please fill all required fields with valid data.';
            $message_type = 'error';
            error_log("Validation failed: source or description empty or amount invalid");
        } else {
            try {
                if ($action === 'add') {
                    error_log("Attempting to add transaction...");
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO financial_transactions 
                        (source, description, amount, type, category, transaction_date, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $result = $stmt->execute([$source, $description, $amount, $type, $category, $transaction_date, $user_id]);
                    
                    if ($result) {
                        $transaction_id = $pdo->lastInsertId();
                        error_log("Transaction added successfully. ID: $transaction_id");
                        
                        // Log to history
                        $history_stmt = $pdo->prepare("
                            INSERT INTO financial_history 
                            (transaction_id, action, new_data, performed_by)
                            VALUES (?, 'CREATE', ?, ?)
                        ");
                        $new_data = json_encode([
                            'source' => $source,
                            'description' => $description,
                            'amount' => $amount,
                            'type' => $type,
                            'category' => $category,
                            'transaction_date' => $transaction_date
                        ]);
                        $history_stmt->execute([$transaction_id, $new_data, $user_id]);
                        
                        $message = 'Transaction added successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to add transaction. Please try again.';
                        $message_type = 'error';
                        error_log("Failed to execute INSERT statement");
                    }
                    
                } elseif ($action === 'edit' && isset($_POST['id'])) {
                    $id = intval($_POST['id']);
                    
                    // Get old data for history
                    $old_stmt = $pdo->prepare("SELECT * FROM financial_transactions WHERE id = ?");
                    $old_stmt->execute([$id]);
                    $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("
                        UPDATE financial_transactions 
                        SET source = ?, description = ?, amount = ?, type = ?, 
                            category = ?, transaction_date = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$source, $description, $amount, $type, $category, $transaction_date, $id]);
                    
                    // Log to history
                    $history_stmt = $pdo->prepare("
                        INSERT INTO financial_history 
                        (transaction_id, action, old_data, new_data, performed_by)
                        VALUES (?, 'UPDATE', ?, ?, ?)
                    ");
                    $old_json = $old_data ? json_encode($old_data) : '{}';
                    $new_data = json_encode([
                        'source' => $source,
                        'description' => $description,
                        'amount' => $amount,
                        'type' => $type,
                        'category' => $category,
                        'transaction_date' => $transaction_date
                    ]);
                    $history_stmt->execute([$id, $old_json, $new_data, $user_id]);
                    
                    $message = 'Transaction updated successfully!';
                    $message_type = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
                error_log("Database error: " . $e->getMessage());
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    try {
        // Get data for history before deleting
        $stmt = $pdo->prepare("SELECT * FROM financial_transactions WHERE id = ?");
        $stmt->execute([$id]);
        $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($old_data) {
            // Delete transaction
            $delete_stmt = $pdo->prepare("DELETE FROM financial_transactions WHERE id = ?");
            $delete_stmt->execute([$id]);
            
            // Log to history
            $history_stmt = $pdo->prepare("
                INSERT INTO financial_history 
                (transaction_id, action, old_data, performed_by)
                VALUES (?, 'DELETE', ?, ?)
            ");
            $old_json = json_encode($old_data);
            $history_stmt->execute([$id, $old_json, $user_id]);
            
            $message = 'Transaction deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Transaction not found.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Error deleting transaction: ' . $e->getMessage();
        $message_type = 'error';
    }
    
    header('Location: financial_management.php?message=' . urlencode($message) . '&type=' . $message_type);
    exit;
}

// Get transaction for editing
$edit_transaction = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM financial_transactions WHERE id = ?");
    $stmt->execute([$id]);
    $edit_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all transactions with filters
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$category_filter = $_GET['category_filter'] ?? '';

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(source LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($type_filter)) {
    $where[] = "type = ?";
    $params[] = $type_filter;
}

if (!empty($category_filter)) {
    $where[] = "category = ?";
    $params[] = $category_filter;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT * FROM financial_transactions $where_sql ORDER BY transaction_date DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories_stmt = $pdo->query("SELECT DISTINCT category FROM financial_transactions WHERE category IS NOT NULL ORDER BY category");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate totals
$totals_stmt = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expenses,
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as balance,
        COUNT(*) as total_count
    FROM financial_transactions
");
$totals = $totals_stmt->fetch(PDO::FETCH_ASSOC);

// Check for message from redirect
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = $_GET['type'] ?? 'success';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Financial Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --green: #059669;
            --green-dark: #047857;
            --light-green: #f0fdf4;
            --red: #dc2626;
            --blue: #3b82f6;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }

        /* HEADER STYLES */
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
        .notification-bell {
            position: relative;
            font-size: 23px;
            color: #64748b;
            cursor: pointer;
        }
        .notification-bell .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            width: 19px;
            height: 19px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
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
        .user-profile:hover { background: #d1fae5; }
        .user-profile img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid #059669;
        }

        /* MAIN LAYOUT */
        .main-layout {
            display: flex;
            margin-top: 80px;
            min-height: calc(100vh - 80px);
        }

        /* SIDEBAR */
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
        .sidebar .logo img { height: 60px; }
        .nav-menu { margin-top: 20px; }
        .nav-item {
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: #64748b;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
        }
        .nav-item:hover, .nav-item.active {
            background: #f0fdf4;
            color: #059669;
            border-left: 5px solid #059669;
        }
        .nav-item i { font-size: 21px; width: 30px; }

        .content-area {
            margin-left: 260px;
            padding: 40px;
            flex: 1;
        }

        /* BREADCRUMB */
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

        /* PAGE HEADER */
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
            font-size: 14px;
        }
        .btn-primary {
            background: var(--green);
            color: white;
        }
        .btn-primary:hover {
            background: var(--green-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5,150,105,0.3);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        .btn-danger {
            background: var(--red);
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }

        /* ALERT MESSAGES */
        .alert {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 18px;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .stat-title {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        .stat-value.income { color: var(--green); }
        .stat-value.expense { color: var(--red); }
        .stat-value.balance { color: var(--blue); }

        /* FORM SECTION */
        .form-section {
            background: white;
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        .form-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 24px;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
        }
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        /* FILTERS SECTION */
        .filters-section {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        .filters-row {
            display: flex;
            gap: 16px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .filter-actions {
            display: flex;
            gap: 12px;
        }

        /* TABLE SECTION */
        .table-section {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        .table-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 24px;
        }
        .table-container {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: #f8fafc;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }
        .data-table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .data-table tr:hover {
            background: #f8fafc;
        }
        .type-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .type-income {
            background: #d1fae5;
            color: #065f46;
        }
        .type-expense {
            background: #fee2e2;
            color: #991b1b;
        }
        .table-actions {
            display: flex;
            gap: 8px;
        }
        .action-btn {
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
            text-decoration: none;
        }
        .action-btn.edit {
            background: #dbeafe;
            color: var(--blue);
        }
        .action-btn.edit:hover {
            background: #bfdbfe;
        }
        .action-btn.delete {
            background: #fee2e2;
            color: var(--red);
        }
        .action-btn.delete:hover {
            background: #fecaca;
        }
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .no-data i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #64748b;
            cursor: pointer;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        /* DEBUG INFO (remove in production) */
        .debug-info {
            background: #fee2e2;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 12px;
            color: #991b1b;
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
                <input type="text" placeholder="Search financial records..." id="globalSearch">
            </div>
        </div>
        
        <div class="header-right">
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="badge">3</span>
            </div>
            <a href="../logout.php" style="text-decoration: none;">
            <div class="user-profile">
                <span><?= htmlspecialchars($user_fullname) ?></span>
                <img src="https://via.placeholder.com/44/059669/ffffff?text=<?= substr($user_fullname,0,1) ?>" alt="User">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            </a>
        </div>
        <script>
            // Toggle dropdown menu
            function toggleDropdown() {
                const dropdown = document.getElementById('userDropdown');
                dropdown.classList.toggle('show');
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const dropdown = document.getElementById('userDropdown');
                const profileContainer = document.querySelector('.user-profile-container');
                
                if (!profileContainer.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            });

            // Close dropdown on Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    document.getElementById('userDropdown').classList.remove('show');
                }
            });
        </script>
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
                <a href="financial_management.php" class="nav-item active"><i class="fas fa-coins"></i> Financial Management</a>
                <a href="financial_history.php" class="nav-item"><i class="fas fa-history"></i> History Log</a>
                <a href="profile.php" class="nav-item"><i class="fas fa-user"></i> My Profile</a>
                <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <a href="support.php"><div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div></a>
            </div>
        </div>

        <!-- CONTENT AREA -->
        <div class="content-area">
            <!-- Debug Info (remove in production) -->
            <?php if (isset($_SESSION['debug'])): ?>
            <div class="debug-info">
                User ID: <?= $user_id ?><br>
                Session: <?= print_r($_SESSION, true) ?>
            </div>
            <?php endif; ?>

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="separator">›</span>
                <a href="financial.php">Financial Overview</a>
                <span class="separator">›</span>
                <span>Financial Management</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Financial Management</h1>
                <div class="page-actions">
                    <a href="financial_history.php" class="btn btn-secondary">
                        <i class="fas fa-history"></i> View History
                    </a>
                    <button class="btn btn-primary" onclick="showAddForm()">
                        <i class="fas fa-plus"></i> Add Transaction
                    </button>
                </div>
            </div>

            <!-- Message Alert -->
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>" id="messageAlert">
                    <span><?= htmlspecialchars($message) ?></span>
                    <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Balance</div>
                    <div class="stat-value balance">₱<?= number_format($totals['balance'], 2, '.', ',') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Total Income</div>
                    <div class="stat-value income">₱<?= number_format($totals['total_income'], 2, '.', ',') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Total Expenses</div>
                    <div class="stat-value expense">₱<?= number_format($totals['total_expenses'], 2, '.', ',') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Total Transactions</div>
                    <div class="stat-value"><?= $totals['total_count'] ?></div>
                </div>
            </div>

            <!-- Add/Edit Form -->
            <div class="form-section" id="transactionForm" style="<?= $edit_transaction ? '' : 'display: none;' ?>">
                <h2 class="form-title"><?= $edit_transaction ? 'Edit Transaction' : 'Add New Transaction' ?></h2>
                <form method="POST" action="" id="transactionFormElement" onsubmit="return validateForm()">
                    <input type="hidden" name="action" value="<?= $edit_transaction ? 'edit' : 'add' ?>">
                    <?php if ($edit_transaction): ?>
                        <input type="hidden" name="id" value="<?= $edit_transaction['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Source *</label>
                            <input type="text" name="source" class="form-control" 
                                   value="<?= htmlspecialchars($edit_transaction['source'] ?? '') ?>" 
                                   placeholder="e.g., San Roque Chapel" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount *</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                                   value="<?= htmlspecialchars($edit_transaction['amount'] ?? '') ?>" 
                                   placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Type *</label>
                            <select name="type" class="form-select" required>
                                <option value="income" <?= ($edit_transaction['type'] ?? '') == 'income' ? 'selected' : '' ?>>Income</option>
                                <option value="expense" <?= ($edit_transaction['type'] ?? '') == 'expense' ? 'selected' : '' ?>>Expense</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="offering" <?= ($edit_transaction['category'] ?? '') == 'offering' ? 'selected' : '' ?>>Offering</option>
                                <option value="tithes" <?= ($edit_transaction['category'] ?? '') == 'tithes' ? 'selected' : '' ?>>Tithes</option>
                                <option value="donation" <?= ($edit_transaction['category'] ?? '') == 'donation' ? 'selected' : '' ?>>Donation</option>
                                <option value="event" <?= ($edit_transaction['category'] ?? '') == 'event' ? 'selected' : '' ?>>Event</option>
                                <option value="maintenance" <?= ($edit_transaction['category'] ?? '') == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                <option value="utilities" <?= ($edit_transaction['category'] ?? '') == 'utilities' ? 'selected' : '' ?>>Utilities</option>
                                <option value="other" <?= ($edit_transaction['category'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date *</label>
                            <input type="date" name="transaction_date" class="form-control"
                                   value="<?= htmlspecialchars($edit_transaction['transaction_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="Enter transaction description..." required><?= htmlspecialchars($edit_transaction['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="hideForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> <?= $edit_transaction ? 'Update' : 'Save' ?> Transaction
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search source or description..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Type</label>
                            <select name="type_filter" class="form-select">
                                <option value="">All Types</option>
                                <option value="income" <?= $type_filter == 'income' ? 'selected' : '' ?>>Income</option>
                                <option value="expense" <?= $type_filter == 'expense' ? 'selected' : '' ?>>Expense</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Category</label>
                            <select name="category_filter" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter == $cat ? 'selected' : '' ?>><?= ucfirst(htmlspecialchars($cat)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="financial_management.php" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="table-section">
                <h2 class="table-title">Financial Transactions (<?= count($transactions) ?>)</h2>
                <div class="table-container">
                    <?php if (empty($transactions)): ?>
                        <div class="no-data">
                            <i class="fas fa-receipt"></i>
                            <p>No transactions found. Add your first transaction above.</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Source</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></td>
                                        <td><strong><?= htmlspecialchars($transaction['source']) ?></strong></td>
                                        <td><?= htmlspecialchars($transaction['description']) ?></td>
                                        <td><?= ucfirst(htmlspecialchars($transaction['category'])) ?></td>
                                        <td>
                                            <span class="type-badge type-<?= htmlspecialchars($transaction['type']) ?>">
                                                <?= ucfirst(htmlspecialchars($transaction['type'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color: <?= $transaction['type'] == 'income' ? 'var(--green)' : 'var(--red)' ?>;">
                                                <?= $transaction['type'] == 'income' ? '+' : '-' ?>₱<?= number_format($transaction['amount'], 2, '.', ',') ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="?edit=<?= $transaction['id'] ?>" class="action-btn edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <button onclick="confirmDelete(<?= $transaction['id'] ?>)" class="action-btn delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Delete</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <p>Are you sure you want to delete this transaction? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
        // Form visibility
        function showAddForm() {
            document.getElementById('transactionForm').style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
            // Clear form if showing add form
            if (!document.querySelector('input[name="id"]')) {
                document.getElementById('transactionFormElement').reset();
                document.querySelector('.form-title').textContent = 'Add New Transaction';
                document.querySelector('input[name="action"]').value = 'add';
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Save Transaction';
            }
        }
        
        function hideForm() {
            document.getElementById('transactionForm').style.display = 'none';
            // Clear the URL parameters
            if (window.location.href.includes('edit=')) {
                window.history.pushState({}, document.title, window.location.pathname);
            }
        }

        // Form validation
        function validateForm() {
            const source = document.querySelector('input[name="source"]').value.trim();
            const description = document.querySelector('textarea[name="description"]').value.trim();
            const amount = parseFloat(document.querySelector('input[name="amount"]').value);
            const date = document.querySelector('input[name="transaction_date"]').value;
            
            if (!source) {
                alert('Please enter a source.');
                return false;
            }
            if (!description) {
                alert('Please enter a description.');
                return false;
            }
            if (!amount || amount <= 0) {
                alert('Please enter a valid amount greater than 0.');
                return false;
            }
            if (!date) {
                alert('Please select a date.');
                return false;
            }
            
            return true;
        }

        // Delete confirmation
        let deleteId = null;
        
        function confirmDelete(id) {
            deleteId = id;
            document.getElementById('deleteModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('deleteModal').classList.remove('show');
            deleteId = null;
        }
        
        document.getElementById('confirmDeleteBtn').onclick = function() {
            if (deleteId) {
                window.location.href = '?delete=' + deleteId;
            }
        };

        // Global search
        document.getElementById('globalSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value;
                if (searchTerm.trim()) {
                    window.location.href = `?search=${encodeURIComponent(searchTerm)}`;
                }
            }
        });

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        };
        
        // Automatically hide success message after 5 seconds
        <?php if ($message_type === 'success'): ?>
        setTimeout(function() {
            const alert = document.getElementById('messageAlert');
            if (alert) {
                alert.style.display = 'none';
            }
        }, 5000);
        <?php endif; ?>
        
        // Show form if editing
        <?php if ($edit_transaction): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('transactionForm').style.display = 'block';
        });
        <?php endif; ?>
        
        // Debug: Log form submission
        document.getElementById('transactionFormElement').addEventListener('submit', function(e) {
            console.log('Form submitted');
            console.log('Action:', document.querySelector('input[name="action"]').value);
            console.log('Source:', document.querySelector('input[name="source"]').value);
            console.log('Amount:', document.querySelector('input[name="amount"]').value);
        });
    </script>
</body>
</html>