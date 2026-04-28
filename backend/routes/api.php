<?php
require_once __DIR__ . '../../vendor/autoload.php';
require_once __DIR__ . '/../config/jwt.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json'); // always return JSON

function getAuthorizationHeader() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') {
                return $value;
            }
        }
    }

    return null;
}

function validateJWT() {
    $authHeader = getAuthorizationHeader();

    if (!$authHeader) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "No token provided"]);
        exit;
    }

    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid token format"]);
        exit;
    }

    $token = $matches[1];
    $secretKey = getJwtSecret();

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
