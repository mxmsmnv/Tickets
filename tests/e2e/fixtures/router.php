<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . $path;
if ($path !== '/' && is_file($file)) return false;
require rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/index.php';
