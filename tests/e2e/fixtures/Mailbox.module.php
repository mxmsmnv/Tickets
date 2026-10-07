<?php namespace ProcessWire;

class Mailbox extends WireData implements Module {
	public static function getModuleInfo(): array {
		return [
			'title' => 'Mailbox E2E fixture',
			'version' => 102,
			'summary' => 'Deterministic network-free Mailbox contract fixture for Tickets.',
			'singular' => true,
			'autoload' => true,
		];
	}

	public function __construct() {
		parent::__construct();
		$this->set('enableBackgroundSync', true);
		$this->set('enableMailSending', true);
	}

	public function getAccounts(): array {
		return [['id' => 1, 'label' => 'E2E mailbox', 'enabled' => true, 'is_default' => true]];
	}

	public function credentialStatus(): array {
		return ['configured' => true, 'password_source' => 'fixture', 'encryption_key' => 'fixture'];
	}

	public function withAccount(int $accountId, callable $operation) {
		if ($accountId !== 1) throw new WireException('Unknown E2E mailbox account.');
		return $operation();
	}

	public function getAgentMessage(string $folder, int $uid): array {
		if ($folder !== 'INBOX' || $uid !== 101) throw new Wire404Exception('E2E mailbox message not found.');
		return [
			'uid' => 101,
			'message_id' => '<tickets-e2e-101@example.test>',
			'from' => 'E2E Customer <customer@example.test>',
			'to' => 'E2E Support <support@example.test>',
			'cc' => '',
			'subject' => 'E2E Mailbox attachment request',
			'body' => "This imported request contains enough detail for support.\n[cid:image001.png@example.test]",
			'attachments' => [
				['part' => '2', 'name' => 'details.txt', 'type' => 'text/plain', 'bytes' => 31],
				['part' => '3', 'name' => 'pixel.png', 'type' => 'image/png', 'bytes' => 68],
			],
			'links' => [],
		];
	}

	public function getAttachment(string $folder, int $uid, string $part, string $actor = 'backend'): array {
		if ($folder !== 'INBOX' || $uid !== 101) throw new Wire404Exception('E2E mailbox attachment not found.');
		if ($part === '2') {
			$content = "E2E attachment details are safe.\n";
			return ['part' => '2', 'name' => 'details.txt', 'type' => 'text/plain', 'bytes' => strlen($content), 'content' => $content];
		}
		if ($part === '3') {
			$content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
			return ['part' => '3', 'name' => 'pixel.png', 'type' => 'image/png', 'bytes' => strlen((string)$content), 'content' => (string)$content];
		}
		throw new Wire404Exception('Unknown E2E attachment part.');
	}

	public function listMessages(string $folder = 'INBOX', int $page = 1, ?int $limit = null): array {
		return ['messages' => [['uid' => 101, 'subject' => 'E2E Mailbox attachment request']], 'page' => 1, 'pages' => 1];
	}

	public function sendMessage(array $message, string $actor = 'backend'): array {
		$this->recordDelivery('send', $message + ['actor' => $actor]);
		return ['sent' => true, 'sent_copy' => false];
	}

	public function replyMessage(string $folder, int $uid, string $body, bool $replyAll = false, string $actor = 'backend', array $attachments = []): array {
		$this->recordDelivery('reply', compact('folder', 'uid', 'body', 'replyAll', 'actor'));
		return ['sent' => true, 'sent_copy' => false];
	}

	public function ___messageIndexed(array $notification): void {}

	private function recordDelivery(string $kind, array $message): void {
		$to = [];
		foreach ((array)($message['to'] ?? []) as $recipient) $to[] = (string)($recipient['email'] ?? '');
		$this->wire('log')->save('tickets-e2e-mailbox', json_encode([
			'kind' => $kind,
			'to' => $to,
			'subject' => (string)($message['subject'] ?? ''),
			'body' => (string)($message['body'] ?? ''),
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}
}
