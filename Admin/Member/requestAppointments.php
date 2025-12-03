<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Member';
$user_id = $_SESSION['user_id'] ?? 0;

// ==================== FORM SUBMISSION & SAVE ====================
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Retrieve and sanitize
    $fullname           = trim($_POST['fullname'] ?? '');
    $chapel             = trim($_POST['chapel'] ?? '');
    $phone              = trim($_POST['phone'] ?? '');
    $email              = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $priest             = trim($_POST['priest'] ?? '');
    $type               = trim($_POST['type'] ?? '');
    $purpose            = trim($_POST['purpose'] ?? '');
    $preferred_datetime = $_POST['preferred_datetime'] ?? '';
    $notes              = trim($_POST['notes'] ?? '');
    $address            = trim($_POST['address'] ?? '');

    // Validation
    if (empty($fullname))           $errors[] = "Full name is required.";
    if (empty($chapel))             $errors[] = "Please select a chapel/parish.";
    if (empty($phone))              $errors[] = "Phone number is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($type))               $errors[] = "Please select appointment type.";
    if (empty($preferred_datetime)) $errors[] = "Preferred date & time is required.";
    if (strtotime($preferred_datetime) < strtotime('today')) {
        $errors[] = "Preferred date cannot be in the past.";
    }

    // If no errors → SAVE TO DATABASE
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO appointment_requests (
                    user_id, fullname, email, phone, chapel, priest, type, purpose,
                    preferred_datetime, notes, address, status, requested_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
                )
            ");

            $stmt->execute([
                $user_id,
                $fullname,
                $email,
                $phone,
                $chapel,
                $priest ?: NULL,
                $type,
                $purpose,
                $preferred_datetime,
                $notes,
                $address
            ]);

            $success = "Your appointment request has been submitted successfully! 
                        The parish office will review it shortly. God bless you!";

            // Clear form after success
            $_POST = [];

        } catch (Exception $e) {
            $errors[] = "Sorry, something went wrong. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Request Appointment</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }

        /* HEADER & SIDEBAR - SAME AS BEFORE */
        .top-header {
            position: fixed; top: 0; left: 0; right: 0; height: 80px;
            background: white; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 22px;
        }
        .header-left .logo-img {
            height: 58px;
        }
        .header-left img { height: 58px; }
        .header-left .priest-img { width: 54px; height: 54px; border-radius: 50%; border: 3px solid #059669; object-fit: cover; }
        .header-search { flex: 1; max-width: 500px; margin: 0 40px; }
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

        .content-area { margin-left: 260px; padding: 40px; flex: 1; background: #f8fafc; }
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 30px; color: #1e293b; }

        /* ALERTS */
        .alert-success {
            background: #d1fae5; color: #065f46; padding: 20px; border-radius: 16px;
            margin-bottom: 30px; text-align: center; font-weight: 600; border: 2px solid #a7f3d0;
            font-size: 17px;
        }
        .alert-error {
            background: #fee2e2; color: #991b1b; padding: 20px; border-radius: 16px;
            margin-bottom: 30px; border: 2px solid #fecaca;
        }

        /* FORM CARD */
        .form-card { 
            background: white; 
            border-radius: 20px; 
            padding: 40px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.08); 
            max-width: 2000px; 
            margin: 0 auto; 
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-weight: 600; color: #1e293b; margin-bottom: 8px; font-size: 15px; }
        input, select, textarea {
            padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 14px;
            font-size: 15px; background: #f8fafc; transition: all 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: #059669; background: white;
            box-shadow: 0 0 0 4px rgba(5,150,105,0.1);
        }
        textarea { min-height: 110px; resize: vertical; }
        .word-count { align-self: flex-end; font-size: 13px; color: #64748b; margin-top: 6px; }

        .submit-btn {
            background: #059669; color: white; padding: 16px 40px; border: none;
            border-radius: 16px; font-size: 18px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; gap: 12px; margin: 40px auto 0;
            box-shadow: 0 8px 25px rgba(5,150,105,0.3); transition: all 0.3s;
        }
        .submit-btn:hover {
            background: #047857; transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(5,150,105,0.4);
        }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .content-area { padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
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
        <div class="header-search">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Search...">
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

        <!-- MAIN CONTENT -->
        <div class="content-area">
            <h1 class="page-title">Request Appointment</h1>

            <!-- SUCCESS MESSAGE -->
            <?php if ($success): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle" style="font-size:28px; margin-right:12px; vertical-align:middle;"></i>
                    <?= nl2br(htmlspecialchars($success)) ?>
                </div>
            <?php endif; ?>

            <!-- ERROR MESSAGES -->
            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-triangle" style="margin-right:10px;"></i>
                    <strong>Please fix the following:</strong><br><br>
                    <ul style="margin:10px 0; padding-left:20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- FORM -->
            <div class="form-card">
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="fullname" value="<?= htmlspecialchars($_POST['fullname'] ?? $user_fullname) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Chapel/Parish</label>
                            <select name="chapel" required>
                                <option value="">Select Chapel</option>
                                <option value="main" <?= ($_POST['chapel'] ?? '') === 'main' ? 'selected' : '' ?>>Main Parish - San Jose</option>
                                <option value="stjude" <?= ($_POST['chapel'] ?? '') === 'stjude' ? 'selected' : '' ?>>St. Jude Chapel</option>
                                <option value="staana" <?= ($_POST['chapel'] ?? '') === 'staana' ? 'selected' : '' ?>>Sta. Ana Chapel</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="+63" required>
                        </div>
                        <div class="form-group">
                            <label>Priest (Optional)</label>
                            <select name="priest">
                                <option value="">Select Priest</option>
                                <option value="frdodz" <?= ($_POST['priest'] ?? '') === 'frdodz' ? 'selected' : '' ?>>Rev. Fr. Dodz Minguez</option>
                                <option value="frjohn" <?= ($_POST['priest'] ?? '') === 'frjohn' ? 'selected' : '' ?>>Rev. Fr. John Doe</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Purpose / Intention</label>
                            <textarea name="purpose" maxlength="250"><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
                            <small class="word-count">0/250 Words</small>
                        </div>

                        <div class="form-group">
                            <label>Appointment Type</label>
                            <select name="type" required>
                                <option value="">Select Type</option>
                                <option value="baptism" <?= ($_POST['type'] ?? '') === 'baptism' ? 'selected' : '' ?>>Baptism</option>
                                <option value="wedding" <?= ($_POST['type'] ?? '') === 'wedding' ? 'selected' : '' ?>>Wedding Mass</option>
                                <option value="mass" <?= ($_POST['type'] ?? '') === 'mass' ? 'selected' : '' ?>>Mass Intention</option>
                                <option value="confession" <?= ($_POST['type'] ?? '') === 'confession' ? 'selected' : '' ?>>Confession</option>
                                <option value="blessing" <?= ($_POST['type'] ?? '') === 'blessing' ? 'selected' : '' ?>>House/Church Blessing</option>
                                <option value="funeral" <?= ($_POST['type'] ?? '') === 'funeral' ? 'selected' : '' ?>>Funeral Mass</option>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label>Preferred Date & Time</label>
                            <input type="datetime-local" name="preferred_datetime" value="<?= htmlspecialchars($_POST['preferred_datetime'] ?? '') ?>" required>
                        </div>
                        <div class="form-group full">
                            <label>Notes / Special Requests</label>
                            <textarea name="notes" maxlength="250"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                            <small class="word-count">0/250 Words</small>
                        </div>
                        <div class="form-group full">
                            <label>Address (for blessings or visits)</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" placeholder="Complete address if needed">
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i>
                        Submit Appointment Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Word counter
        document.querySelectorAll('textarea').forEach(textarea => {
            const updateCount = () => {
                const words = textarea.value.trim().split(/\s+/).filter(w => w.length > 0). LENGTH;
                textarea.nextElementSibling.textContent = words + '/250 Words';
            };
            textarea.addEventListener('input', updateCount);
            updateCount();
        });
    </script>
</body>
</html>