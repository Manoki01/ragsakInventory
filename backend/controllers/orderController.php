<?php

require_once __DIR__ . '../../models/orders.php';

function failOrderValidation($message) {
    http_response_code(422);
    echo json_encode([
        "status" => "error",
        "message" => $message
    ]);
    exit;
}

function validateOrderText($value, $fieldName, $maxLength) {
    $value = trim((string) $value);

    if ($value === '' || strlen($value) > $maxLength) {
        failOrderValidation($fieldName . " is invalid");
    }

    return $value;
}

function validateOrderID($value, $fieldName) {
    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
        failOrderValidation($fieldName . " must be a positive whole number");
    }

    return (int) $value;
}

function validateOrderStatus($value) {
    $value = strtolower(trim((string) $value));
    $allowed = ['pending', 'completed', 'canceled'];

    if (!in_array($value, $allowed, true)) {
        failOrderValidation("Order status is invalid");
    }

    return $value;
}

function validateOrderDate($value) {
    $value = trim((string) $value);
    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        failOrderValidation("Completion date is invalid");
    }

    return $value . " 00:00:00";
}

function validateOrderPayload($input, $requireOrderID = false) {
    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $payload = [
        'customerName' => validateOrderText($input['customerName'] ?? '', 'Customer name', 50),
        'contactNumber' => validateOrderText($input['contactNumber'] ?? '', 'Contact number', 30),
        'customerAddress' => validateOrderText($input['customerAddress'] ?? '', 'Address', 255),
        'productID' => validateOrderID($input['productID'] ?? null, 'Product ID'),
        'processID' => validateOrderID($input['processID'] ?? null, 'Process ID'),
        'quantity' => validateOrderID($input['quantity'] ?? null, 'Quantity'),
        'orderStatus' => validateOrderStatus($input['orderStatus'] ?? 'pending'),
        'dateCompletion' => validateOrderDate($input['dateCompletion'] ?? '')
    ];

    if ($requireOrderID) {
        $payload['orderID'] = validateOrderID($input['orderID'] ?? null, 'Order ID');
    }

    return $payload;
}

function getOrders() {
    $order = new Order();
    $data = $order->getAll();

    echo json_encode([
        "status" => "success",
        "data" => $data
    ]);
}

function getFinishedOrderProducts() {
    $order = new Order();
    $data = $order->getFinishedProducts();

    echo json_encode([
        "status" => "success",
        "data" => $data
    ]);
}

function createOrder() {
    $input = json_decode(file_get_contents("php://input"), true);
    $payload = validateOrderPayload($input);

    $order = new Order();
    $product = $order->finishedProductExists($payload['productID'], $payload['processID']);

    if ($product === null) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Orders can only use finished products"]);
        exit;
    }

    if ((int) $product['quantity'] < $payload['quantity']) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Order quantity is greater than finished product stock"]);
        exit;
    }

    if ($order->createOrder($payload)) {
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Order registered successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to register order"]);
    }
}

function updateOrder() {
    $input = json_decode(file_get_contents("php://input"), true);
    $payload = validateOrderPayload($input, true);

    $order = new Order();
    $product = $order->finishedProductExists($payload['productID'], $payload['processID']);

    if ($product === null) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Orders can only use finished products"]);
        exit;
    }

    if ((int) $product['quantity'] < $payload['quantity']) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Order quantity is greater than finished product stock"]);
        exit;
    }

    if ($order->updateOrder($payload)) {
        echo json_encode(["status" => "success", "message" => "Order updated successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to update order"]);
    }
}
