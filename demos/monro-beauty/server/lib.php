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
    if ($m = mail_admin()) send_mail($m, strip_tags(strtok($text, "\n")), $text);
    require_once __DIR__ . '/push.php';  // уведомление в браузере на телефонах мастера
    $lines = explode("\n", $text, 2); push_all(push_note($lines[0], $lines[1] ?? ''));
}

// ---------- почта ----------
// Куда слать копии уведомлений мастеру: адрес из кабинета («-» — выключено), иначе из config.php
function mail_admin() {
    require_once __DIR__ . '/store.php';
    $m = (string)kv_get('mail_admin');
    if ($m === '-') return '';
    return $m ?: (string)cfg('mail_admin');
}
// Письмо от студии. Текст — как в Telegram (<b>, <a>, переносы строк), оборачиваем в простой фирменный шаблон.
// Отправить письмо: через почтовый сервер ящика marina@ (SMTP с паролем — письмо подписывается DKIM, меньше спама),
// если пароль ящика не задан или сервер недоступен — обычной функцией mail() хостинга.
function mail_out($to, $subj, $body, $headers) {
    $from = (string)cfg('mail_from');
    if (cfg('smtp_pass')) require_once __DIR__ . '/store.php';
    if (cfg('smtp_pass') && kv_get('smtp_on')) {  // включается после проверки (poll.php?testmail=1)
        $ok = smtp_send($from, $to, "To: <$to>\r\nSubject: $subj\r\n" . $headers, $body);
        if ($ok === true) return true;
        logline('smtp → ' . $to . ': ' . $ok . ' — отправляю через mail()');
    }
    return @mail($to, $subj, $body, $headers, '-f' . $from);
}
function smtp_send($from, $to, $head, $body) {
    $host = (string)(cfg('smtp_host') ?: 'ssl://smtp.timeweb.ru:465');
    $fp = @stream_socket_client($host, $en, $es, 10); if (!$fp) return 'нет связи: ' . $es;
    stream_set_timeout($fp, 15);
    $rd = function () use ($fp) { $r = ''; while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
    $cmd = function ($c, $want) use ($fp, $rd) {
        if ($c !== null) fwrite($fp, $c . "\r\n"); $r = $rd();
        $GLOBALS['smtp_log'][] = ($c === null ? '' : (strpos($c, 'AUTH') === 0 || strlen($c) > 60 || preg_match('/^[A-Za-z0-9+\/=]+$/', $c) ? '[скрыто]' : preg_replace('/<.(.*)@/U', '<*@', $c)) . ' → ') . preg_replace('/\S+@\S+/', '*@*', trim($r));
        return strpos($r, (string)$want) === 0 ? true : trim($r);
    };
    $data = preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $head . "\r\n" . $body));
    foreach ([[null, 220], ['EHLO ' . substr(strrchr($from, '@'), 1), 250], ['AUTH LOGIN', 334], [base64_encode($from), 334], [base64_encode((string)cfg('smtp_pass')), 235],
              ['MAIL FROM:<' . $from . '>', 250], ['RCPT TO:<' . $to . '>', 25], ['DATA', 354], [str_replace("\n", "\r\n", rtrim($data, "\n")) . "\r\n.", 250]] as [$c, $w]) {
        $r = $cmd($c, $w); if ($r !== true) { fclose($fp); return 'SMTP ' . ($c ? strtok($c, ' :') : 'привет') . ': ' . mb_substr($r, 0, 120); }
    }
    fwrite($fp, "QUIT\r\n"); fclose($fp); return true;
}

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
    // Обычный текст + красивая версия (письмо только из HTML почтовики чаще считают спамом), свой Message-ID и дата
    $plain = html_entity_decode(strip_tags(preg_replace(['#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#u', '#<br\s*/?>#i'], ['$2 ($1)', "\n"], $text)), ENT_QUOTES, 'UTF-8')
        . "\n\n—\nСтудия «М.А», Михайловка, ул. Обороны, 41 · +7 995 428-47-88\n" . $site . '/';
    $plain = preg_replace('/\((https?:[^)]+)\) \(\1\)|(https?:\S+) \(\2\)/u', '$1$2', $plain);  // ссылка, подписанная самой собой, — один раз
    $b = 'ma' . bin2hex(random_bytes(8)); $dom = substr(strrchr($from, '@'), 1);
    $headers = "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$b\"\r\n"
        . 'From: =?UTF-8?B?' . base64_encode('Студия «М.А»') . "?= <$from>\r\nReply-To: $from\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $dom . ">\r\nDate: " . date('r') . "\r\n";
    $mime = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($plain))
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body)) . "--$b--\r\n";
    $ok = mail_out($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $mime, $headers);
    if (!$ok) logline('mail to ' . $to . ' failed');
    return $ok;
}

// Письмо покупателю сертификата: при оформлении (ждёт оплату) и когда Марина подтвердит оплату (активен)
function cert_mail($c, $paid) {
    if (empty($c['email'])) return;
    $key = preg_replace('/^(МН|MH|MN|МА|MA)/u', '', preg_replace('/[^A-ZА-Я0-9]/u', '', mb_strtoupper((string)$c['code'])));
    $link = rtrim((string)cfg('site_url'), '/') . '/#cert-' . $key;
    $sum = number_format((int)$c['sum'], 0, ',', ' ') . ' ₽';
    $till = !empty($c['till']) ? date('d.m.Y', strtotime($c['till'])) : '';
    $text = $paid
        ? "Оплата получена — сертификат активен 🎁\n\n<b>№ " . h($c['code']) . "</b> на <b>" . $sum . "</b>" . (!empty($c['to']) ? "\nДля: " . h($c['to']) : '') . ($till ? "\nДействует до " . $till : '')
          . (isset($c['balance']) && (int)$c['balance'] < (int)$c['sum'] ? "\nОстаток: <b>" . number_format((int)$c['balance'], 0, ',', ' ') . " ₽</b>" : '')
          . "\n\nОткрыть, скачать картинкой или переслать: <a href=\"" . h($link) . "\">" . h($link) . "</a>\n\nСохраните это письмо — в нём номер сертификата. Записаться можно на сайте, номер сертификата назовите Марине."
        : "Сертификат <b>№ " . h($c['code']) . "</b> на <b>" . $sum . "</b> оформлен и ждёт оплаты.\n\nКак только Марина увидит перевод, придёт письмо, что сертификат активен.\nСертификат: <a href=\"" . h($link) . "\">" . h($link) . "</a>";
    send_mail($c['email'], ($paid ? 'Ваш подарочный сертификат № ' : 'Сертификат № ') . $c['code'] . ($paid ? ' активен' : ' — ждёт оплаты'), $text);
}

// Письмо с файлом (резервная копия базы)
function send_mail_file($to, $subject, $text, $fname, $data) {
    $from = (string)cfg('mail_from');
    if (!$from || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $b = 'ma' . bin2hex(random_bytes(8));
    $headers = "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$b\"\r\n"
        . 'From: =?UTF-8?B?' . base64_encode('Студия «М.А»') . "?= <$from>\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@'), 1) . ">\r\nDate: " . date('r') . "\r\n";
    $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$b\r\nContent-Type: application/gzip; name=\"$fname\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$fname\"\r\n\r\n" . chunk_split(base64_encode($data))
        . "--$b--\r\n";
    $ok = mail_out($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    if (!$ok) logline('mail file to ' . $to . ' failed');
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
