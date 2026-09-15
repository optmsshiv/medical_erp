<?php
/**
 * Model
 *
 * Base class for every table model (Medicine, Sale, Batch, Customer, ...).
 * Every query automatically runs against the CURRENT client's database
 * (via Tenant::db()) — there is no store_id to remember, because each
 * client already has their own separate database.
 *
 * Usage:
 *   class Medicine extends Model {
 *       protected static string $table = 'medicines';
 *   }
 *
 *   Medicine::all();
 *   Medicine::find(5);
 *   Medicine::where('name', 'LIKE', '%para%');
 *   Medicine::create(['name' => 'Paracetamol 500mg', 'unit' => 'strip']);
 *   Medicine::update(5, ['sale_rate' => 12.50]);
 *   Medicine::delete(5);
 */
require_once __DIR__ . '/Tenant.php';

abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    protected static function db(): PDO
    {
        return Tenant::db();
    }

    protected static function table(): string
    {
        if (empty(static::$table)) {
            throw new RuntimeException(static::class . ' must define a protected static $table.');
        }
        return static::$table;
    }

    /** Fetch every row. Use where()/paginate() for anything large. */
    public static function all(string $orderBy = null): array
    {
        $sql = 'SELECT * FROM ' . static::table();
        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . self::safeIdentifier($orderBy);
        }
        return static::db()->query($sql)->fetchAll();
    }

    public static function find($id): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM ' . static::table() . ' WHERE ' . static::$primaryKey . ' = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Simple single-condition lookup, e.g.:
     *   Medicine::where('category_id', '=', 3)
     *   Medicine::where('name', 'LIKE', '%para%')
     */
    public static function where(string $column, string $operator, $value): array
    {
        self::assertSafeOperator($operator);
        $stmt = static::db()->prepare(
            'SELECT * FROM ' . static::table() . ' WHERE ' . self::safeIdentifier($column) . " {$operator} :value"
        );
        $stmt->execute(['value' => $value]);
        return $stmt->fetchAll();
    }

    public static function first(string $column, string $operator, $value): ?array
    {
        $rows = static::where($column, $operator, $value);
        return $rows[0] ?? null;
    }

    public static function count(): int
    {
        return (int) static::db()->query('SELECT COUNT(*) AS c FROM ' . static::table())->fetch()['c'];
    }

    /** @return int The new row's ID */
    public static function create(array $data): int
    {
        $columns = array_map([self::class, 'safeIdentifier'], array_keys($data));
        $placeholders = array_map(fn($c) => ':' . $c, array_keys($data));

        $sql = 'INSERT INTO ' . static::table()
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        static::db()->prepare($sql)->execute($data);

        return (int) static::db()->lastInsertId();
    }

    public static function update($id, array $data): bool
    {
        $set = implode(', ', array_map(fn($c) => self::safeIdentifier($c) . " = :{$c}", array_keys($data)));

        $sql = 'UPDATE ' . static::table() . " SET {$set} WHERE " . static::$primaryKey . ' = :__id';

        $data['__id'] = $id;

        return static::db()->prepare($sql)->execute($data);
    }

    public static function delete($id): bool
    {
        $stmt = static::db()->prepare(
            'DELETE FROM ' . static::table() . ' WHERE ' . static::$primaryKey . ' = :id'
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Escape hatch for anything the helpers above don't cover — still
     * runs against the current client's connection, still use bound params.
     *   Medicine::query('SELECT * FROM medicines WHERE quantity < :n', ['n' => 10]);
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Column/table names can't be bound as PDO params, so when a name is
     * built from a variable (order-by column, insert/update keys) we
     * whitelist-check it instead of trusting it outright.
     */
    private static function safeIdentifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+( (ASC|DESC))?$/i', $name)) {
            throw new InvalidArgumentException("Unsafe column name: {$name}");
        }
        return $name;
    }

    private static function assertSafeOperator(string $operator): void
    {
        $allowed = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];
        if (!in_array(strtoupper($operator), $allowed, true)) {
            throw new InvalidArgumentException("Unsafe operator: {$operator}");
        }
    }
}