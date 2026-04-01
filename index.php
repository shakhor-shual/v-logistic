<!DOCTYPE html>
<html lang="ru" data-i18n-title="app_title">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" data-i18n="app_title">
    <title>QR Code Scanner</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
    
    <style>
        /* Ваши стили остаются без изменений */
        body {
            font-family: "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #f4f7fb, #e9eef5);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .card {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        h1 {
            font-size: 20px;
            margin: 0;
            color: #1f2d3d;
        }
        
        .status {
            font-size: 14px;
            color: #555;
        }
        
        #reader {
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            display: none;
        }
        
        button {
            padding: 14px;
            font-size: 16px;
            background: #005bbb;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
            width: 100%;
        }
        
        button:hover {
            background: #004799;
        }
        
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        #result-box {
            padding: 12px;
            background: #f1f7ff;
            border-left: 4px solid #005bbb;
            border-radius: 8px;
            display: none;
            word-break: break-all;
            margin-top: 10px;
        }
        
        .toggle-debug {
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        #debug-console {
            display: none;
            background: #0f1720;
            color: #00ff9c;
            font-family: monospace;
            font-size: 12px;
            padding: 10px;
            border-radius: 8px;
            max-height: 150px;
            overflow-y: auto;
        }
        
        #activation-panel {
            display: none;
            flex-direction: column;
            gap: 12px;
        }
        
        #main-panel {
            display: none;
        }
        
        .info-text {
            text-align: center;
            color: #555;
            margin: 0;
            font-size: 14px;
        }
        
        .scan-count {
            font-size: 12px;
            color: #666;
            text-align: center;
            margin-top: 5px;
        }
        
        .language-selector {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 10px;
        }
        
        .lang-btn {
            padding: 5px 10px;
            font-size: 12px;
            width: auto;
            background: #e0e0e0;
            color: #333;
        }
        
        .lang-btn.active {
            background: #005bbb;
            color: white;
        }
    </style>
</head>
<body>

<div class="card">
    <h1 data-i18n="scan_title">Сканер кодов QR</h1>
    
    <div id="protocol-status" class="status" data-i18n="security_check">Проверка безопасности соединения...</div>
    
    <div id="activation-panel">
        <p class="info-text" data-i18n="activation_panel_info_text">Сканируйте QR-код с вашего служебного листка для активации устройства.</p>
        <button id="activate-btn" data-i18n="activation_panel_activate_button">Активировать устройство</button>
    </div>
    
    <div id="main-panel">
        <button id="start-btn" data-i18n="scanner_panel_start_button">Начать сканирование</button>
    </div>
    
    <div id="reader"></div>
    
    <div id="result-box">
        <strong data-i18n="result_last_scanned">Последний сканированный код:</strong><br>
        <span id="scanned-result"></span>
        <div class="scan-count" id="scan-count"></div>
    </div>
    
    <div class="language-selector">
        <button class="lang-btn" data-lang="pl" data-i18n="lang_pl">Polski</button>
        <button class="lang-btn" data-lang="en" data-i18n="lang_en">English</button>
        <button class="lang-btn" data-lang="ru" data-i18n="lang_ru">Русский</button>
        <button class="lang-btn" data-lang="uk" data-i18n="lang_uk">Українська</button>
    </div>
    
    <label class="toggle-debug">
        <input type="checkbox" id="toggle-debug">
        <span data-i18n="debug_show_logs">Показать логи системы</span>
    </label>
    
    <div id="debug-console" data-i18n-debug="system_log">--- ЛОГ СИСТЕМЫ ---<br></div>
</div>

<?php
// Добавляем timestamp для предотвращения кэширования
$i18nJsTime = filemtime('js/i18n.js');
$appJsTime = filemtime('js/app.js');
?>

<script src="js/i18n.js?v=<?php echo $i18nJsTime; ?>"></script>
<script>
// Инициализация и применение переводов
document.addEventListener('DOMContentLoaded', async () => {
    // Инициализируем i18n
    await i18n.init();
    
    // Применяем переводы к DOM
    i18n.updateDOM();
    
    // Настраиваем обработчики для кнопок языка
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const lang = btn.getAttribute('data-lang');
            await i18n.setLanguage(lang);
            i18n.updateDOM();
            
            // Обновляем активный класс кнопок
            document.querySelectorAll('.lang-btn').forEach(b => {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            
            // Обновляем текст чекбокса при смене языка
            updateCheckboxLabel();
        });
    });
    
    // Активируем кнопку текущего языка
    const currentLang = i18n.getCurrentLanguage();
    const activeBtn = document.querySelector(`.lang-btn[data-lang="${currentLang}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
    
    // Настройка обработчика для чекбокса
    const debugCheckbox = document.getElementById('toggle-debug');
    const debugConsole = document.getElementById('debug-console');
    
    function updateCheckboxLabel() {
        const isChecked = debugCheckbox.checked;
        const key = isChecked ? 'debug_hide_logs' : 'debug_show_logs';
        const labelSpan = document.querySelector('.toggle-debug span');
        if (labelSpan) {
            labelSpan.textContent = i18n.t(key);
        }
    }
    
    if (debugCheckbox) {
        debugCheckbox.addEventListener('change', () => {
            if (debugConsole) {
                debugConsole.style.display = debugCheckbox.checked ? 'block' : 'none';
            }
            updateCheckboxLabel();
        });
        
        // Инициализация отображения дебаг консоли
        if (debugConsole) {
            debugConsole.style.display = debugCheckbox.checked ? 'block' : 'none';
        }
        updateCheckboxLabel();
    }
    
    // Добавляем observer для обновления текста чекбокса при смене языка
    i18n.addObserver(() => {
        updateCheckboxLabel();
    });
});

// Добавляем метод t в глобальный объект для удобства использования в app.js
window.__ = (key, params = {}) => i18n.t(key, params);
</script>
<script src="js/app.js?v=<?php echo $appJsTime; ?>"></script>

</body>
</html>