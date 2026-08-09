<?php namespace ProcessWire;

/** Permission-gated operational facade for agents, local integrations and REST. */
final class TicketsAgentApi {

	private Tickets $tickets;
	private User $actor;
	private string $channel;

	public function __construct(Tickets $tickets, User $actor, string $channel = 'php_api') {
		$this->tickets = $tickets;
		$this->actor = $actor;
		$this->channel = $channel;
	}

	public function canRead(): bool {
		$enabled = $this->channel === 'cli' ? (bool)$this->tickets->enable_cli : (bool)$this->tickets->enable_agent_api;
		return $enabled
			&& $this->actor->isLoggedin()
			&& ($this->channel === 'cli' || $this->actor->hasPermission(Tickets::PERMISSION_API))
			&& $this->tickets->canManage($this->actor);
	}

	public function canWrite(): bool {
		return $this->canRead() && $this->tickets->canManage($this->actor);
	}

	public function canAdmin(): bool {
		return $this->canRead() && $this->tickets->canAdmin($this->actor);
	}

	public function capabilities(): array {
		$this->assertCanRead();
		return $this->tickets->capabilities();
	}

	public function dashboard(): array {
		$this->assertCanRead();
		return $this->tickets->dashboardStats();
	}

	public function queue(array $filters = [], int $page = 1, int $limit = 50, string $scope = 'active'): array {
		$this->assertCanRead();
		$result = $this->tickets->queuePage($filters, $page, $limit, $scope);
		$result['items'] = array_map([$this, 'ticketPayload'], (array)$result['items']);
		return $result;
	}

	public function ticket($identifier): array {
		$this->assertCanRead();
		$ticket = $this->findTicket($identifier);
		if(!$ticket) throw new Wire404Exception('Ticket not found.');
		return $this->ticketPayload($ticket);
	}

	public function messages($identifier): array {
		$this->assertCanRead();
		$ticket = $this->findTicket($identifier);
		if(!$ticket) throw new Wire404Exception('Ticket not found.');
		return array_map([$this, 'messagePayload'], $this->tickets->ticketMessages((int)$ticket['id'], true));
	}

	public function report(array $filters = []): array {
		$this->assertCanAdmin();
		return $this->tickets->reportData($filters);
	}

	public function forms(): array {
		$this->assertCanAdmin();
		return array_map(static function(array $form): array {
			return array_intersect_key($form, array_flip([
				'id', 'name', 'title', 'description', 'category', 'topic', 'priority',
				'allow_guests', 'allow_attachment', 'enabled', 'fields', 'created_at', 'updated_at',
			]));
		}, $this->tickets->customForms(false));
	}

	public function update($identifier, array $changes): array {
		$this->assertCanWrite();
		$ticket = $this->findTicket($identifier);
		if(!$ticket) throw new Wire404Exception('Ticket not found.');
		$allowed = array_intersect_key($changes, array_flip(['status', 'priority', 'assigned_user_id']));
		if(!$allowed) throw new WireException('No supported ticket changes were supplied.');
		return $this->ticketPayload($this->tickets->updateTicket((int)$ticket['id'], $this->actor, $allowed));
	}

	public function reply($identifier, string $body): array {
		$this->assertCanWrite();
		$ticket = $this->findTicket($identifier);
		if(!$ticket) throw new Wire404Exception('Ticket not found.');
		return $this->ticketPayload($this->tickets->addReply((int)$ticket['id'], $this->actor, $body, null, true));
	}

	public function note($identifier, string $body): array {
		$this->assertCanWrite();
		$ticket = $this->findTicket($identifier);
		if(!$ticket) throw new Wire404Exception('Ticket not found.');
		return $this->ticketPayload($this->tickets->addInternalNote((int)$ticket['id'], $this->actor, $body));
	}

	private function findTicket($identifier): array {
		if(is_int($identifier) || ctype_digit((string)$identifier)) return $this->tickets->getTicket((int)$identifier);
		return $this->tickets->ticketByKey((string)$identifier, $this->actor);
	}

	private function ticketPayload(array $ticket): array {
		$allowed = [
			'id', 'public_key', 'user_id', 'customer_name', 'customer_email', 'subject',
			'category', 'topic', 'priority', 'status', 'assigned_user_id', 'form_id',
			'custom_values', 'context_type', 'context_id', 'context_url', 'created_at',
			'updated_at', 'closed_at', 'first_response_due_at', 'first_responded_at',
			'resolution_due_at', 'sla_breached_at', 'auto_close_at', 'reopened_at',
			'merged_into_id', 'rating', 'rating_comment', 'rated_at', 'form',
		];
		return array_intersect_key($ticket, array_flip($allowed));
	}

	private function messagePayload(array $message): array {
		$payload = array_intersect_key($message, array_flip([
			'id', 'ticket_id', 'user_id', 'user_name', 'is_staff', 'is_internal',
			'source', 'body', 'created_at', 'attachments',
		]));
		$payload['attachments'] = array_map(static function(array $attachment): array {
			return array_intersect_key($attachment, array_flip([
				'id', 'ticket_id', 'message_id', 'user_id', 'original_name', 'mime_type',
				'file_size', 'width', 'height', 'created_at',
			]));
		}, (array)($payload['attachments'] ?? []));
		return $payload;
	}

	private function assertCanRead(): void {
		if(!$this->canRead()) throw new WirePermissionException('Tickets API access denied.');
	}

	private function assertCanWrite(): void {
		if(!$this->canWrite()) throw new WirePermissionException('Tickets API write access denied.');
	}

	private function assertCanAdmin(): void {
		if(!$this->canAdmin()) throw new WirePermissionException('Tickets administrator access required.');
	}
}
