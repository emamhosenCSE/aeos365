<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \Aero\Platform\Models\LandlordUser::first();

if (!$user) {
    echo "No landlord users found!\n";
    exit(1);
}

echo "User: {$user->name}\n";
echo "Email: {$user->email}\n";
echo "Guard Name: {$user->guard_name}\n";
echo "\nRoles:\n";
foreach ($user->roles as $role) {
    echo "  - {$role->name} (guard: {$role->guard_name}, scope: {$role->scope})\n";
}
echo "\nHas 'Super Administrator' role: " . ($user->hasRole('Super Administrator') ? 'YES' : 'NO') . "\n";
echo "is_super_admin attribute: " . ($user->is_super_admin ? 'YES' : 'NO') . "\n";

// Test with guard parameter
echo "\nTesting with guard parameter:\n";
echo "hasRole('Super Administrator', 'landlord'): " . ($user->hasRole(['Super Administrator'], 'landlord') ? 'YES' : 'NO') . "\n";
