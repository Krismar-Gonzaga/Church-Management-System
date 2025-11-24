<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Member';

// Sample events (replace with real DB query later)
$events = [
    ['date' => '2025-11-24', 'time' => '08:00', 'title' => 'Monday Wake-Up Hour', 'color' => '#dbeafe'],
    ['date' => '2025-11-24', 'time' => '09:00', 'title' => 'All-Team Kickoff', 'color' => '#dbeafe'],
    ['date' => '2025-11-24', 'time' => '10:00', 'title' => 'Financial Update', 'color' => '#dbeafe'],
    ['date' => '2025-11-24', 'time' => '11:00', 'title' => 'New Employee Welcome Lunch', 'color' => '#fef3c7'],
    ['date' => '2025-11-24', 'time' => '14:00', 'title' => 'Design Review', 'color' => '#dbeafe'],
    ['date' => '2025-11-25', 'time' => '09:00', 'title' => 'Design Review: Acme Marketing', 'color' => '#dbeafe'],
    ['date' => '2025-11-25', 'time' => '12:00', 'title' => 'Design System Kickoff Lunch', 'color' => '#fef3c7'],
    ['date' => '2025-11-25', 'time' => '14:00', 'title' => 'Concept Design Review II', 'color' => '#dbeafe'],
    ['date' => '2025-11-25', 'time' => '16:00', 'title' => 'Design Team Happy Hour', 'color' => '#fecaca'],
    ['date' => '2025-11-26', 'time' => '09:00', 'title' => 'Webinar: Figma Tips', 'color' => '#dbeafe'],
    ['date' => '2025-11-26', 'time' => '11:00', 'title' => 'Onboarding Presentation', 'color' => '#dbeafe'],
    ['date' => '2025-11-26', 'time' => '13:00', 'title' => 'MVP Prioritization Workshop', 'color' => '#dbeafe'],
    ['date' => '2025-11-27', 'time' => '09:00', 'title' => 'Coffee Chat', 'color' => '#dbeafe'],
    ['date' => '2025-11-27', 'time' => '10:00', 'title' => 'Health Benefits Walkthrough', 'color' => '#e0e7ff'],
    ['date' => '2025-11-27', 'time' => '12:00', 'title' => 'Marketing Meet-and-Greet', 'color' => '#dbeafe'],
    ['date' => '2025-11-27', 'time' => '14:00', 'title' => 'Design Review', 'color' => '#dbeafe'],
    ['date' => '2025-11-28', 'time' => '09:00', 'title' => 'Coffee Chat', 'color' => '#dbeafe'],
    ['date' => '2025-11-28', 'time' => '14:00', 'title' => '1:1 with Heather', 'color' => '#fed7aa'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Calendar</title>
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
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #059669; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .notification-bell .badge { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 10px; width: 19px; height: 19px; border-radius: 50%; }
        .user-profile { display: flex; align-items: center; gap: 12px; background: #f0fdf4; padding: 10px 18px; border-radius: 14px; border: 1px solid #d1fae5; }
        .user-profile:hover { background: #d1fae5; }
        .user-profile img { width: 44px; height: 44px; border-radius: 50%; border: 2px solid #059669; }

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
        .content-area {
            margin-left: 260px; flex: 1; padding: 40px; background: #f1f5f9;
        }
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 30px; color: #1e293b; }

        .calendar-container {
            background: white; border-radius: 20px; overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 30px;
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
        }
        .view-tabs {
            display: flex; gap: 20px; font-weight: 600;
        }
        .view-tabs span {
            padding: 8px 16px; border-radius: 10px; cursor: pointer;
        }
        .view-tabs .active {
            background: #dc2626; color: white;
        }

        /* Week View Table */
        .week-calendar {
            width: 100%; border-collapse: collapse;
        }
        .week-calendar th {
            text-align: left; padding: 12px 10px; font-weight: 600; color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
        .week-calendar td {
            vertical-align: top; padding: 8px; width: 14.28%; height: 80px;
            border: 1px solid #e2e8f0;
        }
        .time-label {
            font-size: 13px; color: #64748b; text-align: right; padding-right: 12px;
        }
        .day-header {
            text-align: center; font-weight: 700; padding: 15px 0;
            background: #f8fafc; color: #1e293b;
        }
        .event {
            background: #dbeafe; color: #1e40af; padding: 6px 8px;
            border-radius: 8px; font-size: 12px; margin: 4px 0;
            cursor: pointer; transition: 0.3s; border-left: 4px solid #3b82f6;
        }
        .event:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .event.yellow { background: #fef3c7; color: #92400e; border-left-color: #f59e0b; }
        .event.red { background: #fecaca; color: #991b1b; border-left-color: #ef4444; }
        .event.orange { background: #fed7aa; color: #c2410c; border-left-color: #fb923c; }

        @media (max-width: 1024px) {
            .content-area { padding: 20px; }
            .week-calendar { font-size: 12px; }
            .event { font-size: 11px; padding: 4px; }
        }
    </style>
</head>
<body>

    <!-- TOP HEADER -->
    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo">
            <h3 class="parish-name">SJHS</h3 >
            <style>
                .parish-name {
                    font-size: 22px;
                    color: #065f46;
                    font-weight: 700;
                }
            </style>
        </div>
        <!-- SEARCH BAR (CENTERED) -->
         <style>
            /* SEARCH BAR STYLES */
            .header-search {
                flex: 1;
                margin-right: 20px;
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
         </style>
        <div class="header-search">
            <div class="search-box" style="position:relative;">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Search events, masses, appointments...">
            </div>
        </div>
        <div class="header-right">
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="badge">3</span>
            </div>
            <div class="user-profile">
                <span><?= htmlspecialchars($user_fullname) ?></span>
                <img src="https://via.placeholder.com/44/059669/ffffff?text=<?= substr($user_fullname,0,1) ?>" alt="User">
                <i class="fas fa-caret-down"></i>
            </div>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            
            <div class="nav-menu">
                <a href="dashboard.php"><div class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</div></a>
                <a href="announcements.php"><div class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</div></a>
                <a href="calendar.php"><div class="nav-item active"><i class="fas fa-calendar"></i> Calendar</div></a>
                <a href="appointments.php"><div class="nav-item"><i class="fas fa-clock"></i> Appointments</div></a>
                <a href="financial.php"><div class="nav-item"><i class="fas fa-coins"></i> Financial</div></a>
                <a href="profile.php"><div class="nav-item"><i class="fas fa-user"></i> My Profile</div></a>
                <a href="support.php"><div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div></a>
            </div>
        </div>

        <!-- Calendar Content -->
        <div class="content-area">
            <h1 class="page-title">Calendar</h1>

            <div class="calendar-container">
                <div class="calendar-header">
                    <div class="calendar-nav">
                        <button><i class="fas fa-chevron-left"></i></button>
                        <h2>November 23 – 29, 2025</h2>
                        <button><i class="fas fa-chevron-right"></i></button>
                        <button style="margin-left:20px; color:#059669; font-weight:600;">Today</button>
                    </div>
                    <div class="view-tabs">
                        <span>Day</span>
                        <span class="active">Week</span>
                        <span>Month</span>
                        <span>Year</span>
                    </div>
                </div>

                <table class="week-calendar">
                    <thead>
                        <tr>
                            <th></th>
                            <th class="day-header">Sun<br>23</th>
                            <th class="day-header">Mon<br>24</th>
                            <th class="day-header">Tue<br>25</th>
                            <th class="day-header">Wed<br>26</th>
                            <th class="day-header">Thu<br>27</th>
                            <th class="day-header">Fri<br>28</th>
                            <th class="day-header">Sat<br>29</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $times = ['7:00 AM','8:00 AM','9:00 AM','10:00 AM','11:00 AM','12:00 PM','1:00 PM','2:00 PM','3:00 PM','4:00 PM','5:00 PM'];
                        foreach ($times as $time):
                            $hour = str_replace([' AM',' PM'], '', $time);
                            $hour = $hour . (strpos($time, 'PM') !== false && $hour != '12' ? ' PM' : ' AM');
                        ?>
                        <tr>
                            <td class="time-label"><?= $time ?></td>
                            <?php for($d=23; $d<=29; $d++): ?>
                                <td>
                                    <?php
                                    foreach($events as $e):
                                        if(date('Y-m-d', strtotime("2025-11-$d")) == $e['date'] && $e['time'] == str_replace([' AM',' PM'], [':00 AM',':00 PM'], $time)):
                                    ?>
                                    <div class="event <?= isset($e['color']) ? '' : 'blue' ?>"
                                         style="background:<?= $e['color'] ?? '#dbeafe' ?>; border-left-color:<?= $e['color'] == '#fef3c7' ? '#f59e0b' : ($e['color'] == '#fecaca' ? '#ef4444' : ($e['color'] == '#fed7aa' ? '#fb923c' : '#3b82f6')) ?>">
                                        <?= htmlspecialchars($e['title']) ?>
                                    </div>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>