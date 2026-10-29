<?php
/** Billing engine actions: generate invoices, record payment state, edit plans/settings. */
require __DIR__ . '/_lib.php';

function bills(): never { backTo('/super-admin/billing.php'); }

/**
 * WhatsApp dues reminder for ONE unpaid invoice row (joined with its client;
 * client_status + due_date expected). Dedupe: once per invoice per day, counted
 * from wa_log payloads (the invoice number is embedded in every message).
 * Returns ['ok' => bool, 'why' => string]. Never throws — billing UX first.
 */
function remindInvoice(array $i): array
{
    $to = trim((string) ($i['whatsapp'] ?? ''));
    if ($to === '') { $to = trim((string) ($i['phone'] ?? '')); }
    if ($to === '') { return ['ok' => false, 'why' => 'no number on file']; }
    $invNo = (string) ($i['invoice_no'] ?? '');
    try {
        $n = master()->query("SELECT COUNT(*) n FROM wa_log WHERE kind = 'due_reminder' AND created_at >= CURDATE() AND payload LIKE "
            . master()->quote('%' . $invNo . '%'))->fetch();
        if ($n && (int) ($n['n'] ?? 0) > 0) { return ['ok' => false, 'why' => 'already reminded today']; }
    } catch (Throwable $e) { /* no log table yet → dedupe skipped, send still one call */ }

    $dueTs = strtotime((string) ($i['due_date'] ?? ''));
    $dueTxt = $dueTs ? date('d M Y', $dueTs) : 'now';
    $amt = '₹' . number_format((float) ($i['amount'] ?? 0), 2);
    $overdue = $dueTs && $dueTs < strtotime(date('Y-m-d'));
    $days = $overdue ? (int) floor((time() - $dueTs) / 86400) : 0;
    $msg = $overdue
        ? 'Invoice ' . $invNo . ' of ' . $amt . ' is overdue by ' . $days . ' day(s) (was due ' . $dueTxt . ').'
        : 'Invoice ' . $invNo . ' of ' . $amt . ' is due on ' . $dueTxt . '.';
    $upi = trim(billingSetting('upi_id', ''));
    if ($upi !== '') {
        $msg .= ' Pay via UPI ' . $upi;
        $note = trim(billingSetting('pay_note', ''));
        if ($note !== '') { $msg .= ' — ' . $note; }
        $msg .= '.';
    }
    if (strtolower((string) ($i['client_status'] ?? '')) === 'suspended') {
        $msg .= ' Your OPTMS-RX console is currently suspended — payment restores access immediately.';
    } else {
        $msg .= ' Your OPTMS-RX service continues uninterrupted once received. Thank you!';
    }
    waEnsure();
    $r = waSend((int) ($i['client_id'] ?? 0), $to, 'alert',
                [(string) ($i['name'] ?? 'there'), mb_substr($msg, 0, 300), date('d M Y')], $msg);
    return ['ok' => !empty($r['ok']), 'why' => empty($r['ok']) ? substr((string) ($r['error'] ?? 'send failed'), 0, 140) : 'sent'];
}

/** Rows for the reminder queries — one place, both actions. */
function remindRowsSql(string $where): string
{
    return "SELECT ci.id, ci.client_id, ci.invoice_no, ci.amount, ci.due_date,
                   c.name, c.whatsapp, c.phone, c.status AS client_status
              FROM client_invoices ci JOIN clients c ON c.id = ci.client_id
             WHERE {$where}
             ORDER BY ci.due_date ASC, ci.id ASC";
}
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
                if (coverInvoiceExists((int) $c['id'], $today)) { $why((string) $c['name'] . ' already covered'); continue; }
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
            /* WhatsApp receipt when a payment is marked collected — best-effort,
               billing itself already committed. */
            if ($act === 'pay' && $row) {
                try {
                    waEnsure();
                    $inv2 = master()->query(
                        "SELECT ci.invoice_no, ci.amount, ci.period_from, ci.period_to, c.name, c.whatsapp
                           FROM client_invoices ci JOIN clients c ON c.id = ci.client_id
                          WHERE ci.id = {$inv} LIMIT 1")->fetch();
                    if ($inv2 && (string) ($inv2['whatsapp'] ?? '') !== '') {
                        $r = waSend((int) $row['client_id'], (string) $inv2['whatsapp'], 'paid', [
                            (string) $inv2['name'], (string) $inv2['invoice_no'],
                            '₹' . number_format((float) $inv2['amount'], 2),
                            date('d M Y', strtotime((string) $inv2['period_from'])) . '–' . date('d M Y', strtotime((string) $inv2['period_to'])),
                        ], 'Payment received — invoice ' . $inv2['invoice_no'] . ' of ₹' . number_format((float) $inv2['amount'], 2)
                          . ' for ' . date('d M Y', strtotime((string) $inv2['period_from'])) . '–' . date('d M Y', strtotime((string) $inv2['period_to'])) . '. Thank you! Your OPTMS-RX console stays active.');
                        audit('WA_RECEIPT', (int) $row['client_id'], (string) $row['invoice_no'] . ' → ' . ($r['ok'] ? 'receipt sent' : ($r['error'] ?? 'failed')));
                    }
                } catch (Throwable $waErr) { /* never breaks billing */ }
            }
            flashOk('Invoice updated.');
            bills();

        /* ---------- dues reminders (WhatsApp) ---------- */
        case 'remind':
            $inv = (int) ($_POST['invoice_id'] ?? 0);
            $i = master()->query(remindRowsSql("ci.id = {$inv} AND ci.status = 'unpaid'") . ' LIMIT 1')->fetch();
            if (!$i) { flashErr('Only unpaid invoices can be reminded.'); bills(); }
            $res = remindInvoice($i);
            audit('WA_DUE_REMIND', (int) $i['client_id'], (string) $i['invoice_no'] . ' → ' . $res['why']);
            if ($res['ok']) { flashOk('WhatsApp reminder sent to ' . $i['invoice_no'] . '.'); }
            else { flashErr('Reminder not sent: ' . $res['why'] . '.'); }
            bills();

        case 'remind_overdue':
            $qt = master()->quote($today);
            $rows = master()->query(remindRowsSql("ci.status = 'unpaid' AND ci.due_date < {$qt}") . ' LIMIT 25')->fetchAll();
            if (!$rows) { flashOk('Nothing overdue — no reminders sent.'); bills(); }
            $sent = 0; $skipped = [];
            foreach ($rows as $i) {
                $res = remindInvoice($i);
                if ($res['ok']) { $sent++; } else { $skipped[] = $i['invoice_no'] . ' (' . $res['why'] . ')'; }
            }
            audit('WA_DUE_BULK', null, 'sent=' . $sent . ' skipped=' . count($skipped));
            $note = $skipped ? ' · skipped: ' . implode(', ', array_slice($skipped, 0, 6)) : '';
            flashOk($sent . ' reminder(s) sent' . $note);
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
                      'pay_note' => trim((string) ($_POST['pay_note'] ?? '')),
                      'company_name' => trim((string) ($_POST['company_name'] ?? '')),
                      'company_addr' => trim((string) ($_POST['company_addr'] ?? '')),
                      'company_contact' => trim((string) ($_POST['company_contact'] ?? '')),
                      'company_gstin' => trim((string) ($_POST['company_gstin'] ?? '')),
                      'company_email' => trim((string) ($_POST['company_email'] ?? ''))] as $k => $v) {
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
