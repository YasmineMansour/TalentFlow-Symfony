<?php
$cookieFile = __DIR__ . '/test_edit_cookies.txt';
@unlink($cookieFile);

function req($url, $method = 'GET', $data = []) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code' => $code, 'body' => substr($resp, $hs), 'hdrs' => substr($resp, 0, $hs)];
}

// List users to find the ID
$r = req('http://127.0.0.1:8000/user/');
preg_match_all('/\/user\/(\d+)/', $r['body'], $ids);
$userId = $ids[1][0] ?? 'none';
echo "User ID: $userId\n";

// Get edit page
$r = req("http://127.0.0.1:8000/user/$userId/edit");
echo "GET edit: {$r['code']}\n";
preg_match('/name="user\[_token\]"[^>]*value="([^"]+)"/', $r['body'], $m);
$tok = $m[1] ?? '';
echo "Token: " . substr($tok, 0, 30) . "...\n";

// POST with empty nom
$r = req("http://127.0.0.1:8000/user/$userId/edit", 'POST', [
    'user[nom]' => '',
    'user[prenom]' => 'Jean',
    'user[email]' => 'jean.dupont@test.com',
    'user[telephone]' => '0612345678',
    'user[role]' => 'CANDIDAT',
    'user[password]' => '',
    'user[_token]' => $tok,
]);
echo "POST empty nom: {$r['code']}\n";
if (str_contains($r['body'], 'should not be blank')) echo "=> VALIDATION: should not be blank\n";
if (str_contains($r['body'], 'ne peut pas')) echo "=> VALIDATION: ne peut pas\n";
if (str_contains($r['body'], 'The CSRF token is invalid')) echo "=> CSRF INVALID\n";
if ($r['code'] === 500) {
    preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $r['body'], $h1);
    preg_match('/<h2[^>]*class="exception-message"[^>]*>(.*?)<\/h2>/s', $r['body'], $h2);
    echo "Error H1: " . strip_tags($h1[1] ?? '') . "\n";
    echo "Error H2: " . strip_tags($h2[1] ?? '') . "\n";
}

@unlink($cookieFile);
