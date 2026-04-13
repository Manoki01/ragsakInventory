<?php

require_once __DIR__ . '../../models/packaging.php';

function getPackaging() {
    $packaging = new Packaging();

    $data = $packaging->getAll();

    echo json_encode($data);
}

function createPackaging() {
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

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

    $packaging = new Packaging();

    $success = $packaging->stockPackaging($input);
    
    header('Content-Type: application/json');

    if ($success) {
        http_response_code(201);
        echo json_encode([
            "message" => "Packaging Stocked Successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "message" => "Failed to Stock Packaging"
        ]);
    }
}