<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../Staff/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../Staff/login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Staff';

$faqs = [
    [
        'question' => 'How do I reset a member password?',
        'answer' => 'Open User Management, view the member, and click the reset password action.'
    ],
    [
        'question' => 'Where can I export financial reports?',
        'answer' => 'Navigate to Financial > Analytics and click on Export to CSV.'
    ],
    [
        'question' => 'How do I approve appointments?',
        'answer' => 'Go to Appointments, filter by Pending, then click Accept or Reject.'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Help & Support</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; margin:0; background:#f8fafc; color:#1f2937; }
        .top-header {
            position:fixed; top:0; left:0; right:0; height:80px;
            background:white; border-bottom:1px solid #e2e8f0;
            display:flex; align-items:center; justify-content:space-between;
            padding:0 40px; z-index:1000; box-shadow:0 4px 20px rgba(0,0,0,0.08);
        }
        .header-left { display:flex; align-items:center; gap:22px; }
        .header-left img { height:58px; }
        .header-search {
            flex:1; margin-right:20px; display:flex; justify-content:right; padding:0 30px;
        }
        .search-box { position:relative; width:100%; max-width:500px; }
        .search-box input {
            width:100%; padding:14px 50px 14px 48px; border:2px solid #e2e8f0;
            border-radius:16px; font-size:15px; background:#f8fafc; outline:none;
            transition:all 0.3s; box-shadow:0 4px 15px rgba(0,0,0,0.05);
        }
        .search-box input:focus { border-color:#059669; background:white; box-shadow:0 8px 25px rgba(5,150,105,0.15); }
        .search-icon { position:absolute; left:18px; top:50%; transform:translateY(-50%); color:#059669; font-size:18px; pointer-events:none; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .notification-bell { position: relative; font-size: 23px; color: #64748b; cursor: pointer; }
        .notification-bell .badge { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 10px; width: 19px; height: 19px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .user-profile { display: flex; align-items: center; gap: 12px; background: var(--light-green); padding: 10px 18px; border-radius: 14px; border: 1px solid #d1fae5; cursor: pointer; }
        .user-profile:hover { background: #d1fae5; }
        .user-profile img { width: 44px; height: 44px; border-radius: 50%; border: 2px solid var(--green); }
        .user-profile span { font-weight: 600; color: #065f46; }
        
        .main-layout { display:flex; margin-top:70px; }
        .sidebar { width:240px; background:white; border-right:1px solid #e5e7eb; min-height:calc(100vh - 70px); position:fixed; top:70px; bottom:0; }
        .nav-menu { padding:30px 20px; display:flex; flex-direction:column; gap:8px; }
        .nav-item { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:10px; color:#4b5563; text-decoration:none; }
        .nav-item.active, .nav-item:hover { background:#e3f5e5; color:#059669; }
        .content-area { margin-left:240px; padding:40px; width:calc(100% - 240px); }
        .card { background:white; border-radius:18px; padding:30px; box-shadow:0 20px 40px rgba(15,23,42,0.08); margin-bottom:24px; }
        .faq-item { border-bottom:1px solid #e5e7eb; padding:18px 0; }
        .faq-item:last-child { border-bottom:none; }
        .faq-question { font-weight:600; margin-bottom:6px; }
        .contact-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; }
        .contact-card { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:16px; padding:20px; }
        @media(max-width:992px){ .sidebar{position:static; width:100%; height:auto;} .content-area{margin-left:0; width:100%; padding:20px;} .main-layout{flex-direction:column;} }
    </style>
</head>
<body>
    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo">
            <h3 class="parish-name">San Jose Parish Laligan</h3>
            <style>
                .parish-name {
                    font-size: 22px;
                    color: #065f46;
                    font-weight: 700;
                }
            </style>
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
    <div class="main-layout">
        <div class="sidebar">
            <div class="nav-menu">
                <a class="nav-item" href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
                <a class="nav-item" href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>
                <a class="nav-item" href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar</a>
                <a class="nav-item" href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a class="nav-item" href="financial.php"><i class="fas fa-coins"></i> Financial</a>
                <a class="nav-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                <a class="nav-item active" href="support.php"><i class="fas fa-life-ring"></i> Help & Support</a>
            </div>
        </div>
        <div class="content-area">
            <div class="card">
                <h3 style="margin-top:0;">Frequently Asked Questions</h3>
                <?php foreach ($faqs as $faq): ?>
                    <div class="faq-item">
                        <div class="faq-question"><?= htmlspecialchars($faq['question']) ?></div>
                        <div class="faq-answer" style="color:#4b5563;"><?= htmlspecialchars($faq['answer']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card">
                <h3 style="margin-top:0;">Contact Channels</h3>
                <div class="contact-grid">
                    <div class="contact-card">
                        <h4 style="margin:0 0 6px;">Parish Office</h4>
                        <p style="margin:0; color:#065f46;">+63 917 123 4567</p>
                        <small>Mon - Fri, 8:00 AM - 5:00 PM</small>
                    </div>
                    <div class="contact-card">
                        <h4 style="margin:0 0 6px;">Email Support</h4>
                        <p style="margin:0; color:#065f46;">support@sjpl.org</p>
                        <small>We respond within 24 hours</small>
                    </div>
                    <div class="contact-card">
                        <h4 style="margin:0 0 6px;">Messenger</h4>
                        <p style="margin:0; color:#065f46;">fb.com/sjplchurch</p>
                        <small>Chat with our coordinator</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

