<?php namespace ProcessWire;

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

/** @var Tickets $tickets */
$tickets = $modules->get('Tickets');
$key = (string)$input->urlSegment1;
if ((string)$input->urlSegment2 === 'file') {
	$config->appendTemplateFile = '';
	$ticket = $tickets->ticketByKey($key, $user);
	$attachment = $ticket ? $tickets->attachment((int)$input->urlSegment3, (string)$input->urlSegment4, $user) : [];
	$path = $attachment ? $tickets->attachmentPath($attachment) : '';
	if (!$attachment || $path === '' || !is_file($path)) {
		http_response_code(404);
		header('Content-Type: text/plain; charset=utf-8');
		echo "Attachment not found.\n";
		exit;
	}
	$filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename((string)$attachment['original_name'])) ?: 'attachment';
	header('Content-Type: ' . (string)$attachment['mime_type']);
	header('Content-Length: ' . filesize($path));
	header('Content-Disposition: inline; filename="' . $filename . '"');
	header('X-Content-Type-Options: nosniff');
	readfile($path);
	exit;
}
$success = [];
$error = '';
if ($input->requestMethod('POST')) {
	try {
		$session->CSRF->validate();
		$success = $tickets->createTicket($user, [
			'customer_name' => (string)$input->post('customer_name'),
			'customer_email' => (string)$input->post('customer_email'),
			'subject' => (string)$input->post('subject'),
			'body' => (string)$input->post('body'),
			'category' => 'technical',
			'topic' => 'technical',
			'priority' => 'normal',
			'privacy_consent' => (int)$input->post('privacy_consent'),
			'website' => (string)$input->post('website'),
			'form_issued_at' => (int)$input->post('form_issued_at'),
			'form_issued_sig' => (string)$input->post('form_issued_sig'),
		]);
	} catch (\Throwable $exception) {
		$error = $exception->getMessage();
	}
}
$proof = $tickets->guestFormProof();
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Tickets E2E support</title>
	<style>
		body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f4f6f8;color:#18202a}main{max-width:720px;margin:4rem auto;padding:2rem;background:white;border-radius:16px;box-shadow:0 12px 40px #18202a1a}label{display:block;font-weight:650;margin-top:1rem}input,textarea{box-sizing:border-box;width:100%;font:inherit;padding:.75rem;border:1px solid #9aa6b2;border-radius:8px}textarea{min-height:10rem}.consent{display:flex;gap:.6rem;align-items:flex-start}.consent input{width:auto;margin-top:.35rem}button{margin-top:1.25rem;padding:.8rem 1.2rem;border:0;border-radius:8px;background:#1769aa;color:white;font:inherit;font-weight:700}.notice{padding:1rem;border-radius:8px;background:#e8f7ec}.error{padding:1rem;border-radius:8px;background:#ffe9e7;color:#8b1f17}.trap{position:absolute;left:-10000px}@media(max-width:640px){main{margin:0;padding:1.25rem;min-height:100vh;border-radius:0}h1{font-size:1.65rem}}
	</style>
</head>
<body><main>
	<h1>Contact support</h1>
	<?php if ($success): ?>
		<div class="notice" role="status"><strong>Ticket created</strong><br>Reference: <span data-ticket-key><?= $e($success['public_key'] ?? '') ?></span></div>
	<?php else: ?>
		<?php if ($error !== ''): ?><div class="error" role="alert"><?= $e($error) ?></div><?php endif; ?>
		<form method="post">
			<input type="hidden" name="<?= $e($session->CSRF->getTokenName()) ?>" value="<?= $e($session->CSRF->getTokenValue()) ?>">
			<input type="hidden" name="form_issued_at" value="<?= (int)$proof['issued_at'] ?>">
			<input type="hidden" name="form_issued_sig" value="<?= $e($proof['signature']) ?>">
			<label>Name<input name="customer_name" required value="E2E Browser Customer"></label>
			<label>Email<input type="email" name="customer_email" required value="browser@example.test"></label>
			<label>Subject<input name="subject" required value="E2E browser support request"></label>
			<label>Message<textarea name="body" required>This browser-created ticket verifies the complete anonymous support submission path.</textarea></label>
			<label class="trap">Website<input name="website" tabindex="-1" autocomplete="off"></label>
			<label class="consent"><input type="checkbox" name="privacy_consent" value="1" required><span>I consent to this E2E support test.</span></label>
			<button type="submit">Create ticket</button>
		</form>
	<?php endif; ?>
</main></body></html>
