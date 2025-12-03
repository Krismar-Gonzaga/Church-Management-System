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

$user_fullname = $_SESSION['user'] ?? 'Administrator';
$user_id = $_SESSION['user_id'] ?? 0;

$message = '';
$message_type = '';

// Handle Add Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = trim($_POST['title'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($title) || empty($message_text)) {
        $message = 'Please fill in all fields';
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, message, posted_by, created_at) VALUES (?, ?, ?, NOW())");
            if ($stmt->execute([$title, $message_text, $user_id])) {
                $message = 'Announcement added successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to add announcement';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle Edit Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($title) || empty($message_text) || $id <= 0) {
        $message = 'Please fill in all fields';
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, message = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$title, $message_text, $id])) {
                $message = 'Announcement updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to update announcement';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle Delete Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = 'Announcement deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to delete announcement';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch ALL announcements with pagination support
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$total = $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$announcements = $pdo->query("
    SELECT a.*, u.fullname, u.profile_pic 
    FROM announcements a 
    JOIN users u ON a.posted_by = u.id 
    ORDER BY a.created_at DESC 
    LIMIT $limit OFFSET $offset
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Announcements</title>
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
            width: 100%;
            padding: 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            background: #f8fafc;
            font-size: 15px;
            transition: all 0.3s;
        }
        .search-box input:focus { border-color: #059669; background: white; box-shadow: 0 8px 25px rgba(5,150,105,0.15); }
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #059669; font-size: 18px; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .notification-bell { position: relative; font-size: 23px; color: #64748b; cursor: pointer; }
        .notification-bell .badge { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 10px; width: 19px; height: 19px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .user-profile { display: flex; align-items: center; gap: 12px; background: #f0fdf4; padding: 10px 18px; border-radius: 14px; cursor: pointer; border: 1px solid #d1fae5; }
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

        /* Content */
        .content-area {
            margin-left: 260px;
            flex: 1;
            padding: 40px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .page-title { font-size: 32px; font-weight: 700; color: #1e293b; }
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 12px 20px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .filter-bar input[type="date"] {
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
        }
        .filter-btn, .add-btn {
            background: #059669;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }
        .filter-btn:hover, .add-btn:hover {
            background: #047857;
        }

        /* Message Alerts */
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

        /* Announcement Cards */
        .announcements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }
        .announcement-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            transition: all 0.4s ease;
            border: 1px solid #f1f5f9;
            position: relative;
        }
        .announcement-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 40px rgba(5,150,105,0.18);
            border-color: #059669;
        }
        .card-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        .card-header img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        .poster-info h4 {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
        }
        .poster-info small {
            color: #64748b;
            font-size: 13px;
        }
        .card-body {
            padding: 24px;
            padding-bottom: 60px;
        }
        .announcement-title {
            font-size: 22px;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 16px;
            line-height: 1.4;
        }
        .announcement-message {
            color: #475569;
            line-height: 1.7;
            margin-bottom: 20px;
            font-size: 15px;
        }
        .announcement-date {
            font-size: 14px;
            color: #059669;
            font-weight: 600;
        }
        .comment-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 28px;
            color: #94a3b8;
        }
        .card-actions {
            position: absolute;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        .btn-edit, .btn-delete {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: 0.3s;
        }
        .btn-edit {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .btn-edit:hover {
            background: #bfdbfe;
        }
        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }
        .btn-delete:hover {
            background: #fecaca;
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
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 4px rgba(5,150,105,0.1);
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
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
        .btn-submit {
            padding: 12px 24px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-submit:hover {
            background: #047857;
        }

        @media (max-width: 1024px) {
            .content-area { padding: 20px; }
            .announcements-grid { grid-template-columns: 1fr; }
            .top-header { padding: 0 20px; flex-direction: column; height: auto; gap: 15px; padding: 15px 20px; }
            .header-search { margin: 0; width: 100%; max-width: none; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .filter-bar { width: 100%; }
        }
    </style>
</head>
<body>

    <!-- TOP HEADER -->
    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo">
            <h3 class="parish-name">San Jose Parish Laligan</h3 >
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
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Search announcements...">
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
                <a href="announcements.php"><div class="nav-item active"><i class="fas fa-bullhorn"></i> Announcements</div></a>
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
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h1 class="page-title">Announcements</h1>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="filter-bar">
                        <input type="date" value="2024-09-23">
                        <input type="date" value="2025-11-23">
                        <button class="filter-btn">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                    <button class="add-btn" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
            </div>

            <div class="announcements-grid">
                <?php foreach ($announcements as $a): ?>
                <div class="announcement-card">
                    <div class="card-header">
                        <img src="https://via.placeholder.com/48/6366f1/ffffff?text=<?= substr($a['fullname'],0,1) ?>" alt="Poster">
                        <div class="poster-info">
                            <h4><?= htmlspecialchars($a['fullname']) ?></h4>
                            <small>Parish Secretary</small>
                        </div>
                        <i class="fas fa-comment-dots comment-icon"></i>
                    </div>
                    <div class="card-body">
                        <div class="announcement-title">
                            <?= htmlspecialchars($a['title']) ?>
                        </div>
                        <div class="announcement-message">
                            <?= nl2br(htmlspecialchars($a['message'])) ?>
                        </div>
                        <div class="announcement-date">
                            <i class="fas fa-calendar-alt"></i> <?= date('F j, Y', strtotime($a['created_at'])) ?>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn-edit" onclick="openEditModal(<?= $a['id'] ?>, <?= json_encode($a['title']) ?>, <?= json_encode($a['message']) ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-delete" onclick="confirmDelete(<?= $a['id'] ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($announcements)): ?>
                <div style="grid-column: 1 / -1; text-align:center; padding:80px; color:#94a3b8;">
                    <i class="fas fa-bullhorn" style="font-size:60px; margin-bottom:20px; opacity:0.3;"></i>
                    <h3>No announcements yet</h3>
                    <p>Check back later for updates from the parish.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Announcement</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="add-title">Title</label>
                        <input type="text" id="add-title" name="title" required placeholder="Enter announcement title">
                    </div>
                    <div class="form-group">
                        <label for="add-message">Message</label>
                        <textarea id="add-message" name="message" required placeholder="Enter announcement message"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Add Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Announcement</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-title">Title</label>
                        <input type="text" id="edit-title" name="title" required placeholder="Enter announcement title">
                    </div>
                    <div class="form-group">
                        <label for="edit-message">Message</label>
                        <textarea id="edit-message" name="message" required placeholder="Enter announcement message"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Update Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete-id">
    </form>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.getElementById('add-title').focus();
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('add-title').value = '';
            document.getElementById('add-message').value = '';
        }

        function openEditModal(id, title, message) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-title').value = title;
            document.getElementById('edit-message').value = message;
            document.getElementById('editModal').style.display = 'block';
            document.getElementById('edit-title').focus();
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
                document.getElementById('delete-id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === editModal) {
                closeEditModal();
            }
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });
    </script>
</body>
</html>

