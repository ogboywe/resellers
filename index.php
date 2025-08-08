<?php
/**
 * Reseller Management System
 * 
 * A comprehensive platform for managing resellers and customers
 * with advanced security features and robust error handling.
 */

require 'funcs.php';
require 'funcx.php';

// Error reporting settings - temporarily disabled to fix 500 error
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_start();

// Set session timeout to 30 minutes
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF'] . "?timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database Configuration
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => 'NoraGO12!',
    'database' => 'yessir'
];

// Global variables
$error = null;
$success = null;
$db_error = null;
$conn = null;
$users = [];
$customers = [];
$trials = [];
$notifications = [];
$activityLog = [];

// Database Connection
try {
    $conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password']);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to ensure proper encoding
    $conn->set_charset("utf8mb4");
    
    // Create database if it doesn't exist
    $conn->query("CREATE DATABASE IF NOT EXISTS {$db_config['database']}");
    $conn->select_db($db_config['database']);
    
    // Check if tables exist, if not, create them
    $tables_exist = true;
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result && $result->num_rows == 0) {
        $tables_exist = false;
    }
    
    if (!$tables_exist) {
        // Create tables
        if (file_exists('db.sql')) {
            $sql = file_get_contents('db.sql');
        } else {
            $sql = ''; // Fallback if db.sql doesn't exist
        }
        
        // Execute multi query
        if (!empty($sql) && $conn->multi_query($sql)) {
            do {
                // Store first result set
                if ($result = $conn->store_result()) {
                    $result->free();
                }
                // Check if there are more result sets
            } while ($conn->more_results() && $conn->next_result());
        }
        
        if ($conn->error) {
            throw new Exception("Error creating tables: " . $conn->error);
        }
        
        // Create trial table separately to avoid SQL issues
        $trialTableSQL = "CREATE TABLE IF NOT EXISTS `trial` (
            `id` int NOT NULL AUTO_INCREMENT,
            `subid` int NOT NULL DEFAULT 0,
            `first_name` varchar(50) NOT NULL,
            `last_name` varchar(50) NOT NULL,
            `email` varchar(100) NOT NULL,
            `phone` varchar(20) NOT NULL,
            `username` varchar(50) NOT NULL,
            `password` varchar(50) NOT NULL,
            `provider_id` varchar(50) NOT NULL,
            `created_by` varchar(50) NOT NULL,
            `created_at` datetime NOT NULL,
            `expires_at` datetime NOT NULL,
            `status` enum('active','expired') NOT NULL DEFAULT 'active',
            `notes` text,
            `last_modified` datetime NOT NULL,
            `modified_by` varchar(50) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE = InnoDB AUTO_INCREMENT = 1";
        
        $conn->query($trialTableSQL);
        
        // Insert default admin user
        $admin_password = password_hash('Manage123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, role, full_name, email, phone, created_at, status) 
                     VALUES ('Manager', '$admin_password', 'owner', 'System Manager', 'manager@example.com', '9876543210', NOW(), 'active')");
        
        $reseller_password = password_hash('password123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, role, created_by, full_name, email, phone, created_at, status) 
                     VALUES ('admin', '$reseller_password', 'reseller', 'Manager', 'Admin User', 'admin@example.com', '1234567890', NOW(), 'active')");
    } else {
        // Even if main tables exist, make sure trial table exists
        $trialTableSQL = "CREATE TABLE IF NOT EXISTS `trial` (
            `id` int NOT NULL AUTO_INCREMENT,
            `subid` int NOT NULL DEFAULT 0,
            `first_name` varchar(50) NOT NULL,
            `last_name` varchar(50) NOT NULL,
            `email` varchar(100) NOT NULL,
            `phone` varchar(20) NOT NULL,
            `username` varchar(50) NOT NULL,
            `password` varchar(50) NOT NULL,
            `provider_id` varchar(50) NOT NULL,
            `created_by` varchar(50) NOT NULL,
            `created_at` datetime NOT NULL,
            `expires_at` datetime NOT NULL,
            `status` enum('active','expired') NOT NULL DEFAULT 'active',
            `notes` text,
            `last_modified` datetime NOT NULL,
            `modified_by` varchar(50) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE = InnoDB AUTO_INCREMENT = 1";
        
        $conn->query($trialTableSQL);
    }
} catch (Exception $e) {
    // If database connection fails, use session-based storage as fallback
    $db_error = "Database connection error: " . $e->getMessage();
    $conn = null;
}

/**
 * Database Functions
 */

// Function to get users
function getUsers() {
    global $conn, $users;
    
    if (empty($users)) {
        if ($conn) {
            $users = [];
            $result = $conn->query("SELECT * FROM users");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $users[$row['username']] = [
                        'id' => $row['id'],
                        'password' => $row['password'],
                        'role' => $row['role'],
                        'created_by' => $row['created_by'],
                        'full_name' => $row['full_name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                        'created_at' => $row['created_at'],
                        'last_login' => $row['last_login'],
                        'login_attempts' => $row['login_attempts'],
                        'status' => $row['status']
                    ];
                }
            }
        } else {
            // Fallback to session storage
            if (!isset($_SESSION['users'])) {
                $_SESSION['users'] = [
                    'admin' => [
                        'id' => 1,
                        'password' => password_hash('password123', PASSWORD_DEFAULT),
                        'role' => 'reseller',
                        'created_by' => 'Manager',
                        'full_name' => 'Admin User',
                        'email' => 'admin@example.com',
                        'phone' => '1234567890',
                        'created_at' => date('Y-m-d H:i:s'),
                        'last_login' => null,
                        'login_attempts' => 0,
                        'status' => 'active'
                    ],
                    'Manager' => [
                        'id' => 2,
                        'password' => password_hash('Manage123', PASSWORD_DEFAULT),
                        'role' => 'owner',
                        'created_by' => null,
                        'full_name' => 'System Manager',
                        'email' => 'manager@example.com',
                        'phone' => '9876543210',
                        'created_at' => date('Y-m-d H:i:s'),
                        'last_login' => null,
                        'login_attempts' => 0,
                        'status' => 'active'
                    ]
                ];
            }
            $users = $_SESSION['users'];
        }
    }
    
    return $users;
}

// Function to get customers
function getCustomers() {
    global $conn, $customers;
    
    if (empty($customers)) {
        if ($conn) {
            $customers = [];
            $result = $conn->query("SELECT * FROM customers ORDER BY created_at DESC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $customers[] = $row;
                }
            }
        } else {
            // Fallback to session storage
            if (!isset($_SESSION['customers'])) {
                $_SESSION['customers'] = [];
            }
            $customers = $_SESSION['customers'];
        }
    }
    
    return $customers;
}

// Function to get trials
function getTrials() {
    global $conn, $trials;
    
    if (empty($trials)) {
        if ($conn) {
            $trials = [];
            $result = $conn->query("SELECT * FROM trial ORDER BY created_at DESC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $trials[] = $row;
                }
            }
        } else {
            // Fallback to session storage
            if (!isset($_SESSION['trials'])) {
                $_SESSION['trials'] = [];
            }
            $trials = $_SESSION['trials'];
        }
    }
    
    return $trials;
}

// Function to get activity log
function getActivityLog() {
    global $conn, $activityLog;
    
    if (empty($activityLog)) {
        if ($conn) {
            $activityLog = [];
            $result = $conn->query("SELECT * FROM activity_log ORDER BY timestamp DESC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $activityLog[] = $row;
                }
            }
        } else {
            // Fallback to session storage
            if (!isset($_SESSION['activity_log'])) {
                $_SESSION['activity_log'] = [];
            }
            $activityLog = $_SESSION['activity_log'];
        }
    }
    
    return $activityLog;
}

// Function to get notifications
function getNotifications() {
    global $conn, $notifications;
    
    if (empty($notifications)) {
        if ($conn) {
            $notifications = [];
            $result = $conn->query("SELECT * FROM notifications ORDER BY timestamp DESC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $notifications[] = $row;
                }
            }
        } else {
            // Fallback to session storage
            if (!isset($_SESSION['notifications'])) {
                $_SESSION['notifications'] = [];
            }
            $notifications = $_SESSION['notifications'];
        }
    }
    
    return $notifications;
}

// Function to update user
function updateUser($username, $data) {
    global $conn, $users;
    
    if ($conn) {
        $set_clauses = [];
        foreach ($data as $key => $value) {
            $set_clauses[] = "$key = '" . $conn->real_escape_string($value) . "'";
        }
        $set_clause = implode(', ', $set_clauses);
        $conn->query("UPDATE users SET $set_clause WHERE username = '" . $conn->real_escape_string($username) . "'");
        
        // Clear users cache to force reload
        $users = [];
    } else {
        // Fallback to session storage
        foreach ($data as $key => $value) {
            $_SESSION['users'][$username][$key] = $value;
        }
        $users = $_SESSION['users'];
    }
}

// Function to create user
function createUser($data) {
    global $conn, $users;
    
    if ($conn) {
        $columns = implode(', ', array_keys($data));
        $values = "'" . implode("', '", array_map(function($value) use ($conn) {
            return $conn->real_escape_string($value);
        }, array_values($data))) . "'";
        $conn->query("INSERT INTO users ($columns) VALUES ($values)");

        //die($conn->error);
        
        // Clear users cache to force reload
        $users = [];
    } else {
        // Fallback to session storage
        $username = $data['username'];
        unset($data['username']);
        $_SESSION['users'][$username] = $data;
        $users = $_SESSION['users'];
    }
}

// Function to create customer
function createCustomer($data) {
    global $conn, $customers;
    
    if ($conn) {
        $columns = implode(', ', array_keys($data));
        $values = "'" . implode("', '", array_map(function($value) use ($conn) {
            return $conn->real_escape_string($value);
        }, array_values($data))) . "'";
        $conn->query("INSERT INTO customers ($columns) VALUES ($values)");
        $id = $conn->insert_id;
        
        // Clear customers cache to force reload
        $customers = [];
        return $id;
    } else {
        // Fallback to session storage
        $data['id'] = count($_SESSION['customers']);
        $_SESSION['customers'][] = $data;
        $customers = $_SESSION['customers'];
        return count($_SESSION['customers']) - 1;
    }
}

// Function to create trial
function createTrial($data) {
    global $conn, $trials;
    
    if ($conn) {
        $columns = implode(', ', array_keys($data));
        $values = "'" . implode("', '", array_map(function($value) use ($conn) {
            return $conn->real_escape_string($value);
        }, array_values($data))) . "'";
        $conn->query("INSERT INTO trial ($columns) VALUES ($values)");
        $id = $conn->insert_id;
        
        // Clear trials cache to force reload
        $trials = [];
        return $id;
    } else {
        // Fallback to session storage
        $data['id'] = count($_SESSION['trials']) + 1;
        $_SESSION['trials'][] = $data;
        $trials = $_SESSION['trials'];
        return count($_SESSION['trials']);
    }
}

// Function to update customer
function updateCustomer($id, $data) {
    global $conn, $customers;
    
    if ($conn) {
        $set_clauses = [];
        foreach ($data as $key => $value) {
            $set_clauses[] = "$key = '" . $conn->real_escape_string($value) . "'";
        }
        $set_clause = implode(', ', $set_clauses);
        $conn->query("UPDATE customers SET $set_clause WHERE id = " . (int)$id);
        
        // Clear customers cache to force reload
        $customers = [];
    } else {
        // Fallback to session storage
        foreach ($data as $key => $value) {
            $_SESSION['customers'][$id][$key] = $value;
        }
        $customers = $_SESSION['customers'];
    }
}

// Function to log activity
function logActivity($user, $action, $details) {
    global $conn, $activityLog;
    
    if ($conn) {
        $user = $conn->real_escape_string($user);
        $action = $conn->real_escape_string($action);
        $details = $conn->real_escape_string($details);
        $ip_address = $conn->real_escape_string($_SERVER['REMOTE_ADDR']);
        
        $conn->query("INSERT INTO activity_log (user, action, details, ip_address) 
                     VALUES ('$user', '$action', '$details', '$ip_address')");
        
        // Create notification for owner if user is not owner
        $result = $conn->query("SELECT role FROM users WHERE username = '$user'");
        if ($result && $row = $result->fetch_assoc()) {
            if ($row['role'] !== 'owner') {
                $message = "$action - $details";
                $conn->query("INSERT INTO notifications (user, message) 
                             VALUES ('$user', '$message')");
            }
        }
        
        // Clear activity log cache to force reload
        $activityLog = [];
    } else {
        // Fallback to session storage
        $_SESSION['activity_log'][] = [
            'id' => count($_SESSION['activity_log']) + 1,
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $user,
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ];
        
        // Create notification for owner
        $users = getUsers();
        if ($users[$user]['role'] !== 'owner') {
            $_SESSION['notifications'][] = [
                'id' => count($_SESSION['notifications']) + 1,
                'timestamp' => date('Y-m-d H:i:s'),
                'user' => $user,
                'message' => "$action - $details",
                'read' => false
            ];
        }
        
        $activityLog = $_SESSION['activity_log'];
    }
}

// Function to mark notification as read
function markNotificationAsRead($id) {
    global $conn, $notifications;
    
    if ($conn) {
        $conn->query("UPDATE notifications SET `read` = 1 WHERE id = " . (int)$id);
        
        // Clear notifications cache to force reload
        $notifications = [];
    } else {
        // Fallback to session storage
        foreach ($_SESSION['notifications'] as $index => $notification) {
            if ($notification['id'] == $id) {
                $_SESSION['notifications'][$index]['read'] = true;
                break;
            }
        }
        $notifications = $_SESSION['notifications'];
    }
}

// Function to mark all notifications as read
function markAllNotificationsAsRead() {
    global $conn, $notifications;
    
    if ($conn) {
        $conn->query("UPDATE notifications SET `read` = 1");
        
        // Clear notifications cache to force reload
        $notifications = [];
    } else {
        // Fallback to session storage
        foreach ($_SESSION['notifications'] as $index => $notification) {
            $_SESSION['notifications'][$index]['read'] = true;
        }
        $notifications = $_SESSION['notifications'];
    }
}

// Function to check expired customers
function checkExpiredCustomers() {
    global $conn, $customers;
    
    if ($conn) {
        $conn->query("UPDATE customers SET status = 'expired' WHERE expires_at < NOW()");
        
        // Clear customers cache to force reload
        $customers = [];
    } else {
        // Fallback to session storage
        foreach ($_SESSION['customers'] as $key => $customer) {
            if (strtotime($customer['expires_at']) < time()) {
                $_SESSION['customers'][$key]['status'] = 'expired';
            }
        }
        $customers = $_SESSION['customers'];
    }
}

// Function to delete reseller
function deleteReseller($username) {
    global $conn, $users;
    
    if ($conn) {
        // First, reassign all customers to the owner
        $conn->query("UPDATE customers SET created_by = 'Manager' WHERE created_by = '" . $conn->real_escape_string($username) . "'");
        
        // Then delete the reseller
        $conn->query("DELETE FROM users WHERE username = '" . $conn->real_escape_string($username) . "'");
        
        // Clear users cache to force reload
        $users = [];
        return true;
    } else {
        // Fallback to session storage
        if (isset($_SESSION['users'][$username])) {
            // Reassign all customers to the owner
            foreach ($_SESSION['customers'] as $key => $customer) {
                if ($customer['created_by'] === $username) {
                    $_SESSION['customers'][$key]['created_by'] = 'Manager';
                }
            }
            
            // Delete the reseller
            unset($_SESSION['users'][$username]);
            $users = $_SESSION['users'];
            return true;
        }
        return false;
    }
}

// Function to change user password
function changePassword($username, $currentPassword, $newPassword) {
    global $conn, $users, $error;
    
    $users = getUsers();
    
    if (!isset($users[$username])) {
        $error = "User not found";
        return false;
    }
    
    if (!password_verify($currentPassword, $users[$username]['password'])) {
        $error = "Current password is incorrect";
        return false;
    }
    
    updateUser($username, [
        'password' => password_hash($newPassword, PASSWORD_DEFAULT)
    ]);
    
    return true;
}

/**
 * Utility Functions
 */

// Function to generate random password (5 digits)
function generatePassword() {
    return str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
}

// Function to generate username from phone
function generateUsername($phone) {
    // Get last 4 digits and add a 0
    return substr(preg_replace('/[^0-9]/', '', $phone), -4) . '0';
}

// Function to sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate phone
function validatePhone($phone) {
    return preg_match('/^[0-9]{10,15}$/', preg_replace('/[^0-9]/', '', $phone));
}

// Function to validate password strength
function validatePassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// Function to get client IP address
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// Function to filter customers based on search term and pagination
function filterCustomers($customers, $searchTerm, $currentPage, $itemsPerPage, $resellerOnly = false, $resellerName = '', $statusFilter = '') {
    $filteredCustomers = [];
    
    foreach ($customers as $index => $customer) {
        // Filter by reseller if needed
        if ($resellerOnly && $customer['created_by'] !== $resellerName) {
            continue;
        }
        
        // Filter by status if needed
        if (!empty($statusFilter) && $customer['status'] !== $statusFilter) {
            continue;
        }
        
        // Apply search filter if search term exists
        if (!empty($searchTerm)) {
            $searchFields = [
                $customer['first_name'],
                $customer['last_name'],
                $customer['email'],
                $customer['phone'],
                $customer['username'],
                $customer['created_by']
            ];
            
            $found = false;
            foreach ($searchFields as $field) {
                if (stripos($field, $searchTerm) !== false) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                continue;
            }
        }
        
        $filteredCustomers[$index] = $customer;
    }
    
    // Calculate total pages
    $totalItems = count($filteredCustomers);
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    // Get paginated items
    $start = ($currentPage - 1) * $itemsPerPage;
    $paginatedCustomers = array_slice($filteredCustomers, $start, $itemsPerPage, true);
    
    return [
        'customers' => $paginatedCustomers,
        'totalItems' => $totalItems,
        'totalPages' => $totalPages
    ];
}

// Function to get statistics
function getStatistics() {
    $users = getUsers();
    $customers = getCustomers();
    
    $stats = [
        'totalResellers' => 0,
        'totalCustomers' => count($customers),
        'activeCustomers' => 0,
        'expiredCustomers' => 0,
        'customersLast30Days' => 0,
        'customersLast7Days' => 0,
        'resellerStats' => []
    ];
    
    // Count resellers
    foreach ($users as $username => $user) {
        if (isset($user['role']) && $user['role'] === 'reseller') {
            $stats['totalResellers']++;
            $stats['resellerStats'][$username] = [
                'name' => $user['full_name'],
                'totalCustomers' => 0,
                'activeCustomers' => 0,
                'expiredCustomers' => 0,
                'last7Days' => 0,
                'last30Days' => 0
            ];
        }
    }
    
    // Count customers
    $thirtyDaysAgo = strtotime('-30 days');
    $sevenDaysAgo = strtotime('-7 days');
    
    foreach ($customers as $customer) {
        // Count active/expired
        if ($customer['status'] === 'active') {
            $stats['activeCustomers']++;
            if (isset($stats['resellerStats'][$customer['created_by']])) {
                $stats['resellerStats'][$customer['created_by']]['activeCustomers']++;
            }
        } else {
            $stats['expiredCustomers']++;
            if (isset($stats['resellerStats'][$customer['created_by']])) {
                $stats['resellerStats'][$customer['created_by']]['expiredCustomers']++;
            }
        }
        
        // Count by reseller
        if (isset($stats['resellerStats'][$customer['created_by']])) {
            $stats['resellerStats'][$customer['created_by']]['totalCustomers']++;
        }
        
        // Count last 30 days
        $createdTime = strtotime($customer['created_at']);
        if ($createdTime >= $thirtyDaysAgo) {
            $stats['customersLast30Days']++;
            if (isset($stats['resellerStats'][$customer['created_by']])) {
                $stats['resellerStats'][$customer['created_by']]['last30Days']++;
            }
            
            // Count last 7 days
            if ($createdTime >= $sevenDaysAgo) {
                $stats['customersLast7Days']++;
                if (isset($stats['resellerStats'][$customer['created_by']])) {
                    $stats['resellerStats'][$customer['created_by']]['last7Days']++;
                }
            }
        }
    }
    
    return $stats;
}

// Check expired customers
checkExpiredCustomers();

/**
 * Form Handling
 */

// Handle login
if (isset($_POST['login']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    $users = getUsers();
    
    if (isset($users[$username])) {
        // Check for too many login attempts
        if ($users[$username]['login_attempts'] >= 5) {
            $error = "Account locked due to too many failed login attempts. Please contact administrator.";
        } else {
            if (password_verify($password, $users[$username]['password'])) {
                $_SESSION['user'] = $username;
                $_SESSION['role'] = $users[$username]['role'];
                
                // Update last login and reset login attempts
                updateUser($username, [
                    'last_login' => date('Y-m-d H:i:s'),
                    'login_attempts' => 0
                ]);
                
                logActivity($username, 'Login', 'User logged in successfully');
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
            } else {
                // Increment login attempts
                updateUser($username, [
                    'login_attempts' => $users[$username]['login_attempts'] + 1
                ]);
                
                $error = "Invalid username or password";
            }
        }
    } else {
        $error = "Invalid username or password";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['user'])) {
        logActivity($_SESSION['user'], 'Logout', 'User logged out');
    }
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle customer creation
if (isset($_POST['create_customer']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
    
    $errors = [];
    
    // Validate inputs
    if (empty($firstName)) $errors[] = "First name is required";
    if (empty($lastName)) $errors[] = "Last name is required";
    if (!validateEmail($email)) $errors[] = "Valid email is required";
    if (!validatePhone($phone)) $errors[] = "Valid phone number is required";
    
    if (empty($errors)) {
        $username = generateUsername($phone);
        $password = generatePassword();
        
        // Calculate dates properly
        $currentDate = date('Y-m-d H:i:s');
        $expirationDate = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // Add 30 days in seconds
        
        // Debug: Log the calculated dates
        // error_log("Customer Creation - Current: $currentDate, Expires: $expirationDate");
        
        $customerData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'username' => $username,
            'password' => $password,
            'provider_id' => '465',
            'created_by' => $_SESSION['user'],
            'created_at' => $currentDate,
            'expires_at' => $expirationDate,
            'status' => 'active',
            'notes' => $notes,
            'last_modified' => $currentDate,
            'modified_by' => $_SESSION['user']
        ];

        // generate account on remote api
        $resultCreation = generateAccount($email, $firstName, $lastName, $phone, $password);

        if(is_numeric($resultCreation)) {

            $customerData["subid"] = $resultCreation;

            // valid!
            createCustomer($customerData);

            logActivity($_SESSION['user'], 'Create Customer', "Created account for $firstName $lastName");
            $success = "Customer created successfully!";

        } elseif($resultCreation == "already_exist") {

            $error = "An user already exists with this username.";

        } else {

           $error = "Critical error.";

        }
        

    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle trial creation
if (isset($_POST['create_trial']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
    
    $errors = [];
    
    // Validate inputs
    if (empty($firstName)) $errors[] = "First name is required";
    if (empty($lastName)) $errors[] = "Last name is required";
    if (!validateEmail($email)) $errors[] = "Valid email is required";
    if (!validatePhone($phone)) $errors[] = "Valid phone number is required";
    
    if (empty($errors)) {
        $username = generateUsername($phone);
        $password = generatePassword();
        
        // Calculate dates properly for trial (1 day)
        $currentDate = date('Y-m-d H:i:s');
        $trialExpirationDate = date('Y-m-d H:i:s', time() + (1 * 24 * 60 * 60)); // Add 1 day in seconds
        
        // Debug: Log the calculated dates
        // error_log("Trial Creation - Current: $currentDate, Expires: $trialExpirationDate");
        
        $trialData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'username' => $username,
            'password' => $password,
            'provider_id' => '465',
            'created_by' => $_SESSION['user'],
            'created_at' => $currentDate,
            'expires_at' => $trialExpirationDate,
            'status' => 'active',
            'notes' => $notes,
            'last_modified' => $currentDate,
            'modified_by' => $_SESSION['user']
        ];

        // generate trial account on remote api using funcx.php (trial-specific function)
        // We need to use the trial version which creates shorter duration accounts
        if (function_exists('generateTrialAccount')) {
            $resultCreation = generateTrialAccount($email, $firstName, $lastName, $phone, $password);
        } else {
            $resultCreation = "function_not_found";
        }

        if(is_numeric($resultCreation)) {

            $trialData["subid"] = $resultCreation;

            // valid!
            createTrial($trialData);

            logActivity($_SESSION['user'], 'Create Trial', "Created trial account for $firstName $lastName");
            $success = "Trial account created successfully!";

        } elseif($resultCreation == "already_exist") {

            $error = "A user already exists with this username.";

        } elseif($resultCreation == "function_not_found") {

            $error = "Trial function not available. Please check funcx.php file.";

        } else {

           $error = "Critical error creating trial.";

        }
        

    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle customer renewal
if (isset($_POST['renew_customer']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $id = $_POST['customer_id'];
    $customers = getCustomers();
    
    $customerFound = false;
    foreach ($customers as $customer) {
        if ($customer['id'] == $id) {
            $customerFound = true;

            $renewResult = extendAccount($customer["subid"]);

            if($renewResult != "success") {
                if ($renewResult == "subscriber_not_found") {
                    $error = "Unable to renew this user: Account not found in the system. Please contact support.";
                } else {
                    $error = "Unable to renew this user. Please try again or contact support.";
                }
            } else {

                // Calculate new expiration date from current expiration or today if expired
                $currentExpiration = strtotime($customer['expires_at']);
                $today = time();
                $baseDate = ($currentExpiration > $today) ? $currentExpiration : $today;
                $newExpirationDate = date('Y-m-d H:i:s', $baseDate + (30 * 24 * 60 * 60)); // Add 30 days
                
                updateCustomer($id, [
                    'expires_at' => $newExpirationDate,
                    'status' => 'active',
                    'last_modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $_SESSION['user']
                ]);

                logActivity($_SESSION['user'], 'Renew Customer', "Renewed account for {$customer['first_name']} {$customer['last_name']}");
                $success = "Customer renewed successfully!";
                break;

            }
            
        }
    }
    
    if (!$customerFound) {
        $error = "Customer not found";
    }
}

// Handle trial to customer conversion
if (isset($_POST['convert_trial_to_customer']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    require_once 'funcx.php';
    
    $id = $_POST['trial_id'];
    $trials = getTrials();
    
    $trialFound = false;
    foreach ($trials as $trial) {
        if ($trial['id'] == $id) {
            $trialFound = true;

            $convertResult = convertTrialToCustomer($trial["subid"]);

            if($convertResult != $trial["subid"]) {
                $error = "Unable to convert this trial to customer. Please try again.";
            } else {
                // Calculate new expiration date (30 days from today)
                $newExpirationDate = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
                
                addCustomer([
                    'first_name' => $trial['first_name'],
                    'last_name' => $trial['last_name'],
                    'email' => $trial['email'],
                    'phone' => $trial['phone'],
                    'subid' => $trial['subid'],
                    'expires_at' => $newExpirationDate,
                    'status' => 'active',
                    'notes' => $trial['notes'] . ' (Converted from trial)',
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $_SESSION['user'],
                    'last_modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $_SESSION['user']
                ]);

                deleteTrial($id);

                logActivity($_SESSION['user'], 'Convert Trial to Customer', "Converted trial account for {$trial['first_name']} {$trial['last_name']} to customer");
                $success = "Trial account converted to customer successfully!";
                break;
            }
        }
    }
    
    if (!$trialFound) {
        $error = "Trial account not found";
    }
}

// Handle customer update
if (isset($_POST['update_customer']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $id = $_POST['customer_id'];
    $customers = getCustomers();
    
    $customerFound = false;
    foreach ($customers as $customer) {
        if ($customer['id'] == $id) {
            $customerFound = true;
            
            $firstName = sanitize($_POST['first_name']);
            $lastName = sanitize($_POST['last_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $notes = sanitize($_POST['notes']);
            
            $errors = [];
            
            // Validate inputs
            if (empty($firstName)) $errors[] = "First name is required";
            if (empty($lastName)) $errors[] = "Last name is required";
            if (!validateEmail($email)) $errors[] = "Valid email is required";
            if (!validatePhone($phone)) $errors[] = "Valid phone number is required";
            
            if (empty($errors)) {
                updateCustomer($id, [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'notes' => $notes,
                    'last_modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $_SESSION['user']
                ]);
                
                logActivity($_SESSION['user'], 'Update Customer', "Updated account for {$customer['first_name']} {$customer['last_name']}");
                $success = "Customer updated successfully!";
            } else {
                $error = implode("<br>", $errors);
            }
            
            break;
        }
    }
    
    if (!$customerFound) {
        $error = "Customer not found";
    }
}

// Handle reseller creation (owner only)
if (isset($_POST['create_reseller']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'] && $_SESSION['role'] === 'owner') {
    $resellerName = sanitize($_POST['reseller_name']);
    $resellerPassword = $_POST['reseller_password'];
    $fullName = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    
    $errors = [];
    $users = getUsers();
    
    // Validate inputs
    if (empty($resellerName)) $errors[] = "Username is required";
    if (empty($resellerPassword)) $errors[] = "Password is required";
    if (!validatePassword($resellerPassword)) $errors[] = "Password must be at least 8 characters and include uppercase, lowercase, and numbers";
    if (empty($fullName)) $errors[] = "Full name is required";
    if (!validateEmail($email)) $errors[] = "Valid email is required";
    if (!validatePhone($phone)) $errors[] = "Valid phone number is required";
    if (isset($users[$resellerName])) $errors[] = "Username already exists";
    
    if (empty($errors)) {
        $userData = [
            'username' => $resellerName,
            'password' => password_hash($resellerPassword, PASSWORD_DEFAULT),
            'role' => 'reseller',
            'created_by' => $_SESSION['user'],
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'created_at' => date('Y-m-d H:i:s'),
            'last_login' => date('Y-m-d H:i:s'),
            'login_attempts' => 0,
            'status' => 'active'
        ];
        
        createUser($userData);
        
        logActivity($_SESSION['user'], 'Create Reseller', "Created reseller account for $fullName");
        $success = "Reseller created successfully!";
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle reseller update (owner only)
if (isset($_POST['update_reseller']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'] && $_SESSION['role'] === 'owner') {
    $resellerName = sanitize($_POST['reseller_name']);
    $fullName = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $status = sanitize($_POST['status']);
    
    $errors = [];
    $users = getUsers();
    
    // Validate inputs
    if (empty($fullName)) $errors[] = "Full name is required";
    if (!validateEmail($email)) $errors[] = "Valid email is required";
    if (!validatePhone($phone)) $errors[] = "Valid phone number is required";
    if (!isset($users[$resellerName])) $errors[] = "Reseller does not exist";
    
    if (empty($errors)) {
        updateUser($resellerName, [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'status' => $status
        ]);
        
        logActivity($_SESSION['user'], 'Update Reseller', "Updated reseller account for $fullName");
        $success = "Reseller updated successfully!";
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle reseller deletion (owner only)
if (isset($_POST['delete_reseller']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'] && $_SESSION['role'] === 'owner') {
    $resellerName = sanitize($_POST['reseller_name']);
    $users = getUsers();
    
    if (isset($users[$resellerName])) {
        if (deleteReseller($resellerName)) {
            logActivity($_SESSION['user'], 'Delete Reseller', "Deleted reseller account for {$users[$resellerName]['full_name']}");
            $success = "Reseller deleted successfully! All customers have been reassigned to you.";
        } else {
            $error = "Failed to delete reseller";
        }
    } else {
        $error = "Reseller not found";
    }
}

// Handle reseller password reset (owner only)
if (isset($_POST['reset_reseller_password']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'] && $_SESSION['role'] === 'owner') {
    $resellerName = sanitize($_POST['reseller_name']);
    $newPassword = $_POST['new_password'];
    
    $errors = [];
    $users = getUsers();
    
    // Validate inputs
    if (empty($newPassword)) $errors[] = "Password is required";
    if (!validatePassword($newPassword)) $errors[] = "Password must be at least 8 characters and include uppercase, lowercase, and numbers";
    if (!isset($users[$resellerName])) $errors[] = "Reseller does not exist";
    
    if (empty($errors)) {
        updateUser($resellerName, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'login_attempts' => 0
        ]);
        
        logActivity($_SESSION['user'], 'Reset Password', "Reset password for reseller $resellerName");
        $success = "Reseller password reset successfully!";
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle user password change
if (isset($_POST['change_password']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate inputs
    if (empty($currentPassword)) $errors[] = "Current password is required";
    if (empty($newPassword)) $errors[] = "New password is required";
    if (!validatePassword($newPassword)) $errors[] = "Password must be at least 8 characters and include uppercase, lowercase, and numbers";
    if ($newPassword !== $confirmPassword) $errors[] = "Passwords do not match";
    
    if (empty($errors)) {
        if (changePassword($_SESSION['user'], $currentPassword, $newPassword)) {
            logActivity($_SESSION['user'], 'Change Password', "Changed password");
            $success = "Password changed successfully!";
        } else {
            $error = "Failed to change password: " . $error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle mark notification as read
if (isset($_GET['mark_read']) && $_SESSION['role'] === 'owner') {
    $id = $_GET['mark_read'];
    markNotificationAsRead($id);
    
    // Redirect to remove the GET parameter
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// Handle mark all notifications as read
if (isset($_GET['mark_all_read']) && $_SESSION['role'] === 'owner') {
    markAllNotificationsAsRead();
    
    // Redirect to remove the GET parameter
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Search and filter functionality
$searchTerm = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Get data for the view
$users = getUsers();
$customers = getCustomers();
$trials = getTrials();
$notifications = getNotifications();
$activityLog = getActivityLog();
$stats = getStatistics();

// Get unread notifications count
$unreadNotifications = 0;
foreach ($notifications as $notification) {
    if (!$notification['read']) {
        $unreadNotifications++;
    }
}

// Dark mode toggle
$darkMode = isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true';
if (isset($_GET['toggle_theme'])) {
    $darkMode = !$darkMode;
    setcookie('darkMode', $darkMode ? 'true' : 'false', time() + (86400 * 30), "/");
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . (strpos($_SERVER["REQUEST_URI"], '?') ? '&' : '?') . http_build_query(array_diff_key($_GET, ['toggle_theme' => ''])));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" href="style.css">

</head>
<body class="<?php echo $darkMode ? 'dark' : ''; ?>">
    <?php if (isset($db_error)): ?>
        <div class="container">
            <div class="alert alert-warning mt-5">
                <h4>Database Connection Warning</h4>
                <p><?php echo $db_error; ?></p>
                <p>The application is running in fallback mode using session storage. For production use, please configure your database correctly.</p>
            </div>
        </div>
    <?php endif; ?>

	<?php if (!isset($_SESSION['user'])): ?>
    <!-- Debug information -->
    <?php if (isset($_POST['login'])): ?>
        <div class="alert alert-info">
            <p>Login attempt for: <?php echo htmlspecialchars($_POST['username']); ?></p>
            <?php
            $users = getUsers();
            if (isset($users[$_POST['username']])) {
                echo "<p>User found in database</p>";
                echo "<p>Login attempts: " . $users[$_POST['username']]['login_attempts'] . "</p>";
                echo "<p>Password verification: " . (password_verify($_POST['password'], $users[$_POST['username']]['password']) ? 'Success' : 'Failed') . "</p>";
            } else {
                echo "<p>User not found in database</p>";
            }
            ?>
        </div>
    <?php endif; ?>
    <!-- Login Form -->
        <!-- Login Form -->
        <div class="container">
            <div class="login-container">
                <div class="login-logo">
                    <i class="fas fa-shield-alt"></i>
                    <h2 class="mt-3">Reseller Management System</h2>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['timeout'])): ?>
                    <div class="alert alert-warning">Your session has expired. Please log in again.</div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="position-relative">
                            <i class="fas fa-user position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
                            <input type="text" id="username" name="username" placeholder="Enter your username" required style="padding-left: 2.5rem;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="position-relative">
                            <i class="fas fa-lock position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required style="padding-left: 2.5rem;">
                        </div>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <a href="?toggle_theme=1" class="text-muted">
                        <i class="fas fa-<?php echo $darkMode ? 'sun' : 'moon'; ?>"></i>
                        Switch to <?php echo $darkMode ? 'Light' : 'Dark'; ?> Mode
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Dashboard -->
        <div class="dashboard">
            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <i class="fas fa-shield-alt"></i>
                    <h2>RMS</h2>
                </div>
                
                <ul class="sidebar-menu">
                    <li>
                        <a href="#dashboard" class="active" onclick="openTab('dashboard')">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    
                    <?php if ($_SESSION['role'] === 'owner'): ?>
                        <li>
                            <a href="#resellers" onclick="openTab('resellers')">
                                <i class="fas fa-users-cog"></i> Resellers
                            </a>
                        </li>
                    <?php endif; ?>
                    
					<li>
						<a href="#customers" onclick="openTab('customers')">
							<i class="fas fa-users"></i> Customers
						</a>
					</li>

					<?php if ($_SESSION['role'] === 'reseller'): ?>
						<li>
							<a href="#generate" onclick="openTab('generate')">
								<i class="fas fa-user-plus"></i> Generate Login
							</a>
						</li>
						<li>
							<a href="#trial" onclick="openTab('trial')">
								<i class="fas fa-hourglass-start"></i> Generate Trial
							</a>
						</li>
					<?php endif; ?>

                    
                    <?php if ($_SESSION['role'] === 'owner'): ?>
                        <li>
                            <a href="#activity" onclick="openTab('activity')">
                                <i class="fas fa-history"></i> Activity Log
                            </a>
                        </li>
                        
                        <li>
                            <a href="#reports" onclick="openTab('reports')">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li>
                        <a href="#profile" onclick="openTab('profile')">
                            <i class="fas fa-user-circle"></i> Profile
                        </a>
                    </li>
                    
                    <li>
                        <a href="#settings" onclick="openTab('settings')">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    
                    <li>
                        <a href="?logout=1">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <div class="header">
                    <div class="d-flex align-items-center">
                        <button id="sidebar-toggle" class="btn btn-sm btn-secondary d-lg-none me-3">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="mb-0">
                            <?php if ($_SESSION['role'] === 'owner'): ?>
                                Owner Dashboard
                            <?php else: ?>
                                Reseller Dashboard
                            <?php endif; ?>
                        </h1>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <?php if ($_SESSION['role'] === 'owner'): ?>
                            <div class="notifications-dropdown">
                                <button class="btn btn-sm btn-secondary position-relative" id="notifications-toggle">
                                    <i class="fas fa-bell"></i>
                                    <?php if ($unreadNotifications > 0): ?>
                                        <span class="notifications-badge"><?php echo $unreadNotifications; ?></span>
                                    <?php endif; ?>
                                </button>
                                
                                <div class="notifications-menu" id="notifications-menu">
                                    <div class="notifications-header">
                                        <h5 class="mb-0">Notifications</h5>
                                        <a href="?mark_all_read=1" class="font-sm">Mark all as read</a>
                                    </div>
                                    
                                    <div class="notifications-list">
                                        <?php if (empty($notifications)): ?>
                                            <div class="notification-item">
                                                <p class="mb-0 text-muted">No notifications</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($notifications as $notification): ?>
                                                <div class="notification-item <?php echo $notification['read'] ? '' : 'unread'; ?>">
                                                    <div class="notification-content">
                                                        <strong><?php echo $notification['user']; ?></strong>: <?php echo $notification['message']; ?>
                                                    </div>
                                                    <div class="notification-time">
                                                        <?php echo $notification['timestamp']; ?>
                                                        <?php if (!$notification['read']): ?>
                                                            <a href="?mark_read=<?php echo $notification['id']; ?>" class="ml-auto font-sm">Mark as read</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="notifications-footer">
                                        <a href="#activity" onclick="openTab('activity')">View all activity</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <a href="?toggle_theme=1" class="theme-toggle">
                            <i class="fas fa-<?php echo $darkMode ? 'sun' : 'moon'; ?>"></i>
                        </a>
                        
                        <div class="d-flex align-items-center">
                            <div class="me-3 text-right">
                                <div class="font-weight-bold"><?php echo $_SESSION['user']; ?></div>
                                <div class="font-sm text-muted"><?php echo ucfirst($_SESSION['role']); ?></div>
                            </div>
                            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; color: white;">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Dashboard Tab Content -->
                <div id="dashboard-content" class="tab-content active">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon primary">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['totalCustomers']; ?></div>
                                <div class="stat-label">Total Customers</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon success">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['activeCustomers']; ?></div>
                                <div class="stat-label">Active Customers</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon danger">
                                <i class="fas fa-user-times"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['expiredCustomers']; ?></div>
                                <div class="stat-label">Expired Customers</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon info">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['customersLast30Days']; ?></div>
                                <div class="stat-label">New Customers (30 days)</div>
                            </div>
                        </div>
                        
                        <?php if ($_SESSION['role'] === 'owner'): ?>
                            <div class="stat-card">
                                <div class="stat-icon warning">
                                    <i class="fas fa-users-cog"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo $stats['totalResellers']; ?></div>
                                    <div class="stat-label">Total Resellers</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>Recent Customers</h3>
                            <a href="#customers" onclick="openTab('customers')" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View All
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Created</th>
                                            <th>Expires</th>
                                            <th>Status</th>
                                            <?php if ($_SESSION['role'] === 'owner'): ?>
                                                <th>Reseller</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $recentCustomers = array_slice($customers, 0, 5);
                                        if (empty($recentCustomers)): 
                                        ?>
                                            <tr>
                                                <td colspan="<?php echo $_SESSION['role'] === 'owner' ? '7' : '6'; ?>" class="text-center">No customers found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentCustomers as $customer): ?>
                                                <tr>
                                                    <td><?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></td>
                                                    <td><?php echo $customer['username']; ?></td>
                                                    <td><?php echo $customer['email']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($customer['expires_at'])); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $customer['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                                            <?php echo ucfirst($customer['status']); ?>
                                                        </span>
                                                    </td>
                                                    <?php if ($_SESSION['role'] === 'owner'): ?>
                                                        <td><?php echo $customer['created_by']; ?></td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($_SESSION['role'] === 'owner'): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3>Reseller Performance</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Reseller</th>
                                                <th>Total Customers</th>
                                                <th>Active</th>
                                                <th>Expired</th>
                                                <th>Last 7 Days</th>
                                                <th>Last 30 Days</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($stats['resellerStats'])): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">No resellers found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($stats['resellerStats'] as $username => $resellerStat): ?>
                                                    <tr>
                                                        <td><?php echo $resellerStat['name']; ?></td>
                                                        <td><?php echo $resellerStat['totalCustomers']; ?></td>
                                                        <td><?php echo $resellerStat['activeCustomers']; ?></td>
                                                        <td><?php echo $resellerStat['expiredCustomers']; ?></td>
                                                        <td><?php echo $resellerStat['last7Days']; ?></td>
                                                        <td><?php echo $resellerStat['last30Days']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Customers Tab Content -->
                <div id="customers-content" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3>Manage Customers</h3>
                            <?php if ($_SESSION['role'] === 'reseller'): ?>
                                <button class="btn btn-primary" onclick="openModal('create-customer-modal')">
                                    <i class="fas fa-plus"></i> Add Customer
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="filter-bar">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="customer-search" placeholder="Search customers..." value="<?php echo $searchTerm; ?>">
                                </div>
                                
                                <div class="filter-item">
                                    <label for="status-filter">Status:</label>
                                    <select id="status-filter">
                                        <option value="">All</option>
                                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    </select>
                                </div>
                                
                                <button class="btn btn-secondary" id="apply-filters">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Password</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Created</th>
                                            <th>Expires</th>
                                            <th>Status</th>
                                            <?php if ($_SESSION['role'] === 'owner'): ?>
                                                <th>Reseller</th>
                                            <?php endif; ?>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Filter customers based on search term and pagination
                                        $filteredData = filterCustomers(
                                            $customers, 
                                            $searchTerm, 
                                            $currentPage, 
                                            $itemsPerPage, 
                                            $_SESSION['role'] === 'reseller', 
                                            $_SESSION['user'],
                                            $statusFilter
                                        );
                                        
                                        $filteredCustomers = $filteredData['customers'];
                                        $totalPages = $filteredData['totalPages'];
                                        
                                        if (empty($filteredCustomers)): 
                                        ?>
                                            <tr>
                                                <td colspan="<?php echo $_SESSION['role'] === 'owner' ? '10' : '9'; ?>" class="text-center">No customers found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($filteredCustomers as $customer): ?>
                                                <tr>
                                                    <td><?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></td>
                                                    <td><?php echo $customer['username']; ?></td>
                                                    <td><?php echo $customer['password']; ?></td>
                                                    <td><?php echo $customer['email']; ?></td>
                                                    <td><?php echo $customer['phone']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($customer['expires_at'])); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $customer['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                                            <?php echo ucfirst($customer['status']); ?>
                                                        </span>
                                                    </td>
                                                    <?php if ($_SESSION['role'] === 'owner'): ?>
                                                        <td><?php echo $customer['created_by']; ?></td>
                                                    <?php endif; ?>
                                                    <td>
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-sm btn-info" onclick="viewCustomer(<?php echo $customer['id']; ?>)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            
                                                            <?php if ($_SESSION['role'] === 'owner' || $customer['created_by'] === $_SESSION['user']): ?>
                                                                
                                                                <?php if ($customer['status'] === 'expired'): ?>
                                                                    <button class="btn btn-sm btn-success" onclick="renewCustomer(<?php echo $customer['id']; ?>)">
                                                                        <i class="fas fa-sync-alt"></i>
                                                                    </button>
                                                                <?php endif; ?>
                            
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li>
                                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>" class="<?php echo $i === $currentPage ? 'active' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Resellers Tab Content (Owner Only) -->
                <?php if ($_SESSION['role'] === 'owner'): ?>
                    <div id="resellers-content" class="tab-content">
                        <div class="card">
                            <div class="card-header">
                                <h3>Manage Resellers</h3>
                                <button class="btn btn-primary" onclick="openModal('create-reseller-modal')">
                                    <i class="fas fa-plus"></i> Add Reseller
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Full Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Created</th>
                                                <th>Last Login</th>
                                                <th>Status</th>
                                                <th>Customers</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $resellers = [];
                                            foreach ($users as $username => $user) {
                                                if (isset($user['role']) && $user['role'] === 'reseller') {
                                                    $resellers[$username] = $user;
                                                }
                                            }
                                            
                                            if (empty($resellers)): 
                                            ?>
                                                <tr>
                                                    <td colspan="9" class="text-center">No resellers found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($resellers as $username => $reseller): ?>
                                                    <tr>
                                                        <td><?php echo $username; ?></td>
                                                        <td><?php echo $reseller['full_name']; ?></td>
                                                        <td><?php echo $reseller['email']; ?></td>
                                                        <td><?php echo $reseller['phone']; ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($reseller['created_at'])); ?></td>
                                                        <td><?php echo $reseller['last_login'] ? date('M d, Y H:i', strtotime($reseller['last_login'])) : 'Never'; ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $reseller['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                                                <?php echo ucfirst($reseller['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $resellerCustomers = 0;
                                                            foreach ($customers as $customer) {
                                                                if ($customer['created_by'] === $username) {
                                                                    $resellerCustomers++;
                                                                }
                                                            }
                                                            echo $resellerCustomers;
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-2">
                                                                <button class="btn btn-sm btn-primary" onclick="editReseller('<?php echo $username; ?>')">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-warning" onclick="resetResellerPassword('<?php echo $username; ?>')">
                                                                    <i class="fas fa-key"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger" onclick="deleteReseller('<?php echo $username; ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Generate Login Tab Content (Reseller Only) -->
                <?php if ($_SESSION['role'] === 'reseller'): ?>
                    <div id="generate-content" class="tab-content">
                        <div class="card">
                            <div class="card-header">
                                <h3>Generate Customer Login</h3>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="first_name">First Name</label>
                                                <input type="text" id="first_name" name="first_name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="last_name">Last Name</label>
                                                <input type="text" id="last_name" name="last_name" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email">Email</label>
                                                <input type="email" id="email" name="email" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone">Phone</label>
                                                <input type="tel" id="phone" name="phone" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="notes">Notes (Optional)</label>
                                        <textarea id="notes" name="notes" rows="3"></textarea>
                                    </div>
                                    
                                                        <button type="submit" name="create_customer" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Generate Login
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Customer Accounts Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h3>My Customer Accounts</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Created</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $userCustomers = array_filter($customers, function($customer) {
                            return $customer['created_by'] === $_SESSION['user'];
                        });
                        
                        if (empty($userCustomers)): 
                        ?>
                            <tr>
                                <td colspan="9" class="text-center">No customer accounts found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($userCustomers as $customer): ?>
                                <tr>
                                    <td><?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></td>
                                    <td><?php echo $customer['username']; ?></td>
                                    <td><?php echo $customer['password']; ?></td>
                                    <td><?php echo $customer['email']; ?></td>
                                    <td><?php echo $customer['phone']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($customer['expires_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $customer['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo ucfirst($customer['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-info" onclick="viewCustomer(<?php echo $customer['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($customer['status'] === 'expired'): ?>
                                                <button class="btn btn-sm btn-success" onclick="renewCustomer(<?php echo $customer['id']; ?>)">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
                <?php endif; ?>
                <!-- Generate Trial Tab Content (Reseller Only) -->
                <?php if ($_SESSION['role'] === 'reseller'): ?>
                    <div id="trial-content" class="tab-content">
                        <div class="card">
                            <div class="card-header">
                                <h3>Generate Trial Account</h3>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="trial_first_name">First Name</label>
                                                <input type="text" id="trial_first_name" name="first_name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="trial_last_name">Last Name</label>
                                                <input type="text" id="trial_last_name" name="last_name" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="trial_email">Email</label>
                                                <input type="email" id="trial_email" name="email" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="trial_phone">Phone</label>
                                                <input type="tel" id="trial_phone" name="phone" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="trial_notes">Notes (Optional)</label>
                                        <textarea id="trial_notes" name="notes" rows="3"></textarea>
                                    </div>
                                    
                                    <button type="submit" name="create_trial" class="btn btn-primary">
                                        <i class="fas fa-hourglass-start"></i> Generate Trial
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- View Trials Section -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>My Trial Accounts</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Password</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Created</th>
                                            <th>Expires</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $userTrials = array_filter($trials, function($trial) {
                                            return $trial['created_by'] === $_SESSION['user'];
                                        });
                                        
                                        if (empty($userTrials)): 
                                        ?>
                                            <tr>
                                                <td colspan="9" class="text-center">No trial accounts found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($userTrials as $trial): ?>
                                                <tr>
                                                    <td><?php echo $trial['first_name'] . ' ' . $trial['last_name']; ?></td>
                                                    <td><?php echo $trial['username']; ?></td>
                                                    <td><?php echo $trial['password']; ?></td>
                                                    <td><?php echo $trial['email']; ?></td>
                                                    <td><?php echo $trial['phone']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($trial['created_at'])); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($trial['expires_at'])); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $trial['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                                            <?php echo ucfirst($trial['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info" onclick="viewTrial(<?php echo $trial['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($trial['status'] === 'expired'): ?>
                                                            <button class="btn btn-sm btn-success" onclick="convertTrialToCustomer(<?php echo $trial['id']; ?>)">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                

                
                <!-- Activity Log Tab Content (Owner Only) -->
                <?php if ($_SESSION['role'] === 'owner'): ?>
                    <div id="activity-content" class="tab-content">
                        <div class="card">
                            <div class="card-header">
                                <h3>Activity Log</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Details</th>
                                                <th>IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($activityLog)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No activity found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($activityLog as $log): ?>
                                                    <tr>
                                                        <td><?php echo $log['timestamp']; ?></td>
                                                        <td><?php echo $log['user']; ?></td>
                                                        <td><?php echo $log['action']; ?></td>
                                                        <td><?php echo $log['details']; ?></td>
                                                        <td><?php echo $log['ip_address']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Reports Tab Content (Owner Only) -->
                <?php if ($_SESSION['role'] === 'owner'): ?>
                    <div id="reports-content" class="tab-content">
                        <div class="card">
                            <div class="card-header">
                                <h3>Reports</h3>
                            </div>
                            <div class="card-body">
                                <div class="tabs">
                                    <div class="tab active" onclick="openReportTab('customer-growth')">Customer Growth</div>
                                    <div class="tab" onclick="openReportTab('reseller-performance')">Reseller Performance</div>
                                    <div class="tab" onclick="openReportTab('expiration-forecast')">Expiration Forecast</div>
                                </div>
                                
                                <div id="customer-growth-content" class="tab-content active">
                                    <h4>Customer Growth</h4>
                                    <p>This report shows the growth of customers over time.</p>
                                    
                                    <div class="chart-container">
                                        <div class="text-center text-muted">
                                            <i class="fas fa-chart-line fa-3x mb-3"></i>
                                            <p>Chart visualization would be displayed here.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="stats-grid">
                                        <div class="stat-card">
                                            <div class="stat-icon primary">
                                                <i class="fas fa-users"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo $stats['totalCustomers']; ?></div>
                                                <div class="stat-label">Total Customers</div>
                                            </div>
                                        </div>
                                        
                                        <div class="stat-card">
                                            <div class="stat-icon info">
                                                <i class="fas fa-calendar-week"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo $stats['customersLast7Days']; ?></div>
                                                <div class="stat-label">Last 7 Days</div>
                                            </div>
                                        </div>
                                        
                                        <div class="stat-card">
                                            <div class="stat-icon success">
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo $stats['customersLast30Days']; ?></div>
                                                <div class="stat-label">Last 30 Days</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="reseller-performance-content" class="tab-content">
                                    <h4>Reseller Performance</h4>
                                    <p>This report shows the performance of each reseller.</p>
                                    
                                    <div class="chart-container">
                                        <div class="text-center text-muted">
                                            <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                            <p>Chart visualization would be displayed here.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Reseller</th>
                                                    <th>Total Customers</th>
                                                    <th>Active</th>
                                                    <th>Expired</th>
                                                    <th>Last 7 Days</th>
                                                    <th>Last 30 Days</th>
                                                    <th>Performance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($stats['resellerStats'])): ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center">No resellers found</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($stats['resellerStats'] as $username => $resellerStat): ?>
                                                        <tr>
                                                            <td><?php echo $resellerStat['name']; ?></td>
                                                            <td><?php echo $resellerStat['totalCustomers']; ?></td>
                                                            <td><?php echo $resellerStat['activeCustomers']; ?></td>
                                                            <td><?php echo $resellerStat['expiredCustomers']; ?></td>
                                                            <td><?php echo $resellerStat['last7Days']; ?></td>
                                                            <td><?php echo $resellerStat['last30Days']; ?></td>
                                                            <td>
                                                                <div class="progress-bar">
                                                                    <?php 
                                                                    $performance = 0;
                                                                    if ($resellerStat['totalCustomers'] > 0) {
                                                                        $performance = ($resellerStat['activeCustomers'] / $resellerStat['totalCustomers']) * 100;
                                                                    }
                                                                    $performanceClass = 'success';
                                                                    if ($performance < 70) $performanceClass = 'warning';
                                                                    if ($performance < 50) $performanceClass = 'danger';
                                                                    ?>
                                                                    <div class="progress-bar-fill <?php echo $performanceClass; ?>" style="width: <?php echo $performance; ?>%"></div>
                                                                </div>
                                                                <div class="text-right font-sm mt-1"><?php echo round($performance); ?>%</div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div id="expiration-forecast-content" class="tab-content">
                                    <h4>Expiration Forecast</h4>
                                    <p>This report shows upcoming customer expirations.</p>
                                    
                                    <div class="chart-container">
                                        <div class="text-center text-muted">
                                            <i class="fas fa-chart-pie fa-3x mb-3"></i>
                                            <p>Chart visualization would be displayed here.</p>
                                        </div>
                                    </div>
                                    
                                    <?php
                                    $expiringCustomers = [
                                        'next7Days' => 0,
                                        'next30Days' => 0,
                                        'next90Days' => 0
                                    ];
                                    
                                    foreach ($customers as $customer) {
                                        if ($customer['status'] === 'active') {
                                            $expirationDate = strtotime($customer['expires_at']);
                                            $now = time();
                                            $daysDiff = round(($expirationDate - $now) / (60 * 60 * 24));
                                            
                                            if ($daysDiff <= 7) {
                                                $expiringCustomers['next7Days']++;
                                            } elseif ($daysDiff <= 30) {
                                                $expiringCustomers['next30Days']++;
                                            } elseif ($daysDiff <= 90) {
                                                $expiringCustomers['next90Days']++;
                                            }
                                        }
                                    }
                                    ?>
                                    
                                    <div class="stats-grid">
                                        <div class="stat-card">
                                            <div class="stat-icon danger">
                                                <i class="fas fa-exclamation-circle"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo $expiringCustomers['next7Days']; ?></div>
                                                <div class="stat-label">Expiring in 7 Days</div>
                                            </div>
                                        </div>
                                        
                                        <div class="stat-card">
                                            <div class="stat-icon warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo $expiringCustomers['next30Days']; ?></div>
                                                <div class="stat-label">Expiring in 30 Days</div>
                                            </div>
                                        </div>
                                        
                                        <div class="stat-card">
                                            <div class="stat-icon info">
                                                <i class="fas fa-info-circle"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo $expiringCustomers['next90Days']; ?></div>
                                                <div class="stat-label">Expiring in 90 Days</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Tab Content -->
                <div id="profile-content" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3>Profile Information</h3>
                        </div>
                        <div class="card-body">
                            <?php 
                            $currentUser = $users[$_SESSION['user']];
                            ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" value="<?php echo $_SESSION['user']; ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Role</label>
                                        <input type="text" value="<?php echo ucfirst($_SESSION['role']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Full Name</label>
                                        <input type="text" value="<?php echo $currentUser['full_name']; ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" value="<?php echo $currentUser['email']; ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Phone</label>
                                        <input type="tel" value="<?php echo $currentUser['phone']; ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Last Login</label>
                                        <input type="text" value="<?php echo $currentUser['last_login'] ? date('M d, Y H:i', strtotime($currentUser['last_login'])) : 'Never'; ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Account Created</label>
                                <input type="text" value="<?php echo date('M d, Y', strtotime($currentUser['created_at'])); ?>" readonly>
                            </div>
                            
                            <button class="btn btn-primary" onclick="openModal('change-password-modal')">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Tab Content -->
                <div id="settings-content" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3>Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Theme</label>
                                <div class="d-flex gap-3 mt-2">
                                    <a href="?toggle_theme=1" class="btn <?php echo $darkMode ? 'btn-secondary' : 'btn-primary'; ?>">
                                        <i class="fas fa-sun"></i> Light Mode
                                    </a>
                                    <a href="?toggle_theme=1" class="btn <?php echo $darkMode ? 'btn-primary' : 'btn-secondary'; ?>">
                                        <i class="fas fa-moon"></i> Dark Mode
                                    </a>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Session Timeout</label>
                                <p class="text-muted">Your session will automatically expire after 30 minutes of inactivity.</p>
                            </div>
                            
                            <div class="form-group">
                                <label>System Information</label>
                                <div class="table-responsive">
                                    <table>
                                        <tbody>
                                            <tr>
                                                <td>PHP Version</td>
                                                <td><?php echo phpversion(); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Server</td>
                                                <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Database</td>
                                                <td><?php echo $conn ? 'MySQL ' . $conn->server_info : 'Session Storage (Fallback)'; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Current Time</td>
                                                <td><?php echo date('Y-m-d H:i:s'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modals -->
        
        <!-- Create Customer Modal -->
        <div class="modal" id="create-customer-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h4>Create Customer</h4>
                    <button type="button" class="close" onclick="closeModal('create-customer-modal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modal_first_name">First Name</label>
                                    <input type="text" id="modal_first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modal_last_name">Last Name</label>
                                    <input type="text" id="modal_last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modal_email">Email</label>
                                    <input type="email" id="modal_email" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modal_phone">Phone</label>
                                    <input type="tel" id="modal_phone" name="phone" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="modal_notes">Notes (Optional)</label>
                            <textarea id="modal_notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('create-customer-modal')">Cancel</button>
                            <button type="submit" name="create_customer" class="btn btn-primary">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- View Customer Modal -->
        <div class="modal" id="view-customer-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h4>Customer Details</h4>
                    <button type="button" class="close" onclick="closeModal('view-customer-modal')">&times;</button>
                </div>
                <div class="modal-body" id="view-customer-content">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('view-customer-modal')">Close</button>
                </div>
            </div>
        </div>
        
        <!-- View Trial Modal -->
        <div class="modal" id="view-trial-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h4>Trial Account Details</h4>
                    <button type="button" class="close" onclick="closeModal('view-trial-modal')">&times;</button>
                </div>
                <div class="modal-body" id="view-trial-content">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('view-trial-modal')">Close</button>
                </div>
            </div>
        </div>
        
        <!-- Edit Customer Modal -->
        <div class="modal" id="edit-customer-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h4>Edit Customer</h4>
                    <button type="button" class="close" onclick="closeModal('edit-customer-modal')">&times;</button>
                </div>
                <div class="modal-body" id="edit-customer-content">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
        
        <!-- Renew Customer Modal -->
        <div class="modal" id="renew-customer-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h4>Renew Customer</h4>
                    <button type="button" class="close" onclick="closeModal('renew-customer-modal')">&times;</button>
                </div>
                <div class="modal-body" id="renew-customer-content">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
        
        <!-- Delete Customer Modal -->
        <div class="modal" id="delete-customer-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h4>Delete Customer</h4>
                    <button type="button" class="close" onclick="closeModal('delete-customer-modal')">&times;</button>
                </div>
                <div class="modal-body" id="delete-customer-content">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
        
        <!-- Create Reseller Modal (Owner Only) -->
        <?php if ($_SESSION['role'] === 'owner'): ?>
            <div class="modal" id="create-reseller-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>Create Reseller</h4>
                        <button type="button" class="close" onclick="closeModal('create-reseller-modal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reseller_name">Username</label>
                                        <input type="text" id="reseller_name" name="reseller_name" required>
                                    </div>
                  
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reseller_password">Password</label>
                                        <input type="password" id="reseller_password" name="reseller_password" required>
                                        <small class="text-muted">Must be at least 8 characters with uppercase, lowercase, and numbers.</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="full_name">Full Name</label>
                                        <input type="text" id="full_name" name="full_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('create-reseller-modal')">Cancel</button>
                                <button type="submit" name="create_reseller" class="btn btn-primary">Create</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit Reseller Modal -->
            <div class="modal" id="edit-reseller-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>Edit Reseller</h4>
                        <button type="button" class="close" onclick="closeModal('edit-reseller-modal')">&times;</button>
                    </div>
                    <div class="modal-body" id="edit-reseller-content">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
            
            <!-- Reset Reseller Password Modal -->
            <div class="modal" id="reset-reseller-password-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>Reset Reseller Password</h4>
                        <button type="button" class="close" onclick="closeModal('reset-reseller-password-modal')">&times;</button>
                    </div>
                    <div class="modal-body" id="reset-reseller-password-content">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
            
            <!-- Delete Reseller Modal -->
            <div class="modal" id="delete-reseller-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>Delete Reseller</h4>
                        <button type="button" class="close" onclick="closeModal('delete-reseller-modal')">&times;</button>
                    </div>
                    <div class="modal-body" id="delete-reseller-content">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Change Password Modal -->
        <div class="modal" id="change-password-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h4>Change Password</h4>
                    <button type="button" class="close" onclick="closeModal('change-password-modal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <small class="text-muted">Must be at least 8 characters with uppercase, lowercase, and numbers.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('change-password-modal')">Cancel</button>
                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- JavaScript -->
        <script>
            console.log('JavaScript loaded successfully');
            
            // Tab Navigation
            function openTab(tabName) {
                console.log('Opening tab:', tabName);
                // Hide all tab content
                const tabContents = document.querySelectorAll('.tab-content');
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                
                // Show selected tab content
                document.getElementById(tabName + '-content').classList.add('active');
                
                // Update active tab in sidebar
                const tabLinks = document.querySelectorAll('.sidebar-menu a');
                tabLinks.forEach(link => {
                    link.classList.remove('active');
                });
                
                // Find the link with href="#tabName" and add active class
                const activeLink = document.querySelector(`.sidebar-menu a[href="#${tabName}"]`);
                if (activeLink) {
                    activeLink.classList.add('active');
                }
                
                // Close sidebar on mobile after tab selection
                if (window.innerWidth < 992) {
                    document.getElementById('sidebar').classList.remove('show');
                }
                
                // Update URL hash
                window.location.hash = tabName;
            }
            
            // Report Tab Navigation
            function openReportTab(tabName) {
                // Hide all report tab content
                const tabContents = document.querySelectorAll('#reports-content .tab-content');
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                
                // Show selected report tab content
                document.getElementById(tabName + '-content').classList.add('active');
                
                // Update active tab
                const tabLinks = document.querySelectorAll('#reports-content .tab');
                tabLinks.forEach(link => {
                    link.classList.remove('active');
                });
                
                // Find the clicked tab and add active class
                event.currentTarget.classList.add('active');
            }
            
            // Modal Functions
            function openModal(modalId) {
                document.getElementById(modalId).classList.add('show');
            }
            
            function closeModal(modalId) {
                document.getElementById(modalId).classList.remove('show');
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (event.target === modal) {
                        modal.classList.remove('show');
                    }
                });
            });
            
            // Customer Actions
            function viewCustomer(id) {
                // In a real application, this would fetch customer details via AJAX
                // For this example, we'll use the existing data
                const customers = <?php echo json_encode($customers); ?>;
                let customer = null;
                
                for (let i = 0; i < customers.length; i++) {
                    if (customers[i].id == id) {
                        customer = customers[i];
                        break;
                    }
                }
                
                if (customer) {
                    const content = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" value="${customer.first_name}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" value="${customer.last_name}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" value="${customer.username}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password</label>
                                    <input type="text" value="${customer.password}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" value="${customer.email}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" value="${customer.phone}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Created</label>
                                    <input type="text" value="${customer.created_at}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Expires</label>
                                    <input type="text" value="${customer.expires_at}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <input type="text" value="${customer.status}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Created By</label>
                                    <input type="text" value="${customer.created_by}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea readonly rows="3">${customer.notes || ''}</textarea>
                        </div>
                    `;
                    
                    document.getElementById('view-customer-content').innerHTML = content;
                    openModal('view-customer-modal');
                }
            }
            
            function renewCustomer(id) {
                // In a real application, this would fetch customer details via AJAX
                // For this example, we'll use the existing data
                const customers = <?php echo json_encode($customers); ?>;
                let customer = null;
                
                for (let i = 0; i < customers.length; i++) {
                    if (customers[i].id == id) {
                        customer = customers[i];
                        break;
                    }
                }
                
                if (customer) {
                    const content = `
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="customer_id" value="${customer.id}">

                            <p>Are you sure you want to renew the account for <strong>${customer.first_name} ${customer.last_name}</strong>?</p>
                            <p>This will extend the expiration date by 1 month and set the status to active.</p>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('renew-customer-modal')">Cancel</button>
                                <button type="submit" name="renew_customer" class="btn btn-success">Renew</button>
                            </div>
                        </form>
                    `;
                    
                    document.getElementById('renew-customer-content').innerHTML = content;
                    openModal('renew-customer-modal');
                }
            }
            
            function convertTrialToCustomer(id) {
                const trials = <?php echo json_encode($trials); ?>;
                let trial = null;
                
                for (let i = 0; i < trials.length; i++) {
                    if (trials[i].id == id) {
                        trial = trials[i];
                        break;
                    }
                }
                
                if (trial) {
                    const content = `
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="trial_id" value="${trial.id}">

                            <p>Are you sure you want to convert the trial account for <strong>${trial.first_name} ${trial.last_name}</strong> to a customer account?</p>
                            <p>This will create a 30-day subscription and move the account from trials to customers.</p>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('renew-customer-modal')">Cancel</button>
                                <button type="submit" name="convert_trial_to_customer" class="btn btn-success">Convert to Customer</button>
                            </div>
                        </form>
                    `;
                    
                    document.getElementById('renew-customer-content').innerHTML = content;
                    openModal('renew-customer-modal');
                }
            }
            
            // Trial Actions
            function viewTrial(id) {
                // In a real application, this would fetch trial details via AJAX
                // For this example, we'll use the existing data
                const trials = <?php echo json_encode($trials); ?>;
                let trial = null;
                
                for (let i = 0; i < trials.length; i++) {
                    if (trials[i].id == id) {
                        trial = trials[i];
                        break;
                    }
                }
                
                if (trial) {
                    const content = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" value="${trial.first_name}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" value="${trial.last_name}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" value="${trial.username}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password</label>
                                    <input type="text" value="${trial.password}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" value="${trial.email}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" value="${trial.phone}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Created</label>
                                    <input type="text" value="${trial.created_at}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Expires</label>
                                    <input type="text" value="${trial.expires_at}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <input type="text" value="${trial.status}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Created By</label>
                                    <input type="text" value="${trial.created_by}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea readonly rows="3">${trial.notes || ''}</textarea>
                        </div>
                    `;
                    
                    document.getElementById('view-trial-content').innerHTML = content;
                    openModal('view-trial-modal');
                }
            }
            
            // Reseller Actions (Owner Only)
            <?php if ($_SESSION['role'] === 'owner'): ?>
                function editReseller(username) {
                    // In a real application, this would fetch reseller details via AJAX
                    // For this example, we'll use the existing data
                    const users = <?php echo json_encode($users); ?>;
                    const reseller = users[username];
                    
                    if (reseller) {
                        const content = `
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="reseller_name" value="${username}">
                                
                                <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" value="${username}" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_full_name">Full Name</label>
                                    <input type="text" id="edit_full_name" name="full_name" value="${reseller.full_name}" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_email">Email</label>
                                    <input type="email" id="edit_email" name="email" value="${reseller.email}" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_phone">Phone</label>
                                    <input type="tel" id="edit_phone" name="phone" value="${reseller.phone}" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_status">Status</label>
                                    <select id="edit_status" name="status" required>
                                        <option value="active" ${reseller.status === 'active' ? 'selected' : ''}>Active</option>
                                        <option value="inactive" ${reseller.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-reseller-modal')">Cancel</button>
                                    <button type="submit" name="update_reseller" class="btn btn-primary">Update</button>
                                </div>
                            </form>
                        `;
                        
                        document.getElementById('edit-reseller-content').innerHTML = content;
                        openModal('edit-reseller-modal');
                    }
                }
                
                function resetResellerPassword(username) {
                    // In a real application, this would fetch reseller details via AJAX
                    // For this example, we'll use the existing data
                    const users = <?php echo json_encode($users); ?>;
                    const reseller = users[username];
                    
                    if (reseller) {
                        const content = `
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="reseller_name" value="${username}">
                                
                                <p>Reset password for reseller <strong>${reseller.full_name}</strong> (${username})</p>
                                
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                    <small class="text-muted">Must be at least 8 characters with uppercase, lowercase, and numbers.</small>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('reset-reseller-password-modal')">Cancel</button>
                                    <button type="submit" name="reset_reseller_password" class="btn btn-warning">Reset Password</button>
                                </div>
                            </form>
                        `;
                        
                        document.getElementById('reset-reseller-password-content').innerHTML = content;
                        openModal('reset-reseller-password-modal');
                    }
                }
                
                function deleteReseller(username) {
                    // In a real application, this would fetch reseller details via AJAX
                    // For this example, we'll use the existing data
                    const users = <?php echo json_encode($users); ?>;
                    const reseller = users[username];
                    
                    if (reseller) {
                        const content = `
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="reseller_name" value="${username}">
                                
                                <p>Are you sure you want to delete the reseller <strong>${reseller.full_name}</strong> (${username})?</p>
                                <p>All customers created by this reseller will be reassigned to you.</p>
                                <p class="text-danger">This action cannot be undone!</p>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('delete-reseller-modal')">Cancel</button>
                                    <button type="submit" name="delete_reseller" class="btn btn-danger">Delete</button>
                                </div>
                            </form>
                        `;
                        
                        document.getElementById('delete-reseller-content').innerHTML = content;
                        openModal('delete-reseller-modal');
                    }
                }
            <?php endif; ?>
            
            // Notifications Toggle
            const notificationsToggle = document.getElementById('notifications-toggle');
            const notificationsMenu = document.getElementById('notifications-menu');
            
            if (notificationsToggle && notificationsMenu) {
                notificationsToggle.addEventListener('click', function(event) {
                    event.stopPropagation();
                    notificationsMenu.classList.toggle('show');
                });
                
                document.addEventListener('click', function(event) {
                    if (!notificationsMenu.contains(event.target) && event.target !== notificationsToggle) {
                        notificationsMenu.classList.remove('show');
                    }
                });
            }
            
            // Sidebar Toggle for Mobile
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
                
                document.addEventListener('click', function(event) {
                    if (window.innerWidth < 992 && !sidebar.contains(event.target) && event.target !== sidebarToggle) {
                        sidebar.classList.remove('show');
                    }
                });
            }
            
            // Search and Filter
            const customerSearch = document.getElementById('customer-search');
            const statusFilter = document.getElementById('status-filter');
            const applyFilters = document.getElementById('apply-filters');
            
            if (applyFilters) {
                applyFilters.addEventListener('click', function() {
                    const searchTerm = customerSearch.value;
                    const status = statusFilter.value;
                    
                    window.location.href = `?search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(status)}`;
                });
            }
            
            // Initialize based on URL hash
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM loaded, initializing tabs');
                
                // Ensure default tab is active if no hash
                const hash = window.location.hash.substring(1);
                if (hash) {
                    console.log('Opening tab from hash:', hash);
                    openTab(hash);
                } else {
                    console.log('No hash found, opening dashboard');
                    openTab('dashboard');
                }
                
                // Add click event listeners to sidebar links
                const sidebarLinks = document.querySelectorAll('.sidebar-menu a[href^="#"]');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const tabName = this.getAttribute('href').substring(1);
                        console.log('Sidebar link clicked:', tabName);
                        openTab(tabName);
                    });
                });
            });
        </script>
    <?php endif; ?>
</body>
</html>

