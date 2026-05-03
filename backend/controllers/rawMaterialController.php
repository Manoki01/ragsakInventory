<?php

require_once __DIR__ .  '../../models/raw_materials.php';

function validateRawMaterialCreatePayload($input) {
    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $name = trim((string) ($input['unitName'] ?? ''));
    $unitType = trim((string) ($input['unitType'] ?? ''));
    $materialType = trim((string) ($input['materialType'] ?? ''));
    $price = $input['unitPrice'] ?? null;

    if ($name === '' || strlen($name) > 100) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Raw material name is invalid"]);
        exit;
    }

    if ($unitType === '' || strlen($unitType) > 50) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Unit type is invalid"]);
        exit;
    }

    if ($materialType === '' || strlen($materialType) > 50) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Material type is invalid"]);
        exit;
    }

    if (!is_numeric($price) || (float) $price < 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Unit price must be a valid non-negative number"]);
        exit;
    }

    $input['unitName'] = $name;
    $input['unitType'] = $unitType;
    $input['materialType'] = $materialType;
    $input['unitPrice'] = (float) $price;

    return $input;
}

function validateRawMaterialStockPayload($input) {
    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    if (filter_var($input['rawMaterialID'] ?? null, FILTER_VALIDATE_INT) === false || (int) $input['rawMaterialID'] <= 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Raw material ID must be a positive whole number"]);
        exit;
    }

    if (filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT) === false || (int) $input['quantity'] <= 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Quantity must be a positive whole number"]);
        exit;
    }

    $input['rawMaterialID'] = (int) $input['rawMaterialID'];
    $input['quantity'] = (int) $input['quantity'];

    return $input;
}

function getRawMaterials() {
    $rawMaterial = new Raw_Material();

    $data = $rawMaterial->getAll();

    echo json_encode([
        "status" => "success",
        "data" => $data
    ]);
}

function createRawMaterial() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    $input = validateRawMaterialCreatePayload($input);

    $rawMaterial = new Raw_Material();

    if ($rawMaterial->rawMaterialExistsIgnoreCase($input['unitName'])) {
        http_response_code(409);
        echo json_encode([
            "status" => "error",
            "message" => "Raw material already exists, including archived records"
        ]);
        exit;
    }

    $success = $rawMaterial->createRawMaterial($input);

    header('Content-Type: application/json');

    if ($success) { 
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Raw Material Added Successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "message" => "Failed to Add Raw Material"
        ]);
    }
}

function updateRawMaterialInfo() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    if (filter_var($input['rawMaterialID'] ?? null, FILTER_VALIDATE_INT) === false || (int) $input['rawMaterialID'] <= 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Raw material ID must be a positive whole number"]);
        exit;
    }

    $input['rawMaterialID'] = (int) $input['rawMaterialID'];
    $input['unitName'] = trim((string) ($input['unitName'] ?? ''));
    $input['unitType'] = trim((string) ($input['unitType'] ?? ''));
    $input['unitPrice'] = $input['unitPrice'] ?? null;

    if ($input['unitName'] === '' || strlen($input['unitName']) > 100) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Raw material name is invalid"]);
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

    $rawMaterial = new Raw_Material();

    if ($rawMaterial->rawMaterialNameExistsForOtherMaterial($input['unitName'], $input['rawMaterialID'])) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Raw material already exists, including archived records"]);
        exit;
    }

    if ($rawMaterial->updateRawMaterialInfo($input)) {
        echo json_encode(["status" => "success", "message" => "Raw Material Updated Successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to Update Raw Material"]);
    }
}

function stockRawMaterial() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    $input = validateRawMaterialStockPayload($input);
    $authenticatedUser = getCurrentAuthUser();
    $input['userID'] = isset($authenticatedUser->sub) ? (int) $authenticatedUser->sub : 0;

    if ($input['userID'] <= 0) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Authentication required"]);
        exit;
    }

    $rawMaterial = new Raw_Material();

    $success = $rawMaterial->stockRawMaterial($input);
    
    header('Content-Type: application/json');

    if ($success) {
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Raw Material Stocked Successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "message" => "Failed to Stock Raw Material"
        ]);
    }
}

function updateRawMaterialStock() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    if (filter_var($input['rawMaterialID'] ?? null, FILTER_VALIDATE_INT) === false || (int) $input['rawMaterialID'] <= 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Raw material ID must be a positive whole number"]);
        exit;
    }

    if (filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT) === false || (int) $input['quantity'] < 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Quantity must be zero or a positive whole number"]);
        exit;
    }

    $input['rawMaterialID'] = (int) $input['rawMaterialID'];
    $input['quantity'] = (int) $input['quantity'];
    $authenticatedUser = getCurrentAuthUser();
    $input['userID'] = isset($authenticatedUser->sub) ? (int) $authenticatedUser->sub : 0;

    if ($input['userID'] <= 0) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Authentication required"]);
        exit;
    }

    $rawMaterial = new Raw_Material();

    if ($rawMaterial->updateRawMaterialStock($input)) {
        echo json_encode(["status" => "success", "message" => "Raw Material Stock Updated Successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to Update Raw Material Stock"]);
    }
}

function archiveRawMaterial() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    if (filter_var($input['rawMaterialID'] ?? null, FILTER_VALIDATE_INT) === false || (int) $input['rawMaterialID'] <= 0) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Raw material ID must be a positive whole number"]);
        exit;
    }

    $rawMaterial = new Raw_Material();

    if ($rawMaterial->archiveRawMaterial((int) $input['rawMaterialID'])) {
        echo json_encode(["status" => "success", "message" => "Raw material archived successfully"]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Raw material was not found or is already archived"]);
    }
}
