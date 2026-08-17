<?php
/**
 * Password-gated report body (static-hosting endpoint).
 *
 * The page's primary unlock path is the Laravel route POST /report/unlock,
 * which is immune to this host's stale-static-snapshot quirk. This file is the
 * fallback for plain static hosting and keeps the report portable.
 *
 * The body itself lives OUTSIDE the web root (resources/reports/) so it can
 * never be fetched directly, whatever the server config.
 *
 * A plain GET or a wrong password returns 403 with no content.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$PW = 'libas2026'; // shared with the client separately — change here to rotate
$pw = $_POST['pw'] ?? '';

if (!is_string($pw) || !hash_equals($PW, $pw)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Incorrect password.']);
    exit;
}

$bodyFile = __DIR__ . '/../../resources/reports/review-response.html';
if (!is_file($bodyFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Report body is missing on the server.']);
    exit;
}

echo json_encode(['ok' => true, 'html' => file_get_contents($bodyFile)]);
