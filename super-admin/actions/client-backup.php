<?php
/** Download a tenant database backup.
 *  Preferred: mysqldump on the host. Fallback: honest refusal with next step —
 *  never ship a half-empty "backup" that isn't restorable. */
require __DIR__ . '/_lib.php';

$id     = (int) ($_GET['client_id'] ?? 0);
$client = findClient($id);
if (!$client) { flashErr('Client not found.'); back(); }

$dbName = (string) ($client['db_name'] ?? '');
$name   = (string) ($client['name'] ?? '');
if ($dbName === '') { flashErr('This client has no database on record.'); back(); }

$mysqldump = null;
foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', 'mysqldump'] as $bin) {
    $out = [];
    exec(escapeshellarg($bin) . ' --version 2>&1', $out, $code);
    if ($code === 0) { $mysqldump = $bin; break; }
}

if ($mysqldump === null) {
    flashErr('mysqldump is not available on this server — run it from the host shell instead: mysqldump ' . $dbName . ' > ' . $dbName . '.sql');
    back();
}

// Read credentials the same way the app does.
$user = env('DB_USER', getenv('DB_USER') ?: '');
$pass = env('DB_PASS', getenv('DB_PASS') ?: '');
$host = env('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');

$file = sprintf('%s_%s.sql', $dbName, date('Ymd_His'));
$tmp  = tempnam(sys_get_temp_dir(), 'bkp');

$cmd = sprintf(
    '%s --single-transaction --quick -h %s -u %s %s %s --result-file=%s 2>&1',
    escapeshellarg($mysqldump),
    escapeshellarg($host),
    escapeshellarg($user),
    $pass !== '' ? '--password=' . escapeshellarg($pass) : '',
    escapeshellarg($dbName),
    escapeshellarg($tmp)
);
exec($cmd, $out, $code);

if ($code !== 0 || !file_exists($tmp) || filesize($tmp) < 200) {
    @unlink($tmp);
    flashErr('Backup command failed — check DB credentials in config. No file was produced.');
    back();
}

audit('BACKUP_DOWNLOAD', $id, $name . ' → ' . $file);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
