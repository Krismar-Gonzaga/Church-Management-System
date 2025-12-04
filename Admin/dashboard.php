<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Ensure only admins can access this view
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Administrator';

// Pagination variables
$announcements_per_page = 10;
$appointments_per_page = 8;

$announcements_page = isset($_GET['a_page']) ? (int)$_GET['a_page'] : 1;
$appointments_page = isset($_GET['ap_page']) ? (int)$_GET['ap_page'] : 1;

$announcements_offset = ($announcements_page - 1) * $announcements_per_page;
$appointments_offset = ($appointments_page - 1) * $appointments_per_page;

// Get total counts for pagination
$total_announcements = $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$total_appointments = $pdo->query("SELECT COUNT(*) FROM appointment_requests")->fetchColumn();

$total_announcement_pages = ceil($total_announcements / $announcements_per_page);
$total_appointment_pages = ceil($total_appointments / $appointments_per_page);

// Fetch all announcements (without LIMIT)
$announcements_stmt = $pdo->prepare("
    SELECT a.*, u.fullname 
    FROM announcements a 
    JOIN users u ON a.posted_by = u.id 
    ORDER BY a.created_at DESC 
    LIMIT :limit OFFSET :offset
");
$announcements_stmt->bindValue(':limit', $announcements_per_page, PDO::PARAM_INT);
$announcements_stmt->bindValue(':offset', $announcements_offset, PDO::PARAM_INT);
$announcements_stmt->execute();
$announcements = $announcements_stmt->fetchAll();

// Fetch all appointments (without LIMIT)
$appointments_stmt = $pdo->prepare("
    SELECT ar.*, u.fullname, ar.type, ar.status 
    FROM appointment_requests ar 
    JOIN users u ON ar.user_id = u.id 
    ORDER BY ar.requested_at DESC 
    LIMIT :limit OFFSET :offset
");
$appointments_stmt->bindValue(':limit', $appointments_per_page, PDO::PARAM_INT);
$appointments_stmt->bindValue(':offset', $appointments_offset, PDO::PARAM_INT);
$appointments_stmt->execute();
$appointments = $appointments_stmt->fetchAll();

// For the count display
$all_announcements_count = $total_announcements;
$all_appointments_count = $total_appointments;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }

        /* TOP HEADER - FIXED AT THE VERY TOP */
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
        .header-left .priest-img {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #059669;
            box-shadow: 0 4px 15px rgba(5,150,105,0.3);
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
        .user-profile span {
            font-weight: 600;
            color: #065f46;
        }
        .dropdown-arrow { color: #059669; font-size: 14px; }

        /* Main Layout - Starts below the fixed header */
        .main-layout {
            display: flex;
            margin-top: 80px; /* Space for fixed header */
            min-height: calc(100vh - 80px);
        }

        /* Left Sidebar */
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
        }
        .nav-item:hover, .nav-item.active {
            background: #f0fdf4;
            color: #059669;
            border-left: 5px solid #059669;
        }
        .nav-item i { font-size: 21px; width: 30px; }

        /* Content Area */
        .content-area {
            margin-left: 260px;
            flex: 1;
            display: flex;
            gap: 50px;
            padding: 40px;
        }
        .center-area { 
            flex: 2; 
            max-width: 820px; 
        }
        .page-title {
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 24px;
            color: #1e293b;
        }
        .section {
            background: white;
            border-radius: 18px;
            padding: 28px;
            height: fit-content;
            box-shadow: 0 6px 25px rgba(0,0,0,0.06);
            margin-bottom: 30px;
        }
        .announcement {
            display: flex;
            gap: 18px;
            min-height: 120px;
            padding: 20px 0;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 18px;
            border-radius: 12px;
        }
        .announcement:last-child { border-bottom: none; margin-bottom: 0; }
        .announcement img { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; }
        .announcement-meta { 
            font-size: 13.5px; 
            color: #64748b; 
            margin-bottom: 8px; 
        }
        .announcement-title { 
            font-size: 18px; 
            font-weight: 600; 
            color: #065f46; 
            margin-bottom: 10px; 
        }

        /* Right Sidebar */
        .right-sidebar {
            width: 500px;
            background: white;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.06);
            height: fit-content;
            position: sticky;
            top: 110px;
        }
        .sidebar-title {
            font-size: 19px;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 22px;
        }
        .appointment-item { 
            padding: 16px 0; 
            border-bottom: 1px solid #f1f5f9; 
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .appointment-card {
            background: #ffffff;
            padding: 18px 20px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            transition: all 0.35s ease;
            position: relative;
            overflow: hidden;
        }
        .appointment-item:hover .appointment-card {
            transform: translateY(-6px);
            box-shadow: 0 14px 32px rgba(5, 150, 105, 0.18) !important;
            border-color: #059669;
            background: linear-gradient(to bottom, #ffffff 0%, #f8fff9 100%);
        }
        .appointment-item:last-child { border-bottom: none; }
        .status {
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 11.5px; 
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
        }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-rejected { background: #fce7f3; color: #be123c; }
        .status-rescheduled { background: #dbeafe; color: #1d4ed8; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .btn-view {
            background: #e2e8f0; 
            color: #475569; 
            padding: 7px 14px; 
            border-radius: 10px;
            font-size: 12px; 
            border: none; 
            cursor: pointer; 
            font-weight: 500;
            float: right;
            margin-top: -25px;
        }
        
        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .pagination-btn {
            padding: 8px 16px;
            background: #f0fdf4;
            border: 1px solid #d1fae5;
            border-radius: 8px;
            color: #065f46;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .pagination-btn:hover:not(:disabled) {
            background: #059669;
            color: white;
            border-color: #059669;
        }
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .pagination-info {
            color: #64748b;
            font-size: 14px;
            margin: 0 10px;
        }
        
        /* Count Badges */
        .count-badge {
            background: #059669;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
            vertical-align: middle;
        }

        @media (max-width: 1024px) {
            .content-area { flex-direction: column; padding: 20px; }
            .right-sidebar { width: 100%; order: -1; position: static; }
            .top-header { padding: 0 20px; height: 70px; }
            .header-left { gap: 15px; }
            .header-left .logo-img { height: 48px; }
            .header-left .priest-img { width: 46px; height: 46px; }
        }
        
        /* SEARCH BAR STYLES */
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
        
        .parish-name {
            font-size: 22px;
            color: #065f46;
            font-weight: 700;
        }
    </style>
</head>
<body>

    <!-- TOP FIXED HEADER -->
    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo" class="logo-img">
            <h3 class="parish-name">San Jose Parish Laligan</h3>
        </div>

        <!-- SEARCH BAR (CENTERED) -->
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

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Left Sidebar -->
        <div class="sidebar">
            <div class="nav-menu">
                <a href="dashboard.php"><div class="nav-item active"><i class="fas fa-tachometer-alt"></i> Dashboard</div></a>
                <a href="announcements.php"><div class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</div></a>
                <a href="calendar.php"><div class="nav-item"><i class="fas fa-calendar"></i> Calendar</div></a>
                <a href="appointments.php"><div class="nav-item"><i class="fas fa-clock"></i> Appointments</div></a>
                <a href="financial.php"><div class="nav-item"><i class="fas fa-coins"></i> Financial</div></a>
                <a href="profile.php"><div class="nav-item"><i class="fas fa-user"></i> My Profile</div></a>
                <a href="userManagement.php"><div class="nav-item"><i class="fas fa-users-cog"></i> User Management</div></a>
                <a href="support.php"><div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div></a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-area">
            <!-- Center: Announcements -->
            <div class="center-area">
                <h1 class="page-title">Dashboard</h1>
                <div class="section">
                    <h2 style="margin-bottom:24px; color:#065f46; font-size:22px;">
                        All Announcements 
                        <span class="count-badge"><?= $all_announcements_count ?> Total</span>
                    </h2>
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $a): ?>
                        <div class="announcement">
                            <img src="https://via.placeholder.com/52/6366f1/ffffff?text=<?= substr($a['fullname'],0,1) ?>" alt="Poster">
                            <div style="flex:1;">
                                <div class="announcement-meta">
                                    <strong><?= htmlspecialchars($a['fullname']) ?></strong> • <?= date('M j, Y • g:i A', strtotime($a['created_at'])) ?>
                                </div>
                                <div class="announcement-title"><?= htmlspecialchars($a['title']) ?></div>
                                <p style="color:#475569; line-height:1.7; margin-top: 5px;">
                                    <?= nl2br(htmlspecialchars(substr($a['message'], 0, 200))) ?>
                                    <?= strlen($a['message']) > 200 ? '...' : '' ?>
                                </p>
                            </div>
                            <div style="margin-left:auto; font-size:26px; color:#94a3b8; align-self: center;">
                                <i class="fas fa-comment-dots"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Announcements Pagination -->
                        <?php if ($total_announcement_pages > 1): ?>
                        <div class="pagination">
                            <button class="pagination-btn" 
                                onclick="window.location.href='?a_page=<?= max(1, $announcements_page-1) ?>&ap_page=<?= $appointments_page ?>'"
                                <?= $announcements_page <= 1 ? 'disabled' : '' ?>>
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                            <span class="pagination-info">
                                Page <?= $announcements_page ?> of <?= $total_announcement_pages ?>
                                (<?= $all_announcements_count ?> announcements)
                            </span>
                            <button class="pagination-btn" 
                                onclick="window.location.href='?a_page=<?= min($total_announcement_pages, $announcements_page+1) ?>&ap_page=<?= $appointments_page ?>'"
                                <?= $announcements_page >= $total_announcement_pages ? 'disabled' : '' ?>>
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <p style="text-align:center; padding:50px; color:#94a3b8; font-size:16px;">
                            <i class="fas fa-bullhorn" style="font-size: 48px; margin-bottom: 20px; display: block;"></i>
                            No announcements have been created yet.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Sidebar: Appointments -->
            <div class="right-sidebar">
                <div class="sidebar-title">
                    All Appointment Requests
                    <span class="count-badge"><?= $all_appointments_count ?> Total</span>
                </div>
                
                <?php if (!empty($appointments)): ?>
                    <?php foreach ($appointments as $ap): ?>
                    <div class="appointment-item">
                        <div class="appointment-card">
                            <div class="appointment-header">
                                <h4 style="margin-bottom: 5px;"><?= htmlspecialchars($ap['fullname']) ?></h4>
                                <div style="font-size: 13px; color: #64748b; margin-bottom: 10px;">
                                    <i class="far fa-calendar"></i> <?= date('M j, Y', strtotime($ap['requested_at'])) ?>
                                    <i class="far fa-clock" style="margin-left: 15px;"></i> <?= date('g:i A', strtotime($ap['requested_at'])) ?>
                                </div>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <strong>Type:</strong> <?= ucfirst($ap['type']) ?>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span class="status status-<?= strtolower($ap['status']) ?>">
                                    <?= ucfirst($ap['status']) ?>
                                </span>
                                <button class="btn-view" onclick="viewAppointment(<?= $ap['id'] ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Appointments Pagination -->
                    <?php if ($total_appointment_pages > 1): ?>
                    <div class="pagination">
                        <button class="pagination-btn" 
                            onclick="window.location.href='?a_page=<?= $announcements_page ?>&ap_page=<?= max(1, $appointments_page-1) ?>'"
                            <?= $appointments_page <= 1 ? 'disabled' : '' ?>>
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <span class="pagination-info">
                            Page <?= $appointments_page ?> of <?= $total_appointment_pages ?>
                        </span>
                        <button class="pagination-btn" 
                            onclick="window.location.href='?a_page=<?= $announcements_page ?>&ap_page=<?= min($total_appointment_pages, $appointments_page+1) ?>'"
                            <?= $appointments_page >= $total_appointment_pages ? 'disabled' : '' ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <p style="text-align:center; padding:30px; color:#94a3b8; font-size:16px;">
                        <i class="fas fa-calendar-check" style="font-size: 48px; margin-bottom: 20px; display: block;"></i>
                        No appointment requests yet.
                    </p>
                <?php endif; ?>
                
                <div style="text-align:center; margin-top:28px; color:#059669; font-weight:600; cursor:pointer; font-size:15px;">
                    <i class="fas fa-chart-bar"></i> View Analytics Dashboard
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function viewAppointment(id) {
            // You can implement a modal or redirect to view appointment details
            alert('Viewing appointment ID: ' + id);
            // window.location.href = 'view_appointment.php?id=' + id;
        }
        
        // Simple function to show all appointments/announcements
        function showAll(type) {
            if (type === 'announcements') {
                window.location.href = 'announcements.php?show=all';
            } else if (type === 'appointments') {
                window.location.href = 'appointments.php?show=all';
            }
        }
    </script>
</body>
</html>