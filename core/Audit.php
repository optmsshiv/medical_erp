<?php
/**
 * Audit
 * Call Audit::log() from any endpoint after a real write action completes.
 * Never throws — a logging failure should never break the action it's
 * trying to record.
 */
require_once __DIR__ . '/Tenant.php';

class Audit
{
    public static function log(string $action, string $detail = ''): void
    {
        try {
            $user = Auth::user();
            $stmt = Tenant::db()->prepare(
                'INSERT INTO audit_logs (user_id, user_name, action, detail) VALUES (:uid, :uname, :action, :detail)'
            );
            $stmt->execute([
                'uid'    => $user['id'] ?? null,
                'uname'  => $user['name'] ?? 'System',
                'action' => $action,
                'detail' => $detail,
            ]);
        } catch (Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
