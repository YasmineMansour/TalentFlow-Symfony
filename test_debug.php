<?php
// Debug: check what happens with a valid user POST
$cookieFile = __DIR__ . '/test_debug_cookies.txt';
@unlink($cookieFile);

$ch = curl_init('http://127.0.0.1:8000/user/new');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$page = curl_exec($ch);
curl_close($ch);

preg_match('/name="user\[_token\]"[^>]*value="([^"]+)"/', $page, $m);
$token = $m[1] ?? 'no-token';
echo "Token: $token\n";

$ch = curl_init('http://127.0.0.1:8000/user/new');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'user[nom]' => 'Dupont',
    'user[prenom]' => 'Jean',
    'user[email]' => 'jean.dupont@test.com',
    'user[telephone]' => '0612345678',
    'user[role]' => 'CANDIDAT',
    'user[password]' => 'password123',
    'user[_token]' => $token,
]));
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($resp, 0, $headerSize);
$body = substr($resp, $headerSize);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Headers:\n$headers\n";

if ($code !== 302) {
    // Search for errors
    if (preg_match('/<title>(.*?)<\/title>/s', $body, $tm)) {
        echo "Title: " . strip_tags($tm[1]) . "\n";
    }
    if (preg_match('/exception-message[^>]*>(.*?)<\//s', $body, $em)) {
        echo "Exception: " . strip_tags($em[1]) . "\n";
    }
    // Check for form errors
    if (preg_match_all('/class="[^"]*error[^"]*"[^>]*>(.*?)<\//s', $body, $errs)) {
        foreach ($errs[1] as $e) {
            echo "Form error: " . strip_tags(trim($e)) . "\n";
        }
    }
    // Look for any validation messages
    if (preg_match_all('/<li>(.*?)<\/li>/s', $body, $lis)) {
        foreach ($lis[1] as $li) {
            $li = trim(strip_tags($li));
            if ($li && strlen($li) < 200) echo "Li: $li\n";
        }
    }
}

@unlink($cookieFile);
