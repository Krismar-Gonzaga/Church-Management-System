<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Ensure only administrators can access
if (($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Staff';
$user_id = $_SESSION['user_id'] ?? 0;

$message = '';
$message_type = '';

// Handle Accept Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept') {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    
    if ($appointment_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE appointment_requests SET status = 'approved' WHERE id = ?");
            if ($stmt->execute([$appointment_id])) {
                $message = 'Appointment accepted successfully!';
                $message_type = 'success';
                // Refresh to show updated status
                header("Location: appointments.php?message=accepted&page=" . ($_GET['page'] ?? 1));
                exit;
            } else {
                $message = 'Failed to accept appointment';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle Reject Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    
    if ($appointment_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE appointment_requests SET status = 'rejected' WHERE id = ?");
            if ($stmt->execute([$appointment_id])) {
                $message = 'Appointment rejected successfully!';
                $message_type = 'success';
                // Refresh to show updated status
                header("Location: appointments.php?message=rejected&page=" . ($_GET['page'] ?? 1));
                exit;
            } else {
                $message = 'Failed to reject appointment';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle Reschedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reschedule') {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $new_datetime = $_POST['new_datetime'] ?? '';
    
    if ($appointment_id > 0 && !empty($new_datetime)) {
        try {
            $stmt = $pdo->prepare("UPDATE appointment_requests SET status = 'rescheduled', preferred_datetime = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$new_datetime, $appointment_id])) {
                $message = 'Appointment rescheduled successfully!';
                $message_type = 'success';
                header("Location: appointments.php?message=rescheduled&page=" . ($_GET['page'] ?? 1));
                exit;
            } else {
                $message = 'Failed to reschedule appointment';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle message from redirect
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'accepted':
            $message = 'Appointment accepted successfully!';
            $message_type = 'success';
            break;
        case 'rejected':
            $message = 'Appointment rejected successfully!';
            $message_type = 'success';
            break;
        case 'rescheduled':
            $message = 'Appointment rescheduled successfully!';
            $message_type = 'success';
            break;
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 8;
$offset = ($page - 1) * $limit;

// Build search conditions
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "ar.status = ?";
    $params[] = $status_filter;
}

if ($type_filter !== 'all') {
    $where_conditions[] = "ar.type = ?";
    $params[] = $type_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(u.fullname LIKE ? OR ar.purpose LIKE ? OR ar.notes LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(ar.preferred_datetime) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(ar.preferred_datetime) <= ?";
    $params[] = $date_to;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM appointment_requests ar JOIN users u ON ar.user_id = u.id $where_clause";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();

// Fetch all appointments with filters and pagination
$query = "
    SELECT ar.*, u.fullname AS requester_name, u.profile_pic, u.email, u.phone,
           (SELECT COUNT(*) FROM appointment_requests WHERE user_id = u.id) as user_total_appointments
    FROM appointment_requests ar 
    JOIN users u ON ar.user_id = u.id 
    $where_clause
    ORDER BY ar.preferred_datetime DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_pages = ceil($total / $limit);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        COUNT(DISTINCT user_id) as unique_users
    FROM appointment_requests
";
$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get upcoming appointments (next 7 days)
$upcoming_query = "
    SELECT ar.*, u.fullname 
    FROM appointment_requests ar 
    JOIN users u ON ar.user_id = u.id 
    WHERE ar.status = 'approved' 
    AND ar.preferred_datetime >= CURDATE() 
    AND ar.preferred_datetime <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY ar.preferred_datetime ASC 
    LIMIT 5
";
$upcoming_appointments = $pdo->query($upcoming_query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Appointments</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            width: 100%; padding: 14px 48px; border: 2px solid #e2e8f0;
            border-radius: 16px; background: #f8fafc; font-size: 15px; transition: all 0.3s;
        }
        .search-box input:focus { border-color: var(--green); background: white; box-shadow: 0 8px 25px rgba(5,150,105,0.15); }
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--green); }
        
        .header-right { display: flex; align-items: center; gap: 20px; }
        .notification-bell { position: relative; font-size: 23px; color: #64748b; cursor: pointer; }
        .notification-bell .badge { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 10px; width: 19px; height: 19px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .user-profile { display: flex; align-items: center; gap: 12px; background: var(--light-green); padding: 10px 18px; border-radius: 14px; border: 1px solid #d1fae5; cursor: pointer; }
        .user-profile:hover { background: #d1fae5; }
        .user-profile img { width: 44px; height: 44px; border-radius: 50%; border: 2px solid var(--green); }
        .user-profile span { font-weight: 600; color: #065f46; }

        .main-layout { display: flex; margin-top: 80px; min-height: calc(100vh - 80px); }
        .sidebar { width: 260px; background: white; border-right: 1px solid #e2e8f0; position: fixed; top: 80px; bottom: 0; overflow-y: auto; z-index: 999; }
        .nav-item { padding: 16px 24px; display: flex; align-items: center; gap: 14px; color: #64748b; cursor: pointer; transition: 0.3s; }
        .nav-item:hover, .nav-item.active { background: var(--light-green); color: var(--green); border-left: 5px solid var(--green); }
        .nav-item a { text-decoration: none; color: inherit; width: 100%; display: flex; align-items: center; gap: 14px; }

        .content-wrapper { margin-left: 260px; display: flex; gap: 40px; padding: 40px; flex: 1; }
        .center-content { flex: 2; max-width: 900px; }
        .right-sidebar { width: 420px; background: white; border-radius: 20px; padding: 28px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); position: sticky; top: 110px; height: fit-content; }

        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 20px; color: #1e293b; }

        .stats-bar {
            display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;
        }
        .stat-card {
            background: white; padding: 20px; border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06); min-width: 160px; flex: 1;
        }
        .stat-value {
            font-size: 28px; font-weight: 700; margin-bottom: 8px;
        }
        .stat-value.total { color: #059669; }
        .stat-value.pending { color: #f59e0b; }
        .stat-value.approved { color: #10b981; }
        .stat-value.rejected { color: #ef4444; }
        .stat-label { font-size: 14px; color: #64748b; font-weight: 500; }

        .filter-bar {
            display: flex; justify-content: space-between; align-items: center;
            background: white; padding: 18px 24px; border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06); margin-bottom: 30px;
            flex-wrap: wrap; gap: 16px;
        }
        .filter-group {
            display: flex; align-items: center; gap: 12px;
        }
        .filter-select, .filter-input {
            padding: 10px 16px; border-radius: 12px; border: 2px solid #e2e8f0;
            background: white; font-size: 14px; min-width: 150px;
        }
        .filter-input { min-width: 180px; }
        .filter-select:focus, .filter-input:focus {
            outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
        }
        .filter-btn {
            background: var(--green); color: white; border: none;
            padding: 10px 20px; border-radius: 12px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; gap: 8px;
        }
        .filter-btn:hover { background: var(--green-dark); }
        .clear-btn {
            background: #f1f5f9; color: #475569; border: none;
            padding: 10px 16px; border-radius: 12px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; gap: 8px;
        }
        .clear-btn:hover { background: #e2e8f0; }

        .appointments-grid { display: grid; gap: 24px; }
        .appointment-card {
            background: white; border-radius: 20px; overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08); transition: all 0.4s ease;
            border: 1px solid #f1f5f9; position: relative;
        }
        .appointment-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(5,150,105,0.18); border-color: var(--green); }

        .card-header {
            padding: 20px; display: flex; align-items: center; gap: 16px;
            background: #f8fff9; border-bottom: 1px solid #f1f5f9;
        }
        .card-header img { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 3px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .user-info { flex: 1; }
        .user-name { font-size: 17px; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
        .user-meta { font-size: 13px; color: #64748b; }
        .user-meta i { margin-right: 5px; }

        .status-badge { padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rescheduled { background: #dbeafe; color: #1d4ed8; }
        .status-rejected { background: #fce7f3; color: #be123c; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .card-body { padding: 24px; padding-bottom: 90px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px dashed #e2e8f0; }
        .detail-label { color: #64748b; font-size: 14px; }
        .detail-value { font-weight: 600; color: #065f46; }

        .notes-section { background: #f8fafc; padding: 16px; border-radius: 12px; margin: 16px 0; }
        .notes-title { font-weight: 600; color: var(--green); margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .notes-text { color: #475569; line-height: 1.6; font-size: 14px; }

        .card-actions {
            position: absolute; bottom: 24px; right: 24px;
            display: flex; gap: 10px;
        }
        .btn-accept, .btn-reject, .btn-reschedule, .btn-view {
            padding: 10px 20px; border: none; border-radius: 12px;
            font-size: 14px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 8px; transition: 0.3s;
        }
        .btn-accept {
            background: #d1fae5; color: #065f46;
        }
        .btn-accept:hover {
            background: var(--green); color: white;
        }
        .btn-reject {
            background: #fee2e2; color: #991b1b;
        }
        .btn-reject:hover {
            background: #ef4444; color: white;
        }
        .btn-reschedule {
            background: #dbeafe; color: #1d4ed8;
        }
        .btn-reschedule:hover {
            background: #3b82f6; color: white;
        }
        .btn-view {
            background: #f3f4f6; color: #374151;
        }
        .btn-view:hover {
            background: #e5e7eb; color: #1f2937;
        }

        .alert {
            padding: 14px 20px; border-radius: 12px; margin-bottom: 20px;
            font-size: 14px; font-weight: 500; display: flex;
            justify-content: space-between; align-items: center;
        }
        .alert-success {
            background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;
        }
        .alert-close {
            background: none; border: none; font-size: 20px;
            cursor: pointer; color: inherit; opacity: 0.7;
        }
        .alert-close:hover { opacity: 1; }

        /* Pagination */
        .pagination {
            display: flex; justify-content: center; align-items: center;
            gap: 10px; margin-top: 40px; padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }
        .pagination-btn {
            padding: 10px 18px; background: #f0fdf4; border: 1px solid #d1fae5;
            border-radius: 10px; color: #065f46; font-weight: 600;
            cursor: pointer; transition: all 0.3s; display: flex;
            align-items: center; gap: 6px;
        }
        .pagination-btn:hover:not(:disabled) {
            background: var(--green); color: white; border-color: var(--green);
        }
        .pagination-btn:disabled {
            opacity: 0.5; cursor: not-allowed;
        }
        .pagination-info {
            color: #64748b; font-size: 14px; margin: 0 15px;
        }

        /* Modal */
        .modal {
            display: none; position: fixed; z-index: 2000;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
        }
        .modal-content {
            background-color: white; margin: 5% auto; padding: 0;
            border-radius: 20px; width: 90%; max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header {
            padding: 24px 30px; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h2 { font-size: 24px; color: #065f46; font-weight: 700; }
        .close-btn {
            background: none; border: none; font-size: 28px; color: #64748b;
            cursor: pointer; padding: 0; width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px; transition: 0.3s;
        }
        .close-btn:hover { background: #f1f5f9; color: #1e293b; }
        .modal-body { padding: 30px; }
        .modal-footer {
            padding: 20px 30px; border-top: 1px solid #e2e8f0;
            display: flex; justify-content: flex-end; gap: 12px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; margin-bottom: 8px; font-weight: 600;
            color: #1e293b; font-size: 14px;
        }
        .form-group input {
            width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0;
            border-radius: 12px; font-size: 15px; transition: 0.3s;
        }
        .form-group input:focus {
            outline: none; border-color: var(--green);
            box-shadow: 0 0 0 4px rgba(5,150,105,0.1);
        }

        @media (max-width: 1200px) {
            .content-wrapper { flex-direction: column; padding: 20px; }
            .right-sidebar { width: 100%; position: static; }
            .filter-bar { flex-direction: column; align-items: flex-start; }
            .stats-bar { flex-direction: column; }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo">
            <h3 style="color:#065f46; font-size:24px; font-weight:700;">San Jose Parish Laligan</h3>
        </div>
        <form method="GET" action="" class="header-search">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="search" placeholder="Search appointments..." value="<?= htmlspecialchars($search_query) ?>">
            </div>
        </form>
        <div class="header-right">
            <div class="notification-bell"><i class="fas fa-bell"></i><span class="badge"><?= $stats['pending'] ?? 0 ?></span></div>
            <div class="user-profile">
                <span><?= htmlspecialchars($user_fullname) ?></span>
                <img src="https://via.placeholder.com/44/059669/ffffff?text=<?= substr($user_fullname,0,1) ?>" alt="User">
                <i class="fas fa-caret-down"></i>
            </div>
        </div>
    </div>

    <!-- SIDEBAR -->
    <div class="main-layout">
        <div class="sidebar">
            <div class="nav-menu" style="margin-top:20px;">
                <a href="dashboard.php"><div class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</div></a>
                <a href="announcements.php"><div class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</div></a>
                <a href="calendar.php"><div class="nav-item"><i class="fas fa-calendar"></i> Calendar</div></a>
                <a href="appointments.php"><div class="nav-item active"><i class="fas fa-clock"></i> Appointments</div></a>
                <a href="financial.php"><div class="nav-item"><i class="fas fa-coins"></i> Financial</div></a>
                <a href="profile.php"><div class="nav-item"><i class="fas fa-user"></i> My Profile</div></a>
                <a href="support.php"><div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div></a>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="content-wrapper">
            <div class="center-content">
                <h1 class="page-title">All Appointments</h1>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($message) ?>
                        <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-value total"><?= $stats['total'] ?? 0 ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value pending"><?= $stats['pending'] ?? 0 ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value approved"><?= $stats['approved'] ?? 0 ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value rejected"><?= $stats['rejected'] ?? 0 ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>

                <!-- Filter Bar -->
                <form method="GET" action="" class="filter-bar">
                    <div class="filter-group">
                        <strong>Filter:</strong>
                        <select name="status" class="filter-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="rescheduled" <?= $status_filter === 'rescheduled' ? 'selected' : '' ?>>Rescheduled</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <select name="type" class="filter-select">
                            <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="baptism" <?= $type_filter === 'baptism' ? 'selected' : '' ?>>Baptism</option>
                            <option value="wedding" <?= $type_filter === 'wedding' ? 'selected' : '' ?>>Wedding</option>
                            <option value="mass_intention" <?= $type_filter === 'mass_intention' ? 'selected' : '' ?>>Mass Intention</option>
                            <option value="confession" <?= $type_filter === 'confession' ? 'selected' : '' ?>>Confession</option>
                            <option value="blessing" <?= $type_filter === 'blessing' ? 'selected' : '' ?>>Blessing</option>
                        </select>
                        <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($date_from) ?>" placeholder="From Date">
                        <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($date_to) ?>" placeholder="To Date">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <?php if ($status_filter !== 'all' || $type_filter !== 'all' || !empty($search_query) || !empty($date_from) || !empty($date_to)): ?>
                            <a href="appointments.php" class="clear-btn">
                                <i class="fas fa-times"></i> Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (!empty($search_query) || $status_filter !== 'all' || $type_filter !== 'all' || !empty($date_from) || !empty($date_to)): ?>
                    <div style="margin-bottom:20px; padding:12px 16px; background:#f0f9ff; border-radius:12px; border-left:4px solid var(--green);">
                        <strong>Active Filters:</strong>
                        <?php if ($status_filter !== 'all'): ?>
                            <span style="background:#d1fae5; padding:4px 8px; border-radius:6px; margin:0 5px;">Status: <?= ucfirst($status_filter) ?></span>
                        <?php endif; ?>
                        <?php if ($type_filter !== 'all'): ?>
                            <span style="background:#dbeafe; padding:4px 8px; border-radius:6px; margin:0 5px;">Type: <?= ucwords(str_replace('_', ' ', $type_filter)) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($search_query)): ?>
                            <span style="background:#fef3c7; padding:4px 8px; border-radius:6px; margin:0 5px;">Search: "<?= htmlspecialchars($search_query) ?>"</span>
                        <?php endif; ?>
                        <?php if (!empty($date_from)): ?>
                            <span style="background:#dcfce7; padding:4px 8px; border-radius:6px; margin:0 5px;">From: <?= htmlspecialchars($date_from) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($date_to)): ?>
                            <span style="background:#fee2e2; padding:4px 8px; border-radius:6px; margin:0 5px;">To: <?= htmlspecialchars($date_to) ?></span>
                        <?php endif; ?>
                        <span style="background:#f3f4f6; padding:4px 8px; border-radius:6px; margin:0 5px;">
                            Showing <?= count($appointments) ?> of <?= $total ?> appointments
                        </span>
                    </div>
                <?php endif; ?>

                <div class="appointments-grid">
                    <?php if (empty($appointments)): ?>
                        <div style="text-align:center; padding:100px 20px; background:white; border-radius:20px; color:#94a3b8;">
                            <i class="fas fa-calendar-times" style="font-size:48px; margin-bottom:16px; color:#cbd5e1;"></i>
                            <p style="font-size:18px; margin-bottom:10px;">No appointment requests found.</p>
                            <?php if (!empty($search_query) || $status_filter !== 'all' || $type_filter !== 'all'): ?>
                                <p style="color:#64748b; font-size:14px;">Try adjusting your filters or search criteria.</p>
                            <?php else: ?>
                                <p style="color:#64748b; font-size:14px;">Check back later for new appointment requests.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($appointments as $ap): ?>
                            <div class="appointment-card" id="appointment-<?= $ap['id'] ?>">
                                <div class="card-header">
                                    <img src="<?= $ap['profile_pic'] ? '../uploads/'.$ap['profile_pic'] : 'https://via.placeholder.com/52/059669/ffffff?text='.substr($ap['requester_name'],0,1) ?>" alt="User">
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($ap['requester_name']) ?></div>
                                        <div class="user-meta">
                                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($ap['email']) ?>
                                            <?php if ($ap['phone']): ?>
                                                <span style="margin-left:15px;"><i class="fas fa-phone"></i> <?= htmlspecialchars($ap['phone']) ?></span>
                                            <?php endif; ?>
                                            <span style="margin-left:15px;"><i class="fas fa-calendar-check"></i> Total Appointments: <?= $ap['user_total_appointments'] ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="status-badge status-<?= htmlspecialchars($ap['status']) ?>">
                                            <?= ucwords(str_replace('_', ' ', $ap['status'])) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="detail-row">
                                        <span class="detail-label">Appointment Type</span>
                                        <span class="detail-value"><?= ucwords(str_replace('_', ' ', $ap['type'])) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Chapel/Parish</span>
                                        <span class="detail-value"><?= ucwords(str_replace('_', ' ', $ap['chapel'])) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Preferred Date & Time</span>
                                        <span class="detail-value">
                                            <i class="far fa-calendar"></i> <?= date('F j, Y', strtotime($ap['preferred_datetime'])) ?>
                                            <i class="far fa-clock" style="margin-left:10px;"></i> <?= date('g:i A', strtotime($ap['preferred_datetime'])) ?>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Submitted On</span>
                                        <span class="detail-value"><?= date('M j, Y g:i A', strtotime($ap['requested_at'])) ?></span>
                                    </div>
                                    <?php if ($ap['priest']): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Requested Priest</span>
                                        <span class="detail-value"><?= ucwords(str_replace('_', ' ', $ap['priest'])) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($ap['purpose']): ?>
                                    <div class="notes-section">
                                        <div class="notes-title"><i class="fas fa-bullseye"></i> Purpose / Intention</div>
                                        <div class="notes-text"><?= nl2br(htmlspecialchars($ap['purpose'])) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($ap['notes']): ?>
                                    <div class="notes-section">
                                        <div class="notes-title"><i class="fas fa-sticky-note"></i> Additional Notes</div>
                                        <div class="notes-text"><?= nl2br(htmlspecialchars($ap['notes'])) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($ap['address']): ?>
                                    <div class="notes-section">
                                        <div class="notes-title"><i class="fas fa-map-marker-alt"></i> Address (Blessing/Visit)</div>
                                        <div class="notes-text"><?= htmlspecialchars($ap['address']) ?></div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="card-actions">
                                        <?php if ($ap['status'] === 'pending'): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="action" value="accept">
                                                <input type="hidden" name="appointment_id" value="<?= $ap['id'] ?>">
                                                <button type="submit" class="btn-accept" onclick="return confirm('Are you sure you want to accept this appointment?')">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                            </form>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="appointment_id" value="<?= $ap['id'] ?>">
                                                <button type="submit" class="btn-reject" onclick="return confirm('Are you sure you want to reject this appointment?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                            <button class="btn-reschedule" onclick="openRescheduleModal(<?= $ap['id'] ?>)">
                                                <i class="fas fa-calendar-alt"></i> Reschedule
                                            </button>
                                        <?php elseif ($ap['status'] === 'rescheduled'): ?>
                                            <button class="btn-reschedule" onclick="openRescheduleModal(<?= $ap['id'] ?>)">
                                                <i class="fas fa-calendar-alt"></i> Update Schedule
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-view" onclick="viewAppointment(<?= $ap['id'] ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <button class="pagination-btn" onclick="goToPage(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    
                    <div class="pagination-info">
                        Page <?= $page ?> of <?= $total_pages ?> (<?= $total ?> appointments)
                    </div>
                    
                    <button class="pagination-btn" onclick="goToPage(<?= $page + 1 ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT SIDEBAR - UPCOMING APPOINTMENTS -->
            <div class="right-sidebar">
                <h3 style="color:#065f46; font-size:21px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-calendar-check"></i> Upcoming Appointments
                    <span style="font-size:14px; color:#64748b; font-weight:normal;">(Next 7 Days)</span>
                </h3>
                
                <?php if (!empty($upcoming_appointments)): ?>
                    <?php foreach ($upcoming_appointments as $up): ?>
                        <div style="display:flex; justify-content:space-between; padding:14px 0; border-bottom:1px solid #f1f5f9; align-items:center;">
                            <div style="display:flex; gap:12px; align-items:center;">
                                <div style="width:40px; height:40px; border-radius:10px; background:var(--light-green); display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-church" style="color:var(--green); font-size:18px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600; font-size:14px; color:#1e293b;">
                                        <?= htmlspecialchars($up['fullname']) ?>
                                    </div>
                                    <small style="color:#64748b; display:block;">
                                        <i class="far fa-calendar"></i> <?= date('M j', strtotime($up['preferred_datetime'])) ?>
                                        <i class="far fa-clock" style="margin-left:10px;"></i> <?= date('g:i A', strtotime($up['preferred_datetime'])) ?>
                                    </small>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:13px; font-weight:600; color:#059669;">
                                    <?= ucfirst($up['type']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:40px 20px; color:#94a3b8;">
                        <i class="fas fa-calendar-plus" style="font-size:48px; margin-bottom:16px; opacity:0.3;"></i>
                        <p>No upcoming appointments</p>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top:30px; padding-top:20px; border-top:1px solid #f1f5f9;">
                    <h4 style="color:#065f46; font-size:16px; margin-bottom:15px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-chart-bar"></i> Quick Stats
                    </h4>
                    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px;">
                        <div style="background:#f8fafc; padding:12px; border-radius:10px; text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:var(--green);"><?= $stats['unique_users'] ?? 0 ?></div>
                            <div style="font-size:12px; color:#64748b;">Unique Users</div>
                        </div>
                        <div style="background:#f8fafc; padding:12px; border-radius:10px; text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:#f59e0b;"><?= $stats['pending'] ?? 0 ?></div>
                            <div style="font-size:12px; color:#64748b;">Needs Action</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reschedule Appointment</h2>
                <button class="close-btn" onclick="closeRescheduleModal()">&times;</button>
            </div>
            <form method="POST" action="" id="rescheduleForm">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="appointment_id" id="reschedule-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_datetime">New Date & Time</label>
                        <input type="datetime-local" id="new_datetime" name="new_datetime" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeRescheduleModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Reschedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewAppointment(id) {
            // You can implement a detailed view modal here
            alert('Viewing appointment details for ID: ' + id);
            // window.location.href = 'view_appointment.php?id=' + id;
        }

        function openRescheduleModal(id) {
            document.getElementById('reschedule-id').value = id;
            
            // Set default date to tomorrow at 9 AM
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(9, 0, 0, 0);
            
            // Format for datetime-local input (YYYY-MM-DDTHH:MM)
            const formattedDate = tomorrow.toISOString().slice(0, 16);
            document.getElementById('new_datetime').value = formattedDate;
            document.getElementById('new_datetime').min = new Date().toISOString().slice(0, 16);
            
            document.getElementById('rescheduleModal').style.display = 'block';
        }

        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').style.display = 'none';
            document.getElementById('rescheduleForm').reset();
        }

        function goToPage(pageNum) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', pageNum);
            window.location.href = url.toString();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('rescheduleModal');
            if (event.target === modal) {
                closeRescheduleModal();
            }
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeRescheduleModal();
            }
        });

        // Auto-hide success message after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                if (alert.style.display !== 'none') {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.style.display = 'none', 500);
                }
            });
        }, 5000);

        // Add today's date as default for date inputs
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                if (!input.value) {
                    if (input.name === 'date_to') {
                        input.value = today;
                    }
                }
            });
        });
    </script>
</body>
</html>