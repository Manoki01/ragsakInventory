<?php

require_once __DIR__ . '../../config/database.php';

class Raw_Material {
    private $conn;

    public function __construct(){
        $this->conn = Database::connect();
    }

    public function getAll() {
        $query = "SELECT * FROM tbl_rawMaterials";

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
}
