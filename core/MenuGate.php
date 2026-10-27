<?php
/**
 * core/MenuGate.php — the roles matrix, enforced where it counts: the server.
 * ---------------------------------------------------------------------------
 * Pages call MenuGate::enforce() right after the auth middleware. A pharmacy
 * user who TYPES a blocked URL (or scripts a fetch) now gets a clean 403
 * instead of the page — the same rule the sidebar already applies visually.
 *
 * Two authorities, one gate:
 *   1. TENANT role matrix (Settings ▸ Roles & Permissions, table
 *      role_permissions): blocks a role from a menu the owner unticked.
 *      Owner/admin (and unknown legacy roles) pass; unconfigured menus stay
 *      open — identical fail-open semantics to the UI, so nobody can lock
 *      themselves out by forgetting a tick.
 *   2. PLATFORM kill-switch (super-admin ▸ Feature control, keys
 *      'menu_<slug-of-Label>' in client_features): a paid-plan switch that
 *      binds EVERY role including the owner — if the module was sold off the
 *      plan, nobody in the tenant opens it.
 *
 * Upload to: <app root>/core/MenuGate.php
 */
final class MenuGate
{
    /** page basename → [ menu label (or '__ANY__'), legacy feature keys ]. */
    private const PAGES = [
        'retail-pos.php'         => ['Retail POS', []],
        'wholesale-billing.php'  => ['Wholesale Billing', ['wholesale']],
        'sales-invoices.php'     => ['Sales Invoices', []],
        'sales-return.php'       => ['Sales Returns', []],
        'customers.php'          => ['Customers', []],
        'customer-dues.php'      => ['Customer Dues', ['customer_dues']],
        'purchase.php'           => ['New Purchase', []],
        'purchase-invoices.php'  => ['Purchase Invoices', []],
        'suppliers.php'          => ['Suppliers', []],
        'supplier-dues.php'      => ['Supplier Dues', ['supplier_dues']],
        'medicine-master.php'    => ['Products / Medicines', []],
        'stock-overview.php'     => ['Stock Overview', []],
        'medicine-ledger.php'    => ['Medicine Ledger', []],
        'prescriptions.php'      => ['Prescriptions', []],
        'doctors.php'            => ['Doctors', []],
        'manufacturers.php'      => ['Manufacturers', []],
        'categories.php'         => ['Medicine Categories', []],
        'schedules.php'          => ['Schedule / Class', []],
        'compliance-reports.php' => ['Compliance Register', ['compliance_export']],
        'payments.php'           => ['Payments', []],
        'expenses.php'           => ['Expenses', ['expenses']],
        'cash-book.php'          => ['Cash Book', []],
        // settings.php bundles six admin tabs; it opens if ANY of them is allowed.
        'settings.php'           => ['__ANY__', ['Users', 'Roles & Permissions', 'Store Settings', 'Invoice Settings', 'Tax Settings', 'Audit Logs']],
    ];

    public static function enforce(): void
    {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $entry = self::PAGES[$script] ?? null;
        if ($entry === null) { return; }                       // page without a menu authority (login, subscription…)
        $isAny = $entry[0] === '__ANY__';
        $labels = $isAny ? $entry[1] : [$entry[0]];
        $legacyKeys = $isAny ? [] : $entry[1];

        $user = null;
        if (class_exists('Auth')) { try { $user = Auth::user(); } catch (Throwable $e) { $user = null; } }
        if (!is_array($user)) { $user = isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : []; }
        $role = strtolower(trim((string) (($user['role'] ?? $_SESSION['role'] ?? $_SESSION['user_role'] ?? ''))));
        $privileged = ($role === '' || strpos($role, 'owner') !== false || strpos($role, 'admin') !== false);

        $features = self::features();
        $why = 'role';
        $anyAlive = false;
        foreach ($labels as $label) {
            $killed = false;
            $featKeys = $isAny ? [self::featKey($label)] : array_merge([self::featKey($label)], $legacyKeys);
            foreach ($featKeys as $fk) {
                if (array_key_exists($fk, $features) && !$features[$fk]) { $killed = true; $why = 'platform'; }
            }
            if ($killed) { continue; }
            $anyAlive = true;
            if ($privileged || self::roleAllows($role, $label)) { return; }   // one allowed path in = the page opens
            $why = 'role';
        }
        if (!$anyAlive) { $why = 'platform'; }
        self::deny(implode(' / ', $labels), $why);
    }

    /** 'menu_<slug>' — identical derivation to MF._featKey() / CLIENT_FEATURE_GROUPS. */
    public static function featKey(string $label): string
    {
        return 'menu_' . trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)) ?? '', '_');
    }

    /** Platform feature map from TenantPolicy (empty array when absent/failing → allow all). */
    private static function features(): array
    {
        static $f = null;
        if ($f !== null) { return $f; }
        $f = [];
        if (class_exists('TenantPolicy')) {
            try {
                $tenant = null;
                if (class_exists('Tenant') && method_exists('Tenant', 'current')) {
                    $cur = @Tenant::current();
                    if (is_array($cur)) { $tenant = $cur; }
                }
                $r = TenantPolicy::row($tenant);
                if (is_array($r) && isset($r['features']) && is_array($r['features'])) { $f = $r['features']; }
            } catch (Throwable $e) { /* fail-open */ }
        }
        return $f;
    }

    /** Explicit unticked row in the tenant matrix → false. Missing row/table → true. */
    private static function roleAllows(string $role, string $label): bool
    {
        static $rows = null;
        if ($rows === null) {
            $rows = [];
            try {
                if (class_exists('Tenant') && method_exists('Tenant', 'db')) {
                    $pdo = Tenant::db();
                    foreach (['role_permissions', 'menu_permissions'] as $tbl) {
                        try {
                            $rows = $pdo->query("SELECT role, module, allowed FROM {$tbl}")->fetchAll(PDO::FETCH_ASSOC);
                            break;                                            // first existing table wins (even if empty)
                        } catch (Throwable $e) { /* try the fallback name */ }
                    }
                }
            } catch (Throwable $e) { $rows = []; }
        }
        if (!$rows) { return true; }                                          // no matrix data → open
        foreach ($rows as $r) {
            if (strtolower(trim((string) $r['role'])) === strtolower(trim($role))
                && trim((string) $r['module']) === $label) {
                return (bool) (int) $r['allowed'];
            }
        }
        return true;
    }

    private static function deny(string $labels, string $why): void
    {
        $wantsJson = (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch')
            || strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
        $msg = $why === 'platform'
            ? 'This module (' . $labels . ') is not part of your current OPTMS-RX plan. Contact your provider to enable it.'
            : 'Your role is not allowed to open ' . $labels . '. The owner can enable it under Settings ▸ Roles & Permissions.';
        http_response_code(403);
        if ($wantsJson) {
            header('Content-Type: application/json');
            exit(json_encode(['ok' => false, 'error' => $msg]));
        }
        exit('<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access restricted</title></head>'
            . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F4F5F9;font-family:-apple-system,Segoe UI,Roboto,sans-serif">'
            . '<div style="background:#fff;border:1px solid #E5E7F0;border-radius:18px;padding:38px 36px;max-width:460px;text-align:center;box-shadow:0 20px 50px rgba(36,30,110,.10)">'
            . '<div style="font-size:18px;font-weight:750;color:#1A1D27;margin-bottom:8px">Access restricted</div>'
            . '<p style="margin:0 0 18px;font-size:13.5px;line-height:1.6;color:#5B6270">' . htmlspecialchars($msg) . '</p>'
            . '<a href="index.php" style="display:inline-block;padding:9px 18px;border-radius:10px;background:#176B5B;color:#fff;text-decoration:none;font-size:13.5px;font-weight:600">Back to dashboard</a>'
            . '</div></body></html>');
    }
}
