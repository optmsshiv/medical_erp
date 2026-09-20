<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Customer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        Json::ok(['data' => Customer::all('name')]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');

        if ($name === '') {
            Json::error('Customer name is required.', 422);
        }

        $id = Customer::create([
            'name'    => $name,
            'phone'   => trim($input['phone'] ?? ''),
            'address' => trim($input['address'] ?? ''),
        ]);

        Json::ok(['id' => $id]);
        break;

    default:
        Json::error('Method not allowed.', 405);
}
