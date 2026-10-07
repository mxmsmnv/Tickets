<?php

declare(strict_types=1);

namespace ProcessWire;

interface Module {}
interface ConfigurableModule {}
class WireException extends \RuntimeException {}
class WireData {
	private array $values = [];
	public function __construct() {}
	public function set(string $name, $value): void { $this->values[$name] = $value; }
	public function get(string $name) { return $this->values[$name] ?? null; }
	public function __set(string $name, $value): void { $this->values[$name] = $value; }
	public function __get(string $name) { return $this->values[$name] ?? null; }
	public function wire(string $name) {
		if ($name !== 'sanitizer') throw new \RuntimeException('Unexpected test wire dependency: ' . $name);
		return new class {
			public function filename(string $value): string { return preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($value)) ?? ''; }
		};
	}
}

require_once dirname(__DIR__) . '/TicketsMailboxIntegration.php';
require_once dirname(__DIR__) . '/Tickets.module.php';

final class TicketsIssueRegressionHarness {
	use TicketsMailboxIntegration;
}

$harness = new TicketsIssueRegressionHarness();
$plainText = new \ReflectionMethod($harness, 'mailboxPlainText');

$html = '<h1>Support</h1><p>Hello customer.</p><p><a href="https://example.test/support/ABC/">Open your private ticket</a></p>'
	. '<div>Next step<br>Reply to this message.</div><p><a href="https://example.test/same">https://example.test/same</a></p>';
$expected = "Support\nHello customer.\nOpen your private ticket\nhttps://example.test/support/ABC/\nNext step\nReply to this message.\nhttps://example.test/same";
if ($plainText->invoke($harness, $html) !== $expected) throw new \RuntimeException('Mailbox HTML-to-text conversion lost a link or block boundary.');

$blankLines = '<p>One</p><div></div><blockquote></blockquote><p>Two</p>';
if ($plainText->invoke($harness, $blankLines) !== "One\n\nTwo") throw new \RuntimeException('Mailbox HTML-to-text conversion did not collapse blank lines.');

$ticketsSource = (string)file_get_contents(dirname(__DIR__) . '/Tickets.module.php');
$mailboxSource = (string)file_get_contents(dirname(__DIR__) . '/TicketsMailboxIntegration.php');
$ticketsReflection = new \ReflectionClass(Tickets::class);
$tickets = $ticketsReflection->newInstanceWithoutConstructor();
$tickets->max_image_mb = 8;
$tickets->allowed_attachment_types = 'jpg,jpeg,png,webp,pdf,docx,txt';
$validateAttachment = new \ReflectionMethod($tickets, 'validateAttachment');
$textMetadata = $validateAttachment->invoke($tickets, 'notes.txt', 5, 'text/plain', static fn() => false);
if (($textMetadata['original_name'] ?? '') !== 'notes.txt' || ($textMetadata['file_size'] ?? 0) !== 5) throw new \RuntimeException('Shared attachment validation rejected a valid text attachment.');
try {
	$validateAttachment->invoke($tickets, 'payload.exe', 5, 'application/octet-stream', static fn() => false);
	throw new \RuntimeException('Shared attachment validation accepted a disallowed extension.');
} catch (WireException $expected) {
}
$pngMetadata = $validateAttachment->invoke($tickets, 'pixel.png', 68, 'image/png', static fn() => [1, 1, 'mime' => 'image/png']);
if (($pngMetadata['width'] ?? 0) !== 1 || ($pngMetadata['height'] ?? 0) !== 1) throw new \RuntimeException('Shared attachment validation lost image dimensions.');

$checks = [
	'createTicket remains public and hookable' => $ticketsReflection->hasMethod('___createTicket') && $ticketsReflection->getMethod('___createTicket')->isPublic(),
	'staff email defaults preserve prior behavior' => str_contains($ticketsSource, "'mail_notification_events' => ['new_ticket', 'customer_reply', 'sla_breach']"),
	'staff email events are configurable' => str_contains($ticketsSource, "name = 'mail_notification_events'") && str_contains($ticketsSource, "staffMailEventEnabled('customer_reply')"),
	'initial delivery follows customer receipt' => str_contains($ticketsSource, '$customerSent = $this->sendTemplateNotification') && !str_contains($ticketsSource, '$staffSent = $this->sendTemplateNotification'),
	'Mailbox attachments use the public API' => str_contains($mailboxSource, "\$status['attachment_access'] = method_exists(\$mailbox, 'getAttachment')") && str_contains($mailboxSource, '$mailbox->getAttachment($folder, $uid, $part, \'tickets\')'),
	'Mailbox bytes use shared attachment validation' => str_contains($ticketsSource, 'private function storeAttachmentBytes(') && str_contains($ticketsSource, 'private function validateAttachment('),
	'inline image references are readable' => str_contains($ticketsSource, "picture, see attachments"),
	'skipped attachment logs exclude content and filenames' => str_contains($mailboxSource, "'event' => 'mailbox_attachment_skipped'") && !str_contains($mailboxSource, "'filename' =>") && !str_contains($mailboxSource, "'content' =>"),
];

foreach ($checks as $label => $ok) if (!$ok) throw new \RuntimeException('Failed: ' . $label);

fwrite(STDOUT, "Tickets issue regressions: OK\n");
