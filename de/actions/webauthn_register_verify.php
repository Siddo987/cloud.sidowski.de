<?php
// /de/actions/webauthn_register_verify.php
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

$input = json_decode(file_get_contents('php://input'), true);
$attestation = $input['attestation'] ?? null;

if (!$attestation) {
    http_response_code(400);
    echo json_encode(['error' => 'Attestation required']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Grundlegende Validierung
if (empty($attestation['id']) || empty($attestation['response']['attestationObject']) || empty($attestation['response']['clientDataJSON'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid attestation format']);
    exit;
}

try {
    // Dekodiere clientDataJSON um Challenge zu extrahieren
    $clientDataJSON_b64 = $attestation['response']['clientDataJSON'];
    $clientDataJSON = base64_decode($clientDataJSON_b64. str_repeat('=', (4 - strlen($clientDataJSON_b64) % 4) % 4), true);
    $clientData = json_decode($clientDataJSON, true);
    
    if (!$clientData || !isset($clientData['challenge'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid client data']);
        exit;
    }
    
    // Validiere Challenge
    $challenge_from_client = $clientData['challenge'];
    $challenge_data = validate_and_use_challenge($conn, $challenge_from_client, 'registration');
    
    if (!$challenge_data || $challenge_data['user_id'] != $user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired challenge']);
        exit;
    }
    
    $credential_id_b64 = $attestation['id'];
    $credential_id_raw = base64_decode(strtr($credential_id_b64, '-_', '+/'));
    $attestationObject_b64 = $attestation['response']['attestationObject'];
    $attestationObjectBinary = base64_decode(strtr($attestationObject_b64, '-_', '+/'));

    // Extract public key and credential ID properly using webauthn-lib
    $attestationStatementSupportManager = new \Webauthn\AttestationStatement\AttestationStatementSupportManager();
    $attestationStatementSupportManager->add(new \Webauthn\AttestationStatement\NoneAttestationStatementSupport());
    $attestationStatementSupportManager->add(new \Webauthn\AttestationStatement\PackedAttestationStatementSupport());
    $attestationStatementSupportManager->add(new \Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport());
    $attestationStatementSupportManager->add(new \Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport());
    $attestationStatementSupportManager->add(new \Webauthn\AttestationStatement\AppleAttestationStatementSupport());

    $attestationObjectLoader = new \Webauthn\AttestationStatement\AttestationObjectLoader($attestationStatementSupportManager);
    $attestationObject = $attestationObjectLoader->load($attestationObjectBinary);
    
    $authData = $attestationObject->getAuthData();
    $credentialData = $authData->getAttestedCredentialData();
    
    if (!$credentialData) {
        throw new Exception("No credential data found in attestation");
    }

    $extracted_credential_id = $credentialData->getCredentialId();
    if ($extracted_credential_id !== $credential_id_raw) {
        throw new Exception("Credential ID mismatch");
    }

    $public_key_raw = $credentialData->getCredentialPublicKey(); // Binary CBOR
    
    $decoder = new \CBOR\Decoder(new \CBOR\Tag\TagManager(), new \CBOR\OtherObject\OtherObjectManager());
    $coseKeyData = $decoder->decode(new \CBOR\StringStream($public_key_raw))->getNormalizedData();
    $coseKey = \Cose\Key\Key::createFromData($coseKeyData);
    $public_key_algo = $coseKey->alg();
    $aaguid = $credentialData->getAaguid()->toString();
    
    $success = save_webauthn_credential(
        $conn,
        $user_id,
        $credential_id_raw,
        $credential_id_b64,
        $public_key_raw,
        $public_key_algo,
        null,
        'Sicherheitsschlüssel'
    );
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Credential registered successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save credential']);
    }
} catch (Exception $e) {
    error_log('WebAuthn register error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
}
?>