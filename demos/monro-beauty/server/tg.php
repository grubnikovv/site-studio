<?php
// Вебхук Telegram (если на сайте есть https): сюда Telegram присылает сообщения и нажатия кнопок бота.
require __DIR__ . '/bot.php';
// Запрос должен прийти от Telegram с нашим секретом (задаётся в setup.php)
if (($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '') !== substr(hash('sha256', cfg('admin_key') . 'wh'), 0, 32)) { http_response_code(403); exit; }
handle_update(json_decode(file_get_contents('php://input'), true) ?: []);
