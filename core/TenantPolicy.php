<?php
/**
 * Tenant control-plane reader — super-admin → pharmacy app enforcement bridge.
 * Reads the tenant's row from the master DB once per request (memoised).
 *
 * FAIL-OPEN BY DESIGN: if the master DB is unreachable or the tables are not
 * migrated yet, every answer degrades to "active / allowed" so tenants can
 * never be locked out by a control-plane hiccup. Revisit to fail-closed only
 * when the portal has named accounts + monitoring.
 *
 * Upload to: <app root>/core/TenantPolicy.php
 */
final class TenantPolicy
{
    private static ?array $cache = null; // ['status', 'trial_ends_at', 'features' => [k => bool]]

    public static function row(?array $tenant): ?array
    {
        if (self::$cache !== null) { return self::$cache; }
        self::$cache = null;
        try {
            $id = (int) ($tenant['id'] ?? $tenant['client_id'] ?? 0);
            if ($id <= 0) { return null; }
            $row = Database::master()->query(
                "SELECT status, trial_ends_at FROM clients WHERE id = {$id} LIMIT 1"
            )->fetch();
            $feats = [];
            try {
                foreach ((array) Database::master()->query(
                    "SELECT feature, allowed FROM client_features WHERE client_id = {$id}"
                )->fetchAll() as $f) {
                    $feats[$f['feature']] = (bool) $f['allowed'];
                }
            } catch (Throwable $e) { /* feature table not migrated yet → all allowed */ }
            self::$cache = [
                'status'        => (string) ($row['status'] ?? 'active'),
                'trial_ends_at' => (string) ($row['trial_ends_at'] ?? ''),
                'features'      => $feats,
            ];
        } catch (Throwable $e) { /* fail-open */ }
        return self::$cache;
    }

    public static function suspended(?array $tenant): bool
    {
        $r = self::row($tenant);
        return is_array($r) && ($r['status'] ?? '') === 'suspended';
    }

    public static function allowed(?array $tenant, string $feature): bool
    {
        $r = self::row($tenant);
        return !is_array($r) || (($r['features'][$feature]) ?? true); // default ON
    }

    /** Days left on trial, or null when no trial date is set. */
    public static function trialDaysLeft(?array $tenant): ?int
    {
        $r = self::row($tenant);
        $d = is_array($r) ? (string) ($r['trial_ends_at'] ?? '') : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { return null; }
        return (int) floor((strtotime($d) - strtotime(date('Y-m-d'))) / 86400);
    }
}
