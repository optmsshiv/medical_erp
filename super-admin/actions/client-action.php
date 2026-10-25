<?php
/** Lifecycle actions on one client: suspend, resume, extend trial, set plan. */
require __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { back(); }

$id     = (int) ($_POST['client_id'] ?? 0);
$act    = trim((string) ($_POST['act'] ?? ''));
$client = findClient($id);

if (!$client) { flashErr('Client not found.'); back(); }
$name = (string) ($client['name'] ?? '');

try {
    switch ($act) {

        case 'suspend':
            master()->query("UPDATE clients SET status = 'suspended'"
                . (hasColumn('clients', 'suspended_at') ? ', suspended_at = NOW()' : '')
                . ' WHERE id = ' . $id . ' LIMIT 1');
            audit('CLIENT_SUSPEND', $id, $name);
            flashOk('“' . $name . '” suspended — their console stops at the gate until you resume it.');
            break;

        case 'resume':
            // Come back as trial only when a future trial date stands; else active.
            $to = (string) ($client['trial_ends_at'] ?? '');
            $status = ($to !== '' && $to >= date('Y-m-d')) ? 'trial' : 'active';
            master()->query("UPDATE clients SET status = " . master()->quote($status)
                . (hasColumn('clients', 'suspended_at') ? ', suspended_at = NULL' : '')
                . ' WHERE id = ' . $id . ' LIMIT 1');
            audit('CLIENT_RESUME', $id, $name . ' → ' . $status);
            flashOk('“' . $name . '” resumed as ' . $status . '.');
            break;

        case 'extend_trial':
            $date = trim((string) ($_POST['trial_ends_at'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { flashErr('Pick a valid trial end date.'); back(); }
            if (!hasColumn('clients', 'trial_ends_at')) { flashErr('Run database/2026_10_20_super_admin_control.sql first — clients.trial_ends_at is missing.'); back(); }
            master()->query('UPDATE clients SET trial_ends_at = ' . master()->quote($date)
                . ", status = 'trial', suspended_at = NULL WHERE id = " . $id . ' LIMIT 1');
            audit('TRIAL_EXTEND', $id, $name . ' → ' . $date);
            flashOk('“' . $name . '” trial now runs until ' . date('d M Y', strtotime($date)) . '.');
            break;

        case 'set_plan':
            $plan = strtolower(trim((string) ($_POST['plan'] ?? '')));
            if (!in_array($plan, ['trial', 'standard', 'annual'], true)) { flashErr('Unknown plan.'); back(); }
            if (!hasColumn('clients', 'plan')) { flashErr('Run database/2026_10_20_super_admin_control.sql first — clients.plan is missing.'); back(); }
            $promote = ($plan !== 'trial' && strtolower((string) ($client['status'] ?? '')) === 'trial');
            master()->query('UPDATE clients SET plan = ' . master()->quote($plan)
                . ($promote ? ", status = 'active'" : '') . ' WHERE id = ' . $id . ' LIMIT 1');
            audit('PLAN_SET', $id, $name . ' → ' . $plan . ($promote ? ' (+activated)' : ''));
            flashOk('“' . $name . '” is now on the ' . ucfirst($plan) . ' plan.');
            break;

        case 'save_plan_trial':
            $changes = [];
            $newPlan = strtolower(trim((string) ($_POST['plan'] ?? '')));
            if ($newPlan !== '' && $newPlan !== strtolower((string) ($client['plan'] ?? 'trial'))) {
                if (!in_array($newPlan, ['trial', 'standard', 'annual'], true)) { flashErr('Unknown plan.'); back(); }
                if (!hasColumn('clients', 'plan')) { flashErr('Run database/2026_10_20_super_admin_control.sql first — clients.plan is missing.'); back(); }
                $promote = ($newPlan !== 'trial' && strtolower((string) ($client['status'] ?? '')) === 'trial');
                master()->query('UPDATE clients SET plan = ' . master()->quote($newPlan)
                    . ($promote ? ", status = 'active'" : '') . ' WHERE id = ' . $id . ' LIMIT 1');
                audit('PLAN_SET', $id, $name . ' → ' . $newPlan . ($promote ? ' (+activated)' : ''));
                $changes[] = 'plan → ' . ucfirst($newPlan) . ($promote ? ', status → Active' : '');
            }
            $newDate = trim((string) ($_POST['trial_ends_at'] ?? ''));
            $curDate = substr((string) ($client['trial_ends_at'] ?? ''), 0, 10);
            if ($newDate !== '' && $newDate !== $curDate) {
                if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $newDate)) { flashErr('Pick a valid trial end date.'); back(); }
                if (!hasColumn('clients', 'trial_ends_at')) { flashErr('Run database/2026_10_20_super_admin_control.sql first — clients.trial_ends_at is missing.'); back(); }
                $status = $newDate >= date('Y-m-d') ? 'trial' : 'active';
                master()->query('UPDATE clients SET trial_ends_at = ' . master()->quote($newDate)
                    . ', status = ' . master()->quote($status) . ', suspended_at = NULL WHERE id = ' . $id . ' LIMIT 1');
                audit('TRIAL_EXTEND', $id, $name . ' → ' . $newDate);
                $changes[] = 'trial ends ' . date('d M Y', strtotime($newDate)) . ' (status: ' . $status . ')';
            }
            flashOk($changes ? '“' . $name . '” — ' . implode(' · ', $changes) . '.' : '“' . $name . '” — nothing changed, nothing saved.');
            break;

        default:
            flashErr('Unknown action.');
    }
} catch (Throwable $e) {
    flashErr($e->getMessage());
}
back();
