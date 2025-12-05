<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Ensure only priests can access
if (($_SESSION['role'] ?? '') !== 'priest') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0; // Get priest's user_id from session
$user_fullname = $_SESSION['user'] ?? 'Priest';

// Get current date parameters
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_day = isset($_GET['day']) ? (int)$_GET['day'] : date('j');

// Calculate previous and next months
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year = $current_year - 1;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year = $current_year + 1;
}

// Fetch the priest's assigned appointments for the current month
$month_start = date('Y-m-01', mktime(0, 0, 0, $current_month, 1, $current_year));
$month_end = date('Y-m-t', mktime(0, 0, 0, $current_month, 1, $current_year));

// MODIFIED QUERY: Only get appointments assigned to this priest
$stmt = $pdo->prepare("
    SELECT ar.*, u.fullname AS requester_name, u.phone, u.email,
           DATE(ar.preferred_datetime) as appointment_date,
           TIME(ar.preferred_datetime) as appointment_time
    FROM appointment_requests ar 
    JOIN users u ON ar.user_id = u.id 
    WHERE ar.priest_id = ?  -- Only show appointments assigned to this priest
    AND ar.status = 'approved'  -- Only approved appointments
    AND DATE(ar.preferred_datetime) BETWEEN ? AND ?
    ORDER BY ar.preferred_datetime ASC
");
$stmt->execute([$user_id, $month_start, $month_end]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group appointments by date
$appointments_by_date = [];
foreach ($appointments as $appointment) {
    $date = $appointment['appointment_date'];
    if (!isset($appointments_by_date[$date])) {
        $appointments_by_date[$date] = [];
    }
    $appointments_by_date[$date][] = $appointment;
}

// MODIFIED: Get appointment statistics for the priest only (assigned appointments)
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_approved,
        COUNT(DISTINCT DATE(preferred_datetime)) as days_with_appointments,
        MIN(DATE(preferred_datetime)) as first_appointment,
        MAX(DATE(preferred_datetime)) as last_appointment
    FROM appointment_requests 
    WHERE priest_id = ?  -- Only count priest's assigned appointments
    AND status = 'approved'  -- Only approved appointments
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// MODIFIED: Fetch today's appointments assigned to this priest only
$today = date('Y-m-d');
$today_stmt = $pdo->prepare("
    SELECT ar.*, u.fullname 
    FROM appointment_requests ar 
    JOIN users u ON ar.user_id = u.id 
    WHERE ar.priest_id = ?  -- Only today's appointments for the priest
    AND ar.status = 'approved'  -- Only approved appointments
    AND DATE(ar.preferred_datetime) = ?
    ORDER BY ar.preferred_datetime ASC
    LIMIT 5
");
$today_stmt->execute([$user_id, $today]);
$today_appointments = $today_stmt->fetchAll();

// MODIFIED: Get appointment type distribution for current month (priest only)
$type_stmt = $pdo->prepare("
    SELECT type, COUNT(*) as count
    FROM appointment_requests 
    WHERE priest_id = ?  -- Only priest's assigned appointments
    AND status = 'approved'  -- Only approved appointments
    AND DATE(preferred_datetime) BETWEEN ? AND ?
    GROUP BY type
    ORDER BY count DESC
");
$type_stmt->execute([$user_id, $month_start, $month_end]);
$type_distribution = $type_stmt->fetchAll();

// Determine current view
$view = isset($_GET['view']) ? $_GET['view'] : 'month';

// Generate calendar days for month view
if ($view === 'month') {
    $first_day_of_month = date('w', strtotime($month_start));
    $days_in_month = date('t', strtotime($month_start));
    
    $calendar_days = [];
    $current_day_counter = 1;
    
    // Fill in blank days for previous month
    for ($i = 0; $i < $first_day_of_month; $i++) {
        $prev_day = date('j', strtotime("-$i days", strtotime($month_start)));
        $calendar_days[] = [
            'day' => $prev_day,
            'current_month' => false,
            'date' => date('Y-m-d', strtotime("-$i days", strtotime($month_start)))
        ];
    }
    
    // Current month days
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
        $calendar_days[] = [
            'day' => $day,
            'current_month' => true,
            'date' => $date,
            'appointments' => $appointments_by_date[$date] ?? [],
            'is_today' => ($current_year == date('Y') && $current_month == date('n') && $day == date('j'))
        ];
    }
    
    // Fill in remaining days for next month
    $total_cells = 42; // 6 weeks * 7 days
    $remaining_cells = $total_cells - count($calendar_days);
    for ($i = 1; $i <= $remaining_cells; $i++) {
        $next_date = date('Y-m-d', strtotime("+$i days", strtotime($month_end)));
        $calendar_days[] = [
            'day' => date('j', strtotime($next_date)),
            'current_month' => false,
            'date' => $next_date
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | My Schedule Calendar</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }

        /* TOP HEADER */
        .top-header {
            position: fixed;
            top: 0; left: 0; right: 0;
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
        .header-left { display: flex; align-items: center; gap: 22px; }
        .header-left img { height: 58px; }
        .header-left .priest-img { width: 54px; height: 54px; border-radius: 50%; border: 3px solid #059669; object-fit: cover; }
        
        .search-box { position: relative; }
        .search-box input {
            width: 100%; padding: 14px 48px; border: 2px solid #e2e8f0;
            border-radius: 16px; background: #f8fafc; font-size: 15px; transition: all 0.3s;
        }
        .search-box input:focus { border-color: #059669; background: white; box-shadow: 0 8px 25px rgba(5,150,105,0.15); }
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #059669; }
        
        .header-right { display: flex; align-items: center; gap: 20px; }
        .notification-bell { position: relative; font-size: 23px; color: #64748b; cursor: pointer; }
        .notification-bell .badge { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 10px; width: 19px; height: 19px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .user-profile { display: flex; align-items: center; gap: 12px; background: #f0fdf4; padding: 10px 18px; border-radius: 14px; border: 1px solid #d1fae5; cursor: pointer; }
        .user-profile:hover { background: #d1fae5; }
        .user-profile img { width: 44px; height: 44px; border-radius: 50%; border: 2px solid #059669; }
        .user-profile span { font-weight: 600; color: #065f46; }

        /* Main Layout */
        .main-layout { display: flex; margin-top: 80px; min-height: calc(100vh - 80px); }
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
        .sidebar .logo { padding: 30px 20px; text-align: center; border-bottom: 1px solid #e2e8f0; }
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
        .nav-item a { text-decoration: none; color: inherit; width: 100%; display: flex; align-items: center; gap: 14px; }

        /* Calendar Content */
        .content-wrapper {
            margin-left: 260px; flex: 1; padding: 40px;
            display: flex; gap: 40px;
        }
        .main-calendar { flex: 2; }
        .right-sidebar { width: 380px; flex-shrink: 0; }
        
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 20px; color: #1e293b; }

        /* Stats Bar */
        .stats-bar {
            display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;
        }
        .stat-card {
            background: white; padding: 20px; border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06); min-width: 180px; flex: 1;
        }
        .stat-value {
            font-size: 28px; font-weight: 700; margin-bottom: 8px; color: #059669;
        }
        .stat-label { font-size: 14px; color: #64748b; font-weight: 500; }

        /* Calendar Container */
        .calendar-container {
            background: white; border-radius: 20px; overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 30px;
            margin-bottom: 30px;
        }
        .calendar-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;
        }
        .calendar-nav {
            display: flex; align-items: center; gap: 20px;
        }
        .calendar-nav button {
            background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer;
            width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        }
        .calendar-nav button:hover {
            background: #f0fdf4; color: #059669;
        }
        .calendar-title {
            font-size: 24px; font-weight: 700; color: #1e293b;
            min-width: 250px; text-align: center;
        }
        .view-tabs {
            display: flex; gap: 10px; font-weight: 600;
        }
        .view-tabs a {
            padding: 8px 16px; border-radius: 10px; cursor: pointer; text-decoration: none;
            color: #64748b; transition: all 0.3s;
        }
        .view-tabs a:hover {
            background: #f0fdf4; color: #059669;
        }
        .view-tabs .active {
            background: #059669 !important; color: white !important;
        }
        .today-btn {
            background: #059669; color: white; border: none; padding: 10px 20px;
            border-radius: 10px; font-weight: 600; cursor: pointer; margin-left: 20px;
        }
        .today-btn:hover { background: #047857; }

        /* Month Calendar */
        .month-calendar {
            width: 100%; border-collapse: collapse;
        }
        .month-calendar th {
            text-align: center; padding: 15px 0; font-weight: 600; color: #64748b;
            border-bottom: 2px solid #e2e8f0; font-size: 14px;
        }
        .month-calendar td {
            vertical-align: top; padding: 12px 8px; width: 14.28%; height: 120px;
            border: 1px solid #e2e8f0; cursor: pointer; transition: all 0.3s;
            position: relative;
        }
        .month-calendar td:hover {
            background: #f8fff9;
        }
        .month-calendar td.other-month {
            background: #f8fafc; color: #cbd5e1;
        }
        .month-calendar td.today {
            background: #f0fdf4; border: 2px solid #059669;
        }
        .day-number {
            font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 8px;
        }
        .other-month .day-number {
            color: #cbd5e1; font-weight: 400;
        }
        .today .day-number {
            color: #059669;
        }
        .event-count {
            position: absolute; top: 8px; right: 8px; background: #059669;
            color: white; font-size: 12px; width: 22px; height: 22px;
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-weight: 600;
        }
        .event-list {
            margin-top: 5px; max-height: 70px; overflow-y: auto;
        }
        .event-item {
            background: #d1fae5; color: #065f46; padding: 4px 6px;
            border-radius: 6px; font-size: 11px; margin: 2px 0;
            border-left: 3px solid #059669; overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap;
        }
        .event-item.baptism { background: #d1fae5; color: #065f46; border-left-color: #059669; }
        .event-item.wedding { background: #fce7f3; color: #be123c; border-left-color: #ec4899; }
        .event-item.mass_intention { background: #fef3c7; color: #92400e; border-left-color: #f59e0b; }
        .event-item.confession { background: #dbeafe; color: #1d4ed8; border-left-color: #3b82f6; }
        .event-item.blessing { background: #f3e8ff; color: #6b21a8; border-left-color: #a855f7; }

        /* Right Sidebar */
        .sidebar-section {
            background: white; border-radius: 20px; padding: 24px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08); margin-bottom: 24px;
        }
        .section-title {
            font-size: 18px; font-weight: 700; color: #065f46;
            margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
        }
        .appointment-card {
            background: #f8fafc; border-radius: 12px; padding: 16px;
            margin-bottom: 12px; border-left: 4px solid #059669;
            cursor: pointer;
            transition: all 0.3s;
        }
        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(5,150,105,0.1);
            border-left-color: #047857;
        }
        .appointment-time {
            font-size: 13px; color: #64748b; margin-bottom: 5px;
            display: flex; align-items: center; gap: 6px;
        }
        .appointment-name {
            font-weight: 600; color: #1e293b; margin-bottom: 5px;
        }
        .appointment-type {
            font-size: 12px; color: #059669; font-weight: 500;
            background: #d1fae5; padding: 3px 8px; border-radius: 10px;
            display: inline-block;
        }
        .empty-state {
            text-align: center; padding: 40px 20px; color: #94a3b8;
        }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.3; }

        /* Type Distribution */
        .type-bar {
            margin-bottom: 12px;
        }
        .type-label {
            display: flex; justify-content: space-between; margin-bottom: 4px;
            font-size: 14px; color: #475569;
        }
        .type-bar-container {
            height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;
        }
        .type-bar-fill {
            height: 100%; border-radius: 4px;
        }
        .baptism-fill { background: #059669; }
        .wedding-fill { background: #ec4899; }
        .mass_intention-fill { background: #f59e0b; }
        .confession-fill { background: #3b82f6; }
        .blessing-fill { background: #a855f7; }

        /* Modal */
        .modal {
            display: none; position: fixed; z-index: 2000;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
        }
        .modal-content {
            background-color: white; margin: 5% auto; padding: 0;
            border-radius: 20px; width: 90%; max-width: 600px;
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
        .modal-body { padding: 30px; max-height: 400px; overflow-y: auto; }
        .modal-footer {
            padding: 20px 30px; border-top: 1px solid #e2e8f0;
            display: flex; justify-content: flex-end; gap: 12px;
        }
        .appointment-detail {
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0;
        }
        .detail-label { font-weight: 600; color: #64748b; font-size: 14px; margin-bottom: 5px; }
        .detail-value { color: #1e293b; font-size: 15px; }

        @media (max-width: 1200px) {
            .content-wrapper { flex-direction: column; padding: 20px; }
            .right-sidebar { width: 100%; }
            .stats-bar { flex-direction: column; }
            .month-calendar td { height: 100px; }
        }
    </style>
</head>
<body>

    <!-- TOP HEADER -->
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

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="nav-menu">
                <a href="dashboard.php"><div class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</div></a>
                <a href="announcements.php"><div class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</div></a>
                <a href="calendar.php"><div class="nav-item active"><i class="fas fa-calendar"></i> My Schedule</div></a>
                <a href="appointments.php"><div class="nav-item"><i class="fas fa-clock"></i> My Appointments</div></a>
                <a href="financial.php"><div class="nav-item"><i class="fas fa-coins"></i> Financial</div></a>
                <a href="profile.php"><div class="nav-item"><i class="fas fa-user"></i> My Profile</div></a>
                <a href="support.php"><div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div></a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-wrapper">
            <div class="main-calendar">
                <h1 class="page-title">My Ministry Schedule Calendar</h1>

                <!-- Statistics -->
                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['total_approved'] ?? 0 ?></div>
                        <div class="stat-label">My Assigned Appointments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= count($appointments) ?></div>
                        <div class="stat-label">My Schedule This Month</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= count($appointments_by_date) ?></div>
                        <div class="stat-label">My Ministry Days</div>
                    </div>
                </div>

                <!-- Calendar Container -->
                <div class="calendar-container">
                    <div class="calendar-header">
                        <div class="calendar-nav">
                            <a href="?view=<?= $view ?>&year=<?= $prev_year ?>&month=<?= $prev_month ?>">
                                <button><i class="fas fa-chevron-left"></i></button>
                            </a>
                            <div class="calendar-title">
                                <?= date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)) ?>
                            </div>
                            <a href="?view=<?= $view ?>&year=<?= $next_year ?>&month=<?= $next_month ?>">
                                <button><i class="fas fa-chevron-right"></i></button>
                            </a>
                            <a href="calendar.php">
                                <button class="today-btn">Today</button>
                            </a>
                        </div>
                        <div class="view-tabs">
                            <a href="?view=month&year=<?= $current_year ?>&month=<?= $current_month ?>" class="<?= $view === 'month' ? 'active' : '' ?>">Month</a>
                            <a href="#" onclick="alert('Week view coming soon!')">Week</a>
                            <a href="#" onclick="alert('Day view coming soon!')">Day</a>
                        </div>
                    </div>

                    <?php if ($view === 'month'): ?>
                    <table class="month-calendar">
                        <thead>
                            <tr>
                                <th>Sun</th>
                                <th>Mon</th>
                                <th>Tue</th>
                                <th>Wed</th>
                                <th>Thu</th>
                                <th>Fri</th>
                                <th>Sat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($week = 0; $week < 6; $week++): ?>
                                <tr>
                                    <?php for ($day = 0; $day < 7; $day++): 
                                        $index = $week * 7 + $day;
                                        if ($index >= count($calendar_days)) break;
                                        $day_data = $calendar_days[$index];
                                        $is_today = $day_data['is_today'] ?? false;
                                        $appointment_count = count($day_data['appointments'] ?? []);
                                    ?>
                                    <td class="<?= !$day_data['current_month'] ? 'other-month' : '' ?> <?= $is_today ? 'today' : '' ?>" 
                                        onclick="viewDayAppointments('<?= $day_data['date'] ?>', <?= $appointment_count ?>)">
                                        <div class="day-number"><?= $day_data['day'] ?></div>
                                        <?php if ($appointment_count > 0): ?>
                                            <div class="event-count"><?= $appointment_count ?></div>
                                            <div class="event-list">
                                                <?php foreach (array_slice($day_data['appointments'] ?? [], 0, 3) as $appt): ?>
                                                    <div class="event-item <?= $appt['type'] ?>" 
                                                         title="<?= htmlspecialchars($appt['requester_name']) ?> - <?= date('g:i A', strtotime($appt['appointment_time'])) ?>">
                                                        <?= date('g:i', strtotime($appt['appointment_time'])) ?> - <?= htmlspecialchars($appt['requester_name']) ?>
                                                    </div>
                                                <?php endforeach; ?>
                                                <?php if ($appointment_count > 3): ?>
                                                    <div class="event-item" style="background:#f3f4f6; color:#6b7280; border-left-color:#9ca3af;">
                                                        +<?= $appointment_count - 3 ?> more
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="right-sidebar">
                <!-- Today's Appointments -->
                <div class="sidebar-section">
                    <h3 class="section-title"><i class="fas fa-calendar-day"></i> My Today's Ministry</h3>
                    <?php if (!empty($today_appointments)): ?>
                        <?php foreach ($today_appointments as $appt): ?>
                            <div class="appointment-card" onclick="viewAppointmentDetails(<?= $appt['id'] ?>)">
                                <div class="appointment-time">
                                    <i class="far fa-clock"></i> <?= date('g:i A', strtotime($appt['preferred_datetime'])) ?>
                                </div>
                                <div class="appointment-name"><?= htmlspecialchars($appt['fullname']) ?></div>
                                <div class="appointment-type"><?= ucwords(str_replace('_', ' ', $appt['type'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>No ministry appointments scheduled for today</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Appointment Type Distribution -->
                <div class="sidebar-section">
                    <h3 class="section-title"><i class="fas fa-chart-pie"></i> My Ministry Distribution</h3>
                    <?php if (!empty($type_distribution)): ?>
                        <?php foreach ($type_distribution as $type): ?>
                            <div class="type-bar">
                                <div class="type-label">
                                    <span><?= ucwords(str_replace('_', ' ', $type['type'])) ?></span>
                                    <span><?= $type['count'] ?></span>
                                </div>
                                <div class="type-bar-container">
                                    <div class="type-bar-fill <?= $type['type'] ?>-fill" 
                                         style="width: <?= ($type['count'] / max(array_column($type_distribution, 'count'))) * 100 ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding:20px 0;">
                            <i class="fas fa-chart-bar"></i>
                            <p>No ministry appointments this month</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Stats -->
                <div class="sidebar-section">
                    <h3 class="section-title"><i class="fas fa-chart-line"></i> My Ministry Stats</h3>
                    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:15px;">
                        <div style="text-align:center;">
                            <div style="font-size:24px; font-weight:700; color:#059669;"><?= $stats['total_approved'] ?? 0 ?></div>
                            <div style="font-size:12px; color:#64748b;">My Ministry Appointments</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:24px; font-weight:700; color:#f59e0b;"><?= $stats['days_with_appointments'] ?? 0 ?></div>
                            <div style="font-size:12px; color:#64748b;">My Ministry Days</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:24px; font-weight:700; color:#6366f1;"><?= count($appointments_by_date) ?></div>
                            <div style="font-size:12px; color:#64748b;">This Month</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:24px; font-weight:700; color:#ec4899;"><?= count($today_appointments) ?></div>
                            <div style="font-size:12px; color:#64748b;">Today's Ministry</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Day Appointments Modal -->
    <div id="dayModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalDayTitle">My Ministry Schedule</h2>
                <button class="close-btn" onclick="closeDayModal()">&times;</button>
            </div>
            <div class="modal-body" id="dayAppointmentsList">
                <!-- Appointments will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeDayModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>My Ministry Appointment Details</h2>
                <button class="close-btn" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body" id="appointmentDetails">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeDetailsModal()">Close</button>
                <button class="btn-submit" onclick="window.location.href='appointments.php'">
                    Manage My Appointments
                </button>
            </div>
        </div>
    </div>

    <script>
        // Day appointments data
        const dayAppointments = <?= json_encode($appointments_by_date) ?>;
        const allAppointments = <?= json_encode($appointments) ?>;

        function viewDayAppointments(date, count) {
            if (count === 0) {
                alert('No ministry appointments scheduled for ' + date);
                return;
            }

            const modalTitle = document.getElementById('modalDayTitle');
            const appointmentsList = document.getElementById('dayAppointmentsList');
            
            const formattedDate = new Date(date).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            modalTitle.textContent = 'My Ministry Schedule for ' + formattedDate;
            
            let html = '';
            if (dayAppointments[date]) {
                dayAppointments[date].forEach(appt => {
                    const time = new Date(appt.preferred_datetime).toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    html += `
                        <div class="appointment-detail" onclick="viewAppointmentDetails(${appt.id})" style="cursor:pointer;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <h3 style="color:#059669; font-size:16px;">${time}</h3>
                                <span style="background:#d1fae5; color:#065f46; padding:3px 8px; border-radius:10px; font-size:12px;">
                                    ${appt.type.replace(/_/g, ' ').toUpperCase()}
                                </span>
                            </div>
                            <div class="detail-label">Member</div>
                            <div class="detail-value">${escapeHtml(appt.requester_name)}</div>
                            <div class="detail-label">Appointment Type</div>
                            <div class="detail-value">${appt.type.replace(/_/g, ' ').toUpperCase()}</div>
                            <div class="detail-label">Purpose</div>
                            <div class="detail-value">${appt.purpose ? escapeHtml(appt.purpose) : 'No purpose specified'}</div>
                        </div>
                    `;
                });
            } else {
                html = '<div class="empty-state"><p>No ministry appointments for this date</p></div>';
            }
            
            appointmentsList.innerHTML = html;
            document.getElementById('dayModal').style.display = 'block';
        }

        function viewAppointmentDetails(id) {
            const appointment = allAppointments.find(a => a.id == id);
            if (!appointment) return;

            const detailsDiv = document.getElementById('appointmentDetails');
            const time = new Date(appointment.preferred_datetime).toLocaleString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            detailsDiv.innerHTML = `
                <div class="appointment-detail">
                    <div class="detail-label">Date & Time</div>
                    <div class="detail-value">${time}</div>
                </div>
                <div class="appointment-detail">
                    <div class="detail-label">Member Name</div>
                    <div class="detail-value">${escapeHtml(appointment.requester_name)}</div>
                </div>
                <div class="appointment-detail">
                    <div class="detail-label">Appointment Type</div>
                    <div class="detail-value">${appointment.type.replace(/_/g, ' ').toUpperCase()}</div>
                </div>
                <div class="appointment-detail">
                    <div class="detail-label">Status</div>
                    <div class="detail-value"><span style="color:#059669; font-weight:600;">✓ ASSIGNED TO YOU</span></div>
                </div>
                <div class="appointment-detail">
                    <div class="detail-label">Chapel/Parish</div>
                    <div class="detail-value">${appointment.chapel ? escapeHtml(appointment.chapel.replace(/_/g, ' ')) : 'Not specified'}</div>
                </div>
                ${appointment.email ? `
                <div class="appointment-detail">
                    <div class="detail-label">Member Email</div>
                    <div class="detail-value">${escapeHtml(appointment.email)}</div>
                </div>
                ` : ''}
                ${appointment.phone ? `
                <div class="appointment-detail">
                    <div class="detail-label">Member Phone</div>
                    <div class="detail-value">${escapeHtml(appointment.phone)}</div>
                </div>
                ` : ''}
                ${appointment.purpose ? `
                <div class="appointment-detail">
                    <div class="detail-label">Purpose / Intention</div>
                    <div class="detail-value" style="white-space:pre-wrap;">${escapeHtml(appointment.purpose)}</div>
                </div>
                ` : ''}
                ${appointment.notes ? `
                <div class="appointment-detail">
                    <div class="detail-label">Additional Notes</div>
                    <div class="detail-value" style="white-space:pre-wrap;">${escapeHtml(appointment.notes)}</div>
                </div>
                ` : ''}
                ${appointment.address ? `
                <div class="appointment-detail">
                    <div class="detail-label">Address (Blessing/Visit)</div>
                    <div class="detail-value">${escapeHtml(appointment.address)}</div>
                </div>
                ` : ''}
                <div class="appointment-detail">
                    <div class="detail-label">Submitted On</div>
                    <div class="detail-value">${new Date(appointment.requested_at).toLocaleString()}</div>
                </div>
            `;

            closeDayModal(); // Close day modal if open
            document.getElementById('detailsModal').style.display = 'block';
        }

        function closeDayModal() {
            document.getElementById('dayModal').style.display = 'none';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        function searchAppointments() {
            const searchTerm = document.getElementById('calendarSearch').value.toLowerCase();
            const appointments = document.querySelectorAll('.event-item');
            
            appointments.forEach(appointment => {
                const text = appointment.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    appointment.style.display = 'block';
                    appointment.parentElement.parentElement.style.backgroundColor = '#f0fdf4';
                } else {
                    appointment.style.display = 'none';
                    appointment.parentElement.parentElement.style.backgroundColor = '';
                }
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const dayModal = document.getElementById('dayModal');
            const detailsModal = document.getElementById('detailsModal');
            
            if (event.target === dayModal) closeDayModal();
            if (event.target === detailsModal) closeDetailsModal();
        }

        // Close modals on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDayModal();
                closeDetailsModal();
            }
        });

        // Highlight current date on load
        document.addEventListener('DOMContentLoaded', function() {
            const today = '<?= date('Y-m-d') ?>';
            const todayCell = document.querySelector(`td[onclick*="${today}"]`);
            if (todayCell) {
                todayCell.style.animation = 'pulse 2s infinite';
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes pulse {
                        0% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.4); }
                        70% { box-shadow: 0 0 0 10px rgba(5, 150, 105, 0); }
                        100% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0); }
                    }
                `;
                document.head.appendChild(style);
            }
            
            // Show appointment count tooltip
            const eventCounts = document.querySelectorAll('.event-count');
            eventCounts.forEach(count => {
                count.title = 'My ministry appointments on this day';
            });
        });
    </script>
</body>
</html>