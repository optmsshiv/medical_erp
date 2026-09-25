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

/** id if it exists, else the previous name (`from`, or `name` on the query string). 0 if none match. */
function resolveManufacturerId(array $input = [], array $query = []): int
{
    $id = (int) ($input['id'] ?? $query['id'] ?? 0);
    if ($id && Manufacturer::find($id)) {
        return $id;
    }
    $from = trim((string) ($input['from'] ?? $query['from'] ?? $query['name'] ?? ''));
    if ($from !== '') {
        $row = Manufacturer::first('name', '=', $from);
        return $row ? (int) $row['id'] : 0;
    }
    $name = trim((string) ($input['name'] ?? ''));
    if ($name !== '') {
        $row = Manufacturer::first('name', '=', $name);
        return $row ? (int) $row['id'] : 0;
    }
    return 0;
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
    $email = trim((string) $pick(['email'], $current['email'] ?? ''));
    $phone = trim((string) $pick(['phone'], $current['phone'] ?? ''));
    $gstin = strtoupper(trim((string) $pick(['gstin'], $current['gstin'] ?? '')));
    $address = trim((string) $pick(['address'], $current['address'] ?? ''));
    $status = trim((string) $pick(['status'], $current['status'] ?? 'Active'));
    $contact = preg_replace('/\s+/', ' ', $contact) ?? $contact;
    $phone = formatManufacturerPhone($phone);
    $status = preg_match('/^in/i', $status) ? 'Inactive' : 'Active';

    if (textLen($contact) > 120) {
        Json::error('Contact person must be 120 characters or fewer.', 422);
    }
    if ($email !== '' && !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
        Json::error('Enter a valid email address.', 422);
    }
    if (textLen($email) > 160) {
        Json::error('Email must be 160 characters or fewer.', 422);
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
        'email' => $email !== '' ? $email : null,
        'phone' => $phone !== '' ? $phone : null,
        'gstin' => $gstin !== '' ? $gstin : null,
        'address' => $address !== '' ? $address : null,
        'status' => $status,
    ];
}

/** Indian mobiles are stored as +91 56985 69565. Other numbers keep their spacing. */
function formatManufacturerPhone(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '';
    }
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }
    if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') {
        $national = substr($digits, 2);
        return '+91 ' . substr($national, 0, 5) . ' ' . substr($national, 5);
    }
    return preg_replace('/\s+/', ' ', $phone) ?? $phone;
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
        $name = trim($input['name'] ?? '');
        // Prefer id. Fall back to `from` (old name) so a save still finds the row
        // when the page only knows the name. If neither matches, create it —
        // a medicine-only name is not a 404.
        $id = resolveManufacturerId($input);
        $current = $id ? Manufacturer::find($id) : null;

        if ($name === '') {
            Json::error('Manufacturer name is required.', 422);
        }
        if (!$current) {
            if (findConflict($name)) {
                Json::error("A manufacturer named \"{$name}\" already exists.", 422);
            }
            $id = Manufacturer::create(array_merge(['name' => $name], profileFromInput($input)));
            Json::ok(['id' => $id, 'created' => true]);
            break;
        }
        if ($conflict = findConflict($name, $id)) {
            Json::error("A manufacturer named \"{$name}\" already exists.", 422);
        }

        Manufacturer::update($id, array_merge(['name' => $name], profileFromInput($input, $current)));
        Json::ok(['id' => $id]);
        break;

    case 'DELETE':
        $id = resolveManufacturerId([], $_GET);

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
