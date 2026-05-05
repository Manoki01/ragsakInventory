<?php

require_once __DIR__ . '/../config/database.php';

class Order {
    private $conn;
    private $lastError = null;

    public function __construct(){
        $this->conn = Database::connect();
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function getFinishedProducts() {
        $stmt = $this->conn->prepare("
            SELECT
                p.productID,
                p.productName,
                p.unitType,
                p.productPrice,
                pr.processName,
                ps.quantity
            FROM tbl_products p
            INNER JOIN tbl_processFlow pf ON p.productID = pf.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            INNER JOIN tbl_productStock ps ON pf.flowID = ps.flowID
            WHERE LOWER(pr.processName) = LOWER('Finished Product')
            AND p.deleted_at IS NULL
            AND pr.deleted_at IS NULL
            ORDER BY p.productName
        ");

        if (!$stmt->execute()) {
            throw new Exception("Failed to load finished products");
        }

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getAll() {
        $query = "
            SELECT
                o.orderID,
                o.customerName,
                o.contactNumber,
                o.customerAddress,
                o.productID,
                o.quantity,
                o.orderStatus,
                o.stockDeducted,
                DATE(o.dateCompletion) AS dateCompletion,
                o.created_at,
                p.productName,
                p.unitType,
                pr.processName,
                ps.quantity AS availableQuantity
            FROM tbl_orders o
            INNER JOIN tbl_products p ON o.productID = p.productID
            INNER JOIN tbl_processFlow pf ON o.productID = pf.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            LEFT JOIN tbl_productStock ps ON pf.flowID = ps.flowID
            WHERE p.deleted_at IS NULL
            AND o.deleted_at IS NULL
            AND LOWER(pr.processName) = LOWER('Finished Product')
            AND pr.deleted_at IS NULL
            ORDER BY o.orderID ASC
        ";

        $result = $this->conn->query($query);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function finishedProductExists($productID) {
        $stmt = $this->conn->prepare("
            SELECT pf.flowID, ps.quantity
            FROM tbl_products p
            INNER JOIN tbl_processFlow pf ON p.productID = pf.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            INNER JOIN tbl_productStock ps ON pf.flowID = ps.flowID
            WHERE p.productID = ?
            AND LOWER(pr.processName) = LOWER('Finished Product')
            AND p.deleted_at IS NULL
            AND pr.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->bind_param("i", $productID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate finished product");
        }

        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        return $result->fetch_assoc();
    }

    private function isTerminalStatus($status) {
        return in_array($status, ['completed', 'canceled', 'late'], true);
    }

    private function logOrderTransaction($userID, $orderID, $action) {
        $stmt = $this->conn->prepare("
            INSERT INTO tbl_orderTransactions (
                userID,
                orderID,
                action
            )
            VALUES (?, ?, ?)
        ");

        $stmt->bind_param(
            "iis",
            $userID,
            $orderID,
            $action
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to log order transaction");
        }
    }

    private function logOrderChangelog($orderID, $action, $initialValue, $finalValue) {
        $stmt = $this->conn->prepare("
            INSERT INTO tbl_orderChangelogs (
                orderID,
                action,
                initialValue,
                finalValue
            )
            VALUES (?, ?, ?, ?)
        ");

        $initialValue = (string) $initialValue;
        $finalValue = (string) $finalValue;

        $stmt->bind_param(
            "isss",
            $orderID,
            $action,
            $initialValue,
            $finalValue
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to log order changelog");
        }
    }

    private function getOrderStockForUpdate($orderID) {
        $stmt = $this->conn->prepare("
            SELECT
                o.orderID,
                o.productID,
                o.quantity,
                o.orderStatus,
                o.stockDeducted,
                DATE(o.dateCompletion) AS dateCompletion,
                pf.flowID,
                ps.quantity AS availableQuantity
            FROM tbl_orders o
            INNER JOIN tbl_products p ON o.productID = p.productID
            INNER JOIN tbl_processFlow pf ON o.productID = pf.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            INNER JOIN tbl_productStock ps ON pf.flowID = ps.flowID
            WHERE o.orderID = ?
            AND o.deleted_at IS NULL
            AND p.deleted_at IS NULL
            AND LOWER(pr.processName) = LOWER('Finished Product')
            AND pr.deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->bind_param("i", $orderID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to load order stock");
        }

        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        return $result->fetch_assoc();
    }

    private function deductOrderStock($orderRow, $reason) {
        $flowID = (int) $orderRow['flowID'];
        $orderQuantity = (int) $orderRow['quantity'];
        $initialQuantity = (int) $orderRow['availableQuantity'];
        $finalQuantity = $initialQuantity - $orderQuantity;

        if ($finalQuantity < 0) {
            $this->lastError = "Order quantity is greater than finished product stock";
            return false;
        }

        $updateStockStmt = $this->conn->prepare("
            UPDATE tbl_productStock
            SET quantity = ?
            WHERE flowID = ?
        ");

        $updateStockStmt->bind_param("ii", $finalQuantity, $flowID);

        if (!$updateStockStmt->execute()) {
            throw new Exception("Failed to deduct finished product stock");
        }

        $action = "Decrease";
        $changelogStmt = $this->conn->prepare("
            INSERT INTO tbl_prodChangelogs (
                flowID,
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
            $flowID,
            $action,
            $orderQuantity,
            $initialQuantity,
            $finalQuantity,
            $reason
        );

        if (!$changelogStmt->execute()) {
            throw new Exception("Failed to log order stock changelog");
        }

        return true;
    }

    public function createOrder($data) {
        $this->lastError = null;
        $this->conn->begin_transaction();

        try {
        $stmt = $this->conn->prepare("
            INSERT INTO tbl_orders (
                customerName,
                contactNumber,
                customerAddress,
                productID,
                quantity,
                orderStatus,
                dateCompletion
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssiiss",
            $data['customerName'],
            $data['contactNumber'],
            $data['customerAddress'],
            $data['productID'],
            $data['quantity'],
            $data['orderStatus'],
            $data['dateCompletion']
        );

            if (!$stmt->execute()) {
                throw new Exception("Failed to create order");
            }

            $orderID = (int) $this->conn->insert_id;

            if (in_array($data['orderStatus'], ['completed', 'late'], true)) {
                $orderRow = $this->getOrderStockForUpdate($orderID);

                if ($orderRow === null) {
                    throw new Exception("Failed to locate created order stock");
                }

                $reasonStatus = $data['orderStatus'] === 'late' ? 'Completed late' : 'Completed';
                $reason = "Order ID " . $orderID . " " . $reasonStatus;

                if (!$this->deductOrderStock($orderRow, $reason)) {
                    $this->conn->rollback();
                    return false;
                }

                $deductStmt = $this->conn->prepare("
                    UPDATE tbl_orders
                    SET stockDeducted = 1,
                        updated_at = NOW()
                    WHERE orderID = ?
                ");

                $deductStmt->bind_param("i", $orderID);

                if (!$deductStmt->execute()) {
                    throw new Exception("Failed to mark order stock deducted");
                }
            }

            $this->logOrderTransaction(
                (int) $data['userID'],
                $orderID,
                "Added order"
            );

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->lastError = "Failed to create order";
            error_log("Order create transaction failed: " . $e->getMessage());
            return false;
        }
    }

    public function updateOrder($data) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_orders
            SET customerName = ?,
                contactNumber = ?,
                customerAddress = ?,
                productID = ?,
                quantity = ?,
                orderStatus = ?,
                dateCompletion = ?,
                updated_at = NOW()
            WHERE orderID = ?
        ");

        $stmt->bind_param(
            "sssiissi",
            $data['customerName'],
            $data['contactNumber'],
            $data['customerAddress'],
            $data['productID'],
            $data['quantity'],
            $data['orderStatus'],
            $data['dateCompletion'],
            $data['orderID']
        );

        return $stmt->execute();
    }

    public function updateOrderInfo($data) {
        $this->lastError = null;
        $this->conn->begin_transaction();

        try {
        $statusStmt = $this->conn->prepare("
            SELECT
                customerName,
                contactNumber,
                customerAddress,
                productID,
                quantity,
                DATE(dateCompletion) AS dateCompletion,
                orderStatus
            FROM tbl_orders
            WHERE orderID = ?
            AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

        $statusStmt->bind_param("i", $data['orderID']);

        if (!$statusStmt->execute()) {
            throw new Exception("Failed to check order status");
        }

        $statusResult = $statusStmt->get_result();

        if (!$statusResult || $statusResult->num_rows === 0) {
            $this->lastError = "Order not found";
            $this->conn->rollback();
            return false;
        }

        $currentOrder = $statusResult->fetch_assoc();
        $currentStatus = strtolower((string) $currentOrder['orderStatus']);

        if ($this->isTerminalStatus($currentStatus)) {
            $this->lastError = "Completed, canceled, and late orders cannot be edited";
            $this->conn->rollback();
            return false;
        }

        $stmt = $this->conn->prepare("
            UPDATE tbl_orders
            SET customerName = ?,
                contactNumber = ?,
                customerAddress = ?,
                productID = ?,
                quantity = ?,
                dateCompletion = ?,
                updated_at = NOW()
            WHERE orderID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param(
            "sssiisi",
            $data['customerName'],
            $data['contactNumber'],
            $data['customerAddress'],
            $data['productID'],
            $data['quantity'],
            $data['dateCompletion'],
            $data['orderID']
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to update order information");
        }

        $fields = [
            'customerName' => 'Changed customer name',
            'contactNumber' => 'Changed contact number',
            'customerAddress' => 'Changed customer address',
            'productID' => 'Changed product',
            'quantity' => 'Changed quantity',
            'dateCompletion' => 'Changed due date'
        ];

        foreach ($fields as $field => $action) {
            $initialValue = (string) ($currentOrder[$field] ?? '');
            $finalValue = (string) ($data[$field] ?? '');

            if ($field === 'dateCompletion') {
                $initialValue = substr($initialValue, 0, 10);
                $finalValue = substr($finalValue, 0, 10);
            }

            if ($initialValue !== $finalValue) {
                $this->logOrderChangelog(
                    (int) $data['orderID'],
                    $action,
                    $initialValue,
                    $finalValue
                );
            }
        }

        $this->logOrderTransaction(
            (int) $data['userID'],
            (int) $data['orderID'],
            "Changed information"
        );

        $this->conn->commit();
        return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->lastError = "Failed to update order information";
            error_log("Order information transaction failed: " . $e->getMessage());
            return false;
        }
    }

    public function updateOrderStatus($data) {
        $this->lastError = null;
        $this->conn->begin_transaction();

        try {
            $orderRow = $this->getOrderStockForUpdate($data['orderID']);

            if ($orderRow === null) {
                $this->lastError = "Order not found";
                $this->conn->rollback();
                return false;
            }

            $currentStatus = strtolower((string) $orderRow['orderStatus']);
            $action = strtolower((string) ($data['action'] ?? ''));

            if ($action === 'complete') {
                $today = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
                $dueDate = substr((string) ($orderRow['dateCompletion'] ?? ''), 0, 10);
                $newStatus = ($dueDate !== '' && $dueDate < $today) ? 'late' : 'completed';
            } else if ($action === 'cancel') {
                $newStatus = 'canceled';
            } else {
                $this->lastError = "Order action is invalid";
                $this->conn->rollback();
                return false;
            }

            if ($this->isTerminalStatus($currentStatus)) {
                $this->lastError = "Completed, canceled, and late orders cannot change status";
                $this->conn->rollback();
                return false;
            }

            if (in_array($newStatus, ['completed', 'late'], true) && (int) $orderRow['stockDeducted'] === 0) {
                $reasonStatus = $newStatus === 'late' ? 'Completed late' : 'Completed';
                $reason = "Order ID " . (int) $orderRow['orderID'] . " " . $reasonStatus;

                if (!$this->deductOrderStock($orderRow, $reason)) {
                    $this->conn->rollback();
                    return false;
                }

                $stockDeducted = 1;
            } else {
                $stockDeducted = (int) $orderRow['stockDeducted'];
            }

            $stmt = $this->conn->prepare("
                UPDATE tbl_orders
                SET orderStatus = ?,
                    stockDeducted = ?,
                    updated_at = NOW()
                WHERE orderID = ?
                AND deleted_at IS NULL
            ");

            $stmt->bind_param(
                "sii",
                $newStatus,
                $stockDeducted,
                $data['orderID']
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to update order status");
            }

            $this->logOrderChangelog(
                (int) $data['orderID'],
                "Changed status",
                $currentStatus,
                $newStatus
            );

            $this->logOrderTransaction(
                (int) $data['userID'],
                (int) $data['orderID'],
                "Changed status to " . $newStatus
            );

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->lastError = "Failed to update order status";
            error_log("Order status transaction failed: " . $e->getMessage());
            return false;
        }
    }

    public function archiveOrder($orderID, $userID) {
        $this->lastError = null;
        $this->conn->begin_transaction();

        try {
        $stmt = $this->conn->prepare("
            UPDATE tbl_orders
            SET deleted_at = NOW(),
                updated_at = NOW()
            WHERE orderID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param("i", $orderID);

            if (!$stmt->execute()) {
                throw new Exception("Failed to archive order");
            }

            $this->logOrderChangelog(
                $orderID,
                "Archived order",
                "active",
                "archived"
            );

            $this->logOrderTransaction(
                $userID,
                $orderID,
                "Archived order"
            );

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->lastError = "Failed to archive order";
            error_log("Order archive transaction failed: " . $e->getMessage());
            return false;
        }
    }
}
