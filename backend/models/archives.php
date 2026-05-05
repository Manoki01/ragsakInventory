<?php

require_once __DIR__ . '/../config/database.php';

class Archives {
    private $conn;
    private $allowedTypes = [
        'products',
        'rawMaterials',
        'packaging',
        'processes',
        'orders'
    ];

    public function __construct() {
        $this->conn = Database::connect();
    }

    private function fetchAll($query) {
        $result = $this->conn->query($query);

        if (!$result) {
            throw new Exception($this->conn->error);
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function restoreRow($table, $idColumn, $id) {
        $stmt = $this->conn->prepare("
            UPDATE {$table}
            SET deleted_at = NULL
            WHERE {$idColumn} = ?
            AND deleted_at IS NOT NULL
        ");

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to restore archived record");
        }

        return $stmt->affected_rows > 0;
    }

    private function restoreOrder($orderID, $userID) {
        $this->conn->begin_transaction();

        try {
            $stmt = $this->conn->prepare("
                UPDATE tbl_orders
                SET deleted_at = NULL,
                    updated_at = NOW()
                WHERE orderID = ?
                AND deleted_at IS NOT NULL
            ");

            $stmt->bind_param("i", $orderID);

            if (!$stmt->execute()) {
                throw new Exception("Failed to restore order");
            }

            if ($stmt->affected_rows <= 0) {
                $this->conn->rollback();
                return false;
            }

            $changelogStmt = $this->conn->prepare("
                INSERT INTO tbl_orderChangelogs (orderID, action, initialValue, finalValue)
                VALUES (?, ?, ?, ?)
            ");

            $action = "Restored order";
            $initialValue = "archived";
            $finalValue = "active";
            $changelogStmt->bind_param("isss", $orderID, $action, $initialValue, $finalValue);

            if (!$changelogStmt->execute()) {
                throw new Exception("Failed to log order restore changelog");
            }

            $transactionStmt = $this->conn->prepare("
                INSERT INTO tbl_orderTransactions (userID, orderID, action)
                VALUES (?, ?, ?)
            ");

            $transactionStmt->bind_param("iis", $userID, $orderID, $action);

            if (!$transactionStmt->execute()) {
                throw new Exception("Failed to log order restore transaction");
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function getDataset() {
        return [
            'products' => $this->fetchAll("
                SELECT
                    p.productID AS id,
                    p.productName AS name,
                    p.unitType,
                    p.productPrice AS price,
                    p.deleted_at,
                    COALESCE(GROUP_CONCAT(DISTINCT pr.processName ORDER BY pf.flowOrder SEPARATOR ', '), 'No Process') AS details
                FROM tbl_products p
                LEFT JOIN tbl_processFlow pf ON p.productID = pf.productID
                LEFT JOIN tbl_process pr ON pf.processID = pr.processID
                WHERE p.deleted_at IS NOT NULL
                GROUP BY p.productID, p.productName, p.unitType, p.productPrice, p.deleted_at
                ORDER BY p.deleted_at DESC, p.productID ASC
            "),
            'rawMaterials' => $this->fetchAll("
                SELECT
                    rawMaterialID AS id,
                    rawMaterialName AS name,
                    quantity,
                    unitType,
                    rawMaterialPrice AS price,
                    matType AS details,
                    deleted_at
                FROM tbl_rawMaterials
                WHERE deleted_at IS NOT NULL
                ORDER BY deleted_at DESC, rawMaterialID ASC
            "),
            'packaging' => $this->fetchAll("
                SELECT
                    packagingID AS id,
                    packagingName AS name,
                    quantity,
                    unitType,
                    packagingPrice AS price,
                    packagingType AS details,
                    deleted_at
                FROM tbl_packaging
                WHERE deleted_at IS NOT NULL
                ORDER BY deleted_at DESC, packagingID ASC
            "),
            'processes' => $this->fetchAll("
                SELECT
                    processID AS id,
                    processName AS name,
                    processDescription AS details,
                    deleted_at
                FROM tbl_process
                WHERE deleted_at IS NOT NULL
                ORDER BY deleted_at DESC, processID ASC
            "),
            'orders' => $this->fetchAll("
                SELECT
                    o.orderID AS id,
                    CONCAT('Order #', o.orderID) AS name,
                    o.customerName,
                    o.contactNumber,
                    o.customerAddress,
                    p.productName,
                    o.quantity,
                    o.orderStatus,
                    o.dateCompletion,
                    o.deleted_at,
                    CONCAT(o.customerName, ' - ', p.productName) AS details
                FROM tbl_orders o
                INNER JOIN tbl_products p ON o.productID = p.productID
                WHERE o.deleted_at IS NOT NULL
                ORDER BY o.deleted_at DESC, o.orderID ASC
            ")
        ];
    }

    public function restore($type, $id, $userID) {
        if (!in_array($type, $this->allowedTypes, true)) {
            throw new InvalidArgumentException("Invalid archive type");
        }

        if ($type === 'orders') {
            return $this->restoreOrder($id, $userID);
        }

        $map = [
            'products' => ['tbl_products', 'productID'],
            'rawMaterials' => ['tbl_rawMaterials', 'rawMaterialID'],
            'packaging' => ['tbl_packaging', 'packagingID'],
            'processes' => ['tbl_process', 'processID']
        ];

        [$table, $idColumn] = $map[$type];
        return $this->restoreRow($table, $idColumn, $id);
    }
}
