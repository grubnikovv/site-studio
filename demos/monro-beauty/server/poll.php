<?php
// Опрос Telegram без https. Лучше всего — cron каждую минуту: php ~/public_html/server/poll.php
// Без cron можно открыть в браузере poll.php?key=admin_key — бот ответит на всё, что ему написали.
// Заодно отправляет напоминания (cron.php).
require __DIR__ . '/bot.php';
if (PHP_SAPI !== 'cli' && ($_GET['key'] ?? '') !== cfg('admin_key')) { http_response_code(403); exit; }
@set_time_limit(90);
if (isset($_GET['diag'])) {  // проверка связи: poll.php?key=...&diag=1
    header('Content-Type: text/plain; charset=utf-8');
    $t = microtime(true); $me = tg('getMe');
    echo 'Telegram: ' . (!empty($me['ok']) ? 'связь есть, бот @' . $me['result']['username'] : 'нет связи') . ' (' . round(microtime(true) - $t, 1) . " с)\n";
    $w = tg('getWebhookInfo'); echo 'Вебхук: ' . json_encode($w['result'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
    echo 'PHP ' . PHP_VERSION . ', curl ' . (curl_version()['version'] ?? '?') . "\n";
    $q = db()->query('SELECT COUNT(*) FROM admins'); echo 'Подключено админов: ' . $q->fetchColumn() . "\n";
    $bk = glob(dirname(cfg('db_path')) . '/backups/ma-*.sqlite') ?: []; echo 'Резервных копий: ' . count($bk) . ($bk ? ', последняя ' . basename(end($bk)) . ' (' . round(filesize(end($bk)) / 1024) . ' КБ)' : '') . "\n";
    $log = @file(dirname(cfg('db_path')) . '/bot.log') ?: [];
    echo "Журнал (последнее):\n" . preg_replace('/bot\d+:[\w-]+/', 'bot***', implode('', array_slice($log, -8)));
    exit;
}
poll_run(PHP_SAPI === 'cli' ? (int)(getenv('POLL_SECS') ?: 50) : 20);
// просроченные брони обучения и сертификаты — удалить, даже если сайт никто не открывал
require_once __DIR__ . '/store.php';
[$doc, $v] = doc_load(true);
if (doc_expire($doc)) doc_save($doc, $v); else doc_abort();
include __DIR__ . '/cron.php';
require_once __DIR__ . '/backup.php'; backup_run(); mail_nag_run();  // ночная резервная копия базы
if (PHP_SAPI !== 'cli') echo "Готово: сообщения боту обработаны.\n";
