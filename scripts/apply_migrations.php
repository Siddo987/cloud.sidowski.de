<?php
// Simple migration runner for local/dev use
// Usage: php scripts/apply_migrations.php

require_once __DIR__ . '/../config/bootstrap.php'; // provides $conn

$dir = __DIR__ . '/../db/migrations';
$files = glob($dir . '/*.sql');
sort($files, SORT_STRING);

if (empty($files)) {
    echo "No migration files found.\n";
    exit(0);
}

// Ensure migrations table exists (in case migration not applied)
$create_migrations_table = "CREATE TABLE IF NOT EXISTS migrations (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL UNIQUE, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
$conn->query($create_migrations_table);

foreach ($files as $file) {
    $name = basename($file);
    // Check if applied
    $stmt = $conn->prepare("SELECT id FROM migrations WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $res = $stmt->get_result();
    $applied = (bool)$res->fetch_assoc();
    $stmt->close();

    if ($applied) {
        echo "Skipping already applied: {$name}\n";
        continue;
    }

    echo "Applying migration: {$name}... ";
    $sql = file_get_contents($file);
    if ($sql === false) { echo "failed to read file\n"; continue; }

    // Use multi_query to allow multiple statements
    if ($conn->multi_query($sql)) {
        // Wait for all results
        do {
            if ($res = $conn->store_result()) { $res->free(); }
        } while ($conn->more_results() && $conn->next_result());

        if ($conn->errno) {
            echo "failed: " . $conn->error . "\n";
            exit(1);
        }

        // Record migration
        $now = date('Y-m-d H:i:s');
        $ins = $conn->prepare("INSERT INTO migrations (name, applied_at) VALUES (?, ?)");
        $ins->bind_param('ss', $name, $now);
        $ins->execute(); $ins->close();
        echo "ok\n";
    } else {
        echo "failed: " . $conn->error . "\n";
        exit(1);
    }
}

echo "All migrations processed.\n";
