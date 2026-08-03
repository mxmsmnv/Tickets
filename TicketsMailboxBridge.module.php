<?php namespace ProcessWire;

/** Lightweight optional hook bridge. Tickets remains usable without Mailbox. */
class TicketsMailboxBridge extends WireData implements Module {

	public static function getModuleInfo(): array {
		return [
			'title' => 'Tickets Mailbox Bridge',
			'version' => Tickets::VERSION,
			'summary' => 'Imports new Mailbox messages into Tickets when the optional integration is enabled.',
			'author' => 'Maxim Semenov',
			'icon' => 'envelope-o',
			'singular' => true,
			'autoload' => true,
			'requires' => ['Tickets'],
		];
	}

	public function init(): void {
		$modules = $this->wire('modules');
		if (!$modules->isInstalled('Mailbox')) return;
		/** @var Tickets $tickets */
		$tickets = $modules->get('Tickets');
		if (!$tickets || !(bool)$tickets->mailbox_inbound_enabled) return;
		$tickets->registerMailboxIntegrationHook();
	}
}
