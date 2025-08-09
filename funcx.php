<?php
// Trial-specific functions for 1-day trial accounts
// Note: Helper functions (getGUID, string_between_two_string, getCookies) are provided by funcs.php

function extendTrialAccount($userId) {


    $GUID = getGUID();
    //echo $GUID;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/protocol/openid-connect/auth?client_id=NoraUI&redirect_uri=https%3A%2F%2Ffreeworld.norago.tv%2Fnora%2Flogin%3Fgo%3D%2Fsubscribers%2F30069169&state=' . $GUID . '&response_mode=fragment&response_type=code&scope=openid&nonce='. $GUID);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HEADER, 1);
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

    //echo $response;


    $cookies1 = getCookies($response);

    //echo "\n==============================================\n";

    $tab = string_between_two_string($response, "tab_id=", "&");
    $execId = string_between_two_string($response, "execution=", "&");
    $sess = string_between_two_string($response, "session_code=", "&");


    //echo $tab . "\n";
    //echo $execId . "\n";
    //echo $sess . "\n";

    //echo "\n==============================================\n";

    //sleep(9999);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/login-actions/authenticate?session_code=' . $sess . '&execution=' . $execId . '&client_id=NoraUI&tab_id=' . $tab);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'accept-language: en-US,en;q=0.9',
        'cache-control: max-age=0',
        'content-type: application/x-www-form-urlencoded',
        'origin: null',
        'priority: u=0, i',
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'username=admin%40usa.com&password=ABC123!!&credentialId=');

    error_log("OAuth: Sending authentication request with credentials admin@usa.com");

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    error_log("OAuth: Authentication request HTTP code: " . $http_code);
    error_log("OAuth: Authentication response length: " . strlen($response));
    error_log("OAuth: Authentication response preview: " . substr($response, 0, 500));

    $cookies = getCookies($response);
    error_log("OAuth: Authentication cookies received: " . print_r($cookies, true));

    if(isset($cookies["KEYCLOAK_SESSION"])) {
    } else {
        error_log("OAuth authentication failed - HTTP code: " . $http_code);
        return "auth_failed";
    }




    //echo "\n==============================================\n";



    $testSir = explode("/", $cookies["KEYCLOAK_SESSION"]);

    $newStr = $testSir[0] . "/" . urlencode($testSir[1]) . "/" . $testSir[2];

    $exploded = explode("\n", $response);

    $code = explode("&", $exploded[3]);

    $code = $code[array_key_last($code)];


    //echo trim($code);


    //echo "\n\n" . 'AUTH_SESSION_ID=' . $cookies1["AUTH_SESSION_ID"] . '; AUTH_SESSION_ID_LEGACY=' . $cookies1["AUTH_SESSION_ID_LEGACY"] . '; KEYCLOAK_SESSION=' . $newStr . '; KEYCLOAK_SESSION_LEGACY=' . $newStr . '; KEYCLOAK_IDENTITY=' . $cookies["KEYCLOAK_IDENTITY"] . '; KEYCLOAK_IDENTITY_LEGACY=' . $cookies["KEYCLOAK_IDENTITY_LEGACY"] . "\n\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/protocol/openid-connect/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
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

    curl_close($ch);

    //echo $response;

    $cookies = getCookies($response);

    $jsonResponseAuth = json_decode($response, true);

    //print_r($jsonResponseAuth);

    //echo "\n==============================================\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/info/timezone');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/login?go=%2Fsubscribers%2F30069169',
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




    // get subscription id

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers/' . $userId . '/extra?activation');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/' . $userId . '/activation',
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

    $jsonResponseSub = json_decode($response, true);




    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers/' . $userId . '/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'content-type: application/json;charset=UTF-8',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/' . $userId . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"approvalRequired":false,"currencyConverterType":"FIXER_IO","currencyId":14001,"paymentKey":null,"subscriberId":' . $userId . ',"autoPay":false,"comment":null,"contentAddonsAutoPay":false,"devicesToPay":6,"length":1,"lengthType":"Days","override":false,"paymentType":"Custom_Subscription","price":0,"prorateToUpcoming":true,"prorateSubscription":false,"subscriptionId":' . $jsonResponseSub["currentSubscription"]["id"] . ',"subscription":null,"contentAddOns":null,"contentSetAddOns":[],"checkNumber":null,"creditCardId":null,"externalPaymentSystemType":null,"paymentSystemType":"CASH","transactionId":null,"location":null,"accessoryIds":[]}');

    $response = curl_exec($ch);

    curl_close($ch);

    if(strpos($response, "paymentStatementId") !== false) {

        return "success";

    } else {

        return "unknown_error1";

    }


}


function generateTrialAccount($email, $firstName, $lastName, $phoneNumber, $userPass) {

    //$email = "gn5iaa2@gmail.com";
    //$firstName = "John";
    //$lastName = "Doe";
    //$phoneNumber = "5141216152";

    $last4 = substr($phoneNumber, -4) . 0;

    $GUID = getGUID();
    //echo $GUID;
    error_log("generateTrialAccount: Starting OAuth flow for email: " . $email);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/protocol/openid-connect/auth?client_id=NoraUI&redirect_uri=https%3A%2F%2Ffreeworld.norago.tv%2Fnora%2Flogin%3Fgo%3D%2Fsubscribers%2F30069169&state=' . $GUID . '&response_mode=fragment&response_type=code&scope=openid&nonce='. $GUID);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'accept-language: en-US,en;q=0.9',
        'cache-control: max-age=0',
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    error_log("generateTrialAccount: Initial OAuth request HTTP code: " . $http_code);
    error_log("generateTrialAccount: Initial OAuth response length: " . strlen($response));

    $cookies1 = getCookies($response);
    error_log("generateTrialAccount: Initial cookies received: " . print_r($cookies1, true));

    $tab = string_between_two_string($response, "tab_id=", "&");
    $execId = string_between_two_string($response, "execution=", "&");
    $sess = string_between_two_string($response, "session_code=", "&");
    
    error_log("generateTrialAccount: Session parameters - tab_id: " . $tab . ", execution: " . $execId . ", session_code: " . $sess);


    //echo $tab . "\n";
    //echo $execId . "\n";
    //echo $sess . "\n";

    //echo "\n==============================================\n";

    //sleep(9999);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/login-actions/authenticate?session_code=' . $sess . '&execution=' . $execId . '&client_id=NoraUI&tab_id=' . $tab);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'accept-language: en-US,en;q=0.9',
        'cache-control: max-age=0',
        'content-type: application/x-www-form-urlencoded',
        'origin: null',
        'priority: u=0, i',
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'username=admin%40usa.com&password=ABC123!!&credentialId=');

    error_log("OAuth: Sending authentication request with credentials admin@usa.com");

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    error_log("OAuth: Authentication request HTTP code: " . $http_code);
    error_log("OAuth: Authentication response length: " . strlen($response));
    error_log("OAuth: Authentication response preview: " . substr($response, 0, 500));

    $cookies = getCookies($response);
    error_log("OAuth: Authentication cookies received: " . print_r($cookies, true));

    if(isset($cookies["KEYCLOAK_SESSION"])) {
    } else {
        error_log("OAuth authentication failed - HTTP code: " . $http_code);
        return "auth_failed";
    }




    //echo "\n==============================================\n";



    $testSir = explode("/", $cookies["KEYCLOAK_SESSION"]);

    $newStr = $testSir[0] . "/" . urlencode($testSir[1]) . "/" . $testSir[2];

    $exploded = explode("\n", $response);

    $code = explode("&", $exploded[3]);

    $code = $code[array_key_last($code)];


    //echo trim($code);


    //echo "\n\n" . 'AUTH_SESSION_ID=' . $cookies1["AUTH_SESSION_ID"] . '; AUTH_SESSION_ID_LEGACY=' . $cookies1["AUTH_SESSION_ID_LEGACY"] . '; KEYCLOAK_SESSION=' . $newStr . '; KEYCLOAK_SESSION_LEGACY=' . $newStr . '; KEYCLOAK_IDENTITY=' . $cookies["KEYCLOAK_IDENTITY"] . '; KEYCLOAK_IDENTITY_LEGACY=' . $cookies["KEYCLOAK_IDENTITY_LEGACY"] . "\n\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/protocol/openid-connect/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
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

    curl_close($ch);

    //echo $response;

    $cookies = getCookies($response);

    $jsonResponseAuth = json_decode($response, true);

    //print_r($jsonResponseAuth);

    //echo "\n==============================================\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/info/timezone');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/login?go=%2Fsubscribers%2F30069169',
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

    //echo $response;

    //echo "\n==============================================\n";


    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HEADER, 1);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'content-type: application/json;charset=UTF-8',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/new',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"name":"' . $last4 . '","accessoryNotes":[],"accountNumber":null,"address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' . $email . '","enabled":null,"expirationTime":null,"firstname":"' . $firstName . '","hasUnlimitedSubscription":null,"language":null,"lastAccess":null,"lastname":"' . $lastName . '","network":{"id":10000285,"name":"VTV","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":"","platforms":null,"prefix":"VV","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":"' . $userPass . '","paymentStatements":[],"phone":"' . $phoneNumber . '","pincode":"1234","registered":null,"state":"","timeZone":"America/Grenada","user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":null}');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    error_log("generateTrialAccount: User creation API HTTP code: " . $http_code);
    error_log("generateTrialAccount: User creation response length: " . strlen($response));
    error_log("generateTrialAccount: User creation response preview: " . substr($response, 0, 500));

    if(strpos($response, "already exist") !== false) {

        return "already_exist";

    } elseif(strpos($response, 'externalId') !== false) {


    } else {

        error_log("generateTrialAccount: User creation failed for " . $email . " - HTTP code: " . $http_code);
        if($http_code == 400) {
            return "invalid_request";
        } elseif($http_code == 401) {
            return "token_failed";
        } else {
            return "subscriber_creation_failed";
        }

    }

    //echo "\n==============================================\n";

    $cookies = getCookies($response);

    $id = string_between_two_string($response, "{\"id\":", ",");

    $AccID = string_between_two_string($response, "accountNumber\":\"", "\"");

    //echo $id . "\n";
    //echo $AccID . "\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers/' .  $id . '/slots');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'content-type: application/json;charset=UTF-8',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/' .  $id . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
        'x-xsrf-token: ' . $cookies["XSRF-TOKEN"],
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, 'XSRF-TOKEN=' . $cookies["XSRF-TOKEN"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' .  $id . ',"name":null,"accessoryNotes":[],"accountNumber":"' .  $AccID . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' .  $email . '","enabled":true,"expirationTime":null,"firstname":"' .  $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' .  $lastName . '","network":{"id":10000285,"name":"VTV","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"VV","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' .  $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);

    curl_close($ch);

    //echo $response;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers/' .  $id . '/slots');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'content-type: application/json;charset=UTF-8',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/' .  $id . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
        'x-xsrf-token: ' . $cookies["XSRF-TOKEN"],
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, 'XSRF-TOKEN=' . $cookies["XSRF-TOKEN"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' .  $id . ',"name":null,"accessoryNotes":[],"accountNumber":"' .  $AccID . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' .  $email . '","enabled":true,"expirationTime":null,"firstname":"' .  $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' .  $lastName . '","network":{"id":10000285,"name":"VTV","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"VV","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' .  $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);

    curl_close($ch);

    //echo $response;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers/' .  $id . '/slots');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'content-type: application/json;charset=UTF-8',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/' .  $id . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
        'x-xsrf-token: ' . $cookies["XSRF-TOKEN"],
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, 'XSRF-TOKEN=' . $cookies["XSRF-TOKEN"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' .  $id . ',"name":null,"accessoryNotes":[],"accountNumber":"' .  $AccID . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' .  $email . '","enabled":true,"expirationTime":null,"firstname":"' .  $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' .  $lastName . '","network":{"id":10000285,"name":"VTV","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"VV","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' .  $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);

    curl_close($ch);

    //echo $response;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers/' .  $id . '/slots');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'content-type: application/json;charset=UTF-8',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/' .  $id . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
        'x-xsrf-token: ' . $cookies["XSRF-TOKEN"],
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, 'XSRF-TOKEN=' . $cookies["XSRF-TOKEN"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' .  $id . ',"name":null,"accessoryNotes":[],"accountNumber":"' .  $AccID . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' .  $email . '","enabled":true,"expirationTime":null,"firstname":"' .  $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' .  $lastName . '","network":{"id":10000285,"name":"VTV","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"VV","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' .  $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);

    curl_close($ch);

    //echo $response;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers/' .  $id . '/slots');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'content-type: application/json;charset=UTF-8',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/' .  $id . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
        'x-xsrf-token: ' . $cookies["XSRF-TOKEN"],
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, 'XSRF-TOKEN=' . $cookies["XSRF-TOKEN"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' .  $id . ',"name":null,"accessoryNotes":[],"accountNumber":"' .  $AccID . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' .  $email . '","enabled":true,"expirationTime":null,"firstname":"' .  $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' .  $lastName . '","network":{"id":10000285,"name":"VTV","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"VV","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' .  $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);

    curl_close($ch);

    //echo $response;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers/' .  $id . '/slots');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'content-type: application/json;charset=UTF-8',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/' .  $id . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
        'x-xsrf-token: ' . $cookies["XSRF-TOKEN"],
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, 'XSRF-TOKEN=' . $cookies["XSRF-TOKEN"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"status":false,"code":null,"codeExpirationTime":null,"subscriber":{"id":' .  $id . ',"name":null,"accessoryNotes":[],"accountNumber":"' .  $AccID . '","address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' .  $email . '","enabled":true,"expirationTime":null,"firstname":"' .  $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":false,"language":null,"lastAccess":null,"lastname":"' .  $lastName . '","network":{"id":10000285,"name":"VTV","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":null,"platforms":null,"prefix":"VV","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":null,"paymentStatements":[],"phone":"' .  $phoneNumber . '","pincode":null,"registered":null,"state":"","timeZone":null,"user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":"NORMAL"}}');

    $response = curl_exec($ch);

    curl_close($ch);

    //echo $response;



    //echo "\n==============================================\n";





    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers/' .  $id . '/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'content-type: application/json;charset=UTF-8',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/' .  $id . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"approvalRequired":false,"currencyConverterType":"FIXER_IO","currencyId":14001,"paymentKey":null,"subscriberId":' .  $id . ',"autoPay":false,"comment":null,"contentAddonsAutoPay":false,"devicesToPay":6,"length":1,"lengthType":"Days","override":true,"paymentType":"Custom_Subscription","price":0,"prorateToUpcoming":true,"prorateSubscription":false,"subscriptionId":210406315,"subscription":null,"contentAddOns":null,"contentSetAddOns":[],"checkNumber":null,"creditCardId":null,"externalPaymentSystemType":null,"paymentSystemType":"CASH","transactionId":null,"location":null,"accessoryIds":[]}');

    $response = curl_exec($ch);

    curl_close($ch);

    //echo $response;

    return $id;

}

function convertTrialToCustomer($trialId) {
    global $conn;
    
    if ($conn) {
        $stmt = $conn->prepare("SELECT * FROM trial WHERE subid = ?");
        $stmt->bind_param("s", $trialId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return "trial_not_found";
        }
        
        $trial = $result->fetch_assoc();
    } else {
        if (!isset($_SESSION['trials'])) {
            return "trial_not_found";
        }
        
        $trial = null;
        foreach ($_SESSION['trials'] as $t) {
            if ($t['subid'] == $trialId) {
                $trial = $t;
                break;
            }
        }
        
        if (!$trial) {
            return "trial_not_found";
        }
    }
    
    $trialSubId = $trial['subid'];
    
    $GUID = getGUID();
    error_log("convertTrialToCustomer: Starting OAuth flow for trial subid: " . $trialSubId);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/protocol/openid-connect/auth?client_id=NoraUI&redirect_uri=https%3A%2F%2Ffreeworld.norago.tv%2Fnora%2Flogin%3Fgo%3D%2Fsubscribers%2F30069169&state=' . $GUID . '&response_mode=fragment&response_type=code&scope=openid&nonce='. $GUID);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'accept-language: en-US,en;q=0.9',
        'cache-control: max-age=0',
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("convertTrialToCustomer: Initial OAuth request HTTP code: " . $http_code);

    $cookies1 = getCookies($response);

    $tab = string_between_two_string($response, "tab_id=", "&");
    $execId = string_between_two_string($response, "execution=", "&");
    $sess = string_between_two_string($response, "session_code=", "&");
    
    error_log("convertTrialToCustomer: Session parameters - tab_id: " . $tab . ", execution: " . $execId);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/login-actions/authenticate?session_code=' . $sess . '&execution=' . $execId . '&client_id=NoraUI&tab_id=' . $tab);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'accept-language: en-US,en;q=0.9',
        'cache-control: max-age=0',
        'content-type: application/x-www-form-urlencoded',
        'origin: null',
        'priority: u=0, i',
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'username=admin%40usa.com&password=ABC123!!&credentialId=');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("convertTrialToCustomer: Authentication request HTTP code: " . $http_code);

    $cookies = getCookies($response);

    if(!isset($cookies["KEYCLOAK_SESSION"])) {
        error_log("convertTrialToCustomer: Authentication failed - KEYCLOAK_SESSION not found");
        return "auth_failed";
    }

    $testSir = explode("/", $cookies["KEYCLOAK_SESSION"]);
    $newStr = $testSir[0] . "/" . urlencode($testSir[1]) . "/" . $testSir[2];
    $exploded = explode("\n", $response);
    $code = explode("&", $exploded[3]);
    $code = $code[array_key_last($code)];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://us-sso.norago.tv/realms/465/protocol/openid-connect/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
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
    curl_close($ch);

    $jsonResponseAuth = json_decode($response, true);
    
    if (!isset($jsonResponseAuth["access_token"])) {
        error_log("convertTrialToCustomer: Token exchange failed");
        return "token_failed";
    }

    error_log("convertTrialToCustomer: Successfully obtained access token, updating trial subscription");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://freeworld.norago.tv/nora/api/subscribers/' . $trialSubId . '/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: Bearer ' . $jsonResponseAuth["access_token"],
        'content-type: application/json;charset=UTF-8',
        'origin: https://freeworld.norago.tv',
        'priority: u=1, i',
        'referer: https://freeworld.norago.tv/nora/subscribers/' . $trialSubId . '/activation',
        'sec-ch-ua: "Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"approvalRequired":false,"currencyConverterType":"FIXER_IO","currencyId":14001,"paymentKey":null,"subscriberId":' . $trialSubId . ',"autoPay":false,"comment":"Converted from trial account","contentAddonsAutoPay":false,"devicesToPay":6,"length":1,"lengthType":"Months","override":true,"paymentType":"Custom_Subscription","price":0,"prorateToUpcoming":true,"prorateSubscription":false,"subscriptionId":210406315,"subscription":null,"contentAddOns":null,"contentSetAddOns":[],"checkNumber":null,"creditCardId":null,"externalPaymentSystemType":null,"paymentSystemType":"CASH","transactionId":null,"location":null,"accessoryIds":[]}');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("convertTrialToCustomer: Payment update HTTP code: " . $http_code);
    error_log("convertTrialToCustomer: Payment update response: " . substr($response, 0, 500));

    if ($http_code !== 200 && $http_code !== 201) {
        error_log("convertTrialToCustomer: Payment update failed with HTTP code: " . $http_code);
        return "payment_update_failed";
    }

    error_log("convertTrialToCustomer: Successfully converted trial to customer in NoraGO TV API");
    return $trialSubId;
}
