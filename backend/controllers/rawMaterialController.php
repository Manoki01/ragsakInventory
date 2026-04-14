<?php

require_once __DIR__ .  '../../models/raw_materials.php';

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

    $rawMaterial = new Raw_Material();

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

function stockRawMaterial() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    $rawMaterial = new Raw_Material();

    $success = $rawMaterial->stockRawMaterial($input);
    
    header('Content-Type: application/json');

    if ($success) {
        http_response_code(201);
        echo json_encode([
            "message" => "Raw Material Stocked Successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "message" => "Failed to Stock Raw Material"
        ]);
    }
}