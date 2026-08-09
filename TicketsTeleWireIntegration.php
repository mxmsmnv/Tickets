<?php namespace ProcessWire;

/** Optional administrator notifications through mxmsmnv/TeleWire. */
trait TicketsTeleWireIntegration {

	public function telewireIntegrationStatus(): array {
		$modules = $this->wire('modules');
		$installed = $modules->isInstalled('TeleWire');
		$telewire = $installed ? $modules->get('TeleWire') : null;
		$compatible = is_object($telewire) && method_exists($telewire, 'send');
		$configured = $compatible
			&& trim((string)$telewire->get('botToken')) !== ''
			&& trim((string)$telewire->get('chatIds')) !== '';
		return [
			'installed' => $installed,
			'compatible' => $compatible,
			'configured' => $configured,
			'enabled' => (bool)$this->telewire_notifications_enabled,
			'ready' => (bool)$this->telewire_notifications_enabled && $configured,
			'events' => $this->telewireNotificationEvents(),
		];
	}

	public function telewireNotificationEvents(): array {
		$allowed = ['new_ticket', 'customer_reply', 'sla_breach'];
		return array_values(array_intersect($allowed, array_map('strval', (array)$this->telewire_notification_events)));
	}

	protected function addTeleWireIntegrationInputfields(InputfieldWrapper $inputfields): void {
		$status = $this->telewireIntegrationStatus();
		$section = $this->wire('modules')->get('InputfieldFieldset');
		$section->label = $this->_('Telegram notifications');
		$section->icon = 'telegram';
		$section->description = $this->_('Notify administrators through the independently configured mxmsmnv/TeleWire module. Tickets never stores the Telegram bot token or chat IDs.');
		$section->collapsed = Inputfield::collapsedYes;

		$state = $this->wire('modules')->get('InputfieldMarkup');
		$state->label = $this->_('TeleWire status');
		if (!$status['installed']) {
			$state->value = '<p><strong>' . $this->_('Not installed') . '</strong></p><p>' . $this->_('Install and configure mxmsmnv/TeleWire before enabling Telegram notifications.') . '</p>';
		} elseif (!$status['compatible']) {
			$state->value = '<p><strong>' . $this->_('Incompatible') . '</strong></p><p>' . $this->_('The installed TeleWire module does not expose its documented send method.') . '</p>';
		} elseif (!$status['configured']) {
			$url = $this->wire('config')->urls->admin . 'module/edit?name=TeleWire&collapse_info=1';
			$state->value = '<p><strong>' . $this->_('Needs configuration') . '</strong></p><p>' . $this->_('Add the bot token and administrator chat IDs in TeleWire settings.') . ' <a href="' . $this->h($url) . '">' . $this->_('Open TeleWire settings') . '</a></p>';
		} else {
			$state->value = '<p><strong>' . ($status['ready'] ? $this->_('Enabled and ready') : $this->_('Configured, currently disabled')) . '</strong></p><p>' . $this->_('Bot credentials and recipients remain managed by TeleWire.') . '</p>';
		}
		$section->add($state);

		$enabled = $this->wire('modules')->get('InputfieldCheckbox');
		$enabled->name = 'telewire_notifications_enabled';
		$enabled->label = $this->_('Send administrator notifications through TeleWire');
		$enabled->description = $this->_('Telegram delivery is independent of transactional email. A TeleWire failure never blocks ticket creation, replies, or SLA automation.');
		$enabled->checked = (bool)$this->telewire_notifications_enabled;
		$section->add($enabled);

		$events = $this->wire('modules')->get('InputfieldCheckboxes');
		$events->name = 'telewire_notification_events';
		$events->label = $this->_('Notify administrators when');
		$events->addOptions([
			'new_ticket' => $this->_('A new ticket is created'),
			'customer_reply' => $this->_('A customer replies'),
			'sla_breach' => $this->_('An SLA target is missed'),
		]);
		$events->value = $this->telewireNotificationEvents();
		$events->showIf = 'telewire_notifications_enabled=1';
		$section->add($events);

		$privacy = $this->wire('modules')->get('InputfieldMarkup');
		$privacy->label = $this->_('Notification data');
		$privacy->value = '<p>' . $this->_('Telegram receives the ticket key, subject, priority, workflow status, event name, and an authenticated admin link. Customer email, message text, guest access tokens, custom fields, and attachments are excluded.') . '</p>';
		$privacy->showIf = 'telewire_notifications_enabled=1';
		$section->add($privacy);
		$inputfields->add($section);
	}

	private function sendTeleWireTicketNotification(string $event, array $ticket): bool {
		if (!(bool)$this->telewire_notifications_enabled || !in_array($event, $this->telewireNotificationEvents(), true)) return false;
		$status = $this->telewireIntegrationStatus();
		if (!$status['ready']) {
			$this->wire('log')->save('tickets', 'TeleWire notification skipped: integration is not ready (' . $event . ').');
			return false;
		}
		try {
			$telewire = $this->wire('modules')->get('TeleWire');
			$sent = (bool)$telewire->send($this->buildTeleWireNotification($event, $ticket), [
				'parse_mode' => 'HTML',
				'disable_web_page_preview' => true,
			]);
			if (!$sent) $this->wire('log')->save('tickets', 'TeleWire notification failed (' . $event . ').');
			return $sent;
		} catch (\Throwable $error) {
			$this->wire('log')->save('tickets', 'TeleWire notification failed (' . $event . ', ' . get_class($error) . ').');
			return false;
		}
	}

	private function buildTeleWireNotification(string $event, array $ticket): string {
		$labels = [
			'new_ticket' => ['🆕', $this->_('New support ticket')],
			'customer_reply' => ['💬', $this->_('Customer reply')],
			'sla_breach' => ['⚠️', $this->_('SLA target missed')],
		];
		if (!isset($labels[$event])) throw new WireException('Unsupported TeleWire notification event.');
		[$icon, $label] = $labels[$event];
		$priority = (string)($this->priorities()[(string)($ticket['priority'] ?? '')] ?? ($ticket['priority'] ?? ''));
		$status = (string)($this->statuses()[(string)($ticket['status'] ?? '')] ?? ($ticket['status'] ?? ''));
		$url = $this->notificationTicketUrl($ticket, true);
		$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
		return $icon . ' <b>' . $escape((string)$label) . '</b>\n\n'
			. '<b>#' . $escape((string)($ticket['public_key'] ?? '')) . '</b> · ' . $escape(mb_substr((string)($ticket['subject'] ?? ''), 0, 180)) . "\n"
			. $escape((string)$this->_('Priority')) . ': ' . $escape($priority) . "\n"
			. $escape((string)$this->_('Status')) . ': ' . $escape($status) . "\n\n"
			. '<a href="' . $escape($url) . '">' . $escape((string)$this->_('Open ticket in admin')) . '</a>';
	}
}
