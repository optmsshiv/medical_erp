<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Medicine.php';
require dirname(__DIR__, 2) . '/models/Category.php';
require dirname(__DIR__, 2) . '/models/Manufacturer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * The frontend's category/manufacturer fields are plain name strings
 * (picked from a <select> populated off the real categories/manufacturers
 * tables). Look up the matching id, creating the row if someone ever
 * sends a name that doesn't exist yet — keeps this endpoint from
 * breaking if a name is typed that isn't in the seeded list.
 */
function findOrCreateId(string $modelClass, string $name): ?int
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $existing = $modelClass::first('name', '=', $name);
    if ($existing) {
        return (int) $existing['id'];
    }

    return $modelClass::create(['name' => $name]);
}

/** Map the frontend's camelCase payload to our snake_case columns. */
function mapPayloadToRow(array $input): array
{
    return [
        'name'            => trim($input['name'] ?? ''),
        'generic_name'    => $input['generic'] ?? '',
        'composition'     => $input['composition'] ?? '',
        'category_id'     => findOrCreateId('Category', $input['category'] ?? ''),
        'manufacturer_id' => findOrCreateId('Manufacturer', $input['manufacturer'] ?? ''),
        'hsn_code'        => $input['hsn'] ?? '',
        'gst_rate'        => (float) ($input['gst'] ?? 0),
        'unit'            => $input['unit'] ?? 'Strip',
        'pack_size'       => $input['packSize'] ?? '',
        'pack_qty'        => max(1, (int) ($input['packQty'] ?? 1)),
        'sub_unit'        => trim($input['subUnit'] ?? ''),
        'allow_loose_sale' => !empty($input['allowLoose']) ? 1 : 0,
        'mrp'             => (float) ($input['mrp'] ?? 0),
        'retail_rate'     => (float) ($input['retailRate'] ?? $input['mrp'] ?? 0),
        'purchase_rate'   => (float) ($input['purchaseRate'] ?? 0),
        'wholesale_rate'  => (float) ($input['wholesaleRate'] ?? 0),
        'min_stock'       => (int) ($input['minStock'] ?? 0),
        'reorder_level'   => (int) ($input['reorderLevel'] ?? 0),
        'schedule_class'  => $input['schedule'] ?? 'OTC',
        'rx_required'     => !empty($input['rxRequired']) ? 1 : 0,
        'status'          => strtolower($input['status'] ?? 'active'),
    ];
}

switch ($method) {
    case 'GET':
        Json::ok(['data' => Medicine::all('name')]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $row = mapPayloadToRow($input);

        if ($row['name'] === '') {
            Json::error('Medicine name is required.', 422);
        }
        if ($row['mrp'] <= 0) {
            Json::error('MRP is required.', 422);
        }

        $id = Medicine::create($row);
        Json::ok(['id' => $id]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($input['id'] ?? 0);

        if (!$id || !Medicine::find($id)) {
            Json::error('Medicine not found.', 404);
        }

        $row = mapPayloadToRow($input);

        if ($row['name'] === '') {
            Json::error('Medicine name is required.', 422);
        }

        Medicine::update($id, $row);
        Json::ok();
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);

        if (!$id || !Medicine::find($id)) {
            Json::error('Medicine not found.', 404);
        }

        // Batches cascade-delete automatically (FK ON DELETE CASCADE).
        Medicine::delete($id);
        Json::ok();
        break;

    default:
        Json::error('Method not allowed.', 405);
}