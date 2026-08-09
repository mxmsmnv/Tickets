<?php

$root = dirname(__DIR__);
$module = file_get_contents($root . '/Tickets.module.php');
$mailbox = file_get_contents($root . '/TicketsMailboxIntegration.php');
$css = file_get_contents($root . '/assets/tickets-config.css');
$js = file_get_contents($root . '/assets/tickets-config.js');

$checks = [
	'config assets are versioned' => str_contains($module, "tickets-config.css?v=' . self::VERSION") && str_contains($module, "tickets-config.js?v=' . self::VERSION"),
	'overview is data driven' => str_contains($module, 'renderConfigOverview()') && str_contains($module, 'TicketsConfigStatusGrid'),
	'sections have stable anchors' => str_contains($module, "'tickets_config_' . \$key") && str_contains($mailbox, "tickets_config_mailbox"),
	'navigation targets ProcessWire fieldset ids' => str_contains($module, '#Inputfield_tickets_config_') && str_contains($js, '#Inputfield_tickets_config_') && !str_contains($js, '#wrap_tickets_config_'),
	'advanced sections use progressive disclosure' => str_contains($module, 'Inputfield::collapsedYes'),
	'overview is responsive' => str_contains($css, '@media (max-width: 640px)') && str_contains($css, 'grid-template-columns: 1fr'),
	'navigation opens collapsed sections' => str_contains($js, "InputfieldStateCollapsed") && str_contains($js, 'scrollIntoView'),
	'workspace sidebar controls' => str_contains($module, "admin_sidebar_desktop")
		&& str_contains($module, "admin_sidebar_tablet")
		&& str_contains($module, "Desktop sidebar")
		&& str_contains($module, "Tablet sidebar"),
];

foreach ($checks as $label => $ok) {
	if (!$ok) throw new RuntimeException('Failed: ' . $label);
}

echo "Tickets config UI: OK\n";
