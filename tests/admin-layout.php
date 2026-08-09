<?php declare(strict_types=1);

$root = dirname(__DIR__);
$process = (string)file_get_contents($root . '/ProcessTickets.module.php');
$css = (string)file_get_contents($root . '/assets/tickets-admin.css');

$checks = [
	'queue variants' => str_contains($process, 'TicketsQueueTable--dashboard') && str_contains($process, 'TicketsQueueTable--workspace'),
	'workspace priority width' => str_contains($css, '.TicketsQueueTable--workspace th:nth-child(5) { width: 7rem; }'),
	'workspace SLA width' => str_contains($css, '.TicketsQueueTable--workspace th:nth-child(6) { width: 10rem; }'),
	'dashboard mobile scope' => str_contains($css, '.TicketsQueueTable--dashboard table,') && str_contains($css, '.TicketsQueueTable--dashboard tr {'),
	'no shared fixed columns' => !str_contains($css, '.TicketsQueueTable th:nth-child('),
];

foreach($checks as $label => $ok) {
	if(!$ok) throw new RuntimeException('Tickets admin layout check failed: ' . $label);
}

fwrite(STDOUT, "Tickets admin layout: OK\n");
