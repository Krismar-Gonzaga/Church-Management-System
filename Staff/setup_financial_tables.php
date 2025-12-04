<?php
// setup_financial_tables.php
// Run this file once to create the necessary tables

session_start();
require_once '../Database/DBconnection.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Please login first.");
}

echo "<h2>Setting up financial tables...</h2>";

try {
    // 1. Create financial_transactions table
    $sql = "
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
        updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($sql);
    echo "✓ Created financial_transactions table<br>";
    
    // 2. Add created_by column if it doesn't exist
    $check = $pdo->query("SHOW COLUMNS FROM financial_transactions LIKE 'created_by'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE financial_transactions ADD COLUMN created_by INT AFTER transaction_date");
        echo "✓ Added created_by column to financial_transactions<br>";
    }
    
    // 3. Create financial_history table
    $sql = "
    CREATE TABLE IF NOT EXISTS financial_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT,
        action ENUM('CREATE','UPDATE','DELETE') NOT NULL,
        old_data TEXT,
        new_data TEXT,
        performed_by INT,
        performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_transaction_id (transaction_id),
        INDEX idx_performed_at (performed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($sql);
    echo "✓ Created financial_history table<br>";
    
    // 4. Insert sample data if table is empty
    $count = $pdo->query("SELECT COUNT(*) as cnt FROM financial_transactions")->fetch()['cnt'];
    if ($count == 0) {
        $sample_data = [
            ['San Roque Chapel', 'Love Offering', 560.00, 'income', 'offering', date('Y-m-d'), 1],
            ['San Jose Parish', 'Offering', 360.00, 'income', 'offering', date('Y-m-d'), 1],
            ['San Michel Chapel', 'Tithes', 650.00, 'income', 'tithes', date('Y-m-d'), 1],
            ['Parish Maintenance', 'Electricity Bill', 1200.00, 'expense', 'utilities', date('Y-m-d'), 1],
            ['Church Renovation', 'Paint and Materials', 850.00, 'expense', 'maintenance', date('Y-m-d'), 1]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO financial_transactions (source, description, amount, type, category, transaction_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($sample_data as $data) {
            $stmt->execute($data);
        }
        echo "✓ Inserted sample data<br>";
    }
    
    echo "<h3 style='color: green;'>Setup completed successfully!</h3>";
    echo "<p><a href='financial_management.php'>Go to Financial Management</a></p>";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>