<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/generic-defaults.php /path/to/processwire\n");
	exit(2);
}

require $root . '/index.php';
/** @var Tickets $tickets */
$tickets = $wire->modules->get('Tickets');
if (!$tickets) throw new \RuntimeException('Tickets is unavailable.');

$runtimeDefaults = Tickets::getDefaultConfig();
$templates = $tickets->mailTemplateDefaults();
$payload = strtolower(json_encode([$runtimeDefaults, $templates], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
foreach (['lqrs', 'lqrs.dev', 'lqrs.com', 'narzan'] as $forbidden) {
	if (str_contains($payload, $forbidden)) throw new \RuntimeException("Project-specific default remains: {$forbidden}");
}
if ((int)($runtimeDefaults['mail_enabled'] ?? 1) !== 0) throw new \RuntimeException('Transactional mail must be opt-in on a fresh installation.');
if ((int)($runtimeDefaults['telegram_notifications_enabled'] ?? 1) !== 0) throw new \RuntimeException('Telegram notifications must be opt-in on a fresh installation.');
if ((string)($runtimeDefaults['admin_conversation_order'] ?? '') !== 'asc') throw new \RuntimeException('Fresh installations must keep chronological admin conversations by default.');
if ((string)($runtimeDefaults['admin_sidebar_desktop'] ?? '') !== 'right') throw new \RuntimeException('Fresh installations must keep the desktop sidebar on the right by default.');
if ((string)($runtimeDefaults['admin_sidebar_tablet'] ?? '') !== 'right') throw new \RuntimeException('Fresh installations must keep the tablet sidebar on the right by default.');
if (!str_contains((string)($templates['ticket_reply_customer']['html_body'] ?? ''), '{{support_name}}')) throw new \RuntimeException('The staff reply template does not use the configurable support name.');

$previousSupportTimezone = (string)$tickets->support_timezone;
$tickets->support_timezone = 'America/New_York';
$foreignContext = $tickets->customerContext([
	'user_id' => 0,
	'customer_city' => 'Paris',
	'customer_region' => 'Île-de-France',
	'customer_country' => 'France',
	'customer_timezone' => 'Europe/Paris',
]);
$sameContext = $tickets->customerContext(['user_id' => 0, 'customer_timezone' => 'America/New_York']);
$tickets->support_timezone = $previousSupportTimezone;
if (($foreignContext['location'] ?? '') !== 'Paris, Île-de-France, France') throw new \RuntimeException('Customer geography is incomplete.');
if (empty($foreignContext['different_timezone'])) throw new \RuntimeException('A different customer timezone must be visible to staff.');
if (!empty($sameContext['different_timezone'])) throw new \RuntimeException('The support timezone must not be presented as a customer time difference.');

fwrite(STDOUT, "Tickets generic defaults: OK\n");
