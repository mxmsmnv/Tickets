<?php namespace ProcessWire;

/** Admin queue and conversation workspace for Tickets. */
class ProcessTickets extends Process {
	public function init(): void {
		parent::init();
		$assetVersion = Tickets::VERSION . '-' . max(
			(int)@filemtime($this->wire('config')->paths->Tickets . 'assets/tickets-admin.css'),
			(int)@filemtime($this->wire('config')->paths->Tickets . 'assets/tickets-admin.js')
		);
		$this->wire('config')->styles->add($this->wire('config')->urls->Tickets . 'assets/tickets-admin.css?v=' . $assetVersion);
		$this->wire('config')->scripts->add($this->wire('config')->urls->Tickets . 'assets/tickets-admin.js?v=' . $assetVersion);
	}

	public static function getModuleInfo(): array {
		return [
			'title' => 'Tickets',
			'version' => Tickets::VERSION,
			'summary' => 'Review, assign and answer customer support tickets.',
			'author' => 'Maxim Semenov',
			'icon' => 'life-ring',
			'requires' => ['Tickets'],
			'permission' => Tickets::PERMISSION_MANAGE,
			'page' => [
				'name' => 'tickets',
				'parent' => 'setup',
				'title' => 'Tickets',
			],
		];
	}

	private function tickets(): Tickets {
		/** @var Tickets $module */
		$module = $this->wire('modules')->get('Tickets');
		return $module;
	}

	public function ___execute(): string {
		$tickets = $this->tickets();
		$input = $this->wire('input');
		$sanitizer = $this->wire('sanitizer');
		$filters = [
			'status' => $sanitizer->name((string)$input->get('status')),
			'category' => $sanitizer->name((string)$input->get('category')),
			'topic' => $sanitizer->name((string)$input->get('topic')),
			'priority' => $sanitizer->name((string)$input->get('priority')),
			'q' => trim($sanitizer->text((string)$input->get('q'))),
		];
		$filters['page'] = max(1, (int)$input->get('page'));
		$filters['limit'] = 50;
		$filters['scope'] = 'active';
		$queueResult = $tickets->queuePage($filters, $filters['page'], 50, 'active');
		$queue = $queueResult['items'];
		$stats = $tickets->dashboardStats();
		$summary = (array)$stats['summary'];
		$this->headline($this->_('Queue overview'));

		$health = '<div class="TicketsDashboardHealth"><span class="TicketsDashboardHealth-dot"></span><div><strong>' . ((int)($summary['waiting_staff'] ?? 0) > 0 ? $this->_('Customer replies need attention') : $this->_('Queue is under control')) . '</strong><small>' . sprintf($this->_('%d active · %d unassigned'), (int)($summary['active'] ?? 0), (int)($summary['unassigned'] ?? 0)) . '</small></div></div>';
		$out = $this->adminNav('queue')
			. $this->pageIntro($this->_('Support operations'), $this->_('Queue overview'), $this->_('Monitor demand, response health, and the work that needs attention now.'), $health);

		$out .= '<section class="TicketsQueue"><div class="TicketsQueueHeader"><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Work queue') . '</p><h2>' . $this->_('Active tickets') . '</h2><span>' . sprintf($this->_('%d results'), (int)$queueResult['total']) . '</span></div>';
		if (!empty($summary['oldest_active_at'])) $out .= '<p><i class="fa fa-clock-o" aria-hidden="true"></i> ' . $this->_('Oldest active') . ': <strong>' . $this->e($this->age((string)$summary['oldest_active_at'])) . '</strong></p>';
		$out .= '</div><p class="TicketsQueueTools"><a class="uk-button uk-button-default" href="' . $this->e($this->wire('page')->url . 'tickets/') . '"><i class="fa fa-filter uk-margin-small-right"></i>' . $this->_('Open filters and bulk actions') . '</a></p>';
		if (!$queue) {
			$out .= '<div class="TicketsEmpty"><i class="fa fa-search" aria-hidden="true"></i><h3>' . $this->_('No tickets match these filters') . '</h3><p>' . $this->_('Change or reset the filters to return to the full queue.') . '</p></div></section>';
		} else {
			$out .= '<div class="uk-overflow-auto TicketsQueueTable TicketsQueueTable--dashboard"><table class="uk-table uk-table-divider uk-table-hover uk-table-middle"><thead><tr><th>' . $this->_('Priority') . '</th><th>' . $this->_('Status') . '</th><th>' . $this->_('Ticket') . '</th><th>' . $this->_('Activity') . '</th><th><span class="uk-hidden">' . $this->_('Action') . '</span></th></tr></thead><tbody>';
			foreach ($queue as $ticket) {
				$url = $this->wire('page')->url . 'view/?id=' . (int)$ticket['id'];
				$topic = (string)($ticket['topic'] ?? 'general');
				$priorityLabel = (string)($tickets->priorities()[$ticket['priority']] ?? $ticket['priority']);
				$statusColor = in_array($ticket['status'], ['resolved', 'closed'], true) ? 'success' : ($ticket['status'] === 'waiting_staff' ? 'warning' : 'neutral');
				$out .= '<tr data-ticket-row data-ticket-url="' . $this->e($url) . '" data-ticket-label="' . $this->e(sprintf($this->_('Open ticket: %s'), (string)$ticket['subject'])) . '">'
					. '<td class="TicketsQueuePriority"><span class="TicketsPriority" data-priority="' . $this->e($ticket['priority']) . '" aria-label="' . $this->e(sprintf($this->_('Priority: %s'), $priorityLabel)) . '" title="' . $this->e($priorityLabel) . '"><i aria-hidden="true"></i><span class="TicketsPriorityLabel">' . $this->e($priorityLabel) . '</span></span></td>'
					. '<td class="TicketsQueueStatus"><span class="TicketsBadge" data-color="' . $statusColor . '">' . $this->e($tickets->statuses()[$ticket['status']] ?? $ticket['status']) . '</span></td>'
					. '<td class="TicketsQueueTicket"><a class="TicketsQueueSubject" href="' . $this->e($url) . '">' . $this->e($ticket['subject']) . '</a><div class="uk-text-meta">#' . $this->e($ticket['public_key']) . ' · ' . $this->e($tickets->types()[$ticket['category']] ?? $ticket['category']) . ' · ' . $this->e($tickets->topics()[$topic] ?? $topic) . '</div><div class="TicketsQueueCustomer"><strong>' . $this->e($ticket['customer_name']) . '</strong><span>' . $this->e($ticket['customer_email']) . '</span></div></td>'
					. '<td class="TicketsQueueActivity"><div class="TicketsQueueActivityInner"><div><strong>' . $this->e($this->age((string)$ticket['updated_at'])) . '</strong><span>' . $this->e(date('M j, H:i', strtotime((string)$ticket['updated_at']))) . '</span></div>' . $this->queueSlaCell($tickets, $ticket) . '</div></td>'
					. '<td class="TicketsQueueAction"><a class="TicketsQueueOpen" href="' . $this->e($url) . '" aria-label="' . $this->_('Open ticket') . '"><i class="fa fa-arrow-right"></i></a></td></tr>';
			}
			$out .= '</tbody></table></div></section>';
		}

		$metrics = [
			['label' => $this->_('Active tickets'), 'value' => (int)($summary['active'] ?? 0), 'note' => sprintf($this->_('%d waiting for support · %d total'), (int)($summary['waiting_staff'] ?? 0), (int)($summary['total'] ?? 0)), 'icon' => 'inbox'],
			['label' => $this->_('Created in 7 days'), 'value' => (int)($summary['created_7d'] ?? 0), 'note' => sprintf($this->_('%d in 30 days · %d guests'), (int)($summary['created_30d'] ?? 0), (int)($summary['guests'] ?? 0)), 'icon' => 'calendar'],
			['label' => $this->_('First response'), 'value' => $this->duration($summary['avg_first_response_minutes'] ?? null), 'note' => $this->_('Average across answered tickets'), 'icon' => 'reply'],
			['label' => $this->_('Resolution time'), 'value' => $this->duration($summary['avg_resolution_minutes'] ?? null), 'note' => sprintf($this->_('%d resolved in 30 days'), (int)($summary['resolved_30d'] ?? 0)), 'icon' => 'resolved'],
			['label' => $this->_('Urgent'), 'value' => (int)($summary['urgent'] ?? 0), 'note' => sprintf($this->_('%d tickets unassigned'), (int)($summary['unassigned'] ?? 0)), 'icon' => 'alert'],
			['label' => $this->_('Conversation volume'), 'value' => (int)($summary['messages'] ?? 0), 'note' => sprintf($this->_('Private files: %d'), (int)($summary['attachments'] ?? 0)), 'icon' => 'conversation'],
			['label' => $this->_('SLA breaches'), 'value' => (int)($summary['sla_breached'] ?? 0), 'note' => $this->_('Active tickets requiring escalation'), 'icon' => 'timer'],
			['label' => $this->_('Customer rating'), 'value' => !empty($summary['avg_rating']) ? number_format((float)$summary['avg_rating'], 1) . '/5' : $this->_('No data'), 'note' => $this->_('Average resolved-ticket rating'), 'icon' => 'rating'],
		];
		$out .= '<section class="TicketsMetricGrid" aria-label="' . $this->_('Support metrics') . '">';
		foreach ($metrics as $metric) {
			$out .= '<article class="TicketsMetric"><span class="TicketsMetric-icon">' . $this->metricIcon($metric['icon']) . '</span><div><span>' . $this->e($metric['label']) . '</span><strong>' . $this->e($metric['value']) . '</strong><small>' . $this->e($metric['note']) . '</small></div></article>';
		}
		$out .= '</section>';
		$out .= '<nav class="TicketsStatusStrip" aria-label="' . $this->_('Filter queue by status') . '">';
		foreach ($tickets->statuses() as $key => $label) {
			$out .= '<a href="' . $this->e($this->wire('page')->url . 'tickets/?status=' . rawurlencode($key)) . '"><span>' . $this->e($label) . '</span><strong>' . (int)($stats['statuses'][$key] ?? 0) . '</strong></a>';
		}
		$out .= '</nav>';

		$out .= '<section class="TicketsInsightGrid"><article class="TicketsPanel TicketsTrend"><header><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Demand') . '</p><h3>' . $this->_('New tickets · 14 days') . '</h3></div><strong>' . array_sum($stats['trend']) . '</strong></header>' . $this->trendChart((array)$stats['trend']) . '</article>'
			. $this->breakdownPanel($this->_('By type'), (array)$stats['types'], $tickets->types(), 'category')
			. $this->breakdownPanel($this->_('By topic'), (array)$stats['topics'], $tickets->topics(), 'topic') . '</section>';

		return $this->workspace($out);
	}

	public function ___executeTickets(): string {
		$this->headline($this->_('All tickets'));
		$out = $this->adminNav('tickets')
			. $this->pageIntro($this->_('Ticket workspace'), $this->_('All tickets'), $this->_('Search and manage requests ordered by priority, active state, and the nearest SLA deadline.'))
			. $this->ticketListing('all', '', true);
		return $this->workspace($out);
	}

	/** Backwards-compatible route retained for saved admin bookmarks. */
	public function ___executeFilters(): string {
		$query = $this->wire('input')->get->getArray();
		$this->wire('session')->redirect($this->wire('page')->url . 'tickets/' . ($query ? '?' . http_build_query($query) : ''));
		return '';
	}

	/** Closed tickets now live in the unified Tickets workspace. */
	public function ___executeClosed(): string {
		$this->wire('session')->redirect($this->wire('page')->url . 'tickets/?status=closed');
		return '';
	}

	public function ___executeInterfaces(): string {
		$tickets = $this->tickets();
		if (!$tickets->canAdmin($this->wire('user'))) throw new WirePermissionException('You cannot manage Tickets interfaces.');
		$this->headline($this->_('Interfaces'));
		$channels = $tickets->capabilities()['channels'];
		$summary = '<div class="TicketsInterfaceSummary">' . $this->interfaceState('API', (bool)$channels['rest']) . $this->interfaceState('CLI', (bool)$channels['cli']) . '</div>';
		$out = $this->adminNav('interfaces')
			. $this->interfaceNav('overview')
			. $this->pageIntro($this->_('Operational interfaces'), $this->_('Interfaces'), $this->_('Inspect status, authentication, routes, and commands without mixing API and CLI workflows.'), $summary)
			. '<section class="TicketsInterfaceGrid TicketsInterfaceGrid--overview">'
			. $this->interfaceOverviewCard('exchange', 'API ' . Tickets::REST_API_VERSION, $this->_('Versioned JSON API'), $this->_('Review session and Bearer authentication, token status, and every supported route.'), $this->wire('page')->url . 'api/', (bool)$channels['rest'])
			. $this->interfaceOverviewCard('terminal', 'CLI', $this->_('Local command line'), $this->_('Review channel status, safety requirements, and the complete command catalogue.'), $this->wire('page')->url . 'cli/', (bool)$channels['cli'])
			. '</section>';
		return $this->workspace($out);
	}

	public function ___executeApi(): string {
		$tickets = $this->tickets();
		if (!$tickets->canAdmin($this->wire('user'))) throw new WirePermissionException('You cannot manage Tickets API access.');
		if ($this->wire('input')->requestMethod('POST')) $this->handleBearerTokenAction($tickets);
		$this->headline($this->_('Tickets API'));
		$channels = $tickets->capabilities()['channels'];
		$apiBase = rtrim((string)$this->wire('config')->urls->root, '/') . '/tickets-api/' . Tickets::REST_API_VERSION . '/';
		$actor = (int)$tickets->rest_bearer_user_id ? $this->wire('users')->get((int)$tickets->rest_bearer_user_id) : null;
		$actorLabel = $actor instanceof User && $actor->id ? (string)$actor->name : $this->_('Not assigned');
		$tokenOnce = trim((string)$this->wire('session')->get('tickets_bearer_token_once'));
		$this->wire('session')->remove('tickets_bearer_token_once');
		$summary = '<div class="TicketsInterfaceSummary">' . $this->interfaceState('PHP API', (bool)$channels['php_api']) . $this->interfaceState('REST', (bool)$channels['rest']) . $this->interfaceState('Bearer', (bool)$channels['bearer']) . '</div>';
		$out = $this->adminNav('interfaces') . $this->interfaceNav('api')
			. $this->pageIntro($this->_('API ' . Tickets::REST_API_VERSION), $this->_('Tickets API'), $this->_('Use a ProcessWire session with CSRF or an explicitly rotated Bearer token assigned to a permitted actor.'), $summary);
		if ($tokenOnce !== '') $out .= '<section class="TicketsTokenOnce" role="status"><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Copy now') . '</p><h2>' . $this->_('New Bearer token') . '</h2><p>' . $this->_('This token is shown once. Store it in a secret manager; Tickets keeps only its SHA-256 hash.') . '</p></div><code>' . $this->e($tokenOnce) . '</code></section>';
		$out .= '<section class="TicketsInterfaceSettings"><header><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Current settings') . '</p><h2>' . $this->_('API access') . '</h2></div><a class="uk-button uk-button-default" href="' . $this->e($this->interfaceSettingsUrl()) . '">' . $this->_('Module settings') . '</a></header><dl>'
			. '<div><dt>' . $this->_('Version') . '</dt><dd><code>' . Tickets::REST_API_VERSION . '</code></dd></div>'
			. '<div><dt>' . $this->_('PHP facade') . '</dt><dd>' . ((bool)$channels['php_api'] ? $this->_('Enabled') : $this->_('Disabled')) . '</dd></div>'
			. '<div><dt>' . $this->_('REST routes') . '</dt><dd>' . ((bool)$channels['rest'] ? $this->_('Enabled') : $this->_('Disabled')) . '</dd></div>'
			. '<div><dt>' . $this->_('Bearer token') . '</dt><dd>' . ((string)$tickets->rest_bearer_token_hash !== '' ? $this->_('Configured') : $this->_('Not configured')) . '</dd></div>'
			. '<div><dt>' . $this->_('Token actor') . '</dt><dd>' . $this->e($actorLabel) . '</dd></div>'
			. '<div><dt>' . $this->_('Rotated') . '</dt><dd>' . $this->e((string)$tickets->rest_bearer_token_created_at ?: $this->_('Never')) . '</dd></div>'
			. '</dl></section>';
		$out .= $this->renderBearerTokenPanel($tickets);
		$out .= '<section class="TicketsInterfaceTable"><header><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">REST</p><h2>' . $this->_('Routes') . '</h2></div><code>' . $this->e($apiBase) . '</code></header><div class="uk-overflow-auto"><table class="uk-table uk-table-divider uk-table-middle"><thead><tr><th>' . $this->_('Method') . '</th><th>' . $this->_('Route') . '</th><th>' . $this->_('Access') . '</th><th>' . $this->_('Purpose') . '</th></tr></thead><tbody>';
		foreach ($this->apiRoutes() as $route) $out .= '<tr><td><span class="TicketsMethod" data-method="' . strtolower($route[0]) . '">' . $route[0] . '</span></td><td><code>' . $this->e($apiBase . $route[1]) . '</code></td><td>' . $this->e($route[2]) . '</td><td>' . $this->e($route[3]) . '</td></tr>';
		$out .= '</tbody></table></div></section><section class="TicketsInterfaceSafety"><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Security boundary') . '</p><h2>' . $this->_('Two authentication modes, one permission model') . '</h2><p>' . $this->_('Session mutations require tickets-rest CSRF. Bearer credentials are accepted only in the Authorization header, receive no CORS opt-in, are independently rate-limited, and inherit the assigned actor permissions.') . '</p></div></section>';
		return $this->workspace($out);
	}

	public function ___executeCli(): string {
		$tickets = $this->tickets();
		if (!$tickets->canAdmin($this->wire('user'))) throw new WirePermissionException('You cannot inspect Tickets CLI access.');
		$this->headline($this->_('Tickets CLI'));
		$enabled = (bool)$tickets->enable_cli;
		$command = 'php site/modules/Tickets/bin/tickets';
		$summary = '<div class="TicketsInterfaceSummary">' . $this->interfaceState('CLI', $enabled) . '</div>';
		$out = $this->adminNav('interfaces') . $this->interfaceNav('cli')
			. $this->pageIntro($this->_('Local interface'), $this->_('Tickets CLI'), $this->_('Run bounded JSON commands on the ProcessWire host; mutations require an explicit --execute flag.'), $summary)
			. '<section class="TicketsInterfaceSettings"><header><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Current settings') . '</p><h2>' . $this->_('Command-line access') . '</h2></div><a class="uk-button uk-button-default" href="' . $this->e($this->interfaceSettingsUrl()) . '">' . $this->_('Module settings') . '</a></header><dl>'
			. '<div><dt>' . $this->_('CLI') . '</dt><dd>' . ($enabled ? $this->_('Enabled') : $this->_('Disabled')) . '</dd></div>'
			. '<div><dt>' . $this->_('Executable') . '</dt><dd><code>site/modules/Tickets/bin/tickets</code></dd></div>'
			. '<div><dt>' . $this->_('ProcessWire root') . '</dt><dd><code>' . $this->e(rtrim((string)$this->wire('config')->paths->root, '/')) . '</code></dd></div>'
			. '<div><dt>' . $this->_('Output') . '</dt><dd>JSON</dd></div></dl></section>'
			. '<section class="TicketsInterfaceTable"><header><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">CLI</p><h2>' . $this->_('Commands') . '</h2></div><code>' . $this->e($command . ' help') . '</code></header><div class="uk-overflow-auto"><table class="uk-table uk-table-divider uk-table-middle"><thead><tr><th>' . $this->_('Mode') . '</th><th>' . $this->_('Command') . '</th><th>' . $this->_('Purpose') . '</th></tr></thead><tbody>';
		foreach ($this->cliCommands() as $row) $out .= '<tr><td><span class="TicketsCommandMode" data-mode="' . $this->e($row[0]) . '">' . ucfirst($row[0]) . '</span></td><td><code>' . $this->e($command . ' ' . $row[1]) . '</code></td><td>' . $this->e($row[2]) . '</td></tr>';
		$out .= '</tbody></table></div></section><section class="TicketsInterfaceSafety"><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Execution safety') . '</p><h2>' . $this->_('Preview first, mutate explicitly') . '</h2><p>' . $this->_('CLI never accepts a password or API token argument. Ticket writes and real maintenance require --execute; automation and retention expose --dry-run previews.') . '</p></div></section>';
		return $this->workspace($out);
	}

	public function ___executeReports(): string {
		$tickets = $this->tickets();
		if ($this->wire('input')->requestMethod('POST')) {
			try {
				if (!$tickets->canAdmin($this->wire('user'))) throw new WirePermissionException('You cannot run retention.');
				$this->wire('session')->CSRF->validate();
				$action = (string)$this->wire('input')->post('report_action');
				if (in_array($action, ['retention_preview', 'retention_run'], true)) {
					$result = $tickets->runRetention($action === 'retention_preview');
					$this->message(!empty($result['disabled']) ? $this->_('Retention is disabled in module settings.') : sprintf($this->_('%d tickets eligible; %d processed.'), (int)$result['eligible'], (int)$result['processed']));
				}
			} catch (\Throwable $error) { $this->error($error->getMessage()); }
			$this->wire('session')->redirect($this->wire('page')->url . 'reports/');
		}
		$days = max(7, min((int)$this->wire('input')->get('days') ?: 30, 365));
		$report = $tickets->reportData(['days' => $days]);
		if ((string)$this->wire('input')->get('export') === 'csv') {
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="tickets-report-' . date('Y-m-d') . '.csv"');
			echo "Dimension,Name,Total,Completed,SLA breaches,Average resolution minutes,Average first response minutes,Average rating,Ratings\n";
			foreach ($report['agents'] as $row) {
				$user = (int)$row['assigned_user_id'] ? $this->wire('users')->get((int)$row['assigned_user_id']) : null;
				$name = $user && $user->id ? (string)$user->name : $this->_('Unassigned');
				echo 'Agent,"' . str_replace('"', '""', $name) . '",' . (int)$row['total'] . ',' . (int)$row['completed'] . ',' . (int)$row['breached'] . ',' . (int)round((float)$row['resolution_minutes']) . ',' . (int)round((float)$row['first_response_minutes']) . ',' . (!empty($row['rating']) ? number_format((float)$row['rating'], 1, '.', '') : '') . ',' . (int)($row['rating_count'] ?? 0) . "\n";
			}
			foreach ($report['types'] as $row) echo 'Type,"' . str_replace('"', '""', (string)($tickets->types()[$row['category']] ?? $row['category'])) . '",' . (int)$row['total'] . ',' . (int)$row['completed'] . ',' . (int)$row['breached'] . ',,,' . (!empty($row['rating']) ? number_format((float)$row['rating'], 1, '.', '') : '') . ',' . (int)($row['rating_count'] ?? 0) . "\n";
			exit;
		}
		$this->headline($this->_('Reports'));
		$summary = $report['summary'];
		$export = '?days=' . $days . '&export=csv';
		$reportActions = '<div class="TicketsReportActions"><form method="get"><label><span class="uk-form-label">' . $this->_('Period') . '</span><select class="uk-select" name="days" onchange="this.form.submit()"><option value="7"' . ($days === 7 ? ' selected' : '') . '>7 days</option><option value="30"' . ($days === 30 ? ' selected' : '') . '>30 days</option><option value="90"' . ($days === 90 ? ' selected' : '') . '>90 days</option><option value="365"' . ($days === 365 ? ' selected' : '') . '>365 days</option></select></label></form><a class="uk-button uk-button-default" href="' . $this->e($export) . '"><i class="fa fa-download uk-margin-small-right"></i>' . $this->_('Export CSV') . '</a></div>';
		$out = $this->adminNav('reports') . $this->pageIntro($this->_('Reporting'), $this->_('Reports'), sprintf($this->_('Operational results for the last %d days.'), $days), $reportActions);
		$metrics = [[$this->_('Created'), (int)($summary['created'] ?? 0)], [$this->_('Completed'), (int)($summary['completed'] ?? 0)], [$this->_('SLA breaches'), (int)($summary['breached'] ?? 0)], [$this->_('Average rating'), !empty($summary['rating']) ? number_format((float)$summary['rating'], 1) . '/5' : $this->_('No data')], [$this->_('First response'), $this->duration($summary['first_response_minutes'] ?? null)], [$this->_('Resolution time'), $this->duration($summary['resolution_minutes'] ?? null)]];
		$out .= '<section class="TicketsMetricGrid">';
		foreach ($metrics as [$label, $value]) $out .= '<article class="TicketsMetric"><div><span>' . $this->e($label) . '</span><strong>' . $this->e($value) . '</strong></div></article>';
		$out .= '</section><section class="TicketsPanel TicketsReportTrend"><header><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Volume') . '</p><h2>' . $this->_('Created and completed') . '</h2></div><ul class="TicketsChartLegend" aria-label="' . $this->_('Chart legend') . '"><li data-series="created"><span class="TicketsChartLegend-label">' . $this->_('Created') . '</span><strong class="TicketsChartLegend-value">' . (int)($summary['created'] ?? 0) . '</strong></li><li data-series="completed"><span class="TicketsChartLegend-label">' . $this->_('Completed') . '</span><strong class="TicketsChartLegend-value">' . (int)($summary['completed'] ?? 0) . '</strong></li></ul></header>' . $this->reportTrendChart((array)$report['daily']) . '</section>';
		$out .= '<div class="TicketsReportGrid"><section class="uk-card uk-card-default TicketsReportPanel"><h2>' . $this->_('By agent') . '</h2>' . $this->reportTable($report['agents'], 'agent') . '</section><section class="uk-card uk-card-default TicketsReportPanel"><h2>' . $this->_('By ticket type') . '</h2>' . $this->reportTable($report['types'], 'type') . '</section></div>';
		$out .= '<div class="TicketsReportGrid">' . $this->breakdownPanel($this->_('Status mix'), (array)$report['statuses'], $tickets->statuses(), 'status') . $this->breakdownPanel($this->_('Priority mix'), (array)$report['priorities'], $tickets->priorities(), 'priority') . '</div>';
		$out .= '<section class="TicketsPanel TicketsBacklog"><h2>' . $this->_('Active backlog age') . '</h2><div class="TicketsMetricGrid">';
		foreach (['under_24h' => $this->_('Under 24 hours'), 'one_to_three_days' => $this->_('1–3 days'), 'three_to_seven_days' => $this->_('3–7 days'), 'over_seven_days' => $this->_('Over 7 days')] as $key => $label) $out .= '<article class="TicketsMetric"><div><span>' . $this->e($label) . '</span><strong>' . (int)($report['backlog'][$key] ?? 0) . '</strong></div></article>';
		$out .= '</div></section>';
		$last = $report['last_run'];
		$out .= '<section class="TicketsPanel TicketsOpsHealth"><div><h2>' . $this->_('Maintenance health') . '</h2><p>' . ($last ? sprintf($this->_('Last %s run: %s.'), $this->e($last['run_type']), $this->e($last['created_at'])) : $this->_('No maintenance run has been recorded yet. Configure cron for automation and retention.')) . '</p><p>' . ((int)$tickets->retention_days > 0 ? sprintf($this->_('Closed tickets are retained for %d days, then %s in batches of %d.'), (int)$tickets->retention_days, $this->e((string)$tickets->retention_action), (int)$tickets->retention_batch_size) : $this->_('Automatic retention is disabled.')) . '</p></div>';
		if ($tickets->canAdmin($this->wire('user'))) $out .= '<div class="TicketsOpsActions"><form method="post"><input type="hidden" name="report_action" value="retention_preview">' . $this->csrf() . '<button class="uk-button uk-button-default" type="submit">' . $this->_('Preview retention') . '</button></form><form method="post" onsubmit="return confirm(\'' . $this->_('Run the configured retention action now? Permanent deletion cannot be undone.') . '\')"><input type="hidden" name="report_action" value="retention_run">' . $this->csrf() . '<button class="uk-button uk-button-danger" type="submit"' . ((int)$tickets->retention_days === 0 ? ' disabled' : '') . '>' . $this->_('Run retention') . '</button></form></div>';
		$out .= '</section>';
		return $this->workspace($out);
	}

	private function ticketListing(string $scope, string $title, bool $showFilters): string {
		$tickets = $this->tickets();
		$input = $this->wire('input');
		if ($input->requestMethod('POST')) {
			try {
				$this->wire('session')->CSRF->validate();
				[$operation, $value] = array_pad(explode(':', (string)$input->post('bulk_choice'), 2), 2, '');
				$count = $tickets->bulkUpdateTickets((array)$input->post('ticket_ids'), $operation, $value, $this->wire('user'));
				$this->message(sprintf($this->_('%d tickets updated.'), $count));
			} catch (\Throwable $error) { $this->error($error->getMessage()); }
			$this->wire('session')->redirect($this->wire('page')->url . 'tickets/');
		}
		$filters = [
			'status' => $this->wire('sanitizer')->name((string)$input->get('status')),
			'category' => $this->wire('sanitizer')->name((string)$input->get('category')),
			'topic' => $this->wire('sanitizer')->name((string)$input->get('topic')),
			'priority' => $this->wire('sanitizer')->name((string)$input->get('priority')),
			'assigned_user_id' => (string)$input->get('assigned_user_id'),
			'date_from' => $this->wire('sanitizer')->text((string)$input->get('date_from')),
			'date_to' => $this->wire('sanitizer')->text((string)$input->get('date_to')),
			'q' => trim($this->wire('sanitizer')->text((string)$input->get('q'))),
		];
		$page = max(1, (int)$input->get('page'));
		$result = $tickets->queuePage($filters, $page, 50, $scope);
		$out = '<section class="TicketsQueue"><div class="TicketsQueueHeader"><div>' . ($title !== '' ? '<h2>' . $this->e($title) . '</h2>' : '') . '<span>' . sprintf($this->_('%d results'), (int)$result['total']) . '</span></div></div>';
		if ($showFilters) $out .= '<form class="TicketsFilters" method="get">'
			. $this->filterInput('q', $this->_('Search'), 'search', $filters['q'], $this->_('Ticket, customer, or email'))
			. $this->filterSelect('status', $this->_('Status'), $this->_('All statuses'), $tickets->statuses(), $filters['status'])
			. $this->filterSelect('category', $this->_('Type'), $this->_('All types'), $tickets->types(), $filters['category'])
			. $this->filterSelect('topic', $this->_('Topic'), $this->_('All topics'), $tickets->topics(), $filters['topic'])
			. $this->filterSelect('priority', $this->_('Priority'), $this->_('All priorities'), $tickets->priorities(), $filters['priority'])
			. $this->filterSelect('assigned_user_id', $this->_('Assigned to'), $this->_('All agents'), $this->staffOptions(), $filters['assigned_user_id'])
			. $this->filterInput('date_from', $this->_('Created from'), 'date', $filters['date_from'])
			. $this->filterInput('date_to', $this->_('Created to'), 'date', $filters['date_to'])
			. '<div class="TicketsFilterSubmit"><button class="uk-button uk-button-default" type="submit">' . $this->_('Go') . '</button></div></form>';
		if (!$result['items']) return $out . '<div class="TicketsEmpty"><h3>' . $this->_('No tickets found') . '</h3></div></section>';
		$out .= '<form method="post" data-tickets-bulk>' . $this->csrf() . '<div class="TicketsBulkBar"><strong>' . $this->_('Bulk actions') . '</strong><select class="uk-select" name="bulk_choice" required><option value="">' . $this->_('Choose action') . '</option><optgroup label="' . $this->_('Change status') . '">';
		foreach ($tickets->statuses() as $value => $label) $out .= '<option value="status:' . $this->e($value) . '">' . $this->e($label) . '</option>';
		$out .= '</optgroup><optgroup label="' . $this->_('Change priority') . '">';
		foreach ($tickets->priorities() as $value => $label) $out .= '<option value="priority:' . $this->e($value) . '">' . $this->e($label) . '</option>';
		$out .= '</optgroup><optgroup label="' . $this->_('Assign agent') . '">';
		foreach ($this->staffOptions() as $value => $label) $out .= '<option value="assign:' . (int)$value . '">' . $this->e($label) . '</option>';
		$out .= '</optgroup></select><button class="uk-button uk-button-primary" type="submit">' . $this->_('Apply to selected') . '</button></div><div class="uk-overflow-auto TicketsQueueTable TicketsQueueTable--workspace"><table class="uk-table uk-table-divider uk-table-middle"><thead><tr><th><input class="uk-checkbox" type="checkbox" data-tickets-select-all aria-label="' . $this->_('Select all') . '"></th><th>' . $this->_('Ticket') . '</th><th>' . $this->_('Customer') . '</th><th>' . $this->_('Status') . '</th><th>' . $this->_('Priority') . '</th><th>' . $this->_('SLA') . '</th><th>' . $this->_('Activity') . '</th></tr></thead><tbody>';
		foreach ($result['items'] as $ticket) {
			$url = $this->wire('page')->url . 'view/?id=' . (int)$ticket['id'];
			$out .= '<tr><td><input class="uk-checkbox" type="checkbox" name="ticket_ids[]" value="' . (int)$ticket['id'] . '"></td><td><a class="TicketsQueueSubject" href="' . $this->e($url) . '">' . $this->e($ticket['subject']) . '</a><small>#' . $this->e($ticket['public_key']) . '</small></td><td>' . $this->e($ticket['customer_name']) . '<small>' . $this->e($ticket['customer_email']) . '</small></td><td>' . $this->e($tickets->statuses()[$ticket['status']] ?? $ticket['status']) . '</td><td>' . $this->e($tickets->priorities()[$ticket['priority']] ?? $ticket['priority']) . '</td><td>' . $this->queueSlaCell($tickets, $ticket) . '</td><td>' . $this->e($this->age((string)$ticket['updated_at'])) . '</td></tr>';
		}
		$out .= '</tbody></table></div></form>' . $this->pagination((int)$result['page'], (int)$result['pages']) . '</section>';
		return $out;
	}

	public function ___executeView(): string {
		$tickets = $this->tickets();
		$id = (int)$this->wire('input')->get('id');
		$ticket = $tickets->getTicket($id);
		if (!$ticket) throw new Wire404Exception('Ticket not found.');

		if ($this->wire('input')->requestMethod('POST')) {
			$isAjaxDraft = (bool)$this->wire('input')->post('tickets_ajax') && in_array((string)$this->wire('input')->post('ticket_action'), ['draft', 'polish'], true);
			try {
				$this->wire('session')->CSRF->validate();
				$action = $this->wire('sanitizer')->name((string)$this->wire('input')->post('ticket_action'));
				if ($action === 'reply') {
					$tickets->addReply($id, $this->wire('user'), (string)$this->wire('input')->post('body'), $_FILES['attachment'] ?? null, true);
					$this->message($this->_('Reply sent.'));
				} elseif ($action === 'update') {
					$tickets->updateTicket($id, $this->wire('user'), [
						'status' => (string)$this->wire('input')->post('status'),
						'priority' => (string)$this->wire('input')->post('priority'),
						'assigned_user_id' => (int)$this->wire('input')->post('assigned_user_id'),
					]);
					$this->message($this->_('Ticket updated.'));
				} elseif ($action === 'draft') {
					$result = $tickets->suggestReply($ticket, $tickets->ticketMessages($id), $this->wire('user'));
					if ($isAjaxDraft) $this->jsonResponse([
						'ok' => true,
						'draft' => (string)$result['draft'],
						'sources' => array_values((array)$result['sources']),
						'message' => $this->_('A grounded draft was prepared. Review it before sending.'),
					]);
					$this->wire('session')->set('tickets_draft_' . $id, (string)$result['draft']);
					$this->wire('session')->set('tickets_sources_' . $id, (array)$result['sources']);
					$this->message($this->_('A grounded draft was prepared. Review it before sending.'));
				} elseif ($action === 'polish') {
					$polished = $tickets->polishReply($ticket, $tickets->ticketMessages($id), $this->wire('user'), (string)$this->wire('input')->post('body'));
					if ($isAjaxDraft) $this->jsonResponse(['ok' => true, 'draft' => $polished, 'sources' => [], 'message' => $this->_('Grammar and clarity improved. Review the result before sending.')]);
					$this->wire('session')->set('tickets_draft_' . $id, $polished);
					$this->message($this->_('Grammar and clarity improved. Review the result before sending.'));
				} elseif ($action === 'extend_sla') {
					$tickets->extendSla($id, $this->wire('user'), (int)$this->wire('input')->post('minutes'));
					$this->message($this->_('SLA deadline extended.'));
				} elseif ($action === 'note') {
					$tickets->addInternalNote($id, $this->wire('user'), (string)$this->wire('input')->post('body'));
					$this->message($this->_('Internal note added.'));
				} elseif ($action === 'link') {
					$tickets->linkTicket($id, (int)$this->wire('input')->post('related_ticket_id'), (string)$this->wire('input')->post('link_type'), $this->wire('user'));
					$this->message($this->_('Ticket linked.'));
				} elseif ($action === 'merge') {
					$tickets->mergeTicket($id, (int)$this->wire('input')->post('related_ticket_id'), $this->wire('user'));
					$this->message($this->_('Duplicate merged into the primary ticket.'));
				}
			} catch (\Throwable $error) {
				if ($isAjaxDraft) $this->jsonResponse(['ok' => false, 'message' => $error->getMessage()], 422);
				$this->error($error->getMessage());
			}
			$this->wire('session')->redirect($this->wire('page')->url . 'view/?id=' . $id);
		}

		$ticket = $tickets->getTicket($id);
		$tickets->markMessagesRead($id, $this->wire('user'), true);
		$messages = $tickets->ticketMessages($id, true);
		$macros = $tickets->macros(true, $ticket);
		$links = $tickets->ticketLinks($id);
		$sla = $tickets->slaState($ticket);
		$draft = (string)$this->wire('session')->get('tickets_draft_' . $id);
		$draftSources = (array)$this->wire('session')->get('tickets_sources_' . $id);
		$this->wire('session')->remove('tickets_draft_' . $id);
		$this->wire('session')->remove('tickets_sources_' . $id);
		$statusColor = in_array($ticket['status'], ['resolved', 'closed'], true) ? 'success' : ($ticket['status'] === 'waiting_staff' ? 'warning' : 'neutral');
		$customerInitial = mb_strtoupper(mb_substr(trim((string)$ticket['customer_name']), 0, 1)) ?: 'C';
		$this->headline($this->_('Ticket workspace'));
		$out = $this->adminNav('view') . '<div class="TicketsViewBack"><a href="' . $this->e($this->wire('page')->url) . '"><i class="fa fa-arrow-left" aria-hidden="true"></i>' . $this->_('Back to queue') . '</a></div>';
		$out .= '<header class="TicketsCaseHeader"><div class="TicketsCaseHeader-main"><div class="TicketsCaseHeader-eyebrow"><span>' . $this->_('Support ticket') . '</span><code>#' . $this->e($ticket['public_key']) . '</code></div><form class="TicketsSubjectForm" method="post"><input type="hidden" name="ticket_action" value="update">' . $this->csrf() . '<input type="hidden" name="status" value="' . $this->e($ticket['status']) . '"><input type="hidden" name="priority" value="' . $this->e($ticket['priority']) . '"><input type="hidden" name="assigned_user_id" value="' . (int)$ticket['assigned_user_id'] . '"><label class="uk-form-label" for="ticket-subject">' . $this->_('Subject') . '</label><div><input class="uk-input" id="ticket-subject" name="subject" value="' . $this->e($ticket['subject']) . '" maxlength="180" required><button class="uk-button uk-button-default" type="submit" aria-label="' . $this->_('Save subject') . '" title="' . $this->_('Save subject') . '"><i class="fa fa-check" aria-hidden="true"></i></button></div></form><div class="TicketsCaseHeader-meta"><span><i class="fa fa-user-o" aria-hidden="true"></i>' . $this->e($ticket['customer_name']) . '</span><span><i class="fa fa-clock-o" aria-hidden="true"></i>' . $this->_('Updated') . ' ' . $this->e($this->age((string)$ticket['updated_at'])) . '</span><span><i class="fa fa-comments-o" aria-hidden="true"></i>' . count($messages) . ' ' . $this->_('messages') . '</span></div></div><div class="TicketsCaseHeader-state"><span class="TicketsBadge" data-color="' . $statusColor . '">' . $this->e($tickets->statuses()[$ticket['status']] ?? $ticket['status']) . '</span><span class="TicketsPriority" data-priority="' . $this->e($ticket['priority']) . '"><i></i>' . $this->e($tickets->priorities()[$ticket['priority']] ?? $ticket['priority']) . '</span></div></header>';

		$out .= '<div class="TicketsViewLayout"><main class="TicketsViewMain"><section class="TicketsConversation" aria-labelledby="tickets-conversation-title"><header><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Conversation') . '</p><h2 id="tickets-conversation-title">' . $this->_('Messages') . '</h2></div><span>' . count($messages) . ' ' . $this->_('total') . '</span></header><div class="TicketsThread">';
		foreach ($messages as $message) {
			$isStaff = !empty($message['is_staff']);
			$isInternal = !empty($message['is_internal']);
			$author = $isInternal ? $this->_('Internal note') : ($isStaff ? (trim((string)$tickets->from_name) ?: $this->_('Support team')) : (string)$ticket['customer_name']);
			$staffName = $isStaff ? (string)($message['user_name'] ?: 'staff') : '';
			$receipt = '';
			if (!$isInternal) {
				$receiptState = !empty($message['read_at']) ? 'read' : (!empty($message['delivered_at']) ? 'delivered' : 'sent');
				$receiptLabel = $receiptState === 'read' ? $this->_('Read') : ($receiptState === 'delivered' ? $this->_('Delivered') : $this->_('Sent'));
				$receipt = '<details class="TicketsMessageReceipt" data-state="' . $receiptState . '"><summary aria-label="' . $this->e($receiptLabel) . '"><i class="fa ' . ($receiptState === 'sent' ? 'fa-check' : 'fa-check-circle') . '" aria-hidden="true"></i><span>' . $this->e($receiptLabel) . '</span></summary><dl><dt>' . $this->_('Sent') . '</dt><dd>' . $this->e($this->dateTime((string)$message['created_at'])) . '</dd><dt>' . $this->_('Delivered') . '</dt><dd>' . $this->e(!empty($message['delivered_at']) ? $this->dateTime((string)$message['delivered_at']) : $this->_('Not confirmed')) . '</dd><dt>' . $this->_('Read') . '</dt><dd>' . $this->e(!empty($message['read_at']) ? $this->dateTime((string)$message['read_at']) : $this->_('Not yet')) . '</dd></dl></details>';
			}
			$out .= '<article class="TicketsMessage" data-author="' . ($isInternal ? 'internal' : ($isStaff ? 'staff' : 'customer')) . '"><div class="TicketsMessage-avatar" aria-hidden="true">' . ($isInternal ? '<i class="fa fa-lock"></i>' : ($isStaff ? '<i class="fa fa-life-ring"></i>' : $this->e($customerInitial))) . '</div><div class="TicketsMessage-content"><header><div><strong>' . $this->e($author) . '</strong>' . ($staffName !== '' ? '<span>' . $this->e($staffName) . '</span>' : '<span>' . $this->_('Customer') . '</span>') . '</div><div class="TicketsMessage-time"><time datetime="' . $this->e(date(DATE_ATOM, strtotime((string)$message['created_at']))) . '">' . $this->e($this->dateTime((string)$message['created_at'])) . '</time>' . $receipt . '</div></header><div class="TicketsMessage-text">' . nl2br($this->e($message['body'])) . '</div>';
			if (!empty($message['attachments'])) $out .= '<div class="TicketsMessage-attachments">';
			foreach ($message['attachments'] as $attachment) {
				$url = $tickets->attachmentUrl($ticket, $attachment);
				$isImage = str_starts_with((string)$attachment['mime_type'], 'image/');
				$preview = $isImage ? '<img src="' . $this->e($url) . '" width="96" height="72" loading="lazy" alt="">' : '<i class="fa fa-file-o" aria-hidden="true"></i>';
				$out .= '<a class="TicketsMessage-attachment" target="_blank" rel="noopener" href="' . $this->e($url) . '"><span class="TicketsMessage-attachmentPreview">' . $preview . '</span><span><strong>' . $this->e($attachment['original_name']) . '</strong><small><i class="fa fa-external-link" aria-hidden="true"></i>' . $this->_($isImage ? 'Open image' : 'Open attachment') . '</small></span></a>';
			}
			if (!empty($message['attachments'])) $out .= '</div>';
			$out .= '</div></article>';
		}
		$out .= '</div></section>';

		$out .= '<section class="TicketsReplyComposer" aria-labelledby="tickets-reply-title"><header><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Respond') . '</p><h2 id="tickets-reply-title">' . $this->_('Reply to customer') . '</h2><p>' . $this->_('Your reply is added to the private conversation and sent by transactional email.') . '</p></div>';
		if ($tickets->ai_assist_enabled) $out .= '<form method="post" data-ticket-ai-draft><input type="hidden" name="ticket_action" value="draft"><input type="hidden" name="tickets_ajax" value="1">' . $this->csrf() . '<button class="uk-button uk-button-default" type="submit" data-idle-label="' . $this->_($draft !== '' ? 'Regenerate draft' : 'Prepare AI draft') . '" data-loading-label="' . $this->_('Preparing draft…') . '"><i class="fa fa-magic uk-margin-small-right"></i><span>' . $this->_($draft !== '' ? 'Regenerate draft' : 'Prepare AI draft') . '</span></button><span class="uk-text-meta TicketsAiDraftStatus" role="status" aria-live="polite" data-ticket-ai-status></span></form>';
		$out .= '</header>';
		$out .= '<div class="TicketsDraftSources" data-ticket-draft-sources' . (!$draftSources ? ' hidden' : '') . '><strong><i class="fa fa-check-circle" aria-hidden="true"></i>' . $this->_('Verified sources used') . '</strong><ul>';
		if ($draftSources) {
			foreach ($draftSources as $source) $out .= '<li><a href="' . $this->e((string)($source['url'] ?? '')) . '" target="_blank" rel="noopener">' . $this->e((string)($source['title'] ?? 'Source')) . '</a></li>';
		}
		$out .= '</ul></div>';
		$out .= '<form class="TicketsReplyForm" method="post" enctype="multipart/form-data"><input type="hidden" name="ticket_action" value="reply">' . $this->csrf();
		if ($macros) {
			$out .= '<label class="uk-form-label" for="ticket-macro">' . $this->_('Quick reply') . '</label><select class="uk-select uk-margin-small-bottom" id="ticket-macro" data-ticket-macro><option value="">' . $this->_('Choose a saved response') . '</option>';
			foreach ($macros as $macro) $out .= '<option value="' . (int)$macro['id'] . '" data-body="' . $this->e($macro['body']) . '">' . $this->e($macro['title']) . '</option>';
			$out .= '</select>';
		}
		$out .= '<label class="uk-form-label" for="ticket-reply">' . $this->_('Message') . '</label><textarea class="uk-textarea" id="ticket-reply" name="body" rows="8" placeholder="' . $this->_('Write a clear, helpful reply…') . '" required data-ticket-reply>' . $this->e($draft) . '</textarea><div class="TicketsReplyActions"><div><label class="TicketsReplyAttachment" for="ticket-attachment"><i class="fa fa-paperclip" aria-hidden="true"></i><span>' . $this->_('Attach file') . '</span><input id="ticket-attachment" type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain"></label>' . ($tickets->ai_assist_enabled ? '<button class="uk-button uk-button-text" type="button" data-ticket-polish data-endpoint="' . $this->e($this->wire('page')->url . 'view/?id=' . $id) . '"><i class="fa fa-pencil" aria-hidden="true"></i>' . $this->_('Fix writing') . '</button>' : '') . '</div><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-paper-plane uk-margin-small-right"></i>' . $this->_('Send reply') . '</button></div></form>';
		$out .= '<details class="TicketsInternalNote"><summary><i class="fa fa-lock" aria-hidden="true"></i>' . $this->_('Add internal note') . '</summary><form class="TicketsInternalNoteForm" method="post"><input type="hidden" name="ticket_action" value="note">' . $this->csrf() . '<textarea class="uk-textarea" name="body" rows="5" required placeholder="' . $this->_('Visible only to support staff…') . '"></textarea><button class="uk-button uk-button-default" type="submit">' . $this->_('Save internal note') . '</button></form></details></section></main>';

		$out .= '<aside class="TicketsViewAside"><form class="TicketsSidePanel" method="post"><input type="hidden" name="ticket_action" value="update">' . $this->csrf() . '<header><span class="TicketsSidePanel-icon"><i class="fa fa-sliders"></i></span><div><h2>' . $this->_('Workflow') . '</h2><p>' . $this->_('Route and resolve this request.') . '</p></div></header>' . $this->select('status', $this->_('Status'), $tickets->statuses(), (string)$ticket['status']) . $this->select('priority', $this->_('Priority'), $tickets->priorities(), (string)$ticket['priority']) . $this->select('assigned_user_id', $this->_('Assigned to'), $this->staffOptions(), (string)(int)$ticket['assigned_user_id']) . '<button class="uk-button uk-button-primary uk-width-1-1" type="submit">' . $this->_('Save workflow') . '</button></form>';
		$slaLabel = $sla['due_at'] !== '' ? date('M j, Y · H:i', strtotime($sla['due_at'])) : $this->_('Not set');
		$slaExtend = in_array((string)$ticket['status'], ['resolved', 'closed'], true)
			? '<p class="TicketsSlaComplete"><i class="fa fa-check-circle" aria-hidden="true"></i>' . $this->_('No active deadline') . '</p>'
			: '<form class="TicketsSlaExtend" method="post"><input type="hidden" name="ticket_action" value="extend_sla">' . $this->csrf() . '<label class="uk-form-label" for="ticket-sla-minutes">' . $this->_('Extend deadline') . '</label><div><select class="uk-select" id="ticket-sla-minutes" name="minutes"><option value="60">+1 hour</option><option value="240">+4 hours</option><option value="1440">+1 day</option><option value="4320">+3 days</option></select><button class="uk-button uk-button-default" type="submit">' . $this->_('Extend') . '</button></div></form>';
		$out .= '<section class="TicketsSidePanel TicketsSla" data-breached="' . (!empty($sla['breached']) ? 'true' : 'false') . '"><h2><i class="fa fa-stopwatch" aria-hidden="true"></i>' . $this->_('SLA') . '</h2><p><strong>' . $this->e($sla['phase'] === 'first_response' ? $this->_('First response') : $this->_('Resolution')) . '</strong><br>' . $this->e($slaLabel) . '</p>' . (!empty($sla['breached']) ? '<span class="uk-label uk-label-danger">' . $this->_('Breached') . '</span>' : '') . $slaExtend . '</section>';
		$rating = max(0, min(5, (int)($ticket['rating'] ?? 0)));
		if ($rating > 0 || in_array((string)$ticket['status'], ['resolved', 'closed'], true)) {
			$feedbackBody = $rating > 0
				? '<div class="TicketsCustomerFeedback-score" aria-label="' . sprintf($this->_('%d out of 5'), $rating) . '"><strong>' . $rating . '/5</strong><span aria-hidden="true">' . str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . '</span></div>'
				: '<p class="TicketsCustomerFeedback-empty">' . $this->_('Awaiting customer feedback.') . '</p>';
			if ($rating > 0 && trim((string)($ticket['rating_comment'] ?? '')) !== '') {
				$feedbackBody .= '<blockquote>' . nl2br($this->e((string)$ticket['rating_comment'])) . '</blockquote>';
			}
			if ($rating > 0 && !empty($ticket['rated_at'])) {
				$feedbackBody .= '<p class="TicketsCustomerFeedback-date">' . $this->_('Submitted') . ' ' . $this->e(date('M j, Y · H:i', strtotime((string)$ticket['rated_at']))) . '</p>';
			}
			$out .= '<section class="TicketsSidePanel TicketsCustomerFeedback"><header><span class="TicketsSidePanel-icon">' . $this->metricIcon('rating') . '</span><div><h2>' . $this->_('Customer feedback') . '</h2><p>' . $this->_('Post-resolution support rating') . '</p></div></header>' . $feedbackBody . '</section>';
		}
		$out .= '<section class="TicketsSidePanel"><h2>' . $this->_('Related tickets') . '</h2>';
		if ($links) { $out .= '<ul class="TicketsRelated">'; foreach ($links as $link) $out .= '<li><a href="' . $this->e($this->wire('page')->url . 'view/?id=' . (int)$link['related_ticket_id']) . '">#' . $this->e($link['public_key']) . ' · ' . $this->e($link['subject']) . '</a><small>' . $this->e($link['link_type']) . '</small></li>'; $out .= '</ul>'; }
		$out .= '<form method="post" class="TicketsLinkForm"><input type="hidden" name="ticket_action" value="link">' . $this->csrf() . '<label class="uk-form-label">' . $this->_('Ticket ID') . '</label><input class="uk-input" type="number" min="1" name="related_ticket_id" required><select class="uk-select uk-margin-small-top" name="link_type"><option value="related">' . $this->_('Related') . '</option><option value="duplicate">' . $this->_('Duplicate') . '</option><option value="parent">' . $this->_('Parent') . '</option><option value="child">' . $this->_('Child') . '</option></select><button class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top" type="submit">' . $this->_('Link ticket') . '</button></form><form method="post" class="uk-margin-small-top" onsubmit="return confirm(\'' . $this->_('Close this duplicate and merge it into the selected primary ticket?') . '\')"><input type="hidden" name="ticket_action" value="merge">' . $this->csrf() . '<input class="uk-input" type="number" min="1" name="related_ticket_id" required placeholder="' . $this->_('Primary ticket ID') . '"><button class="uk-button uk-button-danger uk-width-1-1 uk-margin-small-top" type="submit">' . $this->_('Merge duplicate') . '</button></form></section>';
		$out .= '<section class="TicketsSidePanel"><header><span class="TicketsSidePanel-avatar">' . $this->e($customerInitial) . '</span><div><h2>' . $this->e($ticket['customer_name']) . '</h2><p>' . $this->_('Customer') . '</p></div></header><dl><dt>' . $this->_('Email') . '</dt><dd><a href="mailto:' . $this->e($ticket['customer_email']) . '">' . $this->e($ticket['customer_email']) . '</a></dd><dt>' . $this->_('Type') . '</dt><dd>' . $this->e($tickets->types()[$ticket['category']] ?? $ticket['category']) . '</dd><dt>' . $this->_('Topic') . '</dt><dd>' . $this->e($tickets->topics()[(string)($ticket['topic'] ?? 'general')] ?? (string)($ticket['topic'] ?? 'general')) . '</dd>';
		$place = implode(', ', array_filter([(string)($ticket['customer_city'] ?? ''), (string)($ticket['customer_region'] ?? ''), (string)($ticket['customer_country'] ?? '')]));
		if ($place !== '') $out .= '<dt>' . $this->_('Location') . '</dt><dd>' . $this->e($place) . '</dd>';
		$customerZone = (string)($ticket['customer_timezone'] ?? '');
		if ($customerZone !== '' && in_array($customerZone, timezone_identifiers_list(), true)) {
			$customerNow = new \DateTimeImmutable('now', new \DateTimeZone($customerZone));
			$out .= '<dt>' . $this->_('Customer local time') . '</dt><dd><strong>' . $this->e($customerNow->format('g:i A')) . '</strong><br><small>' . $this->e($customerNow->format('D, M j · T')) . '</small></dd>';
		}
		if (!empty($ticket['context_url'])) $out .= '<dt>' . $this->_('Related record') . '</dt><dd><a href="' . $this->e($ticket['context_url']) . '" target="_blank" rel="noopener">' . $this->e($ticket['context_type'] ?: $ticket['context_url']) . '</a></dd>';
		$labels = [];
		if (!empty($ticket['form'])) {
			$out .= '<dt>' . $this->_('Source form') . '</dt><dd>' . $this->e($ticket['form']['title']) . '</dd>';
			foreach ((array)$ticket['form']['fields'] as $field) $labels[(string)$field['name']] = (string)$field['label'];
		}
		foreach ((array)$ticket['custom_values'] as $key => $value) $out .= '<dt>' . $this->e($labels[$key] ?? $key) . '</dt><dd>' . nl2br($this->e($value)) . '</dd>';
		$out .= '</dl></section><section class="TicketsSidePanel TicketsCaseDates"><h2>' . $this->_('Activity') . '</h2><dl><dt>' . $this->_('Created') . '</dt><dd>' . $this->e(date('M j, Y · H:i', strtotime((string)$ticket['created_at']))) . '</dd><dt>' . $this->_('Last updated') . '</dt><dd>' . $this->e(date('M j, Y · H:i', strtotime((string)$ticket['updated_at']))) . '</dd><dt>' . $this->_('Ticket ID') . '</dt><dd><code>#' . $this->e($ticket['public_key']) . '</code></dd></dl><a class="uk-button uk-button-default uk-width-1-1" href="' . $this->e(rtrim((string)$tickets->public_path, '/') . '/' . $ticket['public_key'] . '/') . '" target="_blank" rel="noopener"><i class="fa fa-external-link uk-margin-small-right"></i>' . $this->_('Open customer view') . '</a></section></aside></div>';
		return $this->workspace($out);
	}

	public function ___executeTemplates(): string {
		$tickets = $this->tickets();
		if (!$tickets->canAdmin($this->wire('user'))) throw new WirePermissionException('You cannot edit transactional mail templates.');
		$input = $this->wire('input');
		if ($this->wire('input')->requestMethod('POST')) {
			try {
				$this->wire('session')->CSRF->validate();
				$templateKey = $this->wire('sanitizer')->name((string)$input->post('template_key'));
				$tickets->saveMailTemplate(
					$templateKey,
					(string)$input->post('subject'),
					(string)$input->post('html_body_' . $templateKey),
					$this->wire('user')
				);
				$this->message($this->_('Transactional mail template saved.'));
			} catch (\Throwable $error) {
				$this->error($error->getMessage());
			}
			$this->wire('session')->redirect($this->wire('page')->url . 'templates/?template=' . rawurlencode($templateKey));
		}
		$this->headline($this->_('Templates'));
		$variables = ['ticket_key', 'subject', 'customer_name', 'customer_email', 'message', 'ticket_url'];
		$meta = [
			'ticket_created_staff' => ['recipient' => 'Support team', 'purpose' => 'Sent to staff when a new request is created.'],
			'ticket_created_customer' => ['recipient' => 'Customer', 'purpose' => 'Confirms receipt and gives the customer a private ticket link.'],
			'ticket_reply_customer' => ['recipient' => 'Customer', 'purpose' => 'Delivers a support reply and a fresh private access link.'],
			'ticket_reply_staff' => ['recipient' => 'Support team', 'purpose' => 'Notifies staff when the customer adds a reply.'],
			'ticket_sla_breach_staff' => ['recipient' => 'Escalation team', 'purpose' => 'Alerts staff once when an active ticket misses its SLA target.'],
		];
		$templates = $tickets->mailTemplates();
		$requestedKey = $this->wire('sanitizer')->name((string)$input->get('template'));
		$selected = $templates ? (array)reset($templates) : [];
		foreach ($templates as $candidate) {
			if ((string)$candidate['template_key'] === $requestedKey) { $selected = $candidate; break; }
		}
		$selectedKey = (string)($selected['template_key'] ?? '');
		$customerCount = 0;
		$staffCount = 0;
		foreach ($templates as $template) {
			$templateRecipient = (string)($meta[(string)$template['template_key']]['recipient'] ?? 'Customer');
			if ($templateRecipient === 'Customer') $customerCount++; else $staffCount++;
		}
		$delivery = '<div class="TicketsIntroNotice"><strong>' . $this->_('Delivery') . '</strong><span>' . sprintf($this->_('Stored locally · delivered through %s'), $this->e($tickets->mailProviderLabel())) . '</span></div>';
		$out = $this->adminNav('templates')
			. $this->pageIntro($this->_('Transactional email'), $this->_('Templates'), $this->_('Choose a message, edit it visually, inspect the HTML when needed, and check the final email before saving.'), $delivery)
			. '<section class="TicketsTemplateSummary" aria-label="' . $this->_('Template summary') . '"><article><span>' . $this->_('Templates') . '</span><strong>' . count($templates) . '</strong><small>' . $this->_('Transactional messages') . '</small></article><article><span>' . $this->_('Customer') . '</span><strong>' . $customerCount . '</strong><small>' . $this->_('Customer-facing messages') . '</small></article><article><span>' . $this->_('Operations') . '</span><strong>' . $staffCount . '</strong><small>' . $this->_('Staff and escalation messages') . '</small></article><article><span>' . $this->_('Variables') . '</span><strong>' . count($variables) . '</strong><small>{{ticket_key}}</small></article></section>'
			. '<nav class="TicketsTemplatePicker" aria-label="' . $this->_('Choose mail template') . '"><ul class="uk-subnav uk-subnav-pill">';
		foreach ($templates as $template) {
			$key = (string)$template['template_key'];
			$templateMeta = $meta[$key] ?? ['recipient' => 'Customer', 'purpose' => 'Transactional ticket update.'];
			$out .= '<li' . ($key === $selectedKey ? ' class="uk-active"' : '') . '><a href="?template=' . rawurlencode($key) . '#template-editor"><span>' . $this->e($template['label']) . '</span><small>' . $this->e($templateMeta['recipient']) . '</small></a></li>';
		}
		$out .= '</ul></nav>';
		if ($selected) {
			$key = $selectedKey;
			$editorName = 'html_body_' . $key;
			$editorId = 'tickets-editor-' . str_replace('_', '-', $key);
			$templateMeta = $meta[$key] ?? ['recipient' => 'Customer', 'purpose' => 'Transactional ticket update.'];
			$out .= '<section class="TicketsTemplateWorkspace uk-card uk-card-default" id="template-editor"><header><div><span class="uk-label">' . $this->e($templateMeta['recipient']) . '</span><h2>' . $this->e($selected['label']) . '</h2><p>' . $this->e($templateMeta['purpose']) . '</p><a href="' . $this->e($this->wire('config')->urls->admin . 'module/edit?name=Tickets#Inputfield_mail_header_html') . '">' . $this->_('Customize shared header and footer') . '</a></div><code>' . $this->e($key) . '</code></header>'
				. '<form class="TicketsTemplateForm" method="post" data-ticket-template-form data-ticket-mail-header="' . $this->e((string)$tickets->mail_header_html) . '" data-ticket-mail-footer="' . $this->e((string)$tickets->mail_footer_html) . '">' . $this->csrf()
				. '<input type="hidden" name="template_key" value="' . $this->e($key) . '"><div class="TicketsTemplateGrid"><div class="TicketsTemplateEditor">'
				. '<label class="uk-form-label" for="subject-' . $this->e($key) . '">' . $this->_('Email subject') . '</label><input class="uk-input TicketsTemplateSubject" id="subject-' . $this->e($key) . '" name="subject" maxlength="240" required value="' . $this->e($selected['subject']) . '" data-ticket-template-subject>'
				. '<div class="TicketsVariableBar"><span>' . $this->_('Insert variable') . '</span>';
			foreach ($variables as $variable) $out .= '<button class="uk-button uk-button-default uk-button-small" type="button" data-ticket-variable="{{' . $this->e($variable) . '}}" data-ticket-editor="' . $this->e($editorId) . '">{{' . $this->e($variable) . '}}</button>';
			$out .= '</div><label class="uk-form-label uk-display-block" for="' . $this->e($editorId) . '">' . $this->_('Message body') . '</label>'
				. $this->renderMailEditor($editorName, $editorId, (string)$selected['html_body'])
				. '<div class="TicketsTemplateActions"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-save uk-margin-small-right"></i>' . $this->_('Save template') . '</button><span class="uk-text-meta">' . $this->_('Review the preview before saving.') . '</span></div></div>'
				. '<aside class="TicketsTemplatePreview"><div class="TicketsTemplatePreviewHeader"><span><i class="fa fa-envelope-o" aria-hidden="true"></i> ' . $this->_('Email preview') . '</span><span class="uk-label">' . $this->e($templateMeta['recipient']) . '</span></div><div class="TicketsTemplatePreviewSubject" data-ticket-preview-subject></div><iframe title="' . $this->e($selected['label']) . ' preview" sandbox="" data-ticket-preview-frame></iframe><p class="uk-text-meta"><i class="fa fa-info-circle uk-margin-small-right"></i>' . $this->_('Sample data replaces variables only in this preview.') . '</p></aside></div></form></section>';
		}
		return $this->workspace($out);
	}

	public function ___executeAutomation(): string {
		$tickets = $this->tickets();
		if (!$tickets->canAdmin($this->wire('user'))) throw new WirePermissionException('You cannot manage ticket automation.');
		$input = $this->wire('input');
		if ($input->requestMethod('POST')) {
			try {
				$this->wire('session')->CSRF->validate();
				$kind = $this->wire('sanitizer')->name((string)$input->post('automation_kind'));
				if ($kind === 'routing') {
					$tickets->saveRoutingRule($input->post->getArray(), $this->wire('user'));
					$this->message($this->_('Routing rule saved.'));
				} elseif ($kind === 'macro') {
					$tickets->saveMacro($input->post->getArray(), $this->wire('user'));
					$this->message($this->_('Reply macro saved.'));
				} elseif ($kind === 'run') {
					$result = $tickets->runAutomation(false);
					$this->message(sprintf($this->_('Automation complete: %d escalated, %d auto-closed.'), $result['sla_breaches'], $result['auto_closed']));
				}
			} catch (\Throwable $error) { $this->error($error->getMessage()); }
			$this->wire('session')->redirect($this->wire('page')->url . 'automation/');
		}
		$rules = $tickets->routingRules(false);
		$macros = $tickets->macros(false);
		$ruleId = (int)$input->get('edit_rule');
		$macroId = (int)$input->get('edit_macro');
		$rule = ['id' => 0, 'name' => '', 'enabled' => 1, 'sort_order' => 0, 'category' => '', 'topic' => '', 'form_id' => 0, 'priority' => '', 'assigned_user_id' => 0, 'first_response_minutes' => 0, 'resolution_minutes' => 0];
		foreach ($rules as $candidate) if ((int)$candidate['id'] === $ruleId) $rule = $candidate;
		$macro = ['id' => 0, 'title' => '', 'body' => '', 'category' => '', 'topic' => '', 'enabled' => 1, 'sort_order' => 0];
		foreach ($macros as $candidate) if ((int)$candidate['id'] === $macroId) $macro = $candidate;
		$this->headline($this->_('Automation'));
		$runAutomation = '<form class="TicketsAutomationRun" method="post"><input type="hidden" name="automation_kind" value="run">' . $this->csrf() . '<button class="uk-button uk-button-default" type="submit"><i class="fa fa-play uk-margin-small-right" aria-hidden="true"></i>' . $this->_('Run automation now') . '</button><small>' . $this->_('Checks SLA deadlines and auto-close rules.') . '</small></form>';
		$out = $this->adminNav('automation') . $this->pageIntro($this->_('Operations'), $this->_('Automation'), $this->_('Route new requests, set response targets, and prepare consistent answers for the support team.'), $runAutomation);
		$out .= '<section class="TicketsAutomationSummary" aria-label="' . $this->_('Automation summary') . '"><article><span>' . $this->_('Routing rules') . '</span><strong>' . count($rules) . '</strong></article><article><span>' . $this->_('Quick replies') . '</span><strong>' . count($macros) . '</strong></article><p><i class="fa fa-info-circle" aria-hidden="true"></i>' . $this->_('Lower rule order values are evaluated first. Zero-minute SLA values disable that target.') . '</p></section>';

		$out .= '<div class="TicketsAutomationGrid"><section class="uk-card uk-card-default uk-card-body TicketsAutomationPanel"><header class="TicketsAutomationPanel-header"><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Assignment') . '</p><h2>' . $this->_('Routing rules') . '</h2><p>' . $this->_('Match an incoming request, assign an owner, and apply response targets.') . '</p></div>' . ($ruleId > 0 ? '<a class="uk-button uk-button-default uk-button-small" href="?">' . $this->_('New rule') . '</a>' : '') . '</header><div class="TicketsAutomationList">';
		if (!$rules) {
			$out .= '<div class="TicketsAutomationEmpty"><i class="fa fa-random" aria-hidden="true"></i><span>' . $this->_('No routing rules yet') . '</span><small>' . $this->_('Create a rule below to start assigning requests automatically.') . '</small></div>';
		} else {
			foreach ($rules as $item) {
				$isActive = (int)$item['id'] === (int)$rule['id'];
				$out .= '<a' . ($isActive ? ' class="is-active" aria-current="true"' : '') . ' href="?edit_rule=' . (int)$item['id'] . '"><span><strong>' . $this->e($item['name']) . '</strong><small>' . $this->e($item['category'] ?: $this->_('Any type')) . ' · ' . $this->e($item['topic'] ?: $this->_('Any topic')) . '</small></span><span class="TicketsBadge" data-color="' . ($item['enabled'] ? 'success' : 'neutral') . '">' . ($item['enabled'] ? $this->_('Enabled') : $this->_('Disabled')) . '</span></a>';
			}
		}
		$out .= '</div><form class="TicketsAutomationForm uk-form-stacked" method="post"><input type="hidden" name="automation_kind" value="routing"><input type="hidden" name="id" value="' . (int)$rule['id'] . '">' . $this->csrf()
			. '<div class="TicketsAutomationForm-section"><h3>' . ($ruleId > 0 ? $this->_('Edit routing rule') : $this->_('Create routing rule')) . '</h3>'
			. $this->automationInput('routing', 'name', $this->_('Rule name'), 'text', (string)$rule['name'], ['required' => true, 'maxlength' => 160, 'placeholder' => $this->_('For example: Urgent account requests')])
			. '<div class="TicketsAutomationFields">'
			. $this->automationSelect('routing', 'category', $this->_('Ticket type'), ['' => $this->_('Any type')] + $tickets->types(), (string)$rule['category'])
			. $this->automationSelect('routing', 'topic', $this->_('Topic'), ['' => $this->_('Any topic')] + $tickets->topics(), (string)$rule['topic'])
			. $this->automationSelect('routing', 'priority', $this->_('Priority'), ['' => $this->_('Any priority')] + $tickets->priorities(), (string)$rule['priority'])
			. $this->automationInput('routing', 'form_id', $this->_('Source form ID'), 'number', (string)(int)$rule['form_id'], ['min' => 0, 'help' => $this->_('Use 0 to match every support form.')]) . '</div></div>'
			. '<div class="TicketsAutomationForm-section"><h3>' . $this->_('Assignment and targets') . '</h3><div class="TicketsAutomationFields">'
			. $this->automationSelect('routing', 'assigned_user_id', $this->_('Assigned to'), $this->staffOptions(), (string)$rule['assigned_user_id'])
			. $this->automationInput('routing', 'sort_order', $this->_('Rule order'), 'number', (string)(int)$rule['sort_order'], ['help' => $this->_('Lower values run first.')])
			. $this->automationInput('routing', 'first_response_minutes', $this->_('First response target'), 'number', (string)(int)$rule['first_response_minutes'], ['min' => 0, 'suffix' => $this->_('minutes')])
			. $this->automationInput('routing', 'resolution_minutes', $this->_('Resolution target'), 'number', (string)(int)$rule['resolution_minutes'], ['min' => 0, 'suffix' => $this->_('minutes')]) . '</div></div>'
			. '<footer class="TicketsAutomationActions"><label><input class="uk-checkbox" type="checkbox" name="enabled" value="1"' . (!empty($rule['enabled']) ? ' checked' : '') . '> <span>' . $this->_('Rule enabled') . '</span></label><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-save uk-margin-small-right" aria-hidden="true"></i>' . ($ruleId > 0 ? $this->_('Update rule') : $this->_('Create rule')) . '</button></footer></form></section>';

		$out .= '<section class="uk-card uk-card-default uk-card-body TicketsAutomationPanel"><header class="TicketsAutomationPanel-header"><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Response library') . '</p><h2>' . $this->_('Quick replies') . '</h2><p>' . $this->_('Save reusable answers and show them only for relevant ticket types or topics.') . '</p></div>' . ($macroId > 0 ? '<a class="uk-button uk-button-default uk-button-small" href="?">' . $this->_('New reply') . '</a>' : '') . '</header><div class="TicketsAutomationList">';
		if (!$macros) {
			$out .= '<div class="TicketsAutomationEmpty"><i class="fa fa-comments-o" aria-hidden="true"></i><span>' . $this->_('No quick replies yet') . '</span><small>' . $this->_('Create a reusable answer below to speed up common conversations.') . '</small></div>';
		} else {
			foreach ($macros as $item) {
				$isActive = (int)$item['id'] === (int)$macro['id'];
				$out .= '<a' . ($isActive ? ' class="is-active" aria-current="true"' : '') . ' href="?edit_macro=' . (int)$item['id'] . '"><span><strong>' . $this->e($item['title']) . '</strong><small>' . $this->e($item['category'] ?: $this->_('All types')) . ' · ' . $this->e($item['topic'] ?: $this->_('All topics')) . '</small></span><span class="TicketsBadge" data-color="' . ($item['enabled'] ? 'success' : 'neutral') . '">' . ($item['enabled'] ? $this->_('Enabled') : $this->_('Disabled')) . '</span></a>';
			}
		}
		$out .= '</div><form class="TicketsAutomationForm uk-form-stacked" method="post"><input type="hidden" name="automation_kind" value="macro"><input type="hidden" name="id" value="' . (int)$macro['id'] . '">' . $this->csrf()
			. '<div class="TicketsAutomationForm-section"><h3>' . ($macroId > 0 ? $this->_('Edit quick reply') : $this->_('Create quick reply')) . '</h3>'
			. $this->automationInput('macro', 'title', $this->_('Reply title'), 'text', (string)$macro['title'], ['required' => true, 'maxlength' => 160, 'placeholder' => $this->_('For example: Request received')])
			. '<label class="TicketsAutomationField" for="macro-body"><span class="uk-form-label">' . $this->_('Reply text') . '</span><textarea class="uk-textarea" id="macro-body" name="body" rows="8" maxlength="10000" required placeholder="' . $this->e($this->_('Write the reusable response shown to support agents.')) . '">' . $this->e($macro['body']) . '</textarea><small>' . $this->_('Agents can edit the text before sending it.') . '</small></label>'
			. '<div class="TicketsAutomationFields">'
			. $this->automationSelect('macro', 'category', $this->_('Ticket type'), ['' => $this->_('All types')] + $tickets->types(), (string)$macro['category'])
			. $this->automationSelect('macro', 'topic', $this->_('Topic'), ['' => $this->_('All topics')] + $tickets->topics(), (string)$macro['topic']) . '</div></div>'
			. '<footer class="TicketsAutomationActions"><label><input class="uk-checkbox" type="checkbox" name="enabled" value="1"' . (!empty($macro['enabled']) ? ' checked' : '') . '> <span>' . $this->_('Reply enabled') . '</span></label><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-save uk-margin-small-right" aria-hidden="true"></i>' . ($macroId > 0 ? $this->_('Update reply') : $this->_('Create reply')) . '</button></footer></form></section></div>';
		return $this->workspace($out);
	}

	public function ___executeForms(): string {
		$tickets = $this->tickets();
		if (!$tickets->canAdmin($this->wire('user'))) throw new WirePermissionException('You cannot manage custom forms.');
		$input = $this->wire('input');
		if ($input->requestMethod('POST')) {
			try {
				$this->wire('session')->CSRF->validate();
				$action = $this->wire('sanitizer')->name((string)$input->post('form_action'));
				if ($action === 'import_formbuilder') {
					$sourceIds = array_values(array_unique(array_filter(array_map('intval', (array)$input->post('source_ids')))));
					if (!$sourceIds) throw new WireException($this->_('Choose at least one FormBuilder form.'));
					$importer = $tickets->formBuilderImporter();
					foreach ($sourceIds as $sourceId) $importer->import($sourceId, $this->wire('user'));
					$this->message(sprintf($this->_('%d FormBuilder forms imported as drafts.'), count($sourceIds)));
				} elseif ($action === 'delete') {
					$tickets->deleteCustomForm((int)$input->post('id'), $this->wire('user'));
					$this->message($this->_('Form deleted.'));
				} else {
					$form = $tickets->saveCustomForm($input->post->getArray(), $this->wire('user'));
					$this->message($this->_('Form saved.'));
					$this->wire('session')->redirect($this->wire('page')->url . 'forms/?id=' . (int)$form['id']);
				}
			} catch (\Throwable $error) {
				$this->error($error->getMessage());
			}
			$this->wire('session')->redirect($this->wire('page')->url . 'forms/');
		}

		$id = (int)$input->get('id');
		$form = $id > 0 ? $tickets->customForm($id) : [];
		$isImport = (bool)$input->get('import');
		$isEditor = $id > 0 || $input->get('new');
		$this->headline($isEditor ? $this->_('Edit form') : $this->_('Forms'));
		$out = $this->adminNav('forms');
		if ($isImport) {
			$importer = $tickets->formBuilderImporter();
			$out .= '<p><a href="' . $this->e($this->wire('page')->url . 'forms/') . '"><i class="fa fa-arrow-left uk-margin-small-right"></i>' . $this->_('Back to forms') . '</a></p>';
			$out .= $this->pageIntro($this->_('Migration'), $this->_('Import from FormBuilder'), $this->_('Copy selected definitions into Tickets. Imports are disabled drafts until you review routing, legal copy, fields and attachments. FormBuilder remains unchanged.'));
			$candidates = $importer->candidates($this->wire('user'));
			if (!$candidates) return $this->workspace($out . '<div class="TicketsEmpty"><i class="fa fa-wpforms"></i><h3>' . $this->_('No FormBuilder forms found') . '</h3></div>');
			$out .= '<form method="post" class="TicketsImportForms">' . $this->csrf() . '<input type="hidden" name="form_action" value="import_formbuilder"><div class="TicketsImportForms-list">';
			foreach ($candidates as $candidate) {
				$out .= '<label class="TicketsImportForm"><input class="uk-checkbox" type="checkbox" name="source_ids[]" value="' . (int)$candidate['source_id'] . '"' . (!$candidate['fields'] ? ' disabled' : '') . '><span><strong>' . $this->e($candidate['title']) . '</strong><small>' . count($candidate['fields']) . ' ' . $this->_('portable fields') . ' · <code>' . $this->e($candidate['target_name']) . '</code>' . (!empty($candidate['existing']) ? ' · ' . $this->_('will update existing draft') : '') . '</small>';
				foreach ($candidate['warnings'] as $warning) $out .= '<small class="TicketsImportForm-warning"><i class="fa fa-exclamation-triangle"></i> ' . $this->e($warning) . '</small>';
				if (!$candidate['fields']) $out .= '<small class="TicketsImportForm-warning">' . $this->_('No importable fields') . '</small>';
				$out .= '</span></label>';
			}
			$out .= '</div><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-download uk-margin-small-right"></i>' . $this->_('Import selected forms') . '</button></form>';
			return $this->workspace($out);
		}
		if (!$isEditor) {
			$forms = $tickets->customForms();
			$createForm = '<span class="TicketsFormActions"><a class="uk-button uk-button-default" href="?import=1"><i class="fa fa-download uk-margin-small-right"></i>' . $this->_('Import from FormBuilder') . '</a><a class="uk-button uk-button-primary" href="?new=1"><i class="fa fa-plus uk-margin-small-right"></i>' . $this->_('Create form') . '</a></span>';
			$out .= $this->pageIntro($this->_('Reusable support intake'), $this->_('Forms'), $this->_('Build fields visually, embed a form in page content, and route every submission into the ticket workflow.'), $createForm);
			if (!$forms) return $this->workspace($out . '<div class="TicketsEmpty"><i class="fa fa-wpforms"></i><h3>' . $this->_('No custom forms yet') . '</h3><p>' . $this->_('Create the first form and place it on any ProcessWire page.') . '</p></div>');
			$out .= '<div class="TicketsFormsGrid">';
			foreach ($forms as $item) {
				$enabled = !empty($item['enabled']);
				$previewUrl = $enabled
					? rtrim((string)$tickets->public_path, '/') . '/form/' . rawurlencode((string)$item['name']) . '/'
					: '?id=' . (int)$item['id'] . '#tickets-form-preview';
				$previewAttrs = $enabled ? ' target="_blank" rel="noopener"' : '';
				$previewIcon = $enabled ? ' <i class="fa fa-external-link" aria-hidden="true"></i>' : '';
				$out .= '<article class="TicketsFormCard"><header><span class="TicketsBadge" data-color="' . ($enabled ? 'success' : 'neutral') . '">' . $this->_($enabled ? 'Active' : 'Disabled') . '</span><span>' . count($item['fields']) . ' ' . $this->_('fields') . '</span></header><h3>' . $this->e($item['title']) . '</h3><p>' . $this->e($item['description']) . '</p><code>[[tickets-form:' . $this->e($item['name']) . ']]</code><footer><a class="uk-button uk-button-default" href="?id=' . (int)$item['id'] . '">' . $this->_('Edit form') . '</a><a href="' . $this->e($previewUrl) . '"' . $previewAttrs . '>' . $this->_($enabled ? 'Preview' : 'Preview draft') . $previewIcon . '</a></footer></article>';
			}
			return $this->workspace($out . '</div>');
		}

		if (!$form) $form = [
			'id' => 0, 'name' => '', 'title' => '', 'description' => '', 'success_message' => 'Thank you. Your request was sent to the support team.',
			'submit_label' => 'Send request', 'category' => 'other', 'topic' => 'general', 'priority' => 'normal',
			'allow_guests' => 1, 'allow_attachment' => 0, 'enabled' => 1,
			'fields' => [
				['name' => 'full_name', 'label' => 'Your name', 'type' => 'text', 'required' => true, 'width' => 'half', 'placeholder' => '', 'help' => '', 'options' => []],
				['name' => 'subject', 'label' => 'What can we help with?', 'type' => 'text', 'required' => true, 'width' => 'full', 'placeholder' => '', 'help' => '', 'options' => []],
				['name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'required' => true, 'width' => 'full', 'placeholder' => '', 'help' => '', 'options' => []],
			],
		];
		$out .= '<p><a href="' . $this->e($this->wire('page')->url . 'forms/') . '"><i class="fa fa-arrow-left uk-margin-small-right"></i>' . $this->_('Back to forms') . '</a></p>';
		$out .= '<form class="TicketsFormEditor" method="post" data-tickets-form-builder>' . $this->csrf() . '<input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="' . (int)$form['id'] . '"><input type="hidden" name="fields_json" data-tickets-fields-json value="' . $this->e(json_encode($form['fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '">';
		$out .= '<section class="TicketsFormEditor-main"><div class="uk-card uk-card-default uk-card-body"><h2>' . $this->_('Form identity') . '</h2><div class="uk-grid-small" uk-grid><label class="uk-width-2-3@s"><span class="uk-form-label">' . $this->_('Title') . '</span><input class="uk-input" name="title" required maxlength="160" value="' . $this->e($form['title']) . '" data-tickets-form-title></label><label class="uk-width-1-3@s"><span class="uk-form-label">' . $this->_('Slug') . '</span><input class="uk-input" name="name" required maxlength="120" value="' . $this->e($form['name']) . '" data-tickets-form-slug></label><label class="uk-width-1-1"><span class="uk-form-label">' . $this->_('Description') . '</span><textarea class="uk-textarea" name="description" rows="3">' . $this->e($form['description']) . '</textarea></label></div></div>';
		$out .= '<div class="uk-card uk-card-default uk-card-body"><div class="TicketsFormFieldsHead"><div><h2>' . $this->_('Fields') . '</h2><p>' . $this->_('Add, remove, and order the inputs shown to customers.') . '</p></div><button class="uk-button uk-button-default" type="button" data-tickets-add-field><i class="fa fa-plus uk-margin-small-right"></i>' . $this->_('Add field') . '</button></div><div class="TicketsFormFields" data-tickets-fields>';
		foreach ($form['fields'] as $field) $out .= $this->formFieldRow($field, $tickets->formFieldTypes());
		$out .= '</div></div><div class="uk-card uk-card-default uk-card-body TicketsFormPreview" id="tickets-form-preview"><div class="TicketsFormFieldsHead"><div><h2>' . $this->_('Live preview') . '</h2><p>' . $this->_('A safe admin-only preview of the customer form. No ticket is submitted.') . '</p></div></div><div data-tickets-form-preview></div></div></section>';
		$out .= '<aside class="TicketsFormEditor-side"><div class="uk-card uk-card-default uk-card-body"><h2>' . $this->_('Routing and access') . '</h2>' . $this->select('category', $this->_('Ticket type'), $tickets->types(), (string)$form['category']) . $this->select('topic', $this->_('Topic'), $tickets->topics(), (string)$form['topic']) . $this->select('priority', $this->_('Priority'), $tickets->priorities(), (string)$form['priority']) . '<label><input class="uk-checkbox" type="checkbox" name="allow_guests" value="1"' . (!empty($form['allow_guests']) ? ' checked' : '') . '> ' . $this->_('Guests may submit') . '</label><label><input class="uk-checkbox" type="checkbox" name="allow_attachment" value="1"' . (!empty($form['allow_attachment']) ? ' checked' : '') . '> ' . $this->_('Allow image attachment') . '</label><label><input class="uk-checkbox" type="checkbox" name="enabled" value="1"' . (!empty($form['enabled']) ? ' checked' : '') . '> ' . $this->_('Form is active') . '</label></div>';
		$out .= '<div class="uk-card uk-card-default uk-card-body"><h2>' . $this->_('Confirmation') . '</h2><label class="uk-form-label">' . $this->_('Submit button') . '</label><input class="uk-input" name="submit_label" maxlength="80" value="' . $this->e($form['submit_label']) . '"><label class="uk-form-label uk-margin-top">' . $this->_('Success message') . '</label><textarea class="uk-textarea" name="success_message" rows="4">' . $this->e($form['success_message']) . '</textarea></div>';
		if (!empty($form['name'])) $out .= '<div class="uk-card uk-card-muted uk-card-body"><h2>' . $this->_('Embed') . '</h2><label class="uk-form-label">' . $this->_('Rich text shortcode') . '</label><code class="TicketsEmbedCode">[[tickets-form:' . $this->e($form['name']) . ']]</code><label class="uk-form-label uk-margin-top">PHP</label><code class="TicketsEmbedCode">$modules-&gt;get(\'Tickets\')-&gt;renderFormEmbed(\'' . $this->e($form['name']) . '\');</code></div>';
		$out .= '<div class="TicketsFormSave"><button class="uk-button uk-button-primary uk-width-1-1" type="submit"><i class="fa fa-save uk-margin-small-right"></i>' . $this->_('Save form') . '</button></div></aside></form>';
		if (!empty($form['id'])) $out .= '<form class="TicketsFormDelete" method="post" onsubmit="return confirm(\'' . $this->_('Delete this form? Existing tickets will be retained.') . '\')">' . $this->csrf() . '<input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="' . (int)$form['id'] . '"><button class="uk-button uk-button-text uk-text-danger" type="submit">' . $this->_('Delete form') . '</button></form>';
		return $this->workspace($out);
	}

	private function formFieldRow(array $field, array $types): string {
		$options = implode("\n", (array)($field['options'] ?? []));
		$out = '<article class="TicketsFormField" data-tickets-field><header><span class="TicketsFormField-handle"><i class="fa fa-bars"></i></span><strong data-tickets-field-title>' . $this->e($field['label'] ?: $this->_('Untitled field')) . '</strong><span class="TicketsFormField-actions"><button type="button" data-tickets-field-up aria-label="Move up"><i class="fa fa-arrow-up"></i></button><button type="button" data-tickets-field-down aria-label="Move down"><i class="fa fa-arrow-down"></i></button><button type="button" data-tickets-remove-field aria-label="Remove"><i class="fa fa-trash"></i></button></span></header><div class="TicketsFormField-grid"><label><span class="uk-form-label">' . $this->_('Label') . '</span><input class="uk-input" data-field="label" value="' . $this->e($field['label'] ?? '') . '"></label><label><span class="uk-form-label">' . $this->_('Field name') . '</span><input class="uk-input" data-field="name" value="' . $this->e($field['name'] ?? '') . '"></label><label><span class="uk-form-label">' . $this->_('Type') . '</span><select class="uk-select" data-field="type">';
		foreach ($types as $value => $label) $out .= '<option value="' . $this->e($value) . '"' . (($field['type'] ?? 'text') === $value ? ' selected' : '') . '>' . $this->e($label) . '</option>';
		$out .= '</select></label><label><span class="uk-form-label">' . $this->_('Width') . '</span><select class="uk-select" data-field="width"><option value="full"' . (($field['width'] ?? '') !== 'half' ? ' selected' : '') . '>' . $this->_('Full') . '</option><option value="half"' . (($field['width'] ?? '') === 'half' ? ' selected' : '') . '>' . $this->_('Half') . '</option></select></label><label><span class="uk-form-label">' . $this->_('Minimum length') . '</span><input class="uk-input" type="number" min="0" max="20000" data-field="min_length" value="' . (int)($field['min_length'] ?? 0) . '"></label><label><span class="uk-form-label">' . $this->_('Maximum length') . '</span><input class="uk-input" type="number" min="0" max="20000" data-field="max_length" value="' . (int)($field['max_length'] ?? (($field['type'] ?? '') === 'textarea' ? 10000 : 1000)) . '"></label><label><span class="uk-form-label">' . $this->_('Placeholder') . '</span><input class="uk-input" data-field="placeholder" value="' . $this->e($field['placeholder'] ?? '') . '"></label><label><span class="uk-form-label">' . $this->_('Help text') . '</span><input class="uk-input" data-field="help" value="' . $this->e($field['help'] ?? '') . '"></label><label class="TicketsFormField-options"><span class="uk-form-label">' . $this->_('Options, one per line') . '</span><textarea class="uk-textarea" data-field="options" rows="3">' . $this->e($options) . '</textarea></label><label class="TicketsFormField-required"><input class="uk-checkbox" type="checkbox" data-field="required"' . (!empty($field['required']) ? ' checked' : '') . '> ' . $this->_('Required') . '</label></div></article>';
		return $out;
	}

	private function renderMailEditor(string $name, string $id, string $value): string {
		$editor = $this->wire('modules')->get('InputfieldTinyMCE');
		if (!$editor) return '<textarea class="uk-textarea uk-font-monospace" id="' . $this->e($id) . '" name="' . $this->e($name) . '" rows="14" required>' . $this->e($value) . '</textarea>';
		$settings = [
			'height' => 360,
			'resize' => true,
			'plugins' => 'anchor code link lists table',
			'toolbar' => 'undo redo | blocks | bold italic | link blockquote | bullist numlist | table hr | removeformat | code',
			'menubar' => 'edit view insert format table tools',
			'contextmenu' => 'link unlink lists table removeformat',
		];
		$editor->attr('name', $name);
		$editor->attr('id', $id);
		$editor->val($value);
		$editor->height = 360;
		$editor->features = ['toolbar', 'menubar', 'statusbar', 'stickybars', 'purifier', 'pasteFilter'];
		$editor->settingsJSON = json_encode($settings);
		$editor->renderReady();
		$rendered = $editor->render();
		$wrapAttributes = '';
		foreach ($editor->wrapAttr() as $attribute => $attributeValue) {
			if (!in_array($attribute, ['data-settings', 'data-features', 'data-configName'], true) && !str_starts_with($attribute, 'data-upload')) continue;
			$wrapAttributes .= ' ' . $attribute . '="' . $this->e($attributeValue) . '"';
		}
		return '<div id="wrap_' . $this->e($id) . '" class="Inputfield InputfieldTinyMCE TicketsTemplateTinyMCE"' . $wrapAttributes . '>' . $rendered . '</div>';
	}

	private function adminNav(string $active): string {
		$base = $this->wire('page')->url;
		$settings = $this->wire('config')->urls->admin . 'module/edit?name=Tickets&collapse_info=1';
		$items = ['queue' => [$base, $this->_('Queue')], 'tickets' => [$base . 'tickets/', $this->_('Tickets')], 'reports' => [$base . 'reports/', $this->_('Reports')], 'forms' => [$base . 'forms/', $this->_('Forms')], 'automation' => [$base . 'automation/', $this->_('Automation')], 'templates' => [$base . 'templates/', $this->_('Templates')], 'interfaces' => [$base . 'interfaces/', $this->_('Interfaces')]];
		$out = '<div class="TicketsAdminNavigation uk-margin-medium-bottom uk-flex uk-flex-top"><div class="uk-width-expand"><ul class="uk-subnav uk-subnav-pill TicketsAdmin-nav" aria-label="Ticket module sections">';
		foreach ($items as $key => [$url, $label]) $out .= '<li' . (($active === $key || ($active === 'view' && $key === 'queue')) ? ' class="uk-active"' : '') . '><a href="' . $this->e($url) . '">' . $this->e($label) . '</a></li>';
		return $out . '</ul></div><div class="uk-width-auto"><a class="TicketsAdminSettings uk-link-muted uk-display-inline-flex uk-flex-middle" href="' . $this->e($settings) . '" title="' . $this->_('Tickets settings') . '" aria-label="' . $this->_('Tickets settings') . '">' . $this->settingsIcon() . '</a></div></div>';
	}

	private function pageIntro(string $eyebrow, string $title, string $description, string $actions = ''): string {
		return '<section class="TicketsPageIntro" aria-label="' . $this->e($title) . '"><div class="TicketsPageIntro-copy"><p class="TicketsPageIntro-eyebrow">' . $this->e($eyebrow) . '</p><p>' . $this->e($description) . '</p></div>' . ($actions !== '' ? '<div class="TicketsPageIntro-actions">' . $actions . '</div>' : '') . '</section>';
	}

	private function metricIcon(string $name): string {
		$paths = [
			'inbox' => '<path d="M4 6.25A2.25 2.25 0 0 1 6.25 4h11.5A2.25 2.25 0 0 1 20 6.25v11.5A2.25 2.25 0 0 1 17.75 20H6.25A2.25 2.25 0 0 1 4 17.75z"/><path d="M4 14h4l1.5 2h5l1.5-2h4"/>',
			'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M4 9h16M8 13h2m4 0h2m-8 4h2m4 0h2"/>',
			'reply' => '<path d="m9 7-5 5 5 5"/><path d="M5 12h7a7 7 0 0 1 7 7"/>',
			'resolved' => '<circle cx="12" cy="12" r="8"/><path d="m8.5 12 2.25 2.25L15.75 9"/>',
			'alert' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v5m0 3h.01"/>',
			'conversation' => '<path d="M5 17.5 3.5 20v-5A7 7 0 1 1 7 17.5z"/><path d="M9 9h6m-6 3h4"/>',
			'timer' => '<circle cx="12" cy="13" r="7"/><path d="M12 10v4l2.5 1.5M9 3h6m-3 3V3"/>',
			'rating' => '<path d="m12 3 2.65 5.37 5.93.86-4.29 4.18 1.01 5.91L12 16.53l-5.3 2.79 1.01-5.91-4.29-4.18 5.93-.86z"/>',
		];
		$path = $paths[$name] ?? $paths['inbox'];
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
	}

	private function settingsIcon(): string {
		return '<svg data-slot="icon" aria-hidden="true" fill="none" stroke-width="1.5" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" stroke-linecap="round" stroke-linejoin="round"></path><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
	}

	private function pagination(int $page, int $pages): string {
		if ($pages <= 1) return '';
		$params = $this->wire('input')->get->getArray();
		$out = '<nav class="TicketsPagination" aria-label="' . $this->_('Ticket pages') . '">';
		for ($number = 1; $number <= $pages; $number++) {
			if ($pages > 9 && abs($number - $page) > 2 && !in_array($number, [1, $pages], true)) continue;
			$params['page'] = $number;
			$out .= '<a href="?' . $this->e(http_build_query($params)) . '"' . ($number === $page ? ' aria-current="page"' : '') . '>' . $number . '</a>';
		}
		return $out . '</nav>';
	}

	private function reportTable(array $rows, string $dimension): string {
		$tickets = $this->tickets();
		$out = '<div class="uk-overflow-auto"><table class="uk-table uk-table-divider"><thead><tr><th>' . $this->_('Name') . '</th><th>' . $this->_('Total') . '</th><th>' . $this->_('Completed') . '</th><th>' . $this->_('SLA') . '</th>' . ($dimension === 'agent' ? '<th>' . $this->_('First response') . '</th><th>' . $this->_('Resolution') . '</th>' : '') . '<th>' . $this->_('Rating') . '</th></tr></thead><tbody>';
		foreach ($rows as $row) {
			if ($dimension === 'agent') {
				$user = (int)$row['assigned_user_id'] ? $this->wire('users')->get((int)$row['assigned_user_id']) : null;
				$name = $user && $user->id ? (string)($user->get('display_name') ?: $user->name) : $this->_('Unassigned');
			} else $name = (string)($tickets->types()[$row['category']] ?? $row['category']);
			$rating = !empty($row['rating']) ? number_format((float)$row['rating'], 1) . '/5 (' . (int)($row['rating_count'] ?? 0) . ')' : $this->_('No data');
			$out .= '<tr><td>' . $this->e($name) . '</td><td>' . (int)$row['total'] . '</td><td>' . (int)$row['completed'] . '</td><td>' . (int)$row['breached'] . '</td>' . ($dimension === 'agent' ? '<td>' . $this->e($this->duration($row['first_response_minutes'] ?? null)) . '</td><td>' . $this->e($this->duration($row['resolution_minutes'] ?? null)) . '</td>' : '') . '<td>' . $this->e($rating) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	private function reportTrendChart(array $rows): string {
		$max = 1;
		foreach ($rows as $row) $max = max($max, (int)($row['created'] ?? 0), (int)($row['completed'] ?? 0));
		if (!$rows) return '<p class="TicketsEmptyChart">' . $this->_('No ticket activity in this period.') . '</p>';
		$out = '<div class="TicketsReportBars" role="img" aria-label="' . $this->_('Daily created and completed ticket volume') . '">';
		foreach ($rows as $row) {
			$created = (int)($row['created'] ?? 0);
			$completed = (int)($row['completed'] ?? 0);
			$out .= '<span class="TicketsReportBars-day" title="' . $this->e(date('M j', strtotime((string)$row['day'])) . ': ' . $created . ' created, ' . $completed . ' completed') . '"><i data-series="created" style="--tickets-bar:' . max(2, (int)round(($created / $max) * 100)) . '%"></i><i data-series="completed" style="--tickets-bar:' . max(2, (int)round(($completed / $max) * 100)) . '%"></i><small>' . $this->e(date('j', strtotime((string)$row['day']))) . '</small></span>';
		}
		return $out . '</div>';
	}

	private function workspace(string $content): string {
		return '<div class="pw-wrap pw-module-workspace TicketsAdmin">' . $content . '</div>';
	}

	private function queueSlaCell(Tickets $tickets, array $ticket): string {
		if (in_array((string)($ticket['status'] ?? ''), ['resolved', 'closed'], true)) {
			return '<span class="TicketsQueueSla" data-state="complete"><strong>' . $this->_('Complete') . '</strong></span>';
		}
		$sla = $tickets->slaState($ticket);
		if (empty($sla['due_at']) || $sla['remaining_seconds'] === null) {
			return '<span class="TicketsQueueSla" data-state="unset"><strong>' . $this->_('Not set') . '</strong></span>';
		}
		$remaining = (int)$sla['remaining_seconds'];
		$duration = $this->duration(abs($remaining) / 60);
		if (!empty($sla['breached'])) {
			return '<span class="TicketsQueueSla" data-state="breached"><strong>' . $this->_('Breached') . '</strong><small>' . $this->e(sprintf($this->_('%s overdue'), $duration)) . '</small></span>';
		}
		$phase = $sla['phase'] === 'first_response' ? $this->_('First response') : $this->_('Resolution');
		return '<span class="TicketsQueueSla" data-state="due"><strong>' . $this->e($phase) . '</strong><small>' . $this->e(sprintf($this->_('Due in %s'), $duration)) . '</small></span>';
	}

	private function duration($minutes): string {
		if ($minutes === null || $minutes === false) return $this->_('No data');
		$minutes = max(0, (int)round((float)$minutes));
		if ($minutes < 60) return sprintf($this->_('%d min'), $minutes);
		if ($minutes < 1440) return sprintf($this->_('%d h %d min'), intdiv($minutes, 60), $minutes % 60);
		return sprintf($this->_('%d d %d h'), intdiv($minutes, 1440), intdiv($minutes % 1440, 60));
	}

	private function interfaceNav(string $active): string {
		$base = $this->wire('page')->url;
		$items = [
			'overview' => [$base . 'interfaces/', $this->_('Overview')],
			'api' => [$base . 'api/', $this->_('API')],
			'cli' => [$base . 'cli/', $this->_('CLI')],
		];
		$out = '<nav class="TicketsInterfaceNav" aria-label="' . $this->_('Interface sections') . '"><ul class="uk-subnav uk-subnav-pill">';
		foreach ($items as $key => [$url, $label]) $out .= '<li' . ($key === $active ? ' class="uk-active"' : '') . '><a href="' . $this->e($url) . '"' . ($key === $active ? ' aria-current="page"' : '') . '>' . $this->e($label) . '</a></li>';
		return $out . '</ul></nav>';
	}

	private function interfaceState(string $label, bool $enabled): string {
		$state = $enabled ? 'enabled' : 'disabled';
		return '<span class="TicketsInterfaceState" data-state="' . $state . '"><i></i>' . $this->e($label) . ' <strong>' . ($enabled ? $this->_('Enabled') : $this->_('Disabled')) . '</strong></span>';
	}

	private function interfaceOverviewCard(string $icon, string $eyebrow, string $title, string $description, string $url, bool $enabled): string {
		return '<article class="TicketsInterfaceCard"><header><span class="TicketsInterfaceIcon"><i class="fa fa-' . $this->e($icon) . '" aria-hidden="true"></i></span><div><p>' . $this->e($eyebrow) . '</p><h2>' . $this->e($title) . '</h2></div></header><p>' . $this->e($description) . '</p><footer>' . $this->interfaceState($this->_('Status'), $enabled) . '<a class="uk-button uk-button-default" href="' . $this->e($url) . '">' . $this->_('Open') . '</a></footer></article>';
	}

	private function interfaceSettingsUrl(): string {
		return $this->wire('config')->urls->admin . 'module/edit?name=Tickets&collapse_info=1#Inputfield_enable_agent_api';
	}

	private function apiRoutes(): array {
		return [
			['GET', 'session/', 'Session', $this->_('Session capabilities and mutation CSRF token.')],
			['GET', 'capabilities/', 'Session or Bearer', $this->_('API version, channels, and supported capabilities.')],
			['GET', 'dashboard/', 'Session or Bearer', $this->_('Operational queue summary.')],
			['GET', 'queue/?scope=active&limit=25', 'Session or Bearer', $this->_('Bounded filtered ticket queue.')],
			['GET', 'ticket/?id={id}', 'Session or Bearer', $this->_('One redacted ticket record.')],
			['GET', 'messages/?id={id}', 'Session or Bearer', $this->_('Redacted public and internal conversation for staff.')],
			['GET', 'report/?days=30', 'Admin Session or Bearer', $this->_('Bounded operational report.')],
			['GET', 'forms/', 'Admin Session or Bearer', $this->_('Administrative form definitions.')],
			['POST', 'update/', 'Session CSRF or Bearer', $this->_('Update status, priority, or assignment.')],
			['POST', 'reply/', 'Session CSRF or Bearer', $this->_('Add a staff reply.')],
			['POST', 'note/', 'Session CSRF or Bearer', $this->_('Add an internal note.')],
		];
	}

	private function cliCommands(): array {
		return [
			['read', 'capabilities', $this->_('Show channel status and capabilities.')],
			['read', 'dashboard', $this->_('Show queue and response summary.')],
			['read', 'queue --scope=active --limit=25', $this->_('Read a bounded queue page.')],
			['read', 'ticket --id=123', $this->_('Read one redacted ticket.')],
			['read', 'messages --id=123', $this->_('Read one ticket conversation.')],
			['read', 'report --days=30', $this->_('Read a bounded operational report.')],
			['read', 'forms', $this->_('Read administrative form definitions.')],
			['write', 'update --id=123 --status=resolved --execute', $this->_('Update ticket workflow.')],
			['write', 'reply --id=123 --stdin --execute', $this->_('Add a staff reply from JSON stdin.')],
			['write', 'note --id=123 --stdin --execute', $this->_('Add an internal note from JSON stdin.')],
			['preview', 'automation --dry-run', $this->_('Preview SLA and auto-close automation.')],
			['write', 'automation --execute', $this->_('Run bounded automation.')],
			['preview', 'retention --dry-run', $this->_('Preview configured retention.')],
			['write', 'retention --execute', $this->_('Run configured retention.')],
			['preview', 'mailbox-import --limit=25', $this->_('Preview bounded Mailbox ingestion.')],
			['write', 'mailbox-import --limit=25 --execute', $this->_('Import a bounded Mailbox batch.')],
		];
	}

	private function renderBearerTokenPanel(Tickets $tickets): string {
		$options = '';
		foreach ($this->wire('users')->find('include=all, sort=name, limit=200') as $candidate) {
			$allowed = $candidate->isSuperuser() || ($candidate->hasPermission(Tickets::PERMISSION_API) && $candidate->hasPermission(Tickets::PERMISSION_MANAGE));
			if (!$candidate->id || !$allowed) continue;
			$options .= '<option value="' . (int)$candidate->id . '"' . ((int)$tickets->rest_bearer_user_id === (int)$candidate->id ? ' selected' : '') . '>' . $this->e((string)$candidate->name) . '</option>';
		}
		$out = '<section class="TicketsTokenPanel"><header><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">Bearer</p><h2>' . $this->_('Token credential') . '</h2></div>' . $this->interfaceState($this->_('Token'), (string)$tickets->rest_bearer_token_hash !== '') . '</header><p>' . $this->_('Generate a scoped token for one ProcessWire actor with tickets-api and tickets-manage. Rotating immediately invalidates the previous token.') . '</p>';
		if ($options === '') return $out . '<div class="uk-alert-warning" uk-alert><p>' . $this->_('No eligible API actor exists. Grant tickets-api and tickets-manage to a dedicated user first.') . '</p></div></section>';
		$out .= '<div class="TicketsTokenActions"><form method="post"><input type="hidden" name="interface_action" value="rotate_bearer">' . $this->csrf() . '<label><span class="uk-form-label">' . $this->_('Token actor') . '</span><select class="uk-select" name="bearer_user_id" required>' . $options . '</select></label><button class="uk-button uk-button-primary" type="submit">' . ((string)$tickets->rest_bearer_token_hash !== '' ? $this->_('Rotate token') : $this->_('Generate token')) . '</button></form>';
		if ((string)$tickets->rest_bearer_token_hash !== '') $out .= '<form method="post" onsubmit="return confirm(\'' . $this->_('Revoke the current Bearer token? API clients using it will stop immediately.') . '\')"><input type="hidden" name="interface_action" value="revoke_bearer">' . $this->csrf() . '<button class="uk-button uk-button-danger" type="submit">' . $this->_('Revoke token') . '</button></form>';
		return $out . '</div></section>';
	}

	private function handleBearerTokenAction(Tickets $tickets): void {
		$this->wire('session')->CSRF->validate();
		$action = (string)$this->wire('input')->post('interface_action');
		$config = (array)$this->wire('modules')->getConfig('Tickets');
		if ($action === 'rotate_bearer') {
			$actorId = (int)$this->wire('input')->post('bearer_user_id');
			$actor = $this->wire('users')->get($actorId);
			$allowed = $actor instanceof User && $actor->id && ($actor->isSuperuser() || ($actor->hasPermission(Tickets::PERMISSION_API) && $actor->hasPermission(Tickets::PERMISSION_MANAGE)));
			if (!$allowed) throw new WirePermissionException('The selected Bearer actor is not eligible.');
			$token = 'tickets_' . Tickets::REST_API_VERSION . '_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
			$config['rest_bearer_token_hash'] = hash('sha256', $token);
			$config['rest_bearer_user_id'] = $actorId;
			$config['rest_bearer_token_created_at'] = date('Y-m-d H:i:s');
			$this->wire('modules')->saveConfig('Tickets', $config);
			$this->wire('session')->set('tickets_bearer_token_once', $token);
			$this->message($this->_('Bearer token rotated. Copy it from the next screen.'));
		} elseif ($action === 'revoke_bearer') {
			$config['rest_bearer_token_hash'] = '';
			$config['rest_bearer_user_id'] = 0;
			$config['rest_bearer_token_created_at'] = '';
			$this->wire('modules')->saveConfig('Tickets', $config);
			$this->wire('session')->remove('tickets_bearer_token_once');
			$this->message($this->_('Bearer token revoked.'));
		} else {
			throw new WireException('Unsupported interface action.');
		}
		$this->wire('session')->redirect($this->wire('page')->url . 'api/');
	}

	private function age(string $date): string {
		$seconds = max(0, time() - (int)strtotime($date));
		if ($seconds < 60) return $this->_('Just now');
		if ($seconds < 3600) return sprintf($this->_('%d min ago'), intdiv($seconds, 60));
		if ($seconds < 86400) return sprintf($this->_('%d h ago'), intdiv($seconds, 3600));
		return sprintf($this->_('%d d ago'), intdiv($seconds, 86400));
	}

	private function dateTime(string $date): string {
		$timestamp = strtotime($date);
		return $timestamp ? date('M j, Y · H:i', $timestamp) : $this->_('Unknown');
	}

	private function trendChart(array $trend): string {
		$max = max(1, ...array_values($trend));
		$out = '<div class="TicketsTrendChart" role="img" aria-label="' . $this->_('New ticket volume for the last 14 days') . '">';
		foreach ($trend as $day => $amount) {
			$height = max(4, (int)round(((int)$amount / $max) * 100));
			$out .= '<span title="' . $this->e(date('M j', strtotime($day)) . ': ' . (int)$amount) . '"><i style="--tickets-bar:' . $height . '%"></i><small>' . $this->e(date('j', strtotime($day))) . '</small></span>';
		}
		return $out . '</div>';
	}

	private function breakdownPanel(string $title, array $counts, array $labels, string $filter): string {
		$total = max(1, array_sum($counts));
		$out = '<article class="TicketsPanel TicketsBreakdown"><header><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">' . $this->_('Distribution') . '</p><h3>' . $this->e($title) . '</h3></div><strong>' . array_sum($counts) . '</strong></header><div class="TicketsBreakdownList">';
		$shown = 0;
		foreach ($counts as $key => $amount) {
			if ((int)$amount === 0 && $shown >= 3) continue;
			$width = (int)round(((int)$amount / $total) * 100);
			$url = $this->wire('page')->url . 'tickets/?' . rawurlencode($filter) . '=' . rawurlencode((string)$key);
			$out .= '<a href="' . $this->e($url) . '"><span><strong>' . $this->e($labels[$key] ?? $key) . '</strong><small>' . (int)$amount . '</small></span><i><b style="--tickets-share:' . $width . '%"></b></i></a>';
			if (++$shown >= 5) break;
		}
		return $out . '</div></article>';
	}

	private function staffOptions(): array {
		$options = ['0' => $this->_('Unassigned')];
		foreach ($this->wire('users')->find('include=all, sort=name, limit=200') as $candidate) {
			if (!$candidate->isSuperuser() && !$candidate->hasPermission(Tickets::PERMISSION_MANAGE)) continue;
			$label = trim((string)($candidate->get('display_name') ?: $candidate->name));
			if ($label !== '') $options[(string)(int)$candidate->id] = $label;
		}
		return $options;
	}

	private function filterSelect(string $name, string $label, string $placeholder, array $options, string $selected): string {
		$out = '<label class="TicketsFilterField"><span class="uk-form-label">' . $this->e($label) . '</span><select class="uk-select" name="' . $this->e($name) . '"><option value="">' . $this->e($placeholder) . '</option>';
		foreach ($options as $value => $text) $out .= '<option value="' . $this->e($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . $this->e($text) . '</option>';
		return $out . '</select></label>';
	}

	private function filterInput(string $name, string $label, string $type, string $value, string $placeholder = ''): string {
		return '<label class="TicketsFilterField"><span class="uk-form-label">' . $this->e($label) . '</span><input class="uk-input" type="' . $this->e($type) . '" name="' . $this->e($name) . '" value="' . $this->e($value) . '"' . ($placeholder !== '' ? ' placeholder="' . $this->e($placeholder) . '"' : '') . '></label>';
	}

	private function automationSelect(string $prefix, string $name, string $label, array $options, string $selected): string {
		$id = $prefix . '-' . str_replace('_', '-', $name);
		$out = '<label class="TicketsAutomationField" for="' . $this->e($id) . '"><span class="uk-form-label">' . $this->e($label) . '</span><select class="uk-select" id="' . $this->e($id) . '" name="' . $this->e($name) . '">';
		foreach ($options as $value => $text) $out .= '<option value="' . $this->e($value) . '"' . ($selected === (string)$value ? ' selected' : '') . '>' . $this->e($text) . '</option>';
		return $out . '</select></label>';
	}

	private function automationInput(string $prefix, string $name, string $label, string $type, string $value, array $options = []): string {
		$id = $prefix . '-' . str_replace('_', '-', $name);
		$attributes = '';
		foreach (['min', 'max', 'maxlength', 'placeholder'] as $attribute) {
			if (!array_key_exists($attribute, $options)) continue;
			$attributes .= ' ' . $attribute . '="' . $this->e($options[$attribute]) . '"';
		}
		if (!empty($options['required'])) $attributes .= ' required';
		$out = '<label class="TicketsAutomationField" for="' . $this->e($id) . '"><span class="uk-form-label">' . $this->e($label) . '</span><span class="TicketsAutomationInput"><input class="uk-input" id="' . $this->e($id) . '" type="' . $this->e($type) . '" name="' . $this->e($name) . '" value="' . $this->e($value) . '"' . $attributes . '>';
		if (!empty($options['suffix'])) $out .= '<span>' . $this->e($options['suffix']) . '</span>';
		$out .= '</span>';
		if (!empty($options['help'])) $out .= '<small>' . $this->e($options['help']) . '</small>';
		return $out . '</label>';
	}

	private function select(string $name, string $label, array $options, string $selected): string {
		$out = '<label class="uk-form-label" for="' . $this->e($name) . '">' . $this->e($label) . '</label><select class="uk-select" id="' . $this->e($name) . '" name="' . $this->e($name) . '">';
		foreach ($options as $value => $text) $out .= '<option value="' . $this->e($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . $this->e($text) . '</option>';
		return $out . '</select>';
	}

	private function csrf(): string {
		$csrf = $this->wire('session')->CSRF;
		return '<input type="hidden" name="' . $this->e($csrf->getTokenName()) . '" value="' . $this->e($csrf->getTokenValue()) . '">';
	}

	private function jsonResponse(array $payload, int $status = 200): never {
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store, private');
		echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		exit;
	}

	private function e($value): string {
		return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
