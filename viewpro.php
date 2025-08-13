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
    
    $user = getViewProUserByUsername($username);
    if (!$user) {
        $error = "Account not found. Please check your username.";
        return;
    }
    
    $result = renewViewProAccount($user['norago_subid']);
    
    if ($result === 'success') {
        $newExpiration = date('Y-m-d H:i:s', strtotime($user['expires_at'] . ' +30 days'));
        $updated = updateViewProUserExpiration($user['id'], $newExpiration);
        
        if ($updated) {
            $newExpiresDate = date('F j, Y', strtotime($newExpiration));
            $emailSent = sendRenewalEmail($user['email'], $user['first_name'], $user['username'], $newExpiresDate);
            
            if ($emailSent) {
                $success = "Your account has been renewed for 30 days! Check your email for confirmation.";
            } else {
                $success = "Account renewed successfully! New expiration date: " . $newExpiresDate;
            }
        } else {
            $error = "Account renewed but failed to update database. Please contact support.";
        }
    } else {
        $error = "Failed to renew account. Please try again later.";
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
                            <form method="POST">
                                <input type="hidden" name="action" value="renew">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="form-group">
                                    <label for="username">Your Username</label>
                                    <input type="text" id="username" name="username" required placeholder="Enter your ViewProPlus username">
                                </div>
                                
                                <button type="submit" class="btn btn-warning">Renew Subscription</button>
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
                        <h2>Download ViewProPlus</h2>
                        <p>Get our apps for the best viewing experience on all your devices</p>
                        
                        <div class="features-grid">
                            <div class="feature-card">
                                <div class="icon"><i class="fab fa-android"></i></div>
                                <h3>Android</h3>
                                <p>Download from Google Play Store</p>
                                <a href="#" class="btn" style="margin-top: 1rem;">Download</a>
                            </div>
                            <div class="feature-card">
                                <div class="icon"><i class="fab fa-apple"></i></div>
                                <h3>iOS</h3>
                                <p>Download from App Store</p>
                                <a href="#" class="btn" style="margin-top: 1rem;">Download</a>
                            </div>
                            <div class="feature-card">
                                <div class="icon"><i class="fab fa-windows"></i></div>
                                <h3>Windows</h3>
                                <p>Download for Windows PC</p>
                                <a href="#" class="btn" style="margin-top: 1rem;">Download</a>
                            </div>
                            <div class="feature-card">
                                <div class="icon"><i class="fas fa-tv"></i></div>
                                <h3>Smart TV</h3>
                                <p>Available on most smart TV platforms</p>
                                <a href="#" class="btn" style="margin-top: 1rem;">Learn More</a>
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

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
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
