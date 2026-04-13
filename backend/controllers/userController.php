<?php
require_once __DIR__ . '../../models/user.php';

function getLogin() {
    $user = new User();

    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {
        http_response_code(400); // Bad Request
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $success = $user->login($input);

    header('Content-Type: application/json');

    if ($success) {
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Login Successful",
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "message" => "Login Failed"
        ]);
    }
}

function registerUsers() {
    $user = new User();

    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {
        http_response_code(400); // Bad Request
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }
    
    $password = $input['password'];
    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
    $input['password'] = $hashedPass;
    $success = $user->registration($input);

    header('Content-Type: application/json');

    if ($success) {
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "User Registered Successfully",
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "message" => "Failed to Register User"
        ]);
    }
}