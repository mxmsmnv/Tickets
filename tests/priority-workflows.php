<?php namespace ProcessWire;

if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1] ?? getenv('PW_ROOT') ?: '';
if ($root === '' || !is_file(rtrim($root, '/') . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/priority-workflows.php /path/to/processwire\n");
	exit(2);
}
chdir($root);
require rtrim($root, '/') . '/index.php';

/** @var Tickets $tickets */
$tickets = wire('modules')->get('Tickets');
$admin = wire('users')->get('roles=superuser');
if (!$tickets || !$admin->id) throw new \RuntimeException('Tickets and a superuser are required.');
wire('users')->setCurrentUser($admin);
$tickets->mail_enabled = 0;
$suffix = strtolower(bin2hex(random_bytes(4)));
$ticketIds = [];
$ruleId = 0;
$macroId = 0;
$check = static function(bool $condition, string $message): void {
	if (!$condition) throw new \RuntimeException($message);
};

try {
	$rule = $tickets->saveRoutingRule([
		'name' => 'Priority workflow ' . $suffix, 'enabled' => 1, 'sort_order' => -999,
		'form_id' => 987654, 'priority' => 'urgent', 'assigned_user_id' => (int)$admin->id,
		'first_response_minutes' => 30, 'resolution_minutes' => 120,
	], $admin);
	$ruleId = (int)$rule['id'];
	$macro = $tickets->saveMacro(['title' => 'Test ' . $suffix, 'body' => 'Reusable answer.', 'enabled' => 1], $admin);
	$macroId = (int)$macro['id'];
	$check($ruleId > 0 && $macroId > 0, 'Automation records were not created.');

	$first = $tickets->createTicket($admin, [
		'subject' => 'Priority workflow ' . $suffix, 'category' => 'technical', 'topic' => 'technical',
		'priority' => 'urgent', 'body' => 'Test the complete priority workflow.', 'form_id' => 987654,
		'customer_email' => 'tickets-' . $suffix . '@example.com',
		'context_type' => 'page', 'context_id' => 123, 'context_url' => '/test-context/',
	]);
	$ticketIds[] = (int)$first['id'];
	$check((int)$first['assigned_user_id'] === (int)$admin->id, 'Routing did not assign the ticket.');
	$check(!empty($first['first_response_due_at']) && !empty($first['resolution_due_at']), 'SLA dates are missing.');

	$tickets->addInternalNote((int)$first['id'], $admin, 'Private diagnostic note.');
	$check(count($tickets->ticketMessages((int)$first['id'])) === 1, 'Internal note leaked into the customer thread.');
	$check(count($tickets->ticketMessages((int)$first['id'], true)) === 2, 'Staff thread does not include internal note.');
	$tickets->addReply((int)$first['id'], $admin, 'Public staff reply.', null, true);
	$first = $tickets->getTicket((int)$first['id']);
	$check(!empty($first['first_responded_at']), 'First response timestamp is missing.');

	$second = $tickets->createTicket($admin, [
		'subject' => 'Related workflow ' . $suffix, 'category' => 'technical', 'topic' => 'technical',
		'priority' => 'normal', 'body' => 'Related ticket with enough detail.',
		'customer_email' => 'tickets-' . $suffix . '@example.com',
	]);
	$ticketIds[] = (int)$second['id'];
	$tickets->linkTicket((int)$first['id'], (int)$second['id'], 'related', $admin);
	$check(count($tickets->ticketLinks((int)$first['id'])) === 1, 'Ticket link was not stored.');

	$tickets->updateTicket((int)$first['id'], $admin, ['status' => 'resolved']);
	$rated = $tickets->rateTicket((int)$first['id'], $admin, 5, 'Resolved clearly.');
	$check((int)$rated['rating'] === 5, 'Customer rating was not stored.');
	$reopened = $tickets->reopenTicket((int)$first['id'], $admin);
	$check($reopened['status'] === 'waiting_staff' && !empty($reopened['reopened_at']), 'Ticket did not reopen.');

	$result = $tickets->runAutomation(true);
	$check(isset($result['sla_breaches'], $result['auto_closed']), 'Automation result is incomplete.');
	echo "Tickets priority workflows: OK\n";
} finally {
	$db = wire('database');
	foreach ($ticketIds as $ticketId) {
		$db->exec('DELETE FROM tickets_links WHERE ticket_id=' . $ticketId . ' OR related_ticket_id=' . $ticketId);
		$db->exec('DELETE FROM tickets_events WHERE ticket_id=' . $ticketId);
		$db->exec('DELETE FROM tickets_attachments WHERE ticket_id=' . $ticketId);
		$db->exec('DELETE FROM tickets_messages WHERE ticket_id=' . $ticketId);
		$db->exec('DELETE FROM tickets_records WHERE id=' . $ticketId);
	}
	if ($ruleId > 0) $db->exec('DELETE FROM tickets_routing_rules WHERE id=' . $ruleId);
	if ($macroId > 0) $db->exec('DELETE FROM tickets_macros WHERE id=' . $macroId);
}
