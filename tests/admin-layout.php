<?php declare(strict_types=1);

$root = dirname(__DIR__);
$process = (string)file_get_contents($root . '/ProcessTickets.module.php');
$css = (string)file_get_contents($root . '/assets/tickets-admin.css');
$javascript = (string)file_get_contents($root . '/assets/tickets-admin.js');

$checks = [
	'queue variants' => str_contains($process, 'TicketsQueueTable--dashboard') && str_contains($process, 'TicketsQueueTable--workspace'),
	'workspace priority width' => str_contains($css, '.TicketsQueueTable--workspace th:nth-child(5) { width: 7rem; }'),
	'workspace SLA width' => str_contains($css, '.TicketsQueueTable--workspace th:nth-child(6) { width: 10rem; }'),
	'dashboard mobile scope' => str_contains($css, '.TicketsQueueTable--dashboard table,') && str_contains($css, '.TicketsQueueTable--dashboard tr {'),
	'no shared fixed columns' => !str_contains($css, '.TicketsQueueTable th:nth-child('),
	'report legend semantics' => str_contains($process, "aria-label=\"' . \$this->_('Chart legend') . '\"") && str_contains($process, 'TicketsChartLegend-value'),
	'report legend layout' => str_contains($css, '.TicketsChartLegend li {') && str_contains($css, 'grid-template-areas: "swatch label" "swatch value";'),
	'report legend mobile layout' => str_contains($css, '.TicketsReportTrend > header {') && str_contains($css, 'flex-direction: column;'),
	'separate interface pages' => str_contains($process, '___executeApi()') && str_contains($process, '___executeCli()') && str_contains($process, '___executeEmail()') && str_contains($process, '___executeTelegram()') && str_contains($process, "interfaceNav('api')") && str_contains($process, "interfaceNav('cli')") && str_contains($process, "interfaceNav('email')") && str_contains($process, "interfaceNav('telegram')"),
	'interface status and catalogues' => str_contains($process, 'Current settings') && str_contains($process, 'apiRoutes()') && str_contains($process, 'cliCommands()'),
	'interface responsive layout' => str_contains($css, '.TicketsInterfaceSettings dl {') && str_contains($css, '.TicketsTokenActions'),
	'interface navigation reuses module pills' => str_contains($process, '<nav class="TicketsAdminNavigation"') && str_contains($process, 'uk-subnav uk-subnav-pill TicketsAdmin-nav') && !str_contains($css, '.TicketsInterfaceNav'),
	'telegram reuses interface components' => str_contains($process, "interfaceOverviewCard('telegram'") && str_contains($process, 'TicketsInterfaceSettings') && str_contains($process, 'TicketsInterfaceSafety'),
	'email reuses interface components' => str_contains($process, "interfaceOverviewCard('envelope'") && str_contains($process, 'mailProviderLabel()') && str_contains($process, 'Selected provider'),
	'conversation order moves composer semantically' => str_contains($process, "array_reverse(\$messages)") && str_contains($process, "\$conversationOrder === 'desc' ? \$composerOut . \$conversationOut : \$conversationOut . \$composerOut"),
	'attachment selection feedback' => str_contains($process, 'data-ticket-attachment-selection')
		&& str_contains($process, 'data-ticket-attachment-clear')
		&& str_contains($javascript, 'syncAttachmentSelection')
		&& str_contains($javascript, 'URL.createObjectURL(file)')
		&& str_contains($css, '.TicketsReplyAttachmentSelection'),
];

foreach($checks as $label => $ok) {
	if(!$ok) throw new RuntimeException('Tickets admin layout check failed: ' . $label);
}

fwrite(STDOUT, "Tickets admin layout: OK\n");
