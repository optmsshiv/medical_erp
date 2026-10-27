<?php
/* ---------------------------------------------------------------------------
 * api/v1/nav-state.php — platform feature map for the tenant sidebar.
 * GET { features: { "menu:Retail POS": true, "wholesale": false, ... } }
 *
 * What the super-admin's Feature control switched OFF for THIS client, served
 * to the app shell so nav items vanish live (app.js merges it into
 * MF._feats; menuAllowed() consults it before the role matrix). Absent file or
 * unreachable master DB → fetch fails client-side and everything stays open:
 * the same fail-open promise the rest of the control plane makes.
 * ------------------------------------------------------------------------- */
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$feats = [];
if (class_exists('TenantPolicy')) {
    try {
        $tenant = null;
        if (class_exists('Tenant') && method_exists('Tenant', 'current')) {
            $cur = @Tenant::current();
            if (is_array($cur)) { $tenant = $cur; }
        }
        $r = TenantPolicy::row($tenant);
        if (is_array($r) && isset($r['features']) && is_array($r['features'])) { $feats = $r['features']; }
    } catch (Throwable $e) { /* fail-open: no map → all menus live */ }
}

Json::ok(['data' => ['features' => (object) $feats]]);
