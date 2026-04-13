<?php

class Database {
    private static $host = 'localhost';
    private static $user = 'inventory_user';
    private static $pass = 'ragsakInventory123';
    private static $db = 'ragsak_inventoryDB';

    public static function connect() {
        $conn = new mysqli(
            self::$host,
            self::$user,
            self::$pass,
            self::$db
        );

        if($conn->connect_error) {
            die('Database connection failed');
        }

        return $conn;
    }
}