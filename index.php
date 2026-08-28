<?php
/**
 * Index Page — CEISA 4.0 TPS Online Dashboard
 * Jika kredensial sudah ada di .env (auto_auth), langsung diarahkan ke Dashboard
 */
require_once __DIR__ . '/includes/session.php';

$config = require __DIR__ . '/config.php';

// Jika auto_auth aktif atau sudah login, langsung ke dashboard tanpa perlu login manual
if (isLoggedIn() || !empty($config['auto_auth'])) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login Dashboard TPS Online H2H — CEISA 4.0 Bea Cukai">
    <title>Login — <?= htmlspecialchars($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon">🏛️</div>
            <h1>CEISA 4.0</h1>
            <p>TPS Online H2H Dashboard</p>
        </div>

        <div class="login-alert" id="login-alert"></div>

        <form id="login-form" onsubmit="return handleLogin(event)">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-input" 
                       value="<?= htmlspecialchars($config['username'] ?? '') ?>"
                       placeholder="Masukkan username CEISA" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" 
                       placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn btn-primary" id="btn-login">
                <span class="spinner" id="login-spinner"></span>
                <span id="login-text">Masuk ke Dashboard</span>
            </button>
        </form>

        <p style="text-align:center; margin-top:24px; font-size:0.75rem; color:var(--text-muted);">
            Bea Cukai — Direktorat Jenderal Bea dan Cukai<br>
            v<?= htmlspecialchars($config['app_version']) ?>
        </p>
    </div>

    <script>
        async function handleLogin(e) {
            e.preventDefault();

            const btn = document.getElementById('btn-login');
            const spinner = document.getElementById('login-spinner');
            const text = document.getElementById('login-text');
            const alert = document.getElementById('login-alert');

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!username || !password) {
                showAlert('Username dan password harus diisi', 'error');
                return false;
            }

            // Loading state
            btn.disabled = true;
            spinner.style.display = 'inline-block';
            text.textContent = 'Memproses login...';
            alert.classList.remove('visible');

            try {
                const response = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password }),
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = '/dashboard.php';
                    }, 1000);
                } else {
                    showAlert(data.message || 'Login gagal', 'error');
                    btn.disabled = false;
                    spinner.style.display = 'none';
                    text.textContent = 'Masuk ke Dashboard';
                }
            } catch (err) {
                showAlert('Gagal terhubung ke server: ' + err.message, 'error');
                btn.disabled = false;
                spinner.style.display = 'none';
                text.textContent = 'Masuk ke Dashboard';
            }

            return false;
        }

        function showAlert(message, type) {
            const alert = document.getElementById('login-alert');
            alert.textContent = message;
            alert.className = 'login-alert ' + type + ' visible';
        }
    </script>
</body>
</html>
