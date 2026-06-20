<?php
// /de/actions/webauthn_delete.php
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
$credential_id = $input['credential_id'] ?? null;

if (!$credential_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Credential ID required']);
    exit;
}

$user_id = $_SESSION['user_id'];

$success = delete_webauthn_credential($conn, $credential_id, $user_id);

if ($success) {
    // Kaskadierende Invalidierung aller anderen Sessions
    $stmt = $conn->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Die aktuelle Session auf die neue Version updaten, damit der User NICHT ausgeloggt wird
    $stmt_check = $conn->prepare("SELECT session_version FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $res = $stmt_check->get_result()->fetch_assoc();
    $_SESSION["session_version"] = $res["session_version"];
    
    echo json_encode(["success" => true, "message" => "Credential deleted"]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete credential']);
}
?>
