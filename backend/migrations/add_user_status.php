<?php

require_once __DIR__ . '/../config/database.php';

$conn = Database::connect();
$column = $conn->query("SHOW COLUMNS FROM tbl_user LIKE 'status'");

if ($column && $column->num_rows === 0) {
    if (!$conn->query("ALTER TABLE tbl_user ADD COLUMN status ENUM('approved','pending','denied') NOT NULL DEFAULT 'pending' AFTER role")) {
        throw new Exception($conn->error);
    }

    if (!$conn->query("UPDATE tbl_user SET status = 'approved'")) {
        throw new Exception($conn->error);
    }

    echo "Added status column and approved existing users\n";
} else {
    echo "status column already exists\n";
}
