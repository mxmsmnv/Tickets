<?php namespace ProcessWire;

if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1] ?? getenv('PW_ROOT') ?: '';
if ($root === '' || !is_file(rtrim($root, '/') . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/custom-forms.php /path/to/processwire\n");
	exit(2);
}
chdir($root);
require rtrim($root, '/') . '/index.php';

/** @var Tickets $tickets */
$tickets = wire('modules')->get('Tickets');
$admin = wire('users')->get('roles=superuser');
$guest = wire('users')->get('guest');
if (!$tickets || !$admin->id || !$guest->id) throw new \RuntimeException('Tickets, a superuser, and the guest user are required.');

$tickets->mail_enabled = 0;
$oldSpamMinimum = (int)$tickets->spam_min_submit_seconds;
$oldPrivacyPolicyUrl = (string)$tickets->privacy_policy_url;
$oldTermsUrl = (string)$tickets->terms_url;
$tickets->spam_min_submit_seconds = 0;
$tickets->privacy_policy_url = '/privacy-test/';
$tickets->terms_url = 'https://example.com/terms-test/';
$name = 'tickets-test-' . strtolower(bin2hex(random_bytes(4)));
$formId = 0;
$ticketId = 0;
try {
	$form = $tickets->saveCustomForm([
		'title' => 'Custom form integration test', 'name' => $name, 'enabled' => 1, 'allow_guests' => 1,
		'category' => 'technical', 'topic' => 'technical', 'priority' => 'normal',
		'fields' => [
			['name' => 'summary', 'label' => 'Summary', 'type' => 'text', 'required' => 1, 'width' => 'half', 'min_length' => 10, 'max_length' => 40],
			['name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'required' => 1, 'width' => 'full'],
		],
	], $admin);
	$formId = (int)$form['id'];
	assert($formId > 0);
	$embed = $tickets->renderFormEmbed($name, ['summary' => 'Lifecycle preset', 'unknown' => 'Ignored']);
	assert(str_contains($embed, 'data-tickets-form-url'));
	assert(str_contains($embed, 'Lifecycle preset'));
	assert(!str_contains($embed, 'Ignored'));
	$renderedForm = $tickets->renderCustomForm($name);
	assert(str_contains($renderedForm, 'data-tickets-custom-form'));
	assert(str_contains($renderedForm, 'form_issued_sig'));
	assert(str_contains($renderedForm, 'href="/privacy-test/"'));
	assert(str_contains($renderedForm, 'href="https://example.com/terms-test/"'));
	$draft = $form;
	$draft['id'] = $formId;
	$draft['enabled'] = 0;
	$tickets->saveCustomForm($draft, $admin);
	assert($tickets->renderFormEmbed($name) === '');
	assert($tickets->renderCustomForm($name) === '');
	$draft['enabled'] = 1;
	$form = $tickets->saveCustomForm($draft, $admin);
	$rejected = false;
	try {
		$tickets->submitCustomForm($name, $guest, ['customer_email' => $name . '@example.com', 'form_issued_at' => time() - 10, 'privacy_consent' => 1, 'summary' => 'Short', 'details' => 'This otherwise valid body confirms server-side field limits.']);
	} catch (WireException $error) { $rejected = str_contains($error->getMessage(), 'at least 10'); }
	assert($rejected);

	$result = $tickets->submitCustomForm($name, $guest, [
		'customer_email' => $name . '@example.com',
		'form_issued_at' => time() - max(4, (int)$tickets->spam_min_submit_seconds),
		'privacy_consent' => 1,
		'summary' => 'Lifecycle test',
		'details' => 'Validate rendering, validation, ticket persistence, and attribution.',
	], null);
	$ticket = (array)$result['ticket'];
	$ticketId = (int)$ticket['id'];
	assert($ticketId > 0);
	assert((int)$ticket['form_id'] === $formId);
	assert(($ticket['custom_values']['summary'] ?? '') === 'Lifecycle test');

	$text = 'Before [[tickets-form:' . $name . ']] After';
	wire('modules')->get('TextformatterTicketsForms')->format($text);
	assert(str_contains($text, 'TicketsFormEmbed'));
	assert(!str_contains($text, '[[tickets-form:'));
	echo "Tickets custom forms: OK\n";
} finally {
	$tickets->spam_min_submit_seconds = $oldSpamMinimum;
	$tickets->privacy_policy_url = $oldPrivacyPolicyUrl;
	$tickets->terms_url = $oldTermsUrl;
	$db = wire('database');
	if ($ticketId > 0) {
		$db->exec('DELETE FROM tickets_events WHERE ticket_id=' . $ticketId);
		$db->exec('DELETE FROM tickets_attachments WHERE ticket_id=' . $ticketId);
		$db->exec('DELETE FROM tickets_messages WHERE ticket_id=' . $ticketId);
		$db->exec('DELETE FROM tickets_records WHERE id=' . $ticketId);
	}
	if ($formId > 0) $tickets->deleteCustomForm($formId, $admin);
}
