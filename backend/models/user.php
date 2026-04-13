<?php

require_once __DIR__ . '../../config/database.php';

class User {
    private $conn;

    public function __construct() {
        $this->conn = Database::connect();
    }

    public function login($data) {
        session_start();

        $stmt = $this->conn->prepare("SELECT 
            u.userID, 
            u.role,
            p.password
            FROM tbl_user u
            INNER JOIN tbl_password p ON u.passwordID = p.passwordID
            WHERE u.username = ?
        ");

        $stmt->bind_param("s", $data['username']);

        if (!$stmt->execute()) {
            throw new Exception("Failed to connect to database");
        }

        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            return false;
        }

        $hashedPassword = '';
        $stmt->bind_result($userID, $role, $hashedPassword);
        $stmt->fetch();

        if (!password_verify($data['password'], $hashedPassword)) {
            return false;
        }

        $_SESSION['userID'] = $userID;
        $_SESSION['role'] = $role;

        return true;
    }

    public function registration($data) {
        $this->conn->begin_transaction();

        try {
            $stmt1 = $this->conn->prepare("
            INSERT INTO tbl_password (password)
            VALUES (?)");

            $stmt1->bind_param("s", $data["password"]);

            if(!$stmt1->execute()) {
                throw new Exception("Failed to register user.");
            }

            $passwordID = $this->conn->insert_id;

            $stmt2 = $this->conn->prepare("
            INSERT INTO tbl_user (username, passwordID, role)
            VALUES (?, ?, ?)");

            $stmt2->bind_param("sis", $data["username"], $passwordID, $data["role"]);

            if(!$stmt2->execute()) {
                throw new Exception("Failed to register user.");
            }

            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();
            echo "Transaction failed: " . $e->getMessage();
            return false;
        }
    }
}