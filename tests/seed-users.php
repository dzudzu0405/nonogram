<?php
// Local testing only: php tests/seed-users.php
// Creates (or resets) the accounts listed in tests/dev-accounts.json.
if (PHP_SAPI !== 'cli') exit;
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
require __DIR__ . '/../lib/bootstrap.php';

if (db_driver() !== 'sqlite') exit("Refusing to seed test accounts into a MySQL database. Use the local SQLite config.\n");

$sql = file_get_contents(APP_ROOT . '/install/schema.sqlite.sql');
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) db()->exec($stmt);

migrate(true);
$data = json_decode(file_get_contents(__DIR__ . '/dev-accounts.json'), true);
foreach ($data['accounts'] as $a) {
    $id = qv('SELECT id FROM users WHERE email = ?', [$a['email']]);
    if ($id) q('UPDATE users SET password_hash = ?, role = ? WHERE id = ?', [password_hash($a['password'], PASSWORD_DEFAULT), $a['role'], $id]);
    else $id = create_user($a['email'], explode('@', $a['email'])[0], $a['password'], $a['role']);
    foreach ($a['products'] as $p) grant_product((int) $id, $p, 'admin');
}
echo 'Seeded ' . count($data['accounts']) . " test accounts\n";
