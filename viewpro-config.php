<?php

$viewpro_db_config = [
    'host' => 'localhost',
    'username' => 'debian-sys-maint',
    'password' => 'TWcMEARZHKapmVhX',
    'database' => 'ViewPro'
];

$email_config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'noreply@viewproplus.com',
    'smtp_password' => $_ENV['VIEWPRO_SMTP_PASSWORD'] ?? '',
    'from_email' => 'noreply@viewproplus.com',
    'from_name' => 'ViewProPlus',
    'use_smtp' => false // Set to true when SMTP credentials are configured
];

$norago_api_config = [
    'base_url' => 'https://freeworld.norago.tv',
    'auth_url' => 'https://us-sso.norago.tv/realms/465/protocol/openid-connect/auth',
    'login_url' => 'https://us-sso.norago.tv/realms/465/login-actions/authenticate',
    'username' => 'admin@usa.com',
    'password' => 'ABC123!!',
    'network_id' => 10000285,
    'network_prefix' => 'VV'
];

$viewpro_settings = [
    'subscription_price' => 89.97,
    'trial_days' => 1,
    'subscription_days' => 30,
    'referral_bonus_days' => 30,
    'default_address' => '384',
    'default_city' => '2938',
    'default_pincode' => '1234',
    'default_zipcode' => '9238',
    'default_state' => '',
    'default_timezone' => 'America/Grenada',
    'default_network_name' => 'VTV'
];

function getViewProConnection() {
    global $viewpro_db_config;
    
    try {
        $conn = new mysqli(
            $viewpro_db_config['host'],
            $viewpro_db_config['username'],
            $viewpro_db_config['password'],
            $viewpro_db_config['database'],
            3306,
            '/var/run/mysqld/mysqld.sock'
        );
        
        if ($conn->connect_error) {
            throw new Exception("ViewPro Database connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log("ViewPro Database Error: " . $e->getMessage());
        return null;
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

function generateRandomUsername($prefix = 'vp') {
    return $prefix . rand(10000, 99999);
}
