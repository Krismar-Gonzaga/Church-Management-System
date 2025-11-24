<?php
session_start();
require_once '../Database/DBconnection.php'; // This connects to your sjpl_church database

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        try {
            // Check if user exists (email or phone) and is staff/admin/finance
            $stmt = $pdo->prepare("SELECT id, fullname, password, role FROM users WHERE email = ? AND role IN ('admin', 'staff', 'finance') LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Success! Set session
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user']     = $user['fullname'];
                $_SESSION['role']     = $user['role'];

                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Invalid email or password";
            }
        } catch (Exception $e) {
            $error = "Login failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJPL | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .container {
            background: #fff;
            width: 90%;
            max-width: 1000px;
            border-radius: 20px;
            margin-top: 100px;
            margin-bottom: 100px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .left, .right {
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .left {
            background: #ffffff;
        }
        .right {
            background: linear-gradient(135deg, #065f46, #059669);
            position: relative;
        }
        .right::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('../images/priestImage.png') center/cover;
            opacity: 0.2;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            height: 70px;
        }
        h1 {
            text-align: center;
            font-size: 32px;
            margin-bottom: 40px;
            color: #065f46;
            font-family: 'Playfair Display', serif;
        }
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
        }
        .input-group input:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        .input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
        }
        .btn-login {
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
        }
        .btn-login:hover {
            background: #047857;
        }
        .social-login {
            margin-top: 30px;
        }
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid #ddd;
            border-radius: 12px;
            background: #fff;
            cursor: pointer;
            font-size: 15px;
            transition: 0.3s;
        }
        .social-btn:hover {
            background: #f7f7f7;
        }
        .register-link {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
        }
        .register-link a {
            color: #059669;
            text-decoration: none;
            font-weight: 600;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            .right {
                display: none;
            }
            .left, .right { padding: 40px 30px; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Left: Login Form -->
    <div class="left">
        <div class="logo">
            <img src="../images/logo.png" alt="SJPL Logo">
        </div>

        <h1>LOGIN</h1>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="text" name="email" placeholder="Email / Phone" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit" class="btn-login">Login Now</button>
        </form>

        <div class="social-login">
            <div style="text-align:center; margin-bottom:15px; color:#666; font-size:14px;">Login with Others</div>
            
            <button class="social-btn">
                <img src="https://www.google.com/favicon.ico" width="20" alt="Google">
                Login with Google
            </button>
            
            <button class="social-btn">
                <img src="https://upload.wikimedia.org/wikipedia/commons/5/51/Facebook_f_logo_%282019%29.svg" width="20" alt="Facebook">
                Login with Facebook
            </button>
        </div>

        <div class="register-link">
            I have no Account <a href="register.php">Click</a> to register.
        </div>
    </div>

    <!-- Right: Decorative Side -->
    <div class="right"></div>
</div>

</body>
</html>

