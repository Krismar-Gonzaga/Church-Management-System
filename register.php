<?php
session_start();
require_once 'Database/DBconnection.php';

$errors = [];
$success = '';

// AUTO-CREATE users table with updated role options
try {
    $pdo->query("SELECT 1 FROM users LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist → CREATE IT + insert admin
    $sql = "
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','finance','staff','priest','member') DEFAULT 'member',
        phone VARCHAR(15) NULL,
        address TEXT NULL,
        profile_pic VARCHAR(255) DEFAULT 'default.jpg',
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    


    CREATE TABLE IF NOT EXISTS announcements (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255),
        message TEXT,
        posted_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (posted_by) REFERENCES users(id)
    );

    CREATE TABLE IF NOT EXISTS appointment_requests (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        priest_id INT,
        full_name VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(50),
        chapel VARCHAR(100),
        priest VARCHAR(100),
        type VARCHAR(100),
        purpose TEXT,
        preferred_datetime DATETIME,
        notes TEXT,
        address TEXT,
        status VARCHAR(50) DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (priest_id) REFERENCES users(id)
    );














    INSERT INTO users (fullname, email, password, role) VALUES 
    ('SJPL Administrator', 'admin@sjpl.org', 'admin123', 'admin');
    
   
    ALTER TABLE users 
    ADD COLUMN occupation VARCHAR(100) DEFAULT NULL,
    ADD COLUMN baptism_date DATE DEFAULT NULL,
    ADD COLUMN confirmation_date DATE DEFAULT NULL,
    ADD COLUMN first_communion_date DATE DEFAULT NULL,
    ADD COLUMN marriage_date DATE DEFAULT NULL,
    ADD COLUMN last_sacrament_received VARCHAR(50) DEFAULT NULL,
    ADD COLUMN sacrament_notes TEXT DEFAULT NULL;

    ALTER TABLE users 
    ADD COLUMN updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP;
    ";
    $pdo->exec($sql);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $role     = trim($_POST['role'] ?? 'member'); // Default to member if not selected

    // Validation
    if (empty($fullname)) $errors[] = "Full Name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/^09\d{9}$/', $email))
        $errors[] = "Valid Email or Philippine mobile number (09xxxxxxxxx) is required";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm) $errors[] = "Passwords do not match";
    if (!in_array($role, ['priest', 'member'])) $errors[] = "Please select a valid role";

    // Check if email/phone already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Account already exists with this email/phone";
        }
    }

    // Save to database
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$fullname, $email, $hashed, $role])) {
            $success = "Registration successful as " . ucfirst($role) . "! You can now <a href='login.php' style='color:#059669;font-weight:600;'>login here</a>";
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .container {
            background: #fff;
            width: 90%;
            max-width: 1100px;
            border-radius: 20px;
            margin-top: 100px;
            margin-bottom: 100px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            display: grid;
            grid-template-columns: 1fr 1.1fr;
        }
        .left, .right {
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .left { background: #ffffff; }
        .right {
            background: linear-gradient(rgba(5, 150, 105, 0.92), rgba(6, 95, 70, 0.98)),
                        url('../images/priestImage.png') center/cover;
            color: white;
            position: relative;
            text-align: center;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            height: 70px;
        }
        h1 {
            text-align: center;
            font-size: 36px;
            color: #065f46;
            margin-bottom: 30px;
            font-family: 'Playfair Display', serif;
        }
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group input, .input-group select {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
            background-color: white;
            appearance: none;
        }
        .input-group input:focus, .input-group select:focus {
            border-color: #059669;
            box-shadow: 0 0 0 4px rgba(5,150,105,0.15);
        }
        .input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
            z-index: 1;
        }
        .role-select-container {
            position: relative;
        }
        .role-select-container::after {
            content: '▼';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
            pointer-events: none;
        }
        .role-option {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .role-icon {
            width: 20px;
            text-align: center;
        }
        .btn-register {
            width: 100%;
            padding: 15px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-register:hover { background: #047857; }

        .social-btn {
            display: flex; align-items: center; justify-content: center;
            gap: 12px; width: 100%; padding: 12px; margin: 10px 0;
            border: 1px solid #ddd; border-radius: 12px;
            background: #fff; cursor: pointer; font-size: 15px;
        }
        .social-btn:hover { background: #f8f8f8; }

        .login-link {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
        }
        .login-link a { color: #059669; font-weight: 600; text-decoration: none; }

        .error, .success {
            padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px;
        }
        .error { background: #fee2e2; color: #991b1b; }
        .success { background: #d4edda; color: #155724; }

        .right h2 {
            font-size: 48px; line-height: 1.2; margin: 20px 0; font-weight: 800;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.5);
        }
        .right p { font-size: 18px; margin-bottom: 10px; opacity: 0.95; }
        .priest { margin-top: 20px; font-weight: 600; }

        @media (max-width: 868px) {
            .container { grid-template-columns: 1fr; }
            .right { display: none; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Left: Register Form -->
    <div class="left">
        <div class="logo">
            <img src="../images/logo.png" alt="SJPL Logo">        
        </div>

        <h1>REGISTER</h1>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="error"><?= implode('<br>', $errors) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="fullname" placeholder="Full Name" value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
            </div>

            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="text" name="email" placeholder="Email / Phone" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="input-group role-select-container">
                <i class="fas fa-user-tag"></i>
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="priest" <?= ($_POST['role'] ?? '') === 'priest' ? 'selected' : '' ?>>
                        <span class="role-option">
                            <span class="role-icon">⛪</span> Priest
                        </span>
                    </option>
                    <option value="member" <?= ($_POST['role'] ?? '') === 'member' ? 'selected' : '' ?>>
                        <span class="role-option">
                            <span class="role-icon">🙏</span> Member
                        </span>
                    </option>
                </select>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm" placeholder="Confirm Password" required>
            </div>

            <button type="submit" class="btn-register">Register</button>
        </form>

        <div style="text-align:center; margin:30px 0 15px; color:#666; font-size:14px;">Register with Others</div>
        <button class="social-btn">
            <img src="https://www.google.com/favicon.ico" width="20" alt=""> Register with Google
        </button>
        <button class="social-btn">
            <img src="https://upload.wikimedia.org/wikipedia/commons/5/51/Facebook_f_logo_%282019%29.svg" width="20" alt=""> Register with Facebook
        </button>

        <div class="login-link">
            I have Account <a href="login.php">Click</a> to login.
        </div>
    </div>

    <!-- Right: Inspirational Panel -->
    <div class="right">
        <h2>Join Our Community</h2>
        <p>As a <strong>Priest</strong>, you'll have access to:</p>
        <p>• Manage appointments<br>• View member details<br>• Schedule services</p>
        <p>As a <strong>Member</strong>, you'll have access to:</p>
        <p>• Make appointments<br>• View announcements<br>• Access resources</p>
    </div>
</div>

</body>
</html>