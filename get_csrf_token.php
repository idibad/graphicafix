<?php
// Must be called before any output
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $token;

echo json_encode(['token' => $token, 'session_id' => session_id()]);