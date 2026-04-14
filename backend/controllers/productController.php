<?php

require_once __DIR__ . '../../models/products.php';

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

    $product = new Product();

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