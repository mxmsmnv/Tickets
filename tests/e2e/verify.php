<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/e2e/verify.php /path/to/processwire\n");
	exit(2);
}

chdir($root);
require $root . '/index.php';

/** @var Tickets $tickets */
$tickets = $modules->get('Tickets');
if (!$tickets || Tickets::VERSION !== 112) throw new \RuntimeException('Tickets 1.1.2 is not installed.');
if ($tickets->mailNotificationEvents() !== []) throw new \RuntimeException('Staff email events were not disabled for the E2E scenario.');
$mailboxStatus = $tickets->mailboxIntegrationStatus();
if (empty($mailboxStatus['inbound_ready']) || empty($mailboxStatus['hook_ready']) || empty($mailboxStatus['outbound_ready']) || empty($mailboxStatus['attachment_access'])) throw new \RuntimeException('Mailbox fixture is not ready.');

$tables = [Tickets::TABLE_LINKS, Tickets::TABLE_EVENTS, Tickets::TABLE_ATTACHMENTS, Tickets::TABLE_MESSAGES, Tickets::TABLE_MAILBOX, Tickets::TABLE_TICKETS];
foreach ($tables as $table) $database->exec('DELETE FROM `' . $table . '`');
$storage = rtrim((string)$config->paths->assets, '/') . '/tickets/';
if (is_dir($storage)) foreach (glob($storage . '*') ?: [] as $path) if (is_file($path) && basename($path) !== '.htaccess') unlink($path);
foreach (['tickets-e2e-mailbox.txt', 'tickets-e2e-hooks.txt'] as $name) {
	$path = $config->paths->logs . $name;
	if (is_file($path)) unlink($path);
}

$readLog = static function(string $path): array {
	$records = [];
	foreach (is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
		$start = strpos($line, '{');
		$decoded = $start === false ? null : json_decode(substr($line, $start), true);
		if (is_array($decoded)) $records[] = $decoded;
	}
	return $records;
};

$admin = $users->get('e2e-tickets-admin');
if (!$admin->id || !$admin->isSuperuser()) throw new \RuntimeException('E2E administrator is unavailable.');
$users->setCurrentUser($admin);
$created = $tickets->createTicket($admin, [
	'subject' => 'E2E API notification test',
	'body' => 'This E2E API ticket verifies hooks, delivery receipts, and staff email event selection.',
	'category' => 'technical',
	'topic' => 'technical',
	'priority' => 'normal',
]);
if (empty($created['id'])) throw new \RuntimeException('API ticket was not created.');

$mailLog = $config->paths->logs . 'tickets-e2e-mailbox.txt';
$deliveries = $readLog($mailLog);
if (count($deliveries) !== 1 || ($deliveries[0]['to'] ?? []) !== ['e2e-admin@example.test']) throw new \RuntimeException('Disabling staff events did not preserve exactly one customer receipt.');
if (!str_contains((string)($deliveries[0]['body'] ?? ''), "Open your private ticket\nhttp://127.0.0.1:8766/support/")) throw new \RuntimeException('Mailbox plain-text delivery lost the ticket link or paragraph boundary.');

$messages = $tickets->ticketMessages((int)$created['id'], true);
if (empty($messages[0]['delivered_at'])) throw new \RuntimeException('Initial delivery receipt did not follow the customer notification.');

$tickets->addReply((int)$created['id'], $admin, 'This customer-style reply must not emit a disabled staff email.', null, false);
if (count($readLog($mailLog)) !== 1) throw new \RuntimeException('Disabled customer-reply staff email was still delivered.');
$tickets->addReply((int)$created['id'], $admin, 'This staff reply must still notify the customer.', null, true);
if (count($readLog($mailLog)) !== 2) throw new \RuntimeException('Staff-to-customer reply was incorrectly disabled.');

$imported = $tickets->importMailboxMessage(1, 'INBOX', 101, 'tickets-e2e');
if (($imported['action'] ?? '') !== 'ticket_created' || (int)($imported['attachments_imported'] ?? 0) !== 2) throw new \RuntimeException('Mailbox message and attachments were not imported.');
$duplicate = $tickets->importMailboxMessage(1, 'INBOX', 101, 'tickets-e2e');
if (($duplicate['action'] ?? '') !== 'duplicate') throw new \RuntimeException('Mailbox source deduplication failed.');

$importedMessages = $tickets->ticketMessages((int)$imported['ticket_id'], true);
if (count($importedMessages) !== 1 || !str_contains((string)$importedMessages[0]['body'], '[picture, see attachments]')) throw new \RuntimeException('Inline CID reference was not normalized.');
$attachments = (array)($importedMessages[0]['attachments'] ?? []);
if (count($attachments) !== 2) throw new \RuntimeException('Stored attachment count is incorrect.');
$byName = [];
foreach ($attachments as $attachment) {
	$path = $tickets->attachmentPath($attachment);
	if (!is_file($path)) throw new \RuntimeException('Stored attachment file is missing.');
	$byName[(string)$attachment['original_name']] = $attachment + ['path' => $path];
}
if ((string)file_get_contents((string)$byName['details.txt']['path']) !== "E2E attachment details are safe.\n") throw new \RuntimeException('Text attachment bytes changed.');
if ((int)($byName['pixel.png']['width'] ?? 0) !== 1 || (int)($byName['pixel.png']['height'] ?? 0) !== 1 || ($byName['pixel.png']['mime_type'] ?? '') !== 'image/png') throw new \RuntimeException('Image attachment validation metadata is incorrect.');

$hooks = $readLog($config->paths->logs . 'tickets-e2e-hooks.txt');
$ticketHook = array_values(array_filter($hooks, static fn(array $row): bool => ($row['event'] ?? '') === 'ticket_created' && (int)($row['ticket_id'] ?? 0) === (int)$created['id']));
$mailboxHook = array_values(array_filter($hooks, static fn(array $row): bool => ($row['event'] ?? '') === 'mailbox_imported' && (int)($row['attachments_imported'] ?? 0) === 2));
if (!$ticketHook) throw new \RuntimeException('Tickets::createTicket after-hook did not run.');
if (!$mailboxHook) throw new \RuntimeException('Tickets::mailboxMessageImported hook did not include attachment outcome.');

$state = [
	'api_ticket_id' => (int)$created['id'],
	'api_ticket_key' => (string)$created['public_key'],
	'mailbox_ticket_id' => (int)$imported['ticket_id'],
	'mailbox_ticket_key' => (string)$tickets->getTicket((int)$imported['ticket_id'])['public_key'],
	'attachments' => array_keys($byName),
	'deliveries' => count($readLog($mailLog)),
	'hooks' => count($hooks),
];
file_put_contents($config->paths->assets . 'tickets-e2e-state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode(['status' => 'passed'] + $state, JSON_UNESCAPED_SLASHES) . "\n";
