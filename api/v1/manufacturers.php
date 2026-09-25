<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Manufacturer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

/** Find another manufacturer with the same name, if any (excludes $excludeId on edit). */
function findConflict(string $name, ?int $excludeId = null): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $row = Manufacturer::first('name', '=', $name);
    if ($row && ($excludeId === null || (int) $row['id'] !== $excludeId)) {
        return $row;
    }
    return null;
}

function textLen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

/**
 * Contact columns added by database/migrations/2026_09_25_add_manufacturer_contact_columns.sql.
 * Accepts contact_person or contact. On update, omitted keys keep the stored value.
 * Blank strings are stored as NULL.
 */
function profileFromInput(array $input, ?array $current = null): array
{
    $pick = function (array $keys, $fallback) use ($input) {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = $input[$key];
                return is_string($value) ? trim($value) : $value;
            }
        }
        return $fallback;
    };

    $contact = trim((string) $pick(['contact_person', 'contact'], $current['contact_person'] ?? ''));
    $phone = trim((string) $pick(['phone'], $current['phone'] ?? ''));
    $gstin = strtoupper(trim((string) $pick(['gstin'], $current['gstin'] ?? '')));
    $address = trim((string) $pick(['address'], $current['address'] ?? ''));
    $contact = preg_replace('/\s+/', ' ', $contact) ?? $contact;

    if (textLen($contact) > 120) {
        Json::error('Contact person must be 120 characters or fewer.', 422);
    }
    if (textLen($phone) > 32) {
        Json::error('Phone must be 32 characters or fewer.', 422);
    }
    if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone) ?? '') < 6) {
        Json::error('Enter a valid phone number.', 422);
    }
    if ($gstin !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
        Json::error('GSTIN must be 15 characters, like 27AABCU9603R1ZM.', 422);
    }
    if (textLen($address) > 500) {
        Json::error('Address must be 500 characters or fewer.', 422);
    }

    return [
        'contact_person' => $contact !== '' ? $contact : null,
        'phone' => $phone !== '' ? $phone : null,
        'gstin' => $gstin !== '' ? $gstin : null,
        'address' => $address !== '' ? $address : null,
    ];
}

switch ($method) {
    case 'GET':
        // medicine_count lets the frontend show usage and warn before a delete
        // that's about to hit the FK guard on medicines.manufacturer_id.
        // contact_person, phone, gstin and address come back on mf.* after the migration.
        Json::ok(['data' => Manufacturer::query(
            'SELECT mf.*, COUNT(m.id) AS medicine_count
             FROM manufacturers mf
             LEFT JOIN medicines m ON m.manufacturer_id = mf.id
             GROUP BY mf.id
             ORDER BY mf.name'
        )]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');

        if ($name === '') {
            Json::error('Manufacturer name is required.', 422);
        }
        if (findConflict($name)) {
            Json::error("A manufacturer named \"{$name}\" already exists.", 422);
        }

        $id = Manufacturer::create(array_merge(['name' => $name], profileFromInput($input)));
        Json::ok(['id' => $id]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $current = $id ? Manufacturer::find($id) : null;

        if (!$id || !$current) {
            Json::error('Manufacturer not found.', 404);
        }
        if ($name === '') {
            Json::error('Manufacturer name is required.', 422);
        }
        if ($conflict = findConflict($name, $id)) {
            Json::error("A manufacturer named \"{$name}\" already exists.", 422);
        }

        Manufacturer::update($id, array_merge(['name' => $name], profileFromInput($input, $current)));
        Json::ok();
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);

        if (!$id || !Manufacturer::find($id)) {
            Json::error('Manufacturer not found.', 404);
        }

        try {
            Manufacturer::delete($id);
            Json::ok();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Json::error('This manufacturer is assigned to one or more medicines and can\'t be deleted. Reassign them first.', 409);
            }
            throw $e;
        }
        break;

    default:
        Json::error('Method not allowed.', 405);
}
