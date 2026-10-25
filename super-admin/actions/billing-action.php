<?php
/** Billing engine actions: generate invoices, record payment state, edit plans/settings. */
require __DIR__ . '/_lib.php';

function bills(): never { backTo('/super-admin/billing.php'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { bills(); }

$act = trim((string) ($_POST['act'] ?? ''));
$today = date('Y-m-d');

try {
    switch ($act) {

        /* ---------- generate this cycle's invoices ---------- */
        case 'generate':
            $plans = planPrices();
            $grace = max(0, (int) billingSetting('grace_days', '7'));
            $made = 0; $skip = [];
            $rows = master()->query("SELECT * FROM clients WHERE status IN ('active','trial') ORDER BY id ASC")->fetchAll();
            foreach ($rows as $c) {
                $why = static function (string $r) use (&$skip): void { $skip[$r] = ($skip[$r] ?? 0) + 1; };
                $plan = strtolower((string) ($c['plan'] ?? 'trial'));
                if ($plan === 'trial') { $why('on Trial plan — trials are never billed (move the client to Standard/Annual first)'); continue; }
                $price = (float) ($plans[$plan]['price'] ?? 0);
                if ($price <= 0) { $why("plan '{$plan}' has price ₹0 — set it under Plan prices"); continue; }
                if ($plan === 'annual') {
                    $from = date('Y') . '-01-01'; $to = date('Y') . '-12-31';
                    $suffix = date('Y') . 'Y';
                } else {
                    $from = date('Y-m-01'); $to = date('Y-m-t');
                    $suffix = date('Ym');
                }
                if (!coverInvoiceExists((int) $c['id'], $today)) { $why('already covered by an invoice for this period'); continue; }
                $invNo = 'SUB-' . (int) $c['id'] . '-' . $suffix;
                $due   = date('Y-m-d', strtotime($from . ' +' . $grace . ' days'));
                master()->query(
                    'INSERT INTO client_invoices (client_id, invoice_no, period_from, period_to, plan, amount, status, due_date) VALUES ('
                    . (int) $c['id'] . ', ' . master()->quote($invNo) . ', ' . master()->quote($from) . ', ' . master()->quote($to) . ', '
                    . master()->quote($plan) . ', ' . number_format($price, 2, '.', '') . ", 'unpaid', " . master()->quote($due) . ')'
                    . " ON DUPLICATE KEY UPDATE status = 'unpaid', paid_at = NULL, amount = VALUES(amount), due_date = VALUES(due_date)"
                );
                $made++;
            }
            $skipText = $skip ? ' — skipped: ' . implode(' · ', array_map(static fn($k, $v) => "{$k} ×{$v}", array_keys($skip), $skip)) : '';
            audit('BILLING_GENERATE', null, 'invoices created: ' . $made . ($skipText ?: ', none skipped'));

            /* overdue auto-suspend — opt-in only (billing_settings.auto_suspend_overdue) */
            $suspended = 0;
            if (billingSetting('auto_suspend_overdue', '0') === '1') {
                $over = master()->query(
                    "SELECT DISTINCT ci.client_id FROM client_invoices ci
                       JOIN clients cl ON cl.id = ci.client_id AND cl.status <> 'suspended'
                      WHERE ci.status = 'unpaid' AND ci.due_date IS NOT NULL AND ci.due_date < " . master()->quote($today)
                )->fetchAll();
                foreach ($over as $o) {
                    master()->query("UPDATE clients SET status = 'suspended', suspended_at = NOW() WHERE id = " . (int) $o['client_id'] . ' LIMIT 1');
                    audit('AUTO_SUSPEND_OVERDUE', (int) $o['client_id'], 'due invoice crossed grace');
                    $suspended++;
                }
            }
            flashOk('Billing run complete: ' . $made . ' invoice' . ($made === 1 ? '' : 's') . ' created' . $skipText
                . ($suspended ? '. Auto-suspended for overdue: ' . $suspended : '') . '.');
            bills();

        /* ---------- lifecycle of one invoice ---------- */
        case 'pay':
        case 'unpay':
        case 'void':
            $inv = (int) ($_POST['invoice_id'] ?? 0);
            $map = ['pay' => ['paid', 'paid_at = NOW()'], 'unpay' => ['unpaid', 'paid_at = NULL'], 'void' => ['void', 'paid_at = NULL']];
            [$st, $extra] = $map[$act];
            master()->query("UPDATE client_invoices SET status = " . master()->quote($st) . ", {$extra} WHERE id = {$inv} LIMIT 1");
            $row = master()->query("SELECT client_id, invoice_no FROM client_invoices WHERE id = {$inv} LIMIT 1")->fetch();
            audit('INVOICE_' . strtoupper($act), (int) ($row['client_id'] ?? 0), (string) ($row['invoice_no'] ?? ''));

            // paying the last overdue invoice on a suspended client auto-resumes them
            if ($act === 'pay' && $row) {
                $cid = (int) $row['client_id'];
                $open = master()->query(
                    "SELECT COUNT(*) n FROM client_invoices WHERE client_id = {$cid} AND status = 'unpaid' AND due_date < " . master()->quote($today)
                )->fetch();
                if ($open && (int) $open['n'] === 0) {
                    $c = master()->query("SELECT status, trial_ends_at, name FROM clients WHERE id = {$cid} LIMIT 1")->fetch();
                    if ($c && $c['status'] === 'suspended') {
                        $to = (string) ($c['trial_ends_at'] ?? '');
                        $back = ($to !== '' && $to >= $today) ? 'trial' : 'active';
                        master()->query("UPDATE clients SET status = " . master()->quote($back) . ", suspended_at = NULL WHERE id = {$cid} LIMIT 1");
                        audit('AUTO_RESUME_PAID', $cid, (string) $c['name']);
                    }
                }
            }
            flashOk('Invoice updated.');
            bills();

        /* ---------- plan pricing ---------- */
        case 'save_plans':
            foreach ((array) ($_POST['plans'] ?? []) as $id => $row) {
                $price = (float) ($row['price'] ?? 0);
                $active = isset($row['active']) ? 1 : 0;
                master()->query('UPDATE billing_plans SET price = ' . number_format($price, 2, '.', '') . ", active = {$active} WHERE id = " . (int) $id . ' LIMIT 1');
            }
            audit('PLANS_PRICED', null, 'plan prices updated');
            flashOk('Plan prices saved.');
            bills();

        /* ---------- settings ---------- */
        case 'save_settings':
            $grace = max(0, (int) ($_POST['grace_days'] ?? 7));
            $auto  = isset($_POST['auto_suspend_overdue']) ? '1' : '0';
            foreach (['grace_days' => (string) $grace, 'auto_suspend_overdue' => $auto,
                      'upi_id' => trim((string) ($_POST['upi_id'] ?? '')),
                      'pay_note' => trim((string) ($_POST['pay_note'] ?? ''))] as $k => $v) {
                master()->query('INSERT INTO billing_settings (`key`, `value`) VALUES (' . master()->quote($k) . ', ' . master()->quote($v) . ') ON DUPLICATE KEY UPDATE `value` = ' . master()->quote($v));
            }
            audit('BILLING_SETTINGS', null, "grace={$grace} auto_suspend={$auto}");
            flashOk('Billing settings saved' . ($auto === '1' ? ' — overdue clients will now auto-suspend after grace.' : '.'));
            bills();

        default:
            flashErr('Unknown billing action.');
            bills();
    }
} catch (Throwable $e) {
    flashErr($e->getMessage());
    bills();
}
