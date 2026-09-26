<?php

$source = file_get_contents(dirname(__DIR__) . '/Tickets.module.php');
if ($source === false) throw new RuntimeException('Could not read Tickets.module.php.');
$freshSchemaStart = strpos($source, "\t\t\$db->exec('CREATE TABLE IF NOT EXISTS `' . self::TABLE_TICKETS");
$freshSchemaEnd = $freshSchemaStart === false ? false : strpos($source, "\n\t\t\$columns =", $freshSchemaStart);
if ($freshSchemaStart === false || $freshSchemaEnd === false) throw new RuntimeException('Could not locate the fresh tickets schema.');
$freshTicketsSchema = substr($source, $freshSchemaStart, $freshSchemaEnd - $freshSchemaStart);

$checks = [
	'no MySQL UPDATE JOIN migration' => !preg_match('/UPDATE\s+`[^`]+`\s+\w+\s+JOIN/i', $source),
	'portable correlated first-response update' => str_contains($source, 'SET first_responded_at=(SELECT MIN(created_at)'),
	'no boolean expressions passed directly to SUM' => !preg_match('/SUM\s*\(\s*(?!CASE\b)[^)]*(?:\s(?:IN|IS|AND|OR)\s|[=<>])/i', $source),
	'portable conditional aggregates present' => substr_count($source, 'SUM(CASE WHEN') >= 12,
	'portable timestamp arithmetic' => !str_contains($source, 'TIMESTAMPDIFF('),
	'no bound boolean comparison in reply update' => !str_contains($source, 'CASE WHEN :staff=1'),
	'portable index introspection' => str_contains($source, '$db->indexExists($table, $index)'),
	'fresh schema creates migrated indexes only once' => !preg_match('/KEY `(?:form_id|created_at|category|topic|priority)`/', $freshTicketsSchema),
	'null-safe template lookup during install' => str_contains($source, 'if (!$template || !$template->id)'),
	'null-safe fieldgroup lookup during install' => str_contains($source, 'if (!$fieldgroup || !$fieldgroup->id)'),
];

foreach ($checks as $label => $ok) {
	if (!$ok) throw new RuntimeException('Tickets database portability check failed: ' . $label);
}

echo "Tickets database portability checks passed.\n";
