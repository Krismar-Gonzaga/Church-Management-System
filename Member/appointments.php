<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Member';
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'member';

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    
    if ($appointment_id > 0) {
        try {
            // Verify the appointment belongs to the user
            $check_stmt = $pdo->prepare("SELECT id FROM appointment_requests WHERE id = ? AND user_id = ?");
            $check_stmt->execute([$appointment_id, $user_id]);
            
            if ($check_stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE appointment_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$appointment_id])) {
                    header("Location: appointments.php?message=cancelled");
                    exit;
                }
            }
        } catch (Exception $e) {
            // Silently fail
        }
    }
}

// Handle message from redirect
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'cancelled':
            $message = 'Appointment cancelled successfully!';
            $message_type = 'success';
            break;
        case 'requested':
            $message = 'Appointment request submitted successfully!';
            $message_type = 'success';
            break;
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on role
$where_conditions = ["ar.user_id = ?"];
$params = [$user_id];

if ($status_filter !== 'all') {
    $where_conditions[] = "ar.status = ?";
    $params[] = $status_filter;
}

if ($type_filter !== 'all') {
    $where_conditions[] = "ar.type = ?";
    $params[] = $type_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(ar.purpose LIKE ? OR ar.notes LIKE ? OR ar.chapel LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get appointment statistics for the member
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled
    FROM appointment_requests 
    WHERE user_id = ?
";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// MODIFIED QUERY: Fixed to use correct table structure
$query = "
    SELECT ar.*, 
           u.fullname AS requester_name, 
           u.profile_pic,
           p.fullname as priest_fullname, 
           p.profile_pic as priest_profile_pic
    FROM appointment_requests ar 
    LEFT JOIN users u ON ar.user_id = u.id 
    LEFT JOIN users p ON ar.priest_id = p.id 
    $where_clause
    ORDER BY ar.preferred_datetime DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments for sidebar
$upcoming_query = "
    SELECT ar.*, u.fullname, p.fullname as priest_name
    FROM appointment_requests ar 
    LEFT JOIN users u ON ar.user_id = u.id 
    LEFT JOIN users p ON ar.priest_id = p.id
    WHERE ar.user_id = ? 
    AND ar.status = 'approved'
    AND ar.preferred_datetime >= CURDATE()
    ORDER BY ar.preferred_datetime ASC 
    LIMIT 5
";
$upcoming_stmt = $pdo->prepare($upcoming_query);
$upcoming_stmt->execute([$user_id]);
$upcoming_appointments = $upcoming_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | My Appointments</title>
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

        /* Statistics */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; padding: 20px; border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06); text-align: center;
        }
        .stat-value {
            font-size: 28px; font-weight: 700; margin-bottom: 8px;
        }
        .stat-value.total { color: var(--green); }
        .stat-value.pending { color: #f59e0b; }
        .stat-value.approved { color: var(--green); }
        .stat-label { font-size: 14px; color: #64748b; font-weight: 500; }

        /* Filter Bar */
        .filter-bar {
            display: flex; justify-content: space-between; align-items: center;
            background: white; padding: 18px 24px; border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06); margin-bottom: 30px;
            flex-wrap: wrap; gap: 15px;
        }
        .filter-group {
            display: flex; align-items: center; gap: 12px;
        }
        .filter-select {
            padding: 10px 16px; border-radius: 12px; border: 2px solid #e2e8f0;
            background: white; font-size: 14px; min-width: 150px;
        }
        .filter-select:focus {
            outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
        }
        .btn-request {
            background: var(--green); color: white; border: none;
            padding: 12px 28px; border-radius: 14px; font-weight: 600; 
            cursor: pointer; display: flex; align-items: center; gap: 8px; 
            transition: 0.3s; text-decoration: none; white-space: nowrap;
        }
        .btn-request:hover { 
            background: var(--green-dark); color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5,150,105,0.2);
        }

        /* Message Alerts */
        .alert {
            padding: 14px 20px; border-radius: 12px; margin-bottom: 20px;
            font-size: 14px; font-weight: 500; display: flex;
            justify-content: space-between; align-items: center;
        }
        .alert-success {
            background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;
        }
        .alert-close {
            background: none; border: none; font-size: 20px;
            cursor: pointer; color: inherit; opacity: 0.7;
        }
        .alert-close:hover { opacity: 1; }

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

        .status-badge { padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rescheduled { background: #dbeafe; color: #1d4ed8; }
        .status-rejected { background: #fce7f3; color: #be123c; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .detail-row { display: flex; justify-content: space-between; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px dashed #e2e8f0; }
        .detail-label { color: #64748b; font-size: 14px; }
        .detail-value { font-weight: 600; color: #065f46; }

        .notes-section { background: #f8fafc; padding: 16px; border-radius: 12px; margin: 16px 0; }
        .notes-title { font-weight: 600; color: #059669; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .notes-text { color: #475569; line-height: 1.6; font-size: 14px; }

        .card-actions {
            display: flex; gap: 10px; margin-top: 20px;
        }
        .btn-view-details, .btn-cancel {
            padding: 12px 20px; border: none; border-radius: 12px;
            font-weight: 600; cursor: pointer; display: flex;
            align-items: center; gap: 8px; transition: 0.3s; flex: 1;
        }
        .btn-view-details {
            background: var(--green); color: white;
        }
        .btn-view-details:hover {
            background: var(--green-dark); transform: translateY(-2px);
        }
        .btn-cancel {
            background: #fef3c7; color: #92400e; border: 2px solid #fef3c7;
        }
        .btn-cancel:hover {
            background: #fde68a; border-color: #fbbf24;
            transform: translateY(-2px);
        }
        .btn-cancel:disabled {
            opacity: 0.5; cursor: not-allowed;
            background: #f3f4f6; color: #9ca3af; border-color: #e5e7eb;
        }

        /* Priest Badge */
        .priest-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #e0f2fe; color: #0369a1; padding: 4px 10px;
            border-radius: 8px; font-size: 13px; font-weight: 600;
            margin-top: 5px;
        }
        .priest-badge img {
            width: 24px; height: 24px; border-radius: 50%;
            border: 2px solid white;
        }

        /* Empty State */
        .empty-state {
            text-align: center; padding: 80px 20px; background: white; border-radius: 20px;
            color: #94a3b8; box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .empty-state i { font-size: 64px; margin-bottom: 20px; opacity: 0.3; }
        .empty-state h3 { font-size: 22px; margin-bottom: 10px; color: #64748b; }
        .empty-state p { font-size: 15px; margin-bottom: 25px; }

        @media (max-width: 1200px) {
            .content-wrapper { flex-direction: column; padding: 20px; }
            .right-sidebar { width: 100%; position: static; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-group { flex-direction: column; align-items: stretch; }
            .filter-select { width: 100%; }
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
                <input type="text" name="search" placeholder="Search your appointments..." 
                       value="<?= htmlspecialchars($search_query) ?>">
            </div>
        </form>
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
    </div>

    <!-- SIDEBAR -->
    <div class="main-layout">
        <div class="sidebar">
            <div class="nav-menu" style="margin-top:20px;">
                <a href="dashboard.php"><div class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</div></a>
                <a href="announcements.php"><div class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</div></a>
                <a href="calendar.php"><div class="nav-item"><i class="fas fa-calendar"></i> Calendar</div></a>
                <a href="appointments.php"><div class="nav-item active"><i class="fas fa-clock"></i> My Appointments</div></a>
                <a href="financial.php"><div class="nav-item"><i class="fas fa-coins"></i> Financial</div></a>
                <a href="profile.php"><div class="nav-item"><i class="fas fa-user"></i> My Profile</div></a>
                <a href="support.php"><div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div></a>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="content-wrapper">
            <div class="center-content">
                <h1 class="page-title">My Appointments</h1>

                <?php if (isset($message)): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($message) ?>
                        <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
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
                        <div class="stat-value"><?= $stats['rejected'] ?? 0 ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>

                <!-- Filter Bar -->
                <form method="GET" action="" class="filter-bar">
                    <div class="filter-group">
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="rescheduled" <?= $status_filter === 'rescheduled' ? 'selected' : '' ?>>Rescheduled</option>
                        </select>
                        <select name="type" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="baptism" <?= $type_filter === 'baptism' ? 'selected' : '' ?>>Baptism</option>
                            <option value="wedding" <?= $type_filter === 'wedding' ? 'selected' : '' ?>>Wedding</option>
                            <option value="mass_intention" <?= $type_filter === 'mass_intention' ? 'selected' : '' ?>>Mass Intention</option>
                            <option value="confession" <?= $type_filter === 'confession' ? 'selected' : '' ?>>Confession</option>
                            <option value="blessing" <?= $type_filter === 'blessing' ? 'selected' : '' ?>>Blessing</option>
                        </select>
                        <?php if ($status_filter !== 'all' || $type_filter !== 'all' || !empty($search_query)): ?>
                            <a href="appointments.php" style="padding:10px 16px; background:#f1f5f9; color:#475569; border-radius:10px; font-weight:600; text-decoration:none; display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                    <a href="requestAppointments.php" class="btn-request">
                        <i class="fas fa-plus"></i> Request New Appointment
                    </a>
                </form>

                <!-- Active Filters Display -->
                <?php if ($status_filter !== 'all' || $type_filter !== 'all' || !empty($search_query)): ?>
                    <div style="margin-bottom:20px; padding:12px 16px; background:#f0f9ff; border-radius:12px; border-left:4px solid var(--green);">
                        <strong>Active Filters:</strong>
                        <?php if ($status_filter !== 'all'): ?>
                            <span class="status-badge status-<?= $status_filter ?>" style="margin-left:10px;">
                                Status: <?= ucwords($status_filter) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($type_filter !== 'all'): ?>
                            <span style="background:#dbeafe; padding:4px 8px; border-radius:6px; margin-left:10px;">
                                Type: <?= ucwords(str_replace('_', ' ', $type_filter)) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($search_query)): ?>
                            <span style="background:#fef3c7; padding:4px 8px; border-radius:6px; margin-left:10px;">
                                Search: "<?= htmlspecialchars($search_query) ?>"
                            </span>
                        <?php endif; ?>
                        <span style="margin-left:10px; color:#64748b; font-size:14px;">
                            Showing <?= count($appointments) ?> appointment(s)
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Appointments List -->
                <div class="appointments-grid">
                    <?php if (empty($appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No appointments found</h3>
                            <p>
                                <?php if ($status_filter !== 'all' || $type_filter !== 'all' || !empty($search_query)): ?>
                                    Try adjusting your filters or search criteria.
                                <?php else: ?>
                                    You haven't made any appointment requests yet.
                                <?php endif; ?>
                            </p>
                            <a href="requestAppointments.php" class="btn-request" style="display:inline-flex;">
                                <i class="fas fa-plus"></i> Request Your First Appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($appointments as $ap): 
                            $can_cancel = $ap['status'] === 'pending' || $ap['status'] === 'approved';
                            $is_past = strtotime($ap['preferred_datetime']) < time();
                            // Get priest name - first check assigned priest (priest_fullname), then fall back to priest field
                            $priest_name = !empty($ap['priest_fullname']) ? $ap['priest_fullname'] : $ap['priest'];
                            $priest_profile_pic = $ap['priest_profile_pic'] ?? '';
                        ?>
                            <div class="appointment-card">
                                <div class="card-header">
                                    <img src="<?= !empty($ap['profile_pic']) && $ap['profile_pic'] != 'default.jpg' ? '../uploads/profile_pics/'.$ap['profile_pic'] : 'https://via.placeholder.com/52/059669/ffffff?text='.substr($ap['requester_name'],0,1) ?>" alt="User">
                                    <div style="flex:1;">
                                        <h4 style="margin:0; font-size:17px; font-weight:600; color:#1e293b;">
                                            <?= htmlspecialchars($ap['full_name'] ?? $ap['requester_name']) ?>
                                        </h4>
                                        <small style="color:#64748b;">
                                            <?= ucfirst($ap['type']) ?> • <?= date('M j, Y', strtotime($ap['preferred_datetime'])) ?>
                                        </small>
                                        <?php if (!empty($priest_name)): ?>
                                            <div class="priest-badge">
                                                <?php if ($priest_profile_pic && $priest_profile_pic != 'default.jpg'): ?>
                                                    <img src="../uploads/profile_pics/<?= $priest_profile_pic ?>" alt="Priest">
                                                <?php endif; ?>
                                                <span>Assigned Priest: <?= htmlspecialchars($priest_name) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="status-badge status-<?= htmlspecialchars($ap['status']) ?>">
                                            <?= ucwords(str_replace('_', ' ', $ap['status'])) ?>
                                        </span>
                                    </div>
                                </div>

                                <div style="padding:24px;">
                                    <div class="detail-row">
                                        <span class="detail-label">Appointment Type</span>
                                        <span class="detail-value"><?= ucwords(str_replace('_', ' ', $ap['type'])) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Chapel/Parish</span>
                                        <span class="detail-value"><?= htmlspecialchars($ap['chapel'] ?? 'Not specified') ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Preferred Date & Time</span>
                                        <span class="detail-value">
                                            <i class="far fa-calendar"></i> <?= date('F j, Y', strtotime($ap['preferred_datetime'])) ?>
                                            <i class="far fa-clock" style="margin-left:10px;"></i> <?= date('g:i A', strtotime($ap['preferred_datetime'])) ?>
                                            <?php if ($is_past): ?>
                                                <span style="color:#f59e0b; margin-left:10px;">
                                                    <i class="fas fa-exclamation-circle"></i> Past appointment
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Submitted On</span>
                                        <span class="detail-value"><?= date('M j, Y g:i A', strtotime($ap['requested_at'])) ?></span>
                                    </div>
                                    <?php if (!empty($priest_name)): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Assigned Priest</span>
                                        <span class="detail-value">
                                            <?php if ($priest_profile_pic && $priest_profile_pic != 'default.jpg'): ?>
                                                <img src="../uploads/profile_pics/<?= $priest_profile_pic ?>" alt="Priest" style="width:24px; height:24px; border-radius:50%; vertical-align:middle; margin-right:8px;">
                                            <?php endif; ?>
                                            <?= htmlspecialchars($priest_name) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($ap['purpose'])): ?>
                                    <div class="notes-section">
                                        <div class="notes-title"><i class="fas fa-bullseye"></i> Purpose / Intention</div>
                                        <div class="notes-text"><?= nl2br(htmlspecialchars($ap['purpose'])) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($ap['notes'])): ?>
                                    <div class="notes-section">
                                        <div class="notes-title"><i class="fas fa-sticky-note"></i> Additional Notes</div>
                                        <div class="notes-text"><?= nl2br(htmlspecialchars($ap['notes'])) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($ap['address'])): ?>
                                    <div class="notes-section">
                                        <div class="notes-title"><i class="fas fa-map-marker-alt"></i> Address (Blessing/Visit)</div>
                                        <div class="notes-text"><?= htmlspecialchars($ap['address']) ?></div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="card-actions">
                                        <button class="btn-view-details" onclick="viewAppointment(<?= $ap['id'] ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        <?php if ($can_cancel && !$is_past): ?>
                                            <form method="POST" action="" style="flex:1;" onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="appointment_id" value="<?= $ap['id'] ?>">
                                                <button type="submit" class="btn-cancel">
                                                    <i class="fas fa-times"></i> Cancel Appointment
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn-cancel" disabled>
                                                <i class="fas fa-times"></i> Cancel Appointment
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT SIDEBAR -->
            <div class="right-sidebar">
                <h3 style="color:#065f46; font-size:21px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-calendar-check"></i> Upcoming Appointments
                    <span style="font-size:14px; color:#64748b; font-weight:normal;">(Approved)</span>
                </h3>
                
                <?php if (!empty($upcoming_appointments)): ?>
                    <?php foreach ($upcoming_appointments as $up): 
                        $date = new DateTime($up['preferred_datetime']);
                        $is_today = $date->format('Y-m-d') === date('Y-m-d');
                        $is_tomorrow = $date->format('Y-m-d') === date('Y-m-d', strtotime('+1 day'));
                        $priest_name = $up['priest_name'] ?? $up['priest'] ?? 'Not assigned';
                    ?>
                        <div style="display:flex; justify-content:space-between; padding:14px 0; border-bottom:1px solid #f1f5f9; align-items:center;">
                            <div style="display:flex; gap:12px; align-items:center;">
                                <div style="width:40px; height:40px; border-radius:10px; background:var(--light-green); display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-church" style="color:var(--green); font-size:18px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600; font-size:14px; color:#1e293b;">
                                        <?= htmlspecialchars($up['full_name'] ?? $up['fullname']) ?>
                                    </div>
                                    <small style="color:#64748b; display:block;">
                                        <?php if ($is_today): ?>
                                            <span style="color:#059669; font-weight:600;">Today</span>
                                        <?php elseif ($is_tomorrow): ?>
                                            <span style="color:#f59e0b; font-weight:600;">Tomorrow</span>
                                        <?php else: ?>
                                            <i class="far fa-calendar"></i> <?= $date->format('M j') ?>
                                        <?php endif; ?>
                                        <i class="far fa-clock" style="margin-left:10px;"></i> <?= $date->format('g:i A') ?>
                                    </small>
                                    <?php if (!empty($priest_name) && $priest_name !== 'Not assigned'): ?>
                                        <small style="color:#059669; display:block; margin-top:2px;">
                                            <i class="fas fa-user-tie"></i> <?= htmlspecialchars($priest_name) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:13px; font-weight:600; color:#059669;">
                                    <?= ucfirst($up['type']) ?>
                                </div>
                                <span class="status-badge status-approved" style="font-size:11px; padding:4px 10px;">
                                    Approved
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:40px 20px; color:#94a3b8;">
                        <i class="fas fa-calendar-plus" style="font-size:48px; margin-bottom:16px; opacity:0.3;"></i>
                        <p>No upcoming appointments</p>
                    </div>
                <?php endif; ?>
                
                <!-- Quick Stats -->
                <div style="margin-top:30px; padding-top:20px; border-top:1px solid #f1f5f9;">
                    <h4 style="color:#065f46; font-size:16px; margin-bottom:15px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-chart-bar"></i> Quick Stats
                    </h4>
                    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px;">
                        <div style="background:#f8fafc; padding:12px; border-radius:10px; text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:var(--green);"><?= $stats['total'] ?? 0 ?></div>
                            <div style="font-size:12px; color:#64748b;">Total Requests</div>
                        </div>
                        <div style="background:#f8fafc; padding:12px; border-radius:10px; text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:#f59e0b;"><?= $stats['pending'] ?? 0 ?></div>
                            <div style="font-size:12px; color:#64748b;">Pending Review</div>
                        </div>
                        <div style="background:#f8fafc; padding:12px; border-radius:10px; text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:var(--green);"><?= $stats['approved'] ?? 0 ?></div>
                            <div style="font-size:12px; color:#64748b;">Approved</div>
                        </div>
                        <div style="background:#f8fafc; padding:12px; border-radius:10px; text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:#ec4899;"><?= count($upcoming_appointments) ?></div>
                            <div style="font-size:12px; color:#64748b;">Upcoming</div>
                        </div>
                    </div>
                </div>
                
                <!-- Help Tips -->
                <div style="margin-top:30px; padding:16px; background:#f0f9ff; border-radius:12px; border-left:4px solid var(--green);">
                    <h5 style="color:#065f46; font-size:14px; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                        <i class="fas fa-lightbulb"></i> Appointment Tips
                    </h5>
                    <ul style="color:#64748b; font-size:13px; padding-left:20px; margin:0;">
                        <li>Request appointments at least 3 days in advance</li>
                        <li>Check status regularly for updates</li>
                        <li>Cancel appointments you can't attend</li>
                        <li>Contact parish office for urgent requests</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewAppointment(id) {
            // You can implement a modal or redirect to view appointment details
            alert('Viewing appointment details for ID: ' + id + '\n\nFeature coming soon!');
            // window.location.href = 'view_appointment.php?id=' + id;
        }

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

        // Highlight today's appointments
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const appointments = document.querySelectorAll('.appointment-card');
            
            appointments.forEach(appointment => {
                const dateText = appointment.querySelector('.detail-value')?.textContent;
                if (dateText && dateText.includes(today.replace(/-/g, ' '))) {
                    appointment.style.border = '2px solid #059669';
                    appointment.style.boxShadow = '0 10px 30px rgba(5, 150, 105, 0.2)';
                }
            });
        });
    </script>
</body>
</html>