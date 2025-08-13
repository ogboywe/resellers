<?php
require_once 'viewpro-config.php';

function sendViewProEmail($to_email, $subject, $html_body, $alt_body = '') {
    global $email_config;
    
    if ($email_config['use_smtp']) {
        return sendSMTPEmail($to_email, $subject, $html_body, $alt_body);
    } else {
        return sendSimpleEmail($to_email, $subject, $html_body, $alt_body);
    }
}

function sendSimpleEmail($to_email, $subject, $html_body, $alt_body = '') {
    global $email_config;
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . $email_config['from_name'] . " <" . $email_config['from_email'] . ">" . "\r\n";
    
    $success = mail($to_email, $subject, $html_body, $headers);
    
    if (!$success) {
        error_log("ViewPro Email Error: Failed to send email to " . $to_email);
    }
    
    return $success;
}

function sendSMTPEmail($to_email, $subject, $html_body, $alt_body = '') {
    return sendSimpleEmail($to_email, $subject, $html_body, $alt_body);
}

function getTrialEmailTemplate($first_name, $username, $password) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .credentials { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to ViewProPlus!</h1>
            </div>
            <div class='content'>
                <h2>Hello {$first_name},</h2>
                <p>Your 1-day ViewProPlus trial is ready!</p>
                <div class='credentials'>
                    <h3>Your Login Credentials:</h3>
                    <p><strong>Username:</strong> {$username}</p>
                    <p><strong>Password:</strong> {$password}</p>
                </div>
                <p>Your trial account is active for 24 hours. Enjoy exploring all the features ViewProPlus has to offer!</p>
                <p>If you love the service, don't forget to subscribe for just $89.97/month for unlimited access.</p>
            </div>
            <div class='footer'>
                <p>Thank you for trying ViewProPlus!</p>
                <p>Need help? Contact our support team.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

function getSubscriptionEmailTemplate($first_name, $username, $password, $expires_date) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .credentials { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to ViewProPlus!</h1>
            </div>
            <div class='content'>
                <h2>Hello {$first_name},</h2>
                <p>Welcome to ViewProPlus! Your 30-day subscription is now active.</p>
                <div class='credentials'>
                    <h3>Your Login Credentials:</h3>
                    <p><strong>Username:</strong> {$username}</p>
                    <p><strong>Password:</strong> {$password}</p>
                    <p><strong>Expires:</strong> {$expires_date}</p>
                </div>
                <p>Thank you for subscribing to ViewProPlus! You now have full access to all our premium features.</p>
                <p>Your subscription will automatically expire on {$expires_date}. You can renew anytime from our website.</p>
            </div>
            <div class='footer'>
                <p>Thank you for choosing ViewProPlus!</p>
                <p>Need help? Contact our support team.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

function getRenewalEmailTemplate($first_name, $username, $new_expires_date) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #17a2b8; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .renewal-info { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Account Renewed!</h1>
            </div>
            <div class='content'>
                <h2>Hello {$first_name},</h2>
                <p>Great news! Your ViewProPlus account has been successfully renewed for 30 days.</p>
                <div class='renewal-info'>
                    <h3>Renewal Details:</h3>
                    <p><strong>Username:</strong> {$username}</p>
                    <p><strong>New Expiration Date:</strong> {$new_expires_date}</p>
                </div>
                <p>Your account is now active until {$new_expires_date}. Continue enjoying all the premium features ViewProPlus has to offer!</p>
            </div>
            <div class='footer'>
                <p>Thank you for staying with ViewProPlus!</p>
                <p>Need help? Contact our support team.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

function getReferralEmailTemplate($first_name, $referred_friend_name) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #ffc107; color: #212529; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .reward-info { background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; border: 1px solid #ffeaa7; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Congratulations!</h1>
            </div>
            <div class='content'>
                <h2>Hello {$first_name},</h2>
                <p>Congratulations! You've earned a free month credit for referring {$referred_friend_name} to ViewProPlus!</p>
                <div class='reward-info'>
                    <h3>Your Reward:</h3>
                    <p><strong>Free Month Extension:</strong> 30 days added to your account</p>
                    <p><strong>Referred Friend:</strong> {$referred_friend_name}</p>
                </div>
                <p>Your account has been automatically extended by 30 days as a thank you for spreading the word about ViewProPlus!</p>
                <p>Keep referring friends to earn more free months!</p>
            </div>
            <div class='footer'>
                <p>Thank you for helping ViewProPlus grow!</p>
                <p>Need help? Contact our support team.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

function sendTrialEmail($email, $first_name, $username, $password) {
    $subject = "Your ViewProPlus Trial Account is Ready!";
    $html_body = getTrialEmailTemplate($first_name, $username, $password);
    $alt_body = "Hello {$first_name}, Your 1-day ViewProPlus trial is ready! Username: {$username}, Password: {$password}. Thank you for trying ViewProPlus!";
    
    return sendViewProEmail($email, $subject, $html_body, $alt_body);
}

function sendSubscriptionEmail($email, $first_name, $username, $password, $expires_date) {
    $subject = "Welcome to ViewProPlus - Your Subscription is Active!";
    $html_body = getSubscriptionEmailTemplate($first_name, $username, $password, $expires_date);
    $alt_body = "Hello {$first_name}, Welcome to ViewProPlus! Your 30-day subscription is active. Username: {$username}, Password: {$password}, Expires: {$expires_date}";
    
    return sendViewProEmail($email, $subject, $html_body, $alt_body);
}

function sendRenewalEmail($email, $first_name, $username, $new_expires_date) {
    $subject = "ViewProPlus Account Renewed Successfully!";
    $html_body = getRenewalEmailTemplate($first_name, $username, $new_expires_date);
    $alt_body = "Hello {$first_name}, Your ViewProPlus account has been renewed for 30 days. Username: {$username}, New expiration: {$new_expires_date}";
    
    return sendViewProEmail($email, $subject, $html_body, $alt_body);
}

function sendReferralEmail($email, $first_name, $referred_friend_name) {
    $subject = "🎉 You Earned a Free Month - ViewProPlus Referral Reward!";
    $html_body = getReferralEmailTemplate($first_name, $referred_friend_name);
    $alt_body = "Congratulations {$first_name}! You've earned a free month credit for referring {$referred_friend_name} to ViewProPlus!";
    
    return sendViewProEmail($email, $subject, $html_body, $alt_body);
}
