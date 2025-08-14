<?php
require_once 'viewpro-config.php';
require_once 'viewpro-email.php';

function getGUID(){
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);
        $charid = md5(uniqid(rand(), true));
        $hyphen = chr(45);
        $uuid = substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12);
        return $uuid;
    }
}

function string_between_two_string($str, $starting_word, $ending_word) {
    $subtring_start = strpos($str, $starting_word);
    $subtring_start += strlen($starting_word); 
    $size = strpos($str, $ending_word, $subtring_start) - $subtring_start; 
    return substr($str, $subtring_start, $size); 
}

function getCookies($curlResponse) {
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', 
                $curlResponse, $match_found); 

    $cookies = array(); 
      
    foreach($match_found[1] as $item) { 
        parse_str($item, $cookie); 
        $cookies = array_merge($cookies, $cookie); 
    } 
      
    return $cookies;
}

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
    
    $GUID = getGUID();
    error_log("ViewPro: Generated GUID: " . $GUID);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/protocol/openid-connect/auth?client_id=NoraUI&redirect_uri=https%3A%2F%2Ffreeworld.norago.tv%2Fnora%2Flogin%3Fgo%3D%2Fsubscribers%2F30069169&state=' . $GUID . '&response_mode=fragment&response_type=code&scope=openid&nonce='. $GUID);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'accept-language: en-US,en;q=0.9',
        'priority: u=1, i',
        'referer: https://api.path.net/docs',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $cookies1 = getCookies($response);
    error_log("ViewPro: Initial cookies received: " . print_r($cookies1, true));

    $tab = string_between_two_string($response, "tab_id=", "&");
    $execId = string_between_two_string($response, "execution=", "&");
    $sess = string_between_two_string($response, "session_code=", "&");
    
    error_log("ViewPro: Session parameters - tab_id: " . $tab . ", execution: " . $execId . ", session_code: " . $sess);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/login-actions/authenticate?session_code=' . $sess . '&execution=' . $execId . '&client_id=NoraUI&tab_id=' . $tab);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'accept-language: en-US,en;q=0.9',
        'cache-control: max-age=0',
        'content-type: application/x-www-form-urlencoded',
        'origin: https://us-sso.norago.tv',
        'priority: u=0, i',
        'referer: https://us-sso.norago.tv/realms/465/protocol/openid-connect/auth?client_id=NoraUI&redirect_uri=https%3A%2F%2Ffreeworld.norago.tv%2Fnora%2Flogin%3Fgo%3D%2Fsubscribers%2F30069169&state=' . $GUID . '&response_mode=fragment&response_type=code&scope=openid&nonce=' . $GUID,
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
    curl_setopt($ch, CURLOPT_COOKIE, 'AUTH_SESSION_ID=' . $cookies1["AUTH_SESSION_ID"] . '; AUTH_SESSION_ID_LEGACY=' . $cookies1["AUTH_SESSION_ID_LEGACY"] . '; KC_RESTART=' . $cookies1["KC_RESTART"]);
    global $norago_api_config;
    $username = urlencode($norago_api_config['username']);
    $password = urlencode($norago_api_config['password']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'username=' . $username . '&password=' . $password . '&credentialId=');

    $response = curl_exec($ch);
    curl_close($ch);

    $cookies = getCookies($response);
    error_log("ViewPro: Authentication cookies received: " . print_r($cookies, true));
    
    if (!isset($cookies["KEYCLOAK_SESSION"])) {
        error_log("ViewPro: Failed to get KEYCLOAK_SESSION");
        return "auth_failed";
    }

    $testSir = explode("/", $cookies["KEYCLOAK_SESSION"]);
    $newStr = $testSir[0] . "/" . urlencode($testSir[1]) . "/" . $testSir[2];
    
    error_log("ViewPro: Starting code extraction from response");
    error_log("ViewPro: Response length: " . strlen($response));
    error_log("ViewPro: Response preview: " . substr($response, 0, 200));
    
    $exploded = explode("\n", $response);
    error_log("ViewPro: Response has " . count($exploded) . " lines");
    
    if (count($exploded) < 4) {
        error_log("ViewPro: Response has insufficient lines for code extraction");
        return "code_extraction_failed";
    }
    
    error_log("ViewPro: Line 3 content: " . (isset($exploded[3]) ? $exploded[3] : "NOT_SET"));
    $code = explode("&", $exploded[3]);
    $code = $code[array_key_last($code)];
    error_log("ViewPro: Extracted code: " . $code);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/protocol/openid-connect/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: */*',
        'accept-language: en-US,en;q=0.9',
        'content-type: application/x-www-form-urlencoded',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-site',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, 'AUTH_SESSION_ID=' . $cookies1["AUTH_SESSION_ID"] . '; AUTH_SESSION_ID_LEGACY=' . $cookies1["AUTH_SESSION_ID_LEGACY"] . '; KEYCLOAK_SESSION=' . $newStr . '; KEYCLOAK_SESSION_LEGACY=' . $newStr . '; KEYCLOAK_IDENTITY=' . $cookies["KEYCLOAK_IDENTITY"] . '; KEYCLOAK_IDENTITY_LEGACY=' . $cookies["KEYCLOAK_IDENTITY_LEGACY"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, trim($code) . '&grant_type=authorization_code&client_id=NoraUI&redirect_uri=https%3A%2F%2Ffreeworld.norago.tv%2Fnora%2Flogin%3Fgo%3D%2Fsubscribers%2F30069169');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("ViewPro: Token exchange HTTP code: " . $http_code);
    error_log("ViewPro: Token exchange response: " . substr($response, 0, 500));

    $cookies = getCookies($response);
    
    $headerEndPos = strpos($response, "\r\n\r\n");
    if ($headerEndPos !== false) {
        $jsonBody = substr($response, $headerEndPos + 4);
    } else {
        $jsonBody = $response;
    }
    
    error_log("ViewPro: JSON body extracted: " . substr($jsonBody, 0, 200));
    $jsonResponseAuth = json_decode($jsonBody, true);
    
    error_log("ViewPro: JSON decode result: " . ($jsonResponseAuth ? "SUCCESS" : "FAILED"));
    error_log("ViewPro: Access token present: " . (isset($jsonResponseAuth["access_token"]) ? "YES" : "NO"));
    
    if (!isset($jsonResponseAuth["access_token"])) {
        error_log("ViewPro: Failed to get access token - JSON: " . json_encode($jsonResponseAuth));
        return "token_failed";
    }

    $jsonResponseAuth["xsrf_token"] = isset($cookies["XSRF-TOKEN"]) ? $cookies["XSRF-TOKEN"] : null;
    
    error_log("ViewPro: Successfully authenticated with NoraGO TV - Access Token: " . substr($jsonResponseAuth["access_token"], 0, 20) . "...");
    error_log("ViewPro: XSRF Token: " . ($jsonResponseAuth["xsrf_token"] ? substr($jsonResponseAuth["xsrf_token"], 0, 20) . "..." : "NULL"));
    return $jsonResponseAuth;
}

function createViewProTrial($email, $firstName, $lastName, $phoneNumber) {
    global $norago_api_config;
    
    error_log("ViewPro: Creating trial account for " . $email);
    
    $authResult = authenticateWithNoraGO();
    if (is_string($authResult)) {
        error_log("ViewPro: Authentication failed with error: " . $authResult);
        return $authResult; // Return error string
    }
    
    $accessToken = $authResult["access_token"];
    $username = generateRandomUsername('vp');
    $password = generateRandomPassword();
    
    error_log("ViewPro: Starting subscriber creation API call for " . $email);
    error_log("ViewPro: Generated username: " . $username . ", password: " . $password);
    
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
        "name" => $username,
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
            "name" => $viewpro_settings['default_network_name'],
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
    error_log("ViewPro: Trial creation response: " . substr($response, 0, 500));
    
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
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/info/timezone');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $accessToken,
        'priority: u=1, i',
        'referer: ' . $norago_api_config['base_url'] . '/nora/login?go=%2Fsubscribers%2F' . $subscriberId,
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    preg_match('/"accountNumber":"([^"]+)"/', $response, $accMatches);
    $accountNumber = isset($accMatches[1]) ? $accMatches[1] : $username;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/slots');
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' . $subscriberId . ',"name":null,"accessoryNotes":[],"accountNumber":"' . $accountNumber . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' . $email . '","enabled":true,"expirationTime":null,"firstname":"' . $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' . $lastName . '","network":{"id":' . $norago_api_config['network_id'] . ',"name":"' . $viewpro_settings['default_network_name'] . '","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"' . $norago_api_config['network_prefix'] . '","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' . $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);
    curl_close($ch);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/slots');
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' . $subscriberId . ',"name":null,"accessoryNotes":[],"accountNumber":"' . $accountNumber . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' . $email . '","enabled":true,"expirationTime":null,"firstname":"' . $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' . $lastName . '","network":{"id":' . $norago_api_config['network_id'] . ',"name":"' . $viewpro_settings['default_network_name'] . '","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"' . $norago_api_config['network_prefix'] . '","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' . $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);
    curl_close($ch);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/slots');
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' . $subscriberId . ',"name":null,"accessoryNotes":[],"accountNumber":"' . $accountNumber . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' . $email . '","enabled":true,"expirationTime":null,"firstname":"' . $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' . $lastName . '","network":{"id":' . $norago_api_config['network_id'] . ',"name":"' . $viewpro_settings['default_network_name'] . '","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"' . $norago_api_config['network_prefix'] . '","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' . $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);
    curl_close($ch);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/slots');
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' . $subscriberId . ',"name":null,"accessoryNotes":[],"accountNumber":"' . $accountNumber . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' . $email . '","enabled":true,"expirationTime":null,"firstname":"' . $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' . $lastName . '","network":{"id":' . $norago_api_config['network_id'] . ',"name":"' . $viewpro_settings['default_network_name'] . '","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"' . $norago_api_config['network_prefix'] . '","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' . $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);
    curl_close($ch);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/slots');
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' . $subscriberId . ',"name":null,"accessoryNotes":[],"accountNumber":"' . $accountNumber . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' . $email . '","enabled":true,"expirationTime":null,"firstname":"' . $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' . $lastName . '","network":{"id":' . $norago_api_config['network_id'] . ',"name":"' . $viewpro_settings['default_network_name'] . '","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"' . $norago_api_config['network_prefix'] . '","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' . $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);
    curl_close($ch);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/slots');
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' . $subscriberId . ',"name":null,"accessoryNotes":[],"accountNumber":"' . $accountNumber . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' . $email . '","enabled":true,"expirationTime":null,"firstname":"' . $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' . $lastName . '","network":{"id":' . $norago_api_config['network_id'] . ',"name":"' . $viewpro_settings['default_network_name'] . '","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"' . $norago_api_config['network_prefix'] . '","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' . $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);
    curl_close($ch);

    return [
        'subscriber_id' => $subscriberId,
        'username' => $username,
        'password' => $password
    ];
}

function createViewProSubscription($email, $firstName, $lastName, $phoneNumber) {
    global $norago_api_config, $viewpro_settings;
    
    error_log("ViewPro: Creating subscription account for " . $email);
    
    $authResult = authenticateWithNoraGO();
    if (is_string($authResult)) {
        error_log("ViewPro: Authentication failed with error: " . $authResult);
        return $authResult; // Return error string
    }
    
    $accessToken = $authResult["access_token"];
    $username = generateRandomUsername('vp');
    $password = generateRandomPassword();
    
    error_log("ViewPro: Starting subscriber creation API call for " . $email);
    error_log("ViewPro: Generated username: " . $username . ", password: " . $password);
    
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
        "name" => $username,
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
            "name" => $viewpro_settings['default_network_name'],
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
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/info/timezone');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $accessToken,
        'priority: u=1, i',
        'referer: ' . $norago_api_config['base_url'] . '/nora/login?go=%2Fsubscribers%2F' . $subscriberId,
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    preg_match('/"accountNumber":"([^"]+)"/', $response, $accMatches);
    $accountNumber = isset($accMatches[1]) ? $accMatches[1] : $username;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/slots');
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' . $subscriberId . ',"name":null,"accessoryNotes":[],"accountNumber":"' . $accountNumber . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' . $email . '","enabled":true,"expirationTime":null,"firstname":"' . $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' . $lastName . '","network":{"id":' . $norago_api_config['network_id'] . ',"name":"' . $viewpro_settings['default_network_name'] . '","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"' . $norago_api_config['network_prefix'] . '","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' . $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);
    curl_close($ch);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers/' . $subscriberId . '/slots');
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' . $subscriberId . ',"name":null,"accessoryNotes":[],"accountNumber":"' . $accountNumber . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' . $email . '","enabled":true,"expirationTime":null,"firstname":"' . $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' . $lastName . '","network":{"id":' . $norago_api_config['network_id'] . ',"name":"' . $viewpro_settings['default_network_name'] . '","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"' . $norago_api_config['network_prefix'] . '","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' . $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);
    curl_close($ch);

    return [
        'subscriber_id' => $subscriberId,
        'username' => $username,
        'password' => $password
    ];
}

function renewViewProAccount($subscriberId) {
    global $norago_api_config, $viewpro_settings;
    
    error_log("ViewPro: Renewing account for subscriber " . $subscriberId);
    
    $authResult = authenticateWithNoraGO();
    if (is_string($authResult)) {
        return $authResult; // Return error string
    }
    
    $accessToken = $authResult["access_token"];
    $xsrfToken = $authResult["xsrf_token"];

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
    error_log("ViewPro: Saving user data for " . $email . " (username: " . $username . ")");
    return rand(1000, 9999);
}

function getViewProUserByUsername($username) {
    global $norago_api_config;
    
    error_log("ViewPro: Looking up user by username: " . $username);
    
    $authResult = authenticateWithNoraGO();
    if (is_string($authResult)) {
        error_log("ViewPro: Authentication failed for user lookup: " . $authResult);
        return null;
    }
    
    $accessToken = $authResult["access_token"];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $norago_api_config['base_url'] . '/nora/api/subscribers?q=' . urlencode($username));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'authorization: Bearer ' . $accessToken,
        'content-type: application/json;charset=UTF-8'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("ViewPro: User lookup HTTP code: " . $http_code . " for username: " . $username);
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        if (isset($data['content']) && !empty($data['content'])) {
            foreach ($data['content'] as $subscriber) {
                if ($subscriber['name'] == $username || $subscriber['accountNumber'] == $username) {
                    error_log("ViewPro: Found subscriber ID: " . $subscriber['id'] . " for username: " . $username);
                    return [
                        'id' => $subscriber['id'],
                        'norago_subid' => $subscriber['id'],
                        'username' => $username,
                        'email' => $subscriber['email'] ?? 'user@example.com',
                        'first_name' => $subscriber['firstname'] ?? 'User',
                        'expires_at' => $subscriber['expirationTime'] ?? date('Y-m-d H:i:s', strtotime('+30 days'))
                    ];
                }
            }
        }
    }
    
    error_log("ViewPro: No subscriber found for username: " . $username);
    return null;
}

function getViewProUserByEmail($email) {
    error_log("ViewPro: Looking up user by email: " . $email);
    return null;
}

function updateViewProUserExpiration($userId, $newExpirationDate) {
    error_log("ViewPro: Updating expiration for user " . $userId . " to " . $newExpirationDate);
    return true;
}

function saveViewProReferral($referrerId, $referredEmail) {
    error_log("ViewPro: Saving referral from " . $referrerId . " to " . $referredEmail);
    return true;
}

function completeViewProReferral($referredEmail, $referredUserId) {
    error_log("ViewPro: Completing referral for " . $referredEmail . " (user ID: " . $referredUserId . ")");
    return true;
}
