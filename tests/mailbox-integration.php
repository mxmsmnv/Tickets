<?php

declare(strict_types=1);

namespace ProcessWire;

require_once dirname(__DIR__) . '/TicketsMailboxIntegration.php';

final class TicketsMailboxIntegrationHarness {
	use TicketsMailboxIntegration;
}

$harness = new TicketsMailboxIntegrationHarness();
$keyParser = new \ReflectionMethod($harness, 'ticketKeyFromText');
foreach ([
	['[Ticket A1B2C3D4E5F6] Login problem', '', 'A1B2C3D4E5F6'],
	['Re: Ticket #ABCDEF123456', '', 'ABCDEF123456'],
	['Hello', 'Support <ticket+123456ABCDEF@example.com>', '123456ABCDEF'],
	['Unrelated ABCDEF123456', '', ''],
] as [$subject, $recipient, $expected]) {
	if ($keyParser->invoke($harness, $subject, $recipient) !== $expected) throw new \RuntimeException('Mailbox ticket-key recognition failed.');
}

$addressParser = new \ReflectionMethod($harness, 'mailboxAddresses');
$addresses = $addressParser->invoke($harness, 'Person <Customer@Example.com>, support@example.org');
if ($addresses !== ['customer@example.com', 'support@example.org']) throw new \RuntimeException('Mailbox address recognition failed.');

$ticketsSource = (string)file_get_contents(dirname(__DIR__) . '/Tickets.module.php');
$integrationSource = (string)file_get_contents(dirname(__DIR__) . '/TicketsMailboxIntegration.php');
$bridgeSource = (string)file_get_contents(dirname(__DIR__) . '/TicketsMailboxBridge.module.php');
foreach (['mailbox_inbound_enabled' => "'mailbox_inbound_enabled' => 0", 'mailbox_outbound_enabled' => "'mailbox_outbound_enabled' => 0"] as $name => $needle) {
	if (!str_contains($ticketsSource, $needle)) throw new \RuntimeException($name . ' must remain opt-in.');
}
foreach (['getAgentMessage', 'hash_equals', 'claimMailboxSource', 'mailbox_uid', 'mailbox_message_id', 'recipient_mismatch', 'ticket_sender_mismatch', 'sendNotificationThroughMailbox', 'importMailboxInbox'] as $needle) {
	if (!str_contains($ticketsSource . $integrationSource, $needle)) throw new \RuntimeException('Mailbox integration safeguard is missing: ' . $needle);
}
foreach (['registerMailboxIntegrationHook', 'mailbox_inbound_enabled'] as $needle) {
	if (!str_contains($bridgeSource, $needle)) throw new \RuntimeException('Mailbox bridge boundary is missing: ' . $needle);
}
foreach (['Mailbox::messageIndexed', 'handleMailboxIndexedMessage', 'mailboxIntegrationHookRegistered', 'error_class'] as $needle) if (!str_contains($integrationSource, $needle)) throw new \RuntimeException('Conditional Mailbox autoload boundary is missing: ' . $needle);
if (str_contains($integrationSource, 'getMessage(') || str_contains($integrationSource, "['html']") || str_contains($integrationSource, "['raw']")) throw new \RuntimeException('Mailbox integration crossed the agent-safe DTO boundary.');
$cliSource = (string)file_get_contents(dirname(__DIR__) . '/bin/tickets');
foreach (['mailbox-import', '--execute', '--limit=25', 'importMailboxInbox'] as $needle) if (!str_contains($cliSource, $needle)) throw new \RuntimeException('Mailbox import CLI safeguard is missing: ' . $needle);

fwrite(STDOUT, "Tickets optional Mailbox integration tests passed.\n");
