<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/products?categoryId=leather';
$_GET['route'] = 'api/products';
$_GET['categoryId'] = 'leather';

require_once __DIR__ . '/../config/bootstrap.php';

// Dispatch manually via ApiGatewayProvider / RouteManager or ResourceController
$controller = new ResourceController();
// Set resource parameter
$reflector = new ReflectionProperty(RouteProvider::class, 'params');
// Let's test calling ResourceController directly with param
$routeParams = ['resource' => 'products'];
$method = new ReflectionMethod(ApiController::class, 'setParams');
$method->setAccessible(true);
$method->invoke($controller, $routeParams);

try {
    $controller->index();
} catch (Throwable $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}
