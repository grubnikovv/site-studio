<?php
// Приём запросов сайта. Сайт шлёт POST с JSON, ответ — JSON.
//   state   — общая база (кабинету — полностью, посетителю — только занятость)
//   login / sync — вход мастера по паролю и сохранение изменений из кабинета
//   booking, cancel, train, cert, claim, cert_get — действия посетителей
//   poke    — клиентка открывает бота: послушать Telegram, чтобы бот ответил сразу
require __DIR__ . '/store.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allow = array_filter(array_map('trim', explode(',', (string)cfg('allow_origin'))));
if (!$allow || in_array($origin, $allow, true)) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
header('Cache-Control: no-store');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!empty($in['website'])) json_out(['ok' => true]);  // ловушка для ботов
$type = $in['type'] ?? '';
$admin = session_ok($in['token'] ?? '');

// Не больше 60 действий (у мобильных операторов много людей на одном адресе) за 10 минут с одного адреса (чтение базы и работа кабинета — без ограничения)
if (!in_array($type, ['state', 'cert_get', 'push_key', 'push_peek', 'client_data'], true) && !$admin) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    $db = store_init();
    $db->prepare('DELETE FROM hits WHERE t < ?')->execute([time() - 600]);
    $n = $db->prepare('SELECT COUNT(*) FROM hits WHERE ip = ?'); $n->execute([$ip]);
    if ((int)$n->fetchColumn() >= 60) json_out(['ok' => false, 'error' => 'Слишком много запросов. Попробуйте через несколько минут.']);
    $db->prepare('INSERT INTO hits (ip, t) VALUES (?, ?)')->execute([$ip, time()]);
}

// Номер сертификата без приставки «МА-» и знаков (как на сайте)
function cert_key($c) { return preg_replace('/^(МН|MH|MN|МА|MA)/u', '', preg_replace('/[^A-ZА-ЯЁ0-9]/u', '', mb_strtoupper((string)$c))); }
function clean($s, $max) { return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)$s))), 0, $max); }
function fail($msg) { doc_abort(); json_out(['ok' => false, 'error' => $msg]); }
function done($doc, $v, $extra = [], $admin = false) {
    $v = doc_save($doc, $v);
    json_out(['ok' => true, 'v' => $v, 'doc' => $admin ? doc_full($doc) : doc_public($doc)] + $extra);
}

// ---------- база ----------
if ($type === 'state') {
    [$doc, $v] = doc_load(true);
    if (doc_expire($doc)) $v = doc_save($doc, $v); else doc_abort();
    if ((int)($in['v'] ?? -1) === $v) json_out(['ok' => true, 'v' => $v, 'same' => true, 'admin' => $admin]);
    json_out(['ok' => true, 'v' => $v, 'admin' => $admin, 'doc' => $admin ? doc_full($doc) : doc_public($doc)]);
}

if ($type === 'login') {
    if (!pass_ok($in['pass'] ?? '')) { sleep(1); json_out(['ok' => false, 'error' => 'Неверный пароль']); }
    json_out(['ok' => true, 'token' => session_new()]);
}

// Смена пароля в кабинете
if ($type === 'pass_change') {
    if (!$admin) json_out(['ok' => false, 'error' => 'Войдите в кабинет заново', 'relogin' => true]);
    if (!pass_ok($in['old'] ?? '')) { sleep(1); json_out(['ok' => false, 'error' => 'Текущий пароль введён неверно']); }
    if ($e = pass_weak($in['new'] ?? '')) json_out(['ok' => false, 'error' => $e]);
    pass_set($in['new']);
    // остальные телефоны выходят из кабинета, этот остаётся
    store_init()->prepare('DELETE FROM sessions WHERE token <> ?')->execute([hash('sha256', (string)$in['token'])]);
    notify_admins("🔐 Пароль кабинета на сайте изменён. Если это были не вы — нажмите на сайте «Забыли пароль?».");
    json_out(['ok' => true]);
}

// Почта мастера для копий уведомлений: без mail — узнать текущую, с mail — сохранить (пустая — выключить) и прислать проверочное письмо
if ($type === 'mail_admin') {
    if (!$admin) json_out(['ok' => false, 'error' => 'Войдите в кабинет заново', 'relogin' => true]);
    if (!array_key_exists('mail', $in)) json_out(['ok' => true, 'mail' => mail_admin()]);
    $m = trim((string)$in['mail']);
    if ($m === '') { kv_put('mail_admin', '-'); json_out(['ok' => true, 'mail' => '']); }
    if (!filter_var($m, FILTER_VALIDATE_EMAIL) || strlen($m) > 80) json_out(['ok' => false, 'error' => 'Проверьте адрес почты']);
    if (!cfg('mail_from')) json_out(['ok' => false, 'error' => 'Почта студии ещё не подключена. Напишите разработчику.']);
    kv_put('mail_admin', $m);
    $sent = send_mail($m, 'Уведомления с сайта подключены', "Готово! 💅\nНа эту почту будут приходить копии уведомлений с сайта: новые записи, отмены, переносы и заявки.\n\nЕсли письмо попало в «Спам» — отметьте «Не спам», чтобы уведомления приходили во «Входящие».");
    json_out(['ok' => true, 'mail' => $m, 'sent' => (bool)$sent]);
}

// Полная копия базы для мастера (файл JSON): всё, что есть в кабинете
if ($type === 'backup') {
    if (!$admin) json_out(['ok' => false, 'error' => 'Войдите в кабинет заново', 'relogin' => true]);
    require_once __DIR__ . '/backup.php';
    $bk = glob(backup_dir() . '/ma-*.sqlite') ?: [];
    json_out(['ok' => true, 'json' => empty($in['info']) ? backup_json() : '', 'last' => $bk ? substr(basename(end($bk)), 3, 10) : '', 'count' => count($bk)]);
}

// Забыли пароль: код в Telegram мастеру
if ($type === 'pass_reset_req') {
    $admins = (int)store_init()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if (!$admins) json_out(['ok' => false, 'error' => 'Telegram-бот ещё не подключён. Обратитесь к разработчику.']);
    if ((int)kv_get('reset_sent') > time() - 120) json_out(['ok' => true, 'again' => true]);
    $code = (string)random_int(100000, 999999);
    kv_put('reset_hash', password_hash($code, PASSWORD_DEFAULT)); kv_put('reset_until', time() + 900); kv_put('reset_tries', 0); kv_put('reset_sent', time());
    notify_admins("🔐 <b>Код для смены пароля кабинета: " . $code . "</b>\nДействует 15 минут. Никому его не сообщайте.\nЕсли вы не запрашивали код — просто проигнорируйте.");
    json_out(['ok' => true]);
}

if ($type === 'pass_reset') {
    $h = kv_get('reset_hash'); $tries = (int)kv_get('reset_tries');
    if (!$h || (int)kv_get('reset_until') < time() || $tries >= 5) json_out(['ok' => false, 'error' => 'Код устарел. Запросите новый.']);
    kv_put('reset_tries', $tries + 1);
    if (!password_verify(preg_replace('/\D/', '', (string)($in['code'] ?? '')), $h)) { sleep(1); json_out(['ok' => false, 'error' => 'Неверный код. Осталось попыток: ' . (4 - $tries)]); }
    if ($e = pass_weak($in['new'] ?? '')) json_out(['ok' => false, 'error' => $e]);
    pass_set($in['new']); kv_del('reset_hash');
    store_init()->exec('DELETE FROM sessions');  // все старые входы закрываются
    notify_admins("🔐 Пароль кабинета изменён через код из Telegram.");
    json_out(['ok' => true, 'token' => session_new()]);
}

if ($type === 'logout') {
    store_init()->prepare('DELETE FROM sessions WHERE token = ?')->execute([hash('sha256', (string)($in['token'] ?? ''))]);
    json_out(['ok' => true]);
}

if ($type === 'sync') {
    if (!$admin) json_out(['ok' => false, 'error' => 'Войдите в кабинет заново', 'relogin' => true]);
    [$doc, $v] = doc_load(true);
    $ops = is_array($in['ops'] ?? null) ? $in['ops'] : [];
    $wasWait = []; foreach ($doc['certs'] as $c) if (($c['status'] ?? '') === 'wait') $wasWait[$c['id'] ?? ''] = true;
    $before = $doc;
    doc_apply($doc, $ops);
    try { cnotes_diff($before, $doc); } catch (Throwable $e) { logline('cnotes: ' . $e->getMessage()); }
    mirror_bookings($ops, $doc);
    foreach ($doc['certs'] as $c) if (!empty($wasWait[$c['id'] ?? '']) && ($c['status'] ?? '') === 'paid') cert_mail($c, true);  // сертификат оплачен — письмо покупателю
    done($doc, $v, [], true);
}

// ---------- запись клиентки ----------
if ($type === 'booking') {
    $r = is_array($in['rec'] ?? null) ? $in['rec'] : $in;  // старый формат: поля прямо в запросе
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($r['id'] ?? ''));
    $date = (string)($r['date'] ?? ''); $start = (int)($r['start'] ?? -1); $dur = max(15, min(600, (int)($r['dur'] ?? 60)));
    $phone = phone_ok($r['phone'] ?? ''); $name = clean($r['name'] ?? '', 40);
    if (!$admin && count(array_filter(preg_split('/\s+/u', $name), fn($w) => mb_strlen(preg_replace("/[-']/u", '', $w)) >= 2)) < 2) fail('Укажите имя и фамилию, например: Анна Иванова.');
    if (!$id || strlen($id) > 24 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $start < 0 || $start > 1440 || !$phone || mb_strlen($name) < 2)
        json_out(['ok' => false, 'error' => 'Проверьте данные записи.']);
    if (strtotime($date) < strtotime('today') || strtotime($date) > strtotime('+70 days'))
        json_out(['ok' => false, 'error' => 'Дата вне диапазона записи.']);
    [$doc, $v] = doc_load(true);
    $mine = array_filter($doc['bookings'], fn($b) => ($b['status'] ?? '') === 'booked' && digits_phone($b['phone'] ?? '') === $phone && ($b['date'] ?? '') >= date('Y-m-d') && ($b['id'] ?? '') !== $id);
    // повтор — та же услуга в тот же день (и для мастера: защита от ошибки); разные услуги в один день можно
    $svc = (string)($r['svc'] ?? '');
    foreach ($mine as $b) if ($b['date'] === $date && ($b['svc'] ?? '') === $svc) fail($admin ? 'Клиентка уже записана на этот день в ' . hm((int)$b['start']) . ' на эту услугу. Поменяйте услугу или день.'
        : 'На этот номер уже есть запись в этот день в ' . hm((int)$b['start']) . ' на эту услугу. Если записываете другого человека (например, дочку) — укажите его номер телефона. Или выберите другую услугу или день.');
    if (!$admin && count($mine) >= 4) fail('На этот номер уже 4 будущие записи. Чтобы записаться ещё, напишите Марине.');
    if (!slot_free($doc, $date, $start, $dur, $id)) fail('Это время только что заняли. Выберите другое.');
    $remind = in_array($r['remind'] ?? '', ['tg', 'max', 'mail', 'none'], true) ? $r['remind'] : 'none';
    $email = $remind === 'mail' ? mb_strtolower(trim((string)($r['email'] ?? ''))) : '';
    if ($remind === 'mail' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 80)) fail('Проверьте email для напоминаний.');
    $svcName = clean($in['svcName'] ?? ($r['svcName'] ?? ''), 80);
    $rec = ['id' => $id, 'date' => $date, 'start' => $start, 'dur' => $dur, 'svc' => clean($r['svc'] ?? '', 30), 'name' => $name, 'phone' => phone_nice($phone),
        'note' => clean($r['note'] ?? '', 200), 'price' => max(0, (int)($r['price'] ?? 0)), 'remove' => !empty($r['remove']), 'french' => !empty($r['french']), 'remind' => $remind, 'status' => 'booked', 'created' => now_ms()] + ($email ? ['email' => $email] : []) + (!empty($r['other']) ? ['other' => true] : []);
    if (($cw = client_who($in['ctoken'] ?? '')) && $cw['acc']) { $rec['owner'] = (int)$cw['acc']['id']; $rec['accMail'] = $cw['acc']['email']; }  // запись из кабинета — видна в нём
    if (!empty($rec['owner'])) cnote($rec['owner'], '📅', 'Вы записаны', $svcName . ' · ' . when_text($date, $start) . '. Перенести или отменить — в кабинете, не позже чем за 2 дня.', '#kabinet');
    $doc['bookings'] = array_values(array_filter($doc['bookings'], fn($b) => ($b['id'] ?? '') !== $id));
    $doc['bookings'][] = $rec;
    doc_note($doc, '📅', 'Новая запись', $name . ' · ' . $svcName . ' · ' . when_text($date, $start), ['tab' => 'day', 'date' => $date]);
    // для бота и напоминаний
    db()->prepare('INSERT OR REPLACE INTO bookings (id, date, start, dur, svc, coat, name, phone, status, remind, email, created, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "booked", ?, ?, ?, ?)')
        ->execute([$id, $date, $start, $dur, $svcName, empty($r['coat']) ? 0 : 1, $name, $phone, $remind, $email, time(), time()]);
    $v = doc_save($doc, $v);
    notify_admins("📅 <b>Новая запись</b>\n" . h($name) . ', ' . phone_nice($phone) . "\n" . h($svcName) . "\n" . when_text($date, $start)
        . "\nНапоминания: " . ['tg' => 'Telegram', 'max' => 'MAX', 'mail' => 'почта ' . h($email), 'none' => 'не нужны'][$remind]
        . (!empty($r['other']) ? "\nЗаписал(а) другой человек — ссылку на напоминания отправят клиентке" : ''));
    if ($remind === 'mail') {  // подтверждение записи письмом
        $site = rtrim((string)cfg('site_url'), '/');
        send_mail($email, 'Вы записаны в студию «М.А» — ' . when_text($date, $start), 'Здравствуйте, ' . h($name) . "! 💅\n\nВы записаны в студию «М.А»:\n<b>" . h($svcName) . "</b>\n<b>" . when_text($date, $start)
            . "</b>\nМихайловка, ул. Обороны, 41\n" . way_link() . "\n\nНапомним за 2 дня и за 2 часа до визита.\nЕсли планы изменятся — отмените запись на сайте в разделе <a href=\"" . h($site) . "/#zapis\">«Мои записи»</a> или напишите Марине: +7 995 428‑47‑88.");
    }
    json_out(['ok' => true, 'v' => $v, 'doc' => doc_public($doc), 'tg' => 'https://t.me/' . cfg('bot_name') . '?start=b_' . $id . '_' . link_key($id)]);
}

if ($type === 'cancel') {
    // отменить и перенести можно только из личного кабинета (вход по почте или по коду)
    $w = client_who($in['ctoken'] ?? ''); if (!$w) json_out(['ok' => false, 'relogin' => true, 'error' => 'Войдите в личный кабинет, чтобы отменить запись.']);
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($in['id'] ?? '')); $own = client_mine($w);
    [$doc, $v] = doc_load(true);
    $hit = null;
    foreach ($doc['bookings'] as $i => $b) if (($b['id'] ?? '') === $id && $own($b)) $hit = $i;
    if ($hit === null) fail('Запись не найдена.');
    $b = &$doc['bookings'][$hit];
    if (($b['status'] ?? '') === 'booked' && booking_ts($b) - time() < 48 * 3600) { unset($b); fail('До визита меньше 2 дней — отменить можно только через Марину: +7 995 428‑47‑88.'); }
    if (($b['status'] ?? '') === 'booked') {
        $b['status'] = 'cancelled'; $b['by'] = 'client'; $b['cancelledAt'] = now_ms();
        if (!empty($b['certPay']['certId'])) {  // оплата сертификатом возвращается на сертификат
            foreach ($doc['certs'] as &$c) if (($c['id'] ?? '') === $b['certPay']['certId']) {
                $c['balance'] = (int)($c['balance'] ?? 0) + (int)$b['certPay']['sum'];
                $c['spent'] = array_values(array_filter($c['spent'] ?? [], fn($x) => ($x['booking'] ?? '') !== $id));
                if ($c['balance'] > 0) $c['status'] = 'paid';
            }
            unset($c, $b['certPay']);
        }
        doc_note($doc, '❌', 'Клиентка отменила запись', ($b['name'] ?? '') . ' · ' . when_text($b['date'], (int)$b['start']) . ' — время освободилось', ['tab' => 'day', 'date' => $b['date']]);
        db()->prepare('UPDATE bookings SET status = "cancelled", updated = ? WHERE id = ?')->execute([time(), $id]);
        notify_admins("❌ <b>Клиентка отменила запись</b>\n" . h($b['name'] ?? '') . ', ' . h($b['phone'] ?? '') . "\n" . when_text($b['date'], (int)$b['start']) . "\nВремя освободилось.");
    }
    unset($b);
    done($doc, $v);
}

// Клиентка переносит запись сама: не позже чем за 2 дня до визита, на свободное время
if ($type === 'move') {
    $w = client_who($in['ctoken'] ?? ''); if (!$w) json_out(['ok' => false, 'relogin' => true, 'error' => 'Войдите в личный кабинет, чтобы перенести запись.']);
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($in['id'] ?? '')); $own = client_mine($w);
    $date = (string)($in['date'] ?? ''); $start = (int)($in['start'] ?? -1);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $start < 0 || $start > 1440 || strtotime($date) > strtotime('+70 days')) fail('Проверьте новую дату и время.');
    [$doc, $v] = doc_load(true);
    $hit = null; foreach ($doc['bookings'] as $i => $b) if (($b['id'] ?? '') === $id && $own($b)) $hit = $i;
    if ($hit === null) fail('Запись не найдена.');
    $b = $doc['bookings'][$hit];
    if (($b['status'] ?? '') !== 'booked') fail('Эта запись уже отменена.');
    if (booking_ts($b) - time() < 48 * 3600) fail('До визита меньше 2 дней — перенести можно только через Марину: +7 995 428‑47‑88.');
    $dur = (int)($b['dur'] ?? 0) ?: max(15, min(600, (int)($in['dur'] ?? 60)));
    foreach ($doc['bookings'] as $o) if (($o['id'] ?? '') !== $id && ($o['status'] ?? '') === 'booked' && digits_phone($o['phone'] ?? '') === digits_phone($b['phone'] ?? '') && ($o['date'] ?? '') === $date && ($o['svc'] ?? '') === ($b['svc'] ?? ''))
        fail('На этот день у вас уже есть запись на эту услугу. Выберите другой день.');
    if (!slot_free($doc, $date, $start, $dur, $id)) fail('Это время только что заняли. Выберите другое.');
    $from = when_text($b['date'], (int)$b['start']); $to = when_text($date, $start);
    $doc['bookings'][$hit]['date'] = $date; $doc['bookings'][$hit]['start'] = $start; $doc['bookings'][$hit]['dur'] = $dur;
    $doc['bookings'][$hit]['movedBy'] = 'client'; $doc['bookings'][$hit]['movedFrom'] = $b['date'] . ' ' . hm((int)$b['start']); $doc['bookings'][$hit]['updated'] = now_ms();
    doc_note($doc, '🔁', 'Клиентка перенесла запись', ($b['name'] ?? '') . ' · с ' . $from . ' на ' . $to, ['tab' => 'day', 'date' => $date]);
    $v = doc_save($doc, $v);
    // бот и напоминания — на новое время; клиентке — подтверждение
    db()->prepare('UPDATE bookings SET date = ?, start = ?, dur = ?, sent_2d = 0, sent_2h = 0, confirmed = 0, updated = ? WHERE id = ?')->execute([$date, $start, $dur, time(), $id]);
    $q = db()->prepare('SELECT * FROM bookings WHERE id = ?'); $q->execute([$id]); $row = $q->fetch();
    notify_admins("🔁 <b>Клиентка перенесла запись</b>\n" . h($b['name'] ?? '') . ', ' . h($b['phone'] ?? '') . ($row ? "\n" . h($row['svc']) : '') . "\nБыло: " . $from . "\n<b>Стало: " . $to . "</b>");
    if ($row) notify_client($row, 'Запись перенесена — ' . $to, "Запись перенесена 👌\nБыло: " . $from . "\n<b>Теперь: " . $to . "</b>\nМихайловка, ул. Обороны, 41\n" . way_link());
    json_out(['ok' => true, 'v' => $v, 'doc' => doc_public($doc)]);
}

// ---------- кабинет клиентки ----------
if ($type === 'client_code') {
    $phone = phone_ok($in['phone'] ?? ''); if (!$phone) fail('Укажите телефон полностью.');
    $db = client_db();
    $q = $db->prepare('SELECT sent FROM client_codes WHERE phone = ?'); $q->execute([$phone]);
    if ((int)$q->fetchColumn() > time() - 60) fail('Код уже отправлен. Новый можно запросить через минуту.');
    $ch = client_channel($phone);
    if (!$ch) fail('Не нашли, куда прислать код: на этот номер нет записей с напоминаниями в Telegram или на почту. Подключите напоминания при следующей записи или напишите Марине: +7 995 428‑47‑88.');
    $code = (string)random_int(100000, 999999);
    $db->prepare('INSERT OR REPLACE INTO client_codes (phone, hash, until, tries, sent) VALUES (?, ?, ?, 0, ?)')->execute([$phone, password_hash($code, PASSWORD_DEFAULT), time() + 600, time()]);
    $text = "🔐 Код для входа в личный кабинет студии «М.А»: <b>" . $code . "</b>\nДействует 10 минут. Никому его не сообщайте.";
    if ($ch[0] === 'tg') send($ch[1], $text); else send_mail($ch[1], 'Код для входа: ' . $code, $text);
    json_out(['ok' => true, 'via' => $ch[0], 'to' => $ch[0] === 'mail' ? mask_mail($ch[1]) : 'Telegram']);
}
if ($type === 'client_login') {
    $phone = phone_ok($in['phone'] ?? ''); $db = client_db();
    $q = $db->prepare('SELECT * FROM client_codes WHERE phone = ?'); $q->execute([$phone]); $c = $q->fetch();
    if (!$c || $c['until'] < time() || $c['tries'] >= 5) fail('Код устарел. Запросите новый.');
    $db->prepare('UPDATE client_codes SET tries = tries + 1 WHERE phone = ?')->execute([$phone]);
    if (!password_verify(preg_replace('/\D/', '', (string)($in['code'] ?? '')), $c['hash'])) { sleep(1); fail('Неверный код. Осталось попыток: ' . (4 - (int)$c['tries'])); }
    $db->prepare('DELETE FROM client_codes WHERE phone = ?')->execute([$phone]);
    $t = bin2hex(random_bytes(24));
    $db->prepare('INSERT INTO client_sessions (token, phone, created) VALUES (?, ?, ?)')->execute([hash('sha256', $t), $phone, time()]);
    json_out(['ok' => true, 'ctoken' => $t, 'phone' => phone_nice($phone)]);
}
if ($type === 'client_logout') {
    client_db()->prepare('DELETE FROM client_sessions WHERE token = ?')->execute([hash('sha256', (string)($in['ctoken'] ?? ''))]);
    json_out(['ok' => true]);
}
// ---------- личный кабинет по почте и паролю ----------
if ($type === 'acc_register') {
    $m = mail_ok($in['email'] ?? ''); $name = clean($in['name'] ?? '', 40); $pass = (string)($in['pass'] ?? '');
    if (!$m) fail('Проверьте адрес почты.');
    if (mb_strlen($name) < 2) fail('Укажите имя.');
    if (mb_strlen($pass) < 6) fail('Пароль — не короче 6 символов.');
    if (empty($in['consent'])) fail('Отметьте согласие на обработку персональных данных.');
    if (!cfg('mail_from')) fail('Регистрация временно недоступна. Напишите Марине.');
    $a = acc_by_mail($m);
    if ($a && $a['verified']) fail('Эта почта уже зарегистрирована. Войдите или нажмите «Забыли пароль?».');
    $h = password_hash($pass, PASSWORD_DEFAULT);
    if ($a) client_db()->prepare('UPDATE accounts SET name = ?, pass = ? WHERE id = ?')->execute([$name, $h, $a['id']]);
    else client_db()->prepare('INSERT INTO accounts (email, name, pass, created) VALUES (?, ?, ?, ?)')->execute([$m, $name, $h, time()]);
    client_db()->prepare('UPDATE accounts SET news = ? WHERE email = ?')->execute([empty($in['news']) ? 0 : 1, $m]);
    $r = acc_send_code($m, 'reg');
    if ($r === 'fail') fail('Не получилось отправить письмо. Проверьте адрес или попробуйте позже.');
    json_out(['ok' => true, 'email' => $m, 'again' => $r === 'wait']);
}
if ($type === 'acc_code') {  // прислать код ещё раз
    $m = mail_ok($in['email'] ?? ''); $p = ($in['purpose'] ?? '') === 'reset' ? 'reset' : 'reg';
    $a = $m ? acc_by_mail($m) : null;
    if ($a && ($p === 'reset' ? $a['verified'] : !$a['verified'])) {
        $r = acc_send_code($m, $p);
        if ($r === 'wait') fail('Новый код можно запросить через минуту.');
        if ($r === 'fail') fail('Не получилось отправить письмо. Попробуйте позже.');
    }
    json_out(['ok' => true]);
}
if ($type === 'acc_verify') {
    $m = mail_ok($in['email'] ?? ''); $a = $m ? acc_by_mail($m) : null;
    if (!$a) fail('Сначала зарегистрируйтесь.');
    if ($e = acc_check_code($m, 'reg', $in['code'] ?? '')) fail($e);
    client_db()->prepare('UPDATE accounts SET verified = 1 WHERE id = ?')->execute([$a['id']]);
    json_out(['ok' => true, 'ctoken' => acc_session((int)$a['id'])]);
}
if ($type === 'acc_login') {
    $m = mail_ok($in['email'] ?? ''); $a = $m ? acc_by_mail($m) : null;
    if (!$a || !password_verify((string)($in['pass'] ?? ''), (string)$a['pass'])) { sleep(1); fail('Неверная почта или пароль.'); }
    if (!$a['verified']) { acc_send_code($m, 'reg'); json_out(['ok' => false, 'verify' => true, 'email' => $m, 'error' => 'Почта ещё не подтверждена — отправили код ещё раз.']); }
    json_out(['ok' => true, 'ctoken' => acc_session((int)$a['id'])]);
}
if ($type === 'acc_reset_req') {  // ответ одинаковый, есть такая почта или нет — чтобы нельзя было перебирать чужие адреса
    $m = mail_ok($in['email'] ?? ''); if (!$m) fail('Проверьте адрес почты.');
    $a = acc_by_mail($m);
    if ($a && $a['verified'] && acc_send_code($m, 'reset') === 'wait') fail('Код уже отправлен. Новый можно запросить через минуту.');
    json_out(['ok' => true, 'email' => $m]);
}
if ($type === 'acc_reset') {
    $m = mail_ok($in['email'] ?? ''); $a = $m ? acc_by_mail($m) : null; $pass = (string)($in['pass'] ?? '');
    if (mb_strlen($pass) < 6) fail('Новый пароль — не короче 6 символов.');
    if (!$a || !$a['verified']) fail('Код устарел. Запросите новый.');
    if ($e = acc_check_code($m, 'reset', $in['code'] ?? '')) fail($e);
    $db = client_db();
    $db->prepare('UPDATE accounts SET pass = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $a['id']]);
    $db->prepare('DELETE FROM client_sessions WHERE acc = ?')->execute([$a['id']]);  // на других устройствах — войти заново
    json_out(['ok' => true, 'ctoken' => acc_session((int)$a['id'])]);
}
if ($type === 'acc_pass') {  // смена пароля в кабинете
    $w = client_who($in['ctoken'] ?? ''); if (!$w || !$w['acc']) json_out(['ok' => false, 'relogin' => true]);
    if (!password_verify((string)($in['old'] ?? ''), (string)$w['acc']['pass'])) { sleep(1); fail('Текущий пароль введён неверно.'); }
    if (mb_strlen((string)($in['pass'] ?? '')) < 6) fail('Новый пароль — не короче 6 символов.');
    $db = client_db();
    $db->prepare('UPDATE accounts SET pass = ? WHERE id = ?')->execute([password_hash((string)$in['pass'], PASSWORD_DEFAULT), $w['acc']['id']]);
    $db->prepare('DELETE FROM client_sessions WHERE acc = ? AND token <> ?')->execute([$w['acc']['id'], hash('sha256', (string)$in['ctoken'])]);
    json_out(['ok' => true]);
}
// Привязать телефон к кабинету: код приходит туда, куда приходят напоминания по этому номеру (Telegram или почта)
if ($type === 'acc_phone') {
    $w = client_who($in['ctoken'] ?? ''); if (!$w || !$w['acc']) json_out(['ok' => false, 'relogin' => true]);
    $phone = phone_ok($in['phone'] ?? ''); if (!$phone) fail('Укажите телефон полностью.');
    $db = client_db();
    if (empty($in['code'])) {
        $q = $db->prepare('SELECT sent FROM client_codes WHERE phone = ?'); $q->execute([$phone]);
        if ((int)$q->fetchColumn() > time() - 60) fail('Код уже отправлен. Новый можно запросить через минуту.');
        $ch = client_channel($phone);
        if (!$ch) fail('По этому номеру ещё нет записей с напоминаниями, поэтому код прислать некуда. Ничего страшного: записи, которые вы сделаете, войдя в кабинет, появятся здесь сами.');
        $code = (string)random_int(100000, 999999);
        $db->prepare('INSERT OR REPLACE INTO client_codes (phone, hash, until, tries, sent) VALUES (?, ?, ?, 0, ?)')->execute([$phone, password_hash($code, PASSWORD_DEFAULT), time() + 600, time()]);
        $text = "🔐 Код, чтобы привязать номер к личному кабинету студии «М.А»: <b>" . $code . "</b>\nДействует 10 минут. Никому его не сообщайте.";
        if ($ch[0] === 'tg') send($ch[1], $text); else send_mail($ch[1], 'Код ' . $code . ' — привязка номера', $text);
        json_out(['ok' => true, 'via' => $ch[0], 'to' => $ch[0] === 'mail' ? mask_mail($ch[1]) : 'Telegram']);
    }
    $q = $db->prepare('SELECT * FROM client_codes WHERE phone = ?'); $q->execute([$phone]); $c = $q->fetch();
    if (!$c || $c['until'] < time() || $c['tries'] >= 5) fail('Код устарел. Запросите новый.');
    $db->prepare('UPDATE client_codes SET tries = tries + 1 WHERE phone = ?')->execute([$phone]);
    if (!password_verify(preg_replace('/\D/', '', (string)$in['code']), $c['hash'])) { sleep(1); fail('Неверный код. Осталось попыток: ' . (4 - (int)$c['tries'])); }
    $db->prepare('DELETE FROM client_codes WHERE phone = ?')->execute([$phone]);
    $db->prepare('UPDATE accounts SET phone = ? WHERE id = ?')->execute([$phone, $w['acc']['id']]);
    acc_merge($w['acc']['id'], $phone);
    json_out(['ok' => true, 'phone' => phone_nice($phone)]);
}

// Клиентка в кабинете отменяет неоплаченную бронь обучения
if ($type === 'client_train_cancel') {
    $w = client_who($in['ctoken'] ?? ''); if (!$w) json_out(['ok' => false, 'relogin' => true]);
    $own = client_mine($w); $id = (string)($in['id'] ?? '');
    [$doc, $v] = doc_load(true); $hit = null;
    foreach ($doc['training'] as $i => $t) if (($t['id'] ?? '') === $id && $own($t)) $hit = $i;
    if ($hit === null) fail('Заявка не найдена.');
    $t = $doc['training'][$hit];
    if (($t['status'] ?? '') === 'paid') {
        // оферта п. 4.4: раньше чем за 7 дней до начала — предоплата возвращается, позже — остаётся у Марины
        if (($t['date'] ?? '') <= date('Y-m-d')) fail('Обучение уже началось. Напишите Марине.');
        $back = date('Y-m-d') <= date('Y-m-d', strtotime($t['date'] . ' -7 days'));
        $doc['training'][$hit]['status'] = $back ? 'refund' : 'late'; $doc['training'][$hit]['by'] = 'student'; $doc['training'][$hit]['cancelledAt'] = now_ms();
        $pre = number_format((int)($t['pre'] ?? 0), 0, ',', ' ') . ' ₽';
        $txt = ($t['name'] ?? '') . ', ' . ($t['phone'] ?? '') . ' — даты снова свободны. ' . ($back ? 'Верните предоплату ' . $pre . ' переводом в Сбере.' : 'Отмена позже чем за 7 дней — предоплата ' . $pre . ' остаётся у вас.');
        doc_note($doc, '🎓', 'Ученица отменила обучение', $txt, ['tab' => 'req']);
        $v = doc_save($doc, $v);
        notify_admins("🎓 <b>Ученица отменила обучение</b>\n" . h($txt));
        if ($w['acc']) cnote($w['acc']['id'], '🎓', 'Обучение отменено', $back ? 'Марина вернёт предоплату ' . $pre . ' переводом в течение нескольких дней.' : 'Отмена позже чем за 7 дней до начала — предоплата не возвращается (п. 4.4 оферты).', '#kabinet');
        json_out(['ok' => true, 'v' => $v, 'doc' => doc_public($doc), 'refund' => $back]);
    }
    array_splice($doc['training'], $hit, 1);
    doc_note($doc, '🎓', 'Ученица отменила бронь', ($t['name'] ?? '') . ', ' . ($t['phone'] ?? '') . ' — даты снова свободны', ['tab' => 'req']);
    $v = doc_save($doc, $v);
    notify_admins("🎓 <b>Ученица отменила бронь обучения</b>\n" . h($t['name'] ?? '') . ', ' . h($t['phone'] ?? '') . "\nДаты снова свободны.");
    json_out(['ok' => true, 'v' => $v, 'doc' => doc_public($doc)]);
}
// Личные заметки ученицы к обучению (видит только она)
if ($type === 'client_train_note') {
    $w = client_who($in['ctoken'] ?? ''); if (!$w) json_out(['ok' => false, 'relogin' => true]);
    $own = client_mine($w); $id = (string)($in['id'] ?? '');
    [$doc, $v] = doc_load(true); $hit = null;
    foreach ($doc['training'] as $i => $t) if (($t['id'] ?? '') === $id && $own($t)) $hit = $i;
    if ($hit === null) fail('Заявка не найдена.');
    $doc['training'][$hit]['snote'] = mb_substr(trim(strip_tags((string)($in['note'] ?? ''))), 0, 3000);
    $v = doc_save($doc, $v);
    json_out(['ok' => true]);
}
// Прислать сертификат на почту ещё раз
if ($type === 'client_cert_mail') {
    $w = client_who($in['ctoken'] ?? ''); if (!$w) json_out(['ok' => false, 'relogin' => true]);
    $own = client_mine($w); $id = (string)($in['id'] ?? '');
    [$doc] = doc_load(false); $c = null;
    foreach ($doc['certs'] as $x) if (($x['id'] ?? '') === $id && $own($x)) $c = $x;
    if (!$c) fail('Сертификат не найден.');
    if ((int)kv_get('certmail_' . $id) > time() - 60) fail('Письмо уже отправлено. Ещё раз — через минуту.');
    $c['email'] = !empty($c['email']) ? $c['email'] : ($w['acc']['email'] ?? '');
    if (!$c['email']) fail('Не указана почта. Войдите в кабинет по почте.');
    kv_put('certmail_' . $id, time());
    cert_mail($c, ($c['status'] ?? '') !== 'wait');
    json_out(['ok' => true, 'to' => mask_mail($c['email'])]);
}

// Удалить аккаунт по просьбе клиентки (152-ФЗ). Сначала неиспользованные сертификаты уходят на указанную почту.
// Будущие записи остаются у Марины (её ждут), прошедшие — без имени и телефона (для статистики), отменённые — насовсем.
if ($type === 'client_delete') {
    $w = client_who($in['ctoken'] ?? ''); if (!$w) json_out(['ok' => false, 'relogin' => true]);
    $acc = $w['acc']; $m = mail_ok($in['email'] ?? '');
    if ($acc) {
        if ($m !== $acc['email']) fail('Введите почту, с которой вы зарегистрированы: ' . mask_mail($acc['email']));
        if (!password_verify((string)($in['pass'] ?? ''), (string)$acc['pass'])) { sleep(1); fail('Неверный пароль.'); }
    }
    if (empty($in['sure'])) fail('Отметьте, что понимаете последствия удаления.');
    $own = client_mine($w); $today = date('Y-m-d');
    [$doc, $v] = doc_load(true);
    $live = array_values(array_filter($doc['certs'], fn($c) => $own($c) && in_array($c['status'] ?? '', ['wait', 'paid'], true) && (int)($c['balance'] ?? $c['sum'] ?? 0) > 0 && ($c['till'] ?? '9999') >= $today));
    if ($live && !$m) fail('У вас есть неиспользованный сертификат — укажите почту, чтобы мы прислали его перед удалением.');
    foreach ($live as $c) { $c['email'] = $m; cert_mail($c, ($c['status'] ?? '') === 'paid'); }
    $name = $acc['name'] ?? ''; $future = 0; $gone = [];
    foreach ($doc['bookings'] as $i => &$b) {
        if (!$own($b)) continue;
        if ($name === '') $name = $b['name'] ?? '';
        if (($b['status'] ?? '') === 'booked' && ($b['date'] ?? '') >= $today) { unset($b['owner']); $future++; continue; }
        $gone[] = $b['id'] ?? '';
        if (($b['status'] ?? '') === 'cancelled') { $b = null; continue; }
        $b = ['id' => $b['id'], 'date' => $b['date'], 'start' => $b['start'], 'dur' => $b['dur'] ?? 60, 'svc' => $b['svc'] ?? '', 'price' => $b['price'] ?? 0, 'status' => $b['status'] ?? '', 'remove' => !empty($b['remove']), 'french' => !empty($b['french']),
            'name' => 'Клиентка удалена', 'phone' => '', 'anon' => true];
    }
    unset($b);
    $doc['bookings'] = array_values(array_filter($doc['bookings']));
    $doc['training'] = array_values(array_filter($doc['training'], fn($t) => !($own($t) && ($t['status'] ?? '') !== 'paid')));
    foreach ($doc['certs'] as &$c) if ($own($c)) unset($c['owner']);
    unset($c);
    doc_note($doc, '🗑', 'Клиентка удалила аккаунт', $name . ($future ? ' · будущих записей осталось: ' . $future : '') . ($live ? ' · сертификаты отправлены ей на почту' : ''), ['tab' => 'clients']);
    $v = doc_save($doc, $v);
    $db = client_db();
    foreach ($gone as $id) $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);  // из таблицы бота: телефон, чат, почта
    if ($w['phone']) $db->prepare('DELETE FROM client_codes WHERE phone = ?')->execute([$w['phone']]);
    if ($acc) {
        $db->prepare('DELETE FROM client_sessions WHERE acc = ?')->execute([$acc['id']]);
        $db->prepare('DELETE FROM acc_codes WHERE email = ?')->execute([$acc['email']]);
        $db->prepare('DELETE FROM accounts WHERE id = ?')->execute([$acc['id']]);
    } else $db->prepare('DELETE FROM client_sessions WHERE token = ?')->execute([hash('sha256', (string)$in['ctoken'])]);
    notify_admins("🗑 <b>Клиентка удалила аккаунт</b>\n" . h($name) . ($future ? "\nБудущих записей осталось: " . $future . " — они остаются в кабинете." : '') . ($live ? "\nНеиспользованные сертификаты отправлены ей на почту." : ''));
    json_out(['ok' => true, 'certs' => count($live), 'future' => $future]);
}

// ---------- уведомления клиентки ----------
if ($type === 'client_notes') {
    $w = client_who($in['ctoken'] ?? ''); if (!$w || !$w['acc']) json_out(['ok' => true, 'notes' => [], 'unread' => 0]);
    $a = $w['acc'];
    if (!empty($in['seen'])) client_db()->prepare('UPDATE accounts SET seen = ? WHERE id = ?')->execute([time(), $a['id']]);
    $q = client_db()->prepare('SELECT id, acc, icon, title, text, link, t FROM cnotes WHERE (acc = ? OR (acc = 0 AND t >= ?)) AND t > ? ORDER BY t DESC LIMIT 40');
    $q->execute([$a['id'], (int)$a['created'], time() - 90 * 86400]);
    $list = $q->fetchAll(); $seen = !empty($in['seen']) ? time() : (int)$a['seen'];
    $unread = count(array_filter($list, fn($n) => (int)$n['t'] > $seen));
    json_out(['ok' => true, 'notes' => $list, 'unread' => $unread, 'news' => (int)$a['news']]);
}
if ($type === 'acc_news') {  // согласие на письма с новостями и акциями
    $w = client_who($in['ctoken'] ?? ''); if (!$w || !$w['acc']) json_out(['ok' => false, 'relogin' => true]);
    client_db()->prepare('UPDATE accounts SET news = ? WHERE id = ?')->execute([empty($in['on']) ? 0 : 1, $w['acc']['id']]);
    json_out(['ok' => true]);
}
// ---------- нет доступа к старой почте или Telegram: Марина подтверждает номер сама ----------
if ($type === 'acc_phone_ask') {
    $w = client_who($in['ctoken'] ?? ''); if (!$w || !$w['acc']) json_out(['ok' => false, 'relogin' => true]);
    $phone = phone_ok($in['phone'] ?? ''); if (!$phone) fail('Укажите телефон полностью.');
    $db = client_db();
    $db->prepare('DELETE FROM link_reqs WHERE acc = ?')->execute([$w['acc']['id']]);
    $db->prepare('INSERT INTO link_reqs (acc, phone, t) VALUES (?, ?, ?)')->execute([$w['acc']['id'], $phone, time()]);
    [$doc, $v] = doc_load(true);
    doc_note($doc, '📱', 'Просят привязать номер', $w['acc']['name'] . ' (' . $w['acc']['email'] . ') — ' . phone_nice($phone) . '. Подтвердите во вкладке «Клиентки».', ['tab' => 'clients']);
    doc_save($doc, $v);
    notify_admins("📱 <b>Клиентка просит привязать номер к кабинету</b>\n" . h($w['acc']['name']) . ', ' . h($w['acc']['email']) . "\nНомер: " . phone_nice($phone) . "\nЕсли это её номер — подтвердите в кабинете, вкладка «Клиентки».");
    json_out(['ok' => true]);
}
if ($type === 'link_list') {
    if (!$admin) json_out(['ok' => false, 'relogin' => true]);
    $rows = client_db()->query('SELECT r.id, r.phone, r.t, a.name, a.email FROM link_reqs r JOIN accounts a ON a.id = r.acc ORDER BY r.t DESC')->fetchAll();
    [$doc] = doc_load(false);
    foreach ($rows as &$r) {  // как клиентка с этим номером записана у Марины
        $r['known'] = ''; foreach ($doc['bookings'] as $b) if (digits_phone($b['phone'] ?? '') === $r['phone'] && empty($b['anon'])) $r['known'] = $b['name'] ?? '';
        $r['phone'] = phone_nice($r['phone']);
    }
    unset($r);
    $cnt = (int)client_db()->query('SELECT COUNT(*) FROM accounts WHERE verified = 1')->fetchColumn();
    $news = (int)client_db()->query('SELECT COUNT(*) FROM accounts WHERE verified = 1 AND news = 1')->fetchColumn();
    json_out(['ok' => true, 'reqs' => $rows, 'accounts' => $cnt, 'news' => $news]);
}
if ($type === 'link_ok') {
    if (!$admin) json_out(['ok' => false, 'relogin' => true]);
    $db = client_db(); $q = $db->prepare('SELECT * FROM link_reqs WHERE id = ?'); $q->execute([(int)($in['id'] ?? 0)]); $r = $q->fetch();
    if (!$r) fail('Запрос уже обработан.');
    $db->prepare('DELETE FROM link_reqs WHERE id = ?')->execute([$r['id']]);
    if (!empty($in['yes'])) {
        $db->prepare('UPDATE accounts SET phone = ? WHERE id = ?')->execute([$r['phone'], $r['acc']]);
        acc_merge($r['acc'], $r['phone']);
        cnote($r['acc'], '📱', 'Номер привязан', 'Марина подтвердила номер ' . phone_nice($r['phone']) . ' — записи по нему теперь в кабинете.', '#kabinet');
    } else cnote($r['acc'], '📱', 'Номер не привязан', 'Марина не подтвердила номер ' . phone_nice($r['phone']) . '. Если это ошибка — напишите ей.', '#kabinet');
    json_out(['ok' => true]);
}
// ---------- рассылка от Марины всем клиенткам с кабинетом ----------
if ($type === 'broadcast') {
    if (!$admin) json_out(['ok' => false, 'relogin' => true]);
    $title = clean($in['title'] ?? '', 80); $text = mb_substr(trim(strip_tags((string)($in['text'] ?? ''))), 0, 1000);
    if (mb_strlen($title) < 2 || mb_strlen($text) < 2) fail('Напишите заголовок и текст.');
    client_db()->prepare('INSERT INTO cnotes (acc, icon, title, text, link, t) VALUES (0, ?, ?, ?, ?, ?)')->execute(['💌', $title, $text, '', time()]);
    require_once __DIR__ . '/push.php'; push_clients(push_note('💌 ' . $title, $text, './#kabinet'));
    $sent = 0;
    if (!empty($in['mail'])) foreach (client_db()->query('SELECT email FROM accounts WHERE verified = 1 AND news = 1') as $a)
        if (send_mail($a['email'], $title, h($text) . "\n\n<small>Вы получили письмо, потому что согласились получать новости студии. Отписаться — в личном кабинете на сайте.</small>")) $sent++;
    $cnt = (int)client_db()->query('SELECT COUNT(*) FROM accounts WHERE verified = 1')->fetchColumn();
    json_out(['ok' => true, 'accounts' => $cnt, 'mailed' => $sent]);
}

// Всё о клиентке: записи (будущие и история), обучение, сертификаты
if ($type === 'client_data') {
    $w = client_who($in['ctoken'] ?? ''); if (!$w) json_out(['ok' => false, 'relogin' => true]);
    $phone = $w['phone']; $acc = $w['acc'];
    [$doc, $v] = doc_load(true); if (doc_expire($doc)) $v = doc_save($doc, $v); else doc_abort();
    $mine = client_mine($w);
    $bk = array_values(array_map(fn($b) => array_intersect_key($b, array_flip(['id', 'date', 'start', 'dur', 'svc', 'remove', 'french', 'status', 'price', 'phone', 'name', 'remind', 'email', 'by', 'movedBy'])), array_filter($doc['bookings'], $mine)));
    $tr = array_values(array_map(fn($t) => array_intersect_key($t, array_flip(['id', 'course', 'date', 'days', 'status', 'price', 'pre', 'hold', 'claimed', 'phone', 'name', 'snote', 'mnote', 'by', 'refunded', 'paidAt'])), array_filter($doc['training'], $mine)));
    $ce = array_values(array_map(fn($c) => array_intersect_key($c, array_flip(['id', 'code', 'sum', 'balance', 'to', 'status', 'created', 'till', 'phone', 'email', 'name', 'claimed', 'hold'])) + ['spent' => array_map(fn($x) => ['d' => $x['d'] ?? '', 'sum' => (int)($x['sum'] ?? 0)], $c['spent'] ?? [])], array_filter($doc['certs'], $mine)));
    json_out(['ok' => true, 'phone' => $phone ? phone_nice($phone) : '', 'acc' => $acc ? ['email' => $acc['email'], 'name' => $acc['name']] : null, 'bookings' => $bk, 'training' => $tr, 'certs' => $ce, 'v' => $v, 'doc' => doc_public($doc)]);
}

// Клиентка в кабинете меняет почту для напоминаний (или отказывается от писем) — для всех будущих записей
if ($type === 'client_mail') {
    $w = client_who($in['ctoken'] ?? ''); if (!$w) json_out(['ok' => false, 'relogin' => true]);
    $own = client_mine($w);
    $m = mb_strtolower(trim((string)($in['mail'] ?? '')));
    if ($m !== '' && (!filter_var($m, FILTER_VALIDATE_EMAIL) || strlen($m) > 80)) fail('Проверьте адрес почты.');
    if ($m !== '' && !cfg('mail_from')) fail('Напоминания на почту пока не работают. Подключите Telegram или напишите Марине.');
    [$doc, $v] = doc_load(true); $ids = [];
    foreach ($doc['bookings'] as &$b) {
        if (!$own($b) || ($b['status'] ?? '') !== 'booked' || ($b['date'] ?? '') < date('Y-m-d')) continue;
        if ($m !== '') { $b['email'] = $m; $b['remind'] = 'mail'; } else { unset($b['email']); if (($b['remind'] ?? '') === 'mail') $b['remind'] = 'none'; }
        $ids[] = $b['id'];
    }
    unset($b);
    if (!$ids) { doc_abort(); fail('Будущих записей нет — почту можно будет указать при следующей записи.'); }
    $v = doc_save($doc, $v);
    $q = db()->prepare('UPDATE bookings SET email = ?, remind = CASE WHEN ? <> "" THEN "mail" WHEN remind = "mail" THEN "none" ELSE remind END, updated = ? WHERE id = ?');
    foreach ($ids as $id) $q->execute([$m, $m, time(), $id]);
    if ($m !== '') send_mail($m, 'Напоминания о записи будут приходить сюда', "Готово! 💅\nНапоминания о ваших записях в студию «М.А» будут приходить на эту почту: за 2 дня и в день визита.\n\nЕсли письмо попало в «Спам» — отметьте «Не спам».");
    json_out(['ok' => true, 'mail' => $m, 'count' => count($ids)]);
}

// ---------- обучение ----------
if ($type === 'train') {
    $r = is_array($in['rec'] ?? null) ? $in['rec'] : [];
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($r['id'] ?? '')); $phone = phone_ok($r['phone'] ?? ''); $name = clean($r['name'] ?? '', 40);
    $days = array_values(array_filter((array)($r['days'] ?? []), fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)));
    if (!$id || !$phone || mb_strlen($name) < 2 || !$days || count($days) > 10 || $days[0] < date('Y-m-d')) json_out(['ok' => false, 'error' => 'Проверьте данные заявки.']);
    [$doc, $v] = doc_load(true); doc_expire($doc);
    foreach ($doc['training'] as $t) if (digits_phone($t['phone'] ?? '') === $phone && ($t['status'] ?? '') === 'wait' && ($t['hold'] ?? 0) > now_ms())
        fail('На этот номер уже есть неоплаченная бронь. Оплатите её или дождитесь, пока она отменится.');
    foreach ($days as $d) if (!day_free_for_training($doc, $d)) fail('Эти даты только что заняли. Выберите другие.');
    $rec = ['id' => $id, 'course' => clean($r['course'] ?? '', 10), 'date' => $days[0], 'days' => $days, 'name' => $name, 'phone' => phone_nice($phone),
        'price' => (int)($r['price'] ?? 0), 'pre' => (int)($r['pre'] ?? 0), 'status' => 'wait', 'hold' => now_ms() + 30 * 60 * 1000];
    if (($cw = client_who($in['ctoken'] ?? '')) && $cw['acc']) $rec['owner'] = (int)$cw['acc']['id'];  // из кабинета — видна в нём
    $doc['training'][] = $rec;
    $title = clean($in['title'] ?? 'Курс', 80);
    doc_note($doc, '🎓', 'Бронь обучения — ждёт оплату', $name . ' · «' . $title . '» · ' . clean($in['when'] ?? '', 60) . ' · предоплата ' . number_format($rec['pre'], 0, ',', ' ') . ' ₽', ['tab' => 'req']);
    $v = doc_save($doc, $v);
    notify_admins("🎓 <b>Бронь обучения — ждёт оплату</b>\n" . h($name) . ', ' . phone_nice($phone) . "\n«" . h($title) . '», ' . h(clean($in['when'] ?? '', 60)) . "\nПредоплата " . number_format($rec['pre'], 0, ',', ' ') . " ₽ — 30 минут на оплату.");
    json_out(['ok' => true, 'v' => $v, 'doc' => doc_public($doc), 'rec' => $rec]);
}

// ---------- сертификаты ----------
if ($type === 'cert') {
    $r = is_array($in['rec'] ?? null) ? $in['rec'] : [];
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($r['id'] ?? '')); $phone = phone_ok($r['phone'] ?? ''); $name = clean($r['name'] ?? '', 40);
    $sum = (int)($r['sum'] ?? 0); $code = mb_strtoupper(clean($r['code'] ?? '', 12));
    if (!$id || !$phone || mb_strlen($name) < 2 || $sum < 500 || $sum > 100000 || strlen(cert_key($code)) < 6) json_out(['ok' => false, 'error' => 'Проверьте данные сертификата.']);
    $email = mail_ok($r['email'] ?? ''); if (!$email) fail('Укажите почту — на неё придёт сертификат.');
    [$doc, $v] = doc_load(true);
    foreach ($doc['certs'] as $c) if (cert_key($c['code'] ?? '') === cert_key($code)) fail('Попробуйте ещё раз.');
    $rec = ['id' => $id, 'code' => $code, 'sum' => $sum, 'balance' => $sum, 'to' => clean($r['to'] ?? '', 30), 'name' => $name, 'phone' => phone_nice($phone), 'email' => $email,
        'status' => 'wait', 'created' => (string)($r['created'] ?? date('Y-m-d')), 'till' => (string)($r['till'] ?? date('Y-m-d', strtotime('+1 year'))), 'hold' => now_ms() + 2 * 3600 * 1000];  // с этого времени неоплаченный можно удалить
    if (($cw = client_who($in['ctoken'] ?? '')) && $cw['acc']) $rec['owner'] = (int)$cw['acc']['id'];
    $doc['certs'][] = $rec;
    doc_note($doc, '🎁', 'Новый сертификат — ждёт оплату', '№ ' . $code . ' на ' . number_format($sum, 0, ',', ' ') . ' ₽ · ' . $name, ['tab' => 'req']);
    $v = doc_save($doc, $v);
    notify_admins("🎁 <b>Новый сертификат № " . h($code) . "</b> на " . number_format($sum, 0, ',', ' ') . " ₽\n" . h($name) . ', ' . phone_nice($phone) . ', ' . h($email) . "\nЖдёт оплату по СБП.");
    cert_mail($rec, false);
    json_out(['ok' => true, 'v' => $v, 'doc' => doc_public($doc), 'rec' => $rec]);
}

// Клиентка нажала «Я оплатил(а)»
if ($type === 'claim') {
    $kind = ($in['kind'] ?? '') === 't' ? 'training' : 'certs'; $id = (string)($in['id'] ?? ''); $phone = phone_ok($in['phone'] ?? '');
    [$doc, $v] = doc_load(true); doc_expire($doc);
    $hit = null; foreach ($doc[$kind] as $i => $x) if (($x['id'] ?? '') === $id && digits_phone($x['phone'] ?? '') === $phone && $phone) $hit = $i;
    if ($hit === null) fail('Заявка не найдена.');
    $x = &$doc[$kind][$hit];
    if (($x['status'] ?? '') === 'wait' && empty($x['claimed'])) {
        $x['claimed'] = now_ms();  // срок брони не продлеваем: 30 минут на всё
        if ($kind === 'training') { $t = '💳 Ученица оплатила — проверьте'; $txt = ($x['name'] ?? '') . ' · предоплата ' . number_format((int)($x['pre'] ?? 0), 0, ',', ' ') . ' ₽ · проверьте поступление в Сбере'; }
        else { $t = '💳 Сертификат оплачен — проверьте'; $txt = '№ ' . ($x['code'] ?? '') . ' на ' . number_format((int)($x['sum'] ?? 0), 0, ',', ' ') . ' ₽ · ' . ($x['name'] ?? '') . ' · проверьте поступление в Сбере'; }
        doc_note($doc, '💳', mb_substr($t, 2), $txt, ['tab' => 'req']);
        notify_admins('<b>' . h($t) . "</b>\n" . h($txt) . ($kind === 'training'
            ? "\nОтметьте в кабинете «Оплата пришла» до " . date('H:i', (int)($x['hold'] / 1000)) . " — иначе бронь удалится сама."
            : "\nКогда увидите деньги — нажмите в кабинете «Оплата пришла». Сертификат сам не удалится."));
    }
    unset($x);
    done($doc, $v);
}

// Страница подарка: сертификат по номеру (без данных покупателя)
if ($type === 'cert_get') {
    $key = cert_key($in['code'] ?? '');
    [$doc] = doc_load();
    foreach ($doc['certs'] as $c) if (strlen($key) >= 6 && cert_key($c['code'] ?? '') === $key)
        json_out(['ok' => true, 'cert' => array_intersect_key($c, array_flip(['id', 'code', 'sum', 'balance', 'to', 'status', 'created', 'till']))
            + ['spent' => array_map(fn($x) => ['d' => $x['d'] ?? '', 'sum' => (int)($x['sum'] ?? 0)], $c['spent'] ?? [])]]);  // списания без имён
    json_out(['ok' => false]);
}

// ---------- уведомления от браузера ----------
if ($type === 'push_key') { require_once __DIR__ . '/push.php'; json_out(['ok' => true, 'key' => vapid()['pub']]); }
if ($type === 'push_sub') {
    // телефон мастера (вошла в кабинет мастера) или клиентки (вошла в личный кабинет по почте)
    $acc = 0;
    if (!$admin) { $cw = client_who($in['ctoken'] ?? ''); if (!$cw) json_out(['ok' => false, 'error' => 'Войдите в кабинет заново', 'relogin' => true]); if (!$cw['acc']) fail('Уведомления на телефон работают для личного кабинета по почте. Зарегистрируйтесь — это минута.'); $acc = (int)$cw['acc']['id']; }
    require_once __DIR__ . '/push.php';
    $e = (string)($in['endpoint'] ?? '');
    if (!preg_match('#^https://[a-z0-9.-]+/#i', $e) || strlen($e) > 1000) json_out(['ok' => false, 'error' => 'Браузер не дал адрес для уведомлений']);
    $key = fn($k) => preg_match('/^[A-Za-z0-9_-]{16,120}$/', (string)($in[$k] ?? '')) ? (string)$in[$k] : null;  // ключи шифрования от браузера
    push_db()->prepare('INSERT OR REPLACE INTO push_subs (endpoint, created, seen, ua, acc, p256dh, auth) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$e, time(), now_ms(), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200), $acc, $key('p256dh'), $key('auth')]);
    json_out(['ok' => true]);
}
if ($type === 'push_unsub') {
    require_once __DIR__ . '/push.php';
    push_db()->prepare('DELETE FROM push_subs WHERE endpoint = ?')->execute([(string)($in['endpoint'] ?? '')]);
    json_out(['ok' => true]);
}
// Телефон получил пуш и спрашивает, что показать. Адрес подписки известен только этому телефону — он и есть пропуск.
if ($type === 'push_peek') {
    require_once __DIR__ . '/push.php';
    $q = push_db()->prepare('SELECT seen, acc FROM push_subs WHERE endpoint = ?'); $q->execute([(string)($in['endpoint'] ?? '')]);
    $row = $q->fetch(); if (!$row) { logline('push_peek: подписка не найдена (' . substr(md5((string)($in['endpoint'] ?? '')), 0, 6) . ')'); json_out(['ok' => false]); }
    $seen = (int)$row['seen'];
    if ((int)$row['acc'] > 0) {  // телефон клиентки — её уведомления и новости студии
        $a = acc_get((int)$row['acc']); if (!$a) json_out(['ok' => false]);
        $n = client_db()->prepare('SELECT icon, title, text, link, t FROM cnotes WHERE (acc = ? OR (acc = 0 AND t >= ?)) AND t * 1000 > ? - 999 ORDER BY t DESC LIMIT 3');
        $n->execute([$a['id'], (int)$a['created'], $seen]);
        push_db()->prepare('UPDATE push_subs SET seen = ? WHERE endpoint = ?')->execute([now_ms(), (string)$in['endpoint']]);
        json_out(['ok' => true, 'notes' => array_map(fn($x) => ['title' => trim($x['icon'] . ' ' . $x['title']), 'body' => (string)$x['text'], 'tag' => 'c' . $x['t'], 'url' => './' . ($x['link'] ?: '#kabinet')], $n->fetchAll())]);
    }
    [$doc] = doc_load();
    $new = array_values(array_filter($doc['notes'], fn($n) => ($n['t'] ?? 0) > (int)$seen));
    push_db()->prepare('UPDATE push_subs SET seen = ? WHERE endpoint = ?')->execute([now_ms(), (string)$in['endpoint']]);
    $out = array_map(fn($n) => ['title' => trim(($n['icon'] ?? '') . ' ' . ($n['title'] ?? '')), 'body' => (string)($n['text'] ?? ''), 'tag' => (string)($n['id'] ?? ''), 'url' => './#admin'], array_slice($new, 0, 3));
    json_out(['ok' => true, 'notes' => $out]);
}

if ($type === 'poke') { reply_then_continue(['ok' => true]); poll_run(25); exit; }

json_out(['ok' => false, 'error' => 'unknown type']);
