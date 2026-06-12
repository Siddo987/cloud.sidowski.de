<?php
// /config/webauthn_helpers.php
// WebAuthn-Hilfsfunktionen für passwortlose Anmeldung

use Webauthn\Denormalized\Authenticator;
use Webauthn\Denormalized\AuthenticatorAttestationResponse;
use Webauthn\Denormalized\AuthenticatorAssertionResponse;
use Webauthn\Denormalized\PublicKeyCredentialCreationOptions;
use Webauthn\Denormalized\PublicKeyCredentialRequestOptions;
use Webauthn\Exception\InvalidArgumentException;
use Webauthn\Denormalized\PublicKeyCredentialUserEntity;
use Webauthn\Denormalized\PublicKeyCredentialDescriptor;

/**
 * Generiere eine neue Challenge für WebAuthn Registration oder Authentication
 */
function generate_webauthn_challenge() {
    return base64_encode(random_bytes(64));
}

/**
 * Speichern einer Challenge in der Datenbank
 */
function create_webauthn_challenge($conn, $user_id, $challenge, $type = 'registration') {
    $expires = date('Y-m-d H:i:s', time() + 300); // 5 Minuten Gültigkeit
    $stmt = $conn->prepare("INSERT INTO webauthn_challenges (user_id, challenge, type, expires_at) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('isss', $user_id, $challenge, $type, $expires);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Challenge aus der Datenbank validieren und als "used" markieren
 */
function validate_and_use_challenge($conn, $challenge, $type = 'registration') {
    $now = date('Y-m-d H:i:s');
    
    // Prüfe ob Challenge existiert, valide ist und noch nicht verwendet wurde
    $stmt = $conn->prepare("
        SELECT id, user_id FROM webauthn_challenges 
        WHERE challenge = ? AND type = ? AND used = FALSE AND expires_at > ?
        LIMIT 1
    ");
    if (!$stmt) return null;
    
    $stmt->bind_param('sss', $challenge, $type, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) return null;
    
    // Markiere Challenge als verwendet
    $update_stmt = $conn->prepare("UPDATE webauthn_challenges SET used = TRUE WHERE id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param('i', $row['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    return $row;
}

/**
 * Speichere ein WebAuthn Credential in der Datenbank
 */
function save_webauthn_credential($conn, $user_id, $credential_id_raw, $credential_id_b64, $public_key_raw, $public_key_algo, $aaguid = null, $name = 'Sicherheitsschlüssel') {
    $stmt = $conn->prepare("
        INSERT INTO webauthn_credentials 
        (user_id, credential_id, credential_id_b64, public_key, public_key_algo, aaguid, name)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if ($stmt) {
        $stmt->bind_param('issssss', $user_id, $credential_id_raw, $credential_id_b64, $public_key_raw, $public_key_algo, $aaguid, $name);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Lade alle WebAuthn Credentials eines Users
 */
function get_user_webauthn_credentials($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT id, credential_id, public_key, counter, name, created_at, last_used
        FROM webauthn_credentials 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    if (!$stmt) return [];
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $credentials = [];
    
    while ($row = $result->fetch_assoc()) {
        $credentials[] = $row;
    }
    
    $stmt->close();
    return $credentials;
}

/**
 * Finde ein Credential anhand der credential_id
 */
function get_webauthn_credential_by_id($conn, $credential_id_b64) {
    $stmt = $conn->prepare("
        SELECT id, user_id, credential_id, public_key, public_key_algo, counter, name
        FROM webauthn_credentials 
        WHERE credential_id_b64 = ?
        LIMIT 1
    ");
    if (!$stmt) return null;
    
    $stmt->bind_param('s', $credential_id_b64);
    $stmt->execute();
    $result = $stmt->get_result();
    $credential = $result->fetch_assoc();
    $stmt->close();
    
    return $credential;
}

/**
 * Generiere Challenge für WebAuthn Authentication
 */
function generate_webauthn_auth_challenge($user_id) {
    global $conn;
    
    // Hole alle Credentials des Users
    $credentials = get_user_webauthn_credentials($conn, $user_id);
    if (empty($credentials)) {
        throw new Exception('No credentials found');
    }
    
    // Generiere Challenge
    $challenge = generate_webauthn_challenge();
    
    // Speichere Challenge
    create_webauthn_challenge($conn, $user_id, $challenge, 'authentication');
    
    // Erstelle allowCredentials
    $allowCredentials = [];
    foreach ($credentials as $cred) {
        $allowCredentials[] = [
            'id' => $cred['credential_id'], // base64 encoded
            'type' => 'public-key'
        ];
    }
    
    return [
        'challenge' => $challenge,
        'allowCredentials' => $allowCredentials,
        'userVerification' => 'preferred',
        'timeout' => 60000
    ];
}

/**
 * Validiere WebAuthn Assertion
 */
function validate_webauthn_assertion($user_id, $assertion) {
    global $conn;
    
    // Decode ClientDataJSON to get the actual challenge
    $clientDataJSON_b64 = $assertion['response']['clientDataJSON'];
    $clientDataJSON = base64_decode(strtr($clientDataJSON_b64, '-_', '+/'));
    $clientData = json_decode($clientDataJSON, true);
    
    if (!isset($clientData['challenge'])) {
        return false;
    }
    
    // Hole Challenge
    $challenge_data = validate_and_use_challenge($conn, $clientData['challenge'], 'authentication');
    if (!$challenge_data || $challenge_data['user_id'] != $user_id) {
        return false;
    }
    
    // Hole Credential
    $credential = get_webauthn_credential_by_id($conn, $assertion['id']);
    if (!$credential) {
        return false;
    }
    
    // AuthenticatorData
    $authenticatorData_b64 = $assertion['response']['authenticatorData'];
    $authenticatorData = base64_decode(strtr($authenticatorData_b64, '-_', '+/'));
    
    // Data to verify: authenticatorData + SHA256(clientDataJSON)
    $clientDataHash = hash('sha256', $clientDataJSON, true);
    $dataToVerify = $authenticatorData . $clientDataHash;
    
    // Signature
    $signature_b64 = $assertion['response']['signature'];
    $signature = base64_decode(strtr($signature_b64, '-_', '+/'));
    
    // Verify using Cose algorithm
    $decoder = new \CBOR\Decoder(new \CBOR\Tag\TagManager(), new \CBOR\OtherObject\OtherObjectManager());
    $coseKeyData = $decoder->decode(new \CBOR\StringStream($credential['public_key']))->getNormalizedData();
    $coseKey = \Cose\Key\Key::createFromData($coseKeyData);
    
    $algId = $coseKey->alg();
    $algorithmManager = new \Webauthn\AlgorithmManager([
        new \Cose\Algorithm\Signature\ECDSA\ES256(),
        new \Cose\Algorithm\Signature\ECDSA\ES384(),
        new \Cose\Algorithm\Signature\ECDSA\ES512(),
        new \Cose\Algorithm\Signature\RSA\RS256(),
        new \Cose\Algorithm\Signature\RSA\RS384(),
        new \Cose\Algorithm\Signature\RSA\RS512(),
        new \Cose\Algorithm\Signature\EdDSA\EdDSA(),
    ]);
    
    if (!$algorithmManager->has($algId)) {
        throw new Exception("Unsupported algorithm");
    }
    
    $algorithm = $algorithmManager->get($algId);
    $isValid = $algorithm->verify($dataToVerify, $coseKey, $signature);
    
    if ($isValid) {
        // Update counter
        // AuthenticatorData structure: rpIdHash (32), flags (1), signCount (4)
        $signCountBytes = substr($authenticatorData, 33, 4);
        $signCountData = unpack('N', $signCountBytes);
        $newCounter = $signCountData[1];
        
        // Simple counter check: new counter > old counter, unless both are 0
        if ($newCounter > 0 && $newCounter <= $credential['counter']) {
            return false; // Cloned authenticator or replay
        }
        
        update_credential_counter($conn, $credential['id'], max($newCounter, $credential['counter'] + 1));
        return true;
    }
    
    return false;
}

/**
 * Lösche ein WebAuthn Credential
 */
function delete_webauthn_credential($conn, $credential_id, $user_id) {
    $stmt = $conn->prepare("
        DELETE FROM webauthn_credentials 
        WHERE id = ? AND user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('ii', $credential_id, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Aktualisiere den Counter eines WebAuthn Credentials
 */
function update_credential_counter($conn, $credential_id, $counter) {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        UPDATE webauthn_credentials 
        SET counter = ?, last_used = ?
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('isi', $counter, $now, $credential_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Cleanup abgelaufener Challenges (wird periodisch aufgerufen)
 */
function cleanup_expired_challenges($conn) {
    $stmt = $conn->prepare("DELETE FROM webauthn_challenges WHERE expires_at < NOW()");
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Hole WebAuthn Server-Konfiguration (Origin, RP-ID, etc.)
 */
function get_webauthn_config() {
    $base_url = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'http://localhost';
    $origin = parse_url($base_url, PHP_URL_SCHEME) . '://' . parse_url($base_url, PHP_URL_HOST);
    $rp_id = parse_url($base_url, PHP_URL_HOST);
    
    return [
        'origin' => $origin,
        'rp_id' => $rp_id,
        'rp_name' => 'Cloud Platform',
        'challenge_timeout' => 300, // 5 Minuten
    ];
}

?>
