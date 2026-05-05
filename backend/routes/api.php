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

function requireRole($allowedRoles) {
    $user = getCurrentAuthUser();
    $role = $user->role ?? '';

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "You are not allowed to access this resource"
        ]);
        exit;
    }
}

$request = $_GET['route'] ?? '';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch($request) {
    case 'products':
        validateJWT();
        requireRole(['Chairman', 'President', 'Supervisor', 'Manufacturing']);
        require_once __DIR__ . '../../controllers/productController.php';

        if($method == "GET" && $action == "formula") {
            getProductFormula();
        } else if($method == "GET") {
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

        if($method == "POST" && $action == "archive") {
            archiveProduct();
        }

        if($method == "POST" && $action == "save_formula") {
            saveProductFormula();
        }

        break;
    case 'raw_materials':
        validateJWT();
        requireRole(['Chairman', 'President', 'Supervisor', 'Manufacturing']);
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

        if($method == "POST" && $action == "archive") {
            archiveRawMaterial();
        }

        break;
    case 'packaging':
        validateJWT();
        requireRole(['Chairman', 'President', 'Supervisor', 'Manufacturing']);
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

        if($method == "POST" && $action == "archive") {
            archivePackaging();
        }

        break;
    case 'process':
        validateJWT();
        requireRole(['President', 'Supervisor']);
        require_once __DIR__ . '../../controllers/processController.php';

        if($method == "GET" && $action == "details") {
            getProcessDetails();
        } else if($method == "GET") {
            getProcesses();
        }

        if($method == "POST" && $action == "create") {
            createProcess();
        }

        if($method == "POST" && $action == "update") {
            updateProcess();
        }

        if($method == "POST" && $action == "archive") {
            archiveProcess();
        }

        break;

    case 'orders':
        validateJWT();
        requireRole(['Chairman', 'President', 'Supervisor']);
        require_once __DIR__ . '../../controllers/orderController.php';

        if($method == "GET" && $action == "finished_products") {
            getFinishedOrderProducts();
        } else if($method == "GET") {
            getOrders();
        }

        if($method == "POST" && $action == "create") {
            createOrder();
        }

        if($method == "POST" && $action == "update") {
            updateOrder();
        }

        if($method == "POST" && $action == "update_info") {
            updateOrderInfo();
        }

        if($method == "POST" && $action == "update_status") {
            updateOrderStatusOnly();
        }

        if($method == "POST" && $action == "archive") {
            archiveOrder();
        }

        break;

    case 'reports':
        validateJWT();
        requireRole(['Chairman', 'President', 'Supervisor']);
        require_once __DIR__ . '../../controllers/reportController.php';

        if($method == "GET") {
            getReportDataset();
        }

        if($method == "POST" && $action == "log_export") {
            logReportExport();
        }

        break;

    case 'dashboard':
        validateJWT();
        requireRole(['Chairman', 'President', 'Supervisor', 'Manufacturing']);
        require_once __DIR__ . '../../controllers/dashboardController.php';

        if($method == "GET") {
            getDashboardDataset();
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

        if($method == "GET" && $action == "approval") {
            validateJWT();
            requireRole(['President']);
            getApprovalDataset();
        }

        if($method == "GET" && $action == "me") {
            validateJWT();
            getAuthenticatedUser();
        }

        if($method == "POST" && $action == "approval_status") {
            validateJWT();
            requireRole(['President']);
            updateUserApprovalStatus();
        }

        if($method == "POST" && $action == "logout") {
            logoutUser();
        }
}
