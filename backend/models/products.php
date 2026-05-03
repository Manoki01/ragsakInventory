<?php

require_once __DIR__ . '/../config/database.php';

class Product {
    private $conn;
    private $lastError = null;

    public function __construct(){
        $this->conn = Database::connect();
    }

    public function getLastError() {
        return $this->lastError;
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
        WHERE p.deleted_at IS NULL";

        $result = $this->conn->query($query);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getProcessFormula($productID, $processID) {
        $formula = [
            "rawMaterials" => [],
            "packaging" => []
        ];

        $flowStmt = $this->conn->prepare("
            SELECT pf.flowID
            FROM tbl_processFlow pf
            INNER JOIN tbl_products p ON pf.productID = p.productID
            WHERE pf.productID = ?
            AND pf.processID = ?
            AND p.deleted_at IS NULL
            LIMIT 1
        ");

        $flowStmt->bind_param("ii", $productID, $processID);

        if (!$flowStmt->execute()) {
            throw new Exception("Failed to locate product process");
        }

        $flowResult = $flowStmt->get_result();

        if (!$flowResult || $flowResult->num_rows === 0) {
            return null;
        }

        $flowID = (int) $flowResult->fetch_assoc()['flowID'];

        $rawStmt = $this->conn->prepare("
            SELECT
                rm.rawMaterialID,
                rm.rawMaterialName,
                rm.unitType,
                brm.quantityRequired
            FROM tbl_bom b
            INNER JOIN tbl_bomRawMaterials brm ON b.bomID = brm.bomID
            INNER JOIN tbl_rawMaterials rm ON brm.rawMaterialID = rm.rawMaterialID
            WHERE b.flowID = ?
            AND rm.deleted_at IS NULL
            ORDER BY rm.rawMaterialName
        ");

        $rawStmt->bind_param("i", $flowID);

        if (!$rawStmt->execute()) {
            throw new Exception("Failed to load raw material formula");
        }

        $formula["rawMaterials"] = $rawStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $packagingStmt = $this->conn->prepare("
            SELECT
                p.packagingID,
                p.packagingName,
                p.unitType,
                bp.quantityRequired
            FROM tbl_bom b
            INNER JOIN tbl_bomPackaging bp ON b.bomID = bp.bomID
            INNER JOIN tbl_packaging p ON bp.packagingID = p.packagingID
            WHERE b.flowID = ?
            AND p.deleted_at IS NULL
            ORDER BY p.packagingName
        ");

        $packagingStmt->bind_param("i", $flowID);

        if (!$packagingStmt->execute()) {
            throw new Exception("Failed to load packaging formula");
        }

        $formula["packaging"] = $packagingStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return $formula;
    }

    public function saveProcessFormula($productID, $processID, $rawMaterials, $packagingItems) {
        $this->conn->begin_transaction();

        try {
            $flowStmt = $this->conn->prepare("
                SELECT pf.flowID, p.productName, pr.processName
                FROM tbl_processFlow pf
                INNER JOIN tbl_products p ON pf.productID = p.productID
                INNER JOIN tbl_process pr ON pf.processID = pr.processID
                WHERE pf.productID = ?
                AND pf.processID = ?
                AND p.deleted_at IS NULL
                LIMIT 1
            ");

            $flowStmt->bind_param("ii", $productID, $processID);

            if (!$flowStmt->execute()) {
                throw new Exception("Failed to locate product process");
            }

            $flowResult = $flowStmt->get_result();

            if (!$flowResult || $flowResult->num_rows === 0) {
                $this->conn->rollback();
                return false;
            }

            $flow = $flowResult->fetch_assoc();
            $flowID = (int) $flow['flowID'];

            $bomStmt = $this->conn->prepare("
                SELECT bomID
                FROM tbl_bom
                WHERE flowID = ?
                LIMIT 1
            ");

            $bomStmt->bind_param("i", $flowID);

            if (!$bomStmt->execute()) {
                throw new Exception("Failed to locate formula");
            }

            $bomResult = $bomStmt->get_result();

            if ($bomResult && $bomResult->num_rows > 0) {
                $bomID = (int) $bomResult->fetch_assoc()['bomID'];
            } else {
                $bomName = $flow['productName'] . " - " . $flow['processName'] . " Formula";
                $createBomStmt = $this->conn->prepare("
                    INSERT INTO tbl_bom (flowID, bomName)
                    VALUES (?, ?)
                ");
                $createBomStmt->bind_param("is", $flowID, $bomName);

                if (!$createBomStmt->execute()) {
                    throw new Exception("Failed to create formula");
                }

                $bomID = $this->conn->insert_id;
            }

            $rawTypeStmt = $this->conn->prepare("
                SELECT matType
                FROM tbl_rawMaterials
                WHERE rawMaterialID = ?
                AND deleted_at IS NULL
                LIMIT 1
            ");

            foreach ($rawMaterials as $item) {
                $rawMaterialID = (int) $item['rawMaterialID'];
                $rawTypeStmt->bind_param("i", $rawMaterialID);

                if (!$rawTypeStmt->execute()) {
                    throw new Exception("Failed to validate raw material type");
                }

                $rawTypeResult = $rawTypeStmt->get_result();

                if (!$rawTypeResult || $rawTypeResult->num_rows === 0 || strtolower($rawTypeResult->fetch_assoc()['matType']) !== 'main') {
                    $this->conn->rollback();
                    return false;
                }
            }

            $packagingTypeStmt = $this->conn->prepare("
                SELECT packagingType
                FROM tbl_packaging
                WHERE packagingID = ?
                AND deleted_at IS NULL
                LIMIT 1
            ");

            foreach ($packagingItems as $item) {
                $packagingID = (int) $item['packagingID'];
                $packagingTypeStmt->bind_param("i", $packagingID);

                if (!$packagingTypeStmt->execute()) {
                    throw new Exception("Failed to validate packaging type");
                }

                $packagingTypeResult = $packagingTypeStmt->get_result();

                if (!$packagingTypeResult || $packagingTypeResult->num_rows === 0 || strtolower($packagingTypeResult->fetch_assoc()['packagingType']) !== 'main') {
                    $this->conn->rollback();
                    return false;
                }
            }

            $deleteRawStmt = $this->conn->prepare("DELETE FROM tbl_bomRawMaterials WHERE bomID = ?");
            $deleteRawStmt->bind_param("i", $bomID);

            if (!$deleteRawStmt->execute()) {
                throw new Exception("Failed to clear raw material formula");
            }

            $deletePackagingStmt = $this->conn->prepare("DELETE FROM tbl_bomPackaging WHERE bomID = ?");
            $deletePackagingStmt->bind_param("i", $bomID);

            if (!$deletePackagingStmt->execute()) {
                throw new Exception("Failed to clear packaging formula");
            }

            if (!empty($rawMaterials)) {
                $rawInsertStmt = $this->conn->prepare("
                    INSERT INTO tbl_bomRawMaterials (bomID, rawMaterialID, quantityRequired)
                    VALUES (?, ?, ?)
                ");

                foreach ($rawMaterials as $item) {
                    $rawMaterialID = (int) $item['rawMaterialID'];
                    $quantityRequired = (int) $item['quantityRequired'];
                    $rawInsertStmt->bind_param("iii", $bomID, $rawMaterialID, $quantityRequired);

                    if (!$rawInsertStmt->execute()) {
                        throw new Exception("Failed to save raw material formula");
                    }
                }
            }

            if (!empty($packagingItems)) {
                $packagingInsertStmt = $this->conn->prepare("
                    INSERT INTO tbl_bomPackaging (bomID, packagingID, quantityRequired)
                    VALUES (?, ?, ?)
                ");

                foreach ($packagingItems as $item) {
                    $packagingID = (int) $item['packagingID'];
                    $quantityRequired = (int) $item['quantityRequired'];
                    $packagingInsertStmt->bind_param("iii", $bomID, $packagingID, $quantityRequired);

                    if (!$packagingInsertStmt->execute()) {
                        throw new Exception("Failed to save packaging formula");
                    }
                }
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Transaction failed: " . $e->getMessage());
            return false;
        }
    }

    public function productExistsIgnoreCase($productName) {
        $stmt = $this->conn->prepare("
            SELECT productID
            FROM tbl_products
            WHERE LOWER(productName) = LOWER(?)
            LIMIT 1
        ");

        $stmt->bind_param("s", $productName);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate product name");
        }

        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function productNameExistsForOtherProduct($productName, $productID) {
        $stmt = $this->conn->prepare("
            SELECT productID
            FROM tbl_products
            WHERE LOWER(productName) = LOWER(?)
            AND productID <> ?
            LIMIT 1
        ");

        $stmt->bind_param("si", $productName, $productID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to validate product name");
        }

        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function updateProductInfo($data) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_products
            SET productName = ?, unitType = ?, productPrice = ?
            WHERE productID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param(
            "ssdi",
            $data['unitName'],
            $data['unitType'],
            $data['unitPrice'],
            $data['productID']
        );

        return $stmt->execute();
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
        $this->lastError = null;
        $this->conn->begin_transaction();

        try {
            $process = $data['processID'];
            $product = $data['productID'];
            $userID = $data['userID'];

            $checkStmt = $this->conn->prepare("
            SELECT ps.flowID, ps.quantity FROM tbl_productStock ps
            INNER JOIN tbl_processFlow pf ON ps.flowID = pf.flowID
            INNER JOIN tbl_products p ON pf.productID = p.productID
            WHERE pf.productID = ? AND pf.processID = ?
            AND p.deleted_at IS NULL");
            $checkStmt->bind_param(
                'ii',
                $product,
                $process
            );

            if(!$checkStmt->execute()) {
                throw new Exception("Failed to connect to the server");
            }

            $result = $checkStmt->get_result();

            if(!$result || $result->num_rows === 0) {
                $this->lastError = "Product process was not found";
                $this->conn->rollback();
                return false;
            }

            $row = $result->fetch_assoc();
            $quantity = (int) $row['quantity'];
            $flowID = (int) $row['flowID'];
            $stockInQuantity = (int) $data['quantity'];
            $finalQuantity = $stockInQuantity + $quantity;
            $formulaMode = $data['formulaMode'] ?? 'formula';

            if ($formulaMode === 'formula') {
                $rawFormulaStmt = $this->conn->prepare("
                    SELECT
                        brm.rawMaterialID,
                        brm.quantityRequired,
                        rm.rawMaterialName,
                        rm.quantity AS availableQuantity,
                        rm.unitType
                    FROM tbl_bom b
                    INNER JOIN tbl_bomRawMaterials brm ON b.bomID = brm.bomID
                    INNER JOIN tbl_rawMaterials rm ON brm.rawMaterialID = rm.rawMaterialID
                    WHERE b.flowID = ?
                    AND rm.deleted_at IS NULL
                ");
                $rawFormulaStmt->bind_param("i", $flowID);

                if (!$rawFormulaStmt->execute()) {
                    throw new Exception("Failed to load raw material formula");
                }

                $rawFormula = $rawFormulaStmt->get_result()->fetch_all(MYSQLI_ASSOC);

                $packagingFormulaStmt = $this->conn->prepare("
                    SELECT
                        bp.packagingID,
                        bp.quantityRequired,
                        p.packagingName,
                        p.quantity AS availableQuantity,
                        p.unitType
                    FROM tbl_bom b
                    INNER JOIN tbl_bomPackaging bp ON b.bomID = bp.bomID
                    INNER JOIN tbl_packaging p ON bp.packagingID = p.packagingID
                    WHERE b.flowID = ?
                    AND p.deleted_at IS NULL
                ");
                $packagingFormulaStmt->bind_param("i", $flowID);

                if (!$packagingFormulaStmt->execute()) {
                    throw new Exception("Failed to load packaging formula");
                }

                $packagingFormula = $packagingFormulaStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            } else {
                $rawFormula = [];
                $packagingFormula = [];

                $rawCustomStmt = $this->conn->prepare("
                    SELECT rawMaterialID, rawMaterialName, quantity AS availableQuantity, unitType, matType
                    FROM tbl_rawMaterials
                    WHERE rawMaterialID = ?
                    AND deleted_at IS NULL
                    LIMIT 1
                ");

                foreach ($data['formulaRawMaterials'] ?? [] as $item) {
                    $rawMaterialID = (int) $item['rawMaterialID'];
                    $rawCustomStmt->bind_param("i", $rawMaterialID);

                    if (!$rawCustomStmt->execute()) {
                        throw new Exception("Failed to load selected raw material");
                    }

                    $customResult = $rawCustomStmt->get_result();

                    if (!$customResult || $customResult->num_rows === 0) {
                        $this->lastError = "Selected raw material was not found";
                        $this->conn->rollback();
                        return false;
                    }

                    $customRow = $customResult->fetch_assoc();

                    if ($formulaMode === 'alternatives' && strtolower($customRow['matType']) !== 'alternative') {
                        $this->lastError = "Alternatives-only stock-in can only use alternative raw materials";
                        $this->conn->rollback();
                        return false;
                    }

                    $customRow['quantityRequired'] = (int) $item['quantityRequired'];
                    $customRow['isTotalQuantity'] = $formulaMode === 'mixed';
                    $rawFormula[] = $customRow;
                }

                $packagingCustomStmt = $this->conn->prepare("
                    SELECT packagingID, packagingName, quantity AS availableQuantity, unitType, packagingType
                    FROM tbl_packaging
                    WHERE packagingID = ?
                    AND deleted_at IS NULL
                    LIMIT 1
                ");

                foreach ($data['formulaPackaging'] ?? [] as $item) {
                    $packagingID = (int) $item['packagingID'];
                    $packagingCustomStmt->bind_param("i", $packagingID);

                    if (!$packagingCustomStmt->execute()) {
                        throw new Exception("Failed to load selected packaging");
                    }

                    $customResult = $packagingCustomStmt->get_result();

                    if (!$customResult || $customResult->num_rows === 0) {
                        $this->lastError = "Selected packaging was not found";
                        $this->conn->rollback();
                        return false;
                    }

                    $customRow = $customResult->fetch_assoc();

                    if ($formulaMode === 'alternatives' && strtolower($customRow['packagingType']) !== 'alternative') {
                        $this->lastError = "Alternatives-only stock-in can only use alternative packaging";
                        $this->conn->rollback();
                        return false;
                    }

                    $customRow['quantityRequired'] = (int) $item['quantityRequired'];
                    $customRow['isTotalQuantity'] = $formulaMode === 'mixed';
                    $packagingFormula[] = $customRow;
                }
            }

            if (count($rawFormula) === 0 && count($packagingFormula) === 0) {
                $this->lastError = "Formula is required before stocking this product process";
                $this->conn->rollback();
                return false;
            }

            $shortages = [];

            foreach ($rawFormula as $item) {
                $required = !empty($item['isTotalQuantity'])
                    ? (int) $item['quantityRequired']
                    : (int) $item['quantityRequired'] * $stockInQuantity;
                $available = (int) $item['availableQuantity'];

                if ($available < $required) {
                    $shortages[] = $item['rawMaterialName'] . " needs " . $required . " " . $item['unitType'] . " but only has " . $available;
                }
            }

            foreach ($packagingFormula as $item) {
                $required = !empty($item['isTotalQuantity'])
                    ? (int) $item['quantityRequired']
                    : (int) $item['quantityRequired'] * $stockInQuantity;
                $available = (int) $item['availableQuantity'];

                if ($available < $required) {
                    $shortages[] = $item['packagingName'] . " needs " . $required . " " . $item['unitType'] . " but only has " . $available;
                }
            }

            if (count($shortages) > 0) {
                $this->lastError = "Not enough formula inventory: " . implode("; ", $shortages);
                $this->conn->rollback();
                return false;
            }

            if (count($rawFormula) > 0) {
                $deductRawStmt = $this->conn->prepare("
                    UPDATE tbl_rawMaterials
                    SET quantity = quantity - ?
                    WHERE rawMaterialID = ?
                    AND deleted_at IS NULL
                ");
                $rawChangelogStmt = $this->conn->prepare("
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

                foreach ($rawFormula as $item) {
                    $required = !empty($item['isTotalQuantity'])
                        ? (int) $item['quantityRequired']
                        : (int) $item['quantityRequired'] * $stockInQuantity;
                    $rawMaterialID = (int) $item['rawMaterialID'];
                    $initialRawQuantity = (int) $item['availableQuantity'];
                    $finalRawQuantity = $initialRawQuantity - $required;
                    $deductRawStmt->bind_param("ii", $required, $rawMaterialID);

                    if (!$deductRawStmt->execute()) {
                        throw new Exception("Failed to deduct raw material stock");
                    }

                    $changelogAction = "Decrease";
                    $reason = "Product Stock In";
                    $rawChangelogStmt->bind_param(
                        "isiiis",
                        $rawMaterialID,
                        $changelogAction,
                        $required,
                        $initialRawQuantity,
                        $finalRawQuantity,
                        $reason
                    );

                    if (!$rawChangelogStmt->execute()) {
                        throw new Exception("Failed to log raw material product deduction");
                    }
                }
            }

            if (count($packagingFormula) > 0) {
                $deductPackagingStmt = $this->conn->prepare("
                    UPDATE tbl_packaging
                    SET quantity = quantity - ?
                    WHERE packagingID = ?
                    AND deleted_at IS NULL
                ");
                $packagingChangelogStmt = $this->conn->prepare("
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

                foreach ($packagingFormula as $item) {
                    $required = !empty($item['isTotalQuantity'])
                        ? (int) $item['quantityRequired']
                        : (int) $item['quantityRequired'] * $stockInQuantity;
                    $packagingID = (int) $item['packagingID'];
                    $initialPackagingQuantity = (int) $item['availableQuantity'];
                    $finalPackagingQuantity = $initialPackagingQuantity - $required;
                    $deductPackagingStmt->bind_param("ii", $required, $packagingID);

                    if (!$deductPackagingStmt->execute()) {
                        throw new Exception("Failed to deduct packaging stock");
                    }

                    $changelogAction = "Decrease";
                    $reason = "Product Stock In";
                    $packagingChangelogStmt->bind_param(
                        "isiiis",
                        $packagingID,
                        $changelogAction,
                        $required,
                        $initialPackagingQuantity,
                        $finalPackagingQuantity,
                        $reason
                    );

                    if (!$packagingChangelogStmt->execute()) {
                        throw new Exception("Failed to log packaging product deduction");
                    }
                }
            }

            $updateStmt = $this->conn->prepare("UPDATE tbl_productStock SET
            quantity = ?
            WHERE flowID = ?");

            $updateStmt->bind_param(
                'ii',
                $finalQuantity,
                $flowID
            );

            $status = "success";
            if(!$updateStmt->execute()) {
                $status = "failed";
                throw new Exception("Failed to update stock");
            }

            $action = "Stock In";

            $transactionStmt = $this->conn->prepare("INSERT INTO tbl_prodTransactions (
                userID, 
                flowID, 
                status, 
                quantity, 
                action) 
                VALUES (?, ?, ?, ?, ?)");

            $transactionStmt->bind_param(
                'iisis',
                $userID,
                $flowID,
                $status,
                $data['quantity'],
                $action
            );

            if(!$transactionStmt->execute()) {
                throw new Exception("Failed to log transaction");
            }

            $action = "Increase";
            $reason = "Stock In";
            $changelogStmt = $this->conn->prepare("INSERT INTO tbl_prodChangelogs (
                flowID,
                action,
                quantity,
                initialQuantity,
                finalQuantity,
                reason
                ) VALUES (?, ?, ?, ?, ?, ?)");

            $changelogStmt->bind_param(
                'isiiis',
                $flowID,
                $action,
                $data['quantity'],
                $quantity,
                $finalQuantity,
                $reason
            );

            if(!$changelogStmt->execute()) {
                throw new Exception("Failed to log changelog");
            }
            
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();
            $this->lastError = "Failed to stock product";

            error_log("Transaction failed: " . $e->getMessage(), 3, __DIR__ . "../logs/error.log");

            return false;
        }
    }

    public function updateProductStock($data) {
        $this->conn->begin_transaction();

        try {
            $checkStmt = $this->conn->prepare("
                SELECT ps.flowID, ps.quantity
                FROM tbl_productStock ps
                INNER JOIN tbl_processFlow pf ON ps.flowID = pf.flowID
                INNER JOIN tbl_products p ON pf.productID = p.productID
                WHERE pf.productID = ?
                AND pf.processID = ?
                AND p.deleted_at IS NULL
                LIMIT 1
            ");

            $checkStmt->bind_param(
                "ii",
                $data['productID'],
                $data['processID']
            );

            if (!$checkStmt->execute()) {
                throw new Exception("Failed to locate product stock");
            }

            $result = $checkStmt->get_result();

            if (!$result || $result->num_rows === 0) {
                $this->conn->rollback();
                return false;
            }

            $row = $result->fetch_assoc();
            $flowID = (int) $row['flowID'];
            $previousQuantity = (int) $row['quantity'];
            $newQuantity = (int) $data['quantity'];
            $difference = $newQuantity - $previousQuantity;

            if ($difference === 0) {
                $this->conn->commit();
                return true;
            }

            $updateStmt = $this->conn->prepare("
                UPDATE tbl_productStock
                SET quantity = ?
                WHERE flowID = ?
            ");

            $updateStmt->bind_param(
                "ii",
                $newQuantity,
                $flowID
            );

            $status = "success";
            if (!$updateStmt->execute()) {
                $status = "failed";
                throw new Exception("Failed to update product stock");
            }

            $transactionAction = "Stock Update";
            $transactionStmt = $this->conn->prepare("
                INSERT INTO tbl_prodTransactions (
                    userID,
                    flowID,
                    status,
                    quantity,
                    action
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $transactionStmt->bind_param(
                "iisis",
                $data['userID'],
                $flowID,
                $status,
                $newQuantity,
                $transactionAction
            );

            if (!$transactionStmt->execute()) {
                throw new Exception("Failed to log product stock transaction");
            }

            $changelogAction = $difference > 0
                ? "Increase"
                : ($difference < 0 ? "Decrease" : "Set");
            $changelogQuantity = abs($difference);
            $reason = "Edit Stock";

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
                $changelogAction,
                $changelogQuantity,
                $previousQuantity,
                $newQuantity,
                $reason
            );

            if (!$changelogStmt->execute()) {
                throw new Exception("Failed to log product stock changelog");
            }

            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();
            error_log("Transaction failed: " . $e->getMessage());
            return false;
        }
    }

    public function archiveProduct($productID) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_products
            SET deleted_at = NOW()
            WHERE productID = ?
            AND deleted_at IS NULL
        ");

        $stmt->bind_param("i", $productID);

        if (!$stmt->execute()) {
            return false;
        }

        return $stmt->affected_rows > 0;
    }
}
