<?php
/**
 * TPS Tracking (Pergerakan Kontainer) CEISA 4.0 — TPS Online Dashboard
 * Halaman perekaman data tracking pergerakan kontainer di TPS (Gate In, Gate Out, Stacking, Truck In, Pickup, dll.)
 * Target Endpoint: POST /kirim-tps-tracking
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$config = require __DIR__ . '/config.php';
$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$loginTime = $_SESSION['login_time'] ?? time();
$userInitial = strtoupper(substr($username, 0, 2));

$defaultEndpoint = 'kirim-tps-tracking';
$nowDmyHis = date('d-m-Y H:i:s');
$activeDept = strtolower($_GET['dept'] ?? 'tpp');
if (!in_array($activeDept, ['tpp', 'gudang'])) {
    $activeDept = 'tpp';
}
$preloadCont = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['no_cont'] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPS Tracking Kontainer — <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('ceisa_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.35; transform: scale(0.75); }
        }
        .pulse-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            animation: pulseDot 1.4s infinite ease-in-out;
        }
        .content-area {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            height: calc(100vh - var(--header-height));
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        .content-area::-webkit-scrollbar {
            width: 8px;
        }
        .content-area::-webkit-scrollbar-track {
            background: var(--bg-base);
        }
        .content-area::-webkit-scrollbar-thumb {
            background: var(--border-medium);
            border-radius: 4px;
        }
        .content-area::-webkit-scrollbar-thumb:hover {
            background: var(--accent-blue);
        }
        .coco-container {
            padding: 24px 24px 80px 24px;
            max-width: 1440px;
            margin: 0 auto;
        }
        .coco-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--border-subtle);
            margin-bottom: 24px;
        }
        .section-header-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            padding-bottom: 8px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .field-group .label-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
        }
        .field-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin: 0;
        }
        .field-group label .req-star {
            color: var(--accent-red);
            font-weight: 700;
            margin-left: 2px;
        }
        .field-action-btn {
            font-size: 0.78rem;
            color: var(--accent-blue);
            background: transparent;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            padding: 0;
            text-transform: none;
            transition: color 0.15s;
        }
        .field-action-btn:hover {
            color: #60a5fa;
            text-decoration: underline;
        }
        .input-control {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border-medium);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.92rem;
            transition: all 0.2s ease;
        }
        .input-control:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .type-toggle-group {
            display: flex;
            background: var(--bg-input);
            padding: 4px;
            border-radius: 8px;
            border: 1px solid var(--border-medium);
            gap: 4px;
            height: 44px;
            align-items: center;
        }
        .type-btn {
            flex: 1;
            height: 36px;
            padding: 0 12px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .type-btn.active {
            background: var(--accent-blue);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
        }
        .dept-toggle-card {
            background: var(--bg-card);
            border: 1px solid var(--border-medium);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }
        .dept-toggle-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .dept-toggle-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--bg-input);
            padding: 4px;
            border-radius: 8px;
            border: 1px solid var(--border-medium);
            gap: 6px;
        }
        .dept-btn {
            height: 40px;
            border-radius: 6px;
            border: 1px solid transparent;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .dept-btn:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.05);
        }
        .dept-btn.active.dept-tpp {
            background: var(--accent-blue);
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 2px 10px rgba(59, 130, 246, 0.35);
        }
        .dept-btn.active.dept-gudang {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.35);
        }
        .tabs-nav {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border-subtle);
            margin-bottom: 20px;
            padding-bottom: 8px;
        }
        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 0.92rem;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }
        .tab-btn:hover {
            color: var(--text-primary);
            background: var(--bg-card-hover);
        }
        .tab-btn.active {
            background: rgba(59, 130, 246, 0.12);
            color: var(--accent-blue);
            border-bottom: 2px solid var(--accent-blue);
        }
        .json-box {
            width: 100%;
            height: 520px;
            background: #0d131f;
            color: #7dd3fc;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            padding: 18px;
            border-radius: 8px;
            border: 1px solid var(--border-medium);
            resize: vertical;
            line-height: 1.5;
        }
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
        }
        .badge-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .badge-in { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-out { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-ceisa { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .action-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-subtle);
        }
        .btn-action-sm {
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid var(--border-medium);
            background: var(--bg-surface);
            color: var(--text-primary);
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-action-sm:hover {
            background: var(--border-medium);
        }
        .btn-send-prod {
            background: var(--accent-green-gradient);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: transform 0.15s, opacity 0.15s;
            width: 100%;
            justify-content: center;
        }
        .btn-send-prod:hover {
            transform: translateY(-1px);
        }
        .btn-send-prod:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        /* Select2 Theme Customization */
        .select2-container--default .select2-selection--single {
            background-color: var(--bg-input) !important;
            border: 1px solid var(--border-medium) !important;
            border-radius: 8px !important;
            height: 42px !important;
            display: flex !important;
            align-items: center !important;
            transition: all 0.2s ease !important;
        }
        .select2-container--default .select2-selection--single:focus,
        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: var(--accent-blue) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
            outline: none !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--text-primary) !important;
            line-height: 40px !important;
            padding-left: 14px !important;
            padding-right: 36px !important;
            font-size: 0.92rem !important;
            font-family: 'JetBrains Mono', monospace !important;
            font-weight: 600 !important;
            letter-spacing: 0.5px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: var(--text-secondary) !important;
            font-weight: 400 !important;
            font-family: inherit !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            right: 10px !important;
            display: flex !important;
            align-items: center !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: var(--text-secondary) transparent transparent transparent !important;
        }
        .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
            border-color: transparent transparent var(--text-secondary) transparent !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__clear {
            color: var(--text-secondary) !important;
            font-size: 1.2rem !important;
            line-height: 40px !important;
            margin-right: 12px !important;
        }
        .select2-dropdown {
            background-color: var(--bg-surface) !important;
            border: 1px solid var(--border-medium) !important;
            border-radius: 8px !important;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45) !important;
            z-index: 9999 !important;
        }
        .select2-search--dropdown {
            padding: 8px !important;
        }
        .select2-search--dropdown .select2-search__field {
            background-color: var(--bg-input) !important;
            border: 1px solid var(--border-medium) !important;
            border-radius: 6px !important;
            color: var(--text-primary) !important;
            padding: 8px 12px !important;
            font-family: 'JetBrains Mono', monospace !important;
            font-size: 0.9rem !important;
            outline: none !important;
        }
        .select2-search--dropdown .select2-search__field:focus {
            border-color: var(--accent-blue) !important;
        }
        .select2-container--default .select2-results__option {
            padding: 10px 14px !important;
            font-size: 0.88rem !important;
            color: var(--text-primary) !important;
            border-bottom: 1px solid var(--border-subtle) !important;
            transition: background 0.15s !important;
        }
        .select2-container--default .select2-results__option:last-child {
            border-bottom: none !important;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: rgba(59, 130, 246, 0.18) !important;
            color: var(--text-primary) !important;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: rgba(59, 130, 246, 0.25) !important;
            color: var(--accent-blue) !important;
            font-weight: 600 !important;
        }
        /* ===== TIMELINE TRACKER STYLES ===== */
        .timeline-card-wrapper {
            background: var(--bg-surface, #ffffff);
            border: 1.5px solid var(--border-medium, #e2e8f0);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        [data-theme="light"] .timeline-card-wrapper {
            background: #ffffff;
            border-color: #cbd5e1;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
        }
        [data-theme="dark"] .timeline-card-wrapper {
            background: rgba(19, 29, 49, 0.85);
            border-color: rgba(255, 255, 255, 0.12);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .timeline-summary-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            background: var(--bg-card, #f8fafc);
            border: 1px solid var(--border-subtle, #e2e8f0);
            border-radius: 10px;
            padding: 10px 16px;
            margin-bottom: 14px;
            font-size: 0.82rem;
            color: var(--text-secondary, #64748b);
        }
        [data-theme="light"] .timeline-summary-bar {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #475569;
        }
        [data-theme="dark"] .timeline-summary-bar {
            background: var(--bg-card);
            border-color: var(--border-subtle);
            color: var(--text-secondary);
        }
        .timeline-summary-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .timeline-summary-item strong {
            color: var(--text-primary, #0f172a);
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
        }
        [data-theme="light"] .timeline-summary-item strong {
            color: #0f172a;
        }
        [data-theme="dark"] .timeline-summary-item strong {
            color: #f8fafc;
        }

        /* Batch Action Bar */
        .timeline-batch-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 14px;
            border-radius: 10px;
            background: var(--bg-card, #f8fafc);
            border: 1px solid var(--border-subtle, #e2e8f0);
        }
        [data-theme="light"] .timeline-batch-bar {
            background: #f8fafc;
            border-color: #e2e8f0;
        }
        [data-theme="dark"] .timeline-batch-bar {
            background: rgba(15, 23, 42, 0.6);
            border-color: var(--border-medium);
        }
        .btn-batch-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            line-height: 1.4;
            font-family: inherit;
        }
        .btn-batch-unsent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35);
        }
        .btn-batch-unsent:hover:not(:disabled) {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.5);
            transform: translateY(-1px);
        }
        .btn-batch-all {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.35);
        }
        .btn-batch-all:hover:not(:disabled) {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.5);
            transform: translateY(-1px);
        }
        .btn-batch-action:disabled,
        .btn-batch-disabled {
            opacity: 0.45 !important;
            cursor: not-allowed !important;
            transform: none !important;
            box-shadow: none !important;
            filter: grayscale(0.6);
        }
        .badge-batch-pill {
            background: rgba(255, 255, 255, 0.28);
            color: #ffffff;
            font-size: 0.73rem;
            font-weight: 700;
            padding: 1px 8px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.45);
        }

        .timeline-cards-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }
        @media (min-width: 860px) {
            .timeline-cards-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* ===== STEP CARD STYLING ===== */
        .step-card {
            background: var(--bg-card, #ffffff);
            border: 1.5px solid var(--border-medium, #e2e8f0);
            border-radius: 12px;
            padding: 15px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 12px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }
        [data-theme="light"] .step-card {
            background: #ffffff;
            border-color: #cbd5e1;
        }
        [data-theme="dark"] .step-card {
            background: var(--bg-card);
            border-color: var(--border-medium);
        }
        .step-card:hover {
            border-color: var(--accent-blue, #3b82f6);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
        }
        [data-theme="light"] .step-card:hover {
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.14);
            border-color: #3b82f6;
        }

        /* Active Selected State */
        .step-card.active-selected {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.08);
            box-shadow: 0 0 0 2.5px rgba(59, 130, 246, 0.4);
        }
        [data-theme="light"] .step-card.active-selected {
            background: #f0f7ff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2.5px rgba(59, 130, 246, 0.35);
        }
        [data-theme="dark"] .step-card.active-selected {
            background: rgba(59, 130, 246, 0.14);
            border-color: #3b82f6;
            box-shadow: 0 0 0 2.5px rgba(59, 130, 246, 0.4);
        }

        /* Sent Card State */
        .step-card.is-sent-card {
            border-color: rgba(16, 185, 129, 0.5);
            background: rgba(16, 185, 129, 0.06);
        }
        [data-theme="light"] .step-card.is-sent-card {
            background: #f0fdf4;
            border-color: #86efac;
        }
        [data-theme="dark"] .step-card.is-sent-card {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.45);
        }

        /* Sent + Selected Combo */
        .step-card.is-sent-card.active-selected {
            border-color: #10b981;
            box-shadow: 0 0 0 2.5px rgba(16, 185, 129, 0.4);
        }
        [data-theme="light"] .step-card.is-sent-card.active-selected {
            background: #ecfdf5;
            border-color: #10b981;
            box-shadow: 0 0 0 2.5px rgba(16, 185, 129, 0.35);
        }
        [data-theme="dark"] .step-card.is-sent-card.active-selected {
            background: rgba(16, 185, 129, 0.18);
            border-color: #10b981;
            box-shadow: 0 0 0 2.5px rgba(16, 185, 129, 0.4);
        }

        .step-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
        }
        .step-title-box {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-primary, #0f172a);
        }
        [data-theme="light"] .step-title-box {
            color: #0f172a;
        }
        [data-theme="light"] .step-card.active-selected .step-title-box {
            color: #1d4ed8;
        }
        [data-theme="light"] .step-card.is-sent-card .step-title-box {
            color: #15803d;
        }

        .step-badge-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--bg-input, #f1f5f9);
            border: 1.5px solid var(--border-medium, #cbd5e1);
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--text-primary, #334155);
            flex-shrink: 0;
        }
        [data-theme="light"] .step-badge-num {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #334155;
        }
        .step-card.active-selected .step-badge-num {
            background: #3b82f6;
            color: #ffffff;
            border-color: #2563eb;
        }
        .step-card.is-sent-card .step-badge-num {
            background: #10b981;
            color: #ffffff;
            border-color: #059669;
        }

        .step-badge-ready {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.35);
            font-size: 0.72rem;
            padding: 3px 9px;
            border-radius: 6px;
            font-weight: 700;
            white-space: nowrap;
        }
        [data-theme="light"] .step-badge-ready {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }
        [data-theme="dark"] .step-badge-ready {
            background: rgba(59, 130, 246, 0.18);
            color: #60a5fa;
            border-color: rgba(59, 130, 246, 0.4);
        }

        .step-badge-sent {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.35);
            font-size: 0.72rem;
            padding: 3px 9px;
            border-radius: 6px;
            font-weight: 700;
            white-space: nowrap;
        }
        [data-theme="light"] .step-badge-sent {
            background: #dcfce7;
            color: #15803d;
            border-color: #86efac;
        }
        [data-theme="dark"] .step-badge-sent {
            background: rgba(16, 185, 129, 0.18);
            color: #34d399;
            border-color: rgba(16, 185, 129, 0.4);
        }

        /* Inner Body Info */
        .step-body-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 0.78rem;
            color: var(--text-secondary, #64748b);
            line-height: 1.5;
            background: var(--bg-surface, #f8fafc);
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-subtle, #e2e8f0);
        }
        [data-theme="light"] .step-body-info {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #475569;
        }
        [data-theme="light"] .step-card.active-selected .step-body-info {
            background: #ffffff;
            border-color: #bfdbfe;
        }
        [data-theme="light"] .step-card.is-sent-card .step-body-info {
            background: #ffffff;
            border-color: #bbf7d0;
        }
        [data-theme="dark"] .step-body-info {
            background: rgba(0, 0, 0, 0.25);
            border-color: var(--border-subtle);
            color: var(--text-secondary);
        }
        .step-body-info .info-val {
            color: var(--text-primary, #0f172a);
            font-weight: 600;
        }
        [data-theme="light"] .step-body-info .info-val {
            color: #0f172a;
        }
        [data-theme="dark"] .step-body-info .info-val {
            color: #f8fafc;
        }

        .step-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 4px;
        }
        .btn-apply-step {
            flex: 1;
            background: var(--bg-card, #f1f5f9);
            color: var(--text-primary, #334155);
            border: 1px solid var(--border-medium, #cbd5e1);
            border-radius: 7px;
            padding: 8px 12px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.15s ease;
            font-family: inherit;
        }
        [data-theme="light"] .btn-apply-step {
            background: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }
        .btn-apply-step:hover {
            background: var(--accent-blue, #3b82f6);
            color: #ffffff;
            border-color: var(--accent-blue, #3b82f6);
        }
        .btn-apply-step.active {
            background: var(--accent-blue, #3b82f6);
            color: #ffffff;
            border-color: var(--accent-blue, #3b82f6);
            font-weight: 700;
        }
        [data-theme="light"] .btn-apply-step.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #1d4ed8;
        }

        .btn-quick-send {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.35);
            border-radius: 7px;
            padding: 8px 12px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
            font-family: inherit;
        }
        [data-theme="light"] .btn-quick-send {
            background: #ecfdf5;
            color: #059669;
            border-color: #a7f3d0;
        }
        .btn-quick-send:hover {
            background: #10b981;
            color: #ffffff;
            border-color: #059669;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        .btn-disabled-step {
            opacity: 0.45 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
        }

        /* Khusus Tab TPP: Hanya tampilkan Nomor Kontainer (sembunyikan field ukuran, jenis, kode tps, kode gudang) */
        body:not(.dept-is-gudang) #ukuran-cont-group,
        #section-extra-fields {
            display: none !important;
        }
        body:not(.dept-is-gudang) #no-cont-wrapper {
            grid-template-columns: 1fr !important;
        }
    </style>
</head>
<body data-login-time="<?= $loginTime ?>" class="<?= $activeDept === 'gudang' ? 'dept-is-gudang' : '' ?>">
    <div class="dashboard">
        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay"></div>

        <!-- ===== SIDEBAR ===== -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- ===== MAIN CONTENT ===== -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" id="menu-toggle">☰</button>
                    <div class="header-breadcrumb">
                        <span>CEISA 4.0</span>
                        <span class="separator">/</span>
                        <span>Kirim Dokumen</span>
                        <span class="separator">/</span>
                        <span class="current">TPS Tracking Kontainer</span>
                    </div>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="theme-toggle" title="Ubah Mode (Gelap / Terang)">
                        <span class="theme-toggle-icon">🌙</span>
                        <span class="theme-toggle-text">Dark</span>
                    </button>
                    <button class="btn-refresh-token" onclick="refreshAccessToken()" title="Perbarui token JWT dari server CEISA">
                        <span>🔄</span>
                        <span>Refresh Token</span>
                    </button>
                    <div class="header-user">
                        <div class="user-avatar"><?= e($userInitial) ?></div>
                        <span><?= e($username) ?></span>
                        <a href="logout.php" title="Keluar" style="color:var(--accent-red); margin-left:6px; text-decoration:none;">🚪</a>
                    </div>
                </div>
            </header>

            <!-- Main Body -->
            <main class="content-area">
                <div class="coco-container">

                    <!-- Header & Action Card -->
                    <div class="coco-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                            <div>
                                <h2 style="margin: 0; font-size: 1.3rem; color: var(--text-primary); font-weight: 700; display: flex; align-items: center; gap: 10px;">
                                    <span>📍</span> TPS Tracking Pergerakan Kontainer
                                </h2>
                                <p style="margin: 6px 0 0; color: var(--text-secondary); font-size: 0.88rem;">
                                    Rekam data tracking pergerakan fisik kontainer di TPS secara real-time ke sistem Bea Cukai via REST API CEISA 4.0.
                                </p>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <a href="report_tracking.php" class="btn-action-sm" style="text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35);">
                                    <span>📊</span> Laporan Tracking Terkirim
                                </a>
                                <span class="badge-pill badge-ceisa">POST /kirim-tps-tracking</span>
                                <span class="badge-pill" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                    <span class="pulse-dot"></span> CEISA 4.0 OpenAPI
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- FORMULIR PENGIRIMAN TRACKING -->
                    <div id="tab-form-content">
                        <div style="display: grid; grid-template-columns: 1.25fr 1fr; gap: 24px; align-items: start;">

                            <!-- KOLOM KIRI: FORMULIR INPUT -->
                            <div class="coco-card" style="margin-bottom: 0;">
                                <form id="tracking-form" onsubmit="event.preventDefault(); sendTracking();">
                                                    <!-- 1. Nomor Kontainer -->
                                    <div style="margin-bottom: 24px;">
                                        <div class="section-header-row">
                                            <span>📦</span> 1. Nomor Kontainer
                                        </div>

                                        <!-- Pemilihan Departemen Operasional -->
                                        <div class="dept-toggle-card">
                                            <div class="dept-toggle-label">
                                                <span>🏢 Departemen Operasional Lini 2</span>
                                                <span id="dept-database-badge" style="font-size:0.72rem; text-transform:none; padding:2px 8px; border-radius:6px; background:rgba(59,130,246,0.15); color:var(--accent-blue); border:1px solid rgba(59,130,246,0.3);">
                                                    DB: tpp_primamas (PLP FCL)
                                                </span>
                                            </div>
                                            <div class="dept-toggle-group">
                                                <button type="button" class="dept-btn active dept-tpp" id="btn-dept-tpp" onclick="setDepartment('tpp')">
                                                    <span>🏢</span> TPP (PLP / Lapangan)
                                                </button>
                                                <button type="button" class="dept-btn" id="btn-dept-gudang" onclick="setDepartment('gudang')">
                                                    <span>🏬</span> Gudang (LCL / Stripping)
                                                </button>
                                            </div>
                                            <input type="hidden" id="departemen-val" value="tpp">
                                        </div>

                                        <!-- Container Field Nomor Kontainer (Tampil Penuh) -->
                                        <div id="no-cont-wrapper" style="margin-bottom: 14px;">
                                            <div class="field-group">
                                                <div class="label-header">
                                                    <label for="no-cont">Nomor Kontainer <span class="req-star">*</span></label>
                                                    <span id="dept-hint-badge" style="font-size:0.75rem; color:var(--accent-blue); font-weight:600;">⚡ Auto-Fill tpp_primamas</span>
                                                </div>
                                                <select id="no-cont" style="width: 100%;" required>
                                                    <option value=""></option>
                                                </select>
                                            </div>

                                            <div class="field-group" id="ukuran-cont-group" style="display: none;">
                                                <input type="hidden" id="ukuran-cont" value="40">
                                                <input type="hidden" id="ukuran-cont-display" value="40 ft">
                                            </div>
                                        </div>

                                        <!-- Extra fields (Disembunyikan: Parameter baku otomatis terisi di latar belakang) -->
                                        <div id="section-extra-fields" style="display: none;">
                                            <div style="display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 14px;">
                                                <div class="field-group">
                                                    <label for="jenis-cont">Jenis Kontainer <span class="req-star">*</span></label>
                                                    <select id="jenis-cont" class="input-control" onchange="updateJsonPreview()" required>
                                                        <option value="8" selected>8 — FCL (Full Container)</option>
                                                        <option value="7">7 — LCL (Less Container)</option>
                                                        <option value="4">4 — EMPTY (Kontainer Kosong)</option>
                                                    </select>
                                                </div>

                                                <div class="field-group">
                                                    <label for="kode-tps">Kode TPS <span class="req-star">*</span></label>
                                                    <input type="text" id="kode-tps" class="input-control" value="PSU0" readonly style="background:var(--bg-card); cursor:not-allowed; opacity:0.9; font-family:'JetBrains Mono',monospace; text-transform:uppercase; font-weight:600;" title="Kode TPS Baku (PSU0) - Readonly">
                                                </div>

                                                <div class="field-group">
                                                    <label for="kode-gudang">Kode Gudang <span class="req-star">*</span></label>
                                                    <input type="text" id="kode-gudang" class="input-control" value="CPSU" readonly style="background:var(--bg-card); cursor:not-allowed; opacity:0.9; font-family:'JetBrains Mono',monospace; text-transform:uppercase; font-weight:600;" title="Kode Gudang Baku Kontainer (CPSU) - Readonly">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- PANEL TIMELINE RIWAYAT OPERASIONAL KONTAINER -->
                                    <div id="timeline-tracker-container" class="timeline-card-wrapper" style="display: none;">
                                        <div class="section-header-row" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid var(--border-medium); padding-bottom:8px;">
                                            <span style="display:flex; align-items:center; gap:8px;">
                                                <span style="font-size:1.1rem;">📋</span> 
                                                <span style="font-weight:700;">Riwayat Alur Operasional Kontainer:</span>
                                                <span id="timeline-cont-title" style="font-family:'JetBrains Mono',monospace; color:var(--accent-blue); font-size:0.92rem; font-weight:700;"></span>
                                            </span>
                                            <span id="timeline-status-counter" style="font-size:0.78rem; color:var(--text-secondary);"></span>
                                        </div>
                                        
                                        <!-- Ringkasan Profil Kontainer & Trailer -->
                                        <div id="timeline-summary-bar" class="timeline-summary-bar"></div>

                                        <!-- BAR AKSI PENGIRIMAN MASSAL (BATCH SEND) -->
                                        <div class="timeline-batch-bar">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span style="font-size:0.85rem; font-weight:700; color:var(--text-primary);">⚡ Pengiriman Cepat:</span>
                                                <span style="font-size:0.78rem; color:var(--text-secondary);">Kirim banyak alur sekaligus ke CEISA 4.0</span>
                                            </div>
                                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                <button type="button" id="btn-batch-unsent" class="btn-batch-action btn-batch-unsent" onclick="batchSendTimeline('unsent');" title="Kirim semua alur operasional yang belum pernah terkirim ke CEISA">
                                                    <span>🚀</span> Kirim Semua (Belum Terkirim) <span id="badge-unsent-count" class="badge-batch-pill">0</span>
                                                </button>
                                                <button type="button" id="btn-batch-all" class="btn-batch-action btn-batch-all" onclick="batchSendTimeline('all');" title="Kirim seluruh alur operasional (yang belum terkirim + sudah terkirim)">
                                                    <span>⚡</span> Kirim Semua (Semua Alur) <span id="badge-all-count" class="badge-batch-pill">0</span>
                                                </button>
                                            </div>
                                        </div>

                                        <div style="font-size:0.82rem; color:var(--text-secondary); margin-bottom:14px; display:flex; align-items:center; gap:8px; background:rgba(59,130,246,0.08); padding:8px 12px; border-radius:6px; border:1px solid rgba(59,130,246,0.2);">
                                            <span style="font-size:1.1rem;">💡</span> 
                                            <span><b>Petunjuk:</b> Alur operasional kontainer otomatis tersusun di bawah. Klik kartu alur untuk memilih, gunakan tombol <b>[🚀 Kirim CEISA]</b> untuk per alur, atau tombol di atas untuk mengirim massal.</span>
                                        </div>

                                        <!-- Container Kartu Alur -->
                                        <div id="timeline-cards-grid" class="timeline-cards-grid"></div>
                                    </div>

                                    <!-- MANUAL INPUT SECTIONS (DISEMBUNYIKAN AGAR USER TIDAK PERLU MENGISI/MEMILIH MANUAL) -->
                                    <div id="legacy-manual-inputs" style="display: none;">
                                        <!-- 2. Kegiatan & Waktu Pergerakan -->
                                        <div style="margin-bottom: 24px;">
                                            <div class="section-header-row">
                                                <span>⏱️</span> 2. Kegiatan & Waktu Pergerakan Kontainer
                                            </div>

                                            <div class="field-group" style="margin-bottom: 14px;">
                                                <label for="kode-kegiatan">Kode Kegiatan CEISA 4.0 <span class="req-star">*</span></label>
                                                <select id="kode-kegiatan" class="input-control" style="font-weight: 500;" onchange="onKegiatanChange(this.value); updateJsonPreview();" required>
                                                     <optgroup label="🏢 Kegiatan Utama TPP (PLP / Lapangan Penumpukan FCL)">
                                                         <option value="5" selected>5 — GATE IN PLP (Pemasukan Kontainer FCL)</option>
                                                         <option value="17">17 — STACKING DISCHARGE LINI 2 (Posisi Yard Wajib)</option>
                                                         <option value="21">21 — BEHANDLE LINI 2 (Pemeriksaan Fisik Behandle Yard)</option>
                                                         <option value="22">22 — SHIFTING LINI 2 (Pergeseran Posisi Yard)</option>
                                                         <option value="19">19 — TRUCK IN LINI 2 (Truk Penjemput Masuk)</option>
                                                         <option value="20">20 — PICKUP LINI 2 (Pengangkatan Kontainer ke Truk)</option>
                                                         <option value="6">6 — GATE OUT LINI 2 (Pengeluaran Kontainer FCL Lini 2)</option>
                                                     </optgroup>
                                                     <optgroup label="🚢 Alur Ekspor Lini 2">
                                                         <option value="7">7 — GATE IN EKSPOR LINI 2</option>
                                                         <option value="18">18 — STACKING EKSPOR LINI 2 (Posisi Yard Wajib)</option>
                                                         <option value="8">8 — GATE OUT EKSPOR LINI 2</option>
                                                     </optgroup>
                                                     <optgroup label="⚓ Kegiatan Lainnya (Gudang & Dermaga Lini 1)">
                                                         <option value="23">23 — STRIPPING STUFFING LINI 2</option>
                                                         <option value="24">24 — STUFFING KE GUDANG LINI 2 (Posisi Yard Wajib)</option>
                                                         <option value="1">1 — DISCHARGE</option>
                                                         <option value="2">2 — LOADING</option>
                                                         <option value="3">3 — GATE OUT (Codeco Impor Lini 1)</option>
                                                         <option value="4">4 — GATE IN RECEIVING (Codeco Ekspor)</option>
                                                         <option value="9">9 — GATE OUT BATAL EKSPOR</option>
                                                         <option value="10">10 — STACKING DISCHARGE Lini 1</option>
                                                         <option value="11">11 — STACKING EKSPOR Lini 1</option>
                                                         <option value="12">12 — TRUCK IN</option>
                                                         <option value="13">13 — PICKUP</option>
                                                         <option value="14">14 — BEHANDLE Lini 1</option>
                                                         <option value="15">15 — SHIFTING Lini 1</option>
                                                         <option value="16">16 — STRIPPING STUFFING Lini 1</option>
                                                     </optgroup>
                                                 </select>
                                            </div>

                                            <div class="field-group">
                                                <div class="label-header">
                                                    <label for="waktu-kegiatan">Waktu Kegiatan (dd-MM-yyyy HH:mm:ss) <span class="req-star">*</span></label>
                                                    <button type="button" class="field-action-btn" onclick="setNowTime();">⏱️ Gunakan Waktu Sekarang</button>
                                                </div>
                                                <input type="text" id="waktu-kegiatan" class="input-control" value="<?= e($nowDmyHis) ?>" style="font-family:'JetBrains Mono',monospace;" required oninput="updateJsonPreview()">
                                            </div>
                                        </div>

                                        <!-- 3. Posisi Yard & Armada Truk (Opsional) -->
                                        <div style="margin-bottom: 24px;">
                                            <div class="section-header-row">
                                                <span>🚜</span> 3. Posisi Yard & Identitas Armada Truk (Opsional)
                                            </div>

                                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                                                <div class="field-group">
                                                    <label for="block-loc">Block</label>
                                                    <input type="text" id="block-loc" class="input-control" placeholder="Contoh: A1" maxlength="10" oninput="updateJsonPreview()">
                                                </div>
                                                <div class="field-group">
                                                    <label for="slot-loc">Slot</label>
                                                    <input type="text" id="slot-loc" class="input-control" placeholder="Contoh: 01" maxlength="10" oninput="updateJsonPreview()">
                                                </div>
                                                <div class="field-group">
                                                    <label for="tier-loc">Tier</label>
                                                    <input type="text" id="tier-loc" class="input-control" placeholder="Contoh: 01" maxlength="10" oninput="updateJsonPreview()">
                                                </div>
                                            </div>

                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                                <div class="field-group">
                                                    <label for="nopol">Nomor Polisi Truk</label>
                                                    <input type="text" id="nopol" class="input-control" placeholder="Contoh: L 8415 UAD" maxlength="15" style="text-transform:uppercase;" oninput="updateJsonPreview()">
                                                </div>
                                                <div class="field-group">
                                                    <label for="stid">STID (Single Truck ID)</label>
                                                    <input type="text" id="stid" class="input-control" placeholder="Contoh: STID-0012345" maxlength="50" oninput="updateJsonPreview()">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 4. Dokumen Pabean & Pengangkutan (Opsional) -->
                                        <div>
                                            <div class="section-header-row">
                                                <span>📑</span> 4. Dokumen Pabean & Pengangkutan (Opsional)
                                            </div>

                                            <div style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                                                <div class="field-group">
                                                    <label for="kode-dok">Kode Dokumen</label>
                                                    <select id="kode-dok" class="input-control" onchange="updateJsonPreview()">
                                                        <option value="">-- Tanpa Dokumen / Sesuai Fisik --</option>
                                                        
                                                        <optgroup label="📋 Referensi Resmi Kode Dokumen Bea Cukai TPS Online (PDF)">
                                                            <option value="1">1 — SPPB BC 2.0 (Dok. SPPB PIB BC 2.0)</option>
                                                            <option value="2">2 — SPPB BC 2.3 (Dok. SPPB BC 2.3)</option>
                                                            <option value="3" selected>3 — PLP (Dok. PLP/OB A11)</option>
                                                            <option value="4">4 — SPPB BC 1.2 (Dok. Angkut Lanjut)</option>
                                                            <option value="5">5 — SPPF / BCF 2.6 (Pemeriksaan Lokasi Importir)</option>
                                                            <option value="6">6 — NPE (Dokumen Nota Pelayanan Ekspor)</option>
                                                            <option value="7">7 — PKBE</option>
                                                            <option value="8">8 — PPB (Dok. Periksa Fisik / Behandle)</option>
                                                            <option value="9">9 — BCF 1.5 (Barang Tidak Dikuasai / Timbun Lewat Waktu)</option>
                                                            <option value="10">10 — SPPB Empty Container (Pengeluaran Impor Kontainer Kosong)</option>
                                                            <option value="11">11 — SPPBE Batal Ekspor</option>
                                                            <option value="12">12 — SPPBE Pindah Muat Barang Ekspor</option>
                                                            <option value="13">13 — SPPB PIBK</option>
                                                            <option value="14">14 — Returnable Package (Ijin Impor Returnable Package)</option>
                                                            <option value="15">15 — Penimbunan diluar Kawasan Pabean</option>
                                                            <option value="20">20 — BC 1.1.A / SP3B (Pengeluaran Tujuan KPPT)</option>
                                                            <option value="21">21 — SPPB PIB Manual</option>
                                                            <option value="26">26 — Surat Ijin Pengeluaran Barang Untuk Lelang</option>
                                                            <option value="28">28 — Dokumen BC 1.2 - Re-Ekspor (BC 1.2 Belum Aju PIB)</option>
                                                            <option value="32">32 — Empty Container Ekspor</option>
                                                            <option value="35">35 — ATA CARNET Impor</option>
                                                            <option value="36">36 — CPD CARNET Impor</option>
                                                            <option value="40">40 — Pengeluaran Empty Container ex. Stripping</option>
                                                            <option value="41">41 — BC 1.6 (SPPB PLB - BC 1.6)</option>
                                                            <option value="44">44 — Pengeluaran Barang Impor BC 1.1 Outward Manifes</option>
                                                            <option value="54">54 — SP3K (Kontainer Kosong)</option>
                                                            <option value="60">60 — SPPB Rush Handling</option>
                                                            <option value="64">64 — KEK Pengeluaran ke KEK/TPB/FTZ</option>
                                                            <option value="99">99 — Dokumen Pengeluaran Lainnya....</option>
                                                        </optgroup>

                                                        <optgroup label="🏬 Khusus Gudang LCL (CFS Warehouse)">
                                                            <option value="704">704 — MASTER B/L (Master Bill of Lading)</option>
                                                            <option value="705">705 — HOUSE B/L (House Bill of Lading)</option>
                                                            <option value="640">640 — Delivery Order (D/O)</option>
                                                        </optgroup>
                                                    </select>
                                                </div>
                                                <div class="field-group">
                                                    <label for="no-dok">Nomor Dokumen</label>
                                                    <input type="text" id="no-dok" class="input-control" placeholder="Contoh: 016547" maxlength="50" oninput="updateJsonPreview()">
                                                </div>
                                                <div class="field-group">
                                                    <label for="tgl-dok">Tanggal Dokumen</label>
                                                    <input type="text" id="tgl-dok" class="input-control" placeholder="dd-MM-yyyy" maxlength="10" oninput="updateJsonPreview()">
                                                </div>
                                            </div>

                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                                <div class="field-group">
                                                    <label for="no-bl">Nomor B/L atau AWB</label>
                                                    <input type="text" id="no-bl" class="input-control" placeholder="Contoh: SSLSGSUBCAE6364" maxlength="50" oninput="updateJsonPreview()">
                                                </div>
                                                <div class="field-group">
                                                    <label for="tgl-bl">Tanggal B/L</label>
                                                    <input type="text" id="tgl-bl" class="input-control" placeholder="dd-MM-yyyy" maxlength="10" oninput="updateJsonPreview()">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </form>
                            </div>

                            <!-- KOLOM KANAN: LIVE JSON PREVIEW & AKSI KIRIM -->
                            <div style="position: sticky; top: 80px;">
                                <div class="coco-card" style="margin-bottom: 0;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-size: 1.15rem;">⚡</span>
                                            <strong style="color: var(--text-primary); font-size: 1rem;">Live JSON Payload</strong>
                                        </div>
                                        <button type="button" class="btn-action-sm" onclick="copyJsonPayload()">
                                            <span>📋</span> Salin JSON
                                        </button>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.8rem;">
                                        <span style="color: var(--text-secondary);">Target: <code>/kirim-tps-tracking</code></span>
                                        <span id="schema-status" style="color: #10b981; font-weight: 600;">✓ Parameter Wajib Terisi</span>
                                    </div>

                                    <textarea id="json-tracking-preview" class="json-box" readonly></textarea>

                                    <!-- Action Send Row -->
                                    <div style="margin-top: 20px;">
                                        <button type="button" id="btn-submit-tracking" class="btn-send-prod" onclick="sendTracking()">
                                            <span id="send-spinner" style="display: none;">⏳</span>
                                            <span id="send-icon">🚀</span>
                                            <span id="send-text">Kirim Tracking ke CEISA 4.0</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Card Respon Pengiriman -->
                                <div id="send-result-card" class="coco-card" style="display: none; margin-top: 20px; padding: 20px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span id="send-status-badge" class="badge-pill"></span>
                                            <span id="send-timestamp" style="font-size: 0.85rem; color: var(--text-secondary);"></span>
                                        </div>
                                        <button type="button" class="btn-action-sm" onclick="$('#send-raw-response').slideToggle(200)">
                                            <span>📋</span> Toggle Raw Response
                                        </button>
                                    </div>
                                    <div id="send-result-msg" style="font-size: 0.92rem; color: var(--text-primary); margin-bottom: 12px;"></div>
                                    <pre id="send-raw-response" style="display: none; background: #0d131f; color: #a5f3fc; padding: 16px; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 13px; max-height: 260px; overflow: auto; margin: 0;"></pre>
                                </div>
                            </div>

                </div>
            </main>
        </div>
    </div>

    <div id="toast-container" style="position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px;"></div>

    <script>
        let currentPayload = {};
        let searchTimeout = null;
        let historyTableInstance = null;
        let currentDept = 'tpp';
        window.selectedContainerData = null;

        function renderKegiatanOptions(dept) {
            const selectEl = document.getElementById('kode-kegiatan');
            if (!selectEl) return;
            const currentVal = selectEl.value;

            if (dept === 'gudang') {
                selectEl.innerHTML = `
                    <optgroup label="🏬 Kegiatan Utama Gudang (LCL / CFS Warehouse)">
                        <option value="5" ${currentVal==='5'?'selected':''}>5 — GATE IN PLP (Pemasukan Kontainer LCL)</option>
                        <option value="23" ${currentVal==='23' || !currentVal || currentVal==='17'?'selected':''}>23 — STRIPPING STUFFING LINI 2 (Pembongkaran Kargo LCL)</option>
                        <option value="21" ${currentVal==='21'?'selected':''}>21 — BEHANDLE LINI 2 (Pemeriksaan Fisik Kargo / Gudang)</option>
                        <option value="6" ${currentVal==='6'?'selected':''}>6 — GATE OUT LINI 2 (Kontainer Kosong / Empty Return)</option>
                        <option value="20" ${currentVal==='20'?'selected':''}>20 — PICKUP LINI 2 (Pengeluaran Kargo LCL per B/L)</option>
                        <option value="24" ${currentVal==='24'?'selected':''}>24 — STUFFING KE GUDANG LINI 2 (Pemuatan Kargo Ekspor)</option>
                    </optgroup>
                    <optgroup label="🚢 Alur Ekspor Lini 2">
                        <option value="7" ${currentVal==='7'?'selected':''}>7 — GATE IN EKSPOR LINI 2</option>
                        <option value="18" ${currentVal==='18'?'selected':''}>18 — STACKING EKSPOR LINI 2 (Posisi Yard Wajib)</option>
                        <option value="8" ${currentVal==='8'?'selected':''}>8 — GATE OUT EKSPOR LINI 2</option>
                    </optgroup>
                    <optgroup label="⚓ Kegiatan Lainnya (Lini 1 / Lapangan TPP)">
                        <option value="17">17 — STACKING DISCHARGE LINI 2 (Posisi Yard Wajib)</option>
                        <option value="19">19 — TRUCK IN LINI 2</option>
                        <option value="20">20 — PICKUP LINI 2</option>
                        <option value="22">22 — SHIFTING LINI 2 (Posisi Yard Wajib)</option>
                        <option value="1">1 — DISCHARGE</option>
                        <option value="2">2 — LOADING</option>
                        <option value="3">3 — GATE OUT (Codeco Impor Lini 1)</option>
                        <option value="4">4 — GATE IN RECEIVING (Codeco Ekspor)</option>
                        <option value="9">9 — GATE OUT BATAL EKSPOR</option>
                        <option value="10">10 — STACKING DISCHARGE Lini 1</option>
                        <option value="11">11 — STACKING EKSPOR Lini 1</option>
                        <option value="12">12 — TRUCK IN</option>
                        <option value="13">13 — PICKUP</option>
                        <option value="14">14 — BEHANDLE Lini 1</option>
                        <option value="15">15 — SHIFTING Lini 1</option>
                        <option value="16">16 — STRIPPING STUFFING Lini 1</option>
                    </optgroup>
                `;
            } else {
                selectEl.innerHTML = `
                    <optgroup label="🏢 Kegiatan Utama TPP (PLP / Lapangan Penumpukan FCL)">
                        <option value="5" ${currentVal==='5' || !currentVal || currentVal==='23'?'selected':''}>5 — GATE IN PLP (Pemasukan Kontainer FCL)</option>
                        <option value="17" ${currentVal==='17'?'selected':''}>17 — STACKING DISCHARGE LINI 2 (Posisi Yard Wajib)</option>
                        <option value="21" ${currentVal==='21'?'selected':''}>21 — BEHANDLE LINI 2 (Pemeriksaan Fisik Behandle Yard)</option>
                        <option value="22" ${currentVal==='22'?'selected':''}>22 — SHIFTING LINI 2 (Pergeseran Posisi Yard)</option>
                        <option value="20" ${currentVal==='20'?'selected':''}>20 — PICKUP LINI 2 (Pengangkatan Kontainer ke Truk)</option>
                        <option value="6" ${currentVal==='6'?'selected':''}>6 — GATE OUT LINI 2 (Pengeluaran Kontainer FCL Lini 2)</option>
                    </optgroup>
                    <optgroup label="🚢 Alur Ekspor Lini 2">
                        <option value="7" ${currentVal==='7'?'selected':''}>7 — GATE IN EKSPOR LINI 2</option>
                        <option value="18" ${currentVal==='18'?'selected':''}>18 — STACKING EKSPOR LINI 2 (Posisi Yard Wajib)</option>
                        <option value="8" ${currentVal==='8'?'selected':''}>8 — GATE OUT EKSPOR LINI 2</option>
                    </optgroup>
                    <optgroup label="⚓ Kegiatan Lainnya (Gudang & Dermaga Lini 1)">
                        <option value="23">23 — STRIPPING STUFFING LINI 2</option>
                        <option value="24">24 — STUFFING KE GUDANG LINI 2 (Posisi Yard Wajib)</option>
                        <option value="19">19 — TRUCK IN LINI 2</option>
                        <option value="1">1 — DISCHARGE</option>
                        <option value="2">2 — LOADING</option>
                        <option value="3">3 — GATE OUT (Codeco Impor Lini 1)</option>
                        <option value="4">4 — GATE IN RECEIVING (Codeco Ekspor)</option>
                        <option value="9">9 — GATE OUT BATAL EKSPOR</option>
                        <option value="10">10 — STACKING DISCHARGE Lini 1</option>
                        <option value="11">11 — STACKING EKSPOR Lini 1</option>
                        <option value="12">12 — TRUCK IN</option>
                        <option value="13">13 — PICKUP</option>
                        <option value="14">14 — BEHANDLE Lini 1</option>
                        <option value="15">15 — SHIFTING Lini 1</option>
                        <option value="16">16 — STRIPPING STUFFING Lini 1</option>
                    </optgroup>
                `;
            }
        }

        function setDepartment(dept, isInit = false) {
            currentDept = dept;
            $('#departemen-val').val(dept);

            renderKegiatanOptions(dept);

            if (dept === 'gudang') {
                $('body').addClass('dept-is-gudang');
                $('#btn-dept-gudang').addClass('active dept-gudang');
                $('#btn-dept-tpp').removeClass('active dept-tpp');
                $('#dept-hint-badge').html('⚡ Auto-Fill primamas (Gudang LCL)').css('color', '#10b981');
                $('#dept-database-badge').html('DB: primamas (Gudang LCL)').css({
                    'background': 'rgba(16, 185, 129, 0.15)',
                    'color': '#10b981',
                    'border-color': 'rgba(16, 185, 129, 0.3)'
                });
                $('#kode-gudang').val('GPSU');
                $('#jenis-cont').val('7'); // 7 = LCL
                $('#kode-dok').val('704'); // 704 = MASTER B/L (Standar LCL)
                
                // Extra fields tetap disembunyikan agar hanya menyisakan Nomor Kontainer di form

                if (!isInit) showToast('Departemen Gudang (LCL / database primamas) aktif — Dokumen default: 704 (MASTER B/L)', 'info');
            } else {
                $('body').removeClass('dept-is-gudang');
                $('#btn-dept-tpp').addClass('active dept-tpp');
                $('#btn-dept-gudang').removeClass('active dept-gudang');
                $('#dept-hint-badge').html('⚡ Auto-Fill tpp_primamas (TPP PLP)').css('color', 'var(--accent-blue)');
                $('#dept-database-badge').html('DB: tpp_primamas (PLP FCL)').css({
                    'background': 'rgba(59, 130, 246, 0.15)',
                    'color': 'var(--accent-blue)',
                    'border-color': 'rgba(59, 130, 246, 0.3)'
                });
                $('#kode-gudang').val('CPSU');
                $('#jenis-cont').val('8'); // 8 = FCL
                $('#kode-dok').val('3'); // 3 = Persetujuan PLP (Standar PLP FCL)

                // Hide extra inputs for TPP so user only fills/selects Nomor Kontainer
                $('#section-extra-fields').hide();

                if (!isInit) showToast('Departemen TPP (PLP / database tpp_primamas) aktif — Dokumen default: 3 (Persetujuan PLP)', 'info');
            }

            // Reset kontainer yang terpilih agar operator mencari di database yang aktif
            window.selectedContainerData = null;
            activeTimelineData = null;
            selectedTimelineStep = null;
            $('#timeline-tracker-container').slideUp(180);
            $('#no-cont').val(null).trigger('change');
            updateJsonPreview();
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.style.padding = '12px 20px';
            toast.style.borderRadius = '8px';
            toast.style.fontSize = '0.9rem';
            toast.style.fontWeight = '500';
            toast.style.boxShadow = '0 4px 14px rgba(0,0,0,0.3)';
            toast.style.transition = 'all 0.3s ease';
            toast.style.display = 'flex';
            toast.style.alignItems = 'center';
            toast.style.gap = '10px';

            if (type === 'success') {
                toast.style.background = '#065f46';
                toast.style.color = '#a7f3d0';
                toast.style.border = '1px solid #10b981';
                toast.innerHTML = '<span>✅</span> ' + message;
            } else if (type === 'error') {
                toast.style.background = '#7f1d1d';
                toast.style.color = '#fecaca';
                toast.style.border = '1px solid #ef4444';
                toast.innerHTML = '<span>❌</span> ' + message;
            } else {
                toast.style.background = '#1e293b';
                toast.style.color = '#e2e8f0';
                toast.style.border = '1px solid #475569';
                toast.innerHTML = '<span>ℹ️</span> ' + message;
            }

            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        async function refreshAccessToken() {
            try {
                showToast('Memperbarui token dari SSO Bea Cukai...', 'info');
                const res = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ refresh: true })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Token berhasil diperbarui!', 'success');
                } else {
                    showToast('Gagal refresh token: ' + (data.message || 'Error'), 'error');
                }
            } catch (e) {
                showToast('Koneksi auth error: ' + e.message, 'error');
            }
        }

        function setSize(size, btn) {
            $('.type-btn').removeClass('active');
            $(btn).addClass('active');
            $('#ukuran-cont').val(size);
            updateJsonPreview();
        }

        function setNowTime() {
            const now = new Date();
            const d = String(now.getDate()).padStart(2, '0');
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const y = now.getFullYear();
            const H = String(now.getHours()).padStart(2, '0');
            const i = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            $('#waktu-kegiatan').val(`${d}-${m}-${y} ${H}:${i}:${s}`);
            updateJsonPreview();
            showToast('Waktu kegiatan diset ke waktu sekarang', 'info');
        }

        function applySmartTimestamp(item) {
            item = item || window.selectedContainerData;
            if (!item) return;

            const kdKeg = $('#kode-kegiatan').val();
            // 5 = Gate In PLP (Masuk Gudang LCL)
            if (kdKeg === '5') {
                if (item.waktu_masuk) $('#waktu-kegiatan').val(item.waktu_masuk);
                if (item.departemen === 'GUDANG') {
                    $('#jenis-cont').val('7'); // Masuk Gudang: kontainer membawa kargo LCL (Jenis 7)
                }
            } 
            // 16 / 23 = Stripping Stuffing
            else if (kdKeg === '16' || kdKeg === '23') {
                if (item.waktu_stripping) $('#waktu-kegiatan').val(item.waktu_stripping);
                if (item.departemen === 'GUDANG') {
                    $('#jenis-cont').val('7'); // Stripping: pembongkaran kargo LCL (Jenis 7)
                }
            } 
            // 21 = Behandle / 20 = Pickup LCL
            else if (kdKeg === '21' || kdKeg === '20') {
                if (item.departemen === 'GUDANG') {
                    $('#jenis-cont').val('7'); // Kargo LCL (Jenis 7)
                }
            }
            // 6 = Gate Out Lini 2 (Keluar Kosong ex Stripping)
            else if (kdKeg === '6') {
                if (item.waktu_keluar) $('#waktu-kegiatan').val(item.waktu_keluar);
                if (item.departemen === 'GUDANG') {
                    $('#jenis-cont').val('4'); // Gate Out Gudang: kontainer keluar dalam keadaan kosong (Jenis 4 EMPTY)
                }
            } 
            // Fallback ke waktu_masuk jika ada
            else if (item.waktu_masuk && !$('#waktu-kegiatan').val()) {
                $('#waktu-kegiatan').val(item.waktu_masuk);
                if (item.departemen === 'GUDANG') {
                    $('#jenis-cont').val(kdKeg === '6' ? '4' : '7');
                }
            }
        }

        function onKegiatanChange(val) {
            // Posisi Yard (Block/Slot/Tier) hanya relevan dan dikirimkan untuk kegiatan Penumpukan/Yard (Stacking/Shifting)
            const isStackingActivity = ['10', '11', '14', '15', '17', '18', '21', '22', '24'].includes(val.toString());
            if (!isStackingActivity) {
                $('#block-loc').val('');
                $('#slot-loc').val('');
                $('#tier-loc').val('');
            }

            // Logika Jenis Kontainer untuk Gudang:
            // Kontainer datang membawa kargo LCL (Jenis 7 LCL). Setelah selesai stripping dan keluar depo, kontainer kosong menjadi Empty (Jenis 4 EMPTY).
            if (currentDept === 'gudang') {
                if (val === '6') {
                    $('#jenis-cont').val('4'); // Gate Out Gudang: kontainer kosong keluar (Jenis 4 EMPTY)
                } else {
                    $('#jenis-cont').val('7'); // Gate In, Stripping, Behandle, Pickup: kontainer bermuatan kargo LCL (Jenis 7 LCL)
                }
            }

            // Set otomatis dokumen berdasarkan alur kegiatan & departemen
            if (val === '5') {
                // Gate In PLP -> TPP: 3 (Persetujuan PLP), Gudang: 704 (Master B/L) atau 11 (Manifes)
                if (currentDept === 'gudang') {
                    $('#kode-dok').val('704');
                } else {
                    $('#kode-dok').val('3');
                }
            } else if (val === '17' || val === '22') {
                // 17 = Stacking Discharge Lini 2, 22 = Shifting Lini 2 -> TPP: 3 (Persetujuan PLP)
                if (currentDept === 'tpp') {
                    $('#kode-dok').val('3');
                }
            } else if (val === '21') {
                // 21 = Behandle Lini 2 -> TPP: 3 (PLP) atau 8 (PPB)
                if (currentDept === 'tpp') {
                    if (!$('#kode-dok').val()) $('#kode-dok').val('3');
                } else {
                    $('#kode-dok').val('8');
                }
            } else if (val === '23') {
                // 23 = Stripping Stuffing Lini 2 -> Khusus Gudang LCL (704) / TPP (3)
                if (currentDept === 'gudang') {
                    if (!$('#kode-dok').val() || $('#kode-dok').val() === '3') {
                        $('#kode-dok').val('704');
                    }
                } else if (currentDept === 'tpp') {
                    $('#kode-dok').val('3');
                }
            } else if (val === '19' || val === '20') {
                // 19 = Truck In Lini 2, 20 = Pickup Lini 2 -> Default Dokumen SPPB BC 2.0 (1) atau BC 2.3 (2)
                if (!$('#kode-dok').val() || $('#kode-dok').val() === '3' || $('#kode-dok').val() === '704') {
                    $('#kode-dok').val('1'); // SPPB PIB (BC 2.0)
                }
            } else if (val === '6') {
                // Gate Out Lini 2
                if (currentDept === 'gudang') {
                    $('#jenis-cont').val('4'); // Empty container
                } else {
                    if (!$('#kode-dok').val() || $('#kode-dok').val() === '3' || $('#kode-dok').val() === '704') {
                        $('#kode-dok').val('1'); // SPPB PIB (BC 2.0)
                    }
                }
            } else if (val === '7' || val === '8' || val === '18' || val === '24') {
                // Ekspor Lini 2 -> Default PEB (30)
                if (!$('#kode-dok').val()) {
                    $('#kode-dok').val('30');
                }
            }

            // Terapkan timestamp otomatis bila data kontainer sudah dimuat
            if (window.selectedContainerData) {
                applySmartTimestamp(window.selectedContainerData);
            }
        }

        function initSelect2Container() {
            $('#no-cont').select2({
                placeholder: '-- Ketik atau Pilih Nomor Kontainer --',
                allowClear: true,
                tags: true,
                ajax: {
                    url: 'api/tps_tracking.php?action=search_container',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            dept: currentDept
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results || []
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0,
                templateResult: formatContainerOption,
                templateSelection: formatContainerSelection
            });

            // Event saat kontainer dipilih dari dropdown
            $('#no-cont').on('select2:select', function (e) {
                const item = e.params.data;
                autoFillContainerData(item);
            });

            // Event saat pilihan dihapus
            $('#no-cont').on('select2:clear select2:unselect', function () {
                window.selectedContainerData = null;
                activeTimelineData = null;
                selectedTimelineStep = null;
                $('#timeline-tracker-container').slideUp(180);
                setTimeout(updateJsonPreview, 50);
            });

            // Event saat nilai berubah
            $('#no-cont').on('change', function () {
                const val = ($('#no-cont').val() || '').replace(/[\s\-]/g, '').toUpperCase();
                if (val && val.length >= 4) {
                    fetchContainerTimeline(val);
                } else {
                    $('#timeline-tracker-container').slideUp(180);
                }
                updateJsonPreview();
            });
        }

        function formatContainerOption(item) {
            if (item.loading) return item.text;
            if (!item.container_no) return item.text;

            const isEmp = item.status === 'EMPTY';
            const isLcl = item.status === 'LCL';
            const badgeStatus = isEmp 
                ? `<span class="badge-pill badge-out" style="font-size:0.72rem; padding:2px 6px;">EMPTY</span>` 
                : (isLcl 
                    ? `<span class="badge-pill" style="background:rgba(245, 158, 11, 0.15); color:#f59e0b; border:1px solid rgba(245, 158, 11, 0.3); font-size:0.72rem; padding:2px 6px;">LCL</span>`
                    : `<span class="badge-pill badge-in" style="font-size:0.72rem; padding:2px 6px;">FCL</span>`);

            const badgeDept = item.departemen === 'GUDANG'
                ? `<span class="badge-pill" style="background:rgba(16, 185, 129, 0.15); color:#10b981; border:1px solid rgba(16, 185, 129, 0.3); font-size:0.7rem; padding:2px 6px;">🏬 GUDANG</span>`
                : `<span class="badge-pill" style="background:rgba(59, 130, 246, 0.15); color:#60a5fa; border:1px solid rgba(59, 130, 246, 0.3); font-size:0.7rem; padding:2px 6px;">🏢 TPP</span>`;

            const badgeSent = item.already_sent 
                ? `<span class="badge-pill" style="background:rgba(245, 158, 11, 0.15); color:#f59e0b; border:1px solid rgba(245, 158, 11, 0.35); font-size:0.7rem; padding:2px 6px;">Pernah Terkirim</span>` 
                : '';

            let html = `
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="font-family:'JetBrains Mono',monospace; color:var(--text-primary); font-size:0.92rem;">${item.container_no}</strong>
                        ${badgeDept}
                        ${badgeStatus}
                        <span class="badge-pill badge-ceisa" style="font-size:0.72rem; padding:2px 6px;">${item.size_type || '40'} ft</span>
                        ${badgeSent}
                    </div>
                    ${item.yard_block ? `<span style="font-size:0.78rem; color:var(--text-secondary);">📍 ${item.yard_block}${item.slot ? ' S:'+item.slot : ''}${item.tier ? ' T:'+item.tier : ''}</span>` : ''}
                </div>
            `;

            const subInfo = [];
            if (item.no_bl) subInfo.push(`B/L: <span style="color:var(--accent-blue);">${item.no_bl}</span>`);
            if (item.nopol) subInfo.push(`Nopol: <b>${item.nopol}</b>`);
            if (item.no_dokumen) subInfo.push(`Dok: ${item.no_dokumen}`);
            if (item.waktu_stripping) subInfo.push(`Stripping: ${item.waktu_stripping.split(' ')[0]}`);

            if (subInfo.length > 0) {
                html += `<div style="font-size:0.75rem; color:var(--text-secondary); margin-top:2px;">${subInfo.join(' &bull; ')}</div>`;
            }

            return $(html);
        }

        function formatContainerSelection(item) {
            const val = item.container_no || item.id || item.text || '';
            return val ? val.replace(/[\s\-]/g, '').toUpperCase() : '-- Ketik atau Pilih Nomor Kontainer --';
        }

        function autoFillContainerData(item) {
            if (!item) return;

            window.selectedContainerData = item;
            selectedTimelineStep = null; // Reset pilihan alur sebelumnya agar alur kontainer baru terpilih sesuai ketersediaan

            // Pastikan nomor kontainer bersih tanpa spasi atau dash
            const contClean = (item.container_no || item.id || '').replace(/[\s\-]/g, '').toUpperCase();
            if ($('#no-cont').val() !== contClean) {
                if ($('#no-cont').find("option[value='" + contClean + "']").length) {
                    $('#no-cont').val(contClean);
                } else {
                    const newOption = new Option(contClean, contClean, true, true);
                    $('#no-cont').append(newOption);
                }
            }

            // 1. Ukuran Kontainer (Readonly)
            const sz = item.size || (item.size_type && item.size_type.toString().includes('20') ? '20' : (item.size_type && item.size_type.toString().includes('45') ? '45' : '40'));
            $('#ukuran-cont').val(sz);
            $('#ukuran-cont-display').val(sz + ' ft');

            // 2. Jenis Kontainer (4 = EMPTY, 7 = LCL, 8 = FCL sesuai OpenAPI CEISA 4.0)
            if (item.status === 'EMPTY') {
                $('#jenis-cont').val('4');
            } else if (item.status === 'LCL') {
                $('#jenis-cont').val('7');
            } else {
                $('#jenis-cont').val('8'); // FCL
            }

            // 3. Posisi Yard Lapangan
            $('#block-loc').val(item.yard_block || '');
            $('#slot-loc').val(item.slot || '');
            $('#tier-loc').val(item.tier || '');

            // 4. Armada Truk Nopol
            $('#nopol').val(item.nopol || '');

            // 5. Dokumen B/L
            $('#no-bl').val(item.no_bl || '');
            $('#tgl-bl').val(item.tgl_bl || '');

            // 6. Dokumen Pabean & Pengangkutan Berdasarkan Departemen
            if (item.departemen === 'GUDANG') {
                // Untuk Gudang (LCL): dokumen kedatangan kontainer adalah Master B/L (704) atau Manifes (11)
                if (item.no_bl) {
                    $('#no-dok').val(item.no_bl);
                    $('#tgl-dok').val(item.tgl_bl || '');
                    $('#kode-dok').val('704'); // 704 = MASTER B/L
                } else if (item.no_dokumen) {
                    $('#no-dok').val(item.no_dokumen);
                    $('#tgl-dok').val(item.tgl_dokumen || '');
                    $('#kode-dok').val('11'); // 11 = MANIFES (BC 1.1)
                }
            } else {
                // Untuk TPP (PLP): dokumen kedatangan kontainer adalah Persetujuan PLP (3)
                if (item.no_dokumen || item.no_plp) {
                    $('#no-dok').val(item.no_dokumen || item.no_plp);
                    $('#tgl-dok').val(item.tgl_dokumen || item.tgl_plp || '');
                    $('#kode-dok').val('3'); // 3 = Persetujuan PLP
                }
            }

            // 7. Waktu Kegiatan Cerdas
            applySmartTimestamp(item);

            // 8. Muat Timeline Alur Riwayat Kontainer Otomatis
            fetchContainerTimeline(contClean);

            // Update live preview & beri notifikasi toast
            updateJsonPreview();
            
            const deptLabel = item.departemen || (currentDept === 'gudang' ? 'Gudang' : 'TPP');
            if (item.already_sent) {
                showToast(`ℹ️ Kontainer [${deptLabel}] ${contClean} sudah pernah dikirim sebelumnya (${item.last_tracked_status})`, 'info');
            } else {
                showToast(`Data kontainer [${deptLabel}] ${contClean} berhasil dimuat otomatis!`, 'success');
            }
        }

        // ===== RIWAYAT ALUR TIMELINE CONTROLLER =====
        let activeTimelineData = null;
        let selectedTimelineStep = null;

        async function fetchContainerTimeline(contNo) {
            if (!contNo || contNo.length < 4) {
                $('#timeline-tracker-container').slideUp(180);
                return;
            }

            const containerBox = $('#timeline-tracker-container');
            const cardsGrid = $('#timeline-cards-grid');
            const summaryBar = $('#timeline-summary-bar');
            const statusCounter = $('#timeline-status-counter');
            const titleElem = $('#timeline-cont-title');

            containerBox.slideDown(250);
            titleElem.html(`[${contNo}]`);
            summaryBar.html(`<div style="display:flex; align-items:center; gap:8px; padding:4px 0; color:var(--accent-blue);"><span class="pulse-dot"></span> Sedang menelusuri riwayat alur operasional kontainer <b>${contNo}</b> di database ${currentDept.toUpperCase()}...</div>`);
            cardsGrid.html(`
                <div style="grid-column: 1 / -1; padding: 20px; text-align: center; color: var(--text-secondary);">
                    <div style="font-size: 1.4rem; margin-bottom: 6px;">⏳</div>
                    <div>Memuat riwayat alur operasional kontainer...</div>
                </div>
            `);

            try {
                const res = await fetch(`api/tps_tracking.php?action=get_container_timeline&no_cont=${encodeURIComponent(contNo)}&dept=${currentDept}`);
                const data = await res.json();

                if (!data.success) {
                    summaryBar.html(`<div style="color:#f59e0b;">⚠️ ${data.message || 'Data kontainer tidak ditemukan di database'}</div>`);
                    cardsGrid.html(`
                        <div style="grid-column: 1 / -1; padding: 16px; text-align: center; color: var(--text-secondary); background: rgba(245, 158, 11, 0.08); border-radius: 8px; border: 1px dashed rgba(245, 158, 11, 0.3);">
                            <div style="margin-bottom: 4px;">ℹ️ Riwayat operasional tidak ditemukan di database <b>${currentDept.toUpperCase()}</b>.</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);">Anda tetap dapat menginput data pergerakan kontainer secara manual pada form di bawah.</div>
                        </div>
                    `);
                    statusCounter.html('');
                    activeTimelineData = null;
                    return;
                }

                activeTimelineData = data;
                renderTimelineUI(data);

            } catch (err) {
                console.error("Timeline error:", err);
                summaryBar.html(`<div style="color:#ef4444;">❌ Gagal memuat timeline: ${err.message}</div>`);
            }
        }

        function renderTimelineUI(data) {
            const containerBox = $('#timeline-tracker-container');
            const cardsGrid = $('#timeline-cards-grid');
            const summaryBar = $('#timeline-summary-bar');
            const statusCounter = $('#timeline-status-counter');
            const c = data.container || {};
            const timeline = data.timeline || [];

            // 1. Summary Bar
            let summaryHtml = `
                <div class="timeline-summary-item">📦 <span>Kontainer:</span> <strong>${c.nomorKontainer}</strong> (${c.ukuranKontainer} ft ${c.statusKontainer || ''})</div>
                <div class="timeline-summary-item">🚚 <span>In Trailer:</span> <strong>${c.inTrailer || '-'}</strong></div>
                <div class="timeline-summary-item">🚛 <span>Out Trailer:</span> <strong>${c.outTrailer || '-'}</strong></div>
                ${c.lokasiYard && c.lokasiYard !== '-' ? `<div class="timeline-summary-item">📍 <span>Yard:</span> <strong>${c.lokasiYard}</strong></div>` : ''}
                ${c.suratPlp && c.suratPlp !== '-' ? `<div class="timeline-summary-item">📑 <span>PLP:</span> <strong>${c.suratPlp}</strong></div>` : ''}
                ${c.noBl && c.noBl !== '-' ? `<div class="timeline-summary-item">📄 <span>B/L:</span> <strong>${c.noBl}</strong></div>` : ''}
            `;
            summaryBar.html(summaryHtml);

            // Counter sent vs ready
            const completedSteps = timeline.filter(t => t.available && t.payload);
            const sentCount = completedSteps.filter(t => t.is_sent).length;
            const availableCount = completedSteps.length;
            const unsentCount = availableCount - sentCount;
            statusCounter.html(`Tersedia: <b>${availableCount}</b> alur selesai | Terkirim ke CEISA: <b style="color:#10b981;">${sentCount}</b>`);

            // Update badge counts & button disabled state
            $('#badge-unsent-count').text(unsentCount);
            $('#badge-all-count').text(availableCount);

            if (unsentCount === 0) {
                $('#btn-batch-unsent').prop('disabled', true).addClass('btn-batch-disabled').attr('title', 'Semua alur sudah terkirim ke CEISA 4.0');
            } else {
                $('#btn-batch-unsent').prop('disabled', false).removeClass('btn-batch-disabled').attr('title', `Kirim ${unsentCount} alur yang belum pernah terkirim`);
            }

            if (availableCount === 0) {
                $('#btn-batch-all').prop('disabled', true).addClass('btn-batch-disabled');
            } else {
                $('#btn-batch-all').prop('disabled', false).removeClass('btn-batch-disabled');
            }

            // 2. Render Cards - HANYA RENDER ALUR YANG SUDAH SELESAI
            let cardsHtml = '';
            if (completedSteps.length === 0) {
                cardsHtml = `
                    <div style="grid-column: 1 / -1; padding: 28px 20px; text-align: center; background: rgba(255,255,255,0.03); border: 1px dashed rgba(255,255,255,0.15); border-radius: 12px;">
                        <div style="font-size: 2.2rem; margin-bottom: 8px;">⏳</div>
                        <div style="font-weight: 600; font-size: 1.05rem; color: var(--text-primary); margin-bottom: 4px;">Belum Ada Alur Operasional yang Selesai</div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary); max-width: 500px; margin: 0 auto;">
                            Kontainer ini belum menyelesaikan tahapan kegiatan operasional di depo (Gate In, Stacking Yard, dsb.), sehingga alur belum dapat dikirimkan ke CEISA 4.0.
                        </div>
                    </div>
                `;
            } else {
                timeline.forEach((step, idx) => {
                    const isAvail = !!(step.available && step.payload);
                    // JIKA ALUR BELUM SELESAI, MAKA TIDAK DIMUNCULKAN (HILANGKAN DARI DAFTAR)
                    if (!isAvail) return;

                    const isSent = step.is_sent;
                    let badgeClass = 'step-badge-ready';
                    let badgeText = '⚡ Siap Kirim';
                    if (isSent) {
                        badgeClass = 'step-badge-sent';
                        badgeText = `✅ Terkirim (${step.sent_info?.sent_at ? step.sent_info.sent_at.split(' ')[0] : 'CEISA'})`;
                    }

                    const isSelected = (selectedTimelineStep === idx);
                    let cardClass = 'step-card';
                    if (isSent) cardClass += ' is-sent-card';
                    if (isSelected) cardClass += ' active-selected';

                    cardsHtml += `
                        <div class="${cardClass}" id="timeline-card-${idx}" onclick="applyTimelineStep(${idx});" style="cursor: pointer;">
                            <div>
                                <div class="step-header">
                                    <div class="step-title-box">
                                        <span class="step-badge-num">${step.step}</span>
                                        <span style="font-size:1.15rem;">${step.icon}</span>
                                        <span style="letter-spacing: -0.2px;">${step.kegiatanLabel}</span>
                                    </div>
                                    <span class="${badgeClass}">${badgeText}</span>
                                </div>
                                
                                <div class="step-body-info" style="margin-top: 8px;">
                                    <div style="display:flex; justify-content:space-between;">
                                        <span>⏱️ <b>Waktu:</b></span>
                                        <span class="info-val" style="font-family:'JetBrains Mono',monospace; font-weight:700;">${step.waktuKegiatan || '-'}</span>
                                    </div>
                                    ${step.nomorPolisi ? `
                                    <div style="display:flex; justify-content:space-between;">
                                        <span>🚚 <b>Armada:</b></span>
                                        <span class="info-val" style="font-weight:700; color:var(--accent-blue, #0284c7);">${step.nopolLabel}</span>
                                    </div>
                                    ` : ''}
                                    ${step.dokumenLabel && step.dokumenLabel !== '-' ? `
                                    <div style="display:flex; justify-content:space-between; gap:6px;">
                                        <span>📑 <b>Dokumen:</b></span>
                                        <span class="info-val" style="text-align:right; font-weight:600; word-break:break-all;">${step.dokumenLabel}</span>
                                    </div>
                                    ` : ''}
                                    ${step.lokasiYard && step.lokasiYard !== '-' ? `
                                    <div style="display:flex; justify-content:space-between;">
                                        <span>📍 <b>Yard:</b></span>
                                        <span class="info-val" style="font-weight:600;">${step.lokasiYard}</span>
                                    </div>
                                    ` : ''}
                                    <div style="margin-top:3px; font-size:0.73rem; color:var(--text-secondary); border-top:1px dashed var(--border-subtle); padding-top:4px;">
                                        ${step.deskripsi || ''}
                                    </div>
                                </div>
                            </div>

                            <div class="step-actions" onclick="event.stopPropagation();">
                                <button type="button" class="btn-apply-step ${isSelected ? 'active' : ''}" onclick="applyTimelineStep(${idx});" title="Pilih alur ini untuk ditinjau dan dikirim ke CEISA">
                                    <span>${isSelected ? '✓' : '🎯'}</span> ${isSelected ? 'Alur Terpilih' : 'Pilih Alur Ini'}
                                </button>
                                <button type="button" class="btn-quick-send" onclick="quickSendTimelineStep(${idx});" title="Kirim data alur ini langsung ke CEISA 4.0">
                                    <span>🚀</span> Kirim CEISA
                                </button>
                            </div>
                        </div>
                    `;
                });
            }

            cardsGrid.html(cardsHtml);

            // Auto-select alur pertama yang siap kirim (hanya alur yang available dan memiliki payload)
            if (completedSteps.length > 0) {
                let targetStep = -1;
                if (selectedTimelineStep !== null && timeline[selectedTimelineStep] && timeline[selectedTimelineStep].available && timeline[selectedTimelineStep].payload) {
                    targetStep = selectedTimelineStep;
                } else {
                    targetStep = timeline.findIndex(t => t.available && t.payload && !t.is_sent);
                    if (targetStep === -1) {
                        targetStep = timeline.findIndex(t => t.available && t.payload);
                    }
                }

                if (targetStep !== -1) {
                    applyTimelineStep(targetStep, true); // true = silent initial selection
                }
            } else {
                selectedTimelineStep = null;
                $('.step-card').removeClass('active-selected');
                $('#json-tracking-preview').val('{\n    // Belum ada alur operasional yang selesai pada kontainer ini untuk dikirim ke CEISA\n}');
                $('#schema-status').html('<span style="color:#f59e0b;">⚠️ Alur Belum Selesai</span>');
                const btnSend = document.getElementById('btn-submit-tracking');
                if (btnSend) {
                    btnSend.disabled = true;
                    btnSend.style.opacity = '0.45';
                    btnSend.style.cursor = 'not-allowed';
                    btnSend.title = 'Alur operasional belum selesai di lapangan';
                }
            }
        }

        function notifyUnavailableStep(index) {
            if (!activeTimelineData || !activeTimelineData.timeline || !activeTimelineData.timeline[index]) return;
            const step = activeTimelineData.timeline[index];
            Swal.fire({
                title: 'Alur Belum Selesai',
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                        <p style="margin-bottom:8px;">
                            Alur <b>#${step.step} (${step.kegiatanLabel})</b> belum selesai dilaksanakan di lapangan sehingga <b>tidak dapat dipilih atau dikirim ke CEISA 4.0</b>.
                        </p>
                        <div style="background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3); border-radius:8px; padding:10px; color:#d97706; font-size:12.5px;">
                            ℹ️ <i>${step.deskripsi || 'Tahapan operasional ini belum tercatat selesai di sistem depo.'}</i>
                        </div>
                    </div>
                `,
                icon: 'warning',
                confirmButtonColor: '#f59e0b',
                confirmButtonText: 'Mengerti'
            });
        }

        function applyTimelineStep(index, silent = false) {
            if (!activeTimelineData || !activeTimelineData.timeline || !activeTimelineData.timeline[index]) return false;

            const step = activeTimelineData.timeline[index];
            if (!step.available || !step.payload) {
                if (!silent) {
                    notifyUnavailableStep(index);
                }
                return false;
            }

            const p = step.payload;
            selectedTimelineStep = index;

            // Update active state di UI kartu & tombol
            $('.step-card').removeClass('active-selected');
            $(`#timeline-card-${index}`).addClass('active-selected');

            // Perbarui teks tombol di kartu yang aktif dan lainnya
            $('.btn-apply-step').removeClass('active').html('<span>🎯</span> Pilih Alur Ini');
            $(`#timeline-card-${index} .btn-apply-step`).addClass('active').html('<span>✓</span> Alur Terpilih');

            // 1. Kode Kegiatan
            $('#kode-kegiatan').val(p.kodeKegiatan.toString());
            onKegiatanChange(p.kodeKegiatan.toString());

            // 1b. Jenis Kontainer dari Payload Alur Terpilih (Gate In/Stripping/Behandle/Pickup = 7 LCL, Gate Out = 4 EMPTY)
            if (p.jenisKontainer) {
                $('#jenis-cont').val(p.jenisKontainer.toString());
            } else if (currentDept === 'gudang') {
                $('#jenis-cont').val(p.kodeKegiatan == 6 ? '4' : '7');
            }

            // 2. Waktu Kegiatan
            if (p.waktuKegiatan) {
                $('#waktu-kegiatan').val(p.waktuKegiatan);
            }

            // 3. Armada Nopol
            $('#nopol').val(p.nomorPolisi || '');

            // 4. Posisi Yard - Hanya diisi untuk kegiatan Stacking / Yard (kegiatan selain Stacking tidak mengirimkan posisi yard)
            const isStacking = [10, 11, 14, 15, 17, 18, 21, 22, 24].includes(parseInt(p.kodeKegiatan, 10));
            if (isStacking && p.block) {
                $('#block-loc').val(p.block);
                $('#slot-loc').val(p.slot || '');
                $('#tier-loc').val(p.tier || '');
            } else {
                $('#block-loc').val('');
                $('#slot-loc').val('');
                $('#tier-loc').val('');
            }

            // 5. Dokumen
            if (p.kodeDokumen) $('#kode-dok').val(p.kodeDokumen);
            if (p.nomorDokumen) $('#no-dok').val(p.nomorDokumen);
            if (p.tanggalDokumen) $('#tgl-dok').val(p.tanggalDokumen);

            // 6. B/L
            if (p.nomorBlAwb) $('#no-bl').val(p.nomorBlAwb);
            if (p.tanggalBlAwb) $('#tgl-bl').val(p.tanggalBlAwb);

            // Update JSON Preview
            updateJsonPreview();

            // Notifikasi Toast
            if (!silent) {
                showToast(`✓ Alur #${step.step} (${step.kegiatanLabel}) dipilih & siap dikirim ke CEISA!`, 'info');
            }
            return true;
        }

        async function quickSendTimelineStep(index) {
            if (!activeTimelineData || !activeTimelineData.timeline || !activeTimelineData.timeline[index]) return;
            const step = activeTimelineData.timeline[index];
            if (!step.available || !step.payload) {
                notifyUnavailableStep(index);
                return;
            }
            const ok = applyTimelineStep(index);
            if (ok === false) return;
            // Langsung panggil sendTracking() untuk memunculkan modal konfirmasi
            setTimeout(() => {
                sendTracking();
            }, 80);
        }

        async function batchSendTimeline(mode = 'unsent') {
            if (!activeTimelineData || !activeTimelineData.timeline) {
                Swal.fire({
                    title: 'Data Belum Dimuat',
                    text: 'Silakan pilih atau ketik nomor kontainer terlebih dahulu.',
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const timeline = activeTimelineData.timeline;
            const contNo = (activeTimelineData.container?.nomorKontainer || $('#no-cont').val() || '').trim().toUpperCase();

            // Filter alur yang selesai dan memiliki payload
            const completedSteps = [];
            timeline.forEach((step, idx) => {
                if (step.available && step.payload) {
                    completedSteps.push({ index: idx, step: step });
                }
            });

            if (completedSteps.length === 0) {
                Swal.fire({
                    title: 'Tidak Ada Alur Selesai',
                    text: 'Belum ada alur operasional yang selesai pada kontainer ini untuk dikirim.',
                    icon: 'info',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            let targets = [];
            let modeTitle = '';
            let modeDesc = '';

            if (mode === 'unsent') {
                targets = completedSteps.filter(item => !item.step.is_sent);
                if (targets.length === 0) {
                    Swal.fire({
                        title: 'Semua Alur Sudah Terkirim',
                        html: `Seluruh <b>${completedSteps.length} alur operasional</b> untuk kontainer <b>${contNo}</b> sudah pernah terkirim ke <b>CEISA 4.0</b>.<br><br>Gunakan tombol <b>Kirim Semua (Semua Alur)</b> jika Anda ingin mengirim ulang seluruh alur.`,
                        icon: 'info',
                        confirmButtonColor: '#3b82f6',
                        confirmButtonText: 'Mengerti'
                    });
                    return;
                }
                modeTitle = `Kirim ${targets.length} Alur (Belum Terkirim)`;
                modeDesc = `Kirim <b>${targets.length} alur operasional</b> yang <b>belum terkirim</b> untuk kontainer <b>${contNo}</b> ke CEISA 4.0?`;
            } else {
                targets = completedSteps;
                modeTitle = `Kirim Semua (${targets.length} Alur Operasional)`;
                modeDesc = `Kirim seluruh <b>${targets.length} alur operasional</b> (baik yang belum maupun yang sudah terkirim) untuk kontainer <b>${contNo}</b> ke CEISA 4.0?`;
            }

            // Tampilkan dialog konfirmasi daftar alur yang akan dikirim
            const stepsListHtml = targets.map((t, i) => `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:7px 10px; margin-bottom:4px; background:rgba(0,0,0,0.03); border-radius:6px; font-size:12.5px;">
                    <div>
                        <b>#${t.step.step}</b> ${t.step.icon || '📦'} <span style="font-weight:600;">${t.step.kegiatanLabel}</span>
                    </div>
                    <div>
                        ${t.step.is_sent ? '<span style="color:#059669; font-weight:700; font-size:11.5px; background:#dcfce7; padding:2px 8px; border-radius:4px;">Terkirim</span>' : '<span style="color:#2563eb; font-weight:700; font-size:11.5px; background:#eff6ff; padding:2px 8px; border-radius:4px;">Siap Kirim</span>'}
                    </div>
                </div>
            `).join('');

            const confirmRes = await Swal.fire({
                title: modeTitle,
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.5;">
                        <p style="margin-bottom:10px;">${modeDesc}</p>
                        <div style="max-height:200px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px; padding:6px; margin-bottom:12px; background:var(--bg-card,#ffffff);">
                            ${stepsListHtml}
                        </div>
                        <p style="font-size:12px; color:var(--text-secondary); margin:0;">
                            ℹ️ Data akan dikirimkan satu per satu secara berurutan ke gateway Bea Cukai CEISA 4.0.
                        </p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `🚀 Ya, Kirim ${targets.length} Alur`,
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            });

            if (!confirmRes.isConfirmed) return;

            // SweetAlert Progress Modal
            Swal.fire({
                title: 'Mengirim Alur ke CEISA 4.0...',
                html: `
                    <div style="text-align:left; font-size:13px;">
                        <div id="batch-prog-title" style="margin-bottom:8px; font-weight:600; color:var(--accent-blue,#3b82f6);">
                            Menyiapkan pengiriman alur...
                        </div>
                        <div style="background:#e2e8f0; border-radius:6px; height:10px; overflow:hidden; margin-bottom:12px;">
                            <div id="batch-prog-bar" style="background:linear-gradient(90deg, #3b82f6, #10b981); height:100%; width:0%; transition:width 0.3s ease;"></div>
                        </div>
                        <div id="batch-prog-logs" style="max-height:190px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px; padding:8px; background:var(--bg-card,#ffffff); font-size:12px; line-height:1.6;">
                        </div>
                    </div>
                `,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });

            const results = [];
            let successCount = 0;
            let conflictCount = 0;
            let failCount = 0;

            for (let i = 0; i < targets.length; i++) {
                const target = targets[i];
                const step = target.step;
                const percent = Math.round(((i) / targets.length) * 100);

                $('#batch-prog-title').html(`Mengirim alur <b>${i + 1}</b> dari <b>${targets.length}</b>: #${step.step} (${step.kegiatanLabel})...`);
                $('#batch-prog-bar').css('width', `${percent}%`);

                const logItem = $(`
                    <div id="batch-log-${i}" style="display:flex; justify-content:space-between; align-items:center; padding:4px 6px; border-bottom:1px dashed var(--border-subtle,#e2e8f0);">
                        <span><b>#${step.step}</b> ${step.kegiatanLabel}</span>
                        <span class="log-status" style="color:#64748b;">⏳ Mengirim...</span>
                    </div>
                `);
                $('#batch-prog-logs').append(logItem);
                $('#batch-prog-logs').scrollTop($('#batch-prog-logs')[0].scrollHeight);

                try {
                    // Set form sesuai alur ini & build payload
                    applyTimelineStep(target.index, true);
                    const payload = buildPayload();

                    const res = await fetch('api/tps_tracking.php?action=send', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ payload: payload })
                    });

                    const result = await res.json();
                    const ceisaRaw = result.raw || result;
                    const isConflict = (result.code === 409 || ceisaRaw.code === 409 || ceisaRaw.result === 'Data already exists.' || (ceisaRaw.detail && ceisaRaw.detail.includes('sudah pernah')));

                    if (result.success) {
                        successCount++;
                        $(`#batch-log-${i} .log-status`).html('<b style="color:#10b981;">✅ Sukses (' + (result.data?.id ? '#' + result.data.id : 'OK') + ')</b>');
                        results.push({ step: step, status: 'success', text: 'Berhasil dikirim' });
                    } else if (isConflict) {
                        conflictCount++;
                        $(`#batch-log-${i} .log-status`).html('<b style="color:#f59e0b;">⚠️ 409 Sudah Pernah</b>');
                        results.push({ step: step, status: 'conflict', text: 'Sudah pernah dikirim (409)' });
                    } else {
                        failCount++;
                        $(`#batch-log-${i} .log-status`).html('<b style="color:#ef4444;">❌ Gagal</b>');
                        results.push({ step: step, status: 'fail', text: result.message || 'Ditolak gateway' });
                    }
                } catch (err) {
                    failCount++;
                    $(`#batch-log-${i} .log-status`).html('<b style="color:#ef4444;">❌ Error</b>');
                    results.push({ step: step, status: 'error', text: err.message });
                }

                // Jeda 350ms antar pengiriman agar tidak spam gateway
                await new Promise(r => setTimeout(r, 350));
            }

            $('#batch-prog-bar').css('width', '100%');
            $('#batch-prog-title').html('<b>Pengiriman masal selesai!</b> Menyegarkan data...');

            // Segarkan status timeline dan riwayat
            if (contNo) {
                await fetchContainerTimeline(contNo);
            }
            if (typeof loadHistoryTable === 'function') {
                loadHistoryTable();
            }

            // Tampilkan laporan akhir
            let iconReport = 'success';
            if (failCount > 0 && successCount === 0) iconReport = 'error';
            else if (failCount > 0 || conflictCount > 0) iconReport = 'info';

            Swal.fire({
                title: 'Hasil Pengiriman Masal',
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                        <p style="margin-bottom:10px;">
                            Proses pengiriman alur kontainer <b>${contNo}</b> telah selesai diproses.
                        </p>
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; text-align:center; margin-bottom:12px;">
                            <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:8px;">
                                <div style="font-size:20px; font-weight:800; color:#059669;">${successCount}</div>
                                <div style="font-size:11.5px; color:#047857; font-weight:700;">Sukses Baru</div>
                            </div>
                            <div style="background:#fef3c7; border:1px solid #fde68a; border-radius:8px; padding:8px;">
                                <div style="font-size:20px; font-weight:800; color:#d97706;">${conflictCount}</div>
                                <div style="font-size:11.5px; color:#b45309; font-weight:700;">Sudah Pernah (409)</div>
                            </div>
                            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:8px;">
                                <div style="font-size:20px; font-weight:800; color:#dc2626;">${failCount}</div>
                                <div style="font-size:11.5px; color:#b91c1c; font-weight:700;">Gagal</div>
                            </div>
                        </div>
                    </div>
                `,
                icon: iconReport,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Selesai'
            });
            showToast(`Pengiriman masal selesai: ${successCount} sukses, ${conflictCount} sudah ada, ${failCount} gagal`, 'info');
        }

        function buildPayload() {
            const rawCont = ($('#no-cont').val() || '').trim().toUpperCase();
            const contVal = rawCont.replace(/[\s\-]/g, '');
            const payload = {
                departemen: currentDept.toUpperCase(),
                nomorKontainer: contVal,
                ukuranKontainer: $('#ukuran-cont').val(),
                jenisKontainer: $('#jenis-cont').val(),
                kodeTps: $('#kode-tps').val().trim().toUpperCase(),
                kodeGudang: $('#kode-gudang').val().trim().toUpperCase(),
                kodeKegiatan: parseInt($('#kode-kegiatan').val(), 10),
                waktuKegiatan: $('#waktu-kegiatan').val().trim()
            };

            const block = $('#block-loc').val().trim();
            if (block) payload.block = block;

            const slot = $('#slot-loc').val().trim();
            if (slot) payload.slot = slot;

            const tier = $('#tier-loc').val().trim();
            if (tier) payload.tier = tier;

            const nopol = $('#nopol').val().trim().toUpperCase();
            if (nopol) payload.nomorPolisi = nopol;

            const stid = $('#stid').val().trim();
            if (stid) payload.stid = stid;

            const kdDok = $('#kode-dok').val();
            if (kdDok) payload.kodeDokumen = kdDok;

            const noDok = $('#no-dok').val().trim();
            if (noDok) payload.nomorDokumen = noDok;

            const tglDok = $('#tgl-dok').val().trim();
            if (tglDok) payload.tanggalDokumen = tglDok;

            const noBl = $('#no-bl').val().trim();
            if (noBl) payload.nomorBlAwb = noBl;

            const tglBl = $('#tgl-bl').val().trim();
            if (tglBl) payload.tanggalBlAwb = tglBl;

            return payload;
        }

        function updateJsonPreview() {
            currentPayload = buildPayload();
            const btnSend = document.getElementById('btn-submit-tracking');
            const contVal = currentPayload.nomorKontainer;

            // Jika belum ada nomor kontainer, kosongkan JSON preview dan nonaktifkan tombol kirim
            if (!contVal || contVal.length < 4) {
                $('#json-tracking-preview').val('{\n    // Silakan ketik atau pilih nomor kontainer terlebih dahulu\n}');
                $('#schema-status').html('<span style="color:var(--text-secondary);">⚠️ Nomor Kontainer Belum Diisi</span>');
                if (btnSend) {
                    btnSend.disabled = true;
                    btnSend.style.opacity = '0.45';
                    btnSend.style.cursor = 'not-allowed';
                    btnSend.style.filter = 'grayscale(0.7)';
                    btnSend.title = 'Silakan pilih atau ketik nomor kontainer terlebih dahulu';
                }
                return;
            }

            $('#json-tracking-preview').val(JSON.stringify(currentPayload, null, 4));

            const isValid = !!(currentPayload.nomorKontainer && currentPayload.ukuranKontainer && currentPayload.jenisKontainer && currentPayload.kodeTps && currentPayload.kodeGudang && currentPayload.kodeKegiatan && currentPayload.waktuKegiatan);
            if (isValid) {
                $('#schema-status').html('<span style="color:#10b981;">✓ Parameter Wajib Terisi</span>');
                if (btnSend) {
                    btnSend.disabled = false;
                    btnSend.style.opacity = '1';
                    btnSend.style.cursor = 'pointer';
                    btnSend.style.filter = 'none';
                    btnSend.title = 'Kirim data tracking ke gateway CEISA 4.0';
                }
            } else {
                $('#schema-status').html('<span style="color:#f59e0b;">⚠️ Field Bertanda * Wajib Diisi</span>');
                if (btnSend) {
                    btnSend.disabled = true;
                    btnSend.style.opacity = '0.45';
                    btnSend.style.cursor = 'not-allowed';
                    btnSend.style.filter = 'grayscale(0.7)';
                    btnSend.title = 'Lengkapi field bertanda * terlebih dahulu';
                }
            }
        }

        function copyJsonPayload() {
            const text = $('#json-tracking-preview').val();
            navigator.clipboard.writeText(text).then(() => {
                showToast('JSON payload berhasil disalin ke clipboard!', 'success');
            }).catch(err => {
                showToast('Gagal menyalin: ' + err, 'error');
            });
        }

        async function sendTracking() {
            const payload = buildPayload();

            if (!payload.nomorKontainer) {
                Swal.fire({
                    title: 'Nomor Kontainer Kosong',
                    text: 'Silakan isi nomor kontainer terlebih dahulu!',
                    icon: 'warning',
                    confirmButtonColor: '#10b981'
                });
                $('#no-cont').focus();
                return;
            }

            if (!payload.waktuKegiatan) {
                Swal.fire({
                    title: 'Waktu Kegiatan Kosong',
                    text: 'Silakan isi waktu kegiatan!',
                    icon: 'warning',
                    confirmButtonColor: '#10b981'
                });
                return;
            }

            if (activeTimelineData?.timeline && selectedTimelineStep !== null) {
                const activeStep = activeTimelineData.timeline[selectedTimelineStep];
                if (!activeStep || !activeStep.available || !activeStep.payload) {
                    Swal.fire({
                        title: 'Alur Belum Selesai',
                        text: 'Alur operasional yang dipilih belum selesai dilaksanakan di lapangan sehingga tidak dapat dikirim ke CEISA 4.0.',
                        icon: 'warning',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }
            }

            const activeStepObj = (activeTimelineData?.timeline && selectedTimelineStep !== null) ? activeTimelineData.timeline[selectedTimelineStep] : null;
            const kegiatanLabel = activeStepObj ? activeStepObj.kegiatanLabel : $('#kode-kegiatan option:selected').text();
            const dokumenDisplay = activeStepObj ? activeStepObj.dokumenLabel : (payload.nomorDokumen ? ((payload.kodeDokumen ? `[Kode ${payload.kodeDokumen}] ` : '') + payload.nomorDokumen) : '-');

            // 1. Konfirmasi SweetAlert2
            const confirmRes = await Swal.fire({
                title: 'Konfirmasi Pengiriman Tracking',
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                        <p style="margin-bottom:8px;">
                            Kirim data tracking pergerakan kontainer ke <b>CEISA 4.0</b>?
                        </p>
                        <div style="background:rgba(0,0,0,0.2); border:1px solid var(--border-medium); border-radius:8px; padding:12px; margin-bottom:10px;">
                            <div>📦 <b>Kontainer:</b> <code style="color:#38bdf8; font-weight:700;">${payload.nomorKontainer}</code> (${payload.ukuranKontainer} ft)</div>
                            <div>⚡ <b>Kegiatan:</b> ${kegiatanLabel}</div>
                            <div>⏱️ <b>Waktu:</b> ${payload.waktuKegiatan}</div>
                            <div>🏢 <b>TPS / Gudang:</b> ${payload.kodeTps} / ${payload.kodeGudang}</div>
                            ${payload.nomorPolisi ? `<div>🚚 <b>Armada/Nopol:</b> ${payload.nomorPolisi}</div>` : ''}
                            ${dokumenDisplay && dokumenDisplay !== '-' ? `<div>📑 <b>Dokumen:</b> ${dokumenDisplay}</div>` : ''}
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '🚀 Ya, Kirim Tracking',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            });

            if (!confirmRes.isConfirmed) return;

            const btnSend = document.getElementById('btn-submit-tracking');
            const spinner = document.getElementById('send-spinner');
            const icon = document.getElementById('send-icon');

            btnSend.disabled = true;
            spinner.style.display = 'inline-block';
            icon.style.display = 'none';

            // SweetAlert Loading
            Swal.fire({
                title: 'Merekam ke CEISA 4.0...',
                html: `Sedang mengirim tracking kontainer <b>${payload.nomorKontainer}</b> ke gateway Bea Cukai...`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const res = await fetch('api/tps_tracking.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ payload: payload })
                });

                const result = await res.json();
                const ceisaRaw = result.raw || result;
                const isConflict = (result.code === 409 || ceisaRaw.code === 409 || ceisaRaw.result === 'Data already exists.' || (ceisaRaw.detail && ceisaRaw.detail.includes('sudah pernah')));

                // Tampilkan Response Card di UI
                $('#send-result-card').show();
                const badge = $('#send-status-badge');
                badge.removeClass('badge-in badge-out').removeAttr('style');

                if (result.success) {
                    badge.addClass('badge-in').text(`HTTP ${result.code || 201} CREATED`);
                    $('#send-result-msg').html(`✅ <b>Berhasil Terkirim:</b> ${result.message || 'Data tracking pergerakan kontainer berhasil direkam di CEISA 4.0.'}`);
                } else if (isConflict) {
                    badge.css({
                        background: 'rgba(245, 158, 11, 0.18)',
                        color: '#f59e0b',
                        border: '1px solid #f59e0b'
                    }).text(`HTTP 409 CONFLICT — DATA ALREADY EXISTS`);
                    $('#send-result-msg').html(`⚠️ <b>Data already exists:</b> ${ceisaRaw.detail || 'Data sudah pernah dikirim sebelumnya.'} ${ceisaRaw.data?.alasan ? '<br><small style="color:var(--text-secondary);">' + ceisaRaw.data.alasan + '</small>' : ''}`);
                } else {
                    badge.addClass('badge-out').text(`HTTP ${result.code || 400} FAILED`);
                    $('#send-result-msg').html(`❌ <b>Gagal:</b> ${result.message || 'Pengiriman ditolak oleh gateway CEISA 4.0'}`);
                }

                $('#send-timestamp').text('Respon: ' + new Date().toLocaleTimeString('id-ID'));
                
                // Tampilkan format resmi CEISA persis seperti yang diminta
                const displayJson = (isConflict || ceisaRaw.result) ? ceisaRaw : result;
                $('#send-raw-response').text(JSON.stringify(displayJson, null, 4)).show();
                document.getElementById('send-result-card').scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                if (result.success) {
                    const trackingId = result.data?.id || '-';
                    
                    // Segera segarkan riwayat timeline kontainer jika ada
                    if (payload.nomorKontainer) {
                        fetchContainerTimeline(payload.nomorKontainer);
                    }
                    if (typeof loadHistoryTable === 'function') {
                        loadHistoryTable();
                    }

                    Swal.fire({
                        title: '🎉 Tracking Berhasil Direkam!',
                        html: `
                            <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                                Data tracking pergerakan kontainer <b>${payload.nomorKontainer}</b> telah berhasil diterima oleh <b>CEISA 4.0</b>.<br><br>
                                <div style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); border-radius:6px; padding:10px;">
                                    <span>🆔 <b>ID Tracking CEISA:</b> <code style="color:#10b981; font-weight:700;">#${trackingId}</code></span><br>
                                    <span>📦 <b>Kontainer:</b> ${payload.nomorKontainer}</span><br>
                                    <span>⏱️ <b>Waktu Rekam:</b> ${result.data?.waktuRekam || new Date().toLocaleString('id-ID')}</span>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: '📜 Buka Laporan Tracking',
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#64748b'
                    }).then((r) => {
                        if (r.isConfirmed) {
                            window.location.href = 'report_tracking.php';
                        }
                    });
                    showToast('Tracking kontainer sukses dikirim!', 'success');
                } else if (isConflict) {
                    const dupData = ceisaRaw.data || {};
                    Swal.fire({
                        title: '⚠️ Data Sudah Pernah Dikirim!',
                        html: `
                            <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                                <div style="background:rgba(245, 158, 11, 0.12); border:1px solid rgba(245, 158, 11, 0.35); border-radius:8px; padding:12px; margin-bottom:12px;">
                                    <div style="font-weight:700; color:#d97706; font-size:14px; margin-bottom:4px;">
                                        HTTP 409 — ${ceisaRaw.result || 'Data already exists.'}
                                    </div>
                                    <div style="color:var(--text-primary); margin-bottom:6px; font-weight:500;">
                                        ${ceisaRaw.detail || 'Data sudah pernah dikirim sebelumnya.'}
                                    </div>
                                    ${dupData.alasan ? `<div style="font-size:12.5px; color:#d97706; margin-bottom:6px;">ℹ️ ${dupData.alasan}</div>` : ''}
                                    <div style="font-size:12px; color:var(--text-secondary);">
                                        <b>Kontainer:</b> <code>${dupData.nomorKontainer || payload.nomorKontainer}</code> &bull; 
                                        <b>Kegiatan:</b> ${dupData.kodeKegiatan || payload.kodeKegiatan} &bull; 
                                        <b>Waktu:</b> ${dupData.waktuKegiatan || payload.waktuKegiatan}
                                    </div>
                                </div>
                                <div style="font-size:12.5px; color:var(--text-secondary); margin-bottom:10px;">
                                    💡 <b>Solusi:</b> Jika pergerakan ini ingin dicatat dengan waktu baru, klik tombol <b>Gunakan Waktu Sekarang & Kirim Ulang</b> di bawah.
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <span style="font-weight:600; font-size:12px; color:var(--text-secondary);">RAW RESPONSE CEISA 4.0 (HTTP 409):</span>
                                    <button type="button" class="field-action-btn" onclick="navigator.clipboard.writeText($('#modal-dup-json').text()); showToast('JSON disalin ke clipboard!','success');">📋 Salin JSON</button>
                                </div>
                                <pre id="modal-dup-json" style="background:#0d131f; color:#38bdf8; padding:12px; border-radius:8px; font-family:'JetBrains Mono',monospace; font-size:11.5px; line-height:1.5; max-height:180px; overflow:auto; margin:0; border:1px solid #1e293b;">${JSON.stringify(ceisaRaw, null, 4)}</pre>
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '⏱️ Gunakan Waktu Sekarang & Kirim Ulang',
                        cancelButtonText: 'Tutup & Edit Manual',
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#64748b'
                    }).then(r => {
                        if (r.isConfirmed) {
                            setNowTime();
                            showToast('Waktu kegiatan diset ke sekarang. Mengirim ulang tracking...', 'info');
                            setTimeout(() => submitTracking(), 400);
                        }
                    });
                    showToast('Kontainer sudah pernah dikirim sebelumnya (HTTP 409)', 'info');
                } else {
                    Swal.fire({
                        title: 'Pengiriman Tracking Ditolak',
                        html: `
                            <div style="text-align:left; font-size:13.5px;">
                                <p style="color:#ef4444; font-weight:600;">${result.message || 'Gateway mengembalikan error'}</p>
                                <pre style="background:#0d131f; color:#fca5a5; padding:10px; border-radius:6px; font-size:12px; max-height:150px; overflow:auto;">${JSON.stringify(ceisaRaw, null, 2)}</pre>
                            </div>
                        `,
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                    showToast('Pengiriman tracking gagal: ' + (result.message || ''), 'error');
                }

            } catch (err) {
                console.error(err);
                Swal.fire({
                    title: 'Kesalahan Sistem',
                    text: err.message,
                    icon: 'error'
                });
                showToast('Terjadi kesalahan jaringan: ' + err.message, 'error');
            } finally {
                btnSend.disabled = false;
                spinner.style.display = 'none';
                icon.style.display = 'inline-block';
            }
        }



        // Inisialisasi awal
        $(document).ready(function() {
            setDepartment('<?= $activeDept ?>', true);
            initSelect2Container();
            <?php if (!empty($preloadCont)): ?>
            const initialCont = '<?= e($preloadCont) ?>';
            const newOpt = new Option(initialCont, initialCont, true, true);
            $('#no-cont').append(newOpt).trigger('change');
            fetchContainerTimeline(initialCont);
            <?php endif; ?>
            updateJsonPreview();

            // Theme management
            const themeBtn = document.getElementById('theme-toggle');
            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    const cur = document.documentElement.getAttribute('data-theme') || 'dark';
                    const next = cur === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('ceisa_theme', next);
                    const icon = document.querySelector('.theme-toggle-icon');
                    const txt = document.querySelector('.theme-toggle-text');
                    if (icon) icon.textContent = next === 'dark' ? '🌙' : '☀️';
                    if (txt) txt.textContent = next === 'dark' ? 'Dark' : 'Light';
                });
            }

            // Mobile menu toggle
            const menuToggle = document.getElementById('menu-toggle');
            if (menuToggle) {
                menuToggle.addEventListener('click', () => {
                    document.querySelector('.sidebar').classList.toggle('open');
                    document.querySelector('.sidebar-overlay').classList.toggle('active');
                });
            }
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                overlay.addEventListener('click', () => {
                    document.querySelector('.sidebar').classList.remove('open');
                    overlay.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>
