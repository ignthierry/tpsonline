<?php
/**
 * Login Page — CEISA 4.0 TPS Online Dashboard
 * PT. Primamas Segara Utama (TPS Lini 2)
 */
require_once __DIR__ . '/includes/session.php';
$config = require __DIR__ . '/config.php';

// Jika sudah login, langsung arahkan ke dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$rememberedUser = $_COOKIE['ceisa_remember_user'] ?? '';
$isRemembered = !empty($rememberedUser);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Portal Masuk CEISA 4.0 TPS Online H2H — PT. Primamas Segara Utama">
    <title>Masuk Sistem — <?= htmlspecialchars($config['app_name']) ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- CSS Styles -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('ceisa_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    
    <style>
        :root {
            --login-glow-1: rgba(16, 185, 129, 0.15);
            --login-glow-2: rgba(59, 130, 246, 0.15);
        }
        
        body.login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: var(--bg-body);
            position: relative;
            overflow-x: hidden;
            font-family: 'Inter', sans-serif;
        }

        /* Ambient background lighting */
        .ambient-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.6;
            animation: pulseOrb 10s ease-in-out infinite alternate;
        }
        .ambient-orb-1 {
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, #10b981 0%, rgba(16,185,129,0) 70%);
            top: -120px;
            left: -100px;
        }
        .ambient-orb-2 {
            width: 520px;
            height: 520px;
            background: radial-gradient(circle, #3b82f6 0%, rgba(59,130,246,0) 70%);
            bottom: -150px;
            right: -100px;
            animation-delay: -5s;
        }
        @keyframes pulseOrb {
            0% { transform: scale(0.9) translate(0, 0); opacity: 0.4; }
            100% { transform: scale(1.15) translate(30px, 20px); opacity: 0.7; }
        }

        /* Container & Glass Card */
        .login-wrapper {
            width: 100%;
            max-width: 460px;
            position: relative;
            z-index: 10;
        }

        .auth-card {
            background: var(--bg-card);
            border: 1px solid var(--border-medium);
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .auth-card:hover {
            box-shadow: 0 24px 55px rgba(0, 0, 0, 0.45), 0 0 25px rgba(16, 185, 129, 0.1);
        }

        /* Header Branding */
        .auth-brand {
            text-align: center;
            margin-bottom: 30px;
        }
        .brand-badges {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 9999px;
            padding: 5px 14px;
            font-size: 0.76rem;
            font-weight: 700;
            color: #10b981;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }
        .brand-icon-wrapper {
            width: 58px;
            height: 58px;
            margin: 0 auto 12px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(59,130,246,0.2));
            border: 1px solid rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
            box-shadow: 0 8px 20px rgba(16,185,129,0.25);
        }
        .brand-title {
            margin: 0;
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-primary);
        }
        .brand-subtitle {
            margin: 6px 0 0;
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Form Controls */
        .input-group-custom {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group-custom label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 7px;
            letter-spacing: 0.01em;
        }
        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon-left {
            position: absolute;
            left: 14px;
            color: var(--text-secondary);
            font-size: 1rem;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .input-field-custom {
            width: 100%;
            padding: 12px 42px 12px 42px;
            background: var(--bg-input);
            border: 1px solid var(--border-medium);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 0.92rem;
            font-family: inherit;
            transition: all 0.25s ease;
        }
        .input-field-custom:focus {
            outline: none;
            border-color: #10b981;
            background: var(--bg-input-focus, var(--bg-input));
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        .btn-toggle-eye {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 6px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: color 0.2s ease;
        }
        .btn-toggle-eye:hover {
            color: var(--text-primary);
        }

        /* Checkbox & Options */
        .form-options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            font-size: 0.84rem;
        }
        .remember-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--text-secondary);
            user-select: none;
        }
        .remember-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #10b981;
            cursor: pointer;
        }

        /* Submit Button */
        .btn-submit-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 0.96rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35);
            transition: all 0.25s ease;
        }
        .btn-submit-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(16, 185, 129, 0.45);
            filter: brightness(1.05);
        }
        .btn-submit-login:active:not(:disabled) {
            transform: translateY(0);
        }
        .btn-submit-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .auth-banner-alert {
            display: none;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 20px;
            align-items: center;
            gap: 10px;
        }
        .auth-banner-alert.visible {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        .auth-banner-alert.error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        .auth-banner-alert.success {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .auth-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .spinner-login {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="login-page">

    <!-- Ambient Orbs -->
    <div class="ambient-orb ambient-orb-1"></div>
    <div class="ambient-orb ambient-orb-2"></div>

    <!-- Floating Theme Switcher -->
    <button class="theme-toggle-floating" id="theme-toggle" onclick="toggleTheme()" title="Ubah Mode (Gelap / Terang)" aria-label="Toggle theme">
        <span class="theme-toggle-icon" id="theme-toggle-icon">🌙</span>
        <span class="theme-toggle-text" id="theme-toggle-text">Dark</span>
    </button>

    <div class="login-wrapper">
        <div class="auth-card">
            
            <!-- Branding Header -->
            <div class="auth-brand">
                <div class="brand-badges">
                    <span class="pulse-dot"></span> CEISA 4.0 Host-to-Host
                </div>
                <div class="brand-icon-wrapper">
                    🏛️
                </div>
                <h1 class="brand-title">TPS Online H2H</h1>
                <p class="brand-subtitle">PT. Primamas Segara Utama (TPS Lini 2)</p>
            </div>

            <!-- Banner Alert (Hanya muncul dinamis saat ada pesan/error) -->
            <div class="auth-banner-alert" id="auth-alert">
                <span id="alert-icon">⚠️</span>
                <span id="alert-msg"></span>
            </div>

            <!-- Login Form -->
            <form id="form-login" onsubmit="return submitLogin(event)">
                <div class="input-group-custom">
                    <label for="username">Username / ID Pengguna</label>
                    <div class="input-with-icon">
                        <span class="input-icon-left">👤</span>
                        <input type="text" id="username" name="username" class="input-field-custom" 
                               value="<?= htmlspecialchars($rememberedUser) ?>"
                               placeholder="Masukkan username" required <?= empty($rememberedUser) ? 'autofocus' : '' ?> autocomplete="username">
                    </div>
                </div>

                <div class="input-group-custom">
                    <label for="password">Kata Sandi (Password)</label>
                    <div class="input-with-icon">
                        <span class="input-icon-left">🔒</span>
                        <input type="password" id="password" name="password" class="input-field-custom" 
                               placeholder="Masukkan kata sandi" required <?= !empty($rememberedUser) ? 'autofocus' : '' ?> autocomplete="current-password">
                        <button type="button" class="btn-toggle-eye" id="btn-toggle-pwd" onclick="togglePasswordVisibility()" title="Lihat / Sembunyikan Password">
                            👁️
                        </button>
                    </div>
                </div>

                <div class="form-options-row">
                    <label class="remember-label">
                        <input type="checkbox" id="remember-me" name="remember_me" <?= $isRemembered ? 'checked' : '' ?>>
                        <span>Ingat saya selama 30 hari</span>
                    </label>
                    <a href="javascript:void(0)" onclick="showHelp()" style="color:#60a5fa; text-decoration:none; font-size:0.8rem;">Bantuan?</a>
                </div>

                <button type="submit" class="btn-submit-login" id="btn-submit-login">
                    <span class="spinner-login" id="btn-spinner"></span>
                    <span id="btn-label">Masuk ke Sistem</span>
                </button>
            </form>

            <!-- Footer -->
            <div class="auth-footer" style="margin-top: 32px;">
                Direktorat Jenderal Bea dan Cukai &bull; PT. Primamas Segara Utama<br>
                Dashboard TPS Online CEISA 4.0 v1.0
            </div>

        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePasswordVisibility() {
            const pwdInput = document.getElementById('password');
            const eyeBtn = document.getElementById('btn-toggle-pwd');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                eyeBtn.textContent = '🙈';
                eyeBtn.setAttribute('title', 'Sembunyikan Password');
            } else {
                pwdInput.type = 'password';
                eyeBtn.textContent = '👁️';
                eyeBtn.setAttribute('title', 'Lihat Password');
            }
        }

        // Show Help Dialog
        function showHelp() {
            Swal.fire({
                title: 'Bantuan Otentikasi',
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                        <p>Gunakan akun yang telah didaftarkan pada database tabel <code>users</code>.</p>
                        <div style="background:rgba(0,0,0,0.15); border-radius:8px; padding:12px; margin-top:10px;">
                            <b>Akun Standar Tersedia:</b><br>
                            1. <b>Administrator</b>: <code>admin</code> / <code>admin123</code><br>
                            2. <b>Operator</b>: <code>operator</code> / <code>operator123</code>
                        </div>
                        <p style="margin-top:12px; color:var(--text-secondary);">Jika lupa password atau ingin membuat akun baru, hubungi bagian IT PT. Primamas Segara Utama.</p>
                    </div>
                `,
                icon: 'info',
                confirmButtonColor: '#10b981'
            });
        }

        // Handle Form Submission
        async function submitLogin(e) {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const rememberMe = document.getElementById('remember-me').checked;

            const btn = document.getElementById('btn-submit-login');
            const spinner = document.getElementById('btn-spinner');
            const label = document.getElementById('btn-label');
            const alertBox = document.getElementById('auth-alert');
            const alertMsg = document.getElementById('alert-msg');
            const alertIcon = document.getElementById('alert-icon');

            if (!username || !password) {
                showAlert('Username dan password harus diisi', 'error');
                return false;
            }

            // Loading state
            btn.disabled = true;
            spinner.style.display = 'inline-block';
            label.textContent = 'Memverifikasi akun...';
            alertBox.classList.remove('visible');

            try {
                const res = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'login',
                        username: username,
                        password: password,
                        remember_me: rememberMe
                    })
                });

                const data = await res.json();

                if (data.success) {
                    showAlert(data.message, 'success');
                    label.textContent = 'Mengalihkan...';
                    
                    // Notifikasi sukses
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Berhasil!',
                        text: data.message,
                        timer: 1000,
                        showConfirmButton: false,
                        timerProgressBar: true
                    });

                    setTimeout(() => {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 900);
                } else {
                    showAlert(data.message || 'Login gagal. Periksa username dan password Anda.', 'error');
                    btn.disabled = false;
                    spinner.style.display = 'none';
                    label.textContent = 'Masuk ke Sistem';
                    document.getElementById('password').focus();
                }
            } catch (err) {
                showAlert('Gagal terhubung ke server otentikasi: ' + err.message, 'error');
                btn.disabled = false;
                spinner.style.display = 'none';
                label.textContent = 'Masuk ke Sistem';
            }

            return false;
        }

        function showAlert(msg, type) {
            const alertBox = document.getElementById('auth-alert');
            const alertMsg = document.getElementById('alert-msg');
            const alertIcon = document.getElementById('alert-icon');
            alertMsg.textContent = msg;
            alertIcon.textContent = type === 'success' ? '✅' : '❌';
            alertBox.className = 'auth-banner-alert visible ' + type;
        }

        // Theme Toggle
        function updateThemeUI(theme) {
            const isDark = theme === 'dark';
            const icon = document.getElementById('theme-toggle-icon');
            const text = document.getElementById('theme-toggle-text');
            const btn = document.getElementById('theme-toggle');
            if (icon) icon.textContent = isDark ? '🌙' : '☀️';
            if (text) text.textContent = isDark ? 'Dark' : 'Light';
            if (btn) btn.setAttribute('title', isDark ? 'Ubah ke Mode Terang' : 'Ubah ke Mode Gelap');
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem('ceisa_theme', next);
            document.documentElement.setAttribute('data-theme', next);
            updateThemeUI(next);
        }

        updateThemeUI(document.documentElement.getAttribute('data-theme') || 'dark');

        // Bersihkan query string di URL jika ada (misal ?logged_out=1) agar address bar bersih
        if (window.history.replaceState && window.location.search) {
            window.history.replaceState(null, '', window.location.pathname);
        }
    </script>
</body>
</html>
