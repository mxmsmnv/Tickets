<?php declare(strict_types=1);

$root = dirname(__DIR__);
$module = (string)file_get_contents($root . '/Tickets.module.php');
$integration = (string)file_get_contents($root . '/TicketsTelegramIntegration.php');

$checks = [
	'independent trait' => str_contains($module, "require_once __DIR__ . '/TicketsTelegramIntegration.php'") && str_contains($module, 'use TicketsTelegramIntegration;'),
	'opt-in default' => str_contains($module, "'telegram_notifications_enabled' => 0"),
	'capability manifest' => str_contains($module, "'tickets.notifications.telegram'") && str_contains($module, "'telegram' => \$this->telegramIntegrationStatus()['ready']"),
	'new ticket trigger' => str_contains($module, "sendTelegramTicketNotification('new_ticket', \$ticket)"),
	'customer reply trigger' => str_contains($module, "sendTelegramTicketNotification('customer_reply', \$ticket)"),
	'sla trigger' => str_contains($module, "sendTelegramTicketNotification('sla_breach', \$ticket)"),
	'no TeleWire dependency' => !str_contains($module, 'TeleWire') && !str_contains($integration, 'TeleWire'),
	'bounded HTTPS delivery' => str_contains($integration, 'CURLOPT_PROTOCOLS => CURLPROTO_HTTPS') && str_contains($integration, 'CURLOPT_CONNECTTIMEOUT') && str_contains($integration, 'CURLOPT_FOLLOWLOCATION => false'),
	'runtime secret support' => str_contains($integration, 'TICKETS_TELEGRAM_BOT_TOKEN') && str_contains($integration, 'ticketsTelegramBotToken'),
	'credential redaction' => !str_contains($integration, "'token' => \$token") && !str_contains($integration, "'chat_ids' => \$chatIds"),
	'privacy copy' => str_contains($integration, 'Customer email, message text, guest access tokens, custom fields, and attachments are excluded.'),
	'failure isolation' => str_contains($integration, 'catch (\\Throwable $error)') && str_contains($integration, 'return false;'),
];

foreach ($checks as $label => $ok) {
	if (!$ok) throw new RuntimeException('Tickets Telegram wiring check failed: ' . $label);
}

fwrite(STDOUT, "Tickets Telegram wiring: OK\n");
