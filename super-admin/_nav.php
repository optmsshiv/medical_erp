<?php
/**
 * _nav.php — shared portal shell: fixed left sidebar + layout wiring.
 * Include once per page right where the old <div class="bar"> header used to
 * be. Active item is detected from the script name (or set $nav before the
 * include to override). Self-contained CSS, mobile collapse, print-hidden.
 */
static $__saNavOnce;
if ($__saNavOnce ?? false) { return; }
$__saNavOnce = true;

$__cur    = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$__active = (string) ($nav ?? '');
if ($__active === '') {
    $__active = ['dashboard.php' => 'dashboard', 'clients.php' => 'clients',
                 'billing.php' => 'billing', 'whatsapp.php' => 'whatsapp'][$__cur] ?? '';
}
$__items = [
    ['dashboard', 'Dashboard', '/super-admin/dashboard.php', '<path d="M4 13h6V4H4v9zM14 20h6v-9h-6v9zM4 20h6v-4H4v4zM14 8h6V4h-6v4z"/>'],
    ['clients',   'Clients',   '/super-admin/clients.php',   '<circle cx="9" cy="8" r="3.4"/><path d="M2.6 20c.6-3.4 3.2-5.2 6.4-5.2s5.8 1.8 6.4 5.2"/><path d="M16.5 6.6a3 3 0 010 5.4M18.4 14.9c2 .6 3.2 2.2 3.6 4.4"/>'],
    ['billing',   'Billing',   '/super-admin/billing.php',   '<rect x="3" y="6" width="18" height="13" rx="2.4"/><path d="M3 10.5h18M7 15h5"/>'],
    ['whatsapp',  'WhatsApp',  '/super-admin/whatsapp.php',  '<path d="M3.2 21l1.4-4.3A8.4 8.4 0 1112 20.2c-1.5 0-3-.4-4.2-1.1L3.2 21z"/>'],
];
?>
<aside class="sa-side">
    <a class="sa-brand" href="/super-admin/dashboard.php">
        <span class="sa-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.4-2.9 8.2-7 9.5-4.1-1.3-7-5.1-7-9.5V6l7-3z"/><path d="M9.2 12.1l2 2 3.9-4.2"/></svg></span>
        <span class="sa-name"><b>OPTMS·RX</b><span>SUPER ADMIN</span></span>
    </a>
    <div class="sa-sect">Console</div>
    <nav class="sa-links">
        <?php foreach ($__items as [$k, $label, $href, $svg]): ?>
            <a class="<?= $__active === $k ? 'on' : '' ?>" href="<?= $href ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><?= $svg ?></svg><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sa-foot">
        <span class="sa-session"><i></i>Key session</span>
        <a class="sa-out" href="/super-admin/login.php?logout=1">Sign out</a>
    </div>
</aside>
<style>
    body { padding-left: 232px; }
    .sa-side { position:fixed; top:0; bottom:0; left:0; width:232px; background:#FFFFFF; border-right:1px solid #E4E8F1; display:flex; flex-direction:column; padding:18px 14px 14px; z-index:50; color:#3A445C; font-size:13px; box-shadow:1px 0 10px rgba(16,23,40,.05); }
    .sa-brand { display:flex; gap:10px; align-items:center; text-decoration:none; color:#101728; padding:2px 8px 18px; }
    .sa-mark { width:34px; height:34px; border-radius:10px; background:transparent; color:#4F46E5; display:flex; align-items:center; justify-content:center; flex:none; }
    .sa-mark svg { width:22px; height:22px; }
    .sa-name b { display:block; font-size:14.5px; letter-spacing:.01em; }
    .sa-name > span { font-size:9px; font-weight:800; letter-spacing:.16em; color:#4F46E5; }
    .sa-sect { font-size:9.5px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:#98A1B3; padding:2px 10px 8px; }
    .sa-links { display:flex; flex-direction:column; gap:3px; }
    .sa-links a { display:flex; align-items:center; gap:10px; padding:9px 11px; border-radius:10px; color:#3A445C; text-decoration:none; font-weight:600; transition:background .15s, color .15s; }
    .sa-links a svg { width:16px; height:16px; opacity:.85; flex:none; }
    .sa-links a:hover { background:#F1F5FA; color:#101728; }
    .sa-links a.on { background:#EEF0FE; color:#3B32C4; font-weight:800; box-shadow:inset 3px 0 0 #4F46E5; }
    .sa-foot { margin-top:auto; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:12px 6px 2px; border-top:1px solid #E4E8F1; }
    .sa-session { display:inline-flex; align-items:center; gap:7px; font-size:11px; color:#6B7488; }
    .sa-session i { width:7px; height:7px; border-radius:50%; background:#31C48D; box-shadow:0 0 0 3px rgba(49,196,141,.18); }
    .sa-out { font-size:11.5px; font-weight:700; color:#B3453C; text-decoration:none; padding:5px 9px; border-radius:8px; border:1px solid #F0D5D2; background:#FFF; transition:background .15s; }
    .sa-out:hover { background:#FDEEEC; color:#8F2F27; }
    @media (max-width: 960px) {
        body { padding-left:0 !important; }
        .sa-side { position:sticky; top:0; width:auto; flex-direction:row; align-items:center; padding:9px 12px; gap:10px; }
        .sa-brand { padding:0 6px 0 0; }
        .sa-name { display:none; }
        .sa-sect { display:none; }
        .sa-links { flex-direction:row; overflow:auto; }
        .sa-links a { padding:7px 10px; white-space:nowrap; }
        .sa-foot { margin-top:0; border:0; padding:0 0 0 6px; }
    }
    @media print { .sa-side { display:none !important; } body { padding-left:0 !important; } }
</style>
