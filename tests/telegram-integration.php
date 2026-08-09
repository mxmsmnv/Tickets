<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/telegram-integration.php /path/to/processwire\n");
	exit(2);
}

require $root . '/index.php';
/** @var Tickets $tickets */
$tickets = $wire->modules->get('Tickets');
if (!$tickets) throw new \RuntimeException('Tickets is unavailable.');

$defaults = Tickets::getDefaultConfig();
if ((int)($defaults['telegram_notifications_enabled'] ?? 1) !== 0) throw new \RuntimeException('Telegram must be disabled by default.');
if (($defaults['telegram_notification_events'] ?? []) !== ['new_ticket', 'customer_reply', 'sla_breach']) throw new \RuntimeException('Telegram default event catalogue changed.');
if (($defaults['telegram_bot_token'] ?? 'unexpected') !== '' || ($defaults['telegram_chat_ids'] ?? 'unexpected') !== '') throw new \RuntimeException('Telegram credentials must be empty by default.');

$status = $tickets->telegramIntegrationStatus();
foreach (['integration_installed', 'integration_compatible', 'configured', 'enabled', 'ready', 'recipient_count', 'credential_source', 'events'] as $key) {
	if (!array_key_exists($key, $status)) throw new \RuntimeException('Telegram status is missing: ' . $key);
}
if (empty($status['integration_installed']) || empty($status['integration_compatible'])) throw new \RuntimeException('TeleWire 1.0.2 integration is unavailable.');

$oldOrigin = (string)$tickets->notification_origin;
$oldEvents = (array)$tickets->telegram_notification_events;
$oldEnabled = (int)$tickets->telegram_notifications_enabled;
$oldToken = (string)$tickets->telegram_bot_token;
$oldChatIds = (string)$tickets->telegram_chat_ids;
try {
	$tickets->notification_origin = 'https://support.example.test';
	$tickets->telegram_notification_events = ['new_ticket', 'unknown', 'sla_breach'];
	if ($tickets->telegramNotificationEvents() !== ['new_ticket', 'sla_breach']) throw new \RuntimeException('Telegram events do not fail closed.');
	$tickets->telegram_notifications_enabled = 1;
	$tickets->telegram_bot_token = '123456:abcdefghijklmnopqrstuvwxyz_ABCDE';
	$tickets->telegram_chat_ids = "-1001234567890\ninvalid recipient\n@support_admins";
	$configured = $tickets->telegramIntegrationStatus();
	if (empty($configured['ready']) || (int)$configured['recipient_count'] !== 2) throw new \RuntimeException('Telegram readiness or recipient validation failed.');

	$builder = new \ReflectionMethod($tickets, 'buildTelegramNotification');
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
	if (!str_contains($message, 'ABCD1234') || !str_contains($message, 'https://support.example.test/')) throw new \RuntimeException('Telegram message lacks ticket identity or admin URL.');
	if (!str_contains($message, '&lt;checkout&gt; &amp; refund')) throw new \RuntimeException('Telegram HTML was not escaped.');
	foreach (['private@example.test', '"secret"', 'guest_access_hash'] as $forbidden) {
		if (str_contains($message, $forbidden)) throw new \RuntimeException('Telegram message exposed protected data: ' . $forbidden);
	}
} finally {
	$tickets->notification_origin = $oldOrigin;
	$tickets->telegram_notification_events = $oldEvents;
	$tickets->telegram_notifications_enabled = $oldEnabled;
	$tickets->telegram_bot_token = $oldToken;
	$tickets->telegram_chat_ids = $oldChatIds;
}

fwrite(STDOUT, "Tickets Telegram integration: OK\n");
