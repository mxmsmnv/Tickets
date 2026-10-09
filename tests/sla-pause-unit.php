<?php declare(strict_types=1);

namespace ProcessWire {
	interface Module {}
	interface ConfigurableModule {}
	class WireData {
		private array $values = [];
		public function __construct() {}
		public function set(string $key, mixed $value): static { $this->values[$key] = $value; return $this; }
		public function __get(string $key): mixed { return $this->values[$key] ?? null; }
		public function __set(string $key, mixed $value): void { $this->values[$key] = $value; }
	}

	require dirname(__DIR__) . '/Tickets.module.php';

	$tickets = new Tickets();
	$tickets->sla_resolution_minutes = 2880;
	$tickets->auto_close_days = 14;
	$invoke = static function(string $method, array $ticket, string $now) use ($tickets): array {
		return (new \ReflectionMethod(Tickets::class, $method))->invoke($tickets, $ticket, $now);
	};
	$check = static function(bool $condition, string $message): void {
		if (!$condition) throw new \RuntimeException($message);
	};

	$now = '2026-10-09 12:00:00';
	$paused = [
		'status' => 'waiting_customer',
		'updated_at' => '2026-10-09 10:00:00',
		'first_responded_at' => '2026-10-09 09:00:00',
		'resolution_due_at' => '2026-10-09 13:00:00',
		'resolution_paused_at' => '2026-10-09 10:00:00',
		'sla_breached_at' => '2026-10-09 11:00:00',
		'auto_close_at' => '2026-10-23 10:00:00',
		'reopened_at' => null,
	];
	$resumed = $invoke('customerReplySlaTransition', $paused, $now);
	$check($resumed['resolution_due_at'] === '2026-10-09 15:00:00', 'Paused duration was not added to the resolution deadline.');
	$check($resumed['resolution_paused_at'] === null && $resumed['auto_close_at'] === null, 'Resume did not clear pause and auto-close state.');
	$check($resumed['sla_breached_at'] === null, 'Resume did not clear a stale waiting-customer breach.');

	$continued = $invoke('staffReplySlaTransition', $paused, $now);
	$check($continued['resolution_paused_at'] === '2026-10-09 10:00:00', 'A repeated staff reply restarted the SLA pause.');
	$check($continued['auto_close_at'] === '2026-10-23 12:00:00', 'Latest staff reply did not renew auto-close.');

	$closed = $paused;
	$closed['status'] = 'closed';
	$closed['resolution_paused_at'] = null;
	$reopened = $invoke('customerReplySlaTransition', $closed, $now);
	$check($reopened['reopened_at'] === $now, 'Closed-ticket reply did not record reopening.');
	$check($reopened['resolution_due_at'] === '2026-10-11 12:00:00', 'Closed-ticket reply did not receive a full resolution window.');

	$state = $tickets->slaState($paused);
	$check($state['phase'] === 'resolution' && $state['paused'] === true, 'Waiting-customer resolution SLA was not exposed as paused.');
	$check($state['breached'] === false && $state['remaining_seconds'] === 10800, 'Paused SLA did not freeze its remaining time.');

	echo "Tickets SLA pause unit tests passed.\n";
}
