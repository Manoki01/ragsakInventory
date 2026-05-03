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

    if ($packaging->packagingExistsIgnoreCase($input['unitName'])) {
        http_response_code(409);
        echo json_encode([
            "status" => "error",
            "message" => "Packaging already exists"
        ]);
        exit;
    }

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

function updatePackagingInfo() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

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

    $input['packagingID'] = (int) $input['packagingID'];
    $input['unitName'] = trim((string) ($input['unitName'] ?? ''));
    $input['unitType'] = trim((string) ($input['unitType'] ?? ''));
    $input['unitPrice'] = $input['unitPrice'] ?? null;

    if ($input['unitName'] === '' || strlen($input['unitName']) > 100) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Packaging name is invalid"]);
        exit;
    }

    if ($input['unitType'] === '' || strlen($input['unitType']) > 50) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Unit type is invalid"]);
        exit;
    }

    if (!is_numeric($input['unitPrice']) || (float) $input['unitPrice'] < 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Unit price must be a valid non-negative number"]);
        exit;
    }

    $input['unitPrice'] = (float) $input['unitPrice'];

    $packaging = new Packaging();

    if ($packaging->packagingNameExistsForOtherPackaging($input['unitName'], $input['packagingID'])) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Packaging already exists"]);
        exit;
    }

    if ($packaging->updatePackagingInfo($input)) {
        echo json_encode(["status" => "success", "message" => "Packaging Updated Successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to Update Packaging"]);
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

function updatePackagingStock() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

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

    if (filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT) === false || (int) $input['quantity'] < 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Quantity must be zero or a positive whole number"]);
        exit;
    }

    $input['packagingID'] = (int) $input['packagingID'];
    $input['quantity'] = (int) $input['quantity'];

    $packaging = new Packaging();

    if ($packaging->updatePackagingStock($input)) {
        echo json_encode(["status" => "success", "message" => "Packaging Stock Updated Successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to Update Packaging Stock"]);
    }
}

function archivePackaging() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

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

    $packaging = new Packaging();

    if ($packaging->archivePackaging((int) $input['packagingID'])) {
        echo json_encode(["status" => "success", "message" => "Packaging archived successfully"]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Packaging was not found or is already archived"]);
    }
}
