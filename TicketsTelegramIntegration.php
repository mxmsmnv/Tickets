<?php namespace ProcessWire;

/** Independent, opt-in Telegram Bot API notifications for administrators. */
trait TicketsTelegramIntegration {

	public function telegramIntegrationStatus(): array {
		$token = $this->telegramBotToken();
		$chatIds = $this->telegramChatIds();
		$configured = $this->telegramTokenIsValid($token) && $chatIds !== [];
		return [
			'configured' => $configured,
			'enabled' => (bool)$this->telegram_notifications_enabled,
			'ready' => (bool)$this->telegram_notifications_enabled && $configured,
			'recipient_count' => count($chatIds),
			'credential_source' => $this->telegramCredentialSource(),
			'events' => $this->telegramNotificationEvents(),
		];
	}

	public function telegramNotificationEvents(): array {
		$allowed = ['new_ticket', 'customer_reply', 'sla_breach'];
		return array_values(array_intersect($allowed, array_map('strval', (array)$this->telegram_notification_events)));
	}

	protected function addTelegramIntegrationInputfields(InputfieldWrapper $interfaces): void {
		$status = $this->telegramIntegrationStatus();

		$enabled = $this->wire('modules')->get('InputfieldCheckbox');
		$enabled->name = 'telegram_notifications_enabled';
		$enabled->label = $this->_('Enable Telegram administrator notifications');
		$enabled->description = $this->_('Independent Tickets delivery through the Telegram Bot API. Delivery failures never block ticket creation, replies, or SLA automation.');
		$enabled->checked = (bool)$this->telegram_notifications_enabled;
		$interfaces->add($enabled);

		$token = $this->wire('modules')->get('InputfieldText');
		$token->name = 'telegram_bot_token';
		$token->label = $this->_('Telegram bot token');
		$token->description = $this->_('Create a bot with @BotFather. A private runtime override takes precedence over this saved value.');
		$token->notes = $status['credential_source'] === 'runtime'
			? $this->_('A runtime credential is active; the saved token is not used.')
			: $this->_('Stored in ProcessWire module configuration. Never commit this value to source control.');
		$token->attr('type', 'password');
		$token->attr('autocomplete', 'new-password');
		$token->value = (string)$this->telegram_bot_token;
		$token->showIf = 'telegram_notifications_enabled=1';
		$token->columnWidth = 50;
		$interfaces->add($token);

		$chatIds = $this->wire('modules')->get('InputfieldTextarea');
		$chatIds->name = 'telegram_chat_ids';
		$chatIds->label = $this->_('Administrator chat IDs');
		$chatIds->description = $this->_('One numeric chat ID or @channel username per line. The bot must already have permission to send there.');
		$chatIds->value = (string)$this->telegram_chat_ids;
		$chatIds->showIf = 'telegram_notifications_enabled=1';
		$chatIds->columnWidth = 50;
		$interfaces->add($chatIds);

		$events = $this->wire('modules')->get('InputfieldCheckboxes');
		$events->name = 'telegram_notification_events';
		$events->label = $this->_('Notify administrators when');
		$events->addOptions([
			'new_ticket' => $this->_('A new ticket is created'),
			'customer_reply' => $this->_('A customer replies'),
			'sla_breach' => $this->_('An SLA target is missed'),
		]);
		$events->value = $this->telegramNotificationEvents();
		$events->showIf = 'telegram_notifications_enabled=1';
		$events->columnWidth = 70;
		$interfaces->add($events);

		$timeout = $this->wire('modules')->get('InputfieldInteger');
		$timeout->name = 'telegram_timeout_seconds';
		$timeout->label = $this->_('Delivery timeout');
		$timeout->description = $this->_('Total Telegram request timeout in seconds.');
		$timeout->attr('min', 3);
		$timeout->attr('max', 30);
		$timeout->value = max(3, min(30, (int)$this->telegram_timeout_seconds));
		$timeout->showIf = 'telegram_notifications_enabled=1';
		$timeout->columnWidth = 30;
		$interfaces->add($timeout);

		$privacy = $this->wire('modules')->get('InputfieldMarkup');
		$privacy->label = $this->_('Telegram payload');
		$privacy->value = '<p>' . $this->_('Telegram receives only the event, ticket key, subject, priority, workflow status, and an authenticated admin link. Customer email, message text, guest access tokens, custom fields, and attachments are excluded.') . '</p>';
		$privacy->showIf = 'telegram_notifications_enabled=1';
		$interfaces->add($privacy);
	}

	private function sendTelegramTicketNotification(string $event, array $ticket): bool {
		if (!(bool)$this->telegram_notifications_enabled || !in_array($event, $this->telegramNotificationEvents(), true)) return false;
		$status = $this->telegramIntegrationStatus();
		if (!$status['ready']) {
			$this->wire('log')->save('tickets', 'Telegram notification skipped: interface is not ready (' . $event . ').');
			return false;
		}

		$sent = 0;
		$chatIds = $this->telegramChatIds();
		try {
			foreach ($chatIds as $chatId) {
				if ($this->telegramApiRequest($this->telegramBotToken(), [
					'chat_id' => $chatId,
					'text' => $this->buildTelegramNotification($event, $ticket),
					'parse_mode' => 'HTML',
					'disable_web_page_preview' => 'true',
				])) $sent++;
			}
		} catch (\Throwable $error) {
			$this->wire('log')->save('tickets', 'Telegram notification failed (' . $event . ', ' . get_class($error) . ').');
			return false;
		}
		if ($sent !== count($chatIds)) $this->wire('log')->save('tickets', 'Telegram notification delivery incomplete (' . $event . ', ' . $sent . '/' . count($chatIds) . ').');
		return $sent === count($chatIds);
	}

	private function telegramApiRequest(string $token, array $payload): bool {
		if (!$this->telegramTokenIsValid($token) || !function_exists('curl_init')) return false;
		$timeout = max(3, min(30, (int)$this->telegram_timeout_seconds));
		$handle = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
		if ($handle === false) return false;
		try {
			curl_setopt_array($handle, [
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
				CURLOPT_TIMEOUT => $timeout,
				CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
				CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
			]);
			$response = curl_exec($handle);
			$httpCode = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
			$body = is_string($response) ? json_decode($response, true) : null;
			return $httpCode >= 200 && $httpCode < 300 && is_array($body) && !empty($body['ok']);
		} finally {
			curl_close($handle);
		}
	}

	private function buildTelegramNotification(string $event, array $ticket): string {
		$labels = [
			'new_ticket' => ['🆕', $this->_('New support ticket')],
			'customer_reply' => ['💬', $this->_('Customer reply')],
			'sla_breach' => ['⚠️', $this->_('SLA target missed')],
		];
		if (!isset($labels[$event])) throw new WireException('Unsupported Telegram notification event.');
		[$icon, $label] = $labels[$event];
		$priority = (string)($this->priorities()[(string)($ticket['priority'] ?? '')] ?? ($ticket['priority'] ?? ''));
		$status = (string)($this->statuses()[(string)($ticket['status'] ?? '')] ?? ($ticket['status'] ?? ''));
		$url = $this->notificationTicketUrl($ticket, true);
		$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
		return $icon . ' <b>' . $escape((string)$label) . "</b>\n\n"
			. '<b>#' . $escape((string)($ticket['public_key'] ?? '')) . '</b> · ' . $escape(mb_substr((string)($ticket['subject'] ?? ''), 0, 180)) . "\n"
			. $escape((string)$this->_('Priority')) . ': ' . $escape($priority) . "\n"
			. $escape((string)$this->_('Status')) . ': ' . $escape($status) . "\n\n"
			. '<a href="' . $escape($url) . '">' . $escape((string)$this->_('Open ticket in admin')) . '</a>';
	}

	private function telegramBotToken(): string {
		$config = $this->wire('config');
		$runtime = trim((string)($config->ticketsTelegramBotToken ?? ''));
		if ($runtime === '') $runtime = trim((string)getenv('TICKETS_TELEGRAM_BOT_TOKEN'));
		return $runtime !== '' ? $runtime : trim((string)$this->telegram_bot_token);
	}

	private function telegramChatIds(): array {
		$config = $this->wire('config');
		$raw = trim((string)($config->ticketsTelegramChatIds ?? ''));
		if ($raw === '') $raw = trim((string)getenv('TICKETS_TELEGRAM_CHAT_IDS'));
		if ($raw === '') $raw = trim((string)$this->telegram_chat_ids);
		$values = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$valid = array_filter(array_map('strval', $values), static fn(string $value): bool => (bool)preg_match('/^(?:-?\d+|@[A-Za-z][A-Za-z0-9_]{4,31})$/D', $value));
		return array_slice(array_values(array_unique($valid)), 0, 20);
	}

	private function telegramCredentialSource(): string {
		$config = $this->wire('config');
		if (trim((string)($config->ticketsTelegramBotToken ?? '')) !== '' || trim((string)getenv('TICKETS_TELEGRAM_BOT_TOKEN')) !== '') return 'runtime';
		return trim((string)$this->telegram_bot_token) !== '' ? 'module' : 'none';
	}

	private function telegramTokenIsValid(string $token): bool {
		return (bool)preg_match('/^\d{6,}:[A-Za-z0-9_-]{20,}$/D', $token);
	}
}
