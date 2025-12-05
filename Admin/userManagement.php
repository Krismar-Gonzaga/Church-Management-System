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
$user_id = $_SESSION['user_id'] ?? 0;
$message = '';
$message_type = '';

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'member';
    $phone = trim($_POST['phone'] ?? '');
    $zone = trim($_POST['zone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation
    $errors = [];
    if (strlen($fullname) < 2) $errors[] = 'Full name must be at least 2 characters';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    
    // Check if email exists
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->execute([$email]);
    if ($check_stmt->fetch()) $errors[] = 'Email already exists';
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (fullname, email, password, role, phone, zone, address, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$fullname, $email, $hashed_password, $role, $phone, $zone, $address])) {
                $message = 'User added successfully!';
                $message_type = 'success';
                header("Location: userManagement.php?message=added");
                exit;
            } else {
                $message = 'Failed to add user. Please try again.';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $edit_id = (int)($_POST['user_id'] ?? 0);
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'member';
    $phone = trim($_POST['phone'] ?? '');
    $zone = trim($_POST['zone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $civil_status = $_POST['civil_status'] ?? 'single';
    $birthday = $_POST['birthday'] ?? null;
    $occupation = trim($_POST['occupation'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    $errors = [];
    if (strlen($fullname) < 2) $errors[] = 'Full name must be at least 2 characters';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    // Check email uniqueness (excluding current user)
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->execute([$email, $edit_id]);
    if ($check_stmt->fetch()) $errors[] = 'Email already exists';
    
    if (empty($errors)) {
        try {
            // Handle birthday format
            if ($birthday && !empty($birthday)) {
                $birthday = date('Y-m-d', strtotime($birthday));
            } else {
                $birthday = null;
            }
            
            $stmt = $pdo->prepare("
                UPDATE users SET 
                fullname = ?, email = ?, role = ?, phone = ?, zone = ?, 
                address = ?, civil_status = ?, birthday = ?, occupation = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([$fullname, $email, $role, $phone, $zone, $address, $civil_status, $birthday, $occupation, $status, $edit_id])) {
                $message = 'User updated successfully!';
                $message_type = 'success';
                header("Location: userManagement.php?message=updated");
                exit;
            } else {
                $message = 'Failed to update user.';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// Handle Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delete_id = (int)($_POST['user_id'] ?? 0);

    if ($delete_id === $user_id) {
        $message = 'You cannot delete your own account.';
        $message_type = 'error';
    } elseif ($delete_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $message = 'User deleted successfully.';
                $message_type = 'success';
                header("Location: userManagement.php?message=deleted");
                exit;
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

// Handle Bulk Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $user_ids = $_POST['user_ids'] ?? [];
    $deleted_count = 0;
    
    foreach ($user_ids as $id) {
        $id = (int)$id;
        if ($id > 0 && $id !== $user_id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $deleted_count++;
            } catch (Exception $e) {
                // Continue with other deletions
            }
        }
    }
    
    if ($deleted_count > 0) {
        $message = "Successfully deleted $deleted_count user(s).";
        $message_type = 'success';
        header("Location: userManagement.php?message=bulk_deleted");
        exit;
    }
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $status_id = (int)($_POST['user_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    if ($status_id > 0 && $status_id !== $user_id) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$status, $status_id])) {
                $message = 'User status updated successfully!';
                $message_type = 'success';
                header("Location: userManagement.php?message=status_updated");
                exit;
            }
        } catch (Exception $e) {
            // Silently fail
        }
    }
}

// Handle message from redirect
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'added':
            $message = 'User added successfully!';
            $message_type = 'success';
            break;
        case 'updated':
            $message = 'User updated successfully!';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'User deleted successfully!';
            $message_type = 'success';
            break;
        case 'bulk_deleted':
            $message = 'Selected users deleted successfully!';
            $message_type = 'success';
            break;
        case 'status_updated':
            $message = 'User status updated successfully!';
            $message_type = 'success';
            break;
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$zone_filter = isset($_GET['zone']) ? $_GET['zone'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(fullname LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($role_filter !== 'all') {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($zone_filter !== 'all') {
    $where_conditions[] = "zone = ?";
    $params[] = $zone_filter;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Validate sort parameters
$allowed_sorts = ['fullname', 'email', 'role', 'created_at', 'status'];
$allowed_orders = ['asc', 'desc'];
$sort_by = in_array($sort_by, $allowed_sorts) ? $sort_by : 'created_at';
$sort_order = in_array($sort_order, $allowed_orders) ? $sort_order : 'desc';

// Get total count
$count_query = "SELECT COUNT(*) FROM users $where_clause";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();

// Get users with filters
$query = "SELECT * FROM users $where_clause ORDER BY $sort_by $sort_order";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN role = 'member' THEN 1 ELSE 0 END) as members,
        SUM(CASE WHEN role = 'priest' THEN 1 ELSE 0 END) as priests,
        SUM(CASE WHEN role = 'secretary' THEN 1 ELSE 0 END) as secretaries,
        SUM(CASE WHEN role = 'treasurer' THEN 1 ELSE 0 END) as treasurers,
        SUM(CASE WHEN is_active = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN is_active = 'suspended' THEN 1 ELSE 0 END) as suspended
    FROM users
";
$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get unique zones for filter
$zones = $pdo->query("SELECT DISTINCT zone FROM users WHERE zone IS NOT NULL AND zone != '' ORDER BY zone")->fetchAll(PDO::FETCH_COLUMN);

// Get roles for filter
$roles = ['admin', 'priest', 'secretary', 'treasurer', 'member'];
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
            --light-green: #f0fdf4;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #111827;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1f2937; }

        /* Top Header */
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
            z-index: 2000;
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
        .header-right { display: flex; align-items: center; gap: 20px; }
        .notification-bell { position: relative; font-size: 23px; color: #64748b; cursor: pointer; }
        .notification-bell .badge { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; font-size: 10px; width: 19px; height: 19px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .user-profile { display: flex; align-items: center; gap: 12px; background: #f0fdf4; padding: 10px 18px; border-radius: 14px; border: 1px solid #d1fae5; cursor: pointer; }
        .user-profile:hover { background: #d1fae5; }
        .user-profile img { width: 44px; height: 44px; border-radius: 50%; border: 2px solid #059669; }
        .user-profile span { font-weight: 600; color: #065f46; }

        .main-layout {
            display: flex; margin-top: 80px;
        }
        .sidebar {
            width: 260px; background: white; border-right: 1px solid #e2e8f0;
            min-height: calc(100vh - 80px); padding: 30px 0;
            position: fixed; top: 80px; bottom: 0;
            overflow-y: auto;
        }
        .nav-menu { display: flex; flex-direction: column; gap: 6px; padding: 0 25px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 18px; border-radius: 12px;
            color: #4b5563; cursor: pointer; text-decoration: none;
            transition: all 0.3s;
        }
        .nav-item.active, .nav-item:hover {
            background: var(--light-green); color: var(--green);
        }
        .nav-item i { width: 20px; font-size: 16px; }

        .content-area {
            margin-left: 260px; padding: 40px; width: calc(100% - 260px);
            background: #f8fafc;
        }

        /* Message Alerts */
        .alert {
            padding: 14px 20px; border-radius: 12px; margin-bottom: 20px;
            font-size: 14px; font-weight: 500; display: flex;
            justify-content: space-between; align-items: center;
        }
        .alert-success {
            background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;
        }
        .alert-close {
            background: none; border: none; font-size: 20px;
            cursor: pointer; color: inherit; opacity: 0.7;
        }
        .alert-close:hover { opacity: 1; }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .stat-value.total { color: var(--green); }
        .stat-value.active { color: var(--green); }
        .stat-value.admins { color: var(--info); }
        .stat-value.members { color: #8b5cf6; }
        .stat-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        /* Main Card */
        .main-card {
            background: white; border-radius: 20px;
            box-shadow: 0 20px 40px rgba(16,24,40,0.08);
            overflow: hidden;
        }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 30px 30px 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-title { font-size: 24px; font-weight: 700; color: #1f2937; }
        .card-subtitle { color: #6b7280; font-size: 14px; margin-top: 5px; }

        /* Filter Bar */
        .filter-bar {
            display: flex; gap: 12px; padding: 20px 30px;
            background: #f8fafc; border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap; align-items: center;
        }
        .filter-select, .filter-input {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            min-width: 140px;
        }
        .filter-input { min-width: 200px; }
        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
        }
        .filter-btn {
            background: var(--green); color: white;
            border: none; padding: 10px 20px;
            border-radius: 10px; font-weight: 600;
            cursor: pointer; display: flex;
            align-items: center; gap: 8px;
        }
        .filter-btn:hover { background: var(--green-dark); }
        .clear-btn {
            background: #f1f5f9; color: #475569;
            border: none; padding: 10px 16px;
            border-radius: 10px; font-weight: 600;
            cursor: pointer; display: flex;
            align-items: center; gap: 8px;
        }
        .clear-btn:hover { background: #e2e8f0; }

        /* Action Buttons */
        .action-buttons {
            display: flex; gap: 10px; align-items: center;
        }
        .btn-primary, .btn-secondary, .btn-danger {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: var(--green);
            color: white;
        }
        .btn-primary:hover {
            background: var(--green-dark);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: white;
            color: #374151;
            border: 2px solid #d1d5db;
        }
        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }
        .table {
            width: 100%; border-collapse: collapse;
            min-width: 1000px;
        }
        .table thead { background: var(--light-green); }
        .table th {
            padding: 16px 18px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e5e7eb;
        }
        .table td {
            padding: 16px 18px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        .table tbody tr {
            transition: all 0.3s;
        }
        .table tbody tr:hover {
            background: #f8fff9;
        }
        .table tbody tr.inactive {
            opacity: 0.7;
            background: #f9fafb;
        }
        .table tbody tr.suspended {
            background: #fef2f2;
        }

        /* Checkbox */
        .checkbox-cell {
            width: 50px;
            text-align: center;
        }
        .bulk-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* User Info */
        .user-info {
            display: flex; align-items: center; gap: 12px;
        }
        .user-info img {
            width: 42px; height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .user-details h4 {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }
        .user-details small {
            color: #6b7280;
            font-size: 12px;
        }

        /* Role Badges */
        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .role-admin { background: #dbeafe; color: #1d4ed8; }
        .role-priest { background: #f3e8ff; color: #7c3aed; }
        .role-secretary { background: #fef3c7; color: #d97706; }
        .role-treasurer { background: #dcfce7; color: #059669; }
        .role-member { background: #f1f5f9; color: #64748b; }

        /* Status Badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            cursor: pointer;
        }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #f3f4f6; color: #6b7280; }
        .status-suspended { background: #fee2e2; color: #991b1b; }

        /* Action Buttons */
        .action-group {
            display: flex; gap: 8px;
        }
        .action-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.3s;
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .btn-view { background: #e0f2fe; color: #0369a1; }
        .btn-edit { background: #fef3c7; color: #d97706; }
        .btn-delete { background: #fee2e2; color: #dc2626; }
        .btn-view:hover { background: #bae6fd; }
        .btn-edit:hover { background: #fde68a; }
        .btn-delete:hover { background: #fecaca; }

        /* Bulk Actions */
        .bulk-actions {
            display: none;
            padding: 15px 30px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            align-items: center;
            justify-content: space-between;
        }
        .bulk-actions.show {
            display: flex;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .page-btn {
            padding: 8px 14px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        .page-btn:hover:not(:disabled) {
            background: var(--light-green);
            color: var(--green);
            border-color: var(--green);
        }
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .page-info {
            color: #64748b;
            font-size: 14px;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 600px;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(15,23,42,0.35);
            animation: slideIn 0.3s ease;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        @keyframes slideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            padding: 24px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            color: #1f2937;
        }
        .close-btn {
            border: none;
            background: none;
            font-size: 24px;
            color: #9ca3af;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }
        .close-btn:hover {
            background: #f3f4f6;
            color: #374151;
        }
        .modal-body {
            padding: 30px;
            overflow-y: auto;
            flex: 1;
        }
        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 4px rgba(5,150,105,0.1);
        }
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        select.form-control {
            cursor: pointer;
            background: white;
        }

        /* View Modal */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .detail-item {
            margin-bottom: 15px;
        }
        .detail-label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .detail-value {
            font-size: 15px;
            color: #111827;
            font-weight: 500;
            padding: 8px 0;
        }

        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                left: -260px;
                transition: left 0.3s;
                z-index: 1999;
            }
            .sidebar.active {
                left: 0;
            }
            .content-area {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            .top-header {
                flex-wrap: wrap;
                gap: 10px;
                height: auto;
                padding: 15px 20px;
            }
            .header-search {
                order: 3;
                width: 100%;
                margin: 10px 0;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-select, .filter-input {
                width: 100%;
            }
            .action-buttons {
                flex-wrap: wrap;
            }
            .table th, .table td {
                padding: 12px 10px;
                font-size: 13px;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        .empty-state h4 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #64748b;
        }
        .empty-state p {
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="top-header">
        <div class="header-left">
            <img src="../images/logo.png" alt="SJPL Logo">
            <h3 style="color:#065f46; font-size:24px; font-weight:700;">San Jose Parish Laligan</h3>
        </div>
        <form method="GET" action="" class="header-search">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="search" placeholder="Search users by name, email, or phone..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
        </form>
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
                <a class="nav-item active" href="userManagement.php"><i class="fas fa-users-cog"></i> User Management</a>
                <a class="nav-item" href="support.php"><i class="fas fa-question-circle"></i> Help & Support</a>
            </div>
        </div>

        <div class="content-area">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                    <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value total"><?= $stats['total'] ?? 0 ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value active"><?= $stats['active'] ?? 0 ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value admins"><?= $stats['admins'] ?? 0 ?></div>
                    <div class="stat-label">Administrators</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value members"><?= $stats['members'] ?? 0 ?></div>
                    <div class="stat-label">Parish Members</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['priests'] ?? 0 ?></div>
                    <div class="stat-label">Priests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['secretaries'] ?? 0 ?></div>
                    <div class="stat-label">Secretaries</div>
                </div>
            </div>

            <!-- Main Card -->
            <div class="main-card">
                <div class="card-header">
                    <div>
                        <div class="card-title">User Management</div>
                        <div class="card-subtitle">Manage all parish accounts and permissions</div>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-secondary" onclick="openFilterModal()">
                            <i class="fas fa-filter"></i> Advanced Filters
                        </button>
                        <button class="btn-primary" onclick="openAddModal()">
                            <i class="fas fa-user-plus"></i> Add New User
                        </button>
                    </div>
                </div>

                <!-- Filter Bar -->
                <form method="GET" action="" class="filter-bar">
                    <select name="role" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role ?>" <?= $role_filter === $role ? 'selected' : '' ?>>
                                <?= ucwords($role) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                    <select name="zone" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?= $zone_filter === 'all' ? 'selected' : '' ?>>All Zones</option>
                        <?php foreach ($zones as $zone): ?>
                            <option value="<?= htmlspecialchars($zone) ?>" <?= $zone_filter === $zone ? 'selected' : '' ?>>
                                Zone <?= htmlspecialchars($zone) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="sort" class="filter-select" onchange="this.form.submit()">
                        <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Sort by Date</option>
                        <option value="fullname" <?= $sort_by === 'fullname' ? 'selected' : '' ?>>Sort by Name</option>
                        <option value="email" <?= $sort_by === 'email' ? 'selected' : '' ?>>Sort by Email</option>
                        <option value="role" <?= $sort_by === 'role' ? 'selected' : '' ?>>Sort by Role</option>
                    </select>
                    <select name="order" class="filter-select" onchange="this.form.submit()">
                        <option value="desc" <?= $sort_order === 'desc' ? 'selected' : '' ?>>Descending</option>
                        <option value="asc" <?= $sort_order === 'asc' ? 'selected' : '' ?>>Ascending</option>
                    </select>
                    <?php if ($search || $role_filter !== 'all' || $status_filter !== 'all' || $zone_filter !== 'all'): ?>
                        <a href="userManagement.php" class="clear-btn">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <div>
                        <span id="selectedCount">0</span> users selected
                    </div>
                    <div class="action-buttons">
                        <button class="btn-secondary" onclick="selectAll()">
                            <i class="fas fa-check-double"></i> Select All
                        </button>
                        <button class="btn-danger" onclick="bulkDelete()">
                            <i class="fas fa-trash"></i> Delete Selected
                        </button>
                    </div>
                </div>

                <!-- Active Filters Display -->
                <?php if ($search || $role_filter !== 'all' || $status_filter !== 'all' || $zone_filter !== 'all'): ?>
                    <div style="padding:15px 30px; background:#f0f9ff; border-bottom:1px solid #e2e8f0;">
                        <strong>Active Filters:</strong>
                        <?php if ($search): ?>
                            <span class="role-badge role-member" style="margin-left:10px;">
                                <i class="fas fa-search"></i> Search: "<?= htmlspecialchars($search) ?>"
                            </span>
                        <?php endif; ?>
                        <?php if ($role_filter !== 'all'): ?>
                            <span class="role-badge role-<?= $role_filter ?>" style="margin-left:10px;">
                                Role: <?= ucwords($role_filter) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($status_filter !== 'all'): ?>
                            <span class="status-badge status-<?= $status_filter ?>" style="margin-left:10px;">
                                Status: <?= ucwords($status_filter) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($zone_filter !== 'all'): ?>
                            <span class="role-badge role-member" style="margin-left:10px;">
                                <i class="fas fa-map-marker-alt"></i> Zone: <?= htmlspecialchars($zone_filter) ?>
                            </span>
                        <?php endif; ?>
                        <span style="margin-left:10px; color:#64748b; font-size:14px;">
                            Showing <?= count($users) ?> of <?= $total_users ?> users
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Table -->
                <div class="table-container">
                    <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h4>No users found</h4>
                            <p><?php 
                                if ($search || $role_filter !== 'all' || $status_filter !== 'all' || $zone_filter !== 'all') {
                                    echo 'Try adjusting your search or filter criteria.';
                                } else {
                                    echo 'No users registered yet. Add your first user!';
                                }
                            ?></p>
                            <button class="btn-primary" onclick="openAddModal()" style="margin-top:20px;">
                                <i class="fas fa-user-plus"></i> Add First User
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">
                                        <input type="checkbox" class="bulk-checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                    </th>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Role</th>
                                    <th>Zone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): 
                                    $is_current_user = $user['id'] == $user_id;
                                    $status_class = $user['status'] ?? 'active';
                                ?>
                                    <tr class="<?= $status_class ?>">
                                        <td class="checkbox-cell">
                                            <?php if (!$is_current_user): ?>
                                                <input type="checkbox" class="bulk-checkbox user-checkbox" 
                                                       value="<?= $user['id'] ?>" 
                                                       onchange="updateBulkActions()">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="user-info">
                                                <img src="<?= $user['profile_pic'] ? '../uploads/profile_pics/'.$user['profile_pic'] : 'https://via.placeholder.com/42/059669/ffffff?text='.substr($user['fullname'],0,1) ?>" 
                                                     alt="<?= htmlspecialchars($user['fullname']) ?>">
                                                <div class="user-details">
                                                    <h4><?= htmlspecialchars($user['fullname']) ?></h4>
                                                    <small>Joined <?= date('M d, Y', strtotime($user['created_at'])) ?></small>
                                                    <?php if ($user['occupation']): ?>
                                                        <br><small style="color:#059669;"><?= htmlspecialchars($user['occupation']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="margin-bottom:5px;">
                                                <i class="fas fa-envelope" style="color:#64748b; margin-right:5px;"></i>
                                                <?= htmlspecialchars($user['email']) ?>
                                            </div>
                                            <?php if ($user['phone']): ?>
                                                <div>
                                                    <i class="fas fa-phone" style="color:#64748b; margin-right:5px;"></i>
                                                    <?= htmlspecialchars($user['phone']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?= $user['role'] ?>">
                                                <?= ucwords($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $user['zone'] ? 'Zone ' . htmlspecialchars($user['zone']) : '—' ?>
                                        </td>
                                        <td>
                                            <?php if ($is_current_user): ?>
                                                <span class="status-badge status-active">You</span>
                                            <?php else: ?>
                                                <form method="POST" action="" style="display:inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <select name="status" class="status-badge status-<?= $status_class ?>" 
                                                            onchange="this.form.submit()" 
                                                            style="border:none; cursor:pointer; outline:none;">
                                                        <option value="active" <?= $status_class === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="inactive" <?= $status_class === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                        <option value="suspended" <?= $status_class === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                    </select>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-group">
                                                <button class="action-btn btn-view" onclick="viewUser(<?= $user['id'] ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn btn-edit" onclick="editUser(<?= $user['id'] ?>)"
                                                        title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if (!$is_current_user): ?>
                                                    <form method="POST" action="" style="display:inline;" 
                                                          onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($user['fullname'])) ?>?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="action-btn btn-delete" title="Delete User">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Pagination would go here if implemented -->
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="" id="addForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="addFullname">Full Name *</label>
                            <input type="text" id="addFullname" name="fullname" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="addEmail">Email Address *</label>
                            <input type="email" id="addEmail" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="addPassword">Password *</label>
                            <input type="password" id="addPassword" name="password" class="form-control" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="addRole">Role *</label>
                            <select id="addRole" name="role" class="form-control" required>
                                <option value="member">Member</option>
                                <option value="secretary">Secretary</option>
                                <option value="treasurer">Treasurer</option>
                                <option value="priest">Priest</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="addPhone">Phone Number</label>
                            <input type="tel" id="addPhone" name="phone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="addZone">Zone/Area</label>
                            <input type="text" id="addZone" name="zone" class="form-control">
                        </div>
                        <div class="form-group full-width">
                            <label for="addAddress">Address</label>
                            <textarea id="addAddress" name="address" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-save">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="editFullname">Full Name *</label>
                            <input type="text" id="editFullname" name="fullname" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="editEmail">Email Address *</label>
                            <input type="email" id="editEmail" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="editRole">Role *</label>
                            <select id="editRole" name="role" class="form-control" required>
                                <option value="member">Member</option>
                                <option value="secretary">Secretary</option>
                                <option value="treasurer">Treasurer</option>
                                <option value="priest">Priest</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editPhone">Phone Number</label>
                            <input type="tel" id="editPhone" name="phone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="editZone">Zone/Area</label>
                            <input type="text" id="editZone" name="zone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="editCivilStatus">Civil Status</label>
                            <select id="editCivilStatus" name="civil_status" class="form-control">
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="divorced">Divorced</option>
                                <option value="widowed">Widowed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editBirthday">Birthday</label>
                            <input type="date" id="editBirthday" name="birthday" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="editOccupation">Occupation</label>
                            <input type="text" id="editOccupation" name="occupation" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="editStatus">Account Status</label>
                            <select id="editStatus" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="editAddress">Address</label>
                            <textarea id="editAddress" name="address" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View User Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Details</h3>
                <button class="close-btn" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; align-items:center; gap:20px; margin-bottom:30px; padding-bottom:20px; border-bottom:1px solid #e2e8f0;">
                    <img id="viewProfilePic" src="" alt="Profile" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid var(--light-green);">
                    <div>
                        <h4 id="viewFullname" style="margin:0 0 8px 0; color:#1f2937;"></h4>
                        <span id="viewRole" class="role-badge" style="display:inline-block; margin-right:10px;"></span>
                        <span id="viewStatus" class="status-badge" style="display:inline-block;"></span>
                    </div>
                </div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Email Address</div>
                        <div class="detail-value" id="viewEmail"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Phone Number</div>
                        <div class="detail-value" id="viewPhone"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Zone/Area</div>
                        <div class="detail-value" id="viewZone"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Civil Status</div>
                        <div class="detail-value" id="viewCivilStatus"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Birthday</div>
                        <div class="detail-value" id="viewBirthday"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Occupation</div>
                        <div class="detail-value" id="viewOccupation"></div>
                    </div>
                    <div class="detail-item full-width">
                        <div class="detail-label">Address</div>
                        <div class="detail-value" id="viewAddress"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Member Since</div>
                        <div class="detail-value" id="viewCreated"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Last Updated</div>
                        <div class="detail-value" id="viewUpdated"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button>
                <button type="button" class="btn-primary" onclick="editCurrentUser()">
                    <i class="fas fa-edit"></i> Edit User
                </button>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Confirmation Modal -->
    <div id="bulkDeleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Deletion</h3>
                <button class="close-btn" onclick="closeBulkDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color:#ef4444; font-weight:600; margin-bottom:20px;">
                    <i class="fas fa-exclamation-triangle"></i> Warning: This action cannot be undone!
                </p>
                <p>Are you sure you want to delete <span id="deleteCount" style="font-weight:600;">0</span> selected users?</p>
                <p style="color:#64748b; font-size:14px; margin-top:10px;">
                    Note: You cannot delete your own account. Your account will be excluded from this operation.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeBulkDeleteModal()">Cancel</button>
                <button type="button" class="btn-danger" onclick="confirmBulkDelete()">
                    <i class="fas fa-trash"></i> Delete Selected Users
                </button>
            </div>
        </div>
    </div>

    <script>
        // Bulk selection
        function toggleSelectAll(checkbox) {
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            userCheckboxes.forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = checkbox.checked;
                }
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const selected = document.querySelectorAll('.user-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            selectedCount.textContent = selected.length;
            
            if (selected.length > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
            }
        }

        function selectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            selectAllCheckbox.checked = !selectAllCheckbox.checked;
            toggleSelectAll(selectAllCheckbox);
        }

        function bulkDelete() {
            const selected = document.querySelectorAll('.user-checkbox:checked');
            if (selected.length > 0) {
                document.getElementById('deleteCount').textContent = selected.length;
                openBulkDeleteModal();
            }
        }

        function confirmBulkDelete() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_delete';
            form.appendChild(actionInput);
            
            const selected = document.querySelectorAll('.user-checkbox:checked');
            selected.forEach((checkbox, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }

        // User data
        const usersData = <?= json_encode($users) ?>;
        let currentViewUserId = null;

        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
            document.getElementById('addFullname').focus();
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addForm').reset();
        }

        function openEditModal() {
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function openViewModal() {
            document.getElementById('viewModal').style.display = 'flex';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
            currentViewUserId = null;
        }

        function openBulkDeleteModal() {
            document.getElementById('bulkDeleteModal').style.display = 'flex';
        }

        function closeBulkDeleteModal() {
            document.getElementById('bulkDeleteModal').style.display = 'none';
        }

        function viewUser(userId) {
            const user = usersData.find(u => u.id == userId);
            if (!user) return;
            
            currentViewUserId = userId;
            
            // Set profile picture
            const profilePic = document.getElementById('viewProfilePic');
            profilePic.src = user.profile_pic ? 
                '../uploads/profile_pics/' + user.profile_pic : 
                'https://via.placeholder.com/80/059669/ffffff?text=' + user.fullname.charAt(0);
            
            // Set other details
            document.getElementById('viewFullname').textContent = user.fullname;
            document.getElementById('viewEmail').textContent = user.email;
            document.getElementById('viewPhone').textContent = user.phone || 'Not provided';
            document.getElementById('viewZone').textContent = user.zone ? 'Zone ' + user.zone : 'Not assigned';
            document.getElementById('viewCivilStatus').textContent = user.civil_status ? 
                user.civil_status.charAt(0).toUpperCase() + user.civil_status.slice(1) : 'Not specified';
            document.getElementById('viewBirthday').textContent = user.birthday ? 
                new Date(user.birthday).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                }) : 'Not specified';
            document.getElementById('viewOccupation').textContent = user.occupation || 'Not specified';
            document.getElementById('viewAddress').textContent = user.address || 'Not provided';
            document.getElementById('viewCreated').textContent = new Date(user.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('viewUpdated').textContent = user.updated_at ? 
                new Date(user.updated_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }) : 'Never';
            
            // Set role badge
            const roleBadge = document.getElementById('viewRole');
            roleBadge.textContent = user.role.charAt(0).toUpperCase() + user.role.slice(1);
            roleBadge.className = 'role-badge role-' + user.role;
            
            // Set status badge
            const statusBadge = document.getElementById('viewStatus');
            statusBadge.textContent = user.status ? 
                user.status.charAt(0).toUpperCase() + user.status.slice(1) : 'Active';
            statusBadge.className = 'status-badge status-' + (user.status || 'active');
            
            openViewModal();
        }

        function editUser(userId) {
            const user = usersData.find(u => u.id == userId);
            if (!user) return;
            
            // Populate edit form
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editFullname').value = user.fullname;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editRole').value = user.role;
            document.getElementById('editPhone').value = user.phone || '';
            document.getElementById('editZone').value = user.zone || '';
            document.getElementById('editCivilStatus').value = user.civil_status || 'single';
            document.getElementById('editBirthday').value = user.birthday || '';
            document.getElementById('editOccupation').value = user.occupation || '';
            document.getElementById('editStatus').value = user.status || 'active';
            document.getElementById('editAddress').value = user.address || '';
            
            openEditModal();
        }

        function editCurrentUser() {
            if (currentViewUserId) {
                closeViewModal();
                setTimeout(() => editUser(currentViewUserId), 300);
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['addModal', 'editModal', 'viewModal', 'bulkDeleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    switch(modalId) {
                        case 'addModal': closeAddModal(); break;
                        case 'editModal': closeEditModal(); break;
                        case 'viewModal': closeViewModal(); break;
                        case 'bulkDeleteModal': closeBulkDeleteModal(); break;
                    }
                }
            });
        }

        // Close modals on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddModal();
                closeEditModal();
                closeViewModal();
                closeBulkDeleteModal();
            }
        });

        // Auto-hide success message after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                if (alert.style.display !== 'none') {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.style.display = 'none', 500);
                }
            });
        }, 5000);

        // Quick search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                // Add a debounced search
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (this.value.length >= 2 || this.value.length === 0) {
                            this.form.submit();
                        }
                    }, 500);
                });
            }
            
            // Initialize bulk actions
            updateBulkActions();
        });
    </script>
</body>
</html>