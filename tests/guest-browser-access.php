<?php namespace ProcessWire;

if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1] ?? getenv('PW_ROOT') ?: '';
if ($root === '' || !is_file(rtrim($root, '/') . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/guest-browser-access.php /path/to/processwire\n");
	exit(2);
}
chdir($root);
require rtrim($root, '/') . '/index.php';

/** @var Tickets $tickets */
$tickets = wire('modules')->get('Tickets');
$guest = wire('users')->get('guest');
if (!$tickets || !$guest->id) throw new \RuntimeException('Tickets and the guest user are required.');
wire('users')->setCurrentUser($guest);
$db = wire('database');
$key = strtoupper(bin2hex(random_bytes(6)));
$email = 'guest-access-' . strtolower($key) . '@example.com';
$emailToken = bin2hex(random_bytes(32));
$ticketId = 0;
$check = static function(bool $condition, string $message): void {
	if (!$condition) throw new \RuntimeException($message);
};

try {
	$now = date('Y-m-d H:i:s');
	$stmt = $db->prepare('INSERT INTO tickets_records (public_key,user_id,customer_name,customer_email,guest_access_hash,subject,category,topic,priority,status,assigned_user_id,form_id,custom_data,created_at,updated_at) VALUES (:key,0,:name,:email,:hash,:subject,\'general\',\'general\',\'normal\',\'open\',0,0,\'{}\',:created,:updated)');
	$stmt->execute([':key' => $key, ':name' => 'Guest access test', ':email' => $email, ':hash' => hash('sha256', $emailToken), ':subject' => 'Guest browser access', ':created' => $now, ':updated' => $now]);
	$ticketId = (int)$db->lastInsertId();
	$check($tickets->unlockGuestTicketByEmail($key, 'wrong@example.com') === [], 'Wrong email unlocked the ticket.');
	$unlocked = $tickets->unlockGuestTicketByEmail(strtolower($key), strtoupper($email));
	$check((int)($unlocked['id'] ?? 0) === $ticketId, 'Matching email did not unlock the ticket.');
	$browserToken = $tickets->issueGuestBrowserAccessToken($key, $guest, 30);
	$check($browserToken !== '' && !str_contains($browserToken, $email) && !str_contains($browserToken, $emailToken), 'Browser grant is missing or exposes sensitive input.');
	wire('session')->remove('tickets_guest_access_' . $ticketId);
	$restored = $tickets->unlockGuestTicketFromBrowser($key, $browserToken);
	$check((int)($restored['id'] ?? 0) === $ticketId, 'Browser grant did not restore access.');
	wire('session')->remove('tickets_guest_access_' . $ticketId);
	$check($tickets->unlockGuestTicketFromBrowser($key, $browserToken . 'tampered') === [], 'Tampered browser grant was accepted.');
	$db->prepare('UPDATE tickets_records SET guest_access_hash=:hash WHERE id=:id')->execute([':hash' => hash('sha256', bin2hex(random_bytes(32))), ':id' => $ticketId]);
	$check($tickets->unlockGuestTicketFromBrowser($key, $browserToken) === [], 'Rotated guest access hash did not invalidate the browser grant.');
	fwrite(STDOUT, "Tickets guest browser access: OK\n");
} finally {
	wire('session')->remove('tickets_guest_access_' . $ticketId);
	if ($ticketId > 0) $db->exec('DELETE FROM tickets_records WHERE id=' . $ticketId);
}
