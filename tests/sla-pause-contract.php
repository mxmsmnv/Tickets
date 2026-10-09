<?php declare(strict_types=1);

$root = dirname(__DIR__);
$module = (string)file_get_contents($root . '/Tickets.module.php');
$mailbox = (string)file_get_contents($root . '/TicketsMailboxIntegration.php');
$process = (string)file_get_contents($root . '/ProcessTickets.module.php');

$checks = [
	'version is 1.1.2' => str_contains($module, 'public const VERSION = 112;'),
	'pause timestamp is migrated' => str_contains($module, "ensureColumn(self::TABLE_TICKETS, 'resolution_paused_at'")
		&& str_contains($module, "SET resolution_paused_at=updated_at WHERE status=\\'waiting_customer\\'"),
	'waiting customer is excluded from breach automation' => str_contains($module, "WHERE status IN (\\'open\\',\\'waiting_staff\\') AND sla_breached_at IS NULL"),
	'waiting customer is excluded from dashboard breaches' => str_contains($module, "SUM(CASE WHEN status IN (\\'open\\',\\'waiting_staff\\')"),
	'waiting customer is excluded from queue deadline ordering' => substr_count($module, "t.status IN (\\'open\\',\\'waiting_staff\\')") >= 2,
	'waiting customer can auto-close' => str_contains($module, "status IN (\\'resolved\\',\\'waiting_customer\\') AND auto_close_at IS NOT NULL"),
	'web replies use shared SLA transitions' => str_contains($module, '$this->staffReplySlaTransition($ticket, $now)')
		&& substr_count($module, '$this->customerReplySlaTransition($ticket, $now)') >= 3,
	'Resend update records reopening before status' => str_contains($module, "SET reopened_at=:reopened_at,status=\\'waiting_staff\\'"),
	'Mailbox update records reopening before status' => str_contains($mailbox, "SET reopened_at=:reopened_at,status=\\'waiting_staff\\'"),
	'legacy assignment-order CASE is gone' => !str_contains($module . $mailbox, "reopened_at=CASE WHEN status IN"),
	'paused SLA is exposed in the workspace' => str_contains($process, "data-state=\"paused\"")
		&& str_contains($process, "Paused while waiting for customer"),
];

foreach ($checks as $label => $ok) {
	if (!$ok) throw new RuntimeException('Tickets SLA pause contract failed: ' . $label);
}

echo "Tickets SLA pause contract checks passed.\n";
