<?php
$module = (string)file_get_contents(dirname(__DIR__) . '/Tickets.module.php');
$trait = (string)file_get_contents(dirname(__DIR__) . '/src/McpProviderTrait.php');
$checks = [str_contains($module, "'mcpProvider' => true"), str_contains($trait, "'tickets_status'"), !str_contains($trait, 'ticketMessages('), str_contains($trait, "'additionalProperties' => false")];
if(in_array(false, $checks, true)) { fwrite(STDERR, "Tickets MCP provider contract failed.\n"); exit(1); }
echo "Tickets MCP provider contract passed.\n";
