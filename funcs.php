<?php

function getGUID(){
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = md5(uniqid(rand(), true));
        $hyphen = chr(45);// "-"
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
    //Adding the starting index of the starting word to 
    //its length would give its ending index
    $subtring_start += strlen($starting_word); 
    //Length of our required sub string
    $size = strpos($str, $ending_word, $subtring_start) - $subtring_start; 
    // Return the substring from the index substring_start of length size 
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
      
    //print_r( $cookies); 

    return $cookies;

}

function extendAccount($userId) {

    $GUID = getGUID();

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

    $cookies1 = getCookies($response);

    $tab = string_between_two_string($response, "tab_id=", "&");
    $execId = string_between_two_string($response, "execution=", "&");
    $sess = string_between_two_string($response, "session_code=", "&");

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
    curl_close($ch);

    $cookies = getCookies($response);

    if(isset($cookies["KEYCLOAK_SESSION"])) {
    } else {
        return "critical_error1";
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $cookies = getCookies($response);

    $jsonResponseAuth = json_decode($response, true);

    if ($http_code != 200 || !$jsonResponseAuth || !isset($jsonResponseAuth["access_token"])) {
        error_log("extendAccount: Token exchange failed - HTTP " . $http_code . ", Response: " . substr($response, 0, 200));
        return "critical_error2";
    }

    error_log("extendAccount: Token exchange successful, access_token length: " . strlen($jsonResponseAuth["access_token"]));

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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("extendAccount: Timezone API call - HTTP " . $http_code);




    
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"approvalRequired":false,"currencyConverterType":"FIXER_IO","currencyId":14001,"paymentKey":null,"subscriberId":' . $userId . ',"autoPay":false,"comment":null,"contentAddonsAutoPay":false,"devicesToPay":6,"length":1,"lengthType":"Months","override":true,"paymentType":"Custom_Subscription","price":0,"prorateToUpcoming":true,"prorateSubscription":false,"subscriptionId":210406315,"subscription":null,"contentAddOns":null,"contentSetAddOns":[],"checkNumber":null,"creditCardId":null,"externalPaymentSystemType":null,"paymentSystemType":"CASH","transactionId":null,"location":null,"accessoryIds":[]}');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("extendAccount: Payment creation - HTTP " . $http_code . ", Response: " . substr($response, 0, 300));

    if(strpos($response, "paymentStatementId") !== false) {
        error_log("extendAccount: Payment creation successful");
        return "success";
    } else {
        error_log("extendAccount: Payment creation failed - no paymentStatementId found");
        return "unknown_error1";
    }


}


function generateAccount($email, $firstName, $lastName, $phoneNumber, $userPass) {

    //$email = "gn5iaa2@gmail.com";
    //$firstName = "John";
    //$lastName = "Doe";
    //$phoneNumber = "5141216152";

    $last4 = substr($phoneNumber, -4) . 0;

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

    $response = curl_exec($ch);

    curl_close($ch);

    //echo $response;

    $cookies = getCookies($response);


    if(isset($cookies["KEYCLOAK_SESSION"])) {

        //echo "Successfully logged-in!\n";

    } else {

        return "critical_error1";

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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $cookies = getCookies($response);

    $jsonResponseAuth = json_decode($response, true);

    if ($http_code != 200 || !$jsonResponseAuth || !isset($jsonResponseAuth["access_token"])) {
        error_log("extendAccount: Token exchange failed - HTTP " . $http_code . ", Response: " . substr($response, 0, 200));
        return "critical_error2";
    }

    error_log("extendAccount: Token exchange successful, access_token length: " . strlen($jsonResponseAuth["access_token"]));

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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("extendAccount: Timezone API call - HTTP " . $http_code);

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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"id":null,"name":"' . $last4 . '","accessoryNotes":[],"accountNumber":null,"address":"384","city":"2938","country":"US","creditCards":[],"currentPaymentStatement":null,"customChannels":[],"customVods":[],"dateOfBirth":null,"deleted":null,"devices":[],"deviceSlots":[],"email":"' . $email . '","enabled":null,"expirationTime":null,"firstname":"' . $firstName . '","foreignPlatformSubscriberId":"","hasUnlimitedSubscription":null,"language":null,"lastAccess":null,"lastname":"' . $lastName . '","network":{"id":10000285,"name":"VTV","backgroundColor":null,"categorySets":[],"customVideoUrl":null,"deviceCount":0,"hasAssignedAcl":null,"hasAvodSubscription":null,"listingType":"Sequence","multiorgEnabled":false,"multiorgId":null,"networkCatchupLinks":[],"networkChannelLinks":[],"networkThemeLinks":[],"pincode":"","platforms":null,"prefix":"VV","startChannelSettingsEnabled":null,"startChannelSettingsDto":[],"startPageType":null,"staticChannel":null,"screenSaverSettings":null,"subscriberCount":null,"subscribers":[],"timezone":null,"voucherSubscribersAllowed":false,"logoUrl":null,"apiAccessUser":null},"notes":[],"password":"' . $userPass . '","paymentStatements":[],"phone":"' . $phoneNumber . '","pincode":"1234","registered":null,"state":"","timeZone":"America/Grenada","user":null,"zipcode":"9238","tvsAccountNumber":null,"tvsAccountStartDate":null,"tvsThaiId":null,"type":null}');

    $response = curl_exec($ch);

    curl_close($ch);

    if(strpos($response, "already exist") !== false) {

        return "already_exist";

    } elseif(strpos($response, 'externalId') !== false) {

        //echo "Success creating user!";

    } else {

        return "unknown_error1";

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
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"approvalRequired":false,"currencyConverterType":"FIXER_IO","currencyId":14001,"paymentKey":null,"subscriberId":' .  $id . ',"autoPay":false,"comment":null,"contentAddonsAutoPay":false,"devicesToPay":6,"length":1,"lengthType":"Months","override":true,"paymentType":"Custom_Subscription","price":0,"prorateToUpcoming":true,"prorateSubscription":false,"subscriptionId":210406315,"subscription":null,"contentAddOns":null,"contentSetAddOns":[],"checkNumber":null,"creditCardId":null,"externalPaymentSystemType":null,"paymentSystemType":"CASH","transactionId":null,"location":null,"accessoryIds":[]}');

    $response = curl_exec($ch);

    curl_close($ch);

    //echo $response;

    return $id;

}
