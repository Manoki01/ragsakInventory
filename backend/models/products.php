<?php

require_once __DIR__ . '../../config/database.php';

class Product {
    private $conn;

    public function __construct(){
        $this->conn = Database::connect();
    }

    public function getAll() {
        $query = "SELECT 
        p.productID, 
        p.productName, 
        ps.quantity,
        ps.processID,
        (SELECT processName FROM tbl_process WHERE processID = ps.processID) AS processName,
        p.unitType,
        p.productPrice
        FROM tbl_products p
        INNER JOIN tbl_productStock ps ON p.productID = ps.productID
        INNER JOIN tbl_process pp ON pp.processID = ps.processID";

        $result = $this->conn->query($query);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function createProduct($data) {
        if (empty($data['processes']) || !is_array($data['processes'])) {
            error_log("Cannot add product: no processes provided.");
            return false;
        }

        $this->conn->begin_transaction();

        try {
            $stmt1 = $this->conn->prepare(
                'INSERT INTO tbl_products(productName, unitType, productPrice)
                VALUES (?, ?, ?)'
            );
            $stmt1->bind_param(
                'ssi',
                $data['unitName'],
                $data['unitType'],
                $data['unitPrice']
            );

            if (!$stmt1->execute()) {
                throw new Exception("Failed to add Product.");
            }

            // Get the inserted product ID
            $productID = $this->conn->insert_id;
            

            $stmt2 = $this->conn->prepare(
                "INSERT INTO tbl_processFlow (productID, processID, flowOrder) VALUES (?, ?, ?)"
            );

            $flowOrder = 1;
            foreach ($data['processes'] as $process) {
                $quantity = 0;
                $stmt2->bind_param('iiii', $productID, $process, $quantity, $flowOrder);
                if (!$stmt2->execute()) {
                    throw new Exception("Failed to add Product Processes.");
                }

                $flowOrder++;
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Transaction failed: " . $e->getMessage());
            return false;
        }
    }

    public function stockProduct($data) {
        $this->conn->begin_transaction();

        try {
            $process = $data['processID'];
            $product = $data['productID'];

            $checkStmt = $this->conn->prepare("SELECT stockID, quantity FROM tbl_productStock WHERE productID = ? AND processID = ?");
            $checkStmt->bind_param(
                'ii',
                $product,
                $process
            );

            if(!$checkStmt->execute()) {
                throw new Exception("Failed to connect to the server");
            }

            $result = $checkStmt->get_result();

            if($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $quantity = $row['quantity'];
                $finalQuantity = $data['quantity'] + $quantity;

                $updateStmt = $this->conn->prepare("UPDATE tbl_productStock SET
                quantity = ?
                WHERE productID = ? AND processID = ?");

                $updateStmt->bind_param(
                    'iii',
                    $finalQuantity,
                    $product,
                    $process
                );

                if(!$updateStmt->execute()) {
                    throw new Exception("Failed to update stock");
                }
            }
            
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();

            error_log("Transaction failed: " . $e->getMessage(), 3, __DIR__ . "../logs/error.log");

            return false;
        }
    }
}