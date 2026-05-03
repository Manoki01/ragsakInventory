<?php

require_once __DIR__ . '/../config/database.php';

class Order {
    private $conn;

    public function __construct(){
        $this->conn = Database::connect();
    }

    private function getFinishedProcessID() {
        $stmt = $this->conn->prepare("
            SELECT processID
            FROM tbl_process
            WHERE LOWER(processName) = LOWER('Finished Product')
            AND deleted_at IS NULL
            LIMIT 1
        ");

        if (!$stmt->execute()) {
            throw new Exception("Failed to locate finished product process");
        }

        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        return (int) $result->fetch_assoc()['processID'];
    }

    public function getFinishedProducts() {
        $finishedProcessID = $this->getFinishedProcessID();

        if ($finishedProcessID === null) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                p.productID,
                p.productName,
                p.unitType,
                p.productPrice,
                pf.processID,
                pr.processName,
                ps.quantity
            FROM tbl_products p
            INNER JOIN tbl_processFlow pf ON p.productID = pf.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            INNER JOIN tbl_productStock ps ON pf.flowID = ps.flowID
            WHERE pf.processID = ?
            AND p.deleted_at IS NULL
            AND pr.deleted_at IS NULL
            ORDER BY p.productName
        ");

        $stmt->bind_param("i", $finishedProcessID);

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
                o.processID,
                o.quantity,
                o.orderStatus,
                DATE(o.dateCompletion) AS dateCompletion,
                o.created_at,
                p.productName,
                p.unitType,
                pr.processName,
                ps.quantity AS availableQuantity
            FROM tbl_orders o
            INNER JOIN tbl_products p ON o.productID = p.productID
            INNER JOIN tbl_process pr ON o.processID = pr.processID
            LEFT JOIN tbl_processFlow pf ON o.productID = pf.productID AND o.processID = pf.processID
            LEFT JOIN tbl_productStock ps ON pf.flowID = ps.flowID
            WHERE p.deleted_at IS NULL
            ORDER BY o.dateCompletion ASC, o.orderID DESC
        ";

        $result = $this->conn->query($query);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function finishedProductExists($productID, $processID) {
        $stmt = $this->conn->prepare("
            SELECT ps.quantity
            FROM tbl_products p
            INNER JOIN tbl_processFlow pf ON p.productID = pf.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            INNER JOIN tbl_productStock ps ON pf.flowID = ps.flowID
            WHERE p.productID = ?
            AND pf.processID = ?
            AND LOWER(pr.processName) = LOWER('Finished Product')
            AND p.deleted_at IS NULL
            AND pr.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->bind_param("ii", $productID, $processID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate finished product");
        }

        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        return $result->fetch_assoc();
    }

    public function createOrder($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO tbl_orders (
                customerName,
                contactNumber,
                customerAddress,
                productID,
                processID,
                quantity,
                orderStatus,
                dateCompletion
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssiiiss",
            $data['customerName'],
            $data['contactNumber'],
            $data['customerAddress'],
            $data['productID'],
            $data['processID'],
            $data['quantity'],
            $data['orderStatus'],
            $data['dateCompletion']
        );

        return $stmt->execute();
    }

    public function updateOrder($data) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_orders
            SET customerName = ?,
                contactNumber = ?,
                customerAddress = ?,
                productID = ?,
                processID = ?,
                quantity = ?,
                orderStatus = ?,
                dateCompletion = ?,
                updated_at = NOW()
            WHERE orderID = ?
        ");

        $stmt->bind_param(
            "sssiiissi",
            $data['customerName'],
            $data['contactNumber'],
            $data['customerAddress'],
            $data['productID'],
            $data['processID'],
            $data['quantity'],
            $data['orderStatus'],
            $data['dateCompletion'],
            $data['orderID']
        );

        return $stmt->execute();
    }
}
