<?php
// /de/actions/webauthn_register_challenge.php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/webauthn_helpers.php';

// Login prüfen
if (!$is_logged_in) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Generiere Challenge für Registration
$challenge = generate_webauthn_challenge();
create_webauthn_challenge($conn, $user_id, $challenge, 'registration');

// Hole WebAuthn Konfiguration
$config = get_webauthn_config();

$existing_credentials = get_user_webauthn_credentials($conn, $user_id);
$excludeCredentials = [];
foreach ($existing_credentials as $cred) {
    $excludeCredentials[] = [
        'type' => 'public-key',
        'id' => $cred['credential_id_b64']
    ];
}

echo json_encode([
    'challenge' => $challenge,
    'rp' => [
        'name' => defined('APP_NAME') ? APP_NAME : 'Cloud',
        'id' => $config['rp_id']
    ],
    'user' => [
        'id' => base64_encode($user_id . ':' . $username),
        'name' => $username,
        'displayName' => $username
    ],
    'pubKeyCredParams' => [
        ['alg' => -7, 'type' => 'public-key'], // ES256
        ['alg' => -257, 'type' => 'public-key'] // RS256
    ],
    'authenticatorSelection' => [
        'residentKey' => 'required',
        'requireResidentKey' => true,
        'userVerification' => 'preferred'
    ],
    'timeout' => 60000,
    'attestation' => 'none',
    'excludeCredentials' => $excludeCredentials
]);
?>