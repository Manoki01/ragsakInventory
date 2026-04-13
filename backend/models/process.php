<?php

require_once __DIR__ . '../../config/database.php';

class Process {
    private $conn;

    public function __construct(){
        $this->conn = Database::connect();
    }

    public function getAll() {
        $query = "SELECT * FROM tbl_process";

        $result = $this->conn->query($query);

        return $result->fetch_all(MYSQLI_ASSOC);
    }
}