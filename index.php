<!DOCTYPE html>
<html lang="ru" data-i18n-title="app_title">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="description" data-i18n="app_title">
    <title>Ventura Scan - Logistics Scanner</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        
        .app-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 28px;
            box-shadow: 0 8px 24px rgba(0, 44, 75, 0.12);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #005f8c 0%, #0a7b9e 100%);
            padding: 20px 24px;
            text-align: center;
        }
        
        .card-header h1 {
            color: white;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        
        .card-header .subtitle {
            color: rgba(255, 255, 255, 0.85);
            font-size: 14px;
            margin-top: 6px;
        }
        
        .card-content {
            padding: 24px;
        }
        
        /* Панель активации */
        .activation-panel {
            text-align: center;
            padding: 8px 0 16px;
        }
        
        .activation-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e6f0ff 0%, #d4e4fc 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        
        .activation-icon svg {
            width: 44px;
            height: 44px;
        }
        
        .activation-title {
            font-size: 20px;
            font-weight: 700;
            color: #005f8c;
            margin-bottom: 8px;
        }
        
        .activation-desc {
            font-size: 15px;
            color: #5a6e7c;
            margin-bottom: 24px;
            line-height: 1.4;
        }
        
        /* Кнопки */
        .btn {
            border: none;
            border-radius: 60px;
            padding: 16px 24px;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #005f8c 0%, #0a7b9e 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(0, 95, 140, 0.3);
        }
        
        .btn-primary:active {
            transform: scale(0.98);
        }
        
        .btn-secondary {
            background: #eef2f7;
            color: #005f8c;
            border: 1px solid #cbd5e1;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        
        .btn-group .btn {
            flex: 1;
        }
        
        /* ВАЖНО: reader на том же уровне, как в рабочей версии */
        #reader {
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            display: none;
            background: #000;
            margin: 16px 0;
        }
        
        /* Результат сканирования */
        .result-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            margin-top: 16px;
            border-left: 5px solid #0a7b9e;
            display: none;
        }
        
        .result-label {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            color: #5a6e7c;
            margin-bottom: 8px;
        }
        
        .result-code {
            font-size: 24px;
            font-weight: 700;
            font-family: monospace;
            color: #1e293b;
            word-break: break-all;
            background: white;
            padding: 16px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        
        .scan-stats {
            font-size: 14px;
            color: #5a6e7c;
            text-align: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }
        
        /* Кнопка выбора языка */
        .lang-toggle {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 48px;
            height: 48px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            z-index: 100;
        }
        
        .lang-panel {
            position: fixed;
            bottom: 84px;
            right: 24px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            padding: 12px;
            display: none;
            gap: 8px;
            flex-direction: column;
            z-index: 99;
        }
        
        .lang-panel.show {
            display: flex;
        }
        
        .lang-option {
            padding: 10px 20px;
            border: none;
            background: #f1f5f9;
            border-radius: 40px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .lang-option.active {
            background: #005f8c;
            color: white;
        }
        
        .debug-section {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            display: none;
        }
        
        .debug-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 12px;
            color: #94a3b8;
            cursor: pointer;
            padding: 8px;
        }
        
        #debug-console {
            background: #1e293b;
            color: #86efac;
            font-family: monospace;
            font-size: 11px;
            padding: 12px;
            border-radius: 12px;
            max-height: 150px;
            overflow-y: auto;
            margin-top: 12px;
            display: none;
        }
        
        @media (max-width: 480px) {
            .result-code {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

<div class="app-container">
    <div class="card">
        <div class="card-header">
            <h1>VENTURE GROUP</h1>
            <div class="subtitle" data-i18n="header_subtitle">Logistics Scanner</div>
        </div>
        
        <div class="card-content">
            <!-- Панель активации -->
            <div id="activation-panel">
                <div class="activation-panel">
                    <div class="activation-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#005f8c" stroke-width="1.5">
                            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke="currentColor"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor"/>
                        </svg>
                    </div>
                    <div class="activation-title" data-i18n="activation_title">Register Device</div>
                    <div class="activation-desc" data-i18n="activation_desc">Scan the QR code from your service sheet to register this device</div>
                    <button class="btn btn-primary" id="activate-btn" data-i18n="activation_btn">Register Device</button>
                </div>
            </div>
            
            <!-- Панель сканирования -->
            <div id="main-panel" style="display: none;">
                <button class="btn btn-primary" id="start-btn" data-i18n="scan_start_btn">Start Scanning</button>
            </div>
            
            <!-- ВАЖНО: reader на том же уровне, как в рабочей версии -->
            <div id="reader"></div>
            
            <div id="result-box" class="result-card">
                <div class="result-label" data-i18n="scanned_code_label">SCANNED CODE</div>
                <div class="result-code" id="scanned-result"></div>
                <div class="btn-group" id="action-buttons" style="display: none;">
                    <button class="btn btn-primary" id="save-btn" data-i18n="save_btn">✓ Save to Database</button>
                    <button class="btn btn-secondary" id="cancel-btn" data-i18n="cancel_btn">✗ Cancel</button>
                </div>
                <div class="scan-stats" id="scan-count"></div>
            </div>
        </div>
    </div>
    
    <button class="lang-toggle" id="lang-toggle-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
            <circle cx="12" cy="12" r="10"/>
            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
        </svg>
    </button>
    
    <div class="lang-panel" id="lang-panel">
        <button class="lang-option" data-lang="pl">Polski</button>
        <button class="lang-option" data-lang="en">English</button>        
        <button class="lang-option" data-lang="ru">Русский</button>
        <button class="lang-option" data-lang="uk">Українська</button>
        <button class="lang-option" data-lang="ka">ქართული</button>
    </div>
    
    <div class="debug-section">
        <div class="debug-toggle" id="debug-toggle-trigger">
            <span>🔧</span>
            <span data-i18n="debug_show">System Info</span>
        </div>
        <div id="debug-console"></div>
    </div>
</div>

<?php
$i18nJsTime = filemtime('js/i18n.js');
$appJsTime = filemtime('js/app.js');
?>

<script src="js/i18n.js?v=<?php echo $i18nJsTime; ?>"></script>
<script src="js/app.js?v=<?php echo $appJsTime; ?>"></script>

</body>
</html>