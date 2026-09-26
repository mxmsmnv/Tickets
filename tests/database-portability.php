<?php

$source = file_get_contents(dirname(__DIR__) . '/Tickets.module.php');
if ($source === false) throw new RuntimeException('Could not read Tickets.module.php.');

$checks = [
	'no MySQL UPDATE JOIN migration' => !preg_match('/UPDATE\s+`[^`]+`\s+\w+\s+JOIN/i', $source),
	'portable correlated first-response update' => str_contains($source, 'SET first_responded_at=(SELECT MIN(created_at)'),
	'no boolean expressions passed directly to SUM' => !preg_match('/SUM\s*\(\s*(?!CASE\b)[^)]*(?:\s(?:IN|IS|AND|OR)\s|[=<>])/i', $source),
	'portable conditional aggregates present' => substr_count($source, 'SUM(CASE WHEN') >= 12,
];

foreach ($checks as $label => $ok) {
	if (!$ok) throw new RuntimeException('Tickets database portability check failed: ' . $label);
}

echo "Tickets database portability checks passed.\n";
