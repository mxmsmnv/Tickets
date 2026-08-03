<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/operations.php /path/to/processwire\n");
	exit(2);
}

require $root . '/index.php';
/** @var Tickets $tickets */
$tickets = $wire->modules->get('Tickets');
$admin = $wire->users->get('id=41');
if (!$admin->id || !$admin->isSuperuser()) foreach ($wire->users->find('include=all') as $candidate) if ($candidate->isSuperuser()) { $admin = $candidate; break; }
if (!$tickets || !$admin->id) throw new \RuntimeException('Tickets or a superuser is unavailable.');
$wire->users->setCurrentUser($admin);

$created = [];
$oldRetentionDays = (int)$tickets->retention_days;
try {
	$first = $tickets->createTicket($admin, ['subject' => 'Operations test active ticket', 'body' => 'A deterministic ticket used to validate active queue and bulk operations.', 'category' => 'technical', 'topic' => 'technical', 'priority' => 'normal']);
	$second = $tickets->createTicket($admin, ['subject' => 'Operations test closed ticket', 'body' => 'A deterministic ticket used to validate closed scope and retention preview.', 'category' => 'account', 'topic' => 'account', 'priority' => 'normal']);
	$created = [(int)$first['id'], (int)$second['id']];
	$tickets->updateTicket((int)$second['id'], $admin, ['status' => 'closed']);
	$wire->database->exec('UPDATE `' . Tickets::TABLE_TICKETS . '` SET closed_at=DATE_SUB(NOW(), INTERVAL 800 DAY) WHERE id=' . (int)$second['id']);

	$active = $tickets->queuePage(['q' => 'Operations test'], 1, 20, 'active');
	$closed = $tickets->queuePage(['q' => 'Operations test'], 1, 20, 'closed');
	if ((int)$active['total'] !== 1 || (int)$closed['total'] !== 1) throw new \RuntimeException('Queue scopes did not separate active and closed tickets.');

	$count = $tickets->bulkUpdateTickets([(int)$first['id']], 'priority', 'urgent', $admin);
	if ($count !== 1 || $tickets->getTicket((int)$first['id'])['priority'] !== 'urgent') throw new \RuntimeException('Bulk priority update failed.');

	$report = $tickets->reportData(['days' => 365]);
	if (!isset($report['summary'], $report['agents'], $report['types'])) throw new \RuntimeException('Report payload is incomplete.');
	$reportAgent = (array)($report['agents'][0] ?? []);
	$reportType = (array)($report['types'][0] ?? []);
	if (!array_key_exists('rating', $reportAgent) || !array_key_exists('rating_count', $reportAgent)) throw new \RuntimeException('Agent rating report fields are missing.');
	if (!array_key_exists('rating', $reportType) || !array_key_exists('rating_count', $reportType)) throw new \RuntimeException('Ticket-type rating report fields are missing.');

	$tickets->retention_days = 730;
	$retention = $tickets->runRetention(true);
	if ((int)$retention['eligible'] < 1 || empty($retention['dry_run'])) throw new \RuntimeException('Retention dry-run did not find the eligible ticket.');

	fwrite(STDOUT, "Tickets operations: OK\n");
} finally {
	$tickets->retention_days = $oldRetentionDays;
	if ($created) {
		$ids = implode(',', array_map('intval', $created));
		foreach ([Tickets::TABLE_ATTACHMENTS, Tickets::TABLE_MESSAGES, Tickets::TABLE_EVENTS] as $table) $wire->database->exec('DELETE FROM `' . $table . '` WHERE ticket_id IN (' . $ids . ')');
		$wire->database->exec('DELETE FROM `' . Tickets::TABLE_LINKS . '` WHERE ticket_id IN (' . $ids . ') OR related_ticket_id IN (' . $ids . ')');
		$wire->database->exec('DELETE FROM `' . Tickets::TABLE_TICKETS . '` WHERE id IN (' . $ids . ')');
	}
}
