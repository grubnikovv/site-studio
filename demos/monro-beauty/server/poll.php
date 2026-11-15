<?php
// Опрос Telegram без https. Лучше всего — cron каждую минуту: php ~/public_html/server/poll.php
// Без cron можно открыть в браузере poll.php?key=admin_key — бот ответит на всё, что ему написали.
// Заодно отправляет напоминания (cron.php).
require __DIR__ . '/bot.php';
if (PHP_SAPI !== 'cli' && ($_GET['key'] ?? '') !== cfg('admin_key')) { http_response_code(403); exit; }
@set_time_limit(90);
poll_run(PHP_SAPI === 'cli' ? (int)(getenv('POLL_SECS') ?: 50) : 20);
include __DIR__ . '/cron.php';
if (PHP_SAPI !== 'cli') echo "Готово: сообщения боту обработаны.\n";
