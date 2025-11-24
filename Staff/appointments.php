<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Ensure only staff/admin/finance can access
if (!in_array($_SESSION['role'] ?? '', ['admin', 'staff', 'finance'])) {
    header('Location: login.php');
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

// Fetch all appointments for staff (they can see all)
$stmt = $pdo->query("
    SELECT ar.*, u.fullname AS requester_name, u.profile_pic 
    FROM appointment_requests ar 
    JOIN users u ON ar.user_id = u.id 
    ORDER BY ar.requested_at DESC
");
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        .filter-bar {
            display: flex; justify-content: space-between; align-items: center;
            background: white; padding: 18px 24px; border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06); margin-bottom: 30px;
        }

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
        .notes-title { font-weight: 600; color: #059669; margin-bottom: 8px; }

        .card-actions {
            position: absolute;
            bottom: 24px;
            right: 24px;
            display: flex;
            gap: 10px;
        }
        .btn-accept, .btn-reject {
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }
        .btn-accept {
            background: #d1fae5;
            color: #065f46;
        }
        .btn-accept:hover {
            background: #059669;
            color: white;
        }
        .btn-reject {
            background: #fee2e2;
            color: #991b1b;
        }
        .btn-reject:hover {
            background: #ef4444;
            color: white;
        }

        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
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

        @media (max-width: 1200px) {
            .content-wrapper { flex-direction: column; padding: 20px; }
            .right-sidebar { width: 100%; position: static; }
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
                <h1 class="page-title">Appointments</h1>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="filter-bar">
                    <div style="display:flex; gap:16px; align-items:center;">
                        <strong>Filter:</strong>
                        <select style="padding:10px 16px; border-radius:12px; border:2px solid #e2e8f0;">
                            <option>All Types</option>
                            <option>Baptism</option>
                            <option>Wedding Mass</option>
                            <option>Mass Intention</option>
                        </select>
                        <select style="padding:10px 16px; border-radius:12px; border:2px solid #e2e8f0;">
                            <option>All Status</option>
                            <option>Pending</option>
                            <option>Approved</option>
                            <option>Rejected</option>
                        </select>
                    </div>
                </div>

                <div class="appointments-grid">
                    <?php if (empty($appointments)): ?>
                        <div style="text-align:center; padding:100px 20px; background:white; border-radius:20px; color:#94a3b8;">
                            <i class="fas fa-calendar-times" style="font-size:48px; margin-bottom:16px; color:#cbd5e1;"></i>
                            <p style="font-size:18px;">No appointment requests found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($appointments as $ap): ?>
                            <div class="appointment-card">
                                <div class="card-header">
                                    <img src="<?= $ap['profile_pic'] ? '../uploads/'.$ap['profile_pic'] : 'https://via.placeholder.com/52/059669/ffffff?text='.substr($ap['requester_name'],0,1) ?>" alt="User">
                                    <div>
                                        <h4 style="margin:0; font-size:17px; font-weight:600; color:#1e293b;">
                                            <?= htmlspecialchars($ap['requester_name']) ?>
                                        </h4>
                                        <small style="color:#64748b;">
                                            <?= ucfirst($ap['type']) ?> • <?= date('M j, Y', strtotime($ap['preferred_datetime'])) ?>
                                        </small>
                                    </div>
                                    <div style="margin-left:auto;">
                                        <span class="status-badge status-<?= htmlspecialchars($ap['status']) ?>">
                                            <?= ucwords(str_replace('_', ' ', $ap['status'])) ?>
                                        </span>
                                    </div>
                                </div>

                                <div style="padding:24px; padding-bottom:80px;">
                                    <div class="detail-row">
                                        <span class="detail-label">Type</span>
                                        <span class="detail-value"><?= ucfirst($ap['type']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Chapel/Parish</span>
                                        <span class="detail-value"><?= ucfirst(str_replace('_', ' ', $ap['chapel'])) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Preferred Date & Time</span>
                                        <span class="detail-value">
                                            <?= date('F j, Y \a\t g:i A', strtotime($ap['preferred_datetime'])) ?>
                                        </span>
                                    </div>
                                    <?php if ($ap['priest']): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Requested Priest</span>
                                        <span class="detail-value"><?= ucwords(str_replace('_', ' ', $ap['priest'])) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($ap['purpose']): ?>
                                    <div class="notes-section">
                                        <div class="notes-title">Purpose / Intention</div>
                                        <div class="notes-text">
                                            <?= nl2br(htmlspecialchars($ap['purpose'])) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($ap['notes']): ?>
                                    <div class="notes-section">
                                        <div class="notes-title">Additional Notes</div>
                                        <div class="notes-text"><?= nl2br(htmlspecialchars($ap['notes'])) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($ap['address']): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Address (Blessing/Visit)</span>
                                        <span class="detail-value"><?= htmlspecialchars($ap['address']) ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($ap['status'] === 'pending'): ?>
                                    <div class="card-actions">
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
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT SIDEBAR - RECENT ACTIVITY -->
            <div class="right-sidebar">
                <h3 style="color:#065f46; font-size:21px; margin-bottom:20px;">Recent Requests</h3>
                <?php foreach (array_slice($appointments, 0, 7) as $ap): ?>
                    <div style="display:flex; justify-content:space-between; padding:14px 0; border-bottom:1px solid #f1f5f9;">
                        <div style="display:flex; gap:12px; align-items:center;">
                            <i class="fas fa-church" style="color:var(--green); font-size:20px;"></i>
                            <div>
                                <div style="font-weight:600; font-size:14px; color:#1e293b;">
                                    <?= htmlspecialchars($ap['requester_name']) ?>
                                </div>
                                <small style="color:#64748b;">
                                    <?= date('M j, Y • g:i A', strtotime($ap['preferred_datetime'])) ?>
                                </small>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:13px; font-weight:600; color:#059669;">
                                <?= ucfirst($ap['type']) ?>
                            </div>
                            <span class="status-badge status-<?= $ap['status'] ?>" style="font-size:11px; padding:4px 10px;">
                                <?= ucfirst($ap['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>

