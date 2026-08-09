<?php declare(strict_types=1);

$root = dirname(__DIR__);
$module = (string)file_get_contents($root . '/Tickets.module.php');
$rest = (string)file_get_contents($root . '/TicketsRestApi.php');
$process = (string)file_get_contents($root . '/ProcessTickets.module.php');

$checks = [
	'versioned hook' => str_contains($module, "'/tickets-api/{version}/{resource}/'") && str_contains($rest, 'Tickets::REST_API_VERSION'),
	'header-only bearer' => str_contains($rest, "\$_SERVER['HTTP_AUTHORIZATION']") && !str_contains($rest, "input->get('token')") && !str_contains($rest, "body['token']"),
	'hash-at-rest verification' => str_contains($rest, "hash_equals(\$storedHash, hash('sha256', \$match[1]))"),
	'actor-bound permissions' => str_contains($rest, 'rest_bearer_user_id') && str_contains($rest, 'Bearer token actor no longer has API access.'),
	'csrf only for sessions' => str_contains($rest, "\$authMode === 'session'") && str_contains($rest, 'validateCsrf($body)'),
	'401 challenge' => str_contains($rest, 'WWW-Authenticate: Bearer') && str_contains($rest, 'response(401'),
	'independent bearer rate limit' => str_contains($rest, 'TicketsRestBearer:') && str_contains($rest, "\$authMode === 'bearer'"),
	'one-time raw token' => str_contains($process, 'tickets_bearer_token_once') && str_contains($process, "hash('sha256', \$token)") && str_contains($process, "remove('tickets_bearer_token_once')"),
	'revoke metadata' => str_contains($process, "\$config['rest_bearer_token_hash'] = ''") && str_contains($process, "\$config['rest_bearer_user_id'] = 0"),
];

foreach($checks as $label => $ok) {
	if(!$ok) throw new RuntimeException('Tickets REST security check failed: ' . $label);
}

fwrite(STDOUT, "Tickets REST security: OK\n");
