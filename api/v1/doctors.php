<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/core/Audit.php';
require dirname(__DIR__, 2) . '/models/Doctor.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        Json::ok(['data' => Doctor::all('name')]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            Json::error('Doctor name is required.', 422);
        }
        $id = Doctor::create([
            'name'      => $name,
            'specialty' => trim($input['specialty'] ?? ''),
            'phone'     => trim($input['phone'] ?? ''),
            'reg_no'    => trim($input['regNo'] ?? ''),
        ]);
        Audit::log('DOCTOR_CREATE', $name);
        Json::ok(['id' => $id]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($input['id'] ?? 0);
        if (!$id || !Doctor::find($id)) {
            Json::error('Doctor not found.', 404);
        }
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            Json::error('Doctor name is required.', 422);
        }
        Doctor::update($id, [
            'name'      => $name,
            'specialty' => trim($input['specialty'] ?? ''),
            'phone'     => trim($input['phone'] ?? ''),
            'reg_no'    => trim($input['regNo'] ?? ''),
        ]);
        Json::ok();
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id || !Doctor::find($id)) {
            Json::error('Doctor not found.', 404);
        }
        Doctor::delete($id); // sales.doctor_id set to NULL automatically (ON DELETE SET NULL)
        Json::ok();
        break;

    default:
        Json::error('Method not allowed.', 405);
}
