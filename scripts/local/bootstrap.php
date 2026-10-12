<?php
// Phase 1 local environment only. Imports the unchanged official demo SQL.
// This script never reads or writes the LATIV collector database.
$root = dirname(__DIR__, 2);
$secretFile = $root . '/.local/local-access.json';
if (file_exists($secretFile)) {
    throw new RuntimeException('Local installation already initialized; refusing to overwrite it.');
}
$access = [
    'database' => 'crmeb_phase1',
    'db_user' => 'crmeb_local',
    'db_password' => bin2hex(random_bytes(16)),
    'root_password' => bin2hex(random_bytes(16)),
    'admin_account' => 'devadmin',
    'admin_password' => bin2hex(random_bytes(12)),
    'site_url' => 'http://127.0.0.1:18080',
];
$db = new mysqli('127.0.0.1', 'root', '', '', 13306);
if ($db->connect_errno) throw new RuntimeException('Local database connection failed.');
$db->set_charset('utf8mb4');
if ($db->query("SHOW DATABASES LIKE 'crmeb_phase1'")->num_rows) {
    throw new RuntimeException('Refusing to overwrite an existing local database.');
}
$db->query('CREATE DATABASE crmeb_phase1 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$db->select_db($access['database']);
$sql = file_get_contents($root . '/crmeb/public/install/crmeb.sql');
// Compatibility for the test engine's utf8mb4 index limit. The upstream file
// remains unchanged; only physical row format in this local import is altered.
$sql = str_replace('ROW_FORMAT=COMPACT', 'ROW_FORMAT=DYNAMIC', $sql);
if (!$db->multi_query($sql)) throw new RuntimeException('Official SQL import failed: ' . $db->error);
do {
    if ($result = $db->store_result()) $result->free();
    if (!$db->more_results()) break;
    if (!$db->next_result()) throw new RuntimeException('Official SQL statement failed: ' . $db->error);
} while (true);
$statement = $db->prepare('UPDATE eb_system_admin SET account=?,pwd=? WHERE id=1');
$passwordHash = password_hash($access['admin_password'], PASSWORD_BCRYPT);
$statement->bind_param('ss', $access['admin_account'], $passwordHash);
$statement->execute();
$statement = $db->prepare('UPDATE eb_system_config SET value=? WHERE menu_name=?');
foreach (['site_url'=>json_encode($access['site_url']), 'queue_open'=>'0'] as $name=>$value) {
    $statement->bind_param('ss', $value, $name);
    $statement->execute();
}
$db->query("CREATE USER 'crmeb_local'@'127.0.0.1' IDENTIFIED BY '".$access['db_password']."'");
$db->query("GRANT ALL PRIVILEGES ON crmeb_phase1.* TO 'crmeb_local'@'127.0.0.1'");
$db->query("ALTER USER 'root'@'localhost' IDENTIFIED BY '".$access['root_password']."'");
$template = file_get_contents($root . '/crmeb/public/install/.env');
$values = [
 '#DB_HOST#'=>'127.0.0.1', '#DB_NAME#'=>$access['database'], '#DB_USER#'=>$access['db_user'],
 '#DB_PWD#'=>$access['db_password'], '#DB_PORT#'=>'13306', '#DB_PREFIX#'=>'eb_', '#DB_CHARSET#'=>'utf8mb4',
 '#CACHE_TYPE#'=>'file', '#CACHE_PREFIX#'=>'phase1:', '#CACHE_TAG_PREFIX#'=>'phase1-tag:',
 '#RB_HOST#'=>'127.0.0.1', '#RB_PORT#'=>'16379', '#RB_PWD#'=>'', '#RB_SELECT#'=>'0', '#QUEUE_NAME#'=>'phase1',
];
file_put_contents($root . '/crmeb/.env', strtr($template, $values));
file_put_contents($secretFile, json_encode($access, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
touch($root . '/crmeb/public/install.lock');
$tables = $db->query('SHOW TABLES');
echo json_encode(['installed'=>true,'tables'=>$tables->num_rows,'php'=>PHP_VERSION,'database'=>$db->server_info]);
$db->close();
