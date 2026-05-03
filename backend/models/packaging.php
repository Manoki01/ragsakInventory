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

            $checkStmt = $this->conn->prepare("
                SELECT quantity
                FROM tbl_packaging
                WHERE packagingID = ?
                AND deleted_at IS NULL
                LIMIT 1
            ");
            $checkStmt->bind_param("i", $packaging);

            if (!$checkStmt->execute()) {
                throw new Exception("Failed to locate packaging");
            }

            $result = $checkStmt->get_result();

            if (!$result || $result->num_rows === 0) {
                $this->conn->rollback();
                return false;
            }

            $previousQuantity = (int) $result->fetch_assoc()['quantity'];
            $finalQuantity = $previousQuantity + (int) $data['quantity'];

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

            $status = "success";
            $transactionAction = "Stock In";
            $transactionStmt = $this->conn->prepare("
                INSERT INTO tbl_packagingTransactions (
                    userID,
                    packagingID,
                    status,
                    quantity,
                    action
                )
                VALUES (?, ?, ?, ?, ?)
            ");
            $transactionStmt->bind_param(
                "iisis",
                $data['userID'],
                $packaging,
                $status,
                $data['quantity'],
                $transactionAction
            );

            if (!$transactionStmt->execute()) {
                throw new Exception("Failed to log packaging transaction");
            }

            $changelogAction = "Increase";
            $reason = "Stock In";
            $changelogStmt = $this->conn->prepare("
                INSERT INTO tbl_packagingChangelogs (
                    packagingID,
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
                $packaging,
                $changelogAction,
                $data['quantity'],
                $previousQuantity,
                $finalQuantity,
                $reason
            );

            if (!$changelogStmt->execute()) {
                throw new Exception("Failed to log packaging changelog");
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
        $this->conn->begin_transaction();

        try {
            $checkStmt = $this->conn->prepare("
                SELECT quantity
                FROM tbl_packaging
                WHERE packagingID = ?
                AND deleted_at IS NULL
                LIMIT 1
            ");
            $checkStmt->bind_param("i", $data['packagingID']);

            if (!$checkStmt->execute()) {
                throw new Exception("Failed to locate packaging");
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
                UPDATE tbl_packaging
                SET quantity = ?
                WHERE packagingID = ?
                AND deleted_at IS NULL
            ");

            $stmt->bind_param(
                "ii",
                $newQuantity,
                $data['packagingID']
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to update packaging stock");
            }

            $status = "success";
            $transactionAction = "Stock Update";
            $transactionStmt = $this->conn->prepare("
                INSERT INTO tbl_packagingTransactions (
                    userID,
                    packagingID,
                    status,
                    quantity,
                    action
                )
                VALUES (?, ?, ?, ?, ?)
            ");
            $transactionStmt->bind_param(
                "iisis",
                $data['userID'],
                $data['packagingID'],
                $status,
                $newQuantity,
                $transactionAction
            );

            if (!$transactionStmt->execute()) {
                throw new Exception("Failed to log packaging transaction");
            }

            $changelogAction = $difference > 0 ? "Increase" : "Decrease";
            $changelogQuantity = abs($difference);
            $reason = "Edit Stock";
            $changelogStmt = $this->conn->prepare("
                INSERT INTO tbl_packagingChangelogs (
                    packagingID,
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
                $data['packagingID'],
                $changelogAction,
                $changelogQuantity,
                $previousQuantity,
                $newQuantity,
                $reason
            );

            if (!$changelogStmt->execute()) {
                throw new Exception("Failed to log packaging changelog");
            }

            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();
            error_log("Transaction failed: " . $e->getMessage());
            return false;
        }
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
