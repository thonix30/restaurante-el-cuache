<?php
// imports
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\PublicController;
use App\Controllers\CatalogoController;
use App\Controllers\SalonController;
use App\Controllers\AdminController;
use App\Controllers\PersonalController;
use App\Controllers\EquipoController;
use App\Utils\Config;

// Backend/api/v1/index.php

// 1. Load Autoloader
// Disable HTML error output to keep JSON valid
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../autoload.php';

date_default_timezone_set(Config::get('timezone', 'America/Guatemala'));

// 2. Set Headers / CORS
header("Access-Control-Allow-Origin: " . Config::get('cors_origin', '*'));
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("X-Content-Type-Options: nosniff");

// 3. Handle Preflight Options Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 4. Initialize Router
$router = new Router();

// 5. Register Controllers manually
$router->registerController(AuthController::class);
$router->registerController(PublicController::class);
$router->registerController(CatalogoController::class);
$router->registerController(SalonController::class);
$router->registerController(AdminController::class);
$router->registerController(PersonalController::class);
$router->registerController(EquipoController::class);

// 6. Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
