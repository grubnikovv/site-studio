<?php
// Общие функции бота студии «М.А»: настройки, база, Telegram, даты.

$CFG = require __DIR__ . '/config.php';
date_default_timezone_set($CFG['tz']);

function cfg($k) { global $CFG; return $CFG[$k] ?? null; }

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = cfg('db_path');
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('CREATE TABLE IF NOT EXISTS bookings (
        id TEXT PRIMARY KEY, date TEXT, start INTEGER, dur INTEGER, svc TEXT, coat INTEGER DEFAULT 0,
        name TEXT, phone TEXT, status TEXT DEFAULT "booked", remind TEXT, chat_id INTEGER,
        confirmed INTEGER DEFAULT 0, sent_2d INTEGER DEFAULT 0, sent_2h INTEGER DEFAULT 0, sent_corr INTEGER DEFAULT 0,
        created INTEGER, updated INTEGER)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS admins (chat_id INTEGER PRIMARY KEY, name TEXT, added INTEGER)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS hits (ip TEXT, t INTEGER)');
    try { $pdo->exec('ALTER TABLE bookings ADD COLUMN email TEXT'); } catch (Throwable $e) {}  // для напоминаний на почту
    $pdo->exec('CREATE TABLE IF NOT EXISTS outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, payload TEXT, t INTEGER, tries INTEGER DEFAULT 0)');
    return $pdo;
}

// Вызов Telegram Bot API. Ошибки пишем в лог, но не роняем страницу.
function tg($method, $params = []) {
    $url = rtrim(cfg('tg_api'), '/') . '/bot' . cfg('bot_token') . '/' . $method;
    // Связь с Telegram из России бывает нестабильной: до 4 попыток, быстрый таймаут подключения, только IPv4
    for ($try = 1; $try <= 4; $try++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => ($method === 'getUpdates' ? 40 : 15), CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch); $connected = curl_getinfo($ch, CURLINFO_CONNECT_TIME) > 0;
        curl_close($ch);
        if ($res !== false || $connected) break;  // повторяем только если не удалось подключиться
    }
    $ok = $res !== false && (json_decode($res, true)['ok'] ?? false);
    if (!$ok) logline('tg ' . $method . ' failed (попыток ' . $try . '): ' . ($res === false ? $err : $res));
    return $res === false ? null : json_decode($res, true);
}

function logline($s) { @file_put_contents(dirname(cfg('db_path')) . '/bot.log', date('Y-m-d H:i:s ') . $s . "\n", FILE_APPEND); }

function send($chat, $text, $buttons = null) {
    $p = ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($buttons) $p['reply_markup'] = ['inline_keyboard' => $buttons];
    $r = tg('sendMessage', $p);
    // нет связи с Telegram — сообщение в очередь, poll.php отправит его в следующую минуту
    if ($r === null) db()->prepare('INSERT INTO outbox (payload, t) VALUES (?, ?)')->execute([json_encode($p, JSON_UNESCAPED_UNICODE), time()]);
    return $r;
}

// Отправить то, что не ушло из-за сбоя связи (сообщения старше суток уже не нужны)
function flush_outbox() {
    $db = db();
    $db->prepare('DELETE FROM outbox WHERE t < ? OR tries >= 30')->execute([time() - 86400]);
    foreach ($db->query('SELECT * FROM outbox ORDER BY id LIMIT 20')->fetchAll() as $m) {
        $r = tg('sendMessage', json_decode($m['payload'], true));
        if ($r === null) { $db->prepare('UPDATE outbox SET tries = tries + 1 WHERE id = ?')->execute([$m['id']]); break; }
        $db->prepare('DELETE FROM outbox WHERE id = ?')->execute([$m['id']]);
    }
}

// Сообщение Марине (во все подключённые чаты мастера) и копия на почту студии
function notify_admins($text) {
    foreach (db()->query('SELECT chat_id FROM admins') as $a) send($a['chat_id'], $text);
    if (cfg('mail_admin')) send_mail(cfg('mail_admin'), strip_tags(strtok($text, "\n")), $text);
}

// ---------- почта ----------
// Письмо от студии. Текст — как в Telegram (<b>, <a>, переносы строк), оборачиваем в простой фирменный шаблон.
function send_mail($to, $subject, $text) {
    $from = (string)cfg('mail_from');
    if (!$from || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $site = rtrim((string)cfg('site_url'), '/');
    $body = '<!doctype html><html><body style="margin:0;background:#f6f1ef;font-family:Arial,sans-serif;color:#1c1416">'
        . '<div style="max-width:520px;margin:0 auto;padding:24px 16px">'
        . '<div style="font-family:Georgia,serif;font-size:22px;color:#9b1f45;margin-bottom:16px">М.А — академия маникюра и педикюра</div>'
        . '<div style="background:#fff;border-radius:16px;padding:22px 20px;font-size:16px;line-height:1.55">' . str_replace('<a href=', '<a style="color:#9b1f45" href=', nl2br($text)) . '</div>'
        . '<div style="font-size:13px;color:#7a6a6e;margin-top:14px;line-height:1.5">Михайловка, ул. Обороны, 41 · +7 995 428-47-88<br>'
        . '<a href="' . h($site) . '/" style="color:#9b1f45">' . h(preg_replace('#^https?://#', '', $site)) . '</a></div></div></body></html>';
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n"
        . 'From: =?UTF-8?B?' . base64_encode('Студия «М.А»') . "?= <$from>\r\nReply-To: $from\r\n";
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', chunk_split(base64_encode($body)), $headers, '-f' . $from);
    if (!$ok) logline('mail to ' . $to . ' failed');
    return $ok;
}

// Сообщение клиентке о её записи: в Telegram, если бот подключён, иначе на почту, если выбрана почта
function notify_client($b, $subject, $text, $buttons = null) {
    if (!empty($b['chat_id'])) return send($b['chat_id'], $text, $buttons);
    if (($b['remind'] ?? '') === 'mail' && !empty($b['email'])) return send_mail($b['email'], $subject, $text);
    return null;
}

// Ссылка на видео «как пройти» (страница сайта откроет его сама)
function way_link() { return '<a href="' . h(rtrim((string)cfg('site_url'), '/') . '/#kak-proiti') . '">🎬 Как пройти — видео</a>'; }

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hm($m) { return intdiv($m, 60) . ':' . str_pad($m % 60, 2, '0', STR_PAD_LEFT); }

// «понедельник, 16 ноября, 10:00»
function when_text($date, $start) {
    $wd = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];
    $mo = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $t = strtotime($date . ' 12:00');
    return $wd[(int)date('w', $t)] . ', ' . (int)date('j', $t) . ' ' . $mo[(int)date('n', $t)] . ', ' . hm((int)$start);
}
function booking_ts($b) { return strtotime($b['date'] . ' 00:00') + (int)$b['start'] * 60; }

function phone_ok($p) {
    $d = preg_replace('/\D/', '', (string)$p);
    if (strlen($d) === 11 && $d[0] === '8') $d = '7' . substr($d, 1);
    if (strlen($d) === 10 && $d[0] === '9') $d = '7' . $d;
    return preg_match('/^79\d{9}$/', $d) ? $d : '';
}

// +7 900 111-22-33
function phone_nice($d) { return '+7 ' . substr($d, 1, 3) . ' ' . substr($d, 4, 3) . '-' . substr($d, 7, 2) . '-' . substr($d, 9); }

// Отдать ответ браузеру сразу, а скрипт пусть ещё поработает (например, послушает Telegram)
function reply_then_continue($data) {
    ignore_user_abort(true); @set_time_limit(90);
    $body = json_encode($data, JSON_UNESCAPED_UNICODE);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . strlen($body)); header('Connection: close');
    echo $body;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { while (ob_get_level()) ob_end_flush(); flush(); }
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
