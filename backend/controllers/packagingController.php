<?php

require_once __DIR__ . '../../models/packaging.php';

function validatePackagingCreatePayload($input) {
    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $name = trim((string) ($input['unitName'] ?? ''));
    $unitType = trim((string) ($input['unitType'] ?? ''));
    $price = $input['unitPrice'] ?? null;

    if ($name === '' || strlen($name) > 100) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Packaging name is invalid"]);
        exit;
    }

    if ($unitType === '' || strlen($unitType) > 50) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Unit type is invalid"]);
        exit;
    }

    if (!is_numeric($price) || (float) $price < 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Unit price must be a valid non-negative number"]);
        exit;
    }

    $input['unitName'] = $name;
    $input['unitType'] = $unitType;
    $input['unitPrice'] = (float) $price;

    return $input;
}

function validatePackagingStockPayload($input) {
    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    if (filter_var($input['packagingID'] ?? null, FILTER_VALIDATE_INT) === false || (int) $input['packagingID'] <= 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Packaging ID must be a positive whole number"]);
        exit;
    }

    if (filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT) === false || (int) $input['quantity'] <= 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Quantity must be a positive whole number"]);
        exit;
    }

    $input['packagingID'] = (int) $input['packagingID'];
    $input['quantity'] = (int) $input['quantity'];

    return $input;
}

function getPackaging() {
    $packaging = new Packaging();

    $data = $packaging->getAll();

    echo json_encode([
        "status" => "success",
        "data" => $data
    ]);
}

function createPackaging() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    $input = validatePackagingCreatePayload($input);

    $packaging = new Packaging();

    $success = $packaging->createPackaging($input);

    header('Content-Type: application/json');

    if ($success) {
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Packaging Added Successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "message" => "Failed to Add Packaging"
        ]);
    }
}

function stockPackaging() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    $input = validatePackagingStockPayload($input);

    $packaging = new Packaging();

    $success = $packaging->stockPackaging($input);
    
    header('Content-Type: application/json');

    if ($success) {
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Packaging Stocked Successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "message" => "Failed to Stock Packaging"
        ]);
    }
}
