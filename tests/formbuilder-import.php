<?php namespace ProcessWire;

$root = $argv[1] ?? '';
if ($root === '' || !is_file(rtrim($root, '/') . '/index.php')) {
	fwrite(STDERR, "Usage: php tests/formbuilder-import.php /path/to/processwire\n");
	exit(2);
}
require rtrim($root, '/') . '/index.php';
require_once dirname(__DIR__) . '/TicketsFormBuilderImporter.php';

$tickets = wire('modules')->get('Tickets');
if (!$tickets) throw new \RuntimeException('Tickets must be installed in the test site.');
$importer = new TicketsFormBuilderImporter($tickets);
$converted = $importer->convert([
	'name' => 'service-request',
	'submitText' => 'Send',
	'children' => [
		'details' => ['type' => 'Fieldset', 'label' => 'Details', 'children' => [
			'subject' => ['type' => 'Text', 'label' => 'Subject', 'required' => 1, 'maxlength' => 120],
			'when' => ['type' => 'Datetime', 'label' => 'Date'],
		]],
		'channels' => ['type' => 'Checkboxes', 'label' => 'Channels', 'options' => "email=Email\nphone=Phone"],
		'evidence' => ['type' => 'FormBuilderFile', 'label' => 'Evidence'],
		'website' => ['type' => 'URL', 'label' => 'Website'],
	],
]);

$types = array_column($converted['fields'], 'type');
foreach (['section', 'text', 'date', 'checkbox'] as $expected) {
	if (!in_array($expected, $types, true)) throw new \RuntimeException("Missing converted field type: $expected");
}
if (empty($converted['allow_attachment'])) throw new \RuntimeException('File fields must enable the protected attachment control.');
if (count(array_filter($types, static fn(string $type): bool => $type === 'checkbox')) !== 2) throw new \RuntimeException('Multi-value options must become distinct checkbox fields.');
if (!in_array('submitted_website', array_column($converted['fields'], 'name'), true)) throw new \RuntimeException('Reserved security-control names must be mapped safely.');

echo "Tickets FormBuilder import conversion: OK\n";
