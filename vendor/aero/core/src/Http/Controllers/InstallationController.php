<?php

namespace Aero\Core\Http\Controllers;

use Aero\Core\Models\Module;
use Aero\Core\Models\SystemSetting;
use Aero\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class InstallationController extends Controller
{
    /**
     * Installation lock file
     */
    private const LOCK_FILE = 'storage/framework/aero-installation.lock';

    /**
     * Show installation welcome page
     */
    public function index()
    {
        // Check if already installed
        if ($this->isInstalled()) {
            return Inertia::render('Installation/AlreadyInstalled', [
                'title' => 'Already Installed',
                'appUrl' => config('app.url'),
            ]);
        }

        return Inertia::render('Installation/Welcome', [
            'title' => 'Welcome to Aero Enterprise Suite',
            'version' => config('app.version', '1.0.0'),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
        ]);
    }

    /**
     * Show requirements check page
     */
    public function requirements()
    {
        if ($this->isInstalled()) {
            return redirect()->route('login');
        }

        $checks = $this->performRequirementsCheck();

        return Inertia::render('Installation/Requirements', [
            'title' => 'System Requirements',
            'checks' => $checks,
            'canProceed' => $checks['allPassed'],
        ]);
    }

    /**
     * Show database configuration page
     */
    public function database()
    {
        if ($this->isInstalled()) {
            return redirect()->route('login');
        }

        return Inertia::render('Installation/Database', [
            'title' => 'Database Configuration',
            'currentConfig' => [
                'connection' => config('database.default'),
                'host' => config('database.connections.' . config('database.default') . '.host'),
                'port' => config('database.connections.' . config('database.default') . '.port'),
                'database' => config('database.connections.' . config('database.default') . '.database'),
                'username' => config('database.connections.' . config('database.default') . '.username'),
            ],
        ]);
    }

    /**
     * Test database connection
     */
    public function testDatabase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'host' => 'required|string',
            'port' => 'required|integer',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $connection = config('database.default');
            config([
                "database.connections.{$connection}.host" => $request->host,
                "database.connections.{$connection}.port" => $request->port,
                "database.connections.{$connection}.database" => $request->database,
                "database.connections.{$connection}.username" => $request->username,
                "database.connections.{$connection}.password" => $request->password,
            ]);

            DB::purge($connection);
            DB::reconnect($connection);
            DB::connection()->getPdo();

            return response()->json([
                'success' => true,
                'message' => 'Database connection successful!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run installation process
     */
    public function install(Request $request)
    {
        if ($this->isInstalled()) {
            return response()->json([
                'success' => false,
                'message' => 'System is already installed',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8|confirmed',
            'app_name' => 'nullable|string|max:255',
            'timezone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create lock
        $this->createLock();

        try {
            DB::beginTransaction();

            // Step 1: Run migrations
            $this->runMigrations();

            // Step 2: Seed core data
            $this->seedCoreData($request);

            // Step 3: Create admin user
            $user = $this->createAdminUser($request);

            // Step 4: Sync modules
            $this->syncModules();

            // Step 5: Mark as installed
            $this->markAsInstalled();

            DB::commit();
            $this->removeLock();

            return response()->json([
                'success' => true,
                'message' => 'Installation completed successfully!',
                'redirect' => route('login'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->removeLock();

            return response()->json([
                'success' => false,
                'message' => 'Installation failed: ' . $e->getMessage(),
                'error' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Perform requirements check
     */
    private function performRequirementsCheck(): array
    {
        $checks = [
            'php' => [
                'name' => 'PHP Version >= 8.2',
                'passed' => version_compare(PHP_VERSION, '8.2.0', '>='),
                'value' => PHP_VERSION,
            ],
            'extensions' => [],
            'permissions' => [],
        ];

        // Check PHP extensions
        $requiredExtensions = [
            'PDO' => 'pdo',
            'Mbstring' => 'mbstring',
            'OpenSSL' => 'openssl',
            'Tokenizer' => 'tokenizer',
            'XML' => 'xml',
            'Ctype' => 'ctype',
            'JSON' => 'json',
            'BCMath' => 'bcmath',
        ];

        foreach ($requiredExtensions as $name => $ext) {
            $checks['extensions'][] = [
                'name' => $name . ' Extension',
                'passed' => extension_loaded($ext),
                'value' => extension_loaded($ext) ? 'Enabled' : 'Disabled',
            ];
        }

        // Check directory permissions
        $writableDirs = [
            'Storage Directory' => storage_path(),
            'Bootstrap Cache' => bootstrap_path('cache'),
            'Storage Framework' => storage_path('framework'),
            'Storage Logs' => storage_path('logs'),
        ];

        foreach ($writableDirs as $name => $dir) {
            $writable = is_writable($dir);
            $checks['permissions'][] = [
                'name' => $name,
                'passed' => $writable,
                'value' => $writable ? 'Writable' : 'Not Writable',
                'path' => $dir,
            ];
        }

        // Check database connection
        try {
            DB::connection()->getPdo();
            $checks['database'] = [
                'name' => 'Database Connection',
                'passed' => true,
                'value' => 'Connected (' . DB::connection()->getDatabaseName() . ')',
            ];
        } catch (\Exception $e) {
            $checks['database'] = [
                'name' => 'Database Connection',
                'passed' => false,
                'value' => 'Failed: ' . $e->getMessage(),
            ];
        }

        // Calculate if all checks passed
        $allPassed = $checks['php']['passed'] && 
                     $checks['database']['passed'] &&
                     collect($checks['extensions'])->every(fn($check) => $check['passed']) &&
                     collect($checks['permissions'])->every(fn($check) => $check['passed']);

        $checks['allPassed'] = $allPassed;

        return $checks;
    }

    /**
     * Run migrations
     */
    private function runMigrations(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
    }

    /**
     * Seed core data
     */
    private function seedCoreData(Request $request): void
    {
        // Create roles
        $roles = [
            'Super Admin' => 'Full system access',
            'Admin' => 'Administrative access',
            'Manager' => 'Management access',
            'Employee' => 'Basic employee access',
        ];

        foreach ($roles as $name => $description) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }

        // Create system settings
        $settings = [
            ['key' => 'app_name', 'value' => $request->app_name ?? config('app.name'), 'type' => 'general'],
            ['key' => 'timezone', 'value' => $request->timezone ?? config('app.timezone'), 'type' => 'general'],
            ['key' => 'date_format', 'value' => 'Y-m-d', 'type' => 'general'],
            ['key' => 'time_format', 'value' => 'H:i:s', 'type' => 'general'],
            ['key' => 'currency', 'value' => 'USD', 'type' => 'general'],
            ['key' => 'installation_date', 'value' => now()->toDateTimeString(), 'type' => 'system'],
            ['key' => 'mode', 'value' => 'standalone', 'type' => 'system'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    /**
     * Create admin user
     */
    private function createAdminUser(Request $request): User
    {
        $user = User::firstOrCreate(
            ['email' => $request->admin_email],
            [
                'name' => $request->admin_name,
                'password' => Hash::make($request->admin_password),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Assign Super Admin role
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            $user->assignRole($superAdminRole);
        }

        return $user;
    }

    /**
     * Sync modules
     */
    private function syncModules(): void
    {
        Artisan::call('aero:sync-module', [
            '--prune' => true,
        ]);
    }

    /**
     * Check if already installed
     */
    private function isInstalled(): bool
    {
        try {
            DB::connection()->getPdo();
            
            if (!DB::getSchemaBuilder()->hasTable('migrations')) {
                return false;
            }

            $requiredTables = ['users', 'roles', 'system_settings', 'modules'];
            foreach ($requiredTables as $table) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    return false;
                }
            }

            if (User::count() === 0) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Mark as installed
     */
    private function markAsInstalled(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'installation_completed'],
            [
                'value' => now()->toDateTimeString(),
                'type' => 'system',
            ]
        );
    }

    /**
     * Create installation lock
     */
    private function createLock(): void
    {
        $lockPath = base_path(self::LOCK_FILE);
        File::ensureDirectoryExists(dirname($lockPath));
        File::put($lockPath, json_encode([
            'started_at' => now()->toDateTimeString(),
            'pid' => getmypid(),
        ]));
    }

    /**
     * Remove installation lock
     */
    private function removeLock(): void
    {
        $lockPath = base_path(self::LOCK_FILE);
        if (File::exists($lockPath)) {
            File::delete($lockPath);
        }
    }
}
