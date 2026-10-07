<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/mail-provider.php /path/to/processwire\n");
	exit(2);
}

require $root . '/index.php';
/** @var Tickets $tickets */
$tickets = $wire->modules->get('Tickets');
if (!$tickets) throw new \RuntimeException('Tickets is unavailable.');

$original = (string)$tickets->mail_module;
$originalEvents = (array)$tickets->mail_notification_events;
try {
	$options = $tickets->mailProviderOptions();
	if (!isset($options['']) || trim((string)$options['']) === '') {
		throw new \RuntimeException('The default WireMail option is missing.');
	}

	foreach ($wire->modules->findByPrefix('WireMail') as $name) {
		$name = (string)$name;
		if ($name === '' || $name === 'WireMail') continue;
		if (!isset($options[$name])) throw new \RuntimeException('Installed provider is missing from Tickets settings: ' . $name);
	}

	$tickets->mail_module = 'WireMailProviderThatIsNotInstalled';
	if (!str_contains($tickets->mailProviderLabel(), 'not installed')) {
		throw new \RuntimeException('Unavailable provider state is not visible.');
	}

	$tickets->mail_notification_events = ['customer_reply', 'unknown', 'sla_breach'];
	if ($tickets->mailNotificationEvents() !== ['customer_reply', 'sla_breach']) throw new \RuntimeException('Staff mail events do not fail closed.');

	fwrite(STDOUT, "Tickets mail provider selection: OK\n");
} finally {
	$tickets->mail_module = $original;
	$tickets->mail_notification_events = $originalEvents;
}
