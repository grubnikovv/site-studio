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
if (isset($_GET['testpush'])) {  // проверочное уведомление на телефоны мастера: poll.php?key=...&testpush=1
    header('Content-Type: text/plain; charset=utf-8');
    require_once __DIR__ . '/store.php'; require_once __DIR__ . '/push.php';
    [$doc, $v] = doc_load(true);
    doc_note($doc, '🔔', 'Проверка уведомлений', 'Всё работает! Так будут приходить новые записи, отмены и заявки.');
    doc_save($doc, $v);
    $subs = push_db()->query('SELECT endpoint, p256dh FROM push_subs WHERE acc = 0')->fetchAll();
    $t0 = now_ms();
    push_all(push_note('🔔 Проверка уведомлений', 'Всё работает! Так будут приходить новые записи, отмены и заявки.'));
    foreach ($subs as $r) echo 'Подписка ' . substr(md5($r['endpoint']), 0, 6) . ': ' . ($r['p256dh'] ? 'текст внутри пуша (зашифрован)' : 'старая, без ключей — пустой пуш') . "\n";
    if (!$subs) echo "Нет подписок мастера\n";
    sleep(25);  // успел ли браузер «проснуться» и спросить, что показать (push_peek обновляет seen)
    foreach (push_db()->query('SELECT endpoint, created, seen, ua, p256dh FROM push_subs WHERE acc = 0') as $r)
        echo 'Подписка от ' . date('d.m H:i', (int)$r['created']) . ' (' . (preg_match('/YaBrowser|Android|Windows|Mac|iPhone|Chrome/', $r['ua'], $m) ? $m[0] : '?') . ', ' . substr(md5($r['endpoint']), 0, 6) . '): '
            . ($r['p256dh'] ? 'текст внутри пуша — браузеру не нужно обращаться к сайту' : ((int)$r['seen'] >= $t0 ? 'браузер проснулся и забрал уведомление' : 'браузер НЕ обратился к сайту')) . "\n";
    $log = @file(dirname(cfg('db_path')) . '/bot.log') ?: []; echo implode('', array_slice(preg_grep('/push/', $log), -5));
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
