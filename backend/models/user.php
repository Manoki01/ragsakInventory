<?php

require_once __DIR__ . '../../config/database.php';

class User {
    private $conn;

    public function __construct() {
        $this->conn = Database::connect();
    }

    public function login($data) {
        $stmt = $this->conn->prepare("SELECT 
            u.userID, 
            u.username,
            u.role,
            p.password
            FROM tbl_user u
            INNER JOIN tbl_password p ON u.passwordID = p.passwordID
            WHERE LOWER(u.username) = LOWER(?)
            ORDER BY BINARY u.username = BINARY ? DESC, u.userID ASC
            LIMIT 1
        ");

        $stmt->bind_param("ss", $data['username'], $data['username']);

        if (!$stmt->execute()) {
            throw new Exception("Failed to connect to database");
        }

        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            return false;
        }

        $hashedPassword = '';
        $username = '';
        $stmt->bind_result($userID, $username, $role, $hashedPassword);
        $stmt->fetch();

        if (!password_verify($data['password'], $hashedPassword)) {
            return false;
        }

        return [
            'userID' => (int) $userID,
            'username' => $username,
            'role' => $role
        ];
    }

    public function usernameExistsIgnoreCase($username) {
        $stmt = $this->conn->prepare("
            SELECT userID
            FROM tbl_user
            WHERE LOWER(username) = LOWER(?)
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate username");
        }

        $stmt->store_result();

        return $stmt->num_rows > 0;
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
            error_log("Transaction failed: " . $e->getMessage());
            return false;
        }
    }
}
