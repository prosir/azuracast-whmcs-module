<?php


if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


function azuracast_MetaData() {
    return [
        'DisplayName' => 'AzuraCast Module',
        'APIVersion' => '1.1', 
    ];
}


function azuracast_ConfigOptions() {
    return [
        'Storage Limit (MB)' => [
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Specify the storage limit in MB',
            'Default' => '5000',
        ],
        'Maximum Listeners' => [
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Max listeners allowed on the station',
            'Default' => '500',
        ],
        'Bitrate (kbps)' => [
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Audio Bitrate (kbps)',
            'Default' => '128',
        ],
    ];
}

function azuracast_CreateAccount($params) {
    $storageLimit = $params['configoption1'];
    $maxListeners = $params['configoption2'];
    $bitrate = $params['configoption3'];

    $apiUrl = "https://your-azuracast-url.com/api/stations";
    $apiKey = "your_api_key"; 

    $data = [
        'name' => $params['domain'],
        'short_name' => $params['username'],
        'storage_quota' => $storageLimit,
        'max_listeners' => $maxListeners,
        'bitrate' => $bitrate,
        'frontend_type' => 'shoutcast', 
    ];

    $response = azuracast_api_request($apiUrl, $apiKey, $data, 'POST');

    if ($response['success']) {
        return 'success';
    } else {
        return 'Error: ' . $response['message'];
    }
}


function azuracast_SuspendAccount($params) {
    $apiUrl = "https://your-azuracast-url.com/api/stations/{$params['username']}/suspend";
    $apiKey = "your_api_key";
    
    $response = azuracast_api_request($apiUrl, $apiKey, [], 'POST');
    
    if ($response['success']) {
        return 'success';
    } else {
        return 'Error: ' . $response['message'];
    }
}


function azuracast_TerminateAccount($params) {
    $apiUrl = "https://your-azuracast-url.com/api/stations/{$params['username']}/delete";
    $apiKey = "your_api_key";

    $response = azuracast_api_request($apiUrl, $apiKey, [], 'DELETE');

    if ($response['success']) {
        return 'success';
    } else {
        return 'Error: ' . $response['message'];
    }
}

function azuracast_api_request($url, $apiKey, $data = [], $method = 'GET') {
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ]);

    if ($method != 'GET') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
}

function azuracast_ClientArea($params) {
    $loginUrl = azuracast_GetLoginUrl($params);
    
    if (strpos($loginUrl, 'Error') === false) {
        return '<a href="' . $loginUrl . '" target="_blank" class="btn btn-success">Login to AzuraCast</a>';
    } else {
        return '<p>Error: Unable to retrieve login URL. Please contact support.</p>';
    }
}

function azuracast_GetLoginUrl($params) {
    $apiUrl = "https://your-azuracast-url.com/api/stations/{$params['username']}/login";
    $apiKey = "your_api_key";  // Replace with your AzuraCast API key

    // Call AzuraCast API to get the login URL
    $response = azuracast_api_request($apiUrl, $apiKey, [], 'POST');

    if ($response && isset($response['login_url'])) {
        return $response['login_url'];
    } else {
        return 'Error: Unable to generate login URL';
    }
}
?>
