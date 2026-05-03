<?php

require_once __DIR__ . '../../config/database.php';

class Raw_Material {
    private $conn;

    public function __construct(){
        $this->conn = Database::connect();
    }

    public function getAll() {
        $query = "SELECT * FROM tbl_rawMaterials WHERE deleted_at IS NULL";

        $result = $this->conn->query($query);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function rawMaterialExistsIgnoreCase($rawMaterialName) {
        $stmt = $this->conn->prepare("
            SELECT rawMaterialID
            FROM tbl_rawMaterials
            WHERE LOWER(rawMaterialName) = LOWER(?)
            LIMIT 1
        ");

        $stmt->bind_param("s", $rawMaterialName);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate raw material name");
        }

        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function rawMaterialNameExistsForOtherMaterial($rawMaterialName, $rawMaterialID) {
        $stmt = $this->conn->prepare("
            SELECT rawMaterialID
            FROM tbl_rawMaterials
            WHERE LOWER(rawMaterialName) = LOWER(?)
            AND rawMaterialID <> ?
            LIMIT 1
        ");

        $stmt->bind_param("si", $rawMaterialName, $rawMaterialID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate raw material name");
        }

        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function updateRawMaterialInfo($data) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_rawMaterials
            SET rawMaterialName = ?, unitType = ?, rawMaterialprice = ?
            WHERE rawMaterialID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param(
            "ssdi",
            $data['unitName'],
            $data['unitType'],
            $data['unitPrice'],
            $data['rawMaterialID']
        );

        return $stmt->execute();
    }

    public function createRawMaterial($data) {
        $this->conn->begin_transaction();

        try {
            $quantity = 0;
            $stmt = $this->conn->prepare("
            INSERT INTO tbl_rawMaterials (rawMaterialName, quantity, unitType, rawMaterialprice, matType) 
            VALUES (?, ?, ?, ?, ? )");
            
            $stmt->bind_param(
                "sisis",
                $data['unitName'],
                $quantity,
                $data['unitType'],
                $data['unitPrice'],
                $data['materialType']
            );

            if(!$stmt->execute()) {
                throw new Exception("Failed to add Raw Material");
            }

            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();

            error_log("Transaction failed: " . $e->getMessage());

            return false;
        }
    }

    public function stockRawMaterial($data) {
        $this->conn->begin_transaction();

        try {
            $rawMat = $data['rawMaterialID'];

            $updateStmt = $this->conn->prepare("
                UPDATE tbl_rawMaterials 
                SET quantity = quantity + ? 
                WHERE rawMaterialID = ?
                AND deleted_at IS NULL
            ");

            $updateStmt->bind_param(
                "ii",
                $data['quantity'],
                $rawMat
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

    public function updateRawMaterialStock($data) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_rawMaterials
            SET quantity = ?
            WHERE rawMaterialID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param(
            "ii",
            $data['quantity'],
            $data['rawMaterialID']
        );

        return $stmt->execute();
    }

    public function archiveRawMaterial($rawMaterialID) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_rawMaterials
            SET deleted_at = NOW()
            WHERE rawMaterialID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param("i", $rawMaterialID);

        if (!$stmt->execute()) {
            return false;
        }

        return $stmt->affected_rows > 0;
    }
}
