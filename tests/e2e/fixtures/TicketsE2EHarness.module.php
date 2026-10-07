<?php namespace ProcessWire;

class TicketsE2EHarness extends WireData implements Module {
	public static function getModuleInfo(): array {
		return [
			'title' => 'Tickets E2E harness',
			'version' => 1,
			'summary' => 'Records bounded hook evidence for Tickets E2E tests.',
			'singular' => true,
			'autoload' => true,
			'requires' => ['Tickets'],
		];
	}

	public function init(): void {
		$this->addHookAfter('Tickets::createTicket', $this, 'recordTicketCreated');
		$this->addHookAfter('Tickets::mailboxMessageImported', $this, 'recordMailboxImported');
	}

	public function recordTicketCreated(HookEvent $event): void {
		$ticket = (array)$event->return;
		$this->wire('log')->save('tickets-e2e-hooks', json_encode([
			'event' => 'ticket_created',
			'ticket_id' => (int)($ticket['id'] ?? 0),
			'public_key' => (string)($ticket['public_key'] ?? ''),
		], JSON_UNESCAPED_SLASHES));
	}

	public function recordMailboxImported(HookEvent $event): void {
		$result = (array)$event->arguments(0);
		$this->wire('log')->save('tickets-e2e-hooks', json_encode([
			'event' => 'mailbox_imported',
			'action' => (string)($result['action'] ?? ''),
			'ticket_id' => (int)($result['ticket_id'] ?? 0),
			'attachments_imported' => (int)($result['attachments_imported'] ?? 0),
		], JSON_UNESCAPED_SLASHES));
	}
}
