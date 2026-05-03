<?php
require_once __DIR__ . '../../vendor/autoload.php';
require_once __DIR__ . '/../config/jwt.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json'); // always return JSON

set_exception_handler(function ($exception) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Internal server error"
    ]);
    error_log($exception->getMessage());
    exit;
});

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

function getBearerToken() {
    $authHeader = getAuthorizationHeader();

    if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $matches[1];
    }

    if (!empty($_COOKIE['ragsak_auth'])) {
        return $_COOKIE['ragsak_auth'];
    }

    return null;
}

function getCurrentAuthUser() {
    return $GLOBALS['currentAuthUser'] ?? null;
}

function validateJWT() {
    $token = getBearerToken();

    if (!$token) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Authentication required"]);
        exit;
    }
    $secretKey = getJwtSecret();

    try {
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        $now = time();
        $sessionStartedAt = isset($decoded->session_started)
            ? (int) $decoded->session_started
            : (isset($decoded->iat) ? (int) $decoded->iat : $now);

        if (($now - $sessionStartedAt) > getJwtMaxSessionSeconds()) {
            clearAuthCookie();
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Session expired"]);
            exit;
        }

        $expiresAt = isset($decoded->exp) ? (int) $decoded->exp : 0;
        if ($expiresAt > 0 && ($expiresAt - $now) <= getJwtRenewThresholdSeconds()) {
            issueAuthToken([
                'sub' => isset($decoded->sub) ? (int) $decoded->sub : null,
                'username' => $decoded->username ?? null,
                'role' => $decoded->role ?? null
            ], $sessionStartedAt);
        }

        $GLOBALS['currentAuthUser'] = $decoded;
        return $decoded;
    } catch (Exception $e) {
        clearAuthCookie();
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

        if($method == "POST" && $action == "update") {
            updateProductInfo();
        }

        if($method == "POST" && $action == "update_stock") {
            updateProductStock();
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

        if($method == "POST" && $action == "update") {
            updateRawMaterialInfo();
        }

        if($method == "POST" && $action == "update_stock") {
            updateRawMaterialStock();
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

        if($method == "POST" && $action == "update") {
            updatePackagingInfo();
        }

        if($method == "POST" && $action == "update_stock") {
            updatePackagingStock();
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

        if($method == "GET" && $action == "me") {
            validateJWT();
            getAuthenticatedUser();
        }

        if($method == "POST" && $action == "logout") {
            logoutUser();
        }
}
