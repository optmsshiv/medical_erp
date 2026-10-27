<?php
/** WhatsApp (Meta Cloud API) control: settings, client numbers, alerts, test.
 *  Every send is best-effort — nothing here may break the portal flow, so all
 *  network/DB paths are guarded and results land in wa_log for the audit view. */
require __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { backTo('/super-admin/whatsapp.php'); }
$act = trim((string) ($_POST['act'] ?? ''));

try {
    switch ($act) {

        /* ---------- credentials & templates ---------- */
        case 'save_cfg':
            waEnsure();
            $fields = [
                'wa_version' => trim((string) ($_POST['wa_version'] ?? 'v21.0')) ?: 'v21.0',
                'wa_phone_id' => trim((string) ($_POST['wa_phone_id'] ?? '')),
                'wa_lang' => trim((string) ($_POST['wa_lang'] ?? 'en')) ?: 'en',
                'wa_cc' => preg_replace('/\D/', '', (string) ($_POST['wa_cc'] ?? '91')) ?: '91',
                'wa_tpl_onboard' => trim((string) ($_POST['wa_tpl_onboard'] ?? '')),
                'wa_tpl_paid' => trim((string) ($_POST['wa_tpl_paid'] ?? '')),
                'wa_tpl_alert' => trim((string) ($_POST['wa_tpl_alert'] ?? '')),
            ];
            $tok = trim((string) ($_POST['wa_token'] ?? ''));
            if ($tok !== '' && $tok !== '••keep••') { $fields['wa_token'] = $tok; }   // blank = keep stored token
            $fields['wa_enabled'] = (($_POST['wa_enabled'] ?? '0') === '1') ? '1' : '0';   // select, not checkbox — '0' must stick
            foreach ($fields as $k => $v) {
                master()->query('INSERT INTO billing_settings (`key`, `value`) VALUES (' . master()->quote($k) . ', ' . master()->quote($v) . ') ON DUPLICATE KEY UPDATE `value` = ' . master()->quote($v));
            }
            audit('WA_CONFIG', null, 'enabled=' . $fields['wa_enabled'] . ' version=' . $fields['wa_version'] . ' phone_id=' . ($fields['wa_phone_id'] !== '' ? 'set' : 'cleared')
                . ' templates=' . implode('/', array_filter([$fields['wa_tpl_onboard'], $fields['wa_tpl_paid'], $fields['wa_tpl_alert']])));
            flashOk('WhatsApp settings saved' . ($fields['wa_enabled'] === '1' ? ' — delivery ON.' : ' — delivery stays OFF until enabled.'));
            break;

        /* ---------- per-client numbers ---------- */
        case 'save_numbers':
            waEnsure();
            $n = 0;
            foreach ((array) ($_POST['wa'] ?? []) as $cid => $val) {
                $cid = (int) $cid;
                if ($cid <= 0) { continue; }
                $num = waNorm((string) $val, waCfg()['cc']);
                master()->query('UPDATE clients SET whatsapp = ' . ($num === '' ? 'NULL' : master()->quote($num)) . ' WHERE id = ' . $cid . ' LIMIT 1');
                $n++;
            }
            audit('WA_NUMBERS', null, $n . ' client number(s) updated');
            flashOk($n ? "WhatsApp numbers saved for {$n} client(s)." : 'Nothing to save.');
            break;

        /* ---------- test ping ---------- */
        case 'test':
            $to = trim((string) ($_POST['test_to'] ?? ''));
            $text = trim((string) ($_POST['test_text'] ?? ''));
            if ($to === '') { flashErr('Enter the destination number first.'); break; }
            $r = waSend(null, $to, 'test', [], $text !== '' ? $text : 'OPTMS-RX test — WhatsApp wiring is live.');
            if ($r['ok']) { flashOk('Test delivered — Meta message id ' . ($r['api_id'] ?: '(none)') . '.'); }
            else { flashErr('Test failed: ' . ($r['error'] ?? 'unknown')); }
            break;

        /* ---------- alert broadcast ---------- */
        case 'send_alert':
            $kind = 'alert';
            $tplOverride = trim((string) ($_POST['tpl_override'] ?? ''));
            $text = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 900);
            if ($text === '') { flashErr('Write the alert message first.'); break; }
            $scope = trim((string) ($_POST['recipients'] ?? 'active'));
            $where = "whatsapp IS NOT NULL AND whatsapp <> ''";
            if ($scope === 'active') { $where .= " AND status = 'active'"; }
            if ($scope === 'trial')  { $where .= " AND status = 'trial'"; }
            if ($scope === 'paid')   { $where .= " AND status IN ('active','trial')"; }
            if ($scope === 'suspended') { $where .= " AND status = 'suspended'"; }
            $rows = master()->query("SELECT id, name, whatsapp FROM clients WHERE {$where} ORDER BY id ASC")->fetchAll();
            if (!$rows) { flashErr('No reachable clients match that audience — numbers are set in the panel above.'); break; }
            $sent = 0; $fail = 0; $firstErr = '';
            foreach ($rows as $row) {
                $r = waSend((int) $row['id'], (string) $row['whatsapp'], $kind, [(string) $row['name'], $text, date('d M Y')], $text, $tplOverride);
                if ($r['ok']) { $sent++; } else { $fail++; if ($firstErr === '') { $firstErr = (string) ($r['error'] ?? ''); } }
                usleep(150000);                                               // gentle on the per-message rate limit
            }
            audit('WA_ALERT', null, "broadcast to " . count($rows) . " client(s): {$sent} sent, {$fail} failed ({$scope})");
            flashOk($sent ? "Alert sent to {$sent} client(s)" . ($fail ? ", {$fail} failed — see the delivery log below" : '') : 'Nothing sent.');
            if (!$sent && $firstErr !== '') { flashErr('All sends failed: ' . $firstErr); }
            break;

        default:
            flashErr('Unknown WhatsApp action.');
    }
} catch (Throwable $e) {
    flashErr($e->getMessage());
}
backTo('/super-admin/whatsapp.php');
