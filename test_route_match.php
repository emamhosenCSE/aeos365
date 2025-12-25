<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Bootstrap the application
$kernel->bootstrap();

// Count all routes
$routes = app('router')->getRoutes();
echo "Total routes: " . count($routes->getRoutes()) . "\n";

// Debug: list all routes with api/v1
echo "\nRoutes with 'api/v1' in URI:\n";
$count = 0;
foreach ($routes as $route) {
    if (strpos($route->uri(), 'api/v1') !== false) {
        echo "  - " . ($route->getDomain() ?: 'NO_DOMAIN') . '/' . $route->uri() . " -> " . $route->getName() . "\n";
        $count++;
    }
}
echo "\nFound $count api/v1 routes.\n";

// Now try to match
$request = Illuminate\Http\Request::create(
    'https://admin.aeos365.test/api/v1/tenants/7626e511-fa4b-4bf1-b7a0-60802fbbc37f',
    'GET'
);
$request->headers->set('host', 'admin.aeos365.test');

echo "\nRequest host: " . $request->getHost() . "\n";
echo "Request URI: " . $request->getRequestUri() . "\n";

try {
    $route = $routes->match($request);
    echo "Route matched: " . $route->getName() . "\n";
} catch (Exception $e) {
    echo "Match error: " . $e->getMessage() . "\n";
}
