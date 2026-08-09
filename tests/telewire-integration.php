<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/telewire-integration.php /path/to/processwire\n");
	exit(2);
}

require $root . '/index.php';
/** @var Tickets $tickets */
$tickets = $wire->modules->get('Tickets');
if (!$tickets) throw new \RuntimeException('Tickets is unavailable.');

$defaults = Tickets::getDefaultConfig();
if ((int)($defaults['telewire_notifications_enabled'] ?? 1) !== 0) throw new \RuntimeException('TeleWire must be disabled by default.');
if (($defaults['telewire_notification_events'] ?? []) !== ['new_ticket', 'customer_reply', 'sla_breach']) throw new \RuntimeException('TeleWire default event catalogue changed.');

$status = $tickets->telewireIntegrationStatus();
foreach (['installed', 'compatible', 'configured', 'enabled', 'ready', 'events'] as $key) {
	if (!array_key_exists($key, $status)) throw new \RuntimeException('TeleWire status is missing: ' . $key);
}

$oldOrigin = (string)$tickets->notification_origin;
$oldEvents = (array)$tickets->telewire_notification_events;
try {
	$tickets->notification_origin = 'https://support.example.test';
	$tickets->telewire_notification_events = ['new_ticket', 'unknown', 'sla_breach'];
	if ($tickets->telewireNotificationEvents() !== ['new_ticket', 'sla_breach']) throw new \RuntimeException('TeleWire events do not fail closed.');

	$builder = new \ReflectionMethod($tickets, 'buildTeleWireNotification');
	$builder->setAccessible(true);
	$message = (string)$builder->invoke($tickets, 'new_ticket', [
		'id' => 42,
		'public_key' => 'ABCD1234',
		'subject' => 'Broken <checkout> & refund',
		'priority' => 'urgent',
		'status' => 'open',
		'customer_email' => 'private@example.test',
		'custom_data' => '{"secret":"never"}',
	]);
	if (!str_contains($message, 'ABCD1234') || !str_contains($message, 'https://support.example.test/')) throw new \RuntimeException('TeleWire message lacks ticket identity or admin URL.');
	if (!str_contains($message, '&lt;checkout&gt; &amp; refund')) throw new \RuntimeException('TeleWire HTML was not escaped.');
	foreach (['private@example.test', '"secret"', 'guest_access_hash'] as $forbidden) {
		if (str_contains($message, $forbidden)) throw new \RuntimeException('TeleWire message exposed protected data: ' . $forbidden);
	}
} finally {
	$tickets->notification_origin = $oldOrigin;
	$tickets->telewire_notification_events = $oldEvents;
}

fwrite(STDOUT, "Tickets TeleWire integration: OK\n");
