<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Throwable;

class InstallController extends Controller
{
    public const TEMP_KEY = 'base64:hmU1T3OuvHdi5t1wULI8Xp7geI+JIWGog9pBCNxslY8=';

    public const MIN_PHP_VERSION = '8.1';

    public const REQUIRED_EXTENSIONS = [
        'bcmath',
        'ctype',
        'json',
        'mbstring',
        'openssl',
        'PDO',
        'tokenizer',
        'xml',
        'xmlwriter',
        'curl',
        'fileinfo',
        'zip',
    ];

    protected array $databaseDrivers = [
        'mysql' => 'MySQL/MariaDB',
        'sqlite' => 'SQLite',
    ];

    protected bool $hasRequirements;
    protected array $requirements;

    public function __construct()
    {
        $this->requirements = static::getRequirements();
        $this->hasRequirements = !in_array(false, $this->requirements, true);

        $this->middleware(function (Request $request, callable $next) {
            $isInstalled = File::exists(storage_path('installed'));
            $hasRealKey = config('app.key') !== self::TEMP_KEY;

            // L'application est considérée comme installée si le fichier installed existe
            // ET que la clé n'est plus temporaire
            if ($isInstalled && $hasRealKey) {
                return redirect('/')->with('error', __('messages.install.already_installed'));
            }

            // Si seulement une des conditions est vraie, il y a un état incohérent
            // On permet l'accès aux routes d'installation pour corriger
            // (ex: fichier installed existe mais clé temporaire, ou vice versa)

            return $next($request);
        });
    }

    private function getCorrectAppUrl()
    {
        // Utiliser l'URL de la requête actuelle pour déterminer le bon domaine
        $request = request();

        if ($request) {
            $scheme = $request->isSecure() ? 'https' : 'http';
            $host = $request->getHost();
            $port = $request->getPort();

            $url = $scheme . '://' . $host;

            // Ajouter le port seulement s'il n'est pas standard
            if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
                $url .= ':' . $port;
            }

            return $url;
        }

        // Fallback
        return url('/');
    }

    private function prepareEnvironment()
    {
        // Créer les dossiers nécessaires
        $directories = [
            storage_path('app'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            storage_path('app/public'),
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }
        }

        // Créer le fichier de log si nécessaire
        $logFile = storage_path('logs/laravel.log');
        if (!File::exists($logFile)) {
            File::put($logFile, '');
            chmod($logFile, 0644);
        }
    }

    private function createEnvFile()
    {
        // Le fichier .env existe déjà avec la clé temporaire pour permettre à Laravel de démarrer
        // On met juste à jour les valeurs nécessaires pour l'installation
        if (File::exists(base_path('.env'))) {
            $this->updateEnvValues([
                'APP_NAME' => '"CentralCorp Panel"',
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => $this->getCorrectAppUrl(),
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => database_path('database.sqlite'),
            ]);
        } else {
            // Fallback si le fichier n'existe pas
            $this->createBasicEnvFile();
        }
    }

    private function createBasicEnvFile()
    {
        $envContent = "APP_NAME=\"CentralCorp Panel\"\n";
        $envContent .= "APP_ENV=production\n";
        $envContent .= "APP_KEY=" . self::TEMP_KEY . "\n";
        $envContent .= "APP_DEBUG=false\n";
        $envContent .= "APP_URL=" . $this->getCorrectAppUrl() . "\n\n";
        $envContent .= "LOG_CHANNEL=stack\n";
        $envContent .= "LOG_DEPRECATIONS_CHANNEL=null\n";
        $envContent .= "LOG_LEVEL=debug\n\n";
        $envContent .= "DB_CONNECTION=sqlite\n";
        $envContent .= "DB_DATABASE=" . database_path('database.sqlite') . "\n\n";
        $envContent .= "BROADCAST_DRIVER=log\n";
        $envContent .= "CACHE_DRIVER=file\n";
        $envContent .= "FILESYSTEM_DISK=local\n";
        $envContent .= "QUEUE_CONNECTION=sync\n";
        $envContent .= "SESSION_DRIVER=file\n";
        $envContent .= "SESSION_LIFETIME=120\n\n";
        $envContent .= "MEMCACHED_HOST=127.0.0.1\n\n";
        $envContent .= "REDIS_HOST=127.0.0.1\n";
        $envContent .= "REDIS_PASSWORD=null\n";
        $envContent .= "REDIS_PORT=6379\n\n";
        $envContent .= "MAIL_MAILER=smtp\n";
        $envContent .= "MAIL_HOST=mailpit\n";
        $envContent .= "MAIL_PORT=1025\n";
        $envContent .= "MAIL_USERNAME=null\n";
        $envContent .= "MAIL_PASSWORD=null\n";
        $envContent .= "MAIL_ENCRYPTION=null\n";
        $envContent .= "MAIL_FROM_ADDRESS=\"hello@example.com\"\n";
        $envContent .= "MAIL_FROM_NAME=\"\${APP_NAME}\"\n\n";

        File::put(base_path('.env'), $envContent);
    }

    /**
     * Échappe une valeur destinée au fichier .env : retire les sauts de ligne,
     * échappe \, " et $, puis entoure de guillemets doubles. Les valeurs déjà
     * entourées de guillemets sont conservées telles quelles (après nettoyage
     * des sauts de ligne) pour ne pas double-quoter les littéraux fournis.
     */
    private function escapeEnvValue(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);

        if (strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return $value;
        }

        $value = str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value);

        return '"' . $value . '"';
    }

    private function updateEnvValues(array $values)
    {
        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        foreach ($values as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}=" . $this->escapeEnvValue((string) $value);

            // preg_replace_callback : le remplacement n'est pas réinterprété
            // (pas de backreferences accidentelles via $ dans la valeur).
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace_callback($pattern, fn () => $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        File::put($envPath, $envContent);
    }

    private function ensureDatabaseExists()
    {
        $databasePath = database_path('database.sqlite');
        $databaseDir = dirname($databasePath);

        // Créer le dossier database s'il n'existe pas
        if (!File::exists($databaseDir)) {
            File::makeDirectory($databaseDir, 0755, true);
        }

        // Créer le fichier SQLite s'il n'existe pas
        if (!file_exists($databasePath)) {
            try {
                // Créer le fichier
                touch($databasePath);
                // Définir les permissions appropriées
                chmod($databasePath, 0666);
            } catch (Exception $e) {
                throw new Exception('Impossible de créer le fichier de base de données SQLite: ' . $e->getMessage());
            }
        }

        // Vérifier que le fichier est accessible en écriture
        if (!is_writable($databasePath)) {
            throw new Exception('Le fichier de base de données SQLite n\'est pas accessible en écriture: ' . $databasePath);
        }
    }

    public function showDatabase()
    {
        try {
            if (!$this->hasRequirements) {
                return view('install.requirements', [
                    'requirements' => $this->requirements,
                ]);
            }

            return view('install.database', [
                'databaseDrivers' => $this->databaseDrivers,
            ]);
        } catch (Exception $e) {
            return response($e->getMessage(), 500);
        }
    }

    /**
     * Unified installation handler - handles both database setup and admin creation
     */
    public function install(Request $request)
    {
        try {
            // Validate all fields: database + admin
            $this->validate($request, [
                'type' => ['required', Rule::in(array_keys($this->databaseDrivers))],
                'host' => ['required_unless:type,sqlite'],
                'port' => ['nullable', 'integer', 'between:1,65535'],
                'database' => ['required_unless:type,sqlite'],
                'user' => ['required_unless:type,sqlite'],
                'db_password' => ['nullable'],
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'password' => ['required', 'confirmed', Password::default()],
            ]);

            $this->prepareEnvironment();

            // Step 1: Configure database
            $databaseType = $request->input('type');

            if ($databaseType === 'sqlite') {
                // Create SQLite database file
                $this->ensureDatabaseExists();

                // Update .env for SQLite
                $this->updateEnvValues([
                    'DB_CONNECTION' => 'sqlite',
                    'DB_DATABASE' => database_path('database.sqlite'),
                    'DB_HOST' => '',
                    'DB_PORT' => '',
                    'DB_USERNAME' => '',
                    'DB_PASSWORD' => '',
                ]);

                // Configure and test SQLite connection
                DB::purge('sqlite');
                Config::set('database.connections.sqlite.database', database_path('database.sqlite'));
                DB::connection('sqlite')->getPdo();
            } else {
                $host = $request->input('host');
                $port = $request->input('port') ?: '3306';
                $database = $request->input('database');
                $user = $request->input('user');
                $password = $request->input('db_password');

                // Test connection first
                Config::set('database.connections.test', [
                    'driver' => $databaseType,
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $user,
                    'password' => $password,
                ]);

                DB::connection('test')->getPdo();

                // Update .env with MySQL config
                $this->updateEnvValues([
                    'DB_CONNECTION' => $databaseType,
                    'DB_HOST' => $host,
                    'DB_PORT' => $port,
                    'DB_DATABASE' => $database,
                    'DB_USERNAME' => $user,
                    'DB_PASSWORD' => $password,
                ]);

                // Configure the default connection for migrations
                Config::set('database.default', $databaseType);
                Config::set("database.connections.{$databaseType}", [
                    'driver' => $databaseType,
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $user,
                    'password' => $password,
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => null,
                ]);
            }

            // Step 2: Clear caches and run migrations
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            Artisan::call('migrate:fresh', ['--force' => true]);

            // Step 3: Create admin user
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);

            // Step 4: Post-installation setup
            Artisan::call('storage:link');

            File::put(storage_path('installed'), 'Installation complétée le ' . now()->format('Y-m-d H:i:s') . "\nAdmin: " . $user->email);

            $this->generateAppKey();

            $correctUrl = $this->getCorrectAppUrl();
            $this->updateEnvValues(['APP_URL' => $correctUrl]);
            Config::set('app.url', $correctUrl);

            try {
                Artisan::call('cache:clear');
            } catch (Exception $e) {
                // Ignore cache errors
            }

            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            return redirect()->route('install.finish')->with('success', __('messages.install.install_success'));
        } catch (Throwable $t) {
            return back()->withInput()->with('error', __('messages.install.install_error') . ' ' . $t->getMessage());
        }
    }

    public function finish()
    {
        try {
            if (!File::exists(storage_path('installed'))) {
                return redirect()->route('install.database');
            }

            return view('install.success');
        } catch (Exception $e) {
            return response($e->getMessage(), 500);
        }
    }

    private function generateAppKey()
    {
        $key = 'base64:' . base64_encode(Encrypter::generateKey(config('app.cipher')));

        $this->updateEnvValues([
            'APP_KEY' => $key,
        ]);

        // Recharger la configuration
        Config::set('app.key', $key);
    }

    public static function getRequirements(): array
    {
        $requirements = [
            'php' => version_compare(PHP_VERSION, static::MIN_PHP_VERSION, '>='),
            'writable' => is_writable(base_path()),
            'storage-writable' => is_writable(storage_path()),
            'bootstrap-writable' => is_writable(base_path('bootstrap/cache')),
        ];

        foreach (static::REQUIRED_EXTENSIONS as $extension) {
            $requirements['extension-' . $extension] = extension_loaded($extension);
        }

        return $requirements;
    }

    public static function parsePhpVersion(): string
    {
        preg_match('/^(\d+)\.(\d+)/', PHP_VERSION, $matches);

        if (count($matches) > 2) {
            return "{$matches[1]}.{$matches[2]}";
        }

        return PHP_VERSION;
    }
}
