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

    /** Resolve the tenant row by REQUEST HOST — needs nothing from Tenant::resolve(). */
    private static function bootstrapByHost(): ?array
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host);
        $root = strtolower(trim((string) (function_exists('env') ? env('APP_DOMAIN', 'optms.co.in') : 'optms.co.in')));
        if ($host === '' || $host === $root) { return null; }        // bare portal domain — no tenant
        if (!preg_match('/^(.*)\.' . preg_quote($root, '/') . '$/', $host, $m)) { return null; } // other/custom host
        $slug = strtolower(trim($m[1]));
        if (strpos($slug, '.') !== false) { $slug = substr($slug, strrpos($slug, '.') + 1); }
        if ($slug === '' || $slug === 'www') { return null; }
        try {
            $row = Database::master()->query(
                'SELECT id, status, trial_ends_at FROM clients WHERE subdomain = '
                . Database::master()->quote($slug) . ' LIMIT 1'
            )->fetch();
            return is_array($row) && $row ? $row : null;
        } catch (Throwable $e) { return null; }                        // fail-open
    }

    /** One call, pasted in middleware/tenant.php: suspended tenants get a clean stop page. */
    public static function enforce(): void
    {
        $t = self::bootstrapByHost();
        if (!$t || (string) ($t['status'] ?? '') !== 'suspended') { return; }
        http_response_code(402); // Payment Required — the semantic signal
        if (!headers_sent()) { header('Retry-After: 3600'); }
        exit('<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account paused</title></head>'
            . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F4F5F9;font-family:-apple-system,Segoe UI,Roboto,sans-serif">'
            . '<div style="background:#fff;border:1px solid #E5E7F0;border-radius:18px;padding:38px 36px;max-width:430px;text-align:center;box-shadow:0 20px 50px rgba(36,30,110,.10)">'
            . '<div style="font-size:18px;font-weight:750;color:#1A1D27;margin-bottom:8px">Account paused</div>'
            . '<p style="margin:0;font-size:13.5px;line-height:1.6;color:#5B6270">This pharmacy console is temporarily paused.<br>Please contact <b>OPTMS-RX support</b> to resume service.</p>'
            . '</div></body></html>');
    }

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
