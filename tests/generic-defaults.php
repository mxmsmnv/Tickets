<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/generic-defaults.php /path/to/processwire\n");
	exit(2);
}

require $root . '/index.php';
/** @var Tickets $tickets */
$tickets = $wire->modules->get('Tickets');
if (!$tickets) throw new \RuntimeException('Tickets is unavailable.');

$runtimeDefaults = Tickets::getDefaultConfig();
$templates = $tickets->mailTemplateDefaults();
$payload = strtolower(json_encode([$runtimeDefaults, $templates], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
foreach (['lqrs', 'lqrs.dev', 'lqrs.com', 'narzan'] as $forbidden) {
	if (str_contains($payload, $forbidden)) throw new \RuntimeException("Project-specific default remains: {$forbidden}");
}
if ((int)($runtimeDefaults['mail_enabled'] ?? 1) !== 0) throw new \RuntimeException('Transactional mail must be opt-in on a fresh installation.');
if ((int)($runtimeDefaults['telegram_notifications_enabled'] ?? 1) !== 0) throw new \RuntimeException('Telegram notifications must be opt-in on a fresh installation.');
if (!str_contains((string)($templates['ticket_reply_customer']['html_body'] ?? ''), '{{support_name}}')) throw new \RuntimeException('The staff reply template does not use the configurable support name.');

fwrite(STDOUT, "Tickets generic defaults: OK\n");
