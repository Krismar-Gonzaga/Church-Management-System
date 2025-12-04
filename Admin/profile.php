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
$message = '';
$message_type = '';

// Fetch current user's full profile data with sacrament information
$stmt = $pdo->prepare("
    SELECT u.*, 
           DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(), birthday)), '%Y') + 0 AS age,
           CASE 
               WHEN baptism_date IS NOT NULL AND confirmation_date IS NOT NULL AND first_communion_date IS NOT NULL THEN 'All Sacraments'
               WHEN baptism_date IS NOT NULL AND confirmation_date IS NOT NULL THEN 'Baptized & Confirmed'
               WHEN baptism_date IS NOT NULL THEN 'Baptized Only'
               ELSE 'No Sacraments Recorded'
           END as sacrament_status
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    
    if ($field && $value !== '') {
        try {
            // Handle special field validations
            if (in_array($field, ['birthday', 'baptism_date', 'confirmation_date', 'first_communion_date', 'marriage_date'])) {
                // Validate date format
                if (!strtotime($value)) {
                    throw new Exception('Invalid date format');
                }
                $value = date('Y-m-d', strtotime($value));
            }
            
            if ($field === 'fullname' && strlen(trim($value)) < 2) {
                throw new Exception('Name must be at least 2 characters long');
            }
            
            if ($field === 'email') {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                
                // Check if email already exists (excluding current user)
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_stmt->execute([$value, $user_id]);
                if ($check_stmt->fetch()) {
                    throw new Exception('Email already exists');
                }
            }
            
            // Update the field
            $update_stmt = $pdo->prepare("UPDATE users SET $field = ?, updated_at = NOW() WHERE id = ?");
            if ($update_stmt->execute([$value, $user_id])) {
                $message = ucfirst(str_replace('_', ' ', $field)) . ' updated successfully!';
                $message_type = 'success';
                
                // Refresh user data
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = 'Failed to update ' . str_replace('_', ' ', $field);
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        try {
            $upload_dir = '../uploads/profile_pics/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['profile_pic']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Only JPEG, PNG, GIF, and WebP images are allowed');
            }
            
            $file_size = $_FILES['profile_pic']['size'];
            if ($file_size > 5 * 1024 * 1024) { // 5MB limit
                throw new Exception('File size must be less than 5MB');
            }
            
            // Generate unique filename
            $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $filepath)) {
                // Delete old profile picture if exists
                if ($user['profile_pic'] && file_exists($upload_dir . $user['profile_pic'])) {
                    unlink($upload_dir . $user['profile_pic']);
                }
                
                // Update database
                $update_stmt = $pdo->prepare("UPDATE users SET profile_pic = ?, updated_at = NOW() WHERE id = ?");
                if ($update_stmt->execute([$filename, $user_id])) {
                    $message = 'Profile picture updated successfully!';
                    $message_type = 'success';
                    
                    // Refresh user data
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } else {
                throw new Exception('Failed to upload file');
            }
        } catch (Exception $e) {
            $message = 'Upload error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Format data for display
function formatDateForDisplay($date) {
    if (!$date || $date === '0000-00-00') {
        return 'Not recorded';
    }
    return date('F j, Y', strtotime($date));
}

$birthday_formatted = formatDateForDisplay($user['birthday']);
$age = $user['age'] ?? 'N/A';
$zone = $user['zone'] ?? 'Not set';
$address = $user['address'] ?? 'Not set';
$civil_status = ucfirst($user['civil_status'] ?? 'single');
$role_display = $user['role'] === 'member' ? 'Member' : ucwords($user['role']);
$phone = $user['phone'] ?? 'Not set';
$occupation = $user['occupation'] ?? 'Not set';
$baptism_date = formatDateForDisplay($user['baptism_date']);
$confirmation_date = formatDateForDisplay($user['confirmation_date']);
$first_communion_date = formatDateForDisplay($user['first_communion_date']);
$marriage_date = formatDateForDisplay($user['marriage_date']);
$last_sacrament_received = $user['last_sacrament_received'] ?? 'Not recorded';
$sacrament_notes = $user['sacrament_notes'] ?? 'No notes';
$sacrament_status = $user['sacrament_status'] ?? 'No Sacraments Recorded';

// Get all roles for dropdown
$roles = ['admin'];

// Get sacraments for dropdown
$sacraments = ['Baptism', 'Confirmation', 'First Communion', 'Matrimony', 'Anointing of the Sick', 'Confession'];
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
            --sacrament-blue: #3b82f6;
            --sacrament-light-blue: #dbeafe;
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

        /* Message Alerts */
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
        .profile-pic-container {
            position: relative;
            cursor: pointer;
        }
        .profile-pic {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 6px solid white;
            box-shadow: 0 10px 30px rgba(5,150,105,0.2);
            transition: all 0.3s;
        }
        .profile-pic:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }
        .change-photo {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--green);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 15px rgba(5,150,105,0.3);
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
        .sacrament-badge {
            display: inline-block;
            background: var(--sacrament-light-blue);
            color: var(--sacrament-blue);
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-left: 12px;
            border: 1px solid var(--sacrament-blue);
        }

        /* PROFILE DETAILS GRID */
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
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
        .detail-item.sacrament {
            border-color: var(--sacrament-blue);
        }
        .detail-item.sacrament:hover {
            border-color: var(--sacrament-blue);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.1);
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
        .sacrament-label {
            color: var(--sacrament-blue);
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
            min-height: 56px;
        }
        .editable-value {
            cursor: pointer;
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .editable-value:hover {
            background: #f0fdf4;
        }
        .edit-icon {
            color: var(--green);
            font-size: 18px;
            cursor: pointer;
            opacity: 0.7;
            transition: 0.3s;
            padding: 8px;
            border-radius: 8px;
        }
        .edit-icon:hover { 
            opacity: 1; 
            transform: scale(1.1);
            background: #f0fdf4;
        }
        .sacrament-icon {
            color: var(--sacrament-blue);
        }

        /* Sacrament Timeline */
        .sacrament-timeline {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            position: relative;
        }
        .sacrament-timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e2e8f0;
            transform: translateY(-50%);
        }
        .sacrament-step {
            text-align: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }
        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 20px;
            color: #64748b;
        }
        .step-icon.completed {
            background: var(--sacrament-light-blue);
            border-color: var(--sacrament-blue);
            color: var(--sacrament-blue);
        }
        .step-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        .step-date {
            font-size: 11px;
            color: #94a3b8;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header {
            padding: 24px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 {
            font-size: 24px;
            color: #065f46;
            font-weight: 700;
        }
        .modal-header.sacrament-header h2 {
            color: var(--sacrament-blue);
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            color: #64748b;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: 0.3s;
        }
        .close-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        .modal-body {
            padding: 30px;
            max-height: 400px;
            overflow-y: auto;
        }
        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 4px rgba(5,150,105,0.1);
        }
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        select.form-control {
            background: white;
            cursor: pointer;
        }
        .btn-cancel {
            padding: 12px 24px;
            background: #f1f5f9;
            color: #475569;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-cancel:hover {
            background: #e2e8f0;
        }
        .btn-save {
            padding: 12px 24px;
            background: var(--green);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-save:hover {
            background: var(--green-dark);
        }
        .btn-sacrament {
            background: var(--sacrament-blue);
        }
        .btn-sacrament:hover {
            background: #2563eb;
        }

        /* Profile Picture Upload */
        .upload-container {
            text-align: center;
            padding: 20px;
        }
        .upload-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            display: block;
            border: 3px solid var(--green);
        }
        .file-input {
            display: none;
        }
        .upload-label {
            display: inline-block;
            background: var(--green);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }
        .upload-label:hover {
            background: var(--green-dark);
        }

        @media (max-width: 768px) {
            .profile-header { flex-direction: column; text-align: center; }
            .profile-grid { grid-template-columns: 1fr; }
            .content-area { padding: 20px; }
            .modal-content { width: 95%; margin: 10% auto; }
            .sacrament-timeline { flex-wrap: wrap; }
            .sacrament-timeline::before { display: none; }
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
                <img src="<?= $user['profile_pic'] ? '../uploads/profile_pics/'.$user['profile_pic'] : 'https://via.placeholder.com/44/059669/ffffff?text='.substr($user['fullname'],0,1) ?>" alt="User">
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

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                    <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>

            <div class="profile-card">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-pic-container" onclick="openUploadModal()">
                        <img src="<?= $user['profile_pic'] ? '../uploads/profile_pics/'.$user['profile_pic'] : 'https://via.placeholder.com/140/059669/ffffff?text='.substr($user['fullname'],0,1) ?>" 
                             alt="Profile" class="profile-pic">
                        <div class="change-photo">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <div class="profile-info">
                        <h1 onclick="openEditModal('fullname', '<?= htmlspecialchars($user['fullname']) ?>')" style="cursor:pointer;">
                            <?= htmlspecialchars($user['fullname']) ?>
                        </h1>
                        <p onclick="openEditModal('email', '<?= htmlspecialchars($user['email']) ?>')" style="cursor:pointer;">
                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?>
                        </p>
                        <p onclick="openEditModal('phone', '<?= htmlspecialchars($phone) ?>')" style="cursor:pointer;">
                            <i class="fas fa-phone"></i> <?= $phone !== 'Not set' ? htmlspecialchars($phone) : 'No phone number' ?>
                        </p>
                        <div>
                            <span class="role-badge" onclick="openEditModal('role', '<?= htmlspecialchars($user['role']) ?>')" style="cursor:pointer;">
                                Role: <?= $role_display ?>
                            </span>
                            <?php if ($zone !== 'Not set'): ?>
                                <span class="zone-badge" onclick="openEditModal('zone', '<?= htmlspecialchars($zone) ?>')" style="cursor:pointer;">
                                    Zone: <?= htmlspecialchars($zone) ?>
                                </span>
                            <?php endif; ?>
                            <span class="sacrament-badge">
                                <?= $sacrament_status ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Sacrament Timeline -->
                <div class="detail-item sacrament">
                    <div class="detail-label sacrament-label">
                        <i class="fas fa-church"></i> Sacrament Journey
                    </div>
                    <div class="sacrament-timeline">
                        <div class="sacrament-step">
                            <div class="step-icon <?= $user['baptism_date'] ? 'completed' : '' ?>">
                                <i class="fas fa-water"></i>
                            </div>
                            <div class="step-label">Baptism</div>
                            <div class="step-date"><?= $baptism_date ?></div>
                        </div>
                        <div class="sacrament-step">
                            <div class="step-icon <?= $user['first_communion_date'] ? 'completed' : '' ?>">
                                <i class="fas fa-wine-glass-alt"></i>
                            </div>
                            <div class="step-label">First Communion</div>
                            <div class="step-date"><?= $first_communion_date ?></div>
                        </div>
                        <div class="sacrament-step">
                            <div class="step-icon <?= $user['confirmation_date'] ? 'completed' : '' ?>">
                                <i class="fas fa-dove"></i>
                            </div>
                            <div class="step-label">Confirmation</div>
                            <div class="step-date"><?= $confirmation_date ?></div>
                        </div>
                        <div class="sacrament-step">
                            <div class="step-icon <?= $user['marriage_date'] ? 'completed' : '' ?>">
                                <i class="fas fa-ring"></i>
                            </div>
                            <div class="step-label">Matrimony</div>
                            <div class="step-date"><?= $marriage_date ?></div>
                        </div>
                    </div>
                    <div class="detail-value">
                        <div class="editable-value" onclick="openSacramentModal()">
                            <div><strong>Last Sacrament:</strong> <?= htmlspecialchars($last_sacrament_received) ?></div>
                            <div><strong>Status:</strong> <?= $sacrament_status ?></div>
                        </div>
                        <i class="fas fa-edit edit-icon sacrament-icon" onclick="openSacramentModal()" title="Edit Sacrament Information"></i>
                    </div>
                </div>

                <!-- Profile Details -->
                <div class="profile-grid">
                    <!-- Personal Information -->
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-id-card"></i> Personal Information
                        </div>
                        <div class="detail-value">
                            <div class="editable-value" onclick="openEditModal('occupation', '<?= htmlspecialchars($occupation) ?>')">
                                <div><strong>Occupation:</strong> <?= $occupation ?></div>
                                <div><strong>Civil Status:</strong> <?= $civil_status ?></div>
                            </div>
                            <i class="fas fa-edit edit-icon" onclick="openEditModal('occupation', '<?= htmlspecialchars($occupation) ?>')" title="Edit Personal Information"></i>
                        </div>
                    </div>

                    <!-- Birth Information -->
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-birthday-cake"></i> Birth Information
                        </div>
                        <div class="detail-value">
                            <div class="editable-value" onclick="openEditModal('birthday', '<?= $user['birthday'] ?>')">
                                <div><strong>Age:</strong> <?= $age ?> Years</div>
                                <div><strong>Birthdate:</strong> <?= $birthday_formatted ?></div>
                            </div>
                            <i class="fas fa-edit edit-icon" onclick="openEditModal('birthday', '<?= $user['birthday'] ?>')" title="Update Birthday"></i>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-home"></i> Address
                        </div>
                        <div class="detail-value">
                            <div class="editable-value" onclick="openEditModal('address', '<?= htmlspecialchars($address) ?>')">
                                <?= $address !== 'Not set' ? nl2br(htmlspecialchars($address)) : '<em style="color:#94a3b8;">No address provided</em>' ?>
                            </div>
                            <i class="fas fa-edit edit-icon" onclick="openEditModal('address', '<?= htmlspecialchars($address) ?>')" title="Edit Address"></i>
                        </div>
                    </div>

                    <!-- Account Status -->
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-user-check"></i> Account Status
                        </div>
                        <div class="detail-value">
                            <div class="editable-value">
                                <div><strong>Status:</strong> <span style="color:#059669; font-weight:bold;">Active</span></div>
                                <div><strong>Member Since:</strong> <?= date('F j, Y', strtotime($user['created_at'])) ?></div>
                                <div><strong>Last Updated:</strong> <?= date('F j, Y', strtotime($user['updated_at'] ?? $user['created_at'])) ?></div>
                            </div>
                            <i class="fas fa-info-circle edit-icon" style="color:#94a3b8;" title="Account information"></i>
                        </div>
                    </div>
                </div>

                <div style="margin-top:40px; text-align:center;">
                    <button onclick="openEditModal('fullname', '<?= htmlspecialchars($user['fullname']) ?>')" 
                            style="background:var(--green); color:white; padding:14px 32px; border:none; border-radius:16px; font-size:16px; font-weight:600; cursor:pointer; box-shadow:0 8px 25px rgba(5,150,105,0.3); margin-right:15px;">
                        <i class="fas fa-user-edit"></i> Edit Full Profile
                    </button>
                    <button onclick="openSacramentModal()" 
                            style="background:var(--sacrament-blue); color:white; padding:14px 32px; border:none; border-radius:16px; font-size:16px; font-weight:600; cursor:pointer; box-shadow:0 8px 25px rgba(59, 130, 246, 0.3); margin-right:15px;">
                        <i class="fas fa-church"></i> Manage Sacraments
                    </button>
                    <button onclick="openUploadModal()" 
                            style="background:white; color:var(--green); padding:14px 32px; border:2px solid var(--green); border-radius:16px; font-size:16px; font-weight:600; cursor:pointer;">
                        <i class="fas fa-camera"></i> Change Photo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Edit Information</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="field" id="editField">
                <div class="modal-body">
                    <div class="form-group">
                        <label id="fieldLabel" for="fieldValue">Value</label>
                        <div id="inputContainer">
                            <!-- Input field will be generated here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sacrament Modal -->
    <div id="sacramentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header sacrament-header">
                <h2>Manage Sacraments</h2>
                <button class="close-btn" onclick="closeSacramentModal()">&times;</button>
            </div>
            <form method="POST" action="" id="sacramentForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Baptism Date</label>
                        <input type="date" name="baptism_date" class="form-control" 
                               value="<?= $user['baptism_date'] ?>" 
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>First Communion Date</label>
                        <input type="date" name="first_communion_date" class="form-control" 
                               value="<?= $user['first_communion_date'] ?>" 
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Confirmation Date</label>
                        <input type="date" name="confirmation_date" class="form-control" 
                               value="<?= $user['confirmation_date'] ?>" 
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Marriage Date (if applicable)</label>
                        <input type="date" name="marriage_date" class="form-control" 
                               value="<?= $user['marriage_date'] ?>" 
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Sacrament Received</label>
                        <select name="last_sacrament_received" class="form-control">
                            <option value="">Select Sacrament</option>
                            <?php foreach ($sacraments as $sacrament): ?>
                                <option value="<?= $sacrament ?>" <?= $last_sacrament_received === $sacrament ? 'selected' : '' ?>>
                                    <?= $sacrament ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sacrament Notes</label>
                        <textarea name="sacrament_notes" class="form-control" rows="4"><?= htmlspecialchars($sacrament_notes) ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeSacramentModal()">Cancel</button>
                    <button type="submit" class="btn-save btn-sacrament">Save Sacrament Information</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Profile Picture Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Profile Picture</h2>
                <button class="close-btn" onclick="closeUploadModal()">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                <div class="modal-body">
                    <div class="upload-container">
                        <img id="imagePreview" src="<?= $user['profile_pic'] ? '../uploads/profile_pics/'.$user['profile_pic'] : 'https://via.placeholder.com/150/059669/ffffff?text='.substr($user['fullname'],0,1) ?>" 
                             alt="Preview" class="upload-preview">
                        <input type="file" name="profile_pic" id="profilePicInput" class="file-input" accept="image/*" onchange="previewImage(event)">
                        <label for="profilePicInput" class="upload-label">
                            <i class="fas fa-cloud-upload-alt"></i> Choose Image
                        </label>
                        <p style="margin-top:15px; color:#64748b; font-size:14px;">
                            Maximum file size: 5MB<br>
                            Allowed formats: JPG, PNG, GIF, WebP
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" class="btn-save">Upload Photo</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentField = '';
        let currentValue = '';

        function openEditModal(field, value) {
            currentField = field;
            currentValue = value;
            
            const modal = document.getElementById('editModal');
            const title = document.getElementById('modalTitle');
            const label = document.getElementById('fieldLabel');
            const container = document.getElementById('inputContainer');
            const fieldInput = document.getElementById('editField');
            
            // Set field name in hidden input
            fieldInput.value = field;
            
            // Set modal title and label based on field
            const fieldLabels = {
                'fullname': 'Full Name',
                'email': 'Email Address',
                'phone': 'Phone Number',
                'role': 'User Role',
                'zone': 'Zone/Area',
                'address': 'Address',
                'birthday': 'Birthday',
                'civil_status': 'Civil Status',
                'occupation': 'Occupation'
            };
            
            title.textContent = 'Edit ' + (fieldLabels[field] || field.replace('_', ' '));
            label.textContent = fieldLabels[field] || field.replace('_', ' ');
            
            // Create appropriate input based on field type
            let inputHtml = '';
            
            if (field === 'role') {
                inputHtml = `
                    <select name="value" class="form-control" id="fieldValue" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?= $role ?>" ${value === '<?= $role ?>' ? 'selected' : ''}>
                            <?= ucwords($role) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                `;
            } else if (field === 'civil_status') {
                inputHtml = `
                    <select name="value" class="form-control" id="fieldValue" required>
                        <option value="single" ${value === 'single' ? 'selected' : ''}>Single</option>
                        <option value="married" ${value === 'married' ? 'selected' : ''}>Married</option>
                        <option value="divorced" ${value === 'divorced' ? 'selected' : ''}>Divorced</option>
                        <option value="widowed" ${value === 'widowed' ? 'selected' : ''}>Widowed</option>
                    </select>
                `;
            } else if (field === 'address') {
                inputHtml = `
                    <textarea name="value" class="form-control" id="fieldValue" rows="4" required>${value === 'Not set' ? '' : value}</textarea>
                `;
            } else if (field === 'birthday') {
                const dateValue = value === 'Not set' || !value ? '' : value.split(' ')[0];
                inputHtml = `
                    <input type="date" name="value" class="form-control" id="fieldValue" 
                           value="${dateValue}" 
                           max="${new Date().toISOString().split('T')[0]}"
                           required>
                    <small style="color:#64748b; font-size:12px; margin-top:5px; display:block;">
                        Format: YYYY-MM-DD
                    </small>
                `;
            } else if (field === 'email') {
                inputHtml = `
                    <input type="email" name="value" class="form-control" id="fieldValue" 
                           value="${value === 'Not set' ? '' : value}" 
                           required>
                `;
            } else if (field === 'phone') {
                inputHtml = `
                    <input type="tel" name="value" class="form-control" id="fieldValue" 
                           value="${value === 'Not set' ? '' : value}" 
                           pattern="[0-9+\-\s()]{7,20}"
                           title="Phone number (7-20 digits)">
                `;
            } else {
                inputHtml = `
                    <input type="text" name="value" class="form-control" id="fieldValue" 
                           value="${value === 'Not set' ? '' : value}" 
                           required>
                `;
            }
            
            container.innerHTML = inputHtml;
            modal.style.display = 'block';
            
            // Focus on the input
            setTimeout(() => {
                const input = document.getElementById('fieldValue');
                if (input) input.focus();
            }, 100);
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            currentField = '';
            currentValue = '';
        }

        function openSacramentModal() {
            document.getElementById('sacramentModal').style.display = 'block';
        }

        function closeSacramentModal() {
            document.getElementById('sacramentModal').style.display = 'none';
        }

        function openUploadModal() {
            document.getElementById('uploadModal').style.display = 'block';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }

        function previewImage(event) {
            const input = event.target;
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const sacramentModal = document.getElementById('sacramentModal');
            const uploadModal = document.getElementById('uploadModal');
            
            if (event.target === editModal) closeEditModal();
            if (event.target === sacramentModal) closeSacramentModal();
            if (event.target === uploadModal) closeUploadModal();
        }

        // Close modals on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
                closeSacramentModal();
                closeUploadModal();
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

        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Make all editable values clickable
            const editableValues = document.querySelectorAll('.editable-value');
            editableValues.forEach(value => {
                value.style.cursor = 'pointer';
                value.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f0fdf4';
                });
                value.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });

        // Handle sacrament form submission
        document.getElementById('sacramentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Create hidden inputs for each field
            const form = this;
            const formData = new FormData(form);
            
            // Create a new form for submission
            const submitForm = document.createElement('form');
            submitForm.method = 'POST';
            submitForm.style.display = 'none';
            
            formData.forEach((value, key) => {
                if (value) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    submitForm.appendChild(input);
                }
            });
            
            document.body.appendChild(submitForm);
            submitForm.submit();
        });
    </script>
</body>
</html>