<?php
/**
 * CeisaClient - HTTP Client untuk API TPS Online H2H CEISA 4.0
 * 
 * Menangani autentikasi JWT, header beacukai-api-key, auto-refresh token dari ENV, dan request GET/POST
 */

class CeisaClient
{
    private $baseUrl;
    private $authUrl;
    private $apiKey;
    private $username;
    private $password;
    private $timeout;
    private $verifySSL;
    private $cacheFile;

    public function __construct()
    {
        $config = require __DIR__ . '/../config.php';
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->authUrl = $config['auth_url'];
        $this->apiKey = $config['api_key'] ?? '';
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->timeout = $config['curl_timeout'] ?? 45;
        $this->verifySSL = $config['curl_verify_ssl'] ?? false;
        
        $cacheDir = __DIR__ . '/../data';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $this->cacheFile = $cacheDir . '/token_cache.json';
    }

    /**
     * Dapatkan Bearer Access Token yang valid (otomatis login jika expired / belum ada)
     */
    public function getValidAccessToken(bool $forceRefresh = false): string
    {
        if (!$forceRefresh) {
            // 1. Cek dari Session
            if (isset($_SESSION['access_token']) && !empty($_SESSION['access_token'])) {
                $expiry = $_SESSION['token_expiry'] ?? 0;
                if (time() < ($expiry - 120)) { // buffer 2 menit
                    return $_SESSION['access_token'];
                }
            }

            // 2. Cek dari File Cache
            if (file_exists($this->cacheFile)) {
                $cache = json_decode(@file_get_contents($this->cacheFile), true);
                if (!empty($cache['access_token']) && isset($cache['expires_at'])) {
                    if (time() < ($cache['expires_at'] - 120)) {
                        // Sinkronkan ke session
                        $_SESSION['access_token'] = $cache['access_token'];
                        $_SESSION['refresh_token'] = $cache['refresh_token'] ?? '';
                        $_SESSION['token_expiry'] = $cache['expires_at'];
                        $_SESSION['username'] = $cache['username'] ?? $this->username;
                        $_SESSION['name'] = $cache['name'] ?? 'User';
                        return $cache['access_token'];
                    }
                }
            }
        }

        // 3. Login otomatis menggunakan kredensial dari config / ENV
        $loginRes = $this->login($this->username, $this->password, $this->apiKey);
        if ($loginRes['success'] && !empty($loginRes['access_token'])) {
            return $loginRes['access_token'];
        }

        throw new Exception($loginRes['message'] ?? 'Gagal memperoleh access token dari API CEISA');
    }

    /**
     * Login ke API CEISA dan dapatkan JWT token
     */
    public function login(?string $username = null, ?string $password = null, ?string $apiKey = null): array
    {
        $user = $username ?? $this->username;
        $pass = $password ?? $this->password;
        $key = $apiKey ?? $this->apiKey;

        $payload = json_encode([
            'username' => $user,
            'password' => $pass,
        ]);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (!empty($key)) {
            $headers[] = 'beacukai-api-key: ' . $key;
        }

        $ch = curl_init($this->authUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $error,
                'code' => 0,
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['item']['access_token'])) {
            $item = $data['item'];
            $accessToken = $item['access_token'];
            $refreshToken = $item['refresh_token'] ?? '';
            $expiresIn = $item['expires_in'] ?? 28800;
            $expiresAt = time() + $expiresIn;

            // Ekstrak nama user dari token / item
            $userName = $item['name'] ?? $item['preferred_username'] ?? $user;

            // Simpan ke session
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['access_token'] = $accessToken;
                $_SESSION['refresh_token'] = $refreshToken;
                $_SESSION['token_expiry'] = $expiresAt;
                $_SESSION['username'] = $user;
                $_SESSION['name'] = $userName;
                $_SESSION['login_time'] = time();
            }

            // Simpan ke file cache
            @file_put_contents($this->cacheFile, json_encode([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $expiresAt,
                'username' => $user,
                'name' => $userName,
                'cached_at' => time(),
            ], JSON_PRETTY_PRINT));

            return [
                'success' => true,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => $expiresIn,
                'name' => $userName,
                'message' => 'Login berhasil',
            ];
        }

        return [
            'success' => false,
            'message' => $data['message'] ?? $data['detail'] ?? 'Login gagal. Periksa username, password, dan beacukai-api-key.',
            'code' => $httpCode,
            'raw' => $data,
        ];
    }

    /**
     * GET request ke endpoint API CEISA (dengan auto-retry saat token expired)
     */
    public function get(string $endpoint, array $params = [], string $accessToken = ''): array
    {
        // Hapus parameter kosong
        $params = array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        });

        // Dapatkan token otomatis jika belum disediakan
        if (empty($accessToken)) {
            try {
                $accessToken = $this->getValidAccessToken();
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Gagal autentikasi: ' . $e->getMessage(),
                    'code' => 401,
                    'data' => null,
                ];
            }
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ];

        if (!empty($this->apiKey)) {
            $headers[] = 'beacukai-api-key: ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $error,
                'code' => 0,
                'data' => null,
            ];
        }

        $data = json_decode($response, true);

        // Jika 401 Unauthorized, coba force refresh token sekali lagi dan ulangi request
        if ($httpCode === 401) {
            try {
                $freshToken = $this->getValidAccessToken(true);
                return $this->get($endpoint, $params, $freshToken);
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Token expired dan gagal diperbarui: ' . $e->getMessage(),
                    'code' => 401,
                    'token_expired' => true,
                    'data' => null,
                ];
            }
        }

        // Handle forbidden (403)
        if ($httpCode === 403) {
            return [
                'success' => false,
                'message' => $data['detail'] ?? ($data['result'] ?? 'Akses ditolak (403). Akun atau parameter tidak sesuai.'),
                'code' => 403,
                'data' => $data['data'] ?? null,
                'raw' => $data,
            ];
        }

        // Handle bad request (400)
        if ($httpCode === 400) {
            return [
                'success' => false,
                'message' => $data['detail'] ?? ($data['result'] ?? 'Parameter tidak valid (400).'),
                'code' => 400,
                'data' => null,
                'raw' => $data,
            ];
        }

        // Sukses
        if ($httpCode === 200 || $httpCode === 201) {
            return [
                'success' => true,
                'message' => $data['detail'] ?? ($data['result'] ?? 'Data berhasil diambil.'),
                'code' => $data['code'] ?? $httpCode,
                'result' => $data['result'] ?? '',
                'data' => $data['data'] ?? null,
                'path' => $data['path'] ?? $endpoint,
                'date' => $data['date'] ?? '',
                'version' => $data['version'] ?? '',
                'raw' => $data,
            ];
        }

        // Error lainnya
        return [
            'success' => false,
            'message' => $data['detail'] ?? ($data['result'] ?? 'Terjadi kesalahan (HTTP ' . $httpCode . ')'),
            'code' => $httpCode,
            'data' => $data['data'] ?? null,
            'raw' => $data,
        ];
    }

    /**
     * POST request ke endpoint API CEISA
     */
    public function post(string $endpoint, array $payload, string $accessToken = ''): array
    {
        if (empty($accessToken)) {
            try {
                $accessToken = $this->getValidAccessToken();
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Gagal autentikasi: ' . $e->getMessage(),
                    'code' => 401,
                ];
            }
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ];

        if (!empty($this->apiKey)) {
            $headers[] = 'beacukai-api-key: ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $error,
                'code' => 0,
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode === 401) {
            try {
                $freshToken = $this->getValidAccessToken(true);
                return $this->post($endpoint, $payload, $freshToken);
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Token expired: ' . $e->getMessage(),
                    'code' => 401,
                ];
            }
        }

        return [
            'success' => in_array($httpCode, [200, 201]),
            'message' => $data['detail'] ?? ($data['result'] ?? ($httpCode <= 201 ? 'Berhasil' : 'Gagal')),
            'code' => $data['code'] ?? $httpCode,
            'data' => $data['data'] ?? null,
            'result' => $data['result'] ?? '',
            'raw' => $data,
        ];
    }
}
