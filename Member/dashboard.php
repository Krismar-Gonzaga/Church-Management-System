<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Member';

// Fetch data
$announcements = $pdo->query("SELECT a.*, u.fullname FROM announcements a JOIN users u ON a.posted_by = u.id ORDER BY a.created_at DESC LIMIT 10")->fetchAll();
$appointments = $pdo->query("SELECT ar.*, u.fullname, ar.type, ar.status FROM appointment_requests ar JOIN users u ON ar.user_id = u.id ORDER BY ar.requested_at DESC LIMIT 8")->fetchAll();
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
            gap: 30px;
            padding: 40px;
        }
        .center-area { flex: 2; max-width: 820px; }
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
            box-shadow: 0 6px 25px rgba(0,0,0,0.06);
            margin-bottom: 30px;
        }
        .announcement {
            display: flex;
            gap: 18px;
            padding: 20px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .announcement:last-child { border-bottom: none; }
        .announcement img { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; }
        .announcement-meta { font-size: 13.5px; color: #64748b; margin-bottom: 8px; }
        .announcement-title { font-size: 20px; font-weight: 600; color: #065f46; margin-bottom: 10px; }

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
        /* HOVER EFFECT - Modern lift + shadow + border color */
        .appointment-item:hover .appointment-card {
            transform: translateY(-6px);
            box-shadow: 0 14px 32px rgba(5, 150, 105, 0.18) !important;
            border-color: #059669;
            background: linear-gradient(to bottom, #ffffff 0%, #f8fff9 100%);
        }
        
        .appointment-item:last-child { border-bottom: none; }
        .status {
            padding: 5px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 600;
        }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-rejected { background: #fce7f3; color: #be123c; }
        .status-rescheduled { background: #dbeafe; color: #1d4ed8; }
        .btn-view {
            background: #e2e8f0; color: #475569; padding: 7px 14px; border-radius: 10px;
            font-size: 12px; border: none; cursor: pointer; font-weight: 500;
        }

        @media (max-width: 1024px) {
            .content-area { flex-direction: column; padding: 20px; }
            .right-sidebar { width: 100%; order: -1; position: static; }
            .top-header { padding: 0 20px; height: 70px; }
            .header-left { gap: 15px; }
            .header-left .logo-img { height: 48px; }
            .header-left .priest-img { width: 46px; height: 46px; }
        }
    </style>
</head>
<body>

    <!-- TOP FIXED HEADER -->
    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo" class="logo-img">
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

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Left Sidebar -->
        <div class="sidebar">
            
            <div class="nav-menu">
                <div class="nav-item active"><i class="fas fa-tachometer-alt"></i> Dashboard</div>
                <div class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</div>
                <div class="nav-item"><i class="fas fa-calendar"></i> Calendar</div>
                <div class="nav-item"><i class="fas fa-clock"></i> Appointments</div>
                <div class="nav-item"><i class="fas fa-coins"></i> Financial</div>
                <div class="nav-item"><i class="fas fa-user"></i> My Profile</div>
                <div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-area">
            <!-- Center: Announcements -->
            <div class="center-area">
                <h1 class="page-title">Dashboard</h1>
                <div class="section">
                    <h2 style="margin-bottom:24px; color:#065f46; font-size:22px;">Announcements</h2>
                    <?php foreach ($announcements as $a): ?>
                    <div class="announcement">
                        <img src="https://via.placeholder.com/52/6366f1/ffffff?text=<?= substr($a['fullname'],0,1) ?>" alt="Poster">
                        <div style="flex:1;">
                            <div class="announcement-meta">
                                <?= htmlspecialchars($a['fullname']) ?> • <?= date('M j, Y • g:i A', strtotime($a['created_at'])) ?>
                            </div>
                            <div class="announcement-title"><?= htmlspecialchars($a['title']) ?></div>
                            <p style="color:#475569; line-height:1.7;">
                                <?= nl2br(htmlspecialchars($a['message'])) ?>
                            </p>
                        </div>
                        <div style="margin-left:auto; font-size:26px; color:#94a3b8;">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($announcements)): ?>
                        <p style="text-align:center; padding:70px; color:#94a3b8; font-size:16px;">No announcements yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Sidebar: Appointments -->
            <div class="right-sidebar">
                <div class="sidebar-title">
                    Appointment Requests
                    <span style="font-size:15px; color:#64748b; font-weight:normal;">(<?= count($appointments) ?>)</span>
                </div>
                <?php foreach ($appointments as $ap): ?>
                
                <div class="appointment-item">
                    <div class="appointment-card">
                        <div class="appointment-header">
                            <h4><?= htmlspecialchars($ap['fullname']) ?></h4>
                            <Style>
                            .status_status{
                                justify-content: right;
                                margin-top: 10px;
                                margin-left: 210px;
                            }
                        </Style>
                            <span class="status_status"-<?= $ap['status'] === 'rescheduled' ? 'rescheduled' : strtolower($ap['status']) ?>">
                                <?= ucfirst($ap['status']) ?>
                            </span>
                        </div>
                        <Style>
                            .appointment-date{
                                justify-content: right;
                                margin-top: -40px;
                                margin-left: 210px;
                            }
                        </Style>
                        <div class="appointment-date">
                            <?= date('M j, Y • g:i A', strtotime($ap['requested_at'])) ?>
                        </div>
                        <Style>
                            .appointment-type{
                                justify-content: left;
                                margin-top: 1px;
                                margin-bottom: 10px;
                            }
                        </Style>
                        <div class="appointment-type">
                            <strong>Type:</strong> <?= ucfirst($ap['type']) ?>
                        </div>
                        <button class="btn-view">
                            <i class="fas fa-eye"></i> View Full Text
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <div style="text-align:center; margin-top:28px; color:#059669; font-weight:600; cursor:pointer; font-size:15px;">
                    <i class="fas fa-chevron-down"></i> Discover All
                </div>
            </div>
        </div>
    </div>
</body>
</html>