<?php

require_once __DIR__ . '../../config/database.php';

class Process {
    private $conn;

    public function __construct(){
        $this->conn = Database::connect();
    }

    public function getAll() {
        $query = "SELECT * FROM tbl_process WHERE deleted_at IS NULL ORDER BY processName";

        $result = $this->conn->query($query);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function processExistsIgnoreCase($processName) {
        $stmt = $this->conn->prepare("
            SELECT processID
            FROM tbl_process
            WHERE LOWER(processName) = LOWER(?)
            LIMIT 1
        ");

        $stmt->bind_param("s", $processName);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate process name");
        }

        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function processNameExistsForOtherProcess($processName, $processID) {
        $stmt = $this->conn->prepare("
            SELECT processID
            FROM tbl_process
            WHERE LOWER(processName) = LOWER(?)
            AND processID <> ?
            LIMIT 1
        ");

        $stmt->bind_param("si", $processName, $processID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate process name");
        }

        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function createProcess($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO tbl_process (processName, processDescription)
            VALUES (?, ?)
        ");

        $stmt->bind_param(
            "ss",
            $data['processName'],
            $data['processDescription']
        );

        return $stmt->execute();
    }

    public function updateProcess($data) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_process
            SET processName = ?, processDescription = ?
            WHERE processID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param(
            "ssi",
            $data['processName'],
            $data['processDescription'],
            $data['processID']
        );

        return $stmt->execute();
    }

    public function getDetails($processID) {
        $stmt = $this->conn->prepare("
            SELECT processID, processName, processDescription
            FROM tbl_process
            WHERE processID = ?
            AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->bind_param("i", $processID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to load process");
        }

        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        $process = $result->fetch_assoc();

        $productsStmt = $this->conn->prepare("
            SELECT DISTINCT p.productID, p.productName, p.unitType, p.productPrice
            FROM tbl_processFlow pf
            INNER JOIN tbl_products p ON pf.productID = p.productID
            WHERE pf.processID = ?
            AND p.deleted_at IS NULL
            ORDER BY p.productName
        ");

        $productsStmt->bind_param("i", $processID);

        if (!$productsStmt->execute()) {
            throw new Exception("Failed to load process products");
        }

        $process['products'] = $productsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return $process;
    }

    public function archiveProcess($processID) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_process
            SET deleted_at = NOW()
            WHERE processID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param("i", $processID);

        if (!$stmt->execute()) {
            return false;
        }

        return $stmt->affected_rows > 0;
    }
}
