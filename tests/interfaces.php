<?php declare(strict_types=1);

namespace ProcessWire;

$root = rtrim((string)($argv[1] ?? ''), '/');
if($root === '' || !is_file($root . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/interfaces.php /path/to/processwire\n");
	exit(2);
}

require $root . '/index.php';
/** @var Tickets $tickets */
$tickets = $wire->modules->get('Tickets');
$admin = null;
foreach($wire->users->find('include=all') as $candidate) if($candidate->isSuperuser()) { $admin = $candidate; break; }
if(!$tickets || !$admin instanceof User || !$admin->id) throw new \RuntimeException('Tickets or a superuser is unavailable.');

$oldAgentApi = (int)$tickets->enable_agent_api;
$oldRestApi = (int)$tickets->enable_rest_api;
$oldCli = (int)$tickets->enable_cli;
$oldMail = (int)$tickets->mail_enabled;
$created = 0;
try {
	$tickets->enable_agent_api = 1;
	$tickets->enable_rest_api = 1;
	$tickets->enable_cli = 1;
	$tickets->mail_enabled = 0;
	$wire->users->setCurrentUser($admin);
	$api = $tickets->api($admin);
	if(!$api->canRead() || !$api->canWrite() || !$api->canAdmin()) throw new \RuntimeException('Superuser API capabilities were not granted.');
	$manifest = $api->capabilities();
	if(empty($manifest['channels']['php_api']) || empty($manifest['channels']['rest']) || empty($manifest['channels']['cli'])) throw new \RuntimeException('Capability channels are incomplete.');

	$ticket = $tickets->createTicket($admin, [
		'subject' => 'Interfaces security test',
		'body' => 'A deterministic ticket used to verify API redaction and bounded operations.',
		'category' => 'technical',
		'topic' => 'technical',
		'priority' => 'normal',
	]);
	$created = (int)$ticket['id'];
	$record = $api->ticket($created);
	foreach(['guest_access_hash', 'custom_data'] as $secret) if(array_key_exists($secret, $record)) throw new \RuntimeException('Ticket API exposed protected field: ' . $secret);
	$queue = $api->queue(['q' => 'Interfaces security test'], 1, 10, 'all');
	if((int)$queue['total'] !== 1 || (int)$queue['items'][0]['id'] !== $created) throw new \RuntimeException('API queue lookup failed.');
	$messages = $api->messages($created);
	if(count($messages) !== 1 || !isset($messages[0]['body'])) throw new \RuntimeException('API conversation payload is incomplete.');
	foreach((array)($messages[0]['attachments'] ?? []) as $attachment) {
		foreach(['access_token', 'storage_name'] as $secret) if(array_key_exists($secret, $attachment)) throw new \RuntimeException('Message API exposed protected field: ' . $secret);
	}
	$updated = $api->update($created, ['priority' => 'urgent']);
	if((string)$updated['priority'] !== 'urgent') throw new \RuntimeException('API ticket update failed.');

	$guestApi = $tickets->api($wire->users->get('guest'));
	if($guestApi->canRead()) throw new \RuntimeException('Guest API access must fail closed.');
	try {
		$guestApi->dashboard();
		throw new \RuntimeException('Guest API read unexpectedly succeeded.');
	} catch(WirePermissionException $expected) {
	}
	fwrite(STDOUT, "Tickets interfaces: OK\n");
} finally {
	$tickets->enable_agent_api = $oldAgentApi;
	$tickets->enable_rest_api = $oldRestApi;
	$tickets->enable_cli = $oldCli;
	$tickets->mail_enabled = $oldMail;
	if($created > 0) {
		foreach([Tickets::TABLE_ATTACHMENTS, Tickets::TABLE_MESSAGES, Tickets::TABLE_EVENTS] as $table) $wire->database->exec('DELETE FROM `' . $table . '` WHERE ticket_id=' . $created);
		$wire->database->exec('DELETE FROM `' . Tickets::TABLE_LINKS . '` WHERE ticket_id=' . $created . ' OR related_ticket_id=' . $created);
		$wire->database->exec('DELETE FROM `' . Tickets::TABLE_TICKETS . '` WHERE id=' . $created);
	}
}
