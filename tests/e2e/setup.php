<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/e2e/setup.php /path/to/processwire\n");
	exit(2);
}

chdir($root);
require $root . '/index.php';

$bootstrapAdmin = $users->get((int)$config->superUserPageID);
if (!$bootstrapAdmin->id) throw new \RuntimeException('Could not load the ProcessWire superuser');
$users->setCurrentUser($bootstrapAdmin);

$modules->refresh();
foreach (['TeleWire', 'Mailbox', 'Tickets', 'TicketsE2EHarness'] as $name) {
	if (!$modules->isInstalled($name)) $modules->install($name);
	if (!$modules->isInstalled($name)) throw new \RuntimeException('Could not install E2E module: ' . $name);
}

$configData = Tickets::getDefaultConfig();
$configData = array_replace($configData, [
	'public_path' => '/support/',
	'support_email' => 'support@example.test',
	'from_email' => 'support@example.test',
	'from_name' => 'E2E Support',
	'mail_enabled' => 1,
	'mail_notification_events' => [],
	'notification_origin' => 'http://127.0.0.1:8766',
	'spam_min_submit_seconds' => 0,
	'mailbox_inbound_enabled' => 1,
	'mailbox_outbound_enabled' => 1,
	'mailbox_account_id' => 1,
	'mailbox_folder' => 'INBOX',
	'mailbox_require_support_recipient' => 1,
	'telegram_notifications_enabled' => 0,
]);
$modules->saveConfig('Tickets', $configData);

$admin = $users->get('e2e-tickets-admin');
if (!$admin->id) {
	$admin = new User();
	$admin->name = 'e2e-tickets-admin';
	$admin->email = 'e2e-admin@example.test';
	$admin->pass = 'Tickets-E2E-only-2026!';
	$admin->addRole($roles->get('superuser'));
	$admin->save();
}

foreach (['tickets-e2e-mailbox.txt', 'tickets-e2e-hooks.txt'] as $name) {
	$path = $config->paths->logs . $name;
	if (is_file($path)) unlink($path);
}

echo json_encode([
	'status' => 'ready',
	'processwire' => ProcessWire::versionMajor . '.' . ProcessWire::versionMinor . '.' . ProcessWire::versionRevision,
	'tickets_version' => Tickets::VERSION,
	'admin_user' => (string)$admin->name,
	'admin_url' => 'http://127.0.0.1:8766' . $config->urls->admin,
	'portal_url' => 'http://127.0.0.1:8766/support/',
], JSON_UNESCAPED_SLASHES) . "\n";
