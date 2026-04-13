<?php
header('Content-Type: application/json'); // always return JSON

$request = $_GET['route'] ?? '';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch($request) {
    case 'products':
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