<?php declare(strict_types=1);

$root = dirname(__DIR__);
$module = (string)file_get_contents($root . '/Tickets.module.php');
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
	'dark-aware semantic surfaces' => str_contains($css, '--tickets-warning-surface:') && str_contains($css, '--tickets-success-surface:') && str_contains($css, '--tickets-danger-surface:'),
	'dark-aware forms workspace' => str_contains($css, '.TicketsFormCard {') && str_contains($css, '.TicketsFormField { border: 1px solid var(--pw-border-color);') && str_contains($css, 'background: var(--pw-inputs-background); color: var(--pw-text-color);'),
	'no obsolete panel tokens' => !str_contains($css, '--pw-panel-background') && !str_contains($css, '--pw-color-border') && !str_contains($css, '--pw-color-muted') && !str_contains($css, '--pw-accent-color'),
	'separate interface pages' => str_contains($process, '___executeApi()') && str_contains($process, '___executeCli()') && str_contains($process, '___executeEmail()') && str_contains($process, '___executeTelegram()') && str_contains($process, "interfaceNav('api')") && str_contains($process, "interfaceNav('cli')") && str_contains($process, "interfaceNav('email')") && str_contains($process, "interfaceNav('telegram')"),
	'interface status and catalogues' => str_contains($process, 'Current settings') && str_contains($process, 'apiRoutes()') && str_contains($process, 'cliCommands()'),
	'interface responsive layout' => str_contains($css, '.TicketsInterfaceSettings dl {') && str_contains($css, '.TicketsTokenActions'),
	'interface navigation reuses module pills' => str_contains($process, '<nav class="TicketsAdminNavigation"') && str_contains($process, 'uk-subnav uk-subnav-pill TicketsAdmin-nav') && !str_contains($css, '.TicketsInterfaceNav'),
	'telegram reuses interface components' => str_contains($process, "interfaceOverviewCard('telegram'") && str_contains($process, 'TicketsInterfaceSettings') && str_contains($process, 'TicketsInterfaceSafety'),
	'email reuses interface components' => str_contains($process, "interfaceOverviewCard('envelope'") && str_contains($process, 'mailProviderLabel()') && str_contains($process, 'Selected provider'),
	'conversation order moves composer semantically' => str_contains($process, "array_reverse(\$conversationEntries)") && str_contains($process, "\$conversationOrder === 'desc' ? \$composerOut . \$conversationOut : \$conversationOut . \$composerOut"),
	'SLA extensions appear in conversation activity' => str_contains($module, 'public function ticketConversationEvents')
		&& str_contains($module, "event_type=\\'sla_extended\\'")
		&& str_contains($process, 'TicketsConversationEvent')
		&& str_contains($process, "\$this->_('SLA extended')")
		&& str_contains($css, '.TicketsConversationEvent {'),
	'internal note confirms persistence in context' => str_contains($process, "\$redirectFragment = '#ticket-message-'")
		&& str_contains($process, 'id="ticket-message-')
		&& str_contains($css, '.TicketsMessage:target {')
		&& str_contains($module, "'internal_note_message_id'"),
	'configurable responsive sidebar' => str_contains($process, 'data-desktop-sidebar=')
		&& str_contains($process, 'data-tablet-sidebar=')
		&& str_contains($css, '[data-desktop-sidebar="left"]')
		&& str_contains($css, '[data-tablet-sidebar="left"]'),
	'mobile conversation remains first' => str_contains($css, '@media (max-width: 767px)')
		&& str_contains($css, '.TicketsViewMain > .TicketsConversation { order: 1; }')
		&& str_contains($css, '.TicketsViewMain > .TicketsReplyComposer { order: 2; }'),
	'subject form has isolated persistence action' => str_contains($process, "elseif (\$action === 'update_subject')")
		&& str_contains($process, "'subject' => (string)\$this->wire('input')->post('subject')")
		&& str_contains($process, 'name="ticket_action" value="update_subject"')
		&& !preg_match('/<form class="TicketsSubjectForm"[^>]*>.*?name="status"/s', $process),
	'customer geography and timezone context' => str_contains($module, 'public function customerContext(array $ticket)')
		&& str_contains($process, "\$tickets->customerContext(\$ticket)")
		&& str_contains($process, "\$this->_('Geography')")
		&& str_contains($process, "customerContext['different_timezone']"),
	'attachment selection feedback' => str_contains($process, 'data-ticket-attachment-selection')
		&& str_contains($process, 'data-ticket-attachment-clear')
		&& str_contains($javascript, 'syncAttachmentSelection')
		&& str_contains($javascript, 'URL.createObjectURL(file)')
		&& str_contains($css, '.TicketsReplyAttachmentSelection'),
	'TinyMCE follows admin theme' => str_contains($javascript, 'InputfieldTinyMCE.onConfig(configureTicketsTinyMceTheme)')
		&& str_contains($javascript, "settings.skin = dark ? 'oxide-dark' : 'oxide'")
		&& str_contains($javascript, "settings.content_css = dark ? 'dark' : 'default'")
		&& str_contains($javascript, 'InputfieldTinyMCE.resetEditors(editors)')
		&& str_contains($css, '.TicketsTemplateTinyMCE .tox-tinymce')
		&& str_contains($process, "urlSegment1 === 'templates'")
		&& strpos($process, "get('InputfieldTinyMCE')") < strpos($process, "assets/tickets-admin.js"),
	'TinyMCE uses compact toolbar' => str_contains($process, "'menubar' => false")
		&& !str_contains($process, "['toolbar', 'menubar', 'statusbar'"),
	'dashboard has operational title and actions' => str_contains($process, "headline(\$this->_('Support dashboard'))")
		&& str_contains($process, 'TicketsQueueHeader-actions')
		&& str_contains($process, "\$this->_('Manage queue')")
		&& str_contains($process, "\$this->_n('%d ticket', '%d tickets'")
		&& str_contains($css, '.TicketsQueueHeader {')
		&& str_contains($css, 'margin-bottom: var(--tickets-space-sm);'),
	'dashboard health reflects live SLA deadlines' => str_contains($module, 'first_response_due_at<NOW()')
		&& str_contains($module, 'resolution_due_at<NOW()')
		&& str_contains($process, 'data-state="\' . $healthState . \'"')
		&& str_contains($css, '.TicketsDashboardHealth[data-state="danger"]'),
	'related tickets use bounded selectors' => str_contains($module, 'ticketSelectionOptions')
		&& str_contains($module, 'min(200, $limit)')
		&& str_contains($process, 'ticketChoiceSelect')
		&& str_contains($process, 'TicketsMergePanel')
		&& !str_contains($process, 'type="number" min="1" name="related_ticket_id"'),
];

foreach($checks as $label => $ok) {
	if(!$ok) throw new RuntimeException('Tickets admin layout check failed: ' . $label);
}

fwrite(STDOUT, "Tickets admin layout: OK\n");
