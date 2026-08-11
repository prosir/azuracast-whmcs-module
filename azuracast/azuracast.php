<?php

/**
 * AzuraCast provisioning module for WHMCS.
 *
 * Server configuration:
 * - Hostname: AzuraCast hostname (or a full base URL)
 * - Password: AzuraCast administrator API key
 * - Secure: enable for HTTPS (recommended)
 * - Port: optional non-standard port
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

const AZURACAST_STATION_ID_PROPERTY = 'AzuraCast Station ID';
const AZURACAST_SHORT_NAME_PROPERTY = 'AzuraCast Short Name';

function azuracast_MetaData()
{
    return [
        'DisplayName' => 'AzuraCast',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => 80,
        'DefaultSSLPort' => 443,
    ];
}

function azuracast_ConfigOptions()
{
    return [
        'Storage Limit (MB)' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '5000',
            'Description' => 'Media storage quota in MB; use 0 for unlimited.',
        ],
        'Maximum Listeners' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '500',
            'Description' => 'Maximum concurrent listeners; use 0 for unlimited.',
        ],
        'Maximum Bitrate (kbps)' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '128',
            'Description' => 'Maximum permitted station bitrate; use 0 for unlimited.',
        ],
        'Frontend' => [
            'Type' => 'dropdown',
            'Options' => 'icecast,shoutcast',
            'Default' => 'icecast',
            'Description' => 'Broadcast frontend. Icecast is recommended.',
        ],
        'Time Zone' => [
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'UTC',
            'Description' => 'IANA time zone used by the station.',
        ],
    ];
}

function azuracast_TestConnection(array $params)
{
    try {
        azuracast_request($params, 'GET', '/api/admin/stations');
        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function azuracast_CreateAccount(array $params)
{
    try {
        azuracast_require_model($params);

        if (azuracast_station_id($params, false) !== null) {
            throw new RuntimeException('This WHMCS service already has an AzuraCast station ID.');
        }

        $name = trim((string) ($params['domain'] ?? ''));
        if ($name === '') {
            $name = 'Station ' . (int) ($params['serviceid'] ?? 0);
        }
        $shortName = azuracast_short_name(
            (string) ($params['username'] ?? ''),
            $name,
            (int) ($params['serviceid'] ?? 0)
        );

        $existing = azuracast_find_station_by_short_name($params, $shortName);
        if ($existing !== null) {
            throw new RuntimeException(
                'An AzuraCast station with short name "' . $shortName . '" already exists. '
                . 'Delete it or choose a different WHMCS service username.'
            );
        }

        $payload = azuracast_station_payload($params, $name, $shortName);
        $station = azuracast_request($params, 'POST', '/api/admin/stations', $payload);
        $stationId = azuracast_extract_id($station, 'station creation');

        try {
            azuracast_apply_storage_quota($params, $station, $stationId);
            azuracast_save_property($params, AZURACAST_STATION_ID_PROPERTY, (string) $stationId);
            azuracast_save_property($params, AZURACAST_SHORT_NAME_PROPERTY, $shortName);
        } catch (Throwable $setupError) {
            try {
                azuracast_request($params, 'DELETE', '/api/admin/station/' . $stationId);
            } catch (Throwable $rollbackError) {
                throw new RuntimeException(
                    $setupError->getMessage() . ' Automatic cleanup also failed: ' . $rollbackError->getMessage()
                );
            }
            throw new RuntimeException($setupError->getMessage() . ' The incomplete station was removed automatically.');
        }
        return 'success';
    } catch (Throwable $e) {
        return azuracast_error($e);
    }
}

function azuracast_SuspendAccount(array $params)
{
    return azuracast_set_enabled($params, false);
}

function azuracast_UnsuspendAccount(array $params)
{
    try {
        $stationId = azuracast_station_id($params);
        azuracast_request($params, 'PUT', '/api/admin/station/' . $stationId, ['is_enabled' => true]);
        azuracast_request($params, 'POST', '/api/station/' . $stationId . '/restart');
        return 'success';
    } catch (Throwable $e) {
        return azuracast_error($e);
    }
}

function azuracast_TerminateAccount(array $params)
{
    try {
        $stationId = azuracast_station_id($params);
        azuracast_request($params, 'DELETE', '/api/admin/station/' . $stationId);
        azuracast_save_property($params, AZURACAST_STATION_ID_PROPERTY, '');
        azuracast_save_property($params, AZURACAST_SHORT_NAME_PROPERTY, '');
        return 'success';
    } catch (Throwable $e) {
        return azuracast_error($e);
    }
}

function azuracast_ChangePackage(array $params)
{
    try {
        $stationId = azuracast_station_id($params);
        $current = azuracast_request($params, 'GET', '/api/admin/station/' . $stationId);
        $name = isset($current['name']) ? (string) $current['name'] : (string) ($params['domain'] ?? '');
        $shortName = isset($current['short_name'])
            ? (string) $current['short_name']
            : azuracast_short_name((string) ($params['username'] ?? ''), $name, (int) $params['serviceid']);

        $payload = azuracast_station_payload($params, $name, $shortName);
        azuracast_request($params, 'PUT', '/api/admin/station/' . $stationId, $payload);

        // Re-read the station because storage-location identifiers can vary by AzuraCast version.
        $updated = azuracast_request($params, 'GET', '/api/admin/station/' . $stationId);
        azuracast_apply_storage_quota($params, $updated, $stationId);
        azuracast_request($params, 'POST', '/api/station/' . $stationId . '/restart');
        return 'success';
    } catch (Throwable $e) {
        return azuracast_error($e);
    }
}

function azuracast_Start(array $params)
{
    return azuracast_service_action($params, 'start');
}

function azuracast_Stop(array $params)
{
    return azuracast_service_action($params, 'stop');
}

function azuracast_Restart(array $params)
{
    return azuracast_service_action($params, 'restart');
}

function azuracast_AdminCustomButtonArray()
{
    return [
        'Start Station' => 'Start',
        'Stop Station' => 'Stop',
        'Restart Station' => 'Restart',
    ];
}

function azuracast_ClientAreaCustomButtonArray()
{
    return [
        'Start Station' => 'Start',
        'Stop Station' => 'Stop',
        'Restart Station' => 'Restart',
    ];
}

function azuracast_AdminLink(array $params)
{
    try {
        $stationId = azuracast_station_id($params);
        $url = azuracast_base_url($params) . '/station/' . $stationId;
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Open AzuraCast</a>';
    } catch (Throwable $e) {
        return '';
    }
}

function azuracast_ClientArea(array $params)
{
    try {
        $stationId = azuracast_station_id($params);
        $station = azuracast_request($params, 'GET', '/api/admin/station/' . $stationId);
        $status = azuracast_request($params, 'GET', '/api/station/' . $stationId . '/status');

        return [
            'templatefile' => 'clientarea',
            'vars' => [
                'azuracastError' => '',
                'azuracastStationName' => (string) ($station['name'] ?? 'AzuraCast Station'),
                'azuracastShortName' => (string) ($station['short_name'] ?? ''),
                'azuracastManageUrl' => azuracast_base_url($params) . '/station/' . $stationId,
                'azuracastEnabled' => !isset($station['is_enabled']) || (bool) $station['is_enabled'],
                'azuracastFrontendRunning' => (bool) (
                    $status['frontendRunning'] ?? $status['frontend_running'] ?? false
                ),
                'azuracastBackendRunning' => (bool) (
                    $status['backendRunning'] ?? $status['backend_running'] ?? false
                ),
                'azuracastStorageMb' => (int) ($params['configoption1'] ?? 0),
                'azuracastMaxListeners' => (int) ($params['configoption2'] ?? 0),
                'azuracastMaxBitrate' => (int) ($params['configoption3'] ?? 0),
            ],
        ];
    } catch (Throwable $e) {
        return [
            'templatefile' => 'clientarea',
            'vars' => [
                'azuracastError' => $e->getMessage(),
            ],
        ];
    }
}

function azuracast_set_enabled(array $params, $enabled)
{
    try {
        $stationId = azuracast_station_id($params);
        azuracast_request($params, 'PUT', '/api/admin/station/' . $stationId, [
            'is_enabled' => (bool) $enabled,
        ]);
        return 'success';
    } catch (Throwable $e) {
        return azuracast_error($e);
    }
}

function azuracast_service_action(array $params, $action)
{
    try {
        $allowed = ['start', 'stop', 'restart'];
        if (!in_array($action, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported station action.');
        }
        $stationId = azuracast_station_id($params);

        if ($action === 'restart') {
            azuracast_request($params, 'POST', '/api/station/' . $stationId . '/restart');
        } elseif ($action === 'start') {
            azuracast_request($params, 'POST', '/api/station/' . $stationId . '/backend/start');
            azuracast_request($params, 'POST', '/api/station/' . $stationId . '/frontend/start');
        } else {
            azuracast_request($params, 'POST', '/api/station/' . $stationId . '/backend/stop');
            azuracast_request($params, 'POST', '/api/station/' . $stationId . '/frontend/stop');
        }
        return 'success';
    } catch (Throwable $e) {
        return azuracast_error($e);
    }
}

function azuracast_station_payload(array $params, $name, $shortName)
{
    azuracast_positive_integer($params['configoption1'] ?? 0, 'Storage Limit', true);
    $maxListeners = azuracast_positive_integer($params['configoption2'] ?? 0, 'Maximum Listeners', true);
    $maxBitrate = azuracast_positive_integer($params['configoption3'] ?? 0, 'Maximum Bitrate', true);
    $frontend = strtolower(trim((string) ($params['configoption4'] ?? 'icecast')));
    if (!in_array($frontend, ['icecast', 'shoutcast'], true)) {
        throw new InvalidArgumentException('Frontend must be icecast or shoutcast.');
    }

    $timezone = trim((string) ($params['configoption5'] ?? 'UTC')) ?: 'UTC';
    try {
        new DateTimeZone($timezone);
    } catch (Throwable $e) {
        throw new InvalidArgumentException('Invalid IANA time zone: ' . $timezone);
    }

    return [
        'name' => (string) $name,
        'short_name' => (string) $shortName,
        'is_enabled' => true,
        'frontend_type' => $frontend,
        'frontend_config' => ['max_listeners' => $maxListeners],
        'max_bitrate' => $maxBitrate,
        'timezone' => $timezone,
    ];
}

function azuracast_apply_storage_quota(array $params, array $station, $stationId)
{
    $storageMb = azuracast_positive_integer($params['configoption1'] ?? 0, 'Storage Limit', true);
    $storageId = azuracast_storage_location_id($station);

    if ($storageId === null) {
        $station = azuracast_request($params, 'GET', '/api/admin/station/' . (int) $stationId);
        $storageId = azuracast_storage_location_id($station);
        if ($storageId === null) {
            throw new RuntimeException(
                'AzuraCast did not return the station media storage-location ID; the storage quota was not applied.'
            );
        }
    }

    $bytes = $storageMb === 0 ? null : $storageMb * 1024 * 1024;
    azuracast_request(
        $params,
        'PUT',
        '/api/admin/storage_location/' . $storageId,
        ['storageQuotaBytes' => $bytes]
    );
}

function azuracast_storage_location_id(array $station)
{
    foreach (['media_storage_location_id', 'media_storage_location'] as $key) {
        if (!isset($station[$key])) {
            continue;
        }
        $value = $station[$key];
        if (is_array($value) && isset($value['id'])) {
            $value = $value['id'];
        }
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }
    }
    return null;
}

function azuracast_station_id(array $params, $required = true)
{
    azuracast_require_model($params);
    $value = $params['model']->serviceProperties->get(AZURACAST_STATION_ID_PROPERTY);
    if (is_numeric($value) && (int) $value > 0) {
        return (int) $value;
    }
    if (!$required) {
        return null;
    }
    throw new RuntimeException(
        'No AzuraCast station ID is stored for this service. Provision it again or restore the service property.'
    );
}

function azuracast_save_property(array $params, $name, $value)
{
    azuracast_require_model($params);
    $params['model']->serviceProperties->save([$name => (string) $value]);
}

function azuracast_require_model(array $params)
{
    if (!isset($params['model']) || !isset($params['model']->serviceProperties)) {
        throw new RuntimeException('WHMCS service properties are unavailable; WHMCS 7.2 or newer is required.');
    }
}

function azuracast_find_station_by_short_name(array $params, $shortName)
{
    $response = azuracast_request(
        $params,
        'GET',
        '/api/admin/stations?search=' . rawurlencode((string) $shortName)
    );
    if (isset($response['rows']) && is_array($response['rows'])) {
        $stations = $response['rows'];
    } elseif (isset($response['data']) && is_array($response['data'])) {
        $stations = $response['data'];
    } else {
        $stations = $response;
    }
    foreach ($stations as $station) {
        if (is_array($station) && isset($station['short_name']) && $station['short_name'] === $shortName) {
            return $station;
        }
    }
    return null;
}

function azuracast_extract_id(array $response, $operation)
{
    $candidate = $response['id'] ?? ($response['data']['id'] ?? null);
    if (!is_numeric($candidate) || (int) $candidate < 1) {
        throw new RuntimeException('AzuraCast returned no numeric station ID after ' . $operation . '.');
    }
    return (int) $candidate;
}

function azuracast_short_name($username, $name, $serviceId)
{
    $source = trim((string) $username) ?: trim((string) $name);
    $value = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $source));
    $value = trim($value, '_');
    if ($value === '') {
        $value = 'station_' . (int) $serviceId;
    }
    return substr($value, 0, 100);
}

function azuracast_positive_integer($value, $label, $allowZero)
{
    $string = trim((string) $value);
    if ($string === '' || !ctype_digit($string)) {
        throw new InvalidArgumentException($label . ' must be a whole number.');
    }
    $number = (int) $string;
    if ((!$allowZero && $number < 1) || ($allowZero && $number < 0)) {
        throw new InvalidArgumentException($label . ' is outside the allowed range.');
    }
    return $number;
}

function azuracast_base_url(array $params)
{
    $hostname = trim((string) ($params['serverhostname'] ?? ''));
    if ($hostname === '') {
        throw new RuntimeException('AzuraCast server hostname is not configured in WHMCS.');
    }

    if (preg_match('#^https?://#i', $hostname)) {
        $base = rtrim($hostname, '/');
    } else {
        $scheme = !empty($params['serversecure']) ? 'https' : 'http';
        $base = $scheme . '://' . trim($hostname, '/');
    }

    $parts = parse_url($base);
    if ($parts === false || empty($parts['host'])) {
        throw new RuntimeException('The configured AzuraCast hostname is invalid.');
    }

    if (empty($parts['port']) && !empty($params['serverport'])) {
        $port = (int) $params['serverport'];
        $default = (($parts['scheme'] ?? '') === 'https') ? 443 : 80;
        if ($port > 0 && $port !== $default) {
            $base .= ':' . $port;
        }
    }
    return rtrim($base, '/');
}

function azuracast_api_key(array $params)
{
    $key = trim((string) ($params['serverpassword'] ?? ''));
    if ($key === '') {
        $key = trim((string) ($params['serveraccesshash'] ?? ''));
    }
    if ($key === '') {
        throw new RuntimeException('AzuraCast API key is not configured in the WHMCS server Password field.');
    }
    return $key;
}

function azuracast_request(array $params, $method, $path, array $data = null)
{
    if (isset($GLOBALS['azuracast_request_override']) && is_callable($GLOBALS['azuracast_request_override'])) {
        return call_user_func($GLOBALS['azuracast_request_override'], $params, $method, $path, $data);
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required.');
    }

    $url = azuracast_base_url($params) . '/' . ltrim((string) $path, '/');
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize cURL.');
    }

    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . azuracast_api_key($params),
    ];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper((string) $method),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($data !== null) {
        $json = json_encode($data);
        if ($json === false) {
            curl_close($curl);
            throw new RuntimeException('Unable to encode the AzuraCast request as JSON.');
        }
        $options[CURLOPT_POSTFIELDS] = $json;
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }
    curl_setopt_array($curl, $options);

    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : [];
    $decoded = is_array($decoded) ? $decoded : [];
    azuracast_log_request($params, $method, $url, $data, $body, $status);

    if ($body === false || $curlError !== '') {
        throw new RuntimeException('Could not contact AzuraCast: ' . $curlError);
    }
    if ($status < 200 || $status >= 300) {
        $message = $decoded['message'] ?? $decoded['formatted_message'] ?? $decoded['error'] ?? null;
        if (is_array($message)) {
            $message = implode('; ', array_map('strval', $message));
        }
        throw new RuntimeException(
            'AzuraCast API returned HTTP ' . $status . ($message ? ': ' . strip_tags((string) $message) : '.')
        );
    }
    if ($body !== '' && json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('AzuraCast returned an invalid JSON response.');
    }
    return $decoded;
}

function azuracast_log_request(array $params, $method, $url, $request, $response, $status)
{
    if (!function_exists('logModuleCall')) {
        return;
    }
    $safeUrl = preg_replace('#^https?://[^/]+#', '', (string) $url);
    logModuleCall(
        'azuracast',
        strtoupper((string) $method) . ' ' . $safeUrl,
        $request,
        $response,
        null,
        [(string) ($params['serverpassword'] ?? ''), (string) ($params['serveraccesshash'] ?? '')]
    );
}

function azuracast_error(Throwable $e)
{
    return 'AzuraCast: ' . $e->getMessage();
}
