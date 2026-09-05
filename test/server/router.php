<?php

/**
 * Minimal fake SparkPost API used by the CurlClient integration tests.
 * Run with: php -S 127.0.0.1:PORT test/server/router.php
 *
 * Routes (all under /api/v1/):
 *   status/<code>          answers with that HTTP status
 *   flaky/<key>/<n>        answers 503 for the first n calls with that key, then 200
 *   slow                   sleeps 3 seconds before answering
 *   gzip                   answers with a gzip encoded body
 *   anything else          echoes the request (method, path, query, headers, body)
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = file_get_contents('php://input');

header('Content-Type: application/json');
header('X-Test-Header: one');
header('X-Test-Header: two', false);

if (preg_match('#/api/v1/status/(\d+)$#', $path, $m)) {
    http_response_code((int) $m[1]);
    echo json_encode(['errors' => [['message' => 'status '.$m[1], 'code' => 1234]]]);
    exit;
}

if (preg_match('#/api/v1/flaky/([^/]+)/(\d+)$#', $path, $m)) {
    $file = sys_get_temp_dir().'/php-sparkpost-flaky-'.preg_replace('/[^a-z0-9]/i', '', $m[1]);
    $attempts = (int) @file_get_contents($file);
    file_put_contents($file, $attempts + 1);
    if ($attempts < (int) $m[2]) {
        http_response_code(503);
        echo json_encode(['errors' => [['message' => 'try again', 'attempt' => $attempts + 1]]]);
        exit;
    }
    echo json_encode(['results' => ['attempts' => $attempts + 1]]);
    exit;
}

if (preg_match('#/api/v1/slow$#', $path)) {
    sleep(3);
}

if (preg_match('#/api/v1/gzip$#', $path)) {
    header('Content-Encoding: gzip');
    echo gzencode(json_encode(['results' => 'compressed']));
    exit;
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headers[$key] = $value;
    }
}

echo json_encode(['results' => [
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $path,
    'query' => isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '',
    'headers' => $headers,
    'body' => $body,
]]);
