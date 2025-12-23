<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Create a fake request to admin.aeos365.test/login
$request = \Illuminate\Http\Request::create('https://admin.aeos365.test/login', 'POST');

// Show all login routes
$routes = app('router')->getRoutes();
echo "All login routes:\n";
foreach ($routes as $route) {
    if (str_contains($route->uri(), 'login')) {
        echo "  URI: " . $route->uri() . "\n";
        echo "  Methods: " . implode(', ', $route->methods()) . "\n";
        echo "  Domain: " . ($route->getDomain() ?? 'N/A') . "\n";
        echo "  Action: " . $route->getActionName() . "\n";
        echo "  Name: " . ($route->getName() ?? 'N/A') . "\n";
        echo "  ---\n";
    }
}

echo "\nMatched Route for admin.aeos365.test:\n";
$route = $routes->match($request);
echo "  Name: " . ($route->getName() ?? 'N/A') . "\n";
echo "  Action: " . $route->getActionName() . "\n";
echo "  Domain: " . ($route->getDomain() ?? 'N/A') . "\n";
echo "  Middleware: " . implode(', ', $route->middleware()) . "\n";

