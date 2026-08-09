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
$oldMailEnabled = (int)$tickets->mail_enabled;
$tickets->mail_enabled = 0;
try {
	$first = $tickets->createTicket($admin, ['subject' => 'Operations test active ticket', 'body' => 'A deterministic ticket used to validate active queue and bulk operations.', 'category' => 'technical', 'topic' => 'technical', 'priority' => 'normal']);
	$second = $tickets->createTicket($admin, ['subject' => 'Operations test closed ticket', 'body' => 'A deterministic ticket used to validate closed scope and retention preview.', 'category' => 'account', 'topic' => 'account', 'priority' => 'normal']);
	$created = [(int)$first['id'], (int)$second['id']];
	$tickets->updateTicket((int)$second['id'], $admin, ['status' => 'closed']);
	$wire->database->exec('UPDATE `' . Tickets::TABLE_TICKETS . '` SET closed_at=DATE_SUB(NOW(), INTERVAL 800 DAY) WHERE id=' . (int)$second['id']);

	$active = $tickets->queuePage(['q' => 'Operations test'], 1, 20, 'active');
	$closed = $tickets->queuePage(['q' => 'Operations test'], 1, 20, 'closed');
	if ((int)$active['total'] !== 1 || (int)$closed['total'] !== 1) throw new \RuntimeException('Queue scopes did not separate active and closed tickets.');

	$orderPrefix = 'Queue order ' . strtolower(bin2hex(random_bytes(4)));
	$urgentBreached = $tickets->createTicket($admin, ['subject' => $orderPrefix . ' urgent breached', 'body' => 'Validate deterministic queue ordering.', 'category' => 'technical', 'topic' => 'technical', 'priority' => 'urgent']);
	$urgentNear = $tickets->createTicket($admin, ['subject' => $orderPrefix . ' urgent near', 'body' => 'Validate deterministic queue ordering.', 'category' => 'technical', 'topic' => 'technical', 'priority' => 'urgent']);
	$urgentFar = $tickets->createTicket($admin, ['subject' => $orderPrefix . ' urgent far', 'body' => 'Validate deterministic queue ordering.', 'category' => 'technical', 'topic' => 'technical', 'priority' => 'urgent']);
	$urgentClosed = $tickets->createTicket($admin, ['subject' => $orderPrefix . ' urgent closed', 'body' => 'Validate deterministic queue ordering.', 'category' => 'technical', 'topic' => 'technical', 'priority' => 'urgent']);
	$highBreached = $tickets->createTicket($admin, ['subject' => $orderPrefix . ' high breached', 'body' => 'Validate deterministic queue ordering.', 'category' => 'technical', 'topic' => 'technical', 'priority' => 'high']);
	$orderTickets = [$urgentBreached, $urgentNear, $urgentFar, $urgentClosed, $highBreached];
	foreach ($orderTickets as $orderTicket) $created[] = (int)$orderTicket['id'];
	$tickets->updateTicket((int)$urgentBreached['id'], $admin, ['status' => 'waiting_staff']);
	$tickets->updateTicket((int)$urgentFar['id'], $admin, ['status' => 'waiting_customer']);
	$tickets->updateTicket((int)$urgentClosed['id'], $admin, ['status' => 'closed']);
	$tickets->updateTicket((int)$highBreached['id'], $admin, ['status' => 'waiting_staff']);
	$wire->database->exec('UPDATE `' . Tickets::TABLE_TICKETS . '` SET first_response_due_at=DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id=' . (int)$urgentBreached['id']);
	$wire->database->exec('UPDATE `' . Tickets::TABLE_TICKETS . '` SET first_response_due_at=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=' . (int)$urgentNear['id']);
	$wire->database->exec('UPDATE `' . Tickets::TABLE_TICKETS . '` SET first_responded_at=NOW(),resolution_due_at=DATE_ADD(NOW(), INTERVAL 5 HOUR) WHERE id=' . (int)$urgentFar['id']);
	$wire->database->exec('UPDATE `' . Tickets::TABLE_TICKETS . '` SET first_response_due_at=DATE_SUB(NOW(), INTERVAL 3 HOUR) WHERE id=' . (int)$highBreached['id']);
	$ordered = $tickets->queuePage(['q' => $orderPrefix], 1, 20, 'all');
	$orderedIds = array_map('intval', array_column($ordered['items'], 'id'));
	$expectedIds = [(int)$urgentBreached['id'], (int)$urgentNear['id'], (int)$urgentFar['id'], (int)$urgentClosed['id'], (int)$highBreached['id']];
	if ($orderedIds !== $expectedIds) throw new \RuntimeException('Queue order must be priority, active state, SLA breach/deadline, status, and recent activity.');

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
	$tickets->mail_enabled = $oldMailEnabled;
	if ($created) {
		$ids = implode(',', array_map('intval', $created));
		foreach ([Tickets::TABLE_ATTACHMENTS, Tickets::TABLE_MESSAGES, Tickets::TABLE_EVENTS] as $table) $wire->database->exec('DELETE FROM `' . $table . '` WHERE ticket_id IN (' . $ids . ')');
		$wire->database->exec('DELETE FROM `' . Tickets::TABLE_LINKS . '` WHERE ticket_id IN (' . $ids . ') OR related_ticket_id IN (' . $ids . ')');
		$wire->database->exec('DELETE FROM `' . Tickets::TABLE_TICKETS . '` WHERE id IN (' . $ids . ')');
	}
}
