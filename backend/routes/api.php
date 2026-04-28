<?php
require_once __DIR__ . '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json'); // always return JSON

function validateJWT() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "No token provided"]);
        exit;
    }
    $authHeader = $headers['Authorization'];
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid token format"]);
        exit;
    }
    $token = $matches[1];
    $secretKey = 'your-secret-key-here';
    try {
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid token"]);
        exit;
    }
}

$request = $_GET['route'] ?? '';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch($request) {
    case 'products':
        validateJWT();
        require_once __DIR__ . '../../controllers/productController.php';

        if($method == "GET") {
            getProducts();
        }

        if($method == "POST" && $action == "create") {
            createProduct();
        }

        if($method == "POST" && $action == "stock") {
            stockProduct();
        }

        break;
    case 'raw_materials':
        validateJWT();
        require_once __DIR__ . '../../controllers/rawMaterialController.php';

        if($method == "GET") {
            getRawMaterials();
        }

        if($method == "POST" && $action == "create") {
            createRawMaterial();
        }

        if($method == "POST" && $action == "stock") {
            stockRawMaterial();
        }

        break;
    case 'packaging':
        validateJWT();
        require_once __DIR__ .  '../../controllers/packagingController.php';

        if($method == "GET") {
            getPackaging();
        }

        if($method == "POST" && $action == "create") {
            createPackaging();
        }

        if($method == "POST" && $action == "stock") {
            stockPackaging();
        }

        break;
    case 'process':
        validateJWT();
        require_once __DIR__ . '../../controllers/processController.php';

        if($method == "GET") {
            getProcesses();
        }

        break;

    case 'users':
        require_once __DIR__ . '../../controllers/userController.php';

        if($method == "POST" && $action == "login") {
            getLogin();
        }

        if($method == "POST" && $action == "register") {
            registerUsers();
        }
}