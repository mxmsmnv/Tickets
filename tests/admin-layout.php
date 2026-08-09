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
	'report legend semantics' => str_contains($process, "aria-label=\"' . \$this->_('Chart legend') . '\"") && str_contains($process, 'TicketsChartLegend-value'),
	'report legend layout' => str_contains($css, '.TicketsChartLegend li {') && str_contains($css, 'grid-template-areas: "swatch label" "swatch value";'),
	'report legend mobile layout' => str_contains($css, '.TicketsReportTrend > header {') && str_contains($css, 'flex-direction: column;'),
];

foreach($checks as $label => $ok) {
	if(!$ok) throw new RuntimeException('Tickets admin layout check failed: ' . $label);
}

fwrite(STDOUT, "Tickets admin layout: OK\n");
