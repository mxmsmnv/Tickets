<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/e2e/cleanup.php /path/to/processwire\n");
	exit(2);
}

chdir($root);
require $root . '/index.php';

$bootstrapAdmin = $users->get((int)$config->superUserPageID);
if (!$bootstrapAdmin->id) throw new \RuntimeException('Could not load the ProcessWire superuser');
$users->setCurrentUser($bootstrapAdmin);

$support = $pages->get('/support/');
if ($support->id && $support->template && $support->template->name === 'tickets') $support->delete(true);

foreach (['TicketsE2EHarness', 'ProcessTickets', 'TextformatterTicketsForms', 'TicketsMailboxBridge', 'Tickets', 'Mailbox', 'TeleWire'] as $name) {
	if ($modules->isInstalled($name)) {
		try { $modules->uninstall($name); } catch (\Throwable $error) {}
	}
}

$template = $templates->get('tickets');
if ($template && $template->id) {
	$fieldgroup = $template->fieldgroup;
	$templates->delete($template);
	if ($fieldgroup && $fieldgroup->id) {
		try { $fieldgroups->delete($fieldgroup); } catch (\Throwable $error) {}
	}
}
foreach (['tickets-manage', 'tickets-admin', 'tickets-api'] as $name) {
	$permission = $permissions->get($name);
	if ($permission->id) $permissions->delete($permission);
}

$driver = (string)$database->getAttribute(\PDO::ATTR_DRIVER_NAME);
if ($driver === 'mysql') {
	$rows = $database->query("SHOW TABLES LIKE 'tickets\\_%'")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
	foreach ($rows as $table) if (preg_match('/^tickets_[a-z0-9_]+$/', (string)$table)) $database->exec('DROP TABLE `' . $table . '`');
}

$testUser = $users->get('e2e-tickets-admin');
if ($testUser->id) $users->delete($testUser);

$storage = rtrim((string)$config->paths->assets, '/') . '/tickets/';
if (is_dir($storage)) {
	foreach (glob($storage . '*') ?: [] as $path) if (is_file($path)) unlink($path);
	@rmdir($storage);
}
foreach (['tickets-e2e-mailbox.txt', 'tickets-e2e-hooks.txt'] as $name) {
	$path = $config->paths->logs . $name;
	if (is_file($path)) unlink($path);
}
$state = $config->paths->assets . 'tickets-e2e-state.json';
if (is_file($state)) unlink($state);

$modules->refresh();
echo json_encode(['status' => 'clean', 'tickets_installed' => $modules->isInstalled('Tickets')], JSON_UNESCAPED_SLASHES) . "\n";
