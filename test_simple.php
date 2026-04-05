<?php
// Simple step-by-step test

// 1. Get the form page
$ctx = stream_context_create(['http' => ['method' => 'GET']]);
$page = file_get_contents('http://127.0.0.1:8000/user/new', false, $ctx);
preg_match('/name="user\[_token\]"[^>]*value="([^"]+)"/', $page, $m);
$token = $m[1] ?? 'no-token';
echo "1. Token: $token\n";

// 2. POST empty form
$data = http_build_query([
    'user[nom]' => '',
    'user[prenom]' => '',
    'user[email]' => '',
    'user[telephone]' => '',
    'user[role]' => 'CANDIDAT',
    'user[password]' => '',
    'user[_token]' => $token,
]);
$ctx = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'content' => $data,
    'follow_location' => 0,
    'ignore_errors' => true,
]]);
$resp = @file_get_contents('http://127.0.0.1:8000/user/new', false, $ctx);
$status = $http_response_header[0] ?? 'unknown';
echo "2. Empty POST status: $status\n";

// Check for errors in response
if (strpos($resp, 'Exception') !== false || strpos($resp, 'Error') !== false) {
    preg_match('/<title>(.*?)<\/title>/', $resp, $titleMatch);
    echo "   Error page title: " . ($titleMatch[1] ?? 'unknown') . "\n";
    preg_match('/<h2[^>]*class="exception-message"[^>]*>(.*?)<\/h2>/s', $resp, $msgMatch);
    echo "   Exception message: " . strip_tags($msgMatch[1] ?? 'unknown') . "\n";
}

if (strpos($resp, 'ne peut pas') !== false || strpos($resp, 'should not be blank') !== false) {
    echo "   => VALIDATION WORKING (blank errors detected)\n";
}
