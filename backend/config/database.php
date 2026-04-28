<?php

require_once __DIR__ . '/jwt.php';

class Database {
    public static function connect() {
        $host = getRequiredEnv('DB_HOST');
        $user = getRequiredEnv('DB_USER');
        $pass = getRequiredEnv('DB_PASS');
        $db = getRequiredEnv('DB_NAME');

        $conn = new mysqli(
            $host,
            $user,
            $pass,
            $db
        );

        if($conn->connect_error) {
            throw new RuntimeException('Database connection failed');
        }

        return $conn;
    }
}
