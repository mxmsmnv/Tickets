<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/notification-idempotency.php /path/to/processwire\n");
	exit(2);
}

require $root . '/index.php';
/** @var Tickets $tickets */
$tickets = $wire->modules->get('Tickets');
if (!$tickets) throw new \RuntimeException('Tickets is unavailable.');

$method = new \ReflectionMethod(Tickets::class, 'replyNotificationIdempotencyKey');
$ticket = ['id' => 42, 'updated_at' => '2026-08-08 12:34:56'];
$first = $method->invoke($tickets, $ticket, 101);
$second = $method->invoke($tickets, $ticket, 102);
$fallback = $method->invoke($tickets, $ticket, 0);

if ($first !== 'ticket-reply-42-message-101') throw new \RuntimeException('Message ID is missing from the first notification key.');
if ($second !== 'ticket-reply-42-message-102') throw new \RuntimeException('Message ID is missing from the second notification key.');
if ($first === $second) throw new \RuntimeException('Same-second replies still share an idempotency key.');
if ($fallback !== 'ticket-reply-42-' . strtotime($ticket['updated_at'])) throw new \RuntimeException('Legacy no-message fallback changed unexpectedly.');

fwrite(STDOUT, "Tickets notification idempotency: OK\n");
