<?php
session_start();
require_once 'viewpro-config.php';
require_once 'viewpro-functions.php';
require_once 'viewpro-email.php';

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time();

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_window = 3600; // 1 hour
$max_requests = 10;

$success = '';
$error = '';
$section = isset($_GET['section']) ? $_GET['section'] : 'home';

if (isset($_POST['action']) && $_POST['action'] === 'lookup_username' && isset($_POST['username'])) {
    header('Content-Type: application/json');
    
    $username = trim($_POST['username']);
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Username is required']);
        exit;
    }
    
    $user = getViewProUserByUsername($username);
    if ($user) {
        echo json_encode([
            'success' => true,
            'user' => [
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'email' => $user['email'] ?? '',
                'username' => $username
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Username not found']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission. Please try again.";
        error_log("CSRF token mismatch from IP: " . $user_ip);
    } else if (!checkRateLimit($user_ip)) {
        $error = "Too many requests. Please try again later.";
        error_log("Rate limit exceeded for IP: " . $user_ip);
    } else if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'free_trial':
                handleFreeTrial();
                break;
            case 'subscribe':
                handleSubscription();
                break;
            case 'renew':
                handleRenewal();
                break;
            case 'refer':
                handleReferral();
                break;
            case 'contact':
                handleContact();
                break;
        }
    }
}

function handleFreeTrial() {
    global $success, $error;
    
    $email = sanitizeInput($_POST['email']);
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);
    
    // Validate inputs
    if (empty($email) || empty($firstName) || empty($lastName) || empty($phone)) {
        $error = "All fields are required.";
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
        return;
    }
    
    $existingUser = getViewProUserByEmail($email);
    if ($existingUser) {
        $error = "An account with this email already exists.";
        return;
    }
    
    $result = createViewProTrial($email, $firstName, $lastName, $phone);
    
    if (is_array($result)) {
        $userId = saveViewProUser($email, $firstName, $lastName, $phone, $result['username'], $result['password'], $result['subscriber_id'], 'trial');
        
        if ($userId) {
            $emailSent = sendTrialEmail($email, $firstName, $result['username'], $result['password']);
            
            if ($emailSent) {
                $success = "Your 1-day trial account has been created! Check your email for login credentials.";
            } else {
                $success = "Trial account created successfully! Username: " . $result['username'] . ", Password: " . $result['password'];
            }
        } else {
            $error = "Account created but failed to save to database. Please contact support.";
        }
    } else {
        switch ($result) {
            case 'already_exist':
                $error = "An account with this email already exists in our system.";
                break;
            case 'auth_failed':
                $error = "Authentication failed. Please try again later.";
                break;
            case 'token_failed':
                $error = "Failed to obtain access token. Please try again later.";
                break;
            case 'subscriber_creation_failed':
                $error = "Failed to create account. Please try again later.";
                break;
            default:
                $error = "An error occurred while creating your trial account. Please try again.";
        }
    }
}

function handleSubscription() {
    global $success, $error, $viewpro_settings;
    
    $email = sanitizeInput($_POST['email']);
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);
    $referredBy = isset($_POST['referred_by']) ? sanitizeInput($_POST['referred_by']) : null;
    
    // Validate inputs
    if (empty($email) || empty($firstName) || empty($lastName) || empty($phone)) {
        $error = "All fields are required.";
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
        return;
    }
    
    $existingUser = getViewProUserByEmail($email);
    if ($existingUser) {
        $error = "An account with this email already exists.";
        return;
    }
    
    $referrerId = null;
    if ($referredBy) {
        $referrer = getViewProUserByEmail($referredBy);
        if ($referrer) {
            $referrerId = $referrer['id'];
            saveViewProReferral($referrerId, $email);
        }
    }
    
    $result = createViewProSubscription($email, $firstName, $lastName, $phone);
    
    if (is_array($result)) {
        $userId = saveViewProUser($email, $firstName, $lastName, $phone, $result['username'], $result['password'], $result['subscriber_id'], 'subscription', $referrerId);
        
        if ($userId) {
            if ($referrerId) {
                completeViewProReferral($email, $userId);
            }
            
            $expiresDate = date('F j, Y', strtotime('+30 days'));
            $emailSent = sendSubscriptionEmail($email, $firstName, $result['username'], $result['password'], $expiresDate);
            
            if ($emailSent) {
                $success = "Your subscription has been activated! Check your email for login credentials.";
            } else {
                $success = "Subscription activated successfully! Username: " . $result['username'] . ", Password: " . $result['password'];
            }
        } else {
            $error = "Account created but failed to save to database. Please contact support.";
        }
    } else {
        switch ($result) {
            case 'already_exist':
                $error = "An account with this email already exists in our system.";
                break;
            case 'auth_failed':
                $error = "Authentication failed. Please try again later.";
                break;
            case 'token_failed':
                $error = "Failed to obtain access token. Please try again later.";
                break;
            case 'subscriber_creation_failed':
                $error = "Failed to create account. Please try again later.";
                break;
            default:
                $error = "An error occurred while creating your subscription. Please try again.";
        }
    }
}

function handleRenewal() {
    global $success, $error, $viewpro_settings;
    
    $username = sanitizeInput($_POST['username']);
    
    if (empty($username)) {
        $error = "Username is required.";
        return;
    }
    
    $result = renewViewProAccount($username);
    
    if ($result === 'success') {
        $newExpiresDate = date('F j, Y', strtotime('+30 days'));
        $success = "Account renewed successfully! Your subscription has been extended by 30 days. New expiration date: " . $newExpiresDate;
    } else {
        $error = "Failed to renew account: " . $result;
    }
}

function handleReferral() {
    global $success, $error;
    
    $referrerEmail = sanitizeInput($_POST['referrer_email']);
    $friendEmail = sanitizeInput($_POST['friend_email']);
    
    if (empty($referrerEmail) || empty($friendEmail)) {
        $error = "Both email addresses are required.";
        return;
    }
    
    if (!filter_var($referrerEmail, FILTER_VALIDATE_EMAIL) || !filter_var($friendEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter valid email addresses.";
        return;
    }
    
    $referrer = getViewProUserByEmail($referrerEmail);
    if (!$referrer) {
        $error = "Referrer account not found.";
        return;
    }
    
    $existingFriend = getViewProUserByEmail($friendEmail);
    if ($existingFriend) {
        $error = "Your friend already has an account with us.";
        return;
    }
    
    $saved = saveViewProReferral($referrer['id'], $friendEmail);
    
    if ($saved) {
        $success = "Referral saved! When your friend signs up, you'll both get rewards.";
    } else {
        $error = "Failed to save referral. Please try again.";
    }
}

function handleContact() {
    global $success, $error;
    
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "All fields are required.";
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
        return;
    }
    
    $contactEmailSent = sendViewProEmail('support@viewproplus.com', 'Contact Form: ' . $subject, 
        "Name: {$name}<br>Email: {$email}<br>Subject: {$subject}<br><br>Message:<br>{$message}");
    
    if ($contactEmailSent) {
        $success = "Thank you for contacting us! We'll get back to you soon.";
    } else {
        $success = "Thank you for your message. We'll get back to you soon.";
    }
}

function checkRateLimit($ip) {
    global $rate_limit_window, $max_requests;
    
    $conn = getViewProConnection();
    if (!$conn) {
        error_log("Database connection failed for rate limiting");
        return true; // Allow request if DB is down
    }
    
    $timestamp = time();
    $window_start = $timestamp - $rate_limit_window;
    
    $createTable = "CREATE TABLE IF NOT EXISTS viewpro_rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        timestamp INT NOT NULL,
        INDEX idx_ip_timestamp (ip, timestamp)
    )";
    $conn->query($createTable);
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM viewpro_rate_limits WHERE ip = ? AND timestamp > ?");
    $stmt->bind_param("si", $ip, $window_start);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    if ($count >= $max_requests) {
        $conn->close();
        return false;
    }
    
    $stmt = $conn->prepare("INSERT INTO viewpro_rate_limits (ip, timestamp) VALUES (?, ?)");
    $stmt->bind_param("si", $ip, $timestamp);
    $stmt->execute();
    $stmt->close();
    
    $cleanupStmt = $conn->prepare("DELETE FROM viewpro_rate_limits WHERE timestamp < ?");
    $cleanupStmt->bind_param("i", $window_start);
    $cleanupStmt->execute();
    $cleanupStmt->close();
    
    $conn->close();
    return true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ViewProPlus - Premium Streaming Experience</title>
    <link rel="stylesheet" href="viewpro-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header and Navigation -->
    <header class="header">
        <nav class="nav container">
            <a href="?section=home" class="logo">ViewProPlus</a>
            <ul class="nav-menu">
                <li><a href="?section=home">Home</a></li>
                <li><a href="?section=trial">Free Trial</a></li>
                <li><a href="?section=refer">Refer</a></li>
                <li><a href="?section=subscribe">Subscribe</a></li>
                <li><a href="?section=renew">Renew</a></li>
                <li><a href="?section=download">Download</a></li>
                <li><a href="?section=contact">Contact</a></li>
            </ul>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($success): ?>
            <div class="container">
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="container">
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($section === 'home'): ?>
            <!-- Home Section -->
            <section class="section hero">
                <div class="container">
                    <h1>Welcome to ViewProPlus</h1>
                    <p>Experience premium streaming like never before with our cutting-edge platform</p>
                    <a href="?section=trial" class="cta-button">Start Your Free Trial</a>
                </div>
            </section>

            <section class="section">
                <div class="container">
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="icon"><i class="fas fa-play-circle"></i></div>
                            <h3>Premium Content</h3>
                            <p>Access thousands of movies, TV shows, and exclusive content in HD quality</p>
                        </div>
                        <div class="feature-card">
                            <div class="icon"><i class="fas fa-mobile-alt"></i></div>
                            <h3>Multi-Device Support</h3>
                            <p>Watch on your TV, computer, tablet, or smartphone - anywhere, anytime</p>
                        </div>
                        <div class="feature-card">
                            <div class="icon"><i class="fas fa-users"></i></div>
                            <h3>Family Friendly</h3>
                            <p>Create multiple profiles for family members with parental controls</p>
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($section === 'trial'): ?>
            <!-- Free Trial Section -->
            <section class="section">
                <div class="container">
                    <div class="form-section">
                        <h2>Start Your Free Trial</h2>
                        <p>Get 1 day of premium access absolutely free - no credit card required!</p>
                        
                        <div class="form-container">
                            <form method="POST">
                                <input type="hidden" name="action" value="free_trial">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" required>
                                </div>
                                
                                <button type="submit" class="btn">Start Free Trial</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($section === 'refer'): ?>
            <!-- Refer Section -->
            <section class="section">
                <div class="container">
                    <div class="form-section">
                        <h2>Refer a Friend</h2>
                        <p>Refer friends and get a free month when they subscribe!</p>
                        
                        <div class="form-container">
                            <form method="POST">
                                <input type="hidden" name="action" value="refer">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="form-group">
                                    <label for="referrer_email">Your Email Address</label>
                                    <input type="email" id="referrer_email" name="referrer_email" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="friend_email">Friend's Email Address</label>
                                    <input type="email" id="friend_email" name="friend_email" required>
                                </div>
                                
                                <button type="submit" class="btn">Send Referral</button>
                            </form>
                        </div>
                        
                        <div class="features-grid" style="margin-top: 3rem;">
                            <div class="feature-card">
                                <div class="icon"><i class="fas fa-gift"></i></div>
                                <h3>Free Month for You</h3>
                                <p>Get 30 days added to your account when your friend subscribes</p>
                            </div>
                            <div class="feature-card">
                                <div class="icon"><i class="fas fa-heart"></i></div>
                                <h3>Help Your Friends</h3>
                                <p>Share the amazing ViewProPlus experience with people you care about</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($section === 'subscribe'): ?>
            <!-- Subscribe Section -->
            <section class="section">
                <div class="container">
                    <div class="form-section">
                        <h2>Subscribe to ViewProPlus</h2>
                        <p>Get unlimited access to our premium content library</p>
                        
                        <div class="price-display">
                            <div class="price">$<?php echo number_format($viewpro_settings['subscription_price'], 2); ?></div>
                            <div class="period">per month</div>
                        </div>
                        
                        <div class="form-container">
                            <form method="POST">
                                <input type="hidden" name="action" value="subscribe">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="referred_by">Referred by (Email - Optional)</label>
                                    <input type="email" id="referred_by" name="referred_by" placeholder="Enter referrer's email if applicable">
                                </div>
                                
                                <button type="submit" class="btn btn-success">Subscribe Now</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($section === 'renew'): ?>
            <!-- Renew Section -->
            <section class="section">
                <div class="container">
                    <div class="form-section">
                        <h2>Renew Your Subscription</h2>
                        <p>Extend your ViewProPlus access for another 30 days</p>
                        
                        <div class="price-display">
                            <div class="price">$<?php echo number_format($viewpro_settings['subscription_price'], 2); ?></div>
                            <div class="period">for 30 days</div>
                        </div>
                        
                        <div class="form-container">
                            <form method="POST" id="renewForm">
                                <input type="hidden" name="action" value="renew">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="form-group">
                                    <label for="username">Your Username</label>
                                    <input type="text" id="username" name="username" required placeholder="Enter your ViewProPlus username">
                                    <div id="username-loading" class="loading-indicator" style="display: none;">
                                        <i class="fas fa-spinner fa-spin"></i> Looking up account...
                                    </div>
                                </div>
                                
                                <!-- User Verification Display -->
                                <div id="user-verification" class="user-verification" style="display: none;">
                                    <div class="verification-card">
                                        <div class="verification-header">
                                            <i class="fas fa-user-check"></i>
                                            <h3>Account Found</h3>
                                        </div>
                                        <div class="verification-details">
                                            <p><strong>Name:</strong> <span id="user-name"></span></p>
                                            <p><strong>Email:</strong> <span id="user-email"></span></p>
                                            <p><strong>Username:</strong> <span id="user-username"></span></p>
                                        </div>
                                        <div class="verification-question">
                                            <p><strong>Is this your account?</strong></p>
                                            <div class="verification-buttons">
                                                <button type="button" id="confirm-account" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Yes, this is my account
                                                </button>
                                                <button type="button" id="cancel-verification" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> No, try different username
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Error Display -->
                                <div id="username-error" class="alert alert-danger" style="display: none;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span id="error-message"></span>
                                </div>
                                
                                <!-- Renewal Form (hidden until verified) -->
                                <div id="renewal-section" style="display: none;">
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i>
                                        Account verified! You can now proceed with renewal.
                                    </div>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-sync-alt"></i> Renew Subscription
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="alert alert-info" style="margin-top: 2rem;">
                            <strong>Note:</strong> Your renewal will extend your current subscription by 30 days from your current expiration date.
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($section === 'download'): ?>
            <!-- Download Section -->
            <section class="section">
                <div class="container">
                    <div class="form-section">
                        <h2>DOWNLOAD ON FIRESTICK</h2>
                        <p>Get our apps for the best viewing experience on all your devices</p>
                        
                        <!-- Firestick Instructions -->
                        <div class="download-section">
                            <div class="device-header">
                                <div class="icon"><i class="fas fa-tv"></i></div>
                                <h3>Firestick</h3>
                            </div>
                            <ol class="installation-steps">
                                <li>From the HOME page scroll to Settings</li>
                                <li>Select My FireTV than select Developer Options (If you dont see Developer Options it maybe hidden)</li>
                                <li>Select ABOUT and Hit the big circle on Remote 7x to reveal the now-hidden Developer Options menu and hit the back button and Developer Options will show up Navigate to the About section in Settings</li>
                                <li>Select Apps from Unknown Sources and make sure it is Turned ON</li>
                                <li>Return to the Home Screen and hover over to the left called Search icon</li>
                                <li>Type in "Downloader" select the Downloader App and Install and Open (<strong>Make sure you ACCEPT ALLOW Pictures option when it pops, otherwise won't allow you to install app</strong>)</li>
                                <li>Type these numbers into downloader; <strong>2040501</strong></li>
                                <li>Scroll down and select "Install"</li>
                            </ol>
                        </div>

                        <!-- Mac Instructions -->
                        <div class="download-section">
                            <div class="device-header">
                                <div class="icon"><i class="fab fa-apple"></i></div>
                                <h3>Install on Apple MAC (dmg)</h3>
                            </div>
                            <p>Copy and paste this Link in your Web Browser:</p>
                            <div class="download-link">
                                <a href="https://serv1cdn.setplex.net/pcapps/norago/darwin/x64/NoraGO-2.3.0.dmg" class="btn btn-primary" target="_blank">Download for Mac</a>
                            </div>
                        </div>

                        <!-- Smart TV, Apple TV, Roku -->
                        <div class="download-section">
                            <div class="device-header">
                                <div class="icon"><i class="fas fa-tv"></i></div>
                                <h3>Smart TV, APPLE TV and ROKU</h3>
                            </div>
                            <ol class="installation-steps">
                                <li>Go to your App Store and search for "NoraGo" or "SoPlayer" App and Install it</li>
                                <li>Enter in your login Info</li>
                            </ol>
                        </div>

                        <!-- iPhone and iPad -->
                        <div class="download-section">
                            <div class="device-header">
                                <div class="icon"><i class="fab fa-apple"></i></div>
                                <h3>Install on Apple iPhone and Tablet</h3>
                            </div>
                            <ol class="installation-steps">
                                <li>Go to your App Store and search for "NoraGo" App or "SoPlayer" and Install</li>
                                <li>Enter in your login Info</li>
                            </ol>
                        </div>

                        <!-- Android Phone, Tablet -->
                        <div class="download-section">
                            <div class="device-header">
                                <div class="icon"><i class="fab fa-android"></i></div>
                                <h3>Android Phone, Tablet</h3>
                            </div>
                            <ol class="installation-steps">
                                <li>Go to your Google Play Store and search for "NoraGo" App and Install it</li>
                                <li>Enter in your login Info</li>
                            </ol>
                        </div>

                        <!-- PC Instructions -->
                        <div class="download-section">
                            <div class="device-header">
                                <div class="icon"><i class="fab fa-windows"></i></div>
                                <h3>Install on PC (exe)</h3>
                            </div>
                            <p>Copy and paste this Link in your Web Browser:</p>
                            <div class="download-link">
                                <a href="https://serv1cdn.setplex.net/pcapps/norago/win32/x64/NoraGO%20Setup%202.4.1.exe" class="btn btn-primary" target="_blank">Download for PC</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($section === 'contact'): ?>
            <!-- Contact Section -->
            <section class="section">
                <div class="container">
                    <div class="form-section">
                        <h2>Contact Us</h2>
                        <p>Have questions? We're here to help!</p>
                        
                        <div class="form-container">
                            <form method="POST">
                                <input type="hidden" name="action" value="contact">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="subject">Subject</label>
                                    <input type="text" id="subject" name="subject" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message">Message</label>
                                    <textarea id="message" name="message" rows="5" required></textarea>
                                </div>
                                
                                <button type="submit" class="btn">Send Message</button>
                            </form>
                        </div>
                        
                        <div class="features-grid" style="margin-top: 3rem;">
                            <div class="feature-card">
                                <div class="icon"><i class="fas fa-envelope"></i></div>
                                <h3>Email Support</h3>
                                <p>support@viewproplus.com</p>
                            </div>
                            <div class="feature-card">
                                <div class="icon"><i class="fas fa-clock"></i></div>
                                <h3>Response Time</h3>
                                <p>We typically respond within 24 hours</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 ViewProPlus. All rights reserved.</p>
            <p>Premium streaming experience for everyone.</p>
        </div>
    </footer>

    <script>
        document.querySelectorAll('a[href^="?section="]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
            });
        });

        let lookupTimeout;
        const usernameInput = document.getElementById('username');
        const usernameLoading = document.getElementById('username-loading');
        const userVerification = document.getElementById('user-verification');
        const usernameError = document.getElementById('username-error');
        const renewalSection = document.getElementById('renewal-section');
        const renewForm = document.getElementById('renewForm');
        
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                const username = this.value.trim();
                
                clearTimeout(lookupTimeout);
                
                hideAllVerificationSections();
                
                if (username.length >= 3) {
                    lookupTimeout = setTimeout(() => {
                        lookupUsername(username);
                    }, 500);
                }
            });
        }
        
        function hideAllVerificationSections() {
            if (usernameLoading) usernameLoading.style.display = 'none';
            if (userVerification) userVerification.style.display = 'none';
            if (usernameError) usernameError.style.display = 'none';
            if (renewalSection) renewalSection.style.display = 'none';
        }
        
        function lookupUsername(username) {
            if (usernameLoading) usernameLoading.style.display = 'block';
            
            const formData = new FormData();
            formData.append('action', 'lookup_username');
            formData.append('username', username);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (usernameLoading) usernameLoading.style.display = 'none';
                
                if (data.success) {
                    showUserVerification(data.user);
                } else {
                    showError(data.message || 'Username not found');
                }
            })
            .catch(error => {
                if (usernameLoading) usernameLoading.style.display = 'none';
                showError('Error looking up username. Please try again.');
                console.error('Username lookup error:', error);
            });
        }
        
        function showUserVerification(user) {
            if (userVerification) {
                document.getElementById('user-name').textContent = `${user.first_name} ${user.last_name}`;
                document.getElementById('user-email').textContent = user.email || 'Not provided';
                document.getElementById('user-username').textContent = user.username;
                userVerification.style.display = 'block';
            }
        }
        
        function showError(message) {
            if (usernameError && document.getElementById('error-message')) {
                document.getElementById('error-message').textContent = message;
                usernameError.style.display = 'block';
            }
        }
        
        document.addEventListener('click', function(e) {
            if (e.target.id === 'confirm-account' || e.target.closest('#confirm-account')) {
                if (userVerification) userVerification.style.display = 'none';
                if (renewalSection) renewalSection.style.display = 'block';
            }
            
            if (e.target.id === 'cancel-verification' || e.target.closest('#cancel-verification')) {
                hideAllVerificationSections();
                if (usernameInput) {
                    usernameInput.value = '';
                    usernameInput.focus();
                }
            }
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (form.id === 'renewForm') {
                    const renewalSectionVisible = renewalSection && renewalSection.style.display !== 'none';
                    if (!renewalSectionVisible) {
                        e.preventDefault();
                        alert('Please verify your account first by entering your username.');
                        return;
                    }
                }
                
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#007bff';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>
