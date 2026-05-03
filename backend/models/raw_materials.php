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

            $checkStmt = $this->conn->prepare("
                SELECT quantity
                FROM tbl_rawMaterials
                WHERE rawMaterialID = ?
                AND deleted_at IS NULL
                LIMIT 1
            ");
            $checkStmt->bind_param("i", $rawMat);

            if (!$checkStmt->execute()) {
                throw new Exception("Failed to locate raw material");
            }

            $result = $checkStmt->get_result();

            if (!$result || $result->num_rows === 0) {
                $this->conn->rollback();
                return false;
            }

            $previousQuantity = (int) $result->fetch_assoc()['quantity'];
            $finalQuantity = $previousQuantity + (int) $data['quantity'];

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

            $status = "success";
            $transactionAction = "Stock In";
            $transactionStmt = $this->conn->prepare("
                INSERT INTO tbl_rawMatTransactions (
                    userID,
                    rawMaterialID,
                    status,
                    quantity,
                    action
                )
                VALUES (?, ?, ?, ?, ?)
            ");
            $transactionStmt->bind_param(
                "iisis",
                $data['userID'],
                $rawMat,
                $status,
                $data['quantity'],
                $transactionAction
            );

            if (!$transactionStmt->execute()) {
                throw new Exception("Failed to log raw material transaction");
            }

            $changelogAction = "Increase";
            $reason = "Stock In";
            $changelogStmt = $this->conn->prepare("
                INSERT INTO tbl_rawMatChangelogs (
                    rawMaterialID,
                    action,
                    quantity,
                    initialQuantity,
                    finalQuantity,
                    reason
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $changelogStmt->bind_param(
                "isiiis",
                $rawMat,
                $changelogAction,
                $data['quantity'],
                $previousQuantity,
                $finalQuantity,
                $reason
            );

            if (!$changelogStmt->execute()) {
                throw new Exception("Failed to log raw material changelog");
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
        $this->conn->begin_transaction();

        try {
            $checkStmt = $this->conn->prepare("
                SELECT quantity
                FROM tbl_rawMaterials
                WHERE rawMaterialID = ?
                AND deleted_at IS NULL
                LIMIT 1
            ");
            $checkStmt->bind_param("i", $data['rawMaterialID']);

            if (!$checkStmt->execute()) {
                throw new Exception("Failed to locate raw material");
            }

            $result = $checkStmt->get_result();

            if (!$result || $result->num_rows === 0) {
                $this->conn->rollback();
                return false;
            }

            $previousQuantity = (int) $result->fetch_assoc()['quantity'];
            $newQuantity = (int) $data['quantity'];
            $difference = $newQuantity - $previousQuantity;

            if ($difference === 0) {
                $this->conn->commit();
                return true;
            }

            $stmt = $this->conn->prepare("
                UPDATE tbl_rawMaterials
                SET quantity = ?
                WHERE rawMaterialID = ?
                AND deleted_at IS NULL
            ");

            $stmt->bind_param(
                "ii",
                $newQuantity,
                $data['rawMaterialID']
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to update raw material stock");
            }

            $status = "success";
            $transactionAction = "Stock Update";
            $transactionStmt = $this->conn->prepare("
                INSERT INTO tbl_rawMatTransactions (
                    userID,
                    rawMaterialID,
                    status,
                    quantity,
                    action
                )
                VALUES (?, ?, ?, ?, ?)
            ");
            $transactionStmt->bind_param(
                "iisis",
                $data['userID'],
                $data['rawMaterialID'],
                $status,
                $newQuantity,
                $transactionAction
            );

            if (!$transactionStmt->execute()) {
                throw new Exception("Failed to log raw material transaction");
            }

            $changelogAction = $difference > 0 ? "Increase" : "Decrease";
            $changelogQuantity = abs($difference);
            $reason = "Edit Stock";
            $changelogStmt = $this->conn->prepare("
                INSERT INTO tbl_rawMatChangelogs (
                    rawMaterialID,
                    action,
                    quantity,
                    initialQuantity,
                    finalQuantity,
                    reason
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $changelogStmt->bind_param(
                "isiiis",
                $data['rawMaterialID'],
                $changelogAction,
                $changelogQuantity,
                $previousQuantity,
                $newQuantity,
                $reason
            );

            if (!$changelogStmt->execute()) {
                throw new Exception("Failed to log raw material changelog");
            }

            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();
            error_log("Transaction failed: " . $e->getMessage());
            return false;
        }
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
