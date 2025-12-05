<?php
session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

$user_fullname = $_SESSION['user'] ?? 'Member';
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'member';

// Get all available priests from users table where role = 'priest'
$priests_query = "SELECT id, fullname, profile_pic FROM users WHERE role = 'priest' AND is_active = 1 ORDER BY fullname";
$priests_stmt = $pdo->prepare($priests_query);
$priests_stmt->execute();
$priests = $priests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Collect and validate form data
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $chapel = trim($_POST['chapel'] ?? '');
    $priest_id = isset($_POST['priest_id']) && $_POST['priest_id'] !== '' ? (int)$_POST['priest_id'] : null;
    $type = trim($_POST['type'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $preferred_datetime = trim($_POST['preferred_datetime'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($chapel)) $errors[] = "Chapel/Parish is required";
    if (empty($type)) $errors[] = "Appointment type is required";
    if (empty($purpose)) $errors[] = "Purpose/Intention is required";
    if (empty($preferred_datetime)) $errors[] = "Preferred date and time is required";
    
    // Validate date is not in the past
    if (!empty($preferred_datetime) && strtotime($preferred_datetime) < time()) {
        $errors[] = "Preferred date and time cannot be in the past";
    }
    
    // If priest_id is provided, verify it's a valid priest
    if ($priest_id !== null) {
        $check_priest = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'priest'");
        $check_priest->execute([$priest_id]);
        if (!$check_priest->fetch()) {
            $errors[] = "Selected priest is not valid";
        }
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Get priest name if priest_id is provided
            $priest_name = '';
            if ($priest_id !== null) {
                $get_priest_name = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
                $get_priest_name->execute([$priest_id]);
                $priest_data = $get_priest_name->fetch();
                $priest_name = $priest_data['fullname'] ?? '';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO appointment_requests 
                (user_id, priest_id, full_name, email, phone, chapel, priest, type, purpose, preferred_datetime, notes, address, status, requested_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $priest_id,
                $full_name,
                $email,
                $phone,
                $chapel,
                $priest_name,
                $type,
                $purpose,
                $preferred_datetime,
                $notes,
                $address
            ]);
            
            header("Location: appointments.php?message=requested");
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
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

        .content-wrapper { margin-left: 500px; padding: 40px; max-width: 2000px; }

        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 30px; color: #1e293b; }

        /* Form Styles */
        .request-form-container {
            background: white;
            border-radius: 20px;            
            padding: 40px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }

        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .form-header h2 {
            color: #065f46;
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-header p {
            color: #64748b;
            font-size: 15px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }

        .form-group label.required:after {
            content: " *";
            color: #ef4444;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .error-message {
            color: #ef4444;
            font-size: 13px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .error-container {
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 25px;
            color: #991b1b;
        }

        .error-container ul {
            margin: 0;
            padding-left: 20px;
        }

        .error-container li {
            margin-bottom: 5px;
        }

        /* Priest Selection Styles */
        .priest-selection {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .priest-option {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .priest-option:hover {
            border-color: var(--green);
            background: var(--light-green);
        }

        .priest-option.selected {
            border-color: var(--green);
            background: var(--light-green);
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
        }

        .priest-option input[type="radio"] {
            display: none;
        }

        .priest-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 3px solid #e2e8f0;
        }

        .priest-option.selected .priest-avatar {
            border-color: var(--green);
        }

        .priest-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .no-priests {
            padding: 30px;
            text-align: center;
            background: #f8fafc;
            border-radius: 12px;
            color: #64748b;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-submit, .btn-cancel {
            padding: 14px 30px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            border: none;
        }

        .btn-submit {
            background: var(--green);
            color: white;
        }

        .btn-submit:hover {
            background: var(--green-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5,150,105,0.2);
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        .help-text {
            font-size: 13px;
            color: #64748b;
            margin-top: 5px;
            font-style: italic;
        }

        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
        }

        .info-box h4 {
            color: #0369a1;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box ul {
            color: #475569;
            padding-left: 20px;
            margin: 0;
        }

        .info-box li {
            margin-bottom: 8px;
            font-size: 14px;
        }

        @media (max-width: 992px) {
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            .priest-selection {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
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
                <input type="text" placeholder="Search..." disabled>
            </div>
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
    </div>

    <!-- SIDEBAR -->
    <div class="main-layout">
        <div class="sidebar">
            <div class="nav-menu" style="margin-top:20px;">
                <a href="dashboard.php"><div class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</div></a>
                <a href="announcements.php"><div class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</div></a>
                <a href="calendar.php"><div class="nav-item"><i class="fas fa-calendar"></i> Calendar</div></a>
                <a href="appointments.php"><div class="nav-item"><i class="fas fa-clock"></i> My Appointments</div></a>
                <a href="requestAppointments.php"><div class="nav-item active"><i class="fas fa-plus-circle"></i> Request Appointment</div></a>
                <a href="financial.php"><div class="nav-item"><i class="fas fa-coins"></i> Financial</div></a>
                <a href="profile.php"><div class="nav-item"><i class="fas fa-user"></i> My Profile</div></a>
                <a href="support.php"><div class="nav-item"><i class="fas fa-question-circle"></i> Help & Support</div></a>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="content-wrapper">
            <h1 class="page-title">Request New Appointment</h1>

            <?php if (!empty($errors)): ?>
                <div class="error-container">
                    <strong>Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="request-form-container">
                <div class="form-header">
                    <h2><i class="fas fa-calendar-plus"></i> Appointment Request Form</h2>
                    <p>Fill out all required fields to request an appointment. Our staff will review and confirm your appointment.</p>
                </div>

                <form method="POST" action="">
                    <!-- Personal Information -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="color: #065f46; font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-user"></i> Personal Information
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name" class="required">Full Name</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['full_name'] ?? $user_fullname) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email" class="required">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone" class="required">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                                <div class="help-text">Format: 0912-345-6789 or 9123456789</div>
                            </div>
                            <div class="form-group">
                                <label for="chapel" class="required">Chapel / Parish</label>
                                <select id="chapel" name="chapel" class="form-control" required>
                                    <option value="">Select Chapel</option>
                                    <option value="San Jose Parish" <?= (($_POST['chapel'] ?? '') == 'San Jose Parish') ? 'selected' : '' ?>>San Jose Parish (Main)</option>
                                    <option value="San Roque Chapel" <?= (($_POST['chapel'] ?? '') == 'San Roque Chapel') ? 'selected' : '' ?>>San Roque Chapel</option>
                                    <option value="San Miguel Chapel" <?= (($_POST['chapel'] ?? '') == 'San Miguel Chapel') ? 'selected' : '' ?>>San Miguel Chapel</option>
                                    <option value="San Isidro Chapel" <?= (($_POST['chapel'] ?? '') == 'San Isidro Chapel') ? 'selected' : '' ?>>San Isidro Chapel</option>
                                    <option value="Other" <?= (($_POST['chapel'] ?? '') == 'Other') ? 'selected' : '' ?>>Other (Specify in notes)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Appointment Details -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="color: #065f46; font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-calendar-alt"></i> Appointment Details
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="type" class="required">Appointment Type</label>
                                <select id="type" name="type" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="baptism" <?= (($_POST['type'] ?? '') == 'baptism') ? 'selected' : '' ?>>Baptism</option>
                                    <option value="wedding" <?= (($_POST['type'] ?? '') == 'wedding') ? 'selected' : '' ?>>Wedding</option>
                                    <option value="mass_intention" <?= (($_POST['type'] ?? '') == 'mass_intention') ? 'selected' : '' ?>>Mass Intention</option>
                                    <option value="confession" <?= (($_POST['type'] ?? '') == 'confession') ? 'selected' : '' ?>>Confession</option>
                                    <option value="blessing" <?= (($_POST['type'] ?? '') == 'blessing') ? 'selected' : '' ?>>Blessing/House Blessing</option>
                                    <option value="counseling" <?= (($_POST['type'] ?? '') == 'counseling') ? 'selected' : '' ?>>Counseling</option>
                                    <option value="other" <?= (($_POST['type'] ?? '') == 'other') ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="preferred_datetime" class="required">Preferred Date & Time</label>
                                <input type="datetime-local" id="preferred_datetime" name="preferred_datetime" 
                                       class="form-control" value="<?= htmlspecialchars($_POST['preferred_datetime'] ?? '') ?>" 
                                       min="<?= date('Y-m-d\TH:i') ?>" required>
                                <div class="help-text">Select a date and time at least 24 hours from now</div>
                            </div>
                        </div>

                        <!-- Priest Selection -->
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="priest_selection">Select Preferred Priest (Optional)</label>
                            <?php if (!empty($priests)): ?>
                                <div class="priest-selection">
                                    <div class="priest-option" onclick="selectPriest(null)">
                                        <input type="radio" name="priest_id" value="" id="priest_none" 
                                               <?= empty($_POST['priest_id']) ? 'checked' : '' ?>>
                                        <div class="priest-avatar" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-question" style="font-size: 24px; color: #64748b;"></i>
                                        </div>
                                        <div class="priest-name">No Preference</div>
                                        <small style="color: #64748b;">Let us assign a priest</small>
                                    </div>
                                    
                                    <?php foreach ($priests as $priest): ?>
                                        <div class="priest-option" onclick="selectPriest(<?= $priest['id'] ?>)">
                                            <input type="radio" name="priest_id" value="<?= $priest['id'] ?>" 
                                                   id="priest_<?= $priest['id'] ?>"
                                                   <?= ($_POST['priest_id'] ?? '') == $priest['id'] ? 'checked' : '' ?>>
                                            <?php if (!empty($priest['profile_pic']) && $priest['profile_pic'] != 'default.jpg'): ?>
                                                <img src="../uploads/profile_pics/<?= $priest['profile_pic'] ?>" 
                                                     alt="<?= htmlspecialchars($priest['fullname']) ?>" 
                                                     class="priest-avatar">
                                            <?php else: ?>
                                                <div class="priest-avatar" style="background: #059669; display: flex; align-items: center; justify-content: center;">
                                                    <span style="color: white; font-size: 20px; font-weight: bold;">
                                                        <?= substr($priest['fullname'], 0, 1) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="priest-name"><?= htmlspecialchars($priest['fullname']) ?></div>
                                            <small style="color: #059669;"><i class="fas fa-user-tie"></i> Priest</small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-priests">
                                    <i class="fas fa-user-tie" style="font-size: 48px; color: #cbd5e1; margin-bottom: 15px;"></i>
                                    <p>No priests are currently available for selection.</p>
                                    <p style="font-size: 14px;">We will assign a priest based on availability.</p>
                                    <input type="hidden" name="priest_id" value="">
                                </div>
                            <?php endif; ?>
                            <div class="help-text">If you have no preference, we will assign a priest based on availability</div>
                        </div>

                        <div class="form-group">
                            <label for="purpose" class="required">Purpose / Intention</label>
                            <textarea id="purpose" name="purpose" class="form-control" required
                                      placeholder="Please describe the purpose of your appointment..."><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
                            <div class="help-text">For Mass Intentions: Include names of persons to pray for</div>
                        </div>

                        <div class="form-group">
                            <label for="address">Address (For Blessing/House Visits)</label>
                            <textarea id="address" name="address" class="form-control" 
                                      placeholder="If this is for a house blessing or visit, please provide the complete address..."><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="notes">Additional Notes / Special Requests</label>
                            <textarea id="notes" name="notes" class="form-control" 
                                      placeholder="Any additional information or special requests..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Information Box -->
                    <div class="info-box">
                        <h4><i class="fas fa-info-circle"></i> Important Information</h4>
                        <ul>
                            <li>Appointments are subject to priest availability</li>
                            <li>You will receive a confirmation email within 24-48 hours</li>
                            <li>For urgent requests, please call the parish office</li>
                            <li>Please arrive 10-15 minutes before your scheduled time</li>
                            <li>Cancellations should be made at least 24 hours in advance</li>
                        </ul>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="appointments.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Appointment Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Priest selection functionality
        function selectPriest(priestId) {
            // Remove selected class from all priest options
            document.querySelectorAll('.priest-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            const clickedOption = priestId === null 
                ? document.querySelector('.priest-option[onclick="selectPriest(null)"]')
                : document.querySelector(`.priest-option[onclick="selectPriest(${priestId})"]`);
            
            if (clickedOption) {
                clickedOption.classList.add('selected');
                // Check the corresponding radio button
                const radioButton = clickedOption.querySelector('input[type="radio"]');
                if (radioButton) {
                    radioButton.checked = true;
                }
            }
        }
        
        // Initialize selected state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const checkedRadio = document.querySelector('input[name="priest_id"]:checked');
            if (checkedRadio) {
                const priestId = checkedRadio.value;
                if (priestId === '') {
                    selectPriest(null);
                } else {
                    selectPriest(parseInt(priestId));
                }
            }
            
            // Set minimum date to today
            const now = new Date();
            const minDate = now.toISOString().slice(0, 16);
            document.getElementById('preferred_datetime').min = minDate;
            
            // Add 24 hours for minimum time
            now.setHours(now.getHours() + 24);
            const minDate24 = now.toISOString().slice(0, 16);
            
            // If the form was submitted with an error, keep the selected value
            const preferredDatetime = document.getElementById('preferred_datetime').value;
            if (!preferredDatetime) {
                document.getElementById('preferred_datetime').min = minDate24;
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const chapel = document.getElementById('chapel').value;
            const type = document.getElementById('type').value;
            const purpose = document.getElementById('purpose').value.trim();
            const datetime = document.getElementById('preferred_datetime').value;
            
            let errors = [];
            
            if (!fullName) errors.push('Full name is required');
            if (!email || !email.includes('@')) errors.push('Valid email is required');
            if (!phone) errors.push('Phone number is required');
            if (!chapel) errors.push('Please select a chapel/parish');
            if (!type) errors.push('Please select an appointment type');
            if (!purpose) errors.push('Please describe the purpose of your appointment');
            if (!datetime) errors.push('Please select a preferred date and time');
            
            if (datetime) {
                const selectedDate = new Date(datetime);
                const now = new Date();
                if (selectedDate < now) {
                    errors.push('Selected date and time cannot be in the past');
                }
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
            }
        });
    </script>
</body>
</html>