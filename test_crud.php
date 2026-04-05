<?php
/**
 * Test CRUD + Validation for User, Post, Comment
 * Usage: php test_crud.php
 */

$base = 'http://127.0.0.1:8000';

function request(string $method, string $url, array $data = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/test_cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/test_cookies.txt');

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => $body, 'headers' => $headers];
}

function extractToken(string $body, string $formName): string
{
    preg_match('/name="' . preg_quote($formName) . '\[_token\]"[^>]*value="([^"]+)"/', $body, $m);
    return $m[1] ?? '';
}

function extractCsrfDelete(string $body, string $id): string
{
    preg_match('/delete' . $id . '[^"]*/', $body, $m);
    return $m[0] ?? '';
}

function test(string $name, bool $condition): void
{
    $status = $condition ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
    echo "  [$status] $name\n";
}

echo "\n========================================\n";
echo "  TESTS CRUD + CONTROLE DE SAISIE\n";
echo "========================================\n";

// ========== USER CRUD ==========
echo "\n--- USER CRUD ---\n";

// 1. Test index
$r = request('GET', "$base/user/");
test("GET /user/ => 200", $r['code'] === 200);
test("Page contient 'Utilisateurs'", str_contains($r['body'], 'Utilisateurs'));

// 2. Test new form
$r = request('GET', "$base/user/new");
test("GET /user/new => 200", $r['code'] === 200);
$token = extractToken($r['body'], 'user');
test("Formulaire contient CSRF token", !empty($token));

// 3. Test validation - submit empty form
$r = request('POST', "$base/user/new", [
    'user[nom]' => '',
    'user[prenom]' => '',
    'user[email]' => '',
    'user[telephone]' => '',
    'user[role]' => 'CANDIDAT',
    'user[password]' => '',
    'user[_token]' => $token,
]);
test("POST vide => reste sur la page (200)", $r['code'] === 200);
test("Erreur validation: champs vides detectes", str_contains($r['body'], 'This value should not be blank') || str_contains($r['body'], 'ne peut pas'));

// 4. Test validation - invalid email
$token = extractToken($r['body'], 'user');
$r = request('POST', "$base/user/new", [
    'user[nom]' => 'Dupont',
    'user[prenom]' => 'Jean',
    'user[email]' => 'invalid-email',
    'user[telephone]' => '0612345678',
    'user[role]' => 'CANDIDAT',
    'user[password]' => '123456',
    'user[_token]' => $token,
]);
test("POST email invalide => validation error", $r['code'] === 200 && (str_contains($r['body'], 'not a valid email') || str_contains($r['body'], 'email')));

// 5. Test validation - password too short
$token = extractToken($r['body'], 'user');
$r = request('POST', "$base/user/new", [
    'user[nom]' => 'Dupont',
    'user[prenom]' => 'Jean',
    'user[email]' => 'jean@test.com',
    'user[telephone]' => '0612345678',
    'user[role]' => 'CANDIDAT',
    'user[password]' => 'ab',
    'user[_token]' => $token,
]);
test("POST mot de passe trop court => validation error", $r['code'] === 200 && (str_contains($r['body'], 'au moins') || str_contains($r['body'], 'too short') || str_contains($r['body'], 'caractères')));

// 6. Test valid create
$token = extractToken($r['body'], 'user');
$r = request('POST', "$base/user/new", [
    'user[nom]' => 'Dupont',
    'user[prenom]' => 'Jean',
    'user[email]' => 'jean.dupont@test.com',
    'user[telephone]' => '0612345678',
    'user[role]' => 'CANDIDAT',
    'user[password]' => 'password123',
    'user[_token]' => $token,
]);
test("POST valide => redirect 302", $r['code'] === 302);
test("Redirect vers /user/", str_contains($r['headers'], '/user/'));

// 7. Verify user in list
$r = request('GET', "$base/user/");
test("User 'Dupont' visible dans la liste", str_contains($r['body'], 'Dupont'));
test("User 'Jean' visible dans la liste", str_contains($r['body'], 'Jean'));

// Extract user ID from the list
preg_match('/\/user\/(\d+)/', $r['body'], $userIdMatch);
$userId = $userIdMatch[1] ?? '1';

// 8. Test show
$r = request('GET', "$base/user/$userId");
test("GET /user/$userId => 200", $r['code'] === 200);
test("Page show contient 'Dupont'", str_contains($r['body'], 'Dupont'));

// 9. Test edit form
$r = request('GET', "$base/user/$userId/edit");
test("GET /user/$userId/edit => 200", $r['code'] === 200);
$token = extractToken($r['body'], 'user');

// 10. Test edit with validation error (empty nom)
$r = request('POST', "$base/user/$userId/edit", [
    'user[nom]' => '',
    'user[prenom]' => 'Jean',
    'user[email]' => 'jean.dupont@test.com',
    'user[telephone]' => '0612345678',
    'user[role]' => 'CANDIDAT',
    'user[password]' => '',
    'user[_token]' => $token,
]);
test("EDIT nom vide => validation error", $r['code'] === 200);

// 11. Test valid edit
$token = extractToken($r['body'], 'user');
$r = request('POST', "$base/user/$userId/edit", [
    'user[nom]' => 'Martin',
    'user[prenom]' => 'Pierre',
    'user[email]' => 'pierre.martin@test.com',
    'user[telephone]' => '0698765432',
    'user[role]' => 'RECRUTEUR',
    'user[password]' => '',
    'user[_token]' => $token,
]);
test("EDIT valide => redirect 302", $r['code'] === 302);

// Verify edit
$r = request('GET', "$base/user/");
test("User modifie: 'Martin' visible", str_contains($r['body'], 'Martin'));
test("User modifie: 'Pierre' visible", str_contains($r['body'], 'Pierre'));

// ========== POST CRUD ==========
echo "\n--- POST CRUD ---\n";

// 1. Test index
$r = request('GET', "$base/post/");
test("GET /post/ => 200", $r['code'] === 200);

// 2. Test new form
$r = request('GET', "$base/post/new");
test("GET /post/new => 200", $r['code'] === 200);
$token = extractToken($r['body'], 'post');

// 3. Test validation - submit empty
$r = request('POST', "$base/post/new", [
    'post[title]' => '',
    'post[content]' => '',
    'post[author]' => '',
    'post[_token]' => $token,
]);
test("POST vide => validation error (200)", $r['code'] === 200);
test("Erreur: titre vide", str_contains($r['body'], 'ne peut pas') || str_contains($r['body'], 'not be blank'));

// 4. Test validation - title too short
$token = extractToken($r['body'], 'post');
$r = request('POST', "$base/post/new", [
    'post[title]' => 'Ab',
    'post[content]' => 'short',
    'post[author]' => $userId,
    'post[_token]' => $token,
]);
test("POST titre trop court => validation error", $r['code'] === 200 && (str_contains($r['body'], 'au moins') || str_contains($r['body'], 'caractères') || str_contains($r['body'], 'too short')));

// 5. Test valid create
$token = extractToken($r['body'], 'post');
$r = request('POST', "$base/post/new", [
    'post[title]' => 'Mon premier post de test',
    'post[content]' => 'Ceci est le contenu de mon test qui fait plus de dix caractères évidemment.',
    'post[author]' => $userId,
    'post[_token]' => $token,
]);
test("POST valide => redirect 302", $r['code'] === 302);

// 6. Verify in list
$r = request('GET', "$base/post/");
test("Post visible dans la liste", str_contains($r['body'], 'Mon premier post'));

preg_match('/\/post\/(\d+)/', $r['body'], $postIdMatch);
$postId = $postIdMatch[1] ?? '1';

// 7. Test show
$r = request('GET', "$base/post/$postId");
test("GET /post/$postId => 200", $r['code'] === 200);
test("Page show contient le titre", str_contains($r['body'], 'Mon premier post'));

// 8. Test edit
$r = request('GET', "$base/post/$postId/edit");
test("GET /post/$postId/edit => 200", $r['code'] === 200);
$token = extractToken($r['body'], 'post');

$r = request('POST', "$base/post/$postId/edit", [
    'post[title]' => 'Post modifié avec succès',
    'post[content]' => 'Le contenu a été modifié pour tester la mise à jour du CRUD.',
    'post[author]' => $userId,
    'post[_token]' => $token,
]);
test("EDIT post valide => redirect 302", $r['code'] === 302);

$r = request('GET', "$base/post/");
test("Post modifié visible", str_contains($r['body'], 'Post modifi'));

// ========== COMMENT CRUD ==========
echo "\n--- COMMENT CRUD ---\n";

// 1. Test index
$r = request('GET', "$base/comment/");
test("GET /comment/ => 200", $r['code'] === 200);

// 2. Test new form
$r = request('GET', "$base/comment/new");
test("GET /comment/new => 200", $r['code'] === 200);
$token = extractToken($r['body'], 'comment');

// 3. Test validation - submit empty
$r = request('POST', "$base/comment/new", [
    'comment[content]' => '',
    'comment[post]' => '',
    'comment[author]' => '',
    'comment[_token]' => $token,
]);
test("POST vide => validation error (200)", $r['code'] === 200);
test("Erreur: commentaire vide", str_contains($r['body'], 'ne peut pas') || str_contains($r['body'], 'not be blank'));

// 4. Test validation - content too short
$token = extractToken($r['body'], 'comment');
$r = request('POST', "$base/comment/new", [
    'comment[content]' => 'A',
    'comment[post]' => $postId,
    'comment[author]' => $userId,
    'comment[_token]' => $token,
]);
test("POST commentaire trop court => validation error", $r['code'] === 200 && (str_contains($r['body'], 'au moins') || str_contains($r['body'], 'caractères')));

// 5. Test valid create
$token = extractToken($r['body'], 'comment');
$r = request('POST', "$base/comment/new", [
    'comment[content]' => 'Ceci est un commentaire de test parfaitement valide.',
    'comment[post]' => $postId,
    'comment[author]' => $userId,
    'comment[_token]' => $token,
]);
test("POST valide => redirect 302", $r['code'] === 302);

// 6. Verify in list
$r = request('GET', "$base/comment/");
test("Commentaire visible dans la liste", str_contains($r['body'], 'commentaire de test'));

preg_match('/\/comment\/(\d+)/', $r['body'], $commentIdMatch);
$commentId = $commentIdMatch[1] ?? '1';

// 7. Test show
$r = request('GET', "$base/comment/$commentId");
test("GET /comment/$commentId => 200", $r['code'] === 200);

// 8. Test edit
$r = request('GET', "$base/comment/$commentId/edit");
test("GET /comment/$commentId/edit => 200", $r['code'] === 200);
$token = extractToken($r['body'], 'comment');

$r = request('POST', "$base/comment/$commentId/edit", [
    'comment[content]' => 'Commentaire modifié pour tester la mise à jour.',
    'comment[post]' => $postId,
    'comment[author]' => $userId,
    'comment[_token]' => $token,
]);
test("EDIT commentaire valide => redirect 302", $r['code'] === 302);

// ========== DELETE TESTS ==========
echo "\n--- DELETE (nettoyage) ---\n";

// Delete comment
$r = request('GET', "$base/comment/");
$r = request('POST', "$base/comment/$commentId/delete", [
    '_token' => "fake-token",
]);
test("DELETE comment sans CSRF => redirect (pas de suppression)", $r['code'] === 302);

// Check comment still exists (bad CSRF)
$r = request('GET', "$base/comment/$commentId");
test("Comment toujours present (CSRF invalide)", $r['code'] === 200);

// Delete with valid approach - we need the real CSRF token from the page
$r = request('GET', "$base/comment/");
preg_match('/csrf_token.*?delete' . $commentId . '.*?value="([^"]+)"/', $r['body'], $csrfMatch);
// The token is rendered in the template as csrf_token('delete' ~ comment.id)
preg_match('/name="_token" value="([^"]+)"/', $r['body'], $csrfMatch2);
$deleteToken = $csrfMatch2[1] ?? '';

// Actually, let's extract it properly
preg_match_all('/action="\/comment\/' . $commentId . '\/delete".*?value="([^"]+)"/s', $r['body'], $dtMatch);
$deleteToken = $dtMatch[1][0] ?? '';

$r = request('POST', "$base/comment/$commentId/delete", [
    '_token' => $deleteToken,
]);
test("DELETE comment avec CSRF valide => redirect 302", $r['code'] === 302);

// Delete post
$r = request('GET', "$base/post/");
preg_match_all('/action="\/post\/' . $postId . '\/delete".*?value="([^"]+)"/s', $r['body'], $dtMatch);
$deleteToken = $dtMatch[1][0] ?? '';
$r = request('POST', "$base/post/$postId/delete", [
    '_token' => $deleteToken,
]);
test("DELETE post => redirect 302", $r['code'] === 302);

// Delete user
$r = request('GET', "$base/user/");
preg_match_all('/action="\/user\/' . $userId . '\/delete".*?value="([^"]+)"/s', $r['body'], $dtMatch);
$deleteToken = $dtMatch[1][0] ?? '';
$r = request('POST', "$base/user/$userId/delete", [
    '_token' => $deleteToken,
]);
test("DELETE user => redirect 302", $r['code'] === 302);

// Verify all deleted
$r = request('GET', "$base/user/");
test("User supprimé de la liste", !str_contains($r['body'], 'Martin'));

echo "\n========================================\n";
echo "  TESTS TERMINES !\n";
echo "========================================\n\n";

// Cleanup
@unlink(__DIR__ . '/test_cookies.txt');
