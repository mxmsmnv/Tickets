<?php namespace ProcessWire;

/** Optional Mailbox ingestion and delivery, isolated from core ticketing. */
trait TicketsMailboxIntegration {
	private bool $mailboxIntegrationHookRegistered = false;

	public function registerMailboxIntegrationHook(): void {
		if ($this->mailboxIntegrationHookRegistered || !(bool)$this->mailbox_inbound_enabled || !$this->wire('modules')->isInstalled('Mailbox')) return;
		$this->addHookAfter('Mailbox::messageIndexed', $this, 'handleMailboxIndexedMessage');
		$this->mailboxIntegrationHookRegistered = true;
	}

	public function handleMailboxIndexedMessage(HookEvent $event): void {
		try {
			$this->importMailboxNotification((array)$event->arguments(0), 'mailbox-hook');
		} catch (\Throwable $error) {
			$notification = (array)$event->arguments(0);
			$this->wire('log')->save('tickets', json_encode([
				'event' => 'mailbox_import_failed',
				'account_id' => (int)($notification['account_id'] ?? 0),
				'notification_id' => (int)($notification['id'] ?? 0),
				'error_class' => get_class($error),
				'time' => time(),
			], JSON_UNESCAPED_SLASHES));
		}
	}

	public function mailboxIntegrationStatus(): array {
		$status = [
			'installed' => false,
			'compatible' => false,
			'configured' => false,
			'background_sync' => false,
			'sending' => false,
			'inbound_ready' => false,
			'outbound_ready' => false,
			'accounts' => [],
		];
		$modules = $this->wire('modules');
		if (!$modules->isInstalled('Mailbox')) return $status;
		$status['installed'] = true;
		$mailbox = $modules->get('Mailbox');
		$status['compatible'] = $mailbox
			&& method_exists($mailbox, 'withAccount')
			&& method_exists($mailbox, 'getAgentMessage')
			&& method_exists($mailbox, 'getAccounts')
			&& method_exists($mailbox, 'credentialStatus');
		if (!$status['compatible']) return $status;
		$selectedAccount = max(0, (int)$this->mailbox_account_id);
		try {
			$credentials = $selectedAccount > 0
				? (array)$mailbox->withAccount($selectedAccount, function() use ($mailbox): array { return $mailbox->credentialStatus(); })
				: (array)$mailbox->credentialStatus();
		} catch (\Throwable $error) {
			$credentials = [];
		}
		$status['configured'] = !empty($credentials['configured']);
		$status['background_sync'] = (bool)$mailbox->enableBackgroundSync;
		$status['sending'] = (bool)$mailbox->enableMailSending && method_exists($mailbox, 'sendMessage') && method_exists($mailbox, 'replyMessage');
		foreach ((array)$mailbox->getAccounts() as $account) {
			if (empty($account['enabled'])) continue;
			$status['accounts'][] = [
				'id' => (int)($account['id'] ?? 0),
				'label' => mb_substr((string)($account['label'] ?? ('Mailbox ' . (int)($account['id'] ?? 0))), 0, 120),
				'default' => !empty($account['is_default']),
			];
		}
		$accountReady = false;
		foreach ($status['accounts'] as $account) {
			if (($selectedAccount > 0 && (int)$account['id'] === $selectedAccount) || ($selectedAccount === 0 && !empty($account['default']))) {
				$accountReady = true;
				break;
			}
		}
		$status['inbound_ready'] = $status['configured'] && $accountReady && $status['background_sync'];
		$status['outbound_ready'] = $status['configured'] && $accountReady && $status['sending'];
		return $status;
	}

	protected function addMailboxIntegrationInputfields(InputfieldWrapper $inputfields): void {
		$status = $this->mailboxIntegrationStatus();
		/** @var InputfieldWrapper $section */
		$section = $this->wire('modules')->get('InputfieldFieldset');
		$section->label = $this->_('Mailbox integration');
		$section->icon = 'envelope-o';
		$section->description = $this->_('Optionally turn incoming support email into tickets and deliver ticket replies through the configured Mailbox account. Core Tickets, WireMail, and Resend behavior remain available independently.');
		$section->collapsed = Inputfield::collapsedYes;

		$readiness = $this->wire('modules')->get('InputfieldMarkup');
		$readiness->label = $this->_('Integration readiness');
		if (!$status['installed']) $readiness->value = '<p>' . $this->_('Mailbox is not installed. Install and configure it before enabling this integration.') . '</p>';
		elseif (!$status['compatible']) $readiness->value = '<p>' . $this->_('The installed Mailbox version does not expose the required public integration API.') . '</p>';
		else $readiness->value = '<p><strong>' . ($status['inbound_ready'] ? $this->_('Inbound ready') : $this->_('Inbound unavailable')) . '</strong> · ' . ($status['outbound_ready'] ? $this->_('SMTP ready') : $this->_('SMTP unavailable')) . '</p><p class="notes">' . $this->_('Automatic import observes only new indexed messages after Mailbox completes its initial seed. It never downloads the whole mailbox.') . '</p>';
		$section->add($readiness);

		$inbound = $this->wire('modules')->get('InputfieldCheckbox');
		$inbound->name = 'mailbox_inbound_enabled';
		$inbound->label = $this->_('Import new support email from Mailbox');
		$inbound->description = $this->_('New messages in the selected folder create tickets. Messages containing an existing ticket key are appended only when the sender matches that ticket customer.');
		$inbound->checked = (bool)$this->mailbox_inbound_enabled;
		if (!$status['inbound_ready']) $inbound->attr('disabled', 'disabled');
		$section->add($inbound);

		$account = $this->wire('modules')->get('InputfieldSelect');
		$account->name = 'mailbox_account_id';
		$account->label = $this->_('Mailbox account');
		$account->addOption(0, $this->_('Use the default Mailbox account'));
		foreach ($status['accounts'] as $option) $account->addOption((int)$option['id'], (string)$option['label'] . (!empty($option['default']) ? ' ★' : ''));
		$account->value = (int)$this->mailbox_account_id;
		$account->columnWidth = 50;
		$section->add($account);

		$folder = $this->wire('modules')->get('InputfieldText');
		$folder->name = 'mailbox_folder';
		$folder->label = $this->_('Inbound folder');
		$folder->description = $this->_('Exact selectable IMAP folder name. INBOX is recommended.');
		$folder->value = (string)$this->mailbox_folder;
		$folder->required = true;
		$folder->columnWidth = 50;
		$section->add($folder);

		$recipient = $this->wire('modules')->get('InputfieldCheckbox');
		$recipient->name = 'mailbox_require_support_recipient';
		$recipient->label = $this->_('Require the Tickets support address in To or Cc');
		$recipient->description = $this->_('Recommended. Prevents unrelated messages in a shared mailbox from becoming tickets. Disable only for a dedicated support inbox or Bcc-only delivery.');
		$recipient->checked = (bool)$this->mailbox_require_support_recipient;
		$section->add($recipient);

		$outbound = $this->wire('modules')->get('InputfieldCheckbox');
		$outbound->name = 'mailbox_outbound_enabled';
		$outbound->label = $this->_('Deliver Tickets notifications through Mailbox SMTP');
		$outbound->description = $this->_('Requires Send transactional notifications above and enabled Mailbox SMTP. Existing imported conversations are replied to in their original email thread when possible; otherwise Mailbox sends a new plain-text message.');
		$outbound->checked = (bool)$this->mailbox_outbound_enabled;
		if (!$status['outbound_ready']) $outbound->attr('disabled', 'disabled');
		$section->add($outbound);
		$inputfields->add($section);
	}

	public function importMailboxNotification(array $notification, string $actor = 'backend'): array {
		if (!(bool)$this->mailbox_inbound_enabled) return ['action' => 'ignored', 'reason' => 'integration_disabled'];
		$accountId = (int)($notification['account_id'] ?? 0);
		$folder = (string)($notification['folder'] ?? '');
		$uid = (int)($notification['uid'] ?? 0);
		$selectedAccount = max(0, (int)$this->mailbox_account_id);
		if ($selectedAccount > 0 && $accountId !== $selectedAccount) return ['action' => 'ignored', 'reason' => 'account_mismatch'];
		if ($folder !== (string)$this->mailbox_folder) return ['action' => 'ignored', 'reason' => 'folder_mismatch'];
		return $this->importMailboxMessage($accountId, $folder, $uid, $actor);
	}

	public function importMailboxMessage(int $accountId, string $folder, int $uid, string $actor = 'backend'): array {
		$status = $this->mailboxIntegrationStatus();
		if (!(bool)$this->mailbox_inbound_enabled || !$status['inbound_ready']) throw new WirePermissionException('Mailbox inbound ticket integration is not ready.');
		if ($accountId < 1 || $uid < 1 || $folder === '') throw new WireException('A valid Mailbox account, folder, and UID are required.');
		$selectedAccount = max(0, (int)$this->mailbox_account_id);
		if ($selectedAccount > 0 && $accountId !== $selectedAccount) throw new WirePermissionException('This Mailbox account is not selected for Tickets.');
		/** @var Mailbox $mailbox */
		$mailbox = $this->wire('modules')->get('Mailbox');
		$message = $mailbox->withAccount($accountId, function() use ($mailbox, $folder, $uid): array {
			return $mailbox->getAgentMessage($folder, $uid);
		});
		$folderHash = hash('sha256', $folder);
		$messageId = trim((string)($message['message_id'] ?? ''));
		$messageIdHash = hash('sha256', $messageId !== '' ? strtolower($messageId) : $accountId . '|' . $folderHash . '|' . $uid);
		$claim = $this->claimMailboxSource($accountId, $folder, $folderHash, $uid, $messageIdHash);
		if (!$claim['claimed']) return ['action' => 'duplicate', 'ticket_id' => (int)($claim['row']['ticket_id'] ?? 0), 'message_id' => (int)($claim['row']['message_id'] ?? 0)];
		try {
			$result = $this->recognizeMailboxMessage($message, $accountId, $folder, $uid, $actor);
			$this->finishMailboxSource((int)$claim['id'], $result);
			$this->mailboxMessageImported($result + ['account_id' => $accountId, 'uid' => $uid]);
			return $result;
		} catch (\Throwable $error) {
			$this->releaseMailboxSource((int)$claim['id']);
			throw $error;
		}
	}

	public function importMailboxInbox(int $limit = 25, bool $execute = false): array {
		$status = $this->mailboxIntegrationStatus();
		if (!(bool)$this->mailbox_inbound_enabled || !$status['inbound_ready']) throw new WirePermissionException('Mailbox inbound ticket integration is not ready.');
		$limit = max(1, min(100, $limit));
		/** @var Mailbox $mailbox */
		$mailbox = $this->wire('modules')->get('Mailbox');
		$accountId = $this->selectedMailboxAccountId($mailbox);
		$folder = (string)$this->mailbox_folder;
		$page = $mailbox->withAccount($accountId, function() use ($mailbox, $folder, $limit): array {
			return $mailbox->listMessages($folder, 1, $limit);
		});
		$messages = array_slice((array)($page['messages'] ?? []), 0, $limit);
		$result = ['execute' => $execute, 'account_id' => $accountId, 'folder' => $folder, 'candidates' => count($messages), 'ticket_created' => 0, 'reply_added' => 0, 'ignored' => 0, 'duplicate' => 0, 'failed' => 0];
		if (!$execute) return $result;
		foreach ($messages as $message) {
			try {
				$imported = $this->importMailboxMessage($accountId, $folder, (int)($message['uid'] ?? 0), 'tickets-cli');
				$action = (string)($imported['action'] ?? 'ignored');
				if (isset($result[$action])) $result[$action]++;
				else $result['ignored']++;
			} catch (\Throwable $error) {
				$result['failed']++;
			}
		}
		return $result;
	}

	/** Hookable result boundary. Payload contains identifiers and outcome, not message text. */
	public function ___mailboxMessageImported(array $result): void {}

	protected function sendNotificationThroughMailbox(string $to, string $subject, string $html, string $replyTo = ''): bool {
		if (!(bool)$this->mailbox_outbound_enabled || !$this->mailboxIntegrationStatus()['outbound_ready']) return false;
		$to = (string)$this->wire('sanitizer')->email($to);
		if ($to === '') return false;
		$plain = html_entity_decode(strip_tags(preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html) ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$plain = mb_substr(trim($plain), 0, 1048576);
		if ($plain === '') return false;
		try {
			/** @var Mailbox $mailbox */
			$mailbox = $this->wire('modules')->get('Mailbox');
			$key = $this->ticketKeyFromText($subject, '');
			$source = $key !== '' ? $this->latestMailboxSourceForTicketKey($key, $to) : [];
			$accountId = !empty($source['account_id']) ? (int)$source['account_id'] : $this->selectedMailboxAccountId($mailbox);
			$result = $mailbox->withAccount($accountId, function() use ($mailbox, $source, $to, $subject, $plain, $replyTo): array {
				if ($source) return $mailbox->replyMessage((string)$source['folder'], (int)$source['uid'], $plain, false, 'tickets');
				$message = ['to' => [['email' => $to]], 'subject' => $subject, 'body' => $plain];
				if ($this->wire('sanitizer')->email($replyTo)) $message['reply_to'] = [['email' => $replyTo]];
				return $mailbox->sendMessage($message, 'tickets');
			});
			return !empty($result['sent']);
		} catch (\Throwable $error) {
			$this->wire('log')->save('tickets', 'Mailbox notification failed (' . get_class($error) . ').');
			return false;
		}
	}

	private function recognizeMailboxMessage(array $message, int $accountId, string $folder, int $uid, string $actor): array {
		$from = $this->firstMailboxAddress((string)($message['from'] ?? ''));
		if ($from['email'] === '') return ['action' => 'ignored', 'reason' => 'sender_missing'];
		$self = array_filter([strtolower((string)$this->support_email), strtolower((string)$this->from_email)]);
		if (in_array(strtolower($from['email']), $self, true)) return ['action' => 'ignored', 'reason' => 'self_message'];
		if ((bool)$this->mailbox_require_support_recipient) {
			$recipients = $this->mailboxAddresses((string)($message['to'] ?? '') . ',' . (string)($message['cc'] ?? ''));
			if (!in_array(strtolower((string)$this->support_email), $recipients, true)) return ['action' => 'ignored', 'reason' => 'recipient_mismatch'];
		}
		$body = $this->cleanInboundBody((string)($message['body'] ?? ''));
		if ($body === '') return ['action' => 'ignored', 'reason' => 'empty_body'];
		$subject = mb_substr(trim($this->wire('sanitizer')->text((string)($message['subject'] ?? ''))), 0, 180);
		if (mb_strlen($subject) < 5) $subject = $this->_('Email support request');
		$key = $this->ticketKeyFromText($subject, (string)($message['to'] ?? ''));
		$ticket = $key !== '' ? $this->ticketByPublicKeyForMailbox($key) : [];
		$guest = $this->wire('users')->getGuestUser();
		$externalId = 'mailbox:' . $accountId . ':' . substr(hash('sha256', $folder), 0, 20) . ':' . $uid;
		if ($ticket) {
			if (!hash_equals(strtolower((string)$ticket['customer_email']), strtolower($from['email']))) return ['action' => 'ignored', 'reason' => 'ticket_sender_mismatch', 'ticket_id' => (int)$ticket['id']];
			$db = $this->wire('database');
			$db->beginTransaction();
			try {
				$messageRecordId = $this->insertMessage((int)$ticket['id'], $guest, $body, false, false, 'email', $externalId);
				$now = date('Y-m-d H:i:s');
				$stmt = $db->prepare('UPDATE `' . self::TABLE_TICKETS . '` SET status=\'waiting_staff\',updated_at=:updated_at,closed_at=NULL,auto_close_at=NULL,reopened_at=CASE WHEN status IN (\'resolved\',\'closed\') THEN :updated_at ELSE reopened_at END WHERE id=:id');
				$stmt->execute([':updated_at' => $now, ':id' => (int)$ticket['id']]);
				$this->recordEvent((int)$ticket['id'], $guest, 'inbound_email', ['message_id' => $messageRecordId, 'source' => 'mailbox', 'actor' => mb_substr($this->wire('sanitizer')->name($actor), 0, 40)]);
				$db->commit();
			} catch (\Throwable $error) {
				if ($db->inTransaction()) $db->rollBack();
				throw $error;
			}
			return ['action' => 'reply_added', 'ticket_id' => (int)$ticket['id'], 'message_id' => $messageRecordId];
		}
		return $this->createTicketFromMailbox($from, $subject, $body, $externalId, $actor);
	}

	private function createTicketFromMailbox(array $from, string $subject, string $body, string $externalId, string $actor): array {
		$category = 'other';
		$topic = 'general';
		$priority = 'normal';
		$route = $this->matchRoutingRule($category, $topic, 0, $priority);
		$assignedUserId = max(0, (int)($route['assigned_user_id'] ?? 0));
		$firstResponseMinutes = max(15, (int)($route['first_response_minutes'] ?? 0) ?: (int)$this->sla_first_response_minutes);
		$resolutionMinutes = max(60, (int)($route['resolution_minutes'] ?? 0) ?: (int)$this->sla_resolution_minutes);
		$now = date('Y-m-d H:i:s');
		$db = $this->wire('database');
		$guest = $this->wire('users')->getGuestUser();
		$db->beginTransaction();
		try {
			$stmt = $db->prepare('INSERT INTO `' . self::TABLE_TICKETS . '` (public_key,user_id,customer_name,customer_email,guest_access_hash,subject,category,topic,priority,status,assigned_user_id,form_id,custom_data,created_at,updated_at,first_response_due_at,resolution_due_at,context_type,context_id,context_url) VALUES (:public_key,0,:customer_name,:customer_email,:guest_access_hash,:subject,:category,:topic,:priority,\'open\',:assigned_user_id,0,:custom_data,:created_at,:updated_at,:first_response_due_at,:resolution_due_at,\'mailbox\',:context_id,\'\')');
			$stmt->execute([
				':public_key' => strtoupper(bin2hex(random_bytes(6))),
				':customer_name' => mb_substr($from['name'] !== '' ? $from['name'] : strstr($from['email'], '@', true), 0, 120),
				':customer_email' => $from['email'],
				':guest_access_hash' => hash('sha256', random_bytes(32)),
				':subject' => $subject,
				':category' => $category,
				':topic' => $topic,
				':priority' => $priority,
				':assigned_user_id' => $assignedUserId,
				':custom_data' => json_encode(['source' => 'mailbox'], JSON_UNESCAPED_SLASHES),
				':created_at' => $now,
				':updated_at' => $now,
				':first_response_due_at' => date('Y-m-d H:i:s', strtotime($now) + $firstResponseMinutes * 60),
				':resolution_due_at' => date('Y-m-d H:i:s', strtotime($now) + $resolutionMinutes * 60),
				':context_id' => $externalId,
			]);
			$ticketId = (int)$db->lastInsertId();
			$messageId = $this->insertMessage($ticketId, $guest, $body, false, false, 'email', $externalId);
			$this->recordEvent($ticketId, $guest, 'created', ['source' => 'mailbox', 'routing_rule_id' => (int)($route['id'] ?? 0), 'actor' => mb_substr($this->wire('sanitizer')->name($actor), 0, 40)]);
			$db->commit();
		} catch (\Throwable $error) {
			if ($db->inTransaction()) $db->rollBack();
			throw $error;
		}
		return ['action' => 'ticket_created', 'ticket_id' => $ticketId, 'message_id' => $messageId];
	}

	private function claimMailboxSource(int $accountId, string $folder, string $folderHash, int $uid, string $messageIdHash): array {
		$db = $this->wire('database');
		$stmt = $db->prepare('INSERT IGNORE INTO `' . self::TABLE_MAILBOX . '` (account_id,folder_hash,folder,uid,message_id_hash,status,ticket_id,message_id,result,created_at,processed_at) VALUES (:account_id,:folder_hash,:folder,:uid,:message_id_hash,\'processing\',0,0,\'\',:created_at,NULL)');
		$stmt->execute([':account_id' => $accountId, ':folder_hash' => $folderHash, ':folder' => mb_substr($folder, 0, 255), ':uid' => $uid, ':message_id_hash' => $messageIdHash, ':created_at' => date('Y-m-d H:i:s')]);
		if ($stmt->rowCount() > 0) return ['claimed' => true, 'id' => (int)$db->lastInsertId(), 'row' => []];
		$existing = $db->prepare('SELECT id,status,ticket_id,message_id FROM `' . self::TABLE_MAILBOX . '` WHERE (account_id=:account_id AND folder_hash=:folder_hash AND uid=:uid) OR (account_id=:message_account_id AND message_id_hash=:message_id_hash) ORDER BY id LIMIT 1');
		$existing->execute([':account_id' => $accountId, ':folder_hash' => $folderHash, ':uid' => $uid, ':message_account_id' => $accountId, ':message_id_hash' => $messageIdHash]);
		return ['claimed' => false, 'id' => 0, 'row' => $existing->fetch(\PDO::FETCH_ASSOC) ?: []];
	}

	private function finishMailboxSource(int $id, array $result): void {
		$stmt = $this->wire('database')->prepare('UPDATE `' . self::TABLE_MAILBOX . '` SET status=:status,ticket_id=:ticket_id,message_id=:message_id,result=:result,processed_at=:processed_at WHERE id=:id AND status=\'processing\'');
		$stmt->execute([':status' => (string)($result['action'] ?? 'ignored'), ':ticket_id' => (int)($result['ticket_id'] ?? 0), ':message_id' => (int)($result['message_id'] ?? 0), ':result' => mb_substr((string)($result['reason'] ?? ''), 0, 80), ':processed_at' => date('Y-m-d H:i:s'), ':id' => $id]);
	}

	private function releaseMailboxSource(int $id): void {
		$stmt = $this->wire('database')->prepare('DELETE FROM `' . self::TABLE_MAILBOX . '` WHERE id=:id AND status=\'processing\'');
		$stmt->execute([':id' => $id]);
	}

	private function ticketByPublicKeyForMailbox(string $key): array {
		$stmt = $this->wire('database')->prepare('SELECT * FROM `' . self::TABLE_TICKETS . '` WHERE public_key=:public_key LIMIT 1');
		$stmt->execute([':public_key' => strtoupper($key)]);
		return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
	}

	private function latestMailboxSourceForTicketKey(string $key, string $recipient): array {
		$ticket = $this->ticketByPublicKeyForMailbox($key);
		if (!$ticket || !hash_equals(strtolower((string)$ticket['customer_email']), strtolower($recipient))) return [];
		$stmt = $this->wire('database')->prepare('SELECT folder,uid,account_id FROM `' . self::TABLE_MAILBOX . '` WHERE ticket_id=:ticket_id AND status IN (\'ticket_created\',\'reply_added\') ORDER BY id DESC LIMIT 1');
		$stmt->execute([':ticket_id' => (int)$ticket['id']]);
		return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
	}

	private function selectedMailboxAccountId($mailbox): int {
		$selected = max(0, (int)$this->mailbox_account_id);
		if ($selected > 0) return $selected;
		foreach ((array)$mailbox->getAccounts() as $account) if (!empty($account['enabled']) && !empty($account['is_default'])) return (int)$account['id'];
		throw new WireException('No enabled default Mailbox account is available.');
	}

	private function ticketKeyFromText(string $subject, string $recipients): string {
		if (preg_match('/\[\s*Ticket\s+#?([A-F0-9]{12})\s*\]/i', $subject, $match)) return strtoupper($match[1]);
		if (preg_match('/\bTicket\s+#([A-F0-9]{12})\b/i', $subject, $match)) return strtoupper($match[1]);
		if (preg_match('/\bticket\+([A-F0-9]{12})@/i', $recipients, $match)) return strtoupper($match[1]);
		return '';
	}

	private function mailboxAddresses(string $value): array {
		preg_match_all('/[A-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Z0-9.-]+\.[A-Z]{2,63}/i', $value, $matches);
		return array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));
	}

	private function firstMailboxAddress(string $value): array {
		$emails = $this->mailboxAddresses($value);
		$email = $emails[0] ?? '';
		$name = trim(preg_replace('/<?\s*' . preg_quote($email, '/') . '\s*>?/i', '', $value) ?? '', " \t\n\r\0\x0B\"'");
		return ['email' => $email, 'name' => mb_substr($this->wire('sanitizer')->text($name), 0, 120)];
	}
}
