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
    return tg('sendMessage', $p);
}

// Сообщение Марине (во все подключённые чаты мастера)
function notify_admins($text) {
    foreach (db()->query('SELECT chat_id FROM admins') as $a) send($a['chat_id'], $text);
}

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
