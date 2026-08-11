<?php

define('WHMCS', true);
require dirname(__DIR__) . '/azuracast/azuracast.php';

final class FakeProperties
{
    public $values = [];
    public function get($key) { return $this->values[$key] ?? null; }
    public function save(array $values) { $this->values = array_merge($this->values, $values); }
}

final class FakeModel
{
    public $serviceProperties;
    public function __construct() { $this->serviceProperties = new FakeProperties(); }
}

function expect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$calls = [];
$GLOBALS['azuracast_request_override'] = function ($params, $method, $path, $data) use (&$calls) {
    $calls[] = [$method, $path, $data];
    if ($method === 'GET' && strpos($path, '/api/admin/stations') === 0) return [];
    if ($method === 'POST' && $path === '/api/admin/stations') {
        return ['id' => 42, 'media_storage_location' => 7, 'name' => $data['name']];
    }
    return ['success' => true];
};

$model = new FakeModel();
$params = [
    'model' => $model,
    'serviceid' => 99,
    'domain' => 'My Test Radio',
    'username' => 'My Test Radio',
    'configoption1' => '5000',
    'configoption2' => '250',
    'configoption3' => '192',
    'configoption4' => 'icecast',
    'configoption5' => 'Europe/Amsterdam',
    'serverhostname' => 'radio.example.com',
    'serversecure' => true,
    'serverport' => 443,
];

expect(azuracast_CreateAccount($params) === 'success', 'CreateAccount failed');
expect($model->serviceProperties->get(AZURACAST_STATION_ID_PROPERTY) === '42', 'Station ID was not saved');
expect($calls[1][1] === '/api/admin/stations', 'Wrong station creation route');
expect($calls[1][2]['frontend_config']['max_listeners'] === 250, 'Listener limit is wrong');
expect($calls[1][2]['max_bitrate'] === 192, 'Bitrate limit is wrong');
expect($calls[2][1] === '/api/admin/storage_location/7', 'Storage quota route is wrong');
expect($calls[2][2]['storageQuotaBytes'] === 5242880000, 'Storage quota conversion is wrong');

$calls = [];
expect(azuracast_SuspendAccount($params) === 'success', 'SuspendAccount failed');
expect($calls[0][0] === 'PUT' && $calls[0][1] === '/api/admin/station/42', 'Wrong suspend route');
expect($calls[0][2]['is_enabled'] === false, 'Suspend did not disable station');

$calls = [];
expect(azuracast_UnsuspendAccount($params) === 'success', 'UnsuspendAccount failed');
expect($calls[1][1] === '/api/station/42/restart', 'Unsuspend did not restart station');

$calls = [];
expect(azuracast_Stop($params) === 'success', 'Stop failed');
expect($calls[0][1] === '/api/station/42/backend/stop', 'Wrong backend stop route');
expect($calls[1][1] === '/api/station/42/frontend/stop', 'Wrong frontend stop route');

$clientArea = azuracast_ClientArea($params);
expect(is_array($clientArea), 'ClientArea must return a template response');
expect($clientArea['templatefile'] === 'clientarea', 'ClientArea template is wrong');
expect(isset($clientArea['vars']['azuracastManageUrl']), 'ClientArea management URL is missing');

$calls = [];
expect(azuracast_TerminateAccount($params) === 'success', 'TerminateAccount failed');
expect($calls[0][0] === 'DELETE' && $calls[0][1] === '/api/admin/station/42', 'Wrong delete route');
expect($model->serviceProperties->get(AZURACAST_STATION_ID_PROPERTY) === '', 'Station ID was not cleared');

expect(azuracast_short_name('', 'Hello, World!', 2) === 'hello_world', 'Short-name normalization failed');
expect(azuracast_base_url(['serverhostname' => 'radio.example.com', 'serversecure' => true, 'serverport' => 443]) === 'https://radio.example.com', 'Base URL failed');

echo "All module tests passed.\n";
