<?php namespace ProcessWire;

class TeleWire extends WireData implements Module {
	public static function getModuleInfo(): array {
		return [
			'title' => 'TeleWire E2E fixture',
			'version' => 102,
			'summary' => 'Network-free Tickets E2E dependency fixture.',
			'singular' => true,
			'autoload' => false,
		];
	}

	public function createClient(string $token, array $options = []): object {
		return new class {
			public function sendMessage(string $recipient, string $message, array $options = []): bool { return true; }
		};
	}
}
