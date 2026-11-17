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
    echo 'Почта: ' . (cfg('smtp_pass') ? 'через ящик ' . cfg('mail_from') . ' (SMTP, с подписью DKIM)' : 'через mail() хостинга — пароль ящика не задан (секрет MAIL_PASS)') . "\n";
    try {  // база и последняя резервная копия: целые ли, сколько кабинетов и записей
        require_once __DIR__ . '/backup.php';
        $live = backup_check(cfg('db_path')); $last = json_decode((string)kv_get('backup_last'), true) ?: [];
        echo 'База: ' . ($live['ok'] ? 'проверка пройдена' : 'ОШИБКА ПРОВЕРКИ') . ', личных кабинетов ' . $live['acc'] . ', записей ' . $live['book']
            . ($last ? '; последняя копия ' . $last['day'] . ': кабинетов ' . $last['acc'] . ', записей ' . $last['book'] : '') . ', фото в копии: ' . count(glob(backup_dir() . '/av/*') ?: []) . "\n";
    } catch (Throwable $e) { echo 'База: ошибка ' . $e->getMessage() . "\n"; }
    try {  // фото профиля: сколько в базе, есть ли файлы, открываются ли
        require_once __DIR__ . '/store.php';
        $avs = client_db()->query("SELECT av FROM accounts WHERE av <> '' AND av IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(av_dir() . '/*') ?: [];
        echo 'Фото профиля: в базе ' . count($avs) . ', файлов ' . count($files) . ', GD ' . (function_exists('imagecreatefromstring') ? 'есть' : 'НЕТ') . ', WebP ' . (function_exists('imagewebp') ? 'есть' : 'нет');
        if ($avs) { $f = av_dir() . '/' . $avs[0]; echo ', первый файл ' . (is_file($f) ? 'на месте (' . round(filesize($f) / 1024) . ' КБ)' : 'НЕ НАЙДЕН'); }
        if ($avs) {  // открывается ли по ссылке и сколько записей найдено для этой клиентки
            foreach (['https://' . parse_url((string)cfg('site_url'), PHP_URL_HOST), 'http://' . ($_SERVER['HTTP_HOST'] ?? '')] as $base) {
                $ch = curl_init($base . '/server/av.php?f=' . str_replace('.', '_t.', $avs[0])); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_NOBODY => false]);
                $body = curl_exec($ch); echo ', ' . parse_url($base, PHP_URL_SCHEME) . ': код ' . curl_getinfo($ch, CURLINFO_RESPONSE_CODE) . ' ' . curl_getinfo($ch, CURLINFO_CONTENT_TYPE) . ' ' . strlen((string)$body) . ' б'; curl_close($ch);
            }
            $a = client_db()->query("SELECT email, phone FROM accounts WHERE av <> '' LIMIT 1")->fetch(); [$doc] = doc_load(false); $hit = 0; $why = [];
            foreach ($doc['bookings'] as $b) {
                $byE = mb_strtolower((string)($b['email'] ?? '')) === mb_strtolower($a['email']) || mb_strtolower((string)($b['accMail'] ?? '')) === mb_strtolower($a['email']);
                $byP = $a['phone'] && digits_phone($b['phone'] ?? '') === $a['phone'];
                if ($byE || $byP) { $hit++; $why[] = $byE ? 'почта' : 'телефон'; }
            }
            echo ', записей этой клиентки: ' . $hit . ($why ? ' (' . implode(', ', array_unique($why)) . ')' : '') . ', телефон в аккаунте ' . ($a['phone'] ? 'есть' : 'нет');
        }
        echo "\n";
    } catch (Throwable $e) { echo 'Фото профиля: ошибка ' . $e->getMessage() . "\n"; }
    echo 'PHP ' . PHP_VERSION . ', curl ' . (curl_version()['version'] ?? '?') . "\n";
    $q = db()->query('SELECT COUNT(*) FROM admins'); echo 'Подключено админов: ' . $q->fetchColumn() . "\n";
    $bk = glob(dirname(cfg('db_path')) . '/backups/ma-*.sqlite') ?: []; echo 'Резервных копий: ' . count($bk) . ($bk ? ', последняя ' . basename(end($bk)) . ' (' . round(filesize(end($bk)) / 1024) . ' КБ)' : '') . "\n";
    $log = @file(dirname(cfg('db_path')) . '/bot.log') ?: [];
    echo "Журнал (последнее):\n" . preg_replace('/bot\d+:[\w-]+/', 'bot***', implode('', array_slice($log, -8)));
    exit;
}
if (isset($_GET['testmail'])) {  // проверка почты через ящик: poll.php?key=...&testmail=1 — разговор с сервером и последние ошибки
    header('Content-Type: text/plain; charset=utf-8');
    require_once __DIR__ . '/store.php';
    $to = mail_admin() ?: (string)cfg('mail_from'); echo 'Кому: ' . preg_replace('/^(.).*(@.*)$/', '$1***$2', $to) . "\n";
    $GLOBALS['smtp_log'] = [];
    $r = smtp_send((string)cfg('mail_from'), $to, "To: <$to>\r\nSubject: =?UTF-8?B?" . base64_encode('Проверка почты сайта') . "?=\r\nFrom: <" . cfg('mail_from') . ">\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . '@' . substr(strrchr((string)cfg('mail_from'), '@'), 1) . ">\r\nDate: " . date('r') . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n", "Это проверочное письмо с сайта ma-nails.ru.\r\n");
    echo implode("\n", $GLOBALS['smtp_log']) . "\nИтог: " . ($r === true ? 'сервер принял письмо' : $r) . "\n";
    $log = @file(dirname(cfg('db_path')) . '/bot.log') ?: []; echo "Журнал почты:\n" . implode('', array_slice(preg_grep('/mail|smtp/i', $log), -8));
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
