<?php
// scripts/cleanup_deleted_users.php
// This script should be run via cron (e.g. daily)

require_once __DIR__ . '/../config/bootstrap.php';
// If this script has special deletion logic for files, we'd include it here.
// For now, we only delete from `users` and linked tables where cascade is not set.

echo "Starting cleanup of deleted users...\n";

// Find users who have been soft-deleted and the 90-day window has expired.
$stmt = $conn->prepare("SELECT id, username FROM users WHERE deleted = 1 AND deletion_recovery_until IS NOT NULL AND deletion_recovery_until <= NOW()");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $users_to_delete = [];
    while ($row = $result->fetch_assoc()) {
        $users_to_delete[] = $row;
    }
    $stmt->close();

    if (count($users_to_delete) === 0) {
        echo "No users pending permanent deletion.\n";
    } else {
        foreach ($users_to_delete as $user) {
            echo "Permanently deleting user ID: " . $user['id'] . " (" . $user['username'] . ")...\n";
            
            // Delete user files (requires file paths and DB cleanup)
            // As discussed, this might be handled by another existing function or we just remove DB entries.
            // If the schema uses ON DELETE CASCADE for files, then just deleting the user is enough.
            // But we will manually select and remove from `files` just in case.
            
            $f_stmt = $conn->prepare("SELECT id, filepath FROM files WHERE uploader_id = ?");
            if ($f_stmt) {
                $f_stmt->bind_param("i", $user['id']);
                $f_stmt->execute();
                $f_res = $f_stmt->get_result();
                while ($file = $f_res->fetch_assoc()) {
                    if (file_exists($file['filepath'])) {
                        unlink($file['filepath']);
                    }
                }
                $f_stmt->close();
            }

            // The DB files row deletion could be cascaded, but just in case:
            $conn->query("DELETE FROM files WHERE uploader_id = " . (int)$user['id']);
            
            // Delete from webauthn_credentials
            $conn->query("DELETE FROM webauthn_credentials WHERE user_id = " . (int)$user['id']);
            
            // Delete from users
            $conn->query("DELETE FROM users WHERE id = " . (int)$user['id']);

            echo "User " . $user['id'] . " deleted.\n";
        }
        echo "Cleanup completed successfully.\n";
    }
} else {
    echo "Error preparing statement: " . $conn->error . "\n";
}
