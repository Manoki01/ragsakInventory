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
        pf.processID,
        (SELECT processName FROM tbl_process WHERE processID = pf.processID) AS processName,
        p.unitType,
        p.productPrice
        FROM tbl_products p
        INNER JOIN tbl_processFlow pf ON p.productID = pf.productID
        INNER JOIN tbl_productStock ps ON pf.flowID = ps.flowID
        INNER JOIN tbl_process pp ON pp.processID = pf.processID";

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
                $stmt2->bind_param('iii', $productID, $process, $flowOrder);
                if (!$stmt2->execute()) {
                    throw new Exception("Failed to add Product Processes.");
                }

                $flowID = $this->conn->insert_id;
                $quantity = 0;
                $stmt3 = $this->conn->prepare('INSERT INTO tbl_productStock (flowID, quantity) VALUES (?, ?)');
                $stmt3->bind_param('ii', $flowID, $quantity);

                if(!$stmt3->execute()) {
                    throw new Exception(("Failed to add Product Processes."));
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

            $checkStmt = $this->conn->prepare("
            SELECT ps.flowID, ps.quantity FROM tbl_productStock ps
            INNER JOIN tbl_processFlow pf ON ps.flowID = pf.flowID
            WHERE pf.productID = ? AND pf.processID = ?");
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
                $flowID = $row['flowID'];
                $finalQuantity = $data['quantity'] + $quantity;

                $updateStmt = $this->conn->prepare("UPDATE tbl_productStock SET
                quantity = ?
                WHERE flowID = ?");

                $updateStmt->bind_param(
                    'ii',
                    $finalQuantity,
                    $flowID
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