<?php
/**
 * TPS Tracking Batch (Kirim Banyak Kontainer Sekaligus) CEISA 4.0 — TPS Online Dashboard
 * Halaman perekaman data tracking pergerakan banyak kontainer di TPS
 * Masing-masing kontainer otomatis membawa seluruh alur proses operasionalnya (Gate In, Stacking, Stripping/Behandle, Truck In/Pickup, Gate Out)
 * Target Endpoint: POST /tps-tracking/batch & POST /kirim-tps-tracking
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

requireAuth();

$config = require __DIR__ . '/config.php';
$username = $_SESSION['name'] ?? $_SESSION['username'] ?? $config['username'] ?? 'User';
$loginTime = $_SESSION['login_time'] ?? time();
$userInitial = strtoupper(substr($username, 0, 2));

$nowDmyHis = date('d-m-Y H:i:s');
$activeDept = strtolower($_GET['dept'] ?? 'tpp');
if (!in_array($activeDept, ['tpp', 'gudang'])) {
    $activeDept = 'tpp';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPS Tracking Batch — <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
        .content-area::-webkit-scrollbar { width: 8px; }
        .content-area::-webkit-scrollbar-track { background: var(--bg-base); }
        .content-area::-webkit-scrollbar-thumb { background: var(--border-medium); border-radius: 4px; }
        .content-area::-webkit-scrollbar-thumb:hover { background: var(--accent-blue); }
        
        .batch-container {
            padding: 24px 24px 80px 24px;
            max-width: 1540px;
            margin: 0 auto;
        }
        .batch-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--border-subtle);
            margin-bottom: 24px;
        }

        /* Departemen Operasional Toggle Card */
        .dept-toggle-card {
            background: var(--bg-card);
            border: 1px solid var(--border-medium);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
        }
        .dept-toggle-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .dept-toggle-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .dept-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 11px 16px;
            border-radius: 8px;
            border: 1.5px solid var(--border-medium);
            background: var(--bg-surface);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .dept-btn:hover {
            border-color: var(--accent-blue);
            color: var(--text-primary);
        }
        .dept-btn.active.dept-tpp {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            color: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
        }
        .dept-btn.active.dept-gudang {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #ffffff;
            border-color: #10b981;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }

        /* Multi-Container Picker Bar */
        .picker-box {
            background: var(--bg-surface);
            border: 1.5px solid var(--border-medium);
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 22px;
        }
        .picker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }
        .picker-title {
            font-size: 0.96rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .picker-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Select2 Styling */
        .select2-container--default .select2-selection--multiple {
            background-color: var(--bg-input) !important;
            border: 1.5px solid var(--border-medium) !important;
            border-radius: 8px !important;
            min-height: 46px !important;
            padding: 4px 8px !important;
            transition: all 0.2s ease !important;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--accent-blue) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
            outline: none !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: rgba(59, 130, 246, 0.18) !important;
            border: 1px solid rgba(59, 130, 246, 0.4) !important;
            color: var(--text-primary) !important;
            font-family: 'JetBrains Mono', monospace !important;
            font-size: 0.86rem !important;
            font-weight: 700 !important;
            padding: 3px 8px 3px 22px !important;
            border-radius: 6px !important;
            position: relative !important;
            margin-top: 4px !important;
        }
        [data-theme="light"] .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #eff6ff !important;
            border-color: #93c5fd !important;
            color: #1d4ed8 !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #ef4444 !important;
            font-size: 1.1rem !important;
            line-height: 1 !important;
            margin-right: 4px !important;
            border: none !important;
            background: transparent !important;
            position: absolute !important;
            left: 4px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #b91c1c !important;
            background: transparent !important;
        }
        .select2-container--default .select2-search--inline .select2-search__field {
            color: var(--text-primary) !important;
            font-family: 'JetBrains Mono', monospace !important;
            font-size: 0.9rem !important;
            margin-top: 6px !important;
            line-height: 24px !important;
        }
        .select2-dropdown {
            background-color: var(--bg-surface) !important;
            border: 1px solid var(--border-medium) !important;
            border-radius: 8px !important;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45) !important;
            z-index: 9999 !important;
        }
        .select2-search--dropdown .select2-search__field {
            background-color: var(--bg-input) !important;
            border: 1px solid var(--border-medium) !important;
            border-radius: 6px !important;
            color: var(--text-primary) !important;
            padding: 8px 12px !important;
            font-family: 'JetBrains Mono', monospace !important;
            font-size: 0.9rem !important;
        }
        .select2-container--default .select2-results__option {
            padding: 9px 12px !important;
            font-size: 0.88rem !important;
            color: var(--text-primary) !important;
            border-bottom: 1px solid var(--border-subtle) !important;
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

        /* Batch Stats & Action Bar */
        .batch-stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            background: var(--bg-surface);
            border: 1.5px solid var(--border-medium);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
        }
        .stat-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .stat-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .stat-chip strong {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.92rem;
            color: var(--text-primary);
        }
        .btn-batch-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
        }
        .btn-batch-unsent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.35);
        }
        .btn-batch-unsent:hover:not(:disabled) {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.5);
            transform: translateY(-1px);
        }
        .btn-batch-all {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #ffffff;
            box-shadow: 0 2px 10px rgba(59, 130, 246, 0.35);
        }
        .btn-batch-all:hover:not(:disabled) {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.5);
            transform: translateY(-1px);
        }
        .btn-batch-disabled {
            opacity: 0.45 !important;
            cursor: not-allowed !important;
            transform: none !important;
            box-shadow: none !important;
            filter: grayscale(0.6);
        }

        /* View Toggle (Cards vs Table) & Filter */
        .view-controls-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 14px;
            padding: 0 4px;
        }
        .pill-toggle-group {
            display: flex;
            background: var(--bg-surface);
            border: 1px solid var(--border-medium);
            border-radius: 8px;
            padding: 3px;
            gap: 4px;
        }
        .pill-btn {
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .pill-btn:hover { color: var(--text-primary); }
        .pill-btn.active {
            background: var(--accent-blue);
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.35);
        }

        /* ===== CONTAINER BATCH CARD ===== */
        .container-batch-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border-medium);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 22px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
            position: relative;
            transition: all 0.25s ease;
        }
        .container-batch-card:hover {
            border-color: rgba(59, 130, 246, 0.45);
        }
        .container-batch-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-subtle);
            margin-bottom: 12px;
        }
        .cont-title-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cont-box-num {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--accent-blue);
            letter-spacing: 0.5px;
        }
        .container-profile-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 8px 14px;
            margin-bottom: 14px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .container-profile-bar .item strong {
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
        }

        /* Step Cards Grid inside Container */
        .timeline-cards-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }
        @media (min-width: 860px) {
            .timeline-cards-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (min-width: 1300px) {
            .timeline-cards-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        .step-card {
            background: var(--bg-surface);
            border: 1.5px solid var(--border-medium);
            border-radius: 10px;
            padding: 13px 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 10px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .step-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
        }
        .step-card.is-sent-card {
            border-color: rgba(16, 185, 129, 0.4);
            background: rgba(16, 185, 129, 0.04);
        }
        .step-card.is-disabled-card {
            opacity: 0.5;
            filter: grayscale(0.7);
        }
        .step-card.active-selected {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.35);
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
            font-size: 0.88rem;
            color: var(--text-primary);
        }
        .step-badge-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 1.5px solid var(--border-medium);
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--text-primary);
            flex-shrink: 0;
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
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 5px;
            font-weight: 700;
            white-space: nowrap;
        }
        [data-theme="light"] .step-badge-ready {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }
        .step-badge-sent {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.35);
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 5px;
            font-weight: 700;
            white-space: nowrap;
        }
        [data-theme="light"] .step-badge-sent {
            background: #dcfce7;
            color: #15803d;
            border-color: #86efac;
        }

        .step-body-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 0.76rem;
            color: var(--text-secondary);
            background: var(--bg-card);
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid var(--border-subtle);
            line-height: 1.45;
        }
        .step-body-info .info-val {
            color: var(--text-primary);
            font-weight: 600;
        }
        .step-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .btn-quick-send {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.35);
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s ease;
            font-family: inherit;
        }
        .btn-quick-send:hover {
            background: #10b981;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        /* Dual Bottom Cards: Live JSON & Response */
        .batch-dual-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: stretch;
            margin-bottom: 24px;
        }
        @media (max-width: 1024px) {
            .batch-dual-grid {
                grid-template-columns: 1fr;
            }
        }
        .json-box {
            width: 100%;
            min-height: 200px;
            max-height: 250px;
            background: #0d131f;
            color: #a5f3fc;
            padding: 14px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11.5px;
            line-height: 1.5;
            border: 1px solid #1e293b;
            resize: vertical;
        }

        /* Button Main Send */
        .btn-send-batch {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.95rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }
        .btn-send-batch:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
        }
        .btn-send-batch:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        .btn-send-sequential {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 11px 20px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.92rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
        }
        .btn-send-sequential:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.45);
        }
        .btn-send-sequential:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal-card {
            background: var(--bg-card);
            border: 1px solid var(--border-medium);
            border-radius: 14px;
            width: 100%;
            max-width: 980px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            overflow: hidden;
        }

        /* Detailed Table View */
        .batch-table-wrap {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            background: var(--bg-card);
        }
        .batch-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .batch-table th {
            background: var(--bg-surface);
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-size: 0.72rem;
            padding: 10px 10px;
            border-bottom: 2px solid var(--border-medium);
            white-space: nowrap;
        }
        .batch-table td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-subtle);
            vertical-align: middle;
        }
        .batch-table tr:hover td {
            background: rgba(59, 130, 246, 0.05);
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.73rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .badge-in { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-out { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-ceisa { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }

        .btn-action-sm {
            padding: 7px 14px;
            border-radius: 7px;
            border: 1px solid var(--border-medium);
            background: var(--bg-surface);
            color: var(--text-primary);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-action-sm:hover {
            background: var(--border-medium);
        }
    </style>
</head>
<body data-login-time="<?= $loginTime ?>">
    <div class="dashboard">
        <div class="sidebar-overlay"></div>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" id="menu-toggle">☰</button>
                    <div class="header-breadcrumb">
                        <span>CEISA 4.0</span>
                        <span class="separator">/</span>
                        <span>Kirim Dokumen</span>
                        <span class="separator">/</span>
                        <span class="current">TPS Tracking Batch</span>
                    </div>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="theme-toggle" title="Ubah Mode (Gelap / Terang)">
                        <span class="theme-toggle-icon">🌙</span>
                        <span class="theme-toggle-text">Dark</span>
                    </button>
                    <button class="btn-refresh-token" onclick="refreshAccessToken()" title="Perbarui token JWT">
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

            <main class="content-area">
                <div class="batch-container">

                    <!-- Header Card -->
                    <div class="batch-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                            <div>
                                <h2 style="margin: 0; font-size: 1.3rem; color: var(--text-primary); font-weight: 700; display: flex; align-items: center; gap: 10px;">
                                    <span>📦</span> TPS Tracking Batch — Kirim Banyak Kontainer
                                </h2>
                                <p style="margin: 6px 0 0; color: var(--text-secondary); font-size: 0.88rem;">
                                    Pilih banyak kontainer sekaligus. Masing-masing kontainer <strong>otomatis membawa seluruh alur proses operasionalnya</strong> (Gate In, Stacking, Stripping/Behandle, Truck In/Pickup, Gate Out) yang tercatat di sistem.
                                </p>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <a href="report_tracking_batch.php" class="btn-action-sm" style="text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35);">
                                    <span>📊</span> Laporan Batch Terkirim
                                </a>
                                <a href="tps_tracking.php" class="btn-action-sm" style="text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.35);">
                                    <span>📍</span> Kirim Satuan (Single)
                                </a>
                                <span class="badge-pill badge-ceisa">POST /tps-tracking/batch</span>
                                <span class="badge-pill" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                    <span class="pulse-dot"></span> CEISA 4.0 OpenAPI
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Departemen Operasional Lini 2 Toggle -->
                    <div class="dept-toggle-card">
                        <div class="dept-toggle-label">
                            <span style="display:flex; align-items:center; gap:6px;">
                                <span>🏢</span> DEPARTEMEN OPERASIONAL LINI 2
                            </span>
                            <span id="dept-database-badge" style="font-size:0.75rem; text-transform:none; padding:3px 10px; border-radius:6px; background:rgba(59,130,246,0.15); color:var(--accent-blue); border:1px solid rgba(59,130,246,0.3); font-weight:600;">
                                DB: tpp_primamas (PLP FCL)
                            </span>
                        </div>
                        <div class="dept-toggle-group">
                            <button type="button" class="dept-btn active dept-tpp" id="btn-dept-tpp" onclick="setDepartment('tpp')">
                                <span style="font-size:1.1rem;">🏢</span> TPP (PLP / Lapangan Penumpukan FCL)
                            </button>
                            <button type="button" class="dept-btn" id="btn-dept-gudang" onclick="setDepartment('gudang')">
                                <span style="font-size:1.1rem;">🏬</span> Gudang (LCL / CFS Stripping)
                            </button>
                        </div>
                    </div>

                    <!-- 1. MULTI-CONTAINER SELECTOR BAR -->
                    <div class="picker-box">
                        <div class="picker-header">
                            <div class="picker-title">
                                <span>📦</span> 1. Pilih Kontainer yang Akan Dikirim:
                            </div>
                            <div class="picker-actions">
                                <button type="button" class="btn-action-sm" onclick="openPlpModal()" style="background:rgba(139,92,246,0.15); color:#a78bfa; border-color:rgba(139,92,246,0.35);">
                                    <span id="btn-modal-icon">📥</span> <span id="btn-modal-label">Pilih dari Database PLP</span>
                                </button>
                                <button type="button" class="btn-action-sm" onclick="openPasteModal()" style="background:rgba(59,130,246,0.15); color:#60a5fa; border-color:rgba(59,130,246,0.35);">
                                    <span>📋</span> Tempel Daftar Kontainer
                                </button>
                                <button type="button" class="btn-action-sm" onclick="clearAllSelectedContainers()" style="background:rgba(239,68,68,0.12); color:#ef4444; border-color:rgba(239,68,68,0.3);">
                                    <span>🗑️</span> Kosongkan Pilihan
                                </button>
                            </div>
                        </div>

                        <!-- Select2 Multi-Select Dropdown -->
                        <div style="margin-bottom: 8px;">
                            <select id="select-containers" multiple="multiple" style="width: 100%;">
                            </select>
                        </div>

                        <div style="font-size:0.78rem; color:var(--text-secondary); display:flex; align-items:center; gap:6px;">
                            <span>💡</span>
                            <span>Ketik nomor kontainer di atas atau pilih dari daftar database. Setiap kontainer yang dipilih akan langsung menampilkan seluruh tahapan alur operasionalnya di bawah.</span>
                        </div>
                    </div>

                    <!-- 2. BATCH STATS & ACTION TOOLBAR -->
                    <div class="batch-stats-bar">
                        <div class="stat-group">
                            <div class="stat-chip">
                                <span>📦 Kontainer:</span>
                                <strong id="stat-total-conts" style="color:var(--accent-blue);">0</strong>
                            </div>
                            <div class="stat-chip">
                                <span>📋 Total Alur:</span>
                                <strong id="stat-total-flows" style="color:#a78bfa;">0</strong>
                            </div>
                            <div class="stat-chip">
                                <span>⚡ Siap Kirim:</span>
                                <strong id="stat-ready-flows" style="color:#10b981;">0</strong>
                            </div>
                            <div class="stat-chip">
                                <span>✅ Pernah Terkirim:</span>
                                <strong id="stat-sent-flows" style="color:#f59e0b;">0</strong>
                            </div>
                        </div>

                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <button type="button" id="btn-batch-unsent" class="btn-batch-action btn-batch-unsent" onclick="triggerBatchSend('unsent');" title="Kirim seluruh alur yang belum pernah terkirim ke CEISA">
                                <span>🚀</span> Kirim Semua (Belum Terkirim) <span id="badge-unsent-count" style="background:rgba(255,255,255,0.25); padding:1px 7px; border-radius:12px; font-size:0.75rem;">0</span>
                            </button>
                            <button type="button" id="btn-batch-all" class="btn-batch-action btn-batch-all" onclick="triggerBatchSend('all');" title="Kirim seluruh alur operasional dari seluruh kontainer">
                                <span>⚡</span> Kirim Semua (Seluruh Alur) <span id="badge-all-count" style="background:rgba(255,255,255,0.25); padding:1px 7px; border-radius:12px; font-size:0.75rem;">0</span>
                            </button>
                        </div>
                    </div>

                    <!-- 3. VIEW CONTROLS & FILTER -->
                    <div class="view-controls-bar">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:0.84rem; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:6px;">
                                <span>📋</span> Alur Operasional Kontainer:
                            </span>
                            <div class="pill-toggle-group">
                                <button type="button" class="pill-btn active" id="btn-filter-all" onclick="setFlowFilter('all')">Semua Alur</button>
                                <button type="button" class="pill-btn" id="btn-filter-unsent" onclick="setFlowFilter('unsent')">Hanya Belum Terkirim</button>
                            </div>
                        </div>

                        <div class="pill-toggle-group">
                            <button type="button" class="pill-btn active" id="btn-view-cards" onclick="setViewMode('cards')">
                                <span>🗂️</span> Kartu per Kontainer
                            </button>
                            <button type="button" class="pill-btn" id="btn-view-table" onclick="setViewMode('table')">
                                <span>📋</span> Tabel Rincian Semua Alur
                            </button>
                        </div>
                    </div>

                    <!-- 4. CONTAINER & TIMELINE FLOWS CONTAINER -->
                    <div id="batch-flows-wrapper">
                        <!-- Loading State -->
                        <div id="batch-loading-indicator" style="display:none; text-align:center; padding:40px; background:var(--bg-card); border-radius:12px; border:1px solid var(--border-subtle);">
                            <span class="pulse-dot" style="background:var(--accent-blue);"></span>
                            <p style="margin-top:10px; color:var(--text-secondary); font-size:0.9rem;">Sedang menelusuri riwayat alur operasional kontainer terpilih...</p>
                        </div>

                        <!-- Empty State -->
                        <div id="batch-empty-state" class="batch-card" style="text-align:center; padding:48px 24px;">
                            <div style="font-size:2.8rem; margin-bottom:12px;">📦</div>
                            <h3 style="margin:0 0 8px; font-size:1.15rem; color:var(--text-primary); font-weight:700;">Belum Ada Kontainer yang Dipilih</h3>
                            <p style="margin:0 auto 18px; max-width:560px; color:var(--text-secondary); font-size:0.88rem; line-height:1.5;">
                                Silakan ketik atau pilih kontainer pada kotak di atas, gunakan tombol <b>[Pilih dari Database PLP]</b>, atau klik <b>[Tempel Daftar Kontainer]</b> untuk memuat alur proses operasional kontainer secara otomatis.
                            </p>
                            <button type="button" class="btn-action-sm" onclick="openPlpModal()" style="padding:10px 20px; font-size:0.9rem; font-weight:700; background:rgba(59,130,246,0.15); color:var(--accent-blue); border-color:rgba(59,130,246,0.4);">
                                <span>📥</span> Buka Daftar Kontainer Database
                            </button>
                        </div>

                        <!-- Container Cards View Container -->
                        <div id="view-cards-container"></div>

                        <!-- Detailed Table View Container -->
                        <div id="view-table-container" style="display:none;">
                            <div class="batch-table-wrap">
                                <table class="batch-table">
                                    <thead>
                                        <tr>
                                            <th style="width:36px; text-align:center;">
                                                <input type="checkbox" id="check-all-table-flows" checked onchange="toggleAllTableFlows(this.checked)">
                                            </th>
                                            <th>#</th>
                                            <th>No Kontainer</th>
                                            <th>Alur / Step</th>
                                            <th>Waktu Kegiatan</th>
                                            <th>Block/Slot/Tier</th>
                                            <th>Nopol Armada</th>
                                            <th>No B/L</th>
                                            <th>Dokumen Pabean</th>
                                            <th>Status CEISA</th>
                                            <th style="text-align:center;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-detailed-flows"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 5. DUAL CARD SEJAJAR: LIVE JSON ARRAY (Kiri) & RESPON CEISA 4.0 (Kanan) -->
                    <div class="batch-dual-grid">

                        <!-- KIRI: Live JSON Array + Send Buttons -->
                        <div class="batch-card" style="margin-bottom: 0; padding: 20px; display: flex; flex-direction: column;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 1.15rem;">⚡</span>
                                    <strong style="color: var(--text-primary); font-size: 0.96rem;">Live JSON Array Batch</strong>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span id="batch-status" style="color: #10b981; font-weight: 600; font-size: 0.8rem;">0 item siap dikirim</span>
                                    <button type="button" class="btn-action-sm" onclick="copyBatchJson()" style="padding:4px 10px; border-radius:6px; font-weight:600; font-size:0.75rem;">
                                        <span>📋</span> Salin JSON
                                    </button>
                                </div>
                            </div>

                            <div style="margin-bottom: 8px; font-size: 0.78rem; color: var(--text-secondary);">
                                Target Endpoint: <code>POST /tps-tracking/batch</code>
                            </div>

                            <textarea id="json-batch-preview" class="json-box" style="flex: 1; min-height: 190px; max-height: 230px;" readonly>[\n    // Belum ada alur kontainer yang dipilih\n]</textarea>

                            <div style="margin-top: 14px; display:flex; flex-direction:column; gap:8px;">
                                <button type="button" id="btn-send-batch" class="btn-send-batch" onclick="sendBatchViaBatchApi()">
                                    <span id="batch-spinner" style="display: none;" class="pulse-dot"></span>
                                    <span id="batch-icon">🚀</span>
                                    <span id="batch-text">Kirim Sekaligus via Batch API (/tps-tracking/batch)</span>
                                </button>
                                <button type="button" id="btn-send-sequential" class="btn-send-sequential" onclick="sendBatchSequentially()">
                                    <span>⚡</span>
                                    <span>Kirim Bertahap Satu per Satu (Progress Tracker)</span>
                                </button>
                            </div>
                        </div>

                        <!-- KANAN: Respon Gateway CEISA 4.0 -->
                        <div class="batch-card" style="margin-bottom: 0; padding: 20px; display: flex; flex-direction: column;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 1.15rem;">📡</span>
                                    <strong style="color: var(--text-primary); font-size: 0.96rem;">Respon Gateway CEISA 4.0</strong>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span id="batch-result-badge" class="badge-pill" style="background:rgba(100,116,139,0.15); color:var(--text-secondary); border:1px solid var(--border-medium); font-size:0.75rem;">STANDBY</span>
                                    <span id="batch-result-time" style="font-size: 0.78rem; color: var(--text-secondary);"></span>
                                </div>
                            </div>

                            <div id="batch-result-msg" style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px; min-height: 22px; line-height: 1.5;">
                                Menunggu pengiriman batch... Respon resmi dari server CEISA 4.0 akan ditampilkan di sini.
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <span style="font-size: 0.74rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">RAW RESPONSE JSON:</span>
                                <button type="button" onclick="copyBatchResponse()" style="padding:4px 10px; border-radius:6px; font-weight:600; font-size:0.75rem; background:transparent; border:1px solid var(--border-medium); color:var(--text-secondary); cursor:pointer;">
                                    <span>📋</span> Salin Respon
                                </button>
                            </div>

                            <pre id="batch-raw-response" style="flex: 1; background: #0d131f; color: #a5f3fc; padding: 14px; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 11.5px; min-height: 190px; max-height: 230px; overflow: auto; margin: 0; border: 1px solid #1e293b;">// Belum ada respon diterima dari gateway CEISA</pre>
                        </div>

                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Modal 1: Tarik Kontainer dari Database PLP / Gudang -->
    <div id="modal-plp-picker" class="modal-overlay" onclick="if(event.target===this)closePlpModal()">
        <div class="modal-card">
            <div style="padding:18px 24px; border-bottom:1px solid var(--border-medium); display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface);">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.35rem;" id="modal-plp-icon">📦</span>
                    <h3 id="modal-plp-title" style="margin:0; font-size:1.15rem; color:var(--text-primary); font-weight:700;">
                        Pilih Data Kontainer dari Database PLP (tppcontplp)
                    </h3>
                </div>
                <button type="button" onclick="closePlpModal()" style="background:none; border:none; color:var(--text-secondary); font-size:1.8rem; cursor:pointer; padding:2px 8px; line-height:1; border-radius:6px;" title="Tutup">&times;</button>
            </div>
            <div style="padding:14px 24px; border-bottom:1px solid var(--border-subtle); display:flex; gap:12px; align-items:center; background:var(--bg-base); flex-wrap:wrap;">
                <input type="text" id="plp-search-input" placeholder="🔍 Cari Nomor Kontainer / No B/L / Nopol..." style="flex:1; min-width:240px; padding:10px 14px; background:var(--bg-input); border:1px solid var(--border-medium); border-radius:8px; color:var(--text-primary); font-size:0.9rem;" oninput="debouncePlpSearch()">
                <label style="display:flex; align-items:center; gap:6px; font-size:0.82rem; color:var(--text-secondary); cursor:pointer; user-select:none;">
                    <input type="checkbox" id="filter-hide-sent" onchange="renderPlpRows()"> 
                    <span>Sembunyikan yg sudah pernah dikirim</span>
                </label>
                <button type="button" class="btn-action-sm" onclick="loadPlpContainers($('#plp-search-input').val())" style="padding:9px 16px; border-radius:8px; font-weight:600; background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.35);">
                    <span>🔄</span> Cari
                </button>
            </div>
            <div style="padding:16px 24px; overflow-y:auto; flex-grow:1; max-height:52vh;">
                <div id="plp-loading" style="display:none; text-align:center; padding:30px;">
                    <span class="pulse-dot" style="background:#3b82f6;"></span>
                    <p style="margin-top:10px; color:var(--text-secondary); font-size:0.88rem;">Memuat data kontainer dari database operasional...</p>
                </div>
                <table class="batch-table" id="table-plp-picker">
                    <thead>
                        <tr>
                            <th style="width:36px; text-align:center;"><input type="checkbox" id="check-all-plp" onchange="toggleSelectAllPlp(this)"></th>
                            <th>No Kontainer</th>
                            <th>Ukuran</th>
                            <th>Status</th>
                            <th style="width:115px;">Status CEISA</th>
                            <th>Posisi Yard</th>
                            <th>Nopol</th>
                            <th>No B/L</th>
                            <th>No Dokumen</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-plp-picker"></tbody>
                </table>
            </div>
            <div style="padding:16px 24px; border-top:1px solid var(--border-medium); display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface); flex-wrap:wrap; gap:12px;">
                <div style="font-size:0.85rem; color:var(--text-secondary); display:flex; align-items:center; gap:8px;">
                    <span>Dipilih:</span>
                    <span class="badge-pill" style="background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.3); font-weight:700;">
                        <span id="plp-selected-count">0</span> kontainer
                    </span>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="button" onclick="closePlpModal()" style="padding:8px 16px; border-radius:8px; background:transparent; border:1px solid var(--border-medium); color:var(--text-secondary); font-weight:600; cursor:pointer;">Batal</button>
                    <button type="button" id="btn-insert-modal-conts" onclick="addSelectedFromPlpModal()" style="background:linear-gradient(135deg, #10b981, #059669); color:#fff; border:none; padding:10px 22px; font-weight:700; border-radius:8px; box-shadow:0 4px 14px rgba(16,185,129,0.35); cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                        <span>✓</span>
                        <span>Gunakan Kontainer Terpilih (<span id="btn-count-label">0</span>)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 2: Tempel Banyak Kontainer (Bulk Paste) -->
    <div id="modal-paste-conts" class="modal-overlay" onclick="if(event.target===this)closePasteModal()">
        <div class="modal-card" style="max-width: 600px;">
            <div style="padding:18px 24px; border-bottom:1px solid var(--border-medium); display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface);">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.35rem;">📋</span>
                    <h3 style="margin:0; font-size:1.15rem; color:var(--text-primary); font-weight:700;">
                        Tempel Banyak Nomor Kontainer
                    </h3>
                </div>
                <button type="button" onclick="closePasteModal()" style="background:none; border:none; color:var(--text-secondary); font-size:1.8rem; cursor:pointer; line-height:1;" title="Tutup">&times;</button>
            </div>
            <div style="padding:20px 24px; background:var(--bg-base);">
                <p style="margin:0 0 10px; font-size:0.85rem; color:var(--text-secondary);">
                    Tempel daftar nomor kontainer yang dipisahkan oleh <b>koma, spasi, atau baris baru</b> (contoh hasil salin dari Excel):
                </p>
                <textarea id="paste-conts-textarea" style="width:100%; height:160px; padding:12px; background:var(--bg-input); border:1.5px solid var(--border-medium); border-radius:8px; color:var(--text-primary); font-family:'JetBrains Mono',monospace; font-size:0.9rem; line-height:1.5;" placeholder="MSNU1234567&#10;TCNU9876543&#10;WHSU0558494"></textarea>
            </div>
            <div style="padding:16px 24px; border-top:1px solid var(--border-medium); display:flex; justify-content:flex-end; gap:10px; background:var(--bg-surface);">
                <button type="button" onclick="closePasteModal()" style="padding:8px 16px; border-radius:8px; background:transparent; border:1px solid var(--border-medium); color:var(--text-secondary); font-weight:600; cursor:pointer;">Batal</button>
                <button type="button" onclick="processPastedContainers()" style="background:var(--accent-blue); color:#fff; border:none; padding:9px 20px; font-weight:700; border-radius:8px; cursor:pointer;">
                    ⚡ Proses & Muat Alur
                </button>
            </div>
        </div>
    </div>

    <div id="toast-container" style="position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px;"></div>

    <!-- Hidden input parameters -->
    <input type="hidden" id="global-kode-tps" value="PSU0">
    <input type="hidden" id="global-kode-gudang" value="<?= $activeDept === 'gudang' ? 'GPSU' : 'CPSU' ?>">

    <script>
        // State Management
        let currentDept = '<?= $activeDept ?>';
        let selectedContainers = []; // Array of clean container string ['WHSU0558494', 'TCNU1047306']
        let loadedContainersData = {}; // Map of contNo -> { container_info: {...}, total_flows: X, flows: [...] }
        let activeFlowFilter = 'all'; // 'all' | 'unsent'
        let activeViewMode = 'cards'; // 'cards' | 'table'
        let plpLoadedData = [];
        let plpSearchTimeout = null;

        // Inisialisasi Departemen
        function setDepartment(dept) {
            if (currentDept === dept) return;
            currentDept = dept;

            const btnTpp = document.getElementById('btn-dept-tpp');
            const btnGudang = document.getElementById('btn-dept-gudang');
            const badge = document.getElementById('dept-database-badge');
            const modalTitle = document.getElementById('modal-plp-title');
            const btnModalLabel = document.getElementById('btn-modal-label');
            const globalGudang = document.getElementById('global-kode-gudang');

            if (dept === 'gudang') {
                btnTpp.className = 'dept-btn';
                btnGudang.className = 'dept-btn active dept-gudang';
                badge.innerHTML = 'DB: primamas (Gudang LCL)';
                badge.style.background = 'rgba(16, 185, 129, 0.15)';
                badge.style.color = '#10b981';
                badge.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                globalGudang.value = 'GPSU';
                if (modalTitle) modalTitle.textContent = 'Pilih Data Kontainer dari Database Gudang (primamas - LCL)';
                if (btnModalLabel) btnModalLabel.textContent = 'Pilih dari Gudang LCL';
                showToast('Departemen Gudang (LCL / primamas) aktif', 'info');
            } else {
                btnGudang.className = 'dept-btn';
                btnTpp.className = 'dept-btn active dept-tpp';
                badge.innerHTML = 'DB: tpp_primamas (PLP FCL)';
                badge.style.background = 'rgba(59, 130, 246, 0.15)';
                badge.style.color = 'var(--accent-blue)';
                badge.style.borderColor = 'rgba(59, 130, 246, 0.3)';
                globalGudang.value = 'CPSU';
                if (modalTitle) modalTitle.textContent = 'Pilih Data Kontainer dari Database PLP (tppcontplp)';
                if (btnModalLabel) btnModalLabel.textContent = 'Pilih dari Database PLP';
                showToast('Departemen TPP (PLP / tpp_primamas) aktif', 'info');
            }

            // Kosongkan dan refresh data kontainer
            clearAllSelectedContainers();
            initSelect2();
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.style.cssText = 'padding:12px 20px;border-radius:8px;font-size:0.9rem;font-weight:500;box-shadow:0 4px 14px rgba(0,0,0,0.3);transition:all 0.3s ease;display:flex;align-items:center;gap:10px;';
            if (type === 'success') {
                toast.style.background = '#065f46'; toast.style.color = '#a7f3d0'; toast.style.border = '1px solid #10b981';
                toast.innerHTML = '<span>✅</span> ' + message;
            } else if (type === 'error') {
                toast.style.background = '#7f1d1d'; toast.style.color = '#fecaca'; toast.style.border = '1px solid #ef4444';
                toast.innerHTML = '<span>❌</span> ' + message;
            } else {
                toast.style.background = '#1e293b'; toast.style.color = '#e2e8f0'; toast.style.border = '1px solid #475569';
                toast.innerHTML = '<span>ℹ️</span> ' + message;
            }
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(10px)'; setTimeout(() => toast.remove(), 300); }, 4000);
        }

        async function refreshAccessToken() {
            try {
                showToast('Memperbarui token dari SSO Bea Cukai...', 'info');
                const res = await fetch('api/auth.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({refresh:true}) });
                const data = await res.json();
                if (data.success) showToast('Token berhasil diperbarui!', 'success');
                else showToast('Gagal refresh token: ' + (data.message||'Error'), 'error');
            } catch(e) { showToast('Koneksi auth error: ' + e.message, 'error'); }
        }

        // ===== SELECT2 MULTI-CONTAINER INITIALIZATION =====
        function initSelect2() {
            $('#select-containers').select2({
                placeholder: '-- Ketik atau Cari Nomor-Nomor Kontainer --',
                allowClear: true,
                tags: true,
                multiple: true,
                ajax: {
                    url: 'api/tps_tracking_batch.php?action=search_containers',
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

            $('#select-containers').off('change').on('change', function() {
                const values = $(this).val() || [];
                const cleanList = values.map(v => v.replace(/[\s\-]/g, '').toUpperCase()).filter(Boolean);
                syncSelectedContainers(cleanList);
            });
        }

        function formatContainerOption(item) {
            if (item.loading) return item.text;
            if (!item.container_no) return item.text;

            const isEmp = item.status === 'EMPTY';
            const isLcl = item.status === 'LCL';
            const badgeStatus = isEmp 
                ? `<span class="badge-pill badge-out" style="font-size:0.7rem; padding:1px 6px;">EMPTY</span>` 
                : (isLcl 
                    ? `<span class="badge-pill" style="background:rgba(245, 158, 11, 0.15); color:#f59e0b; border:1px solid rgba(245, 158, 11, 0.3); font-size:0.7rem; padding:1px 6px;">LCL</span>`
                    : `<span class="badge-pill badge-in" style="font-size:0.7rem; padding:1px 6px;">FCL</span>`);

            const badgeSent = item.already_sent 
                ? `<span class="badge-pill" style="background:rgba(245, 158, 11, 0.18); color:#f59e0b; border:1px solid rgba(245, 158, 11, 0.35); font-size:0.7rem; padding:1px 6px;">Pernah Terkirim</span>` 
                : '';

            let html = `
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="font-family:'JetBrains Mono',monospace; color:var(--text-primary); font-size:0.9rem;">${item.container_no}</strong>
                        ${badgeStatus}
                        <span class="badge-pill badge-ceisa" style="font-size:0.7rem; padding:1px 6px;">${item.size_type || '40'} ft</span>
                        ${badgeSent}
                    </div>
                    ${item.yard_block ? `<span style="font-size:0.76rem; color:var(--text-secondary);">📍 ${item.yard_block}</span>` : ''}
                </div>
            `;
            return $(html);
        }

        function formatContainerSelection(item) {
            return item.container_no || item.id || item.text || '';
        }

        // ===== SYNC & LOAD BATCH TIMELINE DATA =====
        async function syncSelectedContainers(newList) {
            selectedContainers = [...newList];

            if (selectedContainers.length === 0) {
                loadedContainersData = {};
                renderBatchUI();
                updateBatchJsonPreview();
                return;
            }

            // Temukan kontainer baru yang belum ada di loadedContainersData
            const neededConts = selectedContainers.filter(c => !loadedContainersData[c]);

            if (neededConts.length > 0) {
                $('#batch-loading-indicator').slideDown(150);
                try {
                    const res = await fetch('api/tps_tracking_batch.php?action=get_batch_timelines', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            dept: currentDept,
                            containers: neededConts
                        })
                    });
                    const data = await res.json();
                    if (data.success && data.grouped_by_container) {
                        for (const [cNo, cData] of Object.entries(data.grouped_by_container)) {
                            // Tandai checked secara default untuk alur yang siap kirim
                            if (cData.flows && cData.flows.length > 0) {
                                cData.flows.forEach(f => {
                                    f._checked = true; // default diikutsertakan
                                });
                            }
                            loadedContainersData[cNo] = cData;
                        }
                    }
                } catch (e) {
                    showToast('Gagal memuat alur kontainer: ' + e.message, 'error');
                } finally {
                    $('#batch-loading-indicator').slideUp(150);
                }
            }

            // Hapus kontainer yang sudah tidak ada di selectedContainers
            for (const cNo of Object.keys(loadedContainersData)) {
                if (!selectedContainers.includes(cNo)) {
                    delete loadedContainersData[cNo];
                }
            }

            renderBatchUI();
            updateBatchJsonPreview();
        }

        // ===== REFRESH DATA BATCH SECARA PAKSA DARI SERVER =====
        async function refreshBatchContainersData(contsToRefresh = null) {
            const targets = contsToRefresh || [...selectedContainers];
            if (!targets || targets.length === 0) return;

            // Hapus dari cache memory agar action=get_batch_timelines memuat ulang data terbaru
            targets.forEach(c => {
                delete loadedContainersData[c];
            });

            $('#batch-loading-indicator').slideDown(150);
            try {
                const res = await fetch('api/tps_tracking_batch.php?action=get_batch_timelines', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        dept: currentDept,
                        containers: targets
                    })
                });
                const data = await res.json();
                if (data.success && data.grouped_by_container) {
                    for (const [cNo, cData] of Object.entries(data.grouped_by_container)) {
                        if (cData.flows && cData.flows.length > 0) {
                            cData.flows.forEach(f => {
                                // Default centang hanya alur yang belum terkirim setelah refresh
                                f._checked = !f.is_sent;
                            });
                        }
                        loadedContainersData[cNo] = cData;
                    }
                }
            } catch (e) {
                console.error('Error refreshing batch containers:', e);
            } finally {
                $('#batch-loading-indicator').slideUp(150);
            }

            renderBatchUI();
            updateBatchJsonPreview();
        }

        // ===== RENDER UI BATCH (CARDS & TABLE) =====
        function renderBatchUI() {
            const hasContainers = selectedContainers.length > 0;
            $('#batch-empty-state').toggle(!hasContainers);
            $('#batch-flows-wrapper').toggle(true);

            if (!hasContainers) {
                $('#view-cards-container').html('');
                $('#tbody-detailed-flows').html('');
                updateStats(0, 0, 0, 0);
                return;
            }

            // Hitung statistik keseluruhan
            let totalFlows = 0;
            let readyFlows = 0;
            let sentFlows = 0;

            selectedContainers.forEach(cNo => {
                const cData = loadedContainersData[cNo];
                if (cData && cData.flows) {
                    cData.flows.forEach(f => {
                        totalFlows++;
                        if (f.is_sent) sentFlows++;
                        else readyFlows++;
                    });
                }
            });

            updateStats(selectedContainers.length, totalFlows, readyFlows, sentFlows);

            // Render Cards View
            renderCardsView();

            // Render Detailed Table View
            renderTableView();
        }

        function updateStats(conts, total, ready, sent) {
            $('#stat-total-conts').text(conts);
            $('#stat-total-flows').text(total);
            $('#stat-ready-flows').text(ready);
            $('#stat-sent-flows').text(sent);

            $('#badge-unsent-count').text(ready);
            $('#badge-all-count').text(total);

            if (ready === 0) {
                $('#btn-batch-unsent').prop('disabled', true).addClass('btn-batch-disabled').attr('title', 'Seluruh alur telah terkirim');
            } else {
                $('#btn-batch-unsent').prop('disabled', false).removeClass('btn-batch-disabled').attr('title', `Kirim ${ready} alur yang belum pernah terkirim`);
            }

            if (total === 0) {
                $('#btn-batch-all').prop('disabled', true).addClass('btn-batch-disabled');
            } else {
                $('#btn-batch-all').prop('disabled', false).removeClass('btn-batch-disabled');
            }
        }

        function renderCardsView() {
            const containerEl = $('#view-cards-container');
            let html = '';

            selectedContainers.forEach((cNo, cIdx) => {
                const cData = loadedContainersData[cNo] || { container_info: { nomorKontainer: cNo }, flows: [] };
                const info = cData.container_info || {};
                const flows = cData.flows || [];

                // Filter flows if activeFlowFilter === 'unsent'
                const displayFlows = (activeFlowFilter === 'unsent')
                    ? flows.filter(f => !f.is_sent)
                    : flows;

                const unsentInCont = flows.filter(f => !f.is_sent).length;
                const sentInCont = flows.filter(f => f.is_sent).length;

                const deptTag = (info.departemen === 'GUDANG' || currentDept === 'gudang')
                    ? `<span class="badge-pill" style="background:rgba(16, 185, 129, 0.15); color:#10b981; border:1px solid rgba(16, 185, 129, 0.3);">🏬 GUDANG</span>`
                    : `<span class="badge-pill" style="background:rgba(59, 130, 246, 0.15); color:#60a5fa; border:1px solid rgba(59, 130, 246, 0.3);">🏢 TPP</span>`;

                html += `
                    <div class="container-batch-card" id="cont-box-${cNo}">
                        <div class="container-batch-header">
                            <div class="cont-title-group">
                                <span style="font-size:1.3rem;">📦</span>
                                <span class="cont-box-num">${info.nomorKontainer || cNo}</span>
                                <span class="badge-pill badge-ceisa">${info.ukuranKontainer || '40'} ft</span>
                                <span class="badge-pill ${info.statusKontainer === 'EMPTY' ? 'badge-out' : 'badge-in'}">${info.statusKontainer || 'FCL'}</span>
                                ${deptTag}
                                <span class="badge-pill cont-summary-badge" id="cont-summary-badge-${cNo}" style="background:rgba(255,255,255,0.08); border:1px solid var(--border-subtle); color:var(--text-secondary); margin-left:4px;">
                                    ${flows.length} Alur (${unsentInCont} Siap Kirim, ${sentInCont} Terkirim)
                                </span>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <label style="display:flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--text-secondary); cursor:pointer; user-select:none;">
                                    <input type="checkbox" checked onchange="toggleContAllFlows('${cNo}', this.checked)">
                                    <span>Pilih Semua Alur Kontainer Ini</span>
                                </label>
                                <button type="button" class="btn-action-sm" onclick="removeSingleContainer('${cNo}')" style="background:rgba(239,68,68,0.12); color:#ef4444; border-color:rgba(239,68,68,0.3); padding:4px 10px; font-size:0.78rem;" title="Hapus kontainer ini dari batch">
                                    ✕ Hapus
                                </button>
                            </div>
                        </div>

                        <!-- Profil Kontainer Bar -->
                        <div class="container-profile-bar">
                            <div class="item">🚚 <span>In Trailer:</span> <strong>${info.inTrailer || '-'}</strong></div>
                            <div class="item">🚛 <span>Out Trailer:</span> <strong>${info.outTrailer || '-'}</strong></div>
                            ${info.lokasiYard && info.lokasiYard !== '-' ? `<div class="item">📍 <span>Yard:</span> <strong>${info.lokasiYard}</strong></div>` : ''}
                            ${info.suratPlp && info.suratPlp !== '-' ? `<div class="item">📑 <span>PLP:</span> <strong>${info.suratPlp}</strong></div>` : ''}
                            ${info.noBl && info.noBl !== '-' ? `<div class="item">📄 <span>B/L:</span> <strong>${info.noBl}</strong></div>` : ''}
                        </div>

                        <!-- Step Cards Grid -->
                        <div class="timeline-cards-grid">
                `;

                if (displayFlows.length === 0) {
                    html += `
                        <div style="grid-column: 1 / -1; padding: 22px; text-align: center; background: rgba(0,0,0,0.15); border: 1px dashed var(--border-medium); border-radius: 10px;">
                            <div style="font-size:1.4rem; margin-bottom:4px;">⏳</div>
                            <div style="font-weight:600; font-size:0.9rem; color:var(--text-secondary);">
                                ${flows.length === 0 ? 'Belum Ada Alur Operasional yang Selesai di Depo' : 'Tidak ada alur yang sesuai dengan filter'}
                            </div>
                        </div>
                    `;
                } else {
                    displayFlows.forEach((step, sIdx) => {
                        const isSent = step.is_sent;
                        const isChecked = step._checked !== false;
                        let badgeClass = isSent ? 'step-badge-sent' : 'step-badge-ready';
                        let badgeText = isSent ? `✅ Terkirim (${step.sent_info?.sent_at ? step.sent_info.sent_at.split(' ')[0] : 'CEISA'})` : '⚡ Siap Kirim';

                        html += `
                            <div class="step-card ${isSent ? 'is-sent-card' : ''} ${isChecked ? 'active-selected' : 'is-disabled-card'}" id="step-card-${cNo}-${step.kodeKegiatan}">
                                <div>
                                    <div class="step-header">
                                        <div class="step-title-box">
                                            <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="toggleSingleFlow('${cNo}', ${step.kodeKegiatan}, this.checked)" style="cursor:pointer; width:16px; height:16px;" title="Sertakan alur ini dalam pengiriman batch">
                                            <span class="step-badge-num">${step.step}</span>
                                            <span style="font-size:1.1rem;">${step.icon || '📦'}</span>
                                            <span style="font-size:0.86rem;">${step.kegiatanLabel}</span>
                                        </div>
                                        <span class="${badgeClass}" id="step-badge-${cNo}-${step.kodeKegiatan}">${badgeText}</span>
                                    </div>

                                    <div class="step-body-info" style="margin-top: 8px;">
                                        <div style="display:flex; justify-content:space-between;">
                                            <span>⏱️ <b>Waktu:</b></span>
                                            <span class="info-val" style="font-family:'JetBrains Mono',monospace;">${step.waktuKegiatan || '-'}</span>
                                        </div>
                                        ${step.nopolLabel && step.nopolLabel !== '-' ? `
                                        <div style="display:flex; justify-content:space-between;">
                                            <span>🚚 <b>Armada:</b></span>
                                            <span class="info-val" style="color:var(--accent-blue);">${step.nopolLabel}</span>
                                        </div>
                                        ` : ''}
                                        ${step.lokasiYard && step.lokasiYard !== '-' ? `
                                        <div style="display:flex; justify-content:space-between;">
                                            <span>📍 <b>Yard:</b></span>
                                            <span class="info-val">${step.lokasiYard}</span>
                                        </div>
                                        ` : ''}
                                        ${step.dokumenLabel && step.dokumenLabel !== '-' ? `
                                        <div style="display:flex; justify-content:space-between; gap:6px;">
                                            <span>📑 <b>Dokumen:</b></span>
                                            <span class="info-val" style="word-break:break-all;">${step.dokumenLabel}</span>
                                        </div>
                                        ` : ''}
                                        ${step.deskripsi ? `
                                        <div style="margin-top:2px; font-size:0.71rem; color:var(--text-secondary); border-top:1px dashed var(--border-subtle); padding-top:3px;">
                                            ${step.deskripsi}
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>

                                <div class="step-actions">
                                    <span style="font-size:0.75rem; color:var(--text-secondary);">
                                        Kegiatan #${step.kodeKegiatan}
                                    </span>
                                    <button type="button" class="btn-quick-send" onclick="quickSendSingleFlow('${cNo}', ${step.kodeKegiatan})" title="Kirim hanya alur ini ke CEISA 4.0">
                                        <span>🚀</span> Kirim Alur Ini
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                }

                html += `
                        </div>
                    </div>
                `;
            });

            containerEl.html(html);
        }

        function renderTableView() {
            const tbody = $('#tbody-detailed-flows');
            let rowsHtml = '';
            let rowIdx = 0;

            selectedContainers.forEach(cNo => {
                const cData = loadedContainersData[cNo] || { flows: [] };
                const flows = cData.flows || [];
                const displayFlows = (activeFlowFilter === 'unsent')
                    ? flows.filter(f => !f.is_sent)
                    : flows;

                displayFlows.forEach(step => {
                    rowIdx++;
                    const isChecked = step._checked !== false;
                    const isSent = step.is_sent;
                    const yard = [step.yard_block, step.slot ? 'S:'+step.slot : '', step.tier ? 'T:'+step.tier : ''].filter(Boolean).join(' ');

                    rowsHtml += `
                        <tr style="${!isChecked ? 'opacity:0.4;' : ''}">
                            <td style="text-align:center;">
                                <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="toggleSingleFlow('${cNo}', ${step.kodeKegiatan}, this.checked)">
                            </td>
                            <td style="color:var(--text-secondary); font-weight:600;">${rowIdx}</td>
                            <td><strong style="font-family:'JetBrains Mono',monospace; color:var(--accent-blue);">${cNo}</strong></td>
                            <td><b>#${step.step}</b> ${step.icon || '📦'} ${step.kegiatanLabel}</td>
                            <td style="font-family:'JetBrains Mono',monospace;">${step.waktuKegiatan || '-'}</td>
                            <td>${yard || '-'}</td>
                            <td><b>${step.nopol || '-'}</b></td>
                            <td><small>${step.no_bl || '-'}</small></td>
                            <td><small>${step.nomorDokumen || '-'}</small></td>
                            <td>
                                ${isSent 
                                    ? `<span class="badge-pill badge-in" style="font-size:10.5px;">✅ Terkirim</span>` 
                                    : `<span class="badge-pill badge-ceisa" style="font-size:10.5px;">⚡ Siap</span>`}
                            </td>
                            <td style="text-align:center;">
                                <button type="button" class="btn-quick-send" style="padding:3px 8px; font-size:0.72rem;" onclick="quickSendSingleFlow('${cNo}', ${step.kodeKegiatan})">
                                    🚀 Kirim
                                </button>
                            </td>
                        </tr>
                    `;
                });
            });

            if (rowsHtml === '') {
                rowsHtml = `<tr><td colspan="11" style="text-align:center; padding:24px; color:var(--text-secondary);">Tidak ada alur kegiatan untuk ditampilkan</td></tr>`;
            }

            tbody.html(rowsHtml);
        }

        // Toggle Single Flow Checked State
        function toggleSingleFlow(cNo, kdKeg, isChecked) {
            const cData = loadedContainersData[cNo];
            if (cData && cData.flows) {
                const f = cData.flows.find(x => x.kodeKegiatan == kdKeg);
                if (f) f._checked = isChecked;
            }
            const cardEl = $(`#step-card-${cNo}-${kdKeg}`);
            if (isChecked) {
                cardEl.addClass('active-selected').removeClass('is-disabled-card');
            } else {
                cardEl.removeClass('active-selected').addClass('is-disabled-card');
            }
            updateBatchJsonPreview();
            if (activeViewMode === 'table') renderTableView();
        }

        // Toggle All Flows in a Single Container
        function toggleContAllFlows(cNo, isChecked) {
            const cData = loadedContainersData[cNo];
            if (cData && cData.flows) {
                cData.flows.forEach(f => f._checked = isChecked);
            }
            renderBatchUI();
            updateBatchJsonPreview();
        }

        // Toggle All Flows across All Containers
        function toggleAllTableFlows(isChecked) {
            selectedContainers.forEach(cNo => {
                const cData = loadedContainersData[cNo];
                if (cData && cData.flows) {
                    cData.flows.forEach(f => f._checked = isChecked);
                }
            });
            renderBatchUI();
            updateBatchJsonPreview();
        }

        // Filter: 'all' vs 'unsent'
        function setFlowFilter(filter) {
            activeFlowFilter = filter;
            $('#btn-filter-all').toggleClass('active', filter === 'all');
            $('#btn-filter-unsent').toggleClass('active', filter === 'unsent');
            renderCardsView();
            renderTableView();
        }

        // View Mode: 'cards' vs 'table'
        function setViewMode(mode) {
            activeViewMode = mode;
            $('#btn-view-cards').toggleClass('active', mode === 'cards');
            $('#btn-view-table').toggleClass('active', mode === 'table');
            $('#view-cards-container').toggle(mode === 'cards');
            $('#view-table-container').toggle(mode === 'table');
        }

        // Remove Single Container
        function removeSingleContainer(cNo) {
            const nextList = selectedContainers.filter(c => c !== cNo);
            $('#select-containers').val(nextList).trigger('change');
            showToast(`Kontainer ${cNo} dihapus dari batch`, 'info');
        }

        // Clear All Selected Containers
        function clearAllSelectedContainers() {
            selectedContainers = [];
            loadedContainersData = {};
            $('#select-containers').val(null).trigger('change');
            renderBatchUI();
            updateBatchJsonPreview();
            showToast('Seluruh kontainer terpilih telah dikosongkan', 'info');
        }

        // ===== LIVE JSON ARRAY GENERATOR =====
        function buildActiveBatchPayload() {
            const items = [];
            const kodeTps = $('#global-kode-tps').val() || 'PSU0';
            const kodeGudang = $('#global-kode-gudang').val() || (currentDept === 'gudang' ? 'GPSU' : 'CPSU');

            selectedContainers.forEach(cNo => {
                const cData = loadedContainersData[cNo];
                if (!cData || !cData.flows) return;

                cData.flows.forEach(f => {
                    if (f._checked === false) return; // dilewati jika tidak dicentang

                    // Buat payload item resmi CEISA 4.0 (TdTpsTrackingRequest)
                    const item = {
                        departemen: currentDept.toUpperCase(),
                        kodeTps: kodeTps,
                        kodeGudang: kodeGudang,
                        nomorKontainer: f.container_no || cNo,
                        ukuranKontainer: f.ukuranKontainer || '40',
                        jenisKontainer: f.jenisKontainer || (currentDept === 'gudang' ? '7' : '8'),
                        kodeKegiatan: f.kodeKegiatan,
                        waktuKegiatan: f.waktuKegiatan
                    };

                    if (f.yard_block && f.yard_block.trim()) item.block = f.yard_block.trim();
                    if (f.slot && f.slot.trim()) item.slot = f.slot.trim();
                    if (f.tier && f.tier.trim()) item.tier = f.tier.trim();
                    if (f.nopol && f.nopol.trim()) item.nomorPolisi = f.nopol.trim().replace(/\s+/g, '');
                    if (f.no_bl && f.no_bl.trim()) item.nomorBlAwb = f.no_bl.trim();
                    if (f.tanggalBlAwb && f.tanggalBlAwb.trim()) item.tanggalBlAwb = f.tanggalBlAwb.trim();
                    if (f.kodeDokumen && f.kodeDokumen.trim()) item.kodeDokumen = f.kodeDokumen.trim();
                    if (f.nomorDokumen && f.nomorDokumen.trim()) item.nomorDokumen = f.nomorDokumen.trim();
                    if (f.tanggalDokumen && f.tanggalDokumen.trim()) item.tanggalDokumen = f.tanggalDokumen.trim();

                    items.push(item);
                });
            });

            return items;
        }

        function updateBatchJsonPreview() {
            const items = buildActiveBatchPayload();
            const previewEl = document.getElementById('json-batch-preview');
            const statusEl = document.getElementById('batch-status');
            const btnBatch = document.getElementById('btn-send-batch');
            const btnSeq = document.getElementById('btn-send-sequential');

            if (items.length === 0) {
                previewEl.value = '[\n    // Belum ada alur kontainer yang dipilih atau dicentang\n]';
                statusEl.innerHTML = '<span style="color:var(--text-secondary);">⚠️ 0 item siap</span>';
                if (btnBatch) btnBatch.disabled = true;
                if (btnSeq) btnSeq.disabled = true;
                return;
            }

            previewEl.value = JSON.stringify(items, null, 4);
            statusEl.innerHTML = `<span style="color:#10b981;">✓ ${items.length} item siap dikirim</span>`;
            if (btnBatch) btnBatch.disabled = false;
            if (btnSeq) btnSeq.disabled = false;
        }

        function copyBatchJson() {
            const text = document.getElementById('json-batch-preview').value;
            navigator.clipboard.writeText(text).then(() => showToast('JSON array disalin ke clipboard!', 'success'));
        }

        function copyBatchResponse() {
            const text = document.getElementById('batch-raw-response').textContent;
            navigator.clipboard.writeText(text).then(() => showToast('Respon JSON disalin ke clipboard!', 'success'));
        }

        // ===== HELPER: UPDATE CARD STATUS REALTIME MENJADI TERKIRIM =====
        function markFlowAsSentInUI(cNo, kodeKegiatan, sentDateStr) {
            // 1. Update in-memory flow state
            const cData = loadedContainersData[cNo];
            if (cData && cData.flows) {
                const f = cData.flows.find(x => x.kodeKegiatan == kodeKegiatan);
                if (f) {
                    f.is_sent = true;
                    f._checked = false; // uncheck after sent
                    f.sent_info = {
                        sent_at: sentDateStr,
                        status: 'SUCCESS'
                    };
                }
            }

            // 2. Direct DOM Update for Step Card
            const stepCard = $(`#step-card-${cNo}-${kodeKegiatan}`);
            if (stepCard.length) {
                stepCard.addClass('is-sent-card').removeClass('active-selected');
                stepCard.find('input[type="checkbox"]').prop('checked', false);
                const badgeEl = stepCard.find(`#step-badge-${cNo}-${kodeKegiatan}, .step-header span.step-badge-ready, .step-header span.step-badge-sent`);
                badgeEl.removeClass('step-badge-ready').addClass('step-badge-sent')
                       .html('✅ Terkirim (' + sentDateStr + ')');
            }

            // 3. Update Container Header Badge Summary
            updateContainerHeaderBadge(cNo);

            // 4. Update Summary Statistics Bar
            recalculateSummaryStats();
        }

        function updateContainerHeaderBadge(cNo) {
            const cData = loadedContainersData[cNo];
            if (!cData || !cData.flows) return;
            const flows = cData.flows;
            const unsentInCont = flows.filter(f => !f.is_sent).length;
            const sentInCont = flows.filter(f => f.is_sent).length;
            $(`#cont-summary-badge-${cNo}`).text(`${flows.length} Alur (${unsentInCont} Siap Kirim, ${sentInCont} Terkirim)`);
        }

        function recalculateSummaryStats() {
            let totalFlows = 0;
            let readyFlows = 0;
            let sentFlows = 0;

            selectedContainers.forEach(cNo => {
                const cData = loadedContainersData[cNo];
                if (cData && cData.flows) {
                    cData.flows.forEach(f => {
                        totalFlows++;
                        if (f.is_sent) sentFlows++;
                        else readyFlows++;
                    });
                }
            });

            updateStats(selectedContainers.length, totalFlows, readyFlows, sentFlows);
            updateBatchJsonPreview();
        }

        // ===== PENGIRIMAN BATCH: METODE 1 (BATCH API POST /tps-tracking/batch) =====
        async function sendBatchViaBatchApi() {
            const items = buildActiveBatchPayload();
            if (items.length === 0) {
                Swal.fire({ title: 'Tidak Ada Data', text: 'Pilih minimal 1 alur kontainer untuk dikirim.', icon: 'warning', confirmButtonColor: '#3b82f6' });
                return;
            }

            const uniqueConts = [...new Set(items.map(i => i.nomorKontainer))];
            const deptLabel = currentDept === 'gudang' ? '🏬 Gudang (LCL)' : '🏢 TPP (PLP)';

            const confirmRes = await Swal.fire({
                title: 'Konfirmasi Kirim Batch',
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                        <p>Kirim <b>${items.length} alur operasional</b> dari <b>${uniqueConts.length} kontainer</b> sekaligus via Batch API ke <b>CEISA 4.0</b>?</p>
                        <div style="background:rgba(0,0,0,0.15); border:1px solid var(--border-medium); border-radius:8px; padding:10px; margin-bottom:10px;">
                            <div>🏢 <b>Departemen:</b> ${deptLabel}</div>
                            <div>📦 <b>Kontainer:</b> ${uniqueConts.join(', ')}</div>
                            <div>⚡ <b>Total Alur:</b> ${items.length} item pergerakan</div>
                        </div>
                        <p style="font-size:12px; color:var(--text-secondary); margin:0;">
                            ℹ️ Seluruh alur akan dikirimkan dalam 1 paket data JSON array ke endpoint <code>POST /tps-tracking/batch</code>.
                        </p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `🚀 Ya, Kirim ${items.length} Alur`,
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            });

            if (!confirmRes.isConfirmed) return;

            const btn = document.getElementById('btn-send-batch');
            const spinner = document.getElementById('batch-spinner');
            const icon = document.getElementById('batch-icon');
            btn.disabled = true; spinner.style.display = 'inline-block'; icon.style.display = 'none';

            Swal.fire({
                title: 'Mengirim Batch ke CEISA 4.0...',
                html: `Sedang mengirim <b>${items.length} alur operasional</b> ke gateway Bea Cukai...`,
                allowOutsideClick: false, allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const res = await fetch('api/tps_tracking_batch.php?action=send', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ items: items, departemen: currentDept.toUpperCase() })
                });
                const result = await res.json();
                const ceisaRaw = result.raw || result;
                const isConflict = (result.code === 409 || ceisaRaw.code === 409 || ceisaRaw.result === 'Data already exists.' || (ceisaRaw.detail && ceisaRaw.detail.includes('duplikat')));

                const badge = $('#batch-result-badge');
                badge.removeClass('badge-in badge-out').removeAttr('style');

                if (result.success) {
                    badge.addClass('badge-in').text(`HTTP ${result.code || 201} — BATCH OK`);
                    $('#batch-result-msg').html(`✅ <b>Batch Berhasil:</b> ${result.total_sent || items.length} alur kontainer berhasil direkam.<br><small style="color:var(--text-secondary);">Batch ID: ${result.batch_id || '-'}</small>`);

                    // Langsung perbarui badge di semua card secara realtime
                    const todayStr = new Date().toLocaleDateString('id-ID');
                    items.forEach(it => {
                        markFlowAsSentInUI(it.nomorKontainer, it.kodeKegiatan, todayStr);
                    });
                    
                    // Pastikan beralih ke filter 'all' (Semua Alur) agar alur tetap tampil dengan badge ✅ Terkirim
                    activeFlowFilter = 'all';
                    $('#btn-filter-all').addClass('active');
                    $('#btn-filter-unsent').removeClass('active');

                    Swal.fire({
                        title: '🎉 Batch Tracking Berhasil!',
                        html: `<div style="text-align:left; font-size:13.5px;"><p>${result.total_sent || items.length} alur operasional dari ${uniqueConts.length} kontainer berhasil direkam di CEISA 4.0.</p><p style="color:var(--text-secondary);">Batch ID: <code>${result.batch_id || '-'}</code></p></div>`,
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: '📊 Buka Laporan Batch',
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#10b981'
                    }).then(r => { if (r.isConfirmed) window.location.href = 'report_tracking_batch.php'; });

                    showToast(`Batch ${items.length} alur berhasil dikirim!`, 'success');
                    // Refresh data alur kontainer dari database
                    await refreshBatchContainersData();

                } else if (isConflict) {
                    badge.css({ background: 'rgba(245, 158, 11, 0.18)', color: '#f59e0b', border: '1px solid #f59e0b' }).text(`HTTP 409 CONFLICT`);
                    $('#batch-result-msg').html(`⚠️ <b>Data Duplikat Ditolak CEISA 4.0:</b> ${ceisaRaw.detail || 'Terdapat alur kontainer yang sudah pernah dikirim sebelumnya.'}`);

                    const dupList = ceisaRaw.data?.duplikat || [];
                    const dupContMap = {};
                    dupList.forEach(d => {
                        const c = (d.nomorKontainer || '').replace(/[\s\-]/g, '').toUpperCase();
                        if (c) dupContMap[c] = d;
                    });

                    Swal.fire({
                        title: '⚠️ CEISA 4.0: Data Duplikat Ditemukan!',
                        html: `
                            <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                                <div style="background:rgba(239, 68, 68, 0.12); border:1px solid rgba(239, 68, 68, 0.35); border-radius:8px; padding:12px; margin-bottom:12px;">
                                    <div style="font-weight:700; color:#ef4444; font-size:14px; margin-bottom:4px;">HTTP 409 Conflict — Data Already Exists</div>
                                    <div style="color:var(--text-primary); margin-bottom:8px;">${ceisaRaw.detail || 'Sesuai aturan CEISA 4.0, jika ada 1 saja data duplikat maka seluruh batch ditolak (All-or-Nothing).'}</div>
                                </div>
                                <div style="margin-bottom:10px; font-size:12.5px; color:var(--text-secondary);">
                                    👉 Anda disarankan menggunakan tombol <b>Kirim Bertahap Satu per Satu</b> agar alur yang valid tetap berhasil terkirim dan alur yang 409 diisolasi.
                                </div>
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '⚡ Beralih ke Kirim Bertahap',
                        cancelButtonText: 'Tutup & Periksa Data',
                        confirmButtonColor: '#3b82f6'
                    }).then(actionRes => {
                        if (actionRes.isConfirmed) {
                            sendBatchSequentially();
                        }
                    });

                } else {
                    badge.addClass('badge-out').text(`HTTP ${result.code || 400} — FAILED`);
                    $('#batch-result-msg').html(`❌ <b>Gagal:</b> ${result.message || 'Pengiriman batch ditolak oleh gateway'}`);
                    Swal.fire({ title: 'Batch Tracking Gagal', html: `<p>${result.message || 'Ditolak oleh gateway'}</p>`, icon: 'error', confirmButtonColor: '#ef4444' });
                }

                $('#batch-result-time').text('Respon: ' + new Date().toLocaleTimeString('id-ID'));
                $('#batch-raw-response').text(JSON.stringify(ceisaRaw, null, 4)).show();

            } catch (err) {
                Swal.fire({ title: 'Kesalahan Sistem', text: err.message, icon: 'error' });
                showToast('Terjadi kesalahan jaringan: ' + err.message, 'error');
            } finally {
                btn.disabled = false; spinner.style.display = 'none'; icon.style.display = 'inline-block';
            }
        }

        // ===== PENGIRIMAN BATCH: METODE 2 (BERTAHAP SATU PER SATU SEPERTI single tracking) =====
        async function sendBatchSequentially() {
            const items = buildActiveBatchPayload();
            if (items.length === 0) {
                Swal.fire({ title: 'Tidak Ada Data', text: 'Pilih minimal 1 alur kontainer untuk dikirim.', icon: 'warning', confirmButtonColor: '#3b82f6' });
                return;
            }

            const uniqueConts = [...new Set(items.map(i => i.nomorKontainer))];

            const confirmRes = await Swal.fire({
                title: `Kirim ${items.length} Alur Bertahap`,
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.5;">
                        <p>Kirim <b>${items.length} alur operasional</b> dari <b>${uniqueConts.length} kontainer</b> satu per satu secara berurutan ke CEISA 4.0?</p>
                        <div style="background:rgba(59,130,246,0.08); border:1px solid rgba(59,130,246,0.25); border-radius:8px; padding:10px; margin-bottom:10px; font-size:12.5px;">
                            💡 <b>Kelebihan Kirim Bertahap:</b><br>
                            Tiap alur diproses independen. Alur baru akan langsung <b>✅ Sukses</b>, dan alur yang pernah dikirim akan ditandai <b>⚠️ 409 Sudah Pernah</b> tanpa menggugurkan alur lainnya.
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `🚀 Mulai Pengiriman (${items.length} Alur)`,
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
                        <div id="seq-prog-title" style="margin-bottom:8px; font-weight:600; color:var(--accent-blue,#3b82f6);">
                            Menyiapkan pengiriman alur...
                        </div>
                        <div style="background:#e2e8f0; border-radius:6px; height:10px; overflow:hidden; margin-bottom:12px;">
                            <div id="seq-prog-bar" style="background:linear-gradient(90deg, #3b82f6, #10b981); height:100%; width:0%; transition:width 0.3s ease;"></div>
                        </div>
                        <div id="seq-prog-logs" style="max-height:220px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px; padding:8px; background:var(--bg-card,#ffffff); font-size:12px; line-height:1.6;">
                        </div>
                    </div>
                `,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });

            let successCount = 0;
            let conflictCount = 0;
            let failCount = 0;

            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const percent = Math.round(((i) / items.length) * 100);

                $('#seq-prog-title').html(`Mengirim alur <b>${i + 1}</b> dari <b>${items.length}</b>: <b>${item.nomorKontainer}</b> (Kegiatan #${item.kodeKegiatan})...`);
                $('#seq-prog-bar').css('width', `${percent}%`);

                const logItem = $(`
                    <div id="seq-log-${i}" style="display:flex; justify-content:space-between; align-items:center; padding:4px 6px; border-bottom:1px dashed var(--border-subtle,#e2e8f0);">
                        <span><b>${item.nomorKontainer}</b> — Kegiatan #${item.kodeKegiatan}</span>
                        <span class="log-status" style="color:#64748b;">⏳ Mengirim...</span>
                    </div>
                `);
                $('#seq-prog-logs').append(logItem);
                $('#seq-prog-logs').scrollTop($('#seq-prog-logs')[0].scrollHeight);

                try {
                    const res = await fetch('api/tps_tracking.php?action=send', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ payload: item })
                    });
                    const result = await res.json();
                    const ceisaRaw = result.raw || result;
                    const isConflict = (result.code === 409 || ceisaRaw.code === 409 || ceisaRaw.result === 'Data already exists.' || (ceisaRaw.detail && ceisaRaw.detail.includes('sudah pernah')));

                    if (result.success) {
                        successCount++;
                        $(`#seq-log-${i} .log-status`).html('<b style="color:#10b981;">✅ Sukses</b>');
                    } else if (isConflict) {
                        conflictCount++;
                        $(`#seq-log-${i} .log-status`).html('<b style="color:#f59e0b;">⚠️ 409 Sudah Pernah</b>');
                    } else {
                        failCount++;
                        $(`#seq-log-${i} .log-status`).html('<b style="color:#ef4444;">❌ Gagal</b>');
                    }

                    // JIKA BERHASIL ATAU 409 (SUDAH PERNAH TERKIRIM), LANGSUNG UPDATE BADGE CARD MENJADI TERKIRIM!
                    if (result.success || isConflict) {
                        const todayStr = new Date().toLocaleDateString('id-ID');
                        markFlowAsSentInUI(item.nomorKontainer, item.kodeKegiatan, todayStr);
                    }
                } catch (err) {
                    failCount++;
                    $(`#seq-log-${i} .log-status`).html('<b style="color:#ef4444;">❌ Error</b>');
                }

                // Jeda 300ms antar pengiriman
                await new Promise(r => setTimeout(r, 300));
            }

            $('#seq-prog-bar').css('width', '100%');
            $('#seq-prog-title').html('<b>Pengiriman selesai!</b> Memperbarui data...');

            // Otomatis beralih ke filter 'all' (Semua Alur) agar user langsung melihat alur dengan badge ✅ Terkirim
            activeFlowFilter = 'all';
            $('#btn-filter-all').addClass('active');
            $('#btn-filter-unsent').removeClass('active');

            // Segarkan status data alur dari server dan render ulang seluruh card
            await refreshBatchContainersData();

            // Tampilkan dialog hasil akhir
            Swal.fire({
                title: 'Hasil Pengiriman Bertahap',
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                        <p style="margin-bottom:10px;">Proses pengiriman seluruh alur kontainer telah selesai.</p>
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
                icon: failCount > 0 ? (successCount > 0 ? 'info' : 'error') : 'success',
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Tutup'
            });
        }

        // Quick Send Single Flow directly
        async function quickSendSingleFlow(cNo, kdKeg) {
            const cData = loadedContainersData[cNo];
            if (!cData || !cData.flows) return;
            const step = cData.flows.find(x => x.kodeKegiatan == kdKeg);
            if (!step || !step.payload) return;

            const confirmRes = await Swal.fire({
                title: 'Kirim Alur Ini ke CEISA 4.0?',
                html: `
                    <div style="text-align:left; font-size:13.5px; line-height:1.6;">
                        <p>Kirim pergerakan <b>${step.kegiatanLabel}</b> untuk kontainer <b>${cNo}</b>?</p>
                        <div style="background:rgba(0,0,0,0.15); border:1px solid var(--border-medium); border-radius:8px; padding:10px;">
                            <div>⏱️ <b>Waktu:</b> ${step.waktuKegiatan}</div>
                            ${step.nopolLabel ? `<div>🚚 <b>Armada:</b> ${step.nopolLabel}</div>` : ''}
                            ${step.dokumenLabel ? `<div>📑 <b>Dokumen:</b> ${step.dokumenLabel}</div>` : ''}
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '🚀 Ya, Kirim Sekarang',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                reverseButtons: true
            });

            if (!confirmRes.isConfirmed) return;

            Swal.fire({ title: 'Mengirim...', didOpen: () => Swal.showLoading() });

            try {
                const res = await fetch('api/tps_tracking.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ payload: step.payload })
                });
                const result = await res.json();
                const isConflict = (result.code === 409 || (result.raw && result.raw.code === 409));

                if (result.success || isConflict) {
                    const todayStr = new Date().toLocaleDateString('id-ID');
                    markFlowAsSentInUI(cNo, kdKeg, todayStr);

                    // Pastikan beralih ke filter 'all' (Semua Alur) agar alur tetap terlihat
                    activeFlowFilter = 'all';
                    $('#btn-filter-all').addClass('active');
                    $('#btn-filter-unsent').removeClass('active');

                    if (result.success) {
                        Swal.fire({ title: 'Terkirim!', text: `Alur ${step.kegiatanLabel} kontainer ${cNo} berhasil direkam di CEISA.`, icon: 'success', confirmButtonColor: '#10b981' });
                    } else {
                        Swal.fire({ title: 'Sudah Pernah Terkirim (409)', text: 'Data alur ini sudah tercatat sebelumnya di CEISA 4.0.', icon: 'info', confirmButtonColor: '#f59e0b' });
                    }

                    await refreshBatchContainersData([cNo]);
                } else {
                    Swal.fire({ title: 'Gagal', text: result.message || 'Pengiriman ditolak gateway', icon: 'error' });
                }
            } catch (e) {
                Swal.fire({ title: 'Error', text: e.message, icon: 'error' });
            }
        }

        // Trigger batch send from header buttons
        function triggerBatchSend(mode) {
            // Set flow filter based on mode
            if (mode === 'unsent') {
                // Centang hanya yang belum terkirim, uncheck yang sudah terkirim
                selectedContainers.forEach(cNo => {
                    const cData = loadedContainersData[cNo];
                    if (cData && cData.flows) {
                        cData.flows.forEach(f => f._checked = !f.is_sent);
                    }
                });
            } else {
                // Centang semua
                selectedContainers.forEach(cNo => {
                    const cData = loadedContainersData[cNo];
                    if (cData && cData.flows) {
                        cData.flows.forEach(f => f._checked = true);
                    }
                });
            }
            renderBatchUI();
            updateBatchJsonPreview();

            // Jalankan pengiriman bertahap (paling aman & transparan)
            sendBatchSequentially();
        }

        // ===== MODAL 1: TARIK DARI DATABASE PLP / GUDANG =====
        function openPlpModal() {
            document.getElementById('modal-plp-picker').style.display = 'flex';
            loadPlpContainers();
        }
        function closePlpModal() {
            document.getElementById('modal-plp-picker').style.display = 'none';
        }

        function debouncePlpSearch() {
            clearTimeout(plpSearchTimeout);
            plpSearchTimeout = setTimeout(() => {
                const q = document.getElementById('plp-search-input').value;
                loadPlpContainers(q);
            }, 300);
        }

        async function loadPlpContainers(q = '') {
            document.getElementById('plp-loading').style.display = 'block';
            document.getElementById('tbody-plp-picker').innerHTML = '';
            document.getElementById('check-all-plp').checked = false;
            updatePlpSelectedCount();

            try {
                const res = await fetch(`api/tps_tracking_batch.php?action=search_containers&dept=${currentDept}&q=${encodeURIComponent(q)}`);
                const data = await res.json();
                document.getElementById('plp-loading').style.display = 'none';
                plpLoadedData = data.results || [];
                renderPlpRows();
            } catch(e) {
                document.getElementById('plp-loading').style.display = 'none';
                document.getElementById('tbody-plp-picker').innerHTML = `<tr><td colspan="9" style="text-align:center; color:#ef4444; padding:20px;">Gagal memuat: ${e.message}</td></tr>`;
            }
        }

        function renderPlpRows() {
            const tbody = document.getElementById('tbody-plp-picker');
            tbody.innerHTML = '';
            document.getElementById('check-all-plp').checked = false;
            updatePlpSelectedCount();

            const hideSent = document.getElementById('filter-hide-sent')?.checked || false;
            let displayData = plpLoadedData;
            if (hideSent) {
                displayData = plpLoadedData.filter(item => !item.already_sent);
            }

            if (displayData.length === 0) {
                const dbName = currentDept === 'gudang' ? 'Gudang (primamas)' : 'PLP (tppcontplp)';
                tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:24px; color:var(--text-secondary);">Tidak ada data kontainer ditemukan ${hideSent ? '(semua kontainer sudah pernah dikirim)' : 'di database ' + dbName}</td></tr>`;
                return;
            }

            displayData.forEach((item, i) => {
                const isEmp = item.status === 'EMPTY';
                const isSent = item.already_sent;
                const yard = [item.yard_block, item.slot ? 'S:'+item.slot : '', item.tier ? 'T:'+item.tier : ''].filter(Boolean).join(' ');
                const isAlreadySelected = selectedContainers.includes(item.container_no);

                const tr = document.createElement('tr');
                if (isSent) tr.style.opacity = '0.78';
                tr.innerHTML = `
                    <td style="text-align:center;">
                        <input type="checkbox" class="plp-chk" data-cont="${item.container_no}" ${isAlreadySelected ? 'checked' : ''} onchange="updatePlpSelectedCount()">
                    </td>
                    <td><strong style="font-family:'JetBrains Mono',monospace; color:var(--text-primary); font-size:0.88rem;">${item.container_no}</strong></td>
                    <td>${item.size_type || '40'}</td>
                    <td><span class="badge-pill ${isEmp ? 'badge-out' : 'badge-in'}" style="font-size:10px;">${item.status || 'FCL'}</span></td>
                    <td>
                        ${isSent 
                            ? `<span class="badge-pill" style="background:rgba(245,158,11,0.18); color:#f59e0b; border:1px solid rgba(245,158,11,0.35); font-size:10px;">⚠️ Terkirim</span>` 
                            : `<span class="badge-pill" style="background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3); font-size:10px;">✓ Belum</span>`}
                    </td>
                    <td><span style="font-family:'JetBrains Mono',monospace; font-size:11px;">${yard || '-'}</span></td>
                    <td><b>${item.nopol || '-'}</b></td>
                    <td><small style="color:var(--text-secondary);">${item.no_bl || '-'}</small></td>
                    <td><small style="color:var(--text-secondary);">${item.no_dokumen || '-'}</small></td>
                `;
                tbody.appendChild(tr);
            });
            updatePlpSelectedCount();
        }

        function toggleSelectAllPlp(master) {
            const chks = document.querySelectorAll('.plp-chk');
            chks.forEach(c => c.checked = master.checked);
            updatePlpSelectedCount();
        }

        function updatePlpSelectedCount() {
            const checked = document.querySelectorAll('.plp-chk:checked').length;
            $('#plp-selected-count').text(checked);
            $('#btn-count-label').text(checked);
        }

        function addSelectedFromPlpModal() {
            const chks = document.querySelectorAll('.plp-chk:checked');
            if (chks.length === 0) {
                showToast('Pilih minimal 1 kontainer dari daftar', 'error');
                return;
            }

            const newConts = [];
            chks.forEach(c => {
                const cont = c.dataset.cont;
                if (cont && !newConts.includes(cont)) newConts.push(cont);
            });

            // Update select2
            const currentSelected = $('#select-containers').val() || [];
            const merged = [...new Set([...currentSelected, ...newConts])];

            // Tambahkan option ke select2 jika belum ada
            merged.forEach(c => {
                if ($('#select-containers').find(`option[value='${c}']`).length === 0) {
                    const opt = new Option(c, c, true, true);
                    $('#select-containers').append(opt);
                }
            });

            $('#select-containers').val(merged).trigger('change');
            closePlpModal();
            showToast(`${newConts.length} kontainer ditambahkan ke batch tracking!`, 'success');
        }

        // ===== MODAL 2: BULK PASTE CONTAINERS =====
        function openPasteModal() {
            document.getElementById('modal-paste-conts').style.display = 'flex';
            document.getElementById('paste-conts-textarea').value = '';
            document.getElementById('paste-conts-textarea').focus();
        }
        function closePasteModal() {
            document.getElementById('modal-paste-conts').style.display = 'none';
        }

        function processPastedContainers() {
            const text = document.getElementById('paste-conts-textarea').value || '';
            const rawTokens = text.split(/[\r\n,;\s]+/);
            const validConts = [];

            rawTokens.forEach(t => {
                const clean = t.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                if (clean.length >= 4 && !validConts.includes(clean)) {
                    validConts.push(clean);
                }
            });

            if (validConts.length === 0) {
                showToast('Tidak ada nomor kontainer valid yang ditemukan dari teks yang ditempel', 'error');
                return;
            }

            const currentSelected = $('#select-containers').val() || [];
            const merged = [...new Set([...currentSelected, ...validConts])];

            merged.forEach(c => {
                if ($('#select-containers').find(`option[value='${c}']`).length === 0) {
                    const opt = new Option(c, c, true, true);
                    $('#select-containers').append(opt);
                }
            });

            $('#select-containers').val(merged).trigger('change');
            closePasteModal();
            showToast(`${validConts.length} kontainer berhasil diproses dari teks tempelan!`, 'success');
        }

        // Init Document
        $(document).ready(function() {
            initSelect2();

            // Theme toggle
            const themeBtn = document.getElementById('theme-toggle');
            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    const cur = document.documentElement.getAttribute('data-theme') || 'dark';
                    const next = cur === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('ceisa_theme', next);
                    const ic = document.querySelector('.theme-toggle-icon');
                    const tx = document.querySelector('.theme-toggle-text');
                    if (ic) ic.textContent = next === 'dark' ? '🌙' : '☀️';
                    if (tx) tx.textContent = next === 'dark' ? 'Dark' : 'Light';
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
