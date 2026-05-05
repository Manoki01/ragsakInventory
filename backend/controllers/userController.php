<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/jwt.php';

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

    $authenticatedUser = $user->login($input);

    header('Content-Type: application/json');

    if ($authenticatedUser && !empty($authenticatedUser['loginBlocked'])) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => $authenticatedUser['status'] === 'pending'
                ? "Your account is still pending approval"
                : "Your account registration was denied"
        ]);
    } else if ($authenticatedUser) {
        issueAuthToken([
            'sub' => $authenticatedUser['userID'],
            'username' => $authenticatedUser['username'],
            'role' => $authenticatedUser['role']
        ]);
        
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Login Successful",
            "user" => $authenticatedUser
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid credentials"
        ]);
    }
}

function getAuthenticatedUser() {
    $decodedUser = getCurrentAuthUser();

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "user" => [
            "userID" => isset($decodedUser->sub) ? (int) $decodedUser->sub : null,
            "username" => $decodedUser->username ?? null,
            "role" => $decodedUser->role ?? null
        ]
    ]);
}

function getApprovalDataset() {
    $user = new User();

    echo json_encode([
        "status" => "success",
        "data" => $user->getApprovalDataset()
    ]);
}

function updateUserApprovalStatus() {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $userID = filter_var($input['userID'] ?? null, FILTER_VALIDATE_INT);
    $status = strtolower(trim((string) ($input['status'] ?? '')));

    if (!$userID) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "User is invalid"]);
        exit;
    }

    if (!in_array($status, ['approved', 'denied'], true)) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Approval status is invalid"]);
        exit;
    }

    $user = new User();

    if ($user->updateStatus($userID, $status)) {
        echo json_encode([
            "status" => "success",
            "message" => $status === 'approved' ? "User approved" : "User denied"
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Pending user was not found"
        ]);
    }
}

function logoutUser() {
    clearAuthCookie();

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Logout successful"
    ]);
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

    if ($user->usernameExistsIgnoreCase($input['username'])) {
        http_response_code(409);
        echo json_encode([
            "status" => "error",
            "message" => "Username already exists"
        ]);
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
            "message" => "Registration submitted for approval",
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Failed to Register User"
        ]);
    }
}
