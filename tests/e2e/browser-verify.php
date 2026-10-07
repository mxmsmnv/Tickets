<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/e2e/browser-verify.php /path/to/processwire\n");
	exit(2);
}

chdir($root);
require $root . '/index.php';

/** @var Tickets $tickets */
$tickets = $modules->get('Tickets');
if (!$tickets) throw new \RuntimeException('Tickets is not installed.');

$readLog = static function(string $path): array {
	$records = [];
	foreach (is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
		$start = strpos($line, '{');
		$decoded = $start === false ? null : json_decode(substr($line, $start), true);
		if (is_array($decoded)) $records[] = $decoded;
	}
	return $records;
};

$statement = $database->prepare('SELECT * FROM `' . Tickets::TABLE_TICKETS . '` WHERE subject=:subject ORDER BY id DESC');
$statement->execute([':subject' => 'E2E browser support request']);
$rows = $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
if (count($rows) !== 1) throw new \RuntimeException('The browser did not create exactly one support ticket.');
$ticket = $rows[0];
if ((string)$ticket['customer_email'] !== 'browser@example.test' || (int)$ticket['user_id'] !== 0) throw new \RuntimeException('The browser ticket was not stored as the expected guest request.');

$guest = $users->get((int)$config->guestUserPageID);
$users->setCurrentUser($guest);
$unlocked = $tickets->unlockGuestTicketByEmail((string)$ticket['public_key'], 'browser@example.test');
if ((int)($unlocked['id'] ?? 0) !== (int)$ticket['id']) throw new \RuntimeException('Email recovery did not unlock the browser-created guest ticket.');
$browserToken = $tickets->issueGuestBrowserAccessToken((string)$ticket['public_key'], $guest);
if ($browserToken === '') throw new \RuntimeException('The unlocked guest ticket did not issue a browser access grant.');
$session->remove('tickets_guest_access_' . (int)$ticket['id']);
$restored = $tickets->unlockGuestTicketFromBrowser((string)$ticket['public_key'], $browserToken);
if ((int)($restored['id'] ?? 0) !== (int)$ticket['id']) throw new \RuntimeException('The signed browser grant did not restore guest access.');

$deliveries = array_values(array_filter(
	$readLog($config->paths->logs . 'tickets-e2e-mailbox.txt'),
	static fn(array $row): bool => str_contains((string)($row['subject'] ?? ''), (string)$ticket['public_key'])
));
if (count($deliveries) !== 1 || ($deliveries[0]['to'] ?? []) !== ['browser@example.test']) throw new \RuntimeException('The browser ticket did not produce exactly one customer receipt.');

$hooks = array_values(array_filter(
	$readLog($config->paths->logs . 'tickets-e2e-hooks.txt'),
	static fn(array $row): bool => ($row['event'] ?? '') === 'ticket_created' && (int)($row['ticket_id'] ?? 0) === (int)$ticket['id']
));
if (count($hooks) !== 1) throw new \RuntimeException('The hookable createTicket boundary did not fire for the browser request.');

echo json_encode([
	'status' => 'passed',
	'ticket_id' => (int)$ticket['id'],
	'public_key' => (string)$ticket['public_key'],
	'customer_deliveries' => count($deliveries),
	'create_hooks' => count($hooks),
	'guest_access_restored' => true,
], JSON_UNESCAPED_SLASHES) . "\n";
