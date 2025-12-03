<?php
// config.php - Database Connection for SJPL Church Management System
// Secure PDO Connection with Error Handling

$host = 'localhost';        // Usually 'localhost' on shared hosting or XAMPP
$dbname = 'sjpl_church';    // Your database name (must match what you created)
$username = 'root';         // Default XAMPP/WAMP: root | Hosting: your cPanel username
$password = '';             // Default XAMPP/WAMP: empty | Hosting: your DB password

// Optional: Change these for production (highly recommended)
define('DB_HOST', $host);
define('DB_NAME', $dbname);
define('DB_USER', $username);
define('DB_PASS', $password);

try {
    // Create PDO instance
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Show errors during dev
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Optional: Uncomment line below to test connection on first load
    // echo "<script>console.log('Database connected successfully!');</script>";

} catch (PDOException $e) {
    // In production, hide detailed errors from users
    // For now (development), show the error
    die("
        <div style='font-family: Arial; text-align:center; padding:50px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; margin:50px auto; max-width:600px; border-radius:10px;'>
            <h2>Database Connection Failed</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <hr>
            <small>Check your database name, username, password, and make sure MySQL is running.</small>
        </div>
    ");
}
?>