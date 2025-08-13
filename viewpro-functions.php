<?php
require_once 'viewpro-config.php';
require_once 'viewpro-email.php';

function getViewProCookies($response) {
    $cookies = [];
    preg_match_all('/Set-Cookie:\s*([^;]+)/', $response, $matches);
    foreach ($matches[1] as $cookie) {
        $parts = explode('=', $cookie, 2);
        if (count($parts) == 2) {
            $cookies[trim($parts[0])] = trim($parts[1]);
        }
    }
    return $cookies;
}

function authenticateWithNoraGO() {
    global $norago_api_config;
    
    error_log("ViewPro: Starting NoraGO TV authentication");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/realms/NoraGO/protocol/openid-connect/auth?client_id=NoraUI&redirect_uri=https%3A%2F%2Ffreeworld.norago.tv%2Fnora%2Flogin%3Fgo%3D%2Fsubscribers%2F30069169&state=b5c4b6b8-7b8a-4b8a-8b8a-8b8a8b8a8b8a&response_mode=fragment&response_type=code&scope=openid&nonce=b5c4b6b8-7b8a-4b8a-8b8a-8b8a8b8a8b8a');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'accept-language: en-US,en;q=0.9',
        'cache-control: max-age=0',
        'priority: u=0, i',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: document',
        'sec-fetch-mode: navigate',
        'sec-fetch-site: none',
        'sec-fetch-user: ?1',
        'upgrade-insecure-requests: 1',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $cookies = getViewProCookies($response);
    
    if (!isset($cookies["AUTH_SESSION_ID"])) {
        error_log("ViewPro: Failed to get AUTH_SESSION_ID");
        return "auth_failed";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/realms/NoraGO/login-actions/authenticate?session_code=b5c4b6b8-7b8a-4b8a-8b8a-8b8a8b8a8b8a&execution=b5c4b6b8-7b8a-4b8a-8b8a-8b8a8b8a8b8a&client_id=NoraUI&tab_id=b5c4b6b8-7b8a-4b8a-8b8a-8b8a8b8a8b8a');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'accept-language: en-US,en;q=0.9',
        'cache-control: max-age=0',
        'content-type: application/x-www-form-urlencoded',
        'origin: ' . $norago_api_config['base_url'],
        'priority: u=0, i',
        'referer: ' . $norago_api_config['base_url'] . '/nora/realms/NoraGO/protocol/openid-connect/auth?client_id=NoraUI&redirect_uri=https%3A%2F%2Ffreeworld.norago.tv%2Fnora%2Flogin%3Fgo%3D%2Fsubscribers%2F30069169&state=b5c4b6b8-7b8a-4b8a-8b8a-8b8a8b8a8b8a&response_mode=fragment&response_type=code&scope=openid&nonce=b5c4b6b8-7b8a-4b8a-8b8a-8b8a8b8a8b8a',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: document',
        'sec-fetch-mode: navigate',
        'sec-fetch-site: same-origin',
        'sec-fetch-user: ?1',
        'upgrade-insecure-requests: 1',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, 'AUTH_SESSION_ID=' . $cookies["AUTH_SESSION_ID"] . '; AUTH_SESSION_ID_LEGACY=' . $cookies["AUTH_SESSION_ID_LEGACY"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'username=' . urlencode($norago_api_config['username']) . '&password=' . urlencode($norago_api_config['password']) . '&credentialId=');

    $response = curl_exec($ch);
    curl_close($ch);

    $cookies1 = getViewProCookies($response);
    
    if (!isset($cookies1["KEYCLOAK_SESSION"])) {
        error_log("ViewPro: Failed to get KEYCLOAK_SESSION");
        return "auth_failed";
    }

    preg_match('/code=([^&]+)/', $response, $matches);
    if (!isset($matches[1])) {
        error_log("ViewPro: Failed to extract authorization code");
        return "auth_failed";
    }
    $code = 'code=' . $matches[1];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/realms/NoraGO/protocol/openid-connect/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'content-type: application/x-www-form-urlencoded',
        'origin: ' . $norago_api_config['base_url'],
        'priority: u=1, i',
        'referer: ' . $norago_api_config['base_url'] . '/nora/login?go=%2Fsubscribers%2F30069169',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, 'AUTH_SESSION_ID=' . $cookies1["AUTH_SESSION_ID"] . '; AUTH_SESSION_ID_LEGACY=' . $cookies1["AUTH_SESSION_ID_LEGACY"] . '; KEYCLOAK_SESSION=' . $cookies1["KEYCLOAK_SESSION"] . '; KEYCLOAK_SESSION_LEGACY=' . $cookies1["KEYCLOAK_SESSION"] . '; KEYCLOAK_IDENTITY=' . $cookies["KEYCLOAK_IDENTITY"] . '; KEYCLOAK_IDENTITY_LEGACY=' . $cookies["KEYCLOAK_IDENTITY_LEGACY"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, trim($code) . '&grant_type=authorization_code&client_id=NoraUI&redirect_uri=https%3A%2F%2Ffreeworld.norago.tv%2Fnora%2Flogin%3Fgo%3D%2Fsubscribers%2F30069169');

    $response = curl_exec($ch);
    curl_close($ch);

    $jsonResponseAuth = json_decode($response, true);
    
    if (!isset($jsonResponseAuth["access_token"])) {
        error_log("ViewPro: Failed to get access token");
        return "token_failed";
    }

    error_log("ViewPro: Successfully authenticated with NoraGO TV");
    return $jsonResponseAuth;
}

function createViewProTrial($email, $firstName, $lastName, $phoneNumber) {
    global $norago_api_config;
    
    error_log("ViewPro: Creating trial account for " . $email);
    
    $authResult = authenticateWithNoraGO();
    if (is_string($authResult)) {
        return $authResult; // Return error string
    }
    
    $accessToken = $authResult["access_token"];
    $username = generateRandomUsername('vp');
    $password = generateRandomPassword();
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $accessToken,
        'content-type: application/json;charset=UTF-8',
        'origin: ' . $norago_api_config['base_url'],
        'priority: u=1, i',
        'referer: ' . $norago_api_config['base_url'] . '/nora/subscribers/new',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    
    $payload = json_encode([
        "id" => null,
        "name" => substr($username, -4),
        "accessoryNotes" => [],
        "accountNumber" => null,
        "address" => "384",
        "city" => "2938",
        "country" => "US",
        "creditCards" => [],
        "currentPaymentStatement" => null,
        "customChannels" => [],
        "customVods" => [],
        "dateOfBirth" => null,
        "deleted" => null,
        "devices" => [],
        "deviceSlots" => [],
        "email" => $email,
        "enabled" => null,
        "expirationTime" => null,
        "firstname" => $firstName,
        "hasUnlimitedSubscription" => null,
        "language" => null,
        "lastAccess" => null,
        "lastname" => $lastName,
        "network" => [
            "id" => $norago_api_config['network_id'],
            "name" => "VTV",
            "backgroundColor" => null,
            "categorySets" => [],
            "customVideoUrl" => null,
            "deviceCount" => 0,
            "hasAssignedAcl" => null,
            "hasAvodSubscription" => null,
            "listingType" => "Sequence",
            "multiorgEnabled" => false,
            "multiorgId" => null,
            "networkCatchupLinks" => [],
            "networkChannelLinks" => [],
            "networkThemeLinks" => [],
            "pincode" => "",
            "platforms" => null,
            "prefix" => $norago_api_config['network_prefix'],
            "startChannelSettingsEnabled" => null,
            "startChannelSettingsDto" => [],
            "startPageType" => null,
            "staticChannel" => null,
            "screenSaverSettings" => null,
            "subscriberCount" => null,
            "subscribers" => [],
            "timezone" => null,
            "voucherSubscribersAllowed" => false,
            "logoUrl" => null,
            "apiAccessUser" => null
        ],
        "notes" => [],
        "password" => $password,
        "paymentStatements" => [],
        "phone" => $phoneNumber,
        "pincode" => "1234",
        "registered" => null,
        "state" => "",
        "timeZone" => "America/Grenada",
        "user" => null,
        "zipcode" => "9238",
        "tvsAccountNumber" => null,
        "tvsAccountStartDate" => null,
        "tvsThaiId" => null,
        "type" => null
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("ViewPro: Trial creation HTTP code: " . $http_code);
    
    if (strpos($response, "already exist") !== false) {
        return "already_exist";
    } elseif (strpos($response, 'externalId') === false) {
        error_log("ViewPro: Trial creation failed - HTTP code: " . $http_code);
        return "subscriber_creation_failed";
    }

    preg_match('/"externalId":"([^"]+)"/', $response, $matches);
    if (!isset($matches[1])) {
        error_log("ViewPro: Failed to extract subscriber ID");
        return "subscriber_creation_failed";
    }
    $subscriberId = $matches[1];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $accessToken,
        'content-type: application/json;charset=UTF-8',
        'origin: ' . $norago_api_config['base_url'],
        'priority: u=1, i',
        'referer: ' . $norago_api_config['base_url'] . '/nora/subscribers/' . $subscriberId . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    
    $paymentPayload = json_encode([
        "approvalRequired" => false,
        "currencyConverterType" => "FIXER_IO",
        "currencyId" => 14001,
        "paymentKey" => null,
        "subscriberId" => (int)$subscriberId,
        "autoPay" => false,
        "comment" => null,
        "contentAddonsAutoPay" => false,
        "devicesToPay" => 6,
        "length" => 1,
        "lengthType" => "Days", // 1 day trial
        "override" => true,
        "paymentType" => "Custom_Subscription",
        "price" => 0,
        "prorateToUpcoming" => true,
        "prorateSubscription" => false,
        "subscriptionId" => 210406315,
        "subscription" => null,
        "contentAddOns" => null,
        "contentSetAddOns" => [],
        "checkNumber" => null,
        "creditCardId" => null,
        "externalPaymentSystemType" => null,
        "paymentSystemType" => "CASH",
        "transactionId" => null,
        "location" => null,
        "accessoryIds" => []
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentPayload);
    $response = curl_exec($ch);
    curl_close($ch);

    error_log("ViewPro: Trial payment creation completed for subscriber " . $subscriberId);
    
    return [
        'subscriber_id' => $subscriberId,
        'username' => $username,
        'password' => $password
    ];
}

function createViewProSubscription($email, $firstName, $lastName, $phoneNumber) {
    global $norago_api_config;
    
    error_log("ViewPro: Creating subscription account for " . $email);
    
    $authResult = authenticateWithNoraGO();
    if (is_string($authResult)) {
        return $authResult; // Return error string
    }
    
    $accessToken = $authResult["access_token"];
    $username = generateRandomUsername('vp');
    $password = generateRandomPassword();
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $accessToken,
        'content-type: application/json;charset=UTF-8',
        'origin: ' . $norago_api_config['base_url'],
        'priority: u=1, i',
        'referer: ' . $norago_api_config['base_url'] . '/nora/subscribers/new',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    
    $payload = json_encode([
        "id" => null,
        "name" => substr($username, -4),
        "accessoryNotes" => [],
        "accountNumber" => null,
        "address" => "384",
        "city" => "2938",
        "country" => "US",
        "creditCards" => [],
        "currentPaymentStatement" => null,
        "customChannels" => [],
        "customVods" => [],
        "dateOfBirth" => null,
        "deleted" => null,
        "devices" => [],
        "deviceSlots" => [],
        "email" => $email,
        "enabled" => null,
        "expirationTime" => null,
        "firstname" => $firstName,
        "hasUnlimitedSubscription" => null,
        "language" => null,
        "lastAccess" => null,
        "lastname" => $lastName,
        "network" => [
            "id" => $norago_api_config['network_id'],
            "name" => "VTV",
            "backgroundColor" => null,
            "categorySets" => [],
            "customVideoUrl" => null,
            "deviceCount" => 0,
            "hasAssignedAcl" => null,
            "hasAvodSubscription" => null,
            "listingType" => "Sequence",
            "multiorgEnabled" => false,
            "multiorgId" => null,
            "networkCatchupLinks" => [],
            "networkChannelLinks" => [],
            "networkThemeLinks" => [],
            "pincode" => "",
            "platforms" => null,
            "prefix" => $norago_api_config['network_prefix'],
            "startChannelSettingsEnabled" => null,
            "startChannelSettingsDto" => [],
            "startPageType" => null,
            "staticChannel" => null,
            "screenSaverSettings" => null,
            "subscriberCount" => null,
            "subscribers" => [],
            "timezone" => null,
            "voucherSubscribersAllowed" => false,
            "logoUrl" => null,
            "apiAccessUser" => null
        ],
        "notes" => [],
        "password" => $password,
        "paymentStatements" => [],
        "phone" => $phoneNumber,
        "pincode" => "1234",
        "registered" => null,
        "state" => "",
        "timeZone" => "America/Grenada",
        "user" => null,
        "zipcode" => "9238",
        "tvsAccountNumber" => null,
        "tvsAccountStartDate" => null,
        "tvsThaiId" => null,
        "type" => null
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("ViewPro: Subscription creation HTTP code: " . $http_code);
    
    if (strpos($response, "already exist") !== false) {
        return "already_exist";
    } elseif (strpos($response, 'externalId') === false) {
        error_log("ViewPro: Subscription creation failed - HTTP code: " . $http_code);
        return "subscriber_creation_failed";
    }

    preg_match('/"externalId":"([^"]+)"/', $response, $matches);
    if (!isset($matches[1])) {
        error_log("ViewPro: Failed to extract subscriber ID");
        return "subscriber_creation_failed";
    }
    $subscriberId = $matches[1];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $accessToken,
        'content-type: application/json;charset=UTF-8',
        'origin: ' . $norago_api_config['base_url'],
        'priority: u=1, i',
        'referer: ' . $norago_api_config['base_url'] . '/nora/subscribers/' . $subscriberId . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    
    $paymentPayload = json_encode([
        "approvalRequired" => false,
        "currencyConverterType" => "FIXER_IO",
        "currencyId" => 14001,
        "paymentKey" => null,
        "subscriberId" => (int)$subscriberId,
        "autoPay" => false,
        "comment" => null,
        "contentAddonsAutoPay" => false,
        "devicesToPay" => 6,
        "length" => 1,
        "lengthType" => "Months", // 1 month subscription
        "override" => true,
        "paymentType" => "Custom_Subscription",
        "price" => 0,
        "prorateToUpcoming" => true,
        "prorateSubscription" => false,
        "subscriptionId" => 210406315,
        "subscription" => null,
        "contentAddOns" => null,
        "contentSetAddOns" => [],
        "checkNumber" => null,
        "creditCardId" => null,
        "externalPaymentSystemType" => null,
        "paymentSystemType" => "CASH",
        "transactionId" => null,
        "location" => null,
        "accessoryIds" => []
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentPayload);
    $response = curl_exec($ch);
    curl_close($ch);

    error_log("ViewPro: Subscription payment creation completed for subscriber " . $subscriberId);
    
    return [
        'subscriber_id' => $subscriberId,
        'username' => $username,
        'password' => $password
    ];
}

function renewViewProAccount($subscriberId) {
    global $norago_api_config;
    
    error_log("ViewPro: Renewing account for subscriber " . $subscriberId);
    
    $authResult = authenticateWithNoraGO();
    if (is_string($authResult)) {
        return $authResult; // Return error string
    }
    
    $accessToken = $authResult["access_token"];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $accessToken,
        'content-type: application/json;charset=UTF-8',
        'origin: ' . $norago_api_config['base_url'],
        'priority: u=1, i',
        'referer: ' . $norago_api_config['base_url'] . '/nora/subscribers/' . $subscriberId . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    
    $paymentPayload = json_encode([
        "approvalRequired" => false,
        "currencyConverterType" => "FIXER_IO",
        "currencyId" => 14001,
        "paymentKey" => null,
        "subscriberId" => (int)$subscriberId,
        "autoPay" => false,
        "comment" => null,
        "contentAddonsAutoPay" => false,
        "devicesToPay" => 6,
        "length" => 1,
        "lengthType" => "Months", // 1 month renewal
        "override" => true,
        "paymentType" => "Custom_Subscription",
        "price" => 0,
        "prorateToUpcoming" => true,
        "prorateSubscription" => false,
        "subscriptionId" => 210406315,
        "subscription" => null,
        "contentAddOns" => null,
        "contentSetAddOns" => [],
        "checkNumber" => null,
        "creditCardId" => null,
        "externalPaymentSystemType" => null,
        "paymentSystemType" => "CASH",
        "transactionId" => null,
        "location" => null,
        "accessoryIds" => []
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentPayload);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("ViewPro: Renewal payment HTTP code: " . $http_code . " for subscriber " . $subscriberId);
    
    if ($http_code == 200) {
        return "success";
    } else {
        return "renewal_failed";
    }
}

function saveViewProUser($email, $firstName, $lastName, $phone, $username, $password, $subscriberId, $accountType, $referredBy = null) {
    $conn = getViewProConnection();
    if (!$conn) {
        return false;
    }
    
    $expiresAt = ($accountType === 'trial') ? 
        date('Y-m-d H:i:s', strtotime('+1 day')) : 
        date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $conn->prepare("INSERT INTO viewpro_users (email, first_name, last_name, phone, username, password, norago_subid, account_type, created_at, expires_at, status, referred_by, referral_credits) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'active', ?, 0)");
    $stmt->bind_param("ssssssissi", $email, $firstName, $lastName, $phone, $username, $password, $subscriberId, $accountType, $expiresAt, $referredBy);
    
    $result = $stmt->execute();
    $userId = $conn->insert_id;
    
    $stmt->close();
    $conn->close();
    
    return $result ? $userId : false;
}

function getViewProUserByUsername($username) {
    $conn = getViewProConnection();
    if (!$conn) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT * FROM viewpro_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $user;
}

function getViewProUserByEmail($email) {
    $conn = getViewProConnection();
    if (!$conn) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT * FROM viewpro_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $user;
}

function updateViewProUserExpiration($userId, $newExpirationDate) {
    $conn = getViewProConnection();
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE viewpro_users SET expires_at = ? WHERE id = ?");
    $stmt->bind_param("si", $newExpirationDate, $userId);
    $result = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return $result;
}

function saveViewProReferral($referrerId, $referredEmail) {
    $conn = getViewProConnection();
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("INSERT INTO viewpro_referrals (referrer_id, referred_email, status, created_at) VALUES (?, ?, 'pending', NOW())");
    $stmt->bind_param("is", $referrerId, $referredEmail);
    $result = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return $result;
}

function completeViewProReferral($referredEmail, $referredUserId) {
    $conn = getViewProConnection();
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE viewpro_referrals SET status = 'completed', referred_user_id = ?, completed_at = NOW() WHERE referred_email = ? AND status = 'pending'");
    $stmt->bind_param("is", $referredUserId, $referredEmail);
    $stmt->execute();
    
    $stmt = $conn->prepare("SELECT r.referrer_id, u.first_name, u.last_name, u.email, u.expires_at FROM viewpro_referrals r JOIN viewpro_users u ON r.referrer_id = u.id WHERE r.referred_email = ? AND r.status = 'completed'");
    $stmt->bind_param("s", $referredEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $referrer = $result->fetch_assoc();
    
    if ($referrer) {
        $newExpiration = date('Y-m-d H:i:s', strtotime($referrer['expires_at'] . ' +30 days'));
        updateViewProUserExpiration($referrer['referrer_id'], $newExpiration);
        
        $referredUser = getViewProUserByEmail($referredEmail);
        if ($referredUser) {
            sendReferralEmail($referrer['email'], $referrer['first_name'], $referredUser['first_name'] . ' ' . $referredUser['last_name']);
        }
    }
    
    $stmt->close();
    $conn->close();
    
    return true;
}
