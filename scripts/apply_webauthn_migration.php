<?php
// Simple migration for WebAuthn schema

// Use environment variables (from .env or server) instead of hardcoded credentials
$servername = getenv('DB_SERVER') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: '';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = file_get_contents(__DIR__ . '/../db/migrations/001_webauthn_schema.sql');

if ($conn->multi_query($sql)) {
    echo "Migration applied successfully.\n";
} else {
    echo "Error applying migration: " . $conn->error . "\n";
}

$conn->close();
?>