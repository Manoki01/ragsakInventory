<?php

require_once __DIR__ . '../../models/products.php';

function failValidation($message) {
    http_response_code(422);
    echo json_encode([
        "status" => "error",
        "message" => $message
    ]);
    exit;
}

function validatePositiveInt($value, $fieldName) {
    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
        failValidation($fieldName . " must be a positive whole number");
    }

    return (int) $value;
}

function validateNonEmptyText($value, $fieldName, $maxLength = 100) {
    $value = trim((string) $value);

    if ($value === '' || strlen($value) > $maxLength) {
        failValidation($fieldName . " is invalid");
    }

    return $value;
}

function validateMoney($value, $fieldName) {
    if (!is_numeric($value) || (float) $value < 0) {
        failValidation($fieldName . " must be a valid non-negative number");
    }

    return (float) $value;
}

function getProducts() {
    $product = new Product();

    $data = $product->getAll();

    echo json_encode([
        "status" => "success",
        "data" => $data
    ]);
}

function getProductFormula() {
    $productID = validatePositiveInt($_GET['productID'] ?? null, 'Product ID');
    $processID = validatePositiveInt($_GET['processID'] ?? null, 'Process ID');
    $product = new Product();
    $data = $product->getProcessFormula($productID, $processID);

    if ($data === null) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Product process was not found"
        ]);
        return;
    }

    echo json_encode([
        "status" => "success",
        "data" => $data
    ]);
}

function normalizeFormulaItems($items, $idField, $label) {
    if (!is_array($items)) {
        failValidation($label . " must be a list");
    }

    $normalized = [];
    $seen = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            failValidation($label . " contains an invalid item");
        }

        $id = validatePositiveInt($item[$idField] ?? null, $label . " ID");
        $quantity = validatePositiveInt($item['quantityRequired'] ?? null, $label . " quantity");

        if (isset($seen[$id])) {
            failValidation($label . " cannot contain duplicate items");
        }

        $seen[$id] = true;
        $normalized[] = [
            $idField => $id,
            "quantityRequired" => $quantity
        ];
    }

    return $normalized;
}

function saveProductFormula() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $productID = validatePositiveInt($input['productID'] ?? null, 'Product ID');
    $processID = validatePositiveInt($input['processID'] ?? null, 'Process ID');
    $rawMaterials = normalizeFormulaItems($input['rawMaterials'] ?? [], 'rawMaterialID', 'Raw material');
    $packaging = normalizeFormulaItems($input['packaging'] ?? [], 'packagingID', 'Packaging');

    $product = new Product();

    if ($product->saveProcessFormula($productID, $processID, $rawMaterials, $packaging)) {
        echo json_encode([
            "status" => "success",
            "message" => "Formula saved successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Failed to save formula"
        ]);
    }
}

function createProduct() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );
    
    if (!$input) {
        http_response_code(400); // Bad Request
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $processes = $input['processes'] ?? null;
    if (!is_array($processes) || count($processes) === 0) {
        failValidation("At least one valid process is required");
    }

    $input['unitName'] = validateNonEmptyText($input['unitName'] ?? '', 'Product name');
    $input['unitType'] = validateNonEmptyText($input['unitType'] ?? '', 'Unit type', 50);
    $input['unitPrice'] = validateMoney($input['unitPrice'] ?? null, 'Unit price');
    $input['processes'] = array_values(array_unique(array_map(
        fn ($processId) => validatePositiveInt($processId, 'Process ID'),
        $processes
    )));

    $product = new Product();

    if ($product->productExistsIgnoreCase($input['unitName'])) {
        http_response_code(409);
        echo json_encode([
            "status" => "error",
            "message" => "Product already exists"
        ]);
        exit;
    }

    $success = $product->createProduct($input);

    header('Content-Type: application/json');

    if ($success) {
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Product Added Successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "message" => "Failed to Add Product"
        ]);
    }
}

function updateProductInfo() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $input['productID'] = validatePositiveInt($input['productID'] ?? null, 'Product ID');
    $input['unitName'] = validateNonEmptyText($input['unitName'] ?? '', 'Product name');
    $input['unitType'] = validateNonEmptyText($input['unitType'] ?? '', 'Unit type', 50);
    $input['unitPrice'] = validateMoney($input['unitPrice'] ?? null, 'Unit price');

    $product = new Product();

    if ($product->productNameExistsForOtherProduct($input['unitName'], $input['productID'])) {
        http_response_code(409);
        echo json_encode([
            "status" => "error",
            "message" => "Product already exists"
        ]);
        exit;
    }

    if ($product->updateProductInfo($input)) {
        echo json_encode([
            "status" => "success",
            "message" => "Product Updated Successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Failed to Update Product"
        ]);
    }
}

function stockProduct() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $input['productID'] = validatePositiveInt($input['productID'] ?? null, 'Product ID');
    $input['processID'] = validatePositiveInt($input['processID'] ?? null, 'Process ID');
    $input['quantity'] = validatePositiveInt($input['quantity'] ?? null, 'Quantity');
    $formulaMode = $input['formulaMode'] ?? 'formula';

    if (!in_array($formulaMode, ['formula', 'mixed', 'alternatives'], true)) {
        failValidation("Formula mode is invalid");
    }

    $input['formulaMode'] = $formulaMode;

    if ($formulaMode !== 'formula') {
        $input['formulaRawMaterials'] = normalizeFormulaItems($input['rawMaterials'] ?? [], 'rawMaterialID', 'Raw material');
        $input['formulaPackaging'] = normalizeFormulaItems($input['packaging'] ?? [], 'packagingID', 'Packaging');

        if (count($input['formulaRawMaterials']) === 0 && count($input['formulaPackaging']) === 0) {
            failValidation("Select at least one material or packaging item for this stock-in");
        }
    }

    $authenticatedUser = getCurrentAuthUser();
    $input['userID'] = isset($authenticatedUser->sub) ? (int) $authenticatedUser->sub : 0;

    if ($input['userID'] <= 0) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Authentication required"]);
        exit;
    }

    $product = new Product();

    $success = $product->stockProduct($input);
    
    header('Content-Type: application/json');

    if ($success) {
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Product Stocked Successfully"
        ]);
    } else {
        $message = $product->getLastError() ?: "Failed to Stock Product";
        http_response_code($message === "Failed to Stock Product" ? 500 : 422);
        echo json_encode([
            "status" => "error",
            "message" => $message
        ]);
    }
}

function updateProductStock() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $input['productID'] = validatePositiveInt($input['productID'] ?? null, 'Product ID');
    $input['processID'] = validatePositiveInt($input['processID'] ?? null, 'Process ID');

    if (filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT) === false || (int) $input['quantity'] < 0) {
        failValidation("Quantity must be zero or a positive whole number");
    }

    $input['quantity'] = (int) $input['quantity'];
    $authenticatedUser = getCurrentAuthUser();
    $input['userID'] = isset($authenticatedUser->sub) ? (int) $authenticatedUser->sub : 0;

    if ($input['userID'] <= 0) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Authentication required"]);
        exit;
    }

    $product = new Product();

    if ($product->updateProductStock($input)) {
        echo json_encode([
            "status" => "success",
            "message" => "Product Stock Updated Successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Failed to Update Product Stock"
        ]);
    }
}

function archiveProduct() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $productID = validatePositiveInt($input['productID'] ?? null, 'Product ID');
    $product = new Product();

    if ($product->archiveProduct($productID)) {
        echo json_encode([
            "status" => "success",
            "message" => "Product archived successfully"
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Product was not found or is already archived"
        ]);
    }
}
