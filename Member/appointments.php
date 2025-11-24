<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Member';
$user_id = $_SESSION['user_id'] ?? 0;

// Fetch appointments (same as before)
if ($_SESSION['role'] === 'priest' || $_SESSION['role'] === 'admin') {
    $stmt = $pdo->query("
        SELECT ar.*, u.fullname as requester_name, u.profile_pic 
        FROM appointment_requests ar 
        JOIN users u ON ar.user_id = u.id 
        ORDER BY ar.requested_at DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT ar.*, u.fullname as requester_name, u.profile_pic 
        FROM appointment_requests ar 
        JOIN users u ON ar.user_id = u.id 
        WHERE ar.user_id = ? 
        ORDER BY ar.requested_at DESC
    ");
    $stmt->execute([$user_id]);
}
$appointments = $stmt->fetchAll();
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
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }

        /* TOP HEADER & LAYOUT (unchanged) */
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
        .header-left img { height: 58px; }
        .header-left .priest-img { width: 54px; height: 54px; border-radius: 50%; border: 3px solid #059669; object-fit: cover; }
        
        .search-box { position: relative; }
        .search-box input { width: 100%; padding: 14px 48px; border: 2px solid #e2e8f0; border-radius: 16px; background: #f8fafc; font-size: 15px; }
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #059669; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .notification-bell .badge { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 10px; width: 19px; height: 19px; border-radius: 50%; }
        .user-profile { display: flex; align-items: center; gap: 12px; background: #f0fdf4; padding: 10px 18px; border-radius: 14px; border: 1px solid #d1fae5; }
        .user-profile:hover { background: #d1fae5; }
        .user-profile img { width: 44px; height: 44px; border-radius: 50%; border: 2px solid #059669; }
        .main-layout { display: flex; margin-top: 80px; min-height: calc(100vh - 80px); }
        .sidebar { width: 260px; background: white; border-right: 1px solid #e2e8f0; position: fixed; top: 80px; bottom: 0; overflow-y: auto; z-index: 999; }
        .sidebar .logo { padding: 30px 20px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .nav-item { padding: 16px 24px; display: flex; align-items: center; gap: 14px; color: #64748b; cursor: pointer; transition: 0.3s; }
        .nav-item:hover, .nav-item.active { background: #f0fdf4; color: #059669; border-left: 5px solid #059669; }
        .nav-item a { text-decoration: none; color: inherit; width: 100%; display: flex; align-items: center; gap: 14px; }
        .content-wrapper { margin-left: 260px; display: flex; gap: 40px; padding: 40px; flex: 1; }
        .center-content { flex: 2; max-width: 900px; }
        .right-sidebar { width: 420px; background: white; border-radius: 20px; padding: 28px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); position: sticky; top: 110px; height: fit-content; }
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 20px; color: #1e293b; }

        /* NEW FILTER BAR - EXACTLY LIKE YOUR DESIGN */
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 18px 24px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            margin-bottom: 30px;
        }
        .filter-group {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        .dropdown-wrapper {
            position: relative;
        }
        .dropdown-btn {
            background: #f1f5f9;
            border: none;
            padding: 12px 20px 12px 48px;
            border-radius: 14px;
            font-weight: 600;
            color: #1e293b;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 180px;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        .dropdown-btn:hover { background: #e2e8f0; }
        .dropdown-btn.active { background: #059669; color: white; }
        .dropdown-btn i { font-size: 18px; position: absolute; left: 16px; top: 50%; transform: translateY(-50%); }
        .dropdown-btn::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 12px;
            margin-left: 8px;
        }
        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 14px;
            margin-top: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            overflow: hidden;
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s;
        }
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .dropdown-item {
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: 0.2s;
            border-bottom: 1px solid #f1f5f9;
        }
        .dropdown-item:last-child { border-bottom: none; }
        .dropdown-item:hover { background: #f0fdf4; color: #059669; }
        .dropdown-item.active { background: #d1fae5; color: #059669; font-weight: 600; }
        .btn-request {
            background: white;
            border: 2px solid #059669;
            color: #059669;
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }
        .btn-request:hover { background: #059669; color: white; }

        /* Rest of your existing styles (unchanged) */
        .appointments-grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
        .appointment-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,0.08); transition: all 0.4s ease; border: 1px solid #f1f5f9; }
        .appointment-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(5,150,105,0.18); border-color: #059669; }
        .card-header { padding: 20px; display: flex; align-items: center; gap: 14px; background: #f8fff9; border-bottom: 1px solid #f1f5f9; }
        .card-header img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
        .requester-info h4 { font-size: 16px; font-weight: 600; color: #1e293b; }
        .requester-info small { color: #64748b; font-size: 13px; }
        .card-body { padding: 24px; }
        .detail-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px dashed #e2e8f0; }
        .detail-label { color: #64748b; font-size: 14px; }
        .detail-value { font-weight: 600; color: #065f46; }
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-rejected { background: #fce7f3; color: #be123c; }
        .status-rescheduled { background: #dbeafe; color: #1d4ed8; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .notes-section { background: #f8fafc; padding: 16px; border-radius: 12px; margin: 16px 0; }
        .notes-title { font-size: 14px; color: #64748b; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .notes-text { color: #475569; line-height: 1.6; }
        .action-buttons { margin-top: 20px; }
        .btn-view-details { background: #059669; color: white; padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; width: 100%; text-align: center; }
        .sidebar-title { font-size: 20px; font-weight: 700; color: #065f46; margin-bottom: 20px; }
        .quick-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid #f1f5f9; }
        .quick-item:last-child { border-bottom: none; }
        .quick-left { display: flex; align-items: center; gap: 12px; }
        .quick-left small { color: #64748b; font-size: 13px; }
        .quick-right { text-align: right; }
        .quick-status { font-size: 12px; padding: 4px 10px; border-radius: 20px; font-weight: 600; }

        @media (max-width: 1200px) {
            .content-wrapper { flex-direction: column; padding: 20px; }
            .right-sidebar { width: 100%; position: static; order: -1; }
        }
    </style>
</head>
<body>

    <!-- HEADER & SIDEBAR (unchanged) -->
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
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Search appointments...">
            </div>
        </div>
        <div class="header-right">
            <div class="notification-bell"><i class="fas fa-bell"></i><span class="badge">3</span></div>
            <div class="user-profile">
                <span><?= htmlspecialchars($user_fullname) ?></span>
                <img src="https://via.placeholder.com/44/059669/ffffff?text=<?= substr($user_fullname,0,1) ?>" alt="User">
                <i class="fas fa-caret-down"></i>
            </div>
        </div>
    </div>

    <div class="main-layout">
        <div class="sidebar">
            
            <div class="nav-menu">
                <a href="dashboard.php"><div class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</div></a>
                <a href="announcements.php"><div class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</div></a>
                <a href="calendar.php"><div class="nav-item"><i class="fas fa-calendar"></i> Calendar</div></a>
                <a href="appointments.php"><div class="nav-item active"><i class="fas fa-clock"></i> Appointments</div></a>
                <a href="financial.php"><div class="nav-item"><i class="fas fa-coins"></i> Financial</div></a>
                <a href="profile.php"><div class="nav-item"><i class="fas fa-user"></i> My Profile</div></a>
                <a href="support.php"><div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div></a>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="center-content">
                <h1 class="page-title">Appointments</h1>

                <!-- NEW FILTER BAR WITH DROPDOWNS -->
                <div class="filter-bar">
                    <div class="filter-group">
                        <div class="dropdown-wrapper">
                            <button class="dropdown-btn" onclick="toggleDropdown('type-dropdown')">
                                <i class="fas fa-pray"></i>
                                <span>Type: All</span>
                            </button>
                            <div id="type-dropdown" class="dropdown-menu">
                                <div class="dropdown-item active" data-value="">All</div>
                                <div class="dropdown-item" data-value="baptism"><i class="fas fa-baby"></i> Baptism</div>
                                <div class="dropdown-item" data-value="wedding"><i class="fas fa-ring"></i> Wedding Mass</div>
                                <div class="dropdown-item" data-value="mass"><i class="fas fa-church"></i> Monthly Mass</div>
                                <div class="dropdown-item" data-value="confession"><i class="fas fa-cross"></i> Confession</div>
                            </div>
                        </div>

                        <div class="dropdown-wrapper">
                            <button class="dropdown-btn" onclick="toggleDropdown('status-dropdown')">
                                <i class="fas fa-check-circle"></i>
                                <span>Status: All</span>
                            </button>
                            <div id="status-dropdown" class="dropdown-menu">
                                <div class="dropdown-item active" data-value="">All</div>
                                <div class="dropdown-item" data-value="approved"><i class="fas fa-check"></i> Approved</div>
                                <div class="dropdown-item" data-value="pending"><i class="fas fa-clock"></i> Pending</div>
                                <div class="dropdown-item" data-value="rescheduled"><i class="fas fa-calendar-alt"></i> Rescheduled</div>
                                <div class="dropdown-item" data-value="cancelled"><i class="fas fa-times"></i> Cancelled</div>
                                <div class="dropdown-item" data-value="rejected"><i class="fas fa-ban"></i> Rejected</div>
                            </div>
                        </div>

                        <button class="filter-green" style="padding:12px 24px; border-radius:14px;">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>

                    <button class="btn-request"><i class="fas fa-plus"></i> Request Appointment</button>
                </div>

                <!-- Rest of your appointment cards & right sidebar (unchanged) -->
                <div class="appointments-grid">
                    <?php if (empty($appointments)): ?>
                        <p style="text-align:center; padding:80px; color:#94a3b8; font-size:18px; background:white; border-radius:20px;">
                            No appointments found.
                        </p>
                    <?php else: ?>
                        <?php foreach ($appointments as $ap): ?>
                            <div class="appointment-card">
                                <!-- Your existing card content -->
                                <div class="card-header">
                                    <img src="<?= $ap['profile_pic'] ? '../uploads/'.$ap['profile_pic'] : 'https://via.placeholder.com/48/6366f1/ffffff?text='.substr($ap['requester_name'],0,1) ?>" alt="User">
                                    <div class="requester-info">
                                        <h4><?= htmlspecialchars($ap['requester_name']) ?></h4>
                                        <small>Parishioner</small>
                                    </div>
                                    <div style="margin-left:auto; color:#64748b;">
                                        <?= date('M j, Y', strtotime($ap['preferred_date'])) ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="detail-row">
                                        <span class="detail-label">Status:</span>
                                        <span class="status-badge status-<?= $ap['status'] ?>"><?= ucfirst($ap['status']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Type:</span>
                                        <span class="detail-value"><?= ucfirst($ap['type']) ?> Intention</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Date:</span>
                                        <span class="detail-value"><?= date('F j, Y', strtotime($ap['preferred_date'])) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Time:</span>
                                        <span class="detail-value"><?= date('g:i A', strtotime($ap['preferred_time'])) ?></span>
                                    </div>
                                    <div class="notes-section">
                                        <div class="notes-title">Intention:</div>
                                        <div class="notes-text"><?= nl2br(htmlspecialchars($ap['message'])) ?></div>
                                    </div>
                                    <div class="action-buttons">
                                        <button class="btn-view-details">View Requirements</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT SIDEBAR (unchanged) -->
            <div class="right-sidebar">
                <h3 class="sidebar-title">Recent Appointments</h3>
                <?php foreach (array_slice($appointments, 0, 8) as $ap): ?>
                    <div class="quick-item">
                        <div class="quick-left">
                            <i class="fas fa-church" style="color:#059669; font-size:20px;"></i>
                            <div>
                                <div style="font-weight:600; color:#1e293b; font-size:14px;">
                                    <?= htmlspecialchars($ap['requester_name']) ?>
                                </div>
                                <small><?= date('M j, Y • g:i A', strtotime($ap['preferred_date'].' '.$ap['preferred_time'])) ?></small>
                            </div>
                        </div>
                        <div class="quick-right">
                            <div style="font-size:13px; margin-bottom:4px;"><strong><?= ucfirst($ap['type']) ?></strong></div>
                            <span class="quick-status status-<?= $ap['status'] ?>"><?= ucfirst($ap['status']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="text-align:center; margin-top:24px; color:#059669; font-weight:600; cursor:pointer;">
                    Discover All
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleDropdown(id) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu.id !== id) menu.classList.remove('show');
            });
            document.getElementById(id).classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-wrapper')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // Update button text on selection
        document.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', function() {
                const parent = this.closest('.dropdown-wrapper');
                const btn = parent.querySelector('.dropdown-btn span');
                const icon = parent.querySelector('.dropdown-btn i');
                const text = this.textContent.trim();
                btn.textContent = text === 'All' ? (parent.querySelector('.dropdown-btn').innerHTML.includes('Type') ? 'Type: All' : 'Status: All') : text;
                
                parent.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                parent.querySelector('.dropdown-menu').classList.remove('show');
            });
        });
    </script>
</body>
</html>