<?php
// config.php - Database Connection and Auto-Table Creation for SJPL Church Management System
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
    // First, connect without database to create it if it doesn't exist
    $tempPdo = new PDO(
        "mysql:host=" . DB_HOST,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // Create database if it doesn't exist
    $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Now connect to the specific database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // Create tables automatically
    createTables($pdo);
    
    // Optional: Uncomment line below to test connection on first load
    // echo "<script>console.log('Database connected and tables created successfully!');</script>";

} catch (PDOException $e) {
    die("
        <div style='font-family: Arial; text-align:center; padding:50px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; margin:50px auto; max-width:600px; border-radius:10px;'>
            <h2>Database Connection Failed</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <hr>
            <small>Check your database name, username, password, and make sure MySQL is running.</small>
        </div>
    ");
}

/**
 * Function to create all required tables
 */
function createTables($pdo) {
    // 1. Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fullname VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','finance','staff','priest','member') DEFAULT 'member',
            phone VARCHAR(15) NULL,
            zone VARCHAR(100),
            address TEXT NULL,
            profile_pic VARCHAR(255) DEFAULT 'default.jpg',
            is_active TINYINT(1) DEFAULT 1,
            occupation VARCHAR(100) DEFAULT NULL,
            baptism_date DATE DEFAULT NULL,
            confirmation_date DATE DEFAULT NULL,
            first_communion_date DATE DEFAULT NULL,
            marriage_date DATE DEFAULT NULL,
            last_sacrament_received VARCHAR(50) DEFAULT NULL,
            sacrament_notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // 2. Create appointment_requests table
    $pdo->exec("
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
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (priest_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // 3. Create announcements table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcements (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255),
            message TEXT,
            posted_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // 4. Create financial_transactions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS financial_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(100) NOT NULL,
            description VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type ENUM('income','expense') DEFAULT 'income',
            category VARCHAR(50) DEFAULT 'offering',
            transaction_date DATE NOT NULL,
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // 5. Create financial_history table (audit log)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS financial_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id INT,
            action ENUM('CREATE','UPDATE','DELETE') NOT NULL,
            old_data TEXT,
            new_data TEXT,
            performed_by INT,
            performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE,
            FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // 6. Create admin user if not exists
    createDefaultAdmin($pdo);
    
    // 7. Add indexes for better performance
    addIndexes($pdo);
}

/**
 * Function to create a default admin user
 */
function createDefaultAdmin($pdo) {
    $checkAdmin = $pdo->prepare("SELECT id FROM users WHERE email = 'admin@gmail.com'");
    $checkAdmin->execute();
    
    if ($checkAdmin->rowCount() == 0) {
        // Default admin password: Admin@123 (hashed)
        $hashedPassword = password_hash('Admin@123', PASSWORD_DEFAULT);
        
        $createAdmin = $pdo->prepare("
            INSERT INTO users (fullname, email, password, role, phone, is_active) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $createAdmin->execute([
            'System Administrator',
            'admin@gmail.com',
            $hashedPassword,
            'admin',
            '000-000-0000',
            1
        ]);
    }
}

/**
 * Function to add indexes for better query performance
 */
function addIndexes($pdo) {
    $indexes = [
        // Users table indexes
        "ALTER TABLE users ADD INDEX idx_email (email)",
        "ALTER TABLE users ADD INDEX idx_role (role)",
        "ALTER TABLE users ADD INDEX idx_is_active (is_active)",
        
        // Appointment requests indexes
        "ALTER TABLE appointment_requests ADD INDEX idx_user_id (user_id)",
        "ALTER TABLE appointment_requests ADD INDEX idx_priest_id (priest_id)",
        "ALTER TABLE appointment_requests ADD INDEX idx_status (status)",
        "ALTER TABLE appointment_requests ADD INDEX idx_preferred_datetime (preferred_datetime)",
        
        // Announcements indexes
        "ALTER TABLE announcements ADD INDEX idx_posted_by (posted_by)",
        "ALTER TABLE announcements ADD INDEX idx_created_at (created_at)",
        
        // Financial transactions indexes
        "ALTER TABLE financial_transactions ADD INDEX idx_created_by (created_by)",
        "ALTER TABLE financial_transactions ADD INDEX idx_type (type)",
        "ALTER TABLE financial_transactions ADD INDEX idx_category (category)",
        "ALTER TABLE financial_transactions ADD INDEX idx_transaction_date (transaction_date)",
        
        // Financial history indexes
        "ALTER TABLE financial_history ADD INDEX idx_transaction_id (transaction_id)",
        "ALTER TABLE financial_history ADD INDEX idx_performed_by (performed_by)",
        "ALTER TABLE financial_history ADD INDEX idx_action (action)",
        "ALTER TABLE financial_history ADD INDEX idx_performed_at (performed_at)",
    ];
    
    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Index might already exist, continue
            continue;
        }
    }
}

// Make PDO available globally
return $pdo;
?>