<?php

require_once __DIR__ .  '../../config/database.php';

class Packaging {
    private $conn;

    public function __construct(){
        $this->conn = Database::connect();
    }

    public function getAll() {
        $query = "SELECT * FROM tbl_packaging WHERE deleted_at IS NULL";

        $result = $this->conn->query($query);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function packagingExistsIgnoreCase($packagingName) {
        $stmt = $this->conn->prepare("
            SELECT packagingID
            FROM tbl_packaging
            WHERE LOWER(packagingName) = LOWER(?)
            LIMIT 1
        ");

        $stmt->bind_param("s", $packagingName);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate packaging name");
        }

        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function packagingNameExistsForOtherPackaging($packagingName, $packagingID) {
        $stmt = $this->conn->prepare("
            SELECT packagingID
            FROM tbl_packaging
            WHERE LOWER(packagingName) = LOWER(?)
            AND packagingID <> ?
            LIMIT 1
        ");

        $stmt->bind_param("si", $packagingName, $packagingID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate packaging name");
        }

        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function updatePackagingInfo($data) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_packaging
            SET packagingName = ?, unitType = ?, packagingPrice = ?, packagingType = ?
            WHERE packagingID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param(
            "ssdsi",
            $data['unitName'],
            $data['unitType'],
            $data['unitPrice'],
            $data['packagingType'],
            $data['packagingID']
        );

        return $stmt->execute();
    }

    public function createPackaging($data) {
        $this->conn->begin_transaction();

        try {
            $quantity = 0;
            $stmt = $this->conn->prepare("
            INSERT INTO tbl_packaging (packagingName, quantity, unitType, packagingPrice, packagingType) 
            VALUES (?, ?, ?, ?, ?)");
            
            $stmt->bind_param(
                "sisis",
                $data['unitName'],
                $quantity,
                $data['unitType'],
                $data['unitPrice'],
                $data['packagingType']
            );

            if(!$stmt->execute()) {
                throw new Exception("Failed to add Packaging");
            }

            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();

            error_log("Transaction failed: " . $e->getMessage());

            return false;
        }
    }

    public function stockPackaging($data) {
        $this->conn->begin_transaction();

        try {
            $packaging = $data['packagingID'];

            $updateStmt = $this->conn->prepare("
                UPDATE tbl_packaging SET quantity = quantity + ? 
                WHERE packagingID = ?
                AND deleted_at IS NULL
            ");

            $updateStmt->bind_param(
                "ii",
                $data['quantity'],
                $packaging
            );

            if(!$updateStmt->execute()) {
                throw new Exception("Failed to update stock");
            }

            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();

            error_log("Transaction failed: " . $e->getMessage());

            return false;
        }
    }

    public function updatePackagingStock($data) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_packaging
            SET quantity = ?
            WHERE packagingID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param(
            "ii",
            $data['quantity'],
            $data['packagingID']
        );

        return $stmt->execute();
    }

    public function archivePackaging($packagingID) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_packaging
            SET deleted_at = NOW()
            WHERE packagingID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param("i", $packagingID);

        if (!$stmt->execute()) {
            return false;
        }

        return $stmt->affected_rows > 0;
    }
}
