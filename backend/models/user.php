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
            u.status,
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
        $stmt->bind_result($userID, $username, $role, $status, $hashedPassword);
        $stmt->fetch();

        if (!password_verify($data['password'], $hashedPassword)) {
            return false;
        }

        if ($status !== 'approved') {
            return [
                'loginBlocked' => true,
                'status' => $status
            ];
        }

        return [
            'userID' => (int) $userID,
            'username' => $username,
            'role' => $role,
            'status' => $status
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
            INSERT INTO tbl_user (username, passwordID, role, status)
            VALUES (?, ?, ?, 'pending')");

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

    public function getApprovalDataset() {
        return [
            'summary' => $this->getApprovalSummary(),
            'pendingUsers' => $this->getPendingUsers()
        ];
    }

    public function getApprovalSummary() {
        $result = $this->conn->query("
            SELECT
                COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
                COALESCE(SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END), 0) AS denied
            FROM tbl_user
        ");

        if (!$result) {
            throw new Exception("Failed to load user summary");
        }

        return $result->fetch_assoc();
    }

    public function getPendingUsers() {
        $result = $this->conn->query("
            SELECT userID, username, role, status
            FROM tbl_user
            WHERE status = 'pending'
            ORDER BY userID ASC
        ");

        if (!$result) {
            throw new Exception("Failed to load pending users");
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function updateStatus($userID, $status) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_user
            SET status = ?
            WHERE userID = ?
            AND status = 'pending'
        ");

        $stmt->bind_param("si", $status, $userID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update user status");
        }

        return $stmt->affected_rows > 0;
    }
}
