<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../Staff/login.php');
    exit;
}

// Ensure only administrators can access
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../Staff/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;

// Fetch current user's full profile data
$stmt = $pdo->prepare("
    SELECT u.*, 
           DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(), birthday)), '%Y') + 0 AS age
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Format birthday
$birthday_formatted = $user['birthday'] ? date('F j, Y', strtotime($user['birthday'])) : 'Not set';
$age = $user['age'] ?? 'N/A';
$zone = $user['zone'] ?? 'Not set';
$address = $user['address'] ?? 'Not set';
$civil_status = ucfirst($user['civil_status'] ?? 'single');
$role_display = $user['role'] === 'member' ? 'Member' : ucwords($user['role']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --green: #059669;
            --green-dark: #047857;
            --light-green: #f0fdf4;
            --gray: #64748b;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }

        /* TOP HEADER */
        .top-header {
            position: fixed; top: 0; left: 0; right: 0; height: 80px;
            background: white; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header-left { display: flex; align-items: center; gap: 22px; }
        .header-left img { height: 58px; }
        .header-right { display: flex; align-items: center; gap: 24px; }
        .notification-bell { position: relative; font-size: 23px; color: var(--gray); cursor: pointer; }
        .notification-bell .badge { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 10px; width: 19px; height: 19px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .user-profile { display: flex; align-items: center; gap: 12px; background: var(--light-green); padding: 10px 18px; border-radius: 14px; cursor: pointer; border: 1px solid #d1fae5; }
        .user-profile:hover { background: #d1fae5; }
        .user-profile img { width: 44px; height: 44px; border-radius: 50%; border: 2px solid var(--green); }

        /* MAIN LAYOUT */
        .main-layout { display: flex; margin-top: 80px; min-height: calc(100vh - 80px); }
        .sidebar { width: 260px; background: white; border-right: 1px solid #e2e8f0; position: fixed; top: 80px; bottom: 0; overflow-y: auto; z-index: 999; }
        .nav-item { padding: 16px 24px; display: flex; align-items: center; gap: 14px; color: var(--gray); cursor: pointer; transition: 0.3s; }
        .nav-item:hover, .nav-item.active { background: var(--light-green); color: var(--green); border-left: 5px solid var(--green); }
        .nav-item i { font-size: 21px; width: 30px; }

        .content-area { margin-left: 260px; padding: 40px; flex: 1; }
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 30px; color: #1e293b; }

        /* PROFILE CARD */
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            max-width: 2000px;
            margin: 0 auto;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f1f5f9;
        }
        .profile-pic {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 6px solid white;
            box-shadow: 0 10px 30px rgba(5,150,105,0.2);
        }
        .profile-info h1 {
            font-size: 28px;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 8px;
        }
        .profile-info p {
            color: #64748b;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .role-badge {
            display: inline-block;
            background: var(--light-green);
            color: var(--green);
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 10px;
        }
        .zone-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-left: 12px;
        }

        /* PROFILE DETAILS GRID */
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }
        .detail-item {
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
            position: relative;
        }
        .detail-item:hover {
            border-color: var(--green);
            background: white;
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(5,150,105,0.1);
        }
        .detail-label {
            font-weight: 600;
            color: #475569;
            font-size: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-value {
            font-size: 17px;
            font-weight: 600;
            color: #1e293b;
            padding: 14px 16px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .edit-icon {
            color: var(--green);
            font-size: 18px;
            cursor: pointer;
            opacity: 0.7;
            transition: 0.3s;
        }
        .edit-icon:hover { opacity: 1; transform: scale(1.2); }

        @media (max-width: 768px) {
            .profile-header { flex-direction: column; text-align: center; }
            .profile-grid { grid-template-columns: 1fr; }
            .content-area { padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- TOP HEADER -->
    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo">
            <h3 style="color:#065f46; font-size:24px; font-weight:700;">San Jose Parish Laligan</h3>
        </div>
        <div class="header-right">
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="badge">3</span>
            </div>
            <div class="user-profile">
                <span><?= htmlspecialchars($user['fullname']) ?></span>
                <img src="<?= $user['profile_pic'] ? '../uploads/'.$user['profile_pic'] : 'https://via.placeholder.com/44/059669/ffffff?text='.substr($user['fullname'],0,1) ?>" alt="User">
                <i class="fas fa-caret-down"></i>
            </div>
        </div>
    </div>

    <!-- SIDEBAR -->
    <div class="main-layout">
        <div class="sidebar">
           
            <div style="margin-top:20px;">
                <a href="dashboard.php"><div class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</div></a>
                <a href="announcements.php"><div class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</div></a>
                <a href="calendar.php"><div class="nav-item"><i class="fas fa-calendar"></i> Calendar</div></a>
                <a href="appointments.php"><div class="nav-item"><i class="fas fa-clock"></i> Appointments</div></a>
                <a href="financial.php"><div class="nav-item"><i class="fas fa-coins"></i> Financial</div></a>
                <a href="profile.php"><div class="nav-item active"><i class="fas fa-user"></i> My Profile</div></a>
                <a href="userManagement.php"><div class="nav-item"><i class="fas fa-users-cog"></i> User Management</div></a>
                <a href="support.php"><div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div></a>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="content-area">
            <h1 class="page-title">My Profile</h1>

            <div class="profile-card">
                <!-- Profile Header -->
                <div class="profile-header">
                    <img src="<?= $user['profile_pic'] ? '../images/'.$user['profile_pic'] : 'https://via.placeholder.com/140/059669/ffffff?text='.substr($user['fullname'],0,1) ?>" alt="Profile" class="profile-pic">
                    <div class="profile-info">
                        <h1><?= htmlspecialchars($user['fullname']) ?></h1>
                        <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                        <div>
                            <span class="role-badge">Role: <?= $role_display ?></span>
                            <?php if ($zone !== 'Not set'): ?>
                                <span class="zone-badge">Zone: <?= htmlspecialchars($zone) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Details -->
                <div class="profile-grid">
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-home"></i> Address
                        </div>
                        <div class="detail-value">
                            <?= $address !== 'Not set' ? nl2br(htmlspecialchars($address)) : '<em style="color:#94a3b8;">No address provided</em>' ?>
                            <i class="fas fa-edit edit-icon" title="Edit Address"></i>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-birthday-cake"></i> Age
                        </div>
                        <div class="detail-value">
                            <?= $age ?> Years Old
                            <i class="fas fa-edit edit-icon" title="Update Birthday"></i>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-calendar-alt"></i> Birthdate
                        </div>
                        <div class="detail-value">
                            <?= $birthday_formatted ?>
                            <i class="fas fa-edit edit-icon" title="Update Birthday"></i>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-heart"></i> Civil Status
                        </div>
                        <div class="detail-value">
                            <?= $civil_status ?>
                            <i class="fas fa-edit edit-icon" title="Update Status"></i>
                        </div>
                    </div>
                </div>

                <div style="margin-top:40px; text-align:center;">
                    <button style="background:var(--green); color:white; padding:14px 32px; border:none; border-radius:16px; font-size:16px; font-weight:600; cursor:pointer; box-shadow:0 8px 25px rgba(5,150,105,0.3);">
                        <i class="fas fa-user-edit"></i> Edit Full Profile
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

