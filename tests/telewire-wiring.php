<?php declare(strict_types=1);

$root = dirname(__DIR__);
$module = (string)file_get_contents($root . '/Tickets.module.php');
$integration = (string)file_get_contents($root . '/TicketsTeleWireIntegration.php');

$checks = [
	'optional trait' => str_contains($module, "require_once __DIR__ . '/TicketsTeleWireIntegration.php'") && str_contains($module, 'use TicketsTeleWireIntegration;'),
	'opt-in default' => str_contains($module, "'telewire_notifications_enabled' => 0"),
	'new ticket trigger' => str_contains($module, "sendTeleWireTicketNotification('new_ticket', \$ticket)"),
	'customer reply trigger' => str_contains($module, "sendTeleWireTicketNotification('customer_reply', \$ticket)"),
	'sla trigger' => str_contains($module, "sendTeleWireTicketNotification('sla_breach', \$ticket)"),
	'public TeleWire API only' => str_contains($integration, "->send(\$this->buildTeleWireNotification") && !str_contains($integration, 'TelegramAPI'),
	'no credential storage' => !str_contains($module, 'telewire_bot') && !str_contains($module, 'telewire_chat') && !str_contains($integration, "'botToken' =>") && !str_contains($integration, "'chatIds' =>"),
	'public readiness API' => str_contains($integration, "method_exists(\$telewire, 'isConfigured')") && str_contains($integration, '$telewire->isConfigured()'),
	'privacy copy' => str_contains($integration, 'Customer email, message text, guest access tokens, custom fields, and attachments are excluded.'),
	'failure isolation' => str_contains($integration, 'catch (\\Throwable $error)') && str_contains($integration, 'return false;'),
];

foreach ($checks as $label => $ok) {
	if (!$ok) throw new RuntimeException('Tickets TeleWire wiring check failed: ' . $label);
}

fwrite(STDOUT, "Tickets TeleWire wiring: OK\n");
