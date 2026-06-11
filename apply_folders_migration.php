<?php
// Simple migration runner for folders
// Run this on your server: php apply_folders_migration.php

// Database connection
$DB_SERVER = getenv('DB_SERVER') ?: '';
$DB_USERNAME = getenv('DB_USERNAME') ?: '';
$DB_PASSWORD = getenv('DB_PASSWORD') ?: ''; 
$DB_NAME = getenv('DB_NAME') ?: '';

$conn = new mysqli($DB_SERVER, $DB_USERNAME, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to database.\n";

// SQL for folders table
$sql = "
CREATE TABLE IF NOT EXISTS folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    parent_id INT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted TINYINT(1) DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_parent (user_id, parent_id),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";

if ($conn->query($sql) === TRUE) {
    echo "Folders table created or already exists.\n";
} else {
    echo "Error creating folders table: " . $conn->error . "\n";
}

// Check if folder_id column exists
$result = $conn->query("SHOW COLUMNS FROM files LIKE 'folder_id'");
if ($result->num_rows == 0) {
    // Add folder_id column
    $sql2 = "ALTER TABLE files ADD COLUMN folder_id INT NULL AFTER uploader_id;";
    if ($conn->query($sql2) === TRUE) {
        echo "Added folder_id column to files table.\n";
    } else {
        echo "Error adding folder_id column: " . $conn->error . "\n";
    }
} else {
    echo "folder_id column already exists.\n";
}

// Check if foreign key exists
$result = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'files' AND COLUMN_NAME = 'folder_id' AND REFERENCED_TABLE_NAME = 'folders'");
if ($result->num_rows == 0) {
    // Add foreign key
    $sql3 = "ALTER TABLE files ADD CONSTRAINT fk_files_folder_id FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE;";
    if ($conn->query($sql3) === TRUE) {
        echo "Added foreign key for folder_id.\n";
    } else {
        echo "Error adding foreign key: " . $conn->error . "\n";
    }
} else {
    echo "Foreign key for folder_id already exists.\n";
}

// Check if index exists
$result = $conn->query("SHOW INDEX FROM files WHERE Column_name = 'folder_id'");
if ($result->num_rows == 0) {
    // Add index
    $sql4 = "ALTER TABLE files ADD INDEX idx_folder (folder_id);";
    if ($conn->query($sql4) === TRUE) {
        echo "Added index for folder_id.\n";
    } else {
        echo "Error adding index: " . $conn->error . "\n";
    }
} else {
    echo "Index for folder_id already exists.\n";
}

$conn->close();
echo "Migration completed.\n";
?>
