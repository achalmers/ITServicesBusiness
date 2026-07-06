<?php
/**
 * NexaTech Solutions — CSRF Token Endpoint
 * GET /api/csrf-token.php
 * Returns JSON: { "csrf_token": "..." }
 * Used by static pages (e.g. contact.html) that can't generate a
 * session-bound token server-side at page render time.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo json_encode(['csrf_token' => generateCsrfToken()]);
