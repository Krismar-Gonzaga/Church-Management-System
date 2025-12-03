<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../Staff/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../Staff/login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Administrator';
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id === ($_SESSION['user_id'] ?? 0)) {
        $message = 'You cannot delete your own account.';
        $message_type = 'error';
    } elseif ($user_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $message = 'User deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to delete user. Please try again.';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

$users = $pdo->query("
    SELECT id, fullname, email, role, phone, profile_pic, created_at
    FROM users
    ORDER BY FIELD(role, 'admin','staff','finance','priest','member'), fullname
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | User Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --green: #059669;
            --green-dark: #047857;
            --light-green: #e3f5e5;
            --dark-bg: #111827;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1f2937; }

        /* Top header borrowed from calendar.php */
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
        .header-left { display:flex; align-items:center; gap:22px; }
        .header-left img { height:58px; }
        .header-right { display:flex; align-items:center; gap:20px; }
        .header-search {
            flex: 1;
            margin-right: 20px;
            display: flex;
            justify-content: right;
            padding: 0 30px;
        }
        .search-box { position: relative; width:100%; max-width:500px; }
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
        .notification-bell { position:relative; font-size:23px; color:#64748b; cursor:pointer; }
        .notification-bell .badge {
            position:absolute; top:-8px; right:-8px;
            background:#ef4444; color:white; font-size:10px;
            width:19px; height:19px; border-radius:50%;
            display:flex; align-items:center; justify-content:center; font-weight:bold;
        }
        .profile-pill {
            display:flex; align-items:center; gap:10px;
            background:#f0fdf4; padding:10px 18px; border-radius:14px;
            border:1px solid #d1fae5;
        }
        .profile-pill img {
            width: 38px; height: 38px; border-radius: 50%; border: 2px solid var(--green);
        }

        .main-layout {
            display: flex; margin-top: 70px;
        }
        .sidebar {
            width: 240px; background: white; border-right: 1px solid #e5e7eb;
            min-height: calc(100vh - 70px); padding: 30px 0;
            position: fixed; top: 70px; bottom: 0;
        }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo img { height: 68px; }
        .nav-menu { display: flex; flex-direction: column; gap: 6px; padding: 0 25px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 18px; border-radius: 12px;
            color: #4b5563; cursor: pointer; text-decoration: none;
        }
        .nav-item.active, .nav-item:hover {
            background: var(--light-green); color: var(--green);
        }
        .nav-item i { width: 20px; font-size: 16px; }

        .content-area {
            margin-left: 240px; padding: 40px; width: calc(100% - 240px);
        }

        .card {
            background: white; border-radius: 18px;
            box-shadow: 0 20px 40px rgba(16,24,40,0.08);
            padding: 30px;
        }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }
        .card-title { font-size: 24px; font-weight: 700; color: #1f2937; }
        .table {
            width: 100%; border-collapse: collapse;
            border-radius: 16px; overflow: hidden;
        }
        .table thead { background: #63c174; color: white; }
        th, td { padding: 16px 18px; text-align: left; font-size: 14px; }
        th.checkbox, td.checkbox { width: 50px; text-align: center; }
        .table tbody tr:nth-child(odd) { background: #f2fdf2; }
        .table tbody tr:nth-child(even) { background: #e6f3e8; }

        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-info img {
            width: 48px; height: 48px; border-radius: 50%; object-fit: cover;
            border: 2px solid white; box-shadow: 0 6px 12px rgba(15,23,42,0.25);
        }
        .role-pill {
            background: white; color: #065f46; border: 1px solid #d1fae5;
            padding: 6px 14px; border-radius: 999px; font-weight: 600;
            font-size: 13px;
        }

        .action-btn {
            border: none; background: transparent; cursor: pointer;
            font-size: 20px; margin-right: 12px;
        }
        .action-view { color: #2563eb; }
        .action-delete { color: #ef4444; }
        .action-delete:hover { color: #b91c1c; }

        .alert {
            margin-bottom: 20px; padding: 14px 18px;
            border-radius: 12px; font-size: 14px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .modal {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,0.65); z-index: 2000;
            align-items: center; justify-content: center;
        }
        .modal-content {
            background: white; width: 90%; max-width: 480px;
            border-radius: 20px; padding: 30px;
            box-shadow: 0 25px 60px rgba(15,23,42,0.35);
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(-25px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { margin: 0; font-size: 20px; color: #1f2937; }
        .close-btn {
            border: none; background: none; font-size: 22px; color: #9ca3af; cursor: pointer;
        }
        .modal-body { display: grid; gap: 14px; }
        .detail-label { font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; }
        .detail-value { font-size: 15px; color: #111827; font-weight: 600; }

        @media (max-width: 992px) {
            .sidebar { position: static; width: 100%; height: auto; border-right: none; border-bottom: 1px solid #e5e7eb; }
            .content-area { margin-left: 0; width: 100%; padding: 20px; }
            .main-layout { flex-direction: column; }
            .top-header { flex-wrap: wrap; gap: 10px; height: auto; padding: 15px 20px; }
            .header-search { order: 3; width: 100%; }
        }
    </style>
</head>
<body>

    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo">
            <h3 class="parish-name">San Jose Parish Laligan</h3>
        </div>
        <div class="header-search">
            <div class="search-box" style="position:relative;">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Search users, roles...">
            </div>
        </div>
        <div class="header-right">
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="badge">3</span>
            </div>
            <div class="profile-pill">
                <span><?= htmlspecialchars($user_fullname) ?></span>
                <img src="https://via.placeholder.com/44/059669/ffffff?text=<?= substr($user_fullname,0,1) ?>" alt="Admin">
            </div>
        </div>
    </div>

    <div class="main-layout">
        <div class="sidebar">
            <div class="logo">
                <img src="../images/logo.png" alt="SJPL Logo">
            </div>
            <div class="nav-menu">
                <a class="nav-item" href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
                <a class="nav-item" href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>
                <a class="nav-item" href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar</a>
                <a class="nav-item" href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a class="nav-item" href="financial.php"><i class="fas fa-coins"></i> Financial</a>
                <a class="nav-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                <a class="nav-item active" href="userManagement.php"><i class="fas fa-users-cog"></i> User Management</a>
                <a class="nav-item" href="support.php"><i class="fas fa-question-circle"></i> Help & Support</a>
            </div>
        </div>

        <div class="content-area">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">User Management</div>
                        <p style="color:#6b7280;">Review, view and manage parish accounts</p>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button style="background:white; border:1px solid #d1d5db; border-radius:10px; padding:10px 16px; font-weight:600; color:#374151; cursor:pointer;"><i class="fas fa-filter"></i> Filter</button>
                        <button style="background:var(--green); border:none; border-radius:10px; padding:10px 16px; font-weight:600; color:white; cursor:pointer;"><i class="fas fa-user-plus"></i> Add User</button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <table class="table">
                    <thead>
                        <tr>
                            <th class="checkbox"><input type="checkbox"></th>
                            <th>Name</th>
                            <th>Account</th>
                            <th>Email</th>
                            <th style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="checkbox"><input type="checkbox"></td>
                                <td>
                                    <div class="user-info">
                                        <img src="<?= $u['profile_pic'] ? '../uploads/'.$u['profile_pic'] : 'https://via.placeholder.com/52/059669/ffffff?text='.substr($u['fullname'],0,1) ?>" alt="<?= htmlspecialchars($u['fullname']) ?>">
                                        <div>
                                            <div style="font-weight:600;"><?= htmlspecialchars($u['fullname']) ?></div>
                                            <small style="color:#6b7280;">Joined <?= date('M d, Y', strtotime($u['created_at'] ?? 'now')) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="role-pill"><?= ucfirst($u['role']) ?></span></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <button class="action-btn action-view" title="View" 
                                        data-name="<?= htmlspecialchars($u['fullname']) ?>"
                                        data-email="<?= htmlspecialchars($u['email']) ?>"
                                        data-role="<?= ucfirst($u['role']) ?>"
                                        data-phone="<?= htmlspecialchars($u['phone'] ?? 'N/A') ?>"
                                        onclick="openModal(this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="action-btn action-delete" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Details</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div>
                    <div class="detail-label">Name</div>
                    <div class="detail-value" id="modalName"></div>
                </div>
                <div>
                    <div class="detail-label">Email</div>
                    <div class="detail-value" id="modalEmail"></div>
                </div>
                <div>
                    <div class="detail-label">Role</div>
                    <div class="detail-value" id="modalRole"></div>
                </div>
                <div>
                    <div class="detail-label">Phone</div>
                    <div class="detail-value" id="modalPhone"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(button) {
            document.getElementById('modalName').innerText = button.dataset.name;
            document.getElementById('modalEmail').innerText = button.dataset.email;
            document.getElementById('modalRole').innerText = button.dataset.role;
            document.getElementById('modalPhone').innerText = button.dataset.phone || 'Not provided';
            document.getElementById('viewModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        document.addEventListener('keyup', function(event) {
            if (event.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>

