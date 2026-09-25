<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Category.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

/** Find another category with the same name, if any (excludes $excludeId on edit). */
function findConflict(string $name, ?int $excludeId = null): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $row = Category::first('name', '=', $name);
    if ($row && ($excludeId === null || (int) $row['id'] !== $excludeId)) {
        return $row;
    }
    return null;
}

switch ($method) {
    case 'GET':
        // medicine_count lets the frontend show usage and warn before a delete
        // that's about to hit the FK guard on medicines.category_id.
        Json::ok(['data' => Category::query(
            'SELECT c.*, COUNT(m.id) AS medicine_count
             FROM categories c
             LEFT JOIN medicines m ON m.category_id = c.id
             GROUP BY c.id
             ORDER BY c.name'
        )]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');

        if ($name === '') {
            Json::error('Category name is required.', 422);
        }
        if (findConflict($name)) {
            Json::error("A category named \"{$name}\" already exists.", 422);
        }

        $id = Category::create(['name' => $name]);
        Json::ok(['id' => $id]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');

        if (!$id || !Category::find($id)) {
            Json::error('Category not found.', 404);
        }
        if ($name === '') {
            Json::error('Category name is required.', 422);
        }
        if ($conflict = findConflict($name, $id)) {
            Json::error("A category named \"{$name}\" already exists.", 422);
        }

        Category::update($id, ['name' => $name]);
        Json::ok();
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);

        if (!$id || !Category::find($id)) {
            Json::error('Category not found.', 404);
        }

        try {
            Category::delete($id);
            Json::ok();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Json::error('This category is assigned to one or more medicines and can\'t be deleted. Reassign them first.', 409);
            }
            throw $e;
        }
        break;

    default:
        Json::error('Method not allowed.', 405);
}
