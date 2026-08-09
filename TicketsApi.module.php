<?php namespace ProcessWire;

/** Optional same-origin JSON route bridge for Tickets. */
class TicketsApi extends WireData implements Module {

	public static function getModuleInfo(): array {
		return [
			'title' => 'Tickets API',
			'version' => Tickets::VERSION,
			'summary' => 'Permission-gated same-origin JSON transport for Tickets.',
			'author' => 'Maxim Semenov',
			'icon' => 'exchange',
			'singular' => true,
			'autoload' => true,
			'requires' => ['Tickets'],
		];
	}

	public function init(): void {
		/** @var Tickets|null $tickets */
		$tickets = $this->wire('modules')->get('Tickets');
		if(!$tickets || !(bool)$tickets->enable_agent_api || !(bool)$tickets->enable_rest_api) return;
		$this->addHook('/tickets-api/v1/{resource}/', $this, 'handleRequest');
	}

	public function handleRequest(HookEvent $event): string {
		/** @var Tickets $tickets */
		$tickets = $this->wire('modules')->get('Tickets');
		require_once __DIR__ . '/TicketsRestApi.php';
		$rest = $this->wire(new TicketsRestApi($tickets));
		return $rest->handle((string)$event->arguments('resource'));
	}
}
