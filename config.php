<?php
/**
 * Konfigurasi API TPS Online H2H - CEISA 4.0
 * Memuat konfigurasi dari file .env secara otomatis
 */

// ===== PHP 7.x Compatibility Polyfills =====
if (!function_exists('str_starts_with')) {
    function str_starts_with(?string $haystack, ?string $needle): bool
    {
        if ($needle === null || $needle === '') return true;
        if ($haystack === null) return false;
        return strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(?string $haystack, ?string $needle): bool
    {
        if ($needle === null || $needle === '') return true;
        if ($haystack === null) return false;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(?string $haystack, ?string $needle): bool
    {
        if ($needle === null || $needle === '') return true;
        if ($haystack === null) return false;
        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): array
    {
        $env = [];
        if (!file_exists($path)) {
            return $env;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                $len = strlen($value);
                if ($len >= 2) {
                    if (($value[0] === '"' && $value[$len - 1] === '"') ||
                        ($value[0] === "'" && $value[$len - 1] === "'")) {
                        $value = substr($value, 1, -1);
                    }
                }

                $env[$key] = $value;
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
        return $env;
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }
        return $_ENV[$key] ?? $default;
    }
}

// Muat .env
loadEnv(__DIR__ . '/.env');

return [
    // ===== API CEISA Configuration =====
    'base_url' => env('CEISA_BASE_URL', 'https://sandbox-gw.beacukai.go.id/v1/openapi-tps'),
    'auth_url' => env('CEISA_AUTH_URL', 'https://sandbox-gw.beacukai.go.id/v1/openapi-auth/user/login'),
    
    // Header beacukai-api-key
    'api_key'  => env('CEISA_API_KEY', '64dd6fdb-25cf-4985-9b8f-982feb00d6dd'),
    
    // Kredensial login API CEISA
    'username' => env('CEISA_USERNAME', 'itprimamas'),
    'password' => env('CEISA_PASSWORD', 'Psuit@2025'),
    
    // ===== Session Configuration =====
    'session_name' => 'ceisa4_dashboard',
    'session_lifetime' => 28800, // 8 jam dalam detik
    
    // ===== Application Settings =====
    'app_name' => env('APP_NAME', 'CEISA 4.0 — TPS Online Dashboard'),
    'app_version' => '1.0.0',
    'timezone' => env('TIMEZONE', 'Asia/Jakarta'),
    'auto_auth' => true, // Auto login via ENV (langsung ke dashboard)
    
    // ===== cURL Settings =====
    'curl_timeout' => 45,        // timeout dalam detik
    'curl_verify_ssl' => filter_var(env('CURL_VERIFY_SSL', 'false'), FILTER_VALIDATE_BOOLEAN),

    // ===== Database Settings =====
    'db' => [
        'host' => env('DB_HOST', '192.168.0.192'),
        'user' => env('DB_USER', 'itpsu'),
        'pass' => env('DB_PASS', '123123'),
        'names' => [
            'tpsonline' => env('DB_NAME_TPSONLINE', 'tpsonline'),
            'tpp'       => env('DB_NAME_TPP', 'tpp_primamas'),
            'primamas'  => env('DB_NAME_PRIMAMAS', 'primamas'),
        ]
    ],
];
