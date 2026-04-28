<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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

    // Sanitize inputs
    $input['username'] = filter_var($input['username'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $input['username'] = trim($input['username']);
    $input['password'] = trim($input['password']);

    // Validate username
    if (strlen($input['username']) < 4 || strlen($input['username']) > 50) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username must be 4-50 characters long"]);
        exit;
    }

    // Validate password
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $input['password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters long and contain at least one uppercase letter, one lowercase letter, and one number"]);
        exit;
    }

    $success = $user->login($input);

    header('Content-Type: application/json');

    if ($success) {
        $secretKey = 'your-256-bit-secret-key-here-for-jwt-auth'; // Use env var in production
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600; // 1 hour
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'user' => $input['username']
        ];
        $jwt = JWT::encode($payload, $secretKey, 'HS256');
        
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Login Successful",
            "token" => $jwt
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid credentials"
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
    
    // Sanitize inputs
    $input['username'] = filter_var($input['username'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $input['username'] = trim($input['username']);
    $input['role'] = filter_var($input['role'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $input['role'] = trim($input['role']);
    $input['password'] = trim($input['password']);

    // Validate username
    if (strlen($input['username']) < 4 || strlen($input['username']) > 50) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username must be 4-50 characters long"]);
        exit;
    }

    // Validate password
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $input['password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters long and contain at least one uppercase letter, one lowercase letter, and one number"]);
        exit;
    }

    // Validate role
    $allowedRoles = ['Chairman', 'President', 'Supervisor', 'Manufacturing'];
    if (!in_array($input['role'], $allowedRoles)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid role"]);
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
            "status" => "error",
            "message" => "Failed to Register User"
        ]);
    }
}