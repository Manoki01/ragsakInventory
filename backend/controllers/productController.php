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
        http_response_code(500);
        echo json_encode([
            "message" => "Failed to Stock Product"
        ]);
    }
}
