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
    doc_apply($doc, $ops);
    mirror_bookings($ops, $doc);
    done($doc, $v, [], true);
}

// ---------- запись клиентки ----------
if ($type === 'booking') {
    $r = is_array($in['rec'] ?? null) ? $in['rec'] : $in;  // старый формат: поля прямо в запросе
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($r['id'] ?? ''));
    $date = (string)($r['date'] ?? ''); $start = (int)($r['start'] ?? -1); $dur = max(15, min(600, (int)($r['dur'] ?? 60)));
    $phone = phone_ok($r['phone'] ?? ''); $name = clean($r['name'] ?? '', 40);
    if (!$id || strlen($id) > 24 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $start < 0 || $start > 1440 || !$phone || mb_strlen($name) < 2)
        json_out(['ok' => false, 'error' => 'Проверьте данные записи.']);
    if (strtotime($date) < strtotime('today') || strtotime($date) > strtotime('+70 days'))
        json_out(['ok' => false, 'error' => 'Дата вне диапазона записи.']);
    [$doc, $v] = doc_load(true);
    $mine = array_filter($doc['bookings'], fn($b) => ($b['status'] ?? '') === 'booked' && digits_phone($b['phone'] ?? '') === $phone && ($b['date'] ?? '') >= date('Y-m-d') && ($b['id'] ?? '') !== $id);
    foreach ($mine as $b) if ($b['date'] === $date) fail('На этот день у вас уже есть запись в ' . hm((int)$b['start']) . '.');
    if (count($mine) >= 3) fail('На этот номер уже 3 будущие записи. Чтобы записаться ещё, напишите Марине.');
    if (!slot_free($doc, $date, $start, $dur, $id)) fail('Это время только что заняли. Выберите другое.');
    $remind = in_array($r['remind'] ?? '', ['tg', 'max', 'mail', 'none'], true) ? $r['remind'] : 'none';
    $email = $remind === 'mail' ? mb_strtolower(trim((string)($r['email'] ?? ''))) : '';
    if ($remind === 'mail' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 80)) fail('Проверьте email для напоминаний.');
    $svcName = clean($in['svcName'] ?? ($r['svcName'] ?? ''), 80);
    $rec = ['id' => $id, 'date' => $date, 'start' => $start, 'dur' => $dur, 'svc' => clean($r['svc'] ?? '', 30), 'name' => $name, 'phone' => phone_nice($phone),
        'note' => clean($r['note'] ?? '', 200), 'price' => max(0, (int)($r['price'] ?? 0)), 'remove' => !empty($r['remove']), 'remind' => $remind, 'status' => 'booked', 'created' => now_ms()] + ($email ? ['email' => $email] : []);
    $doc['bookings'] = array_values(array_filter($doc['bookings'], fn($b) => ($b['id'] ?? '') !== $id));
    $doc['bookings'][] = $rec;
    doc_note($doc, '📅', 'Новая запись', $name . ' · ' . $svcName . ' · ' . when_text($date, $start), ['tab' => 'day', 'date' => $date]);
    // для бота и напоминаний
    db()->prepare('INSERT OR REPLACE INTO bookings (id, date, start, dur, svc, coat, name, phone, status, remind, email, created, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "booked", ?, ?, ?, ?)')
        ->execute([$id, $date, $start, $dur, $svcName, empty($r['coat']) ? 0 : 1, $name, $phone, $remind, $email, time(), time()]);
    $v = doc_save($doc, $v);
    notify_admins("📅 <b>Новая запись</b>\n" . h($name) . ', ' . phone_nice($phone) . "\n" . h($svcName) . "\n" . when_text($date, $start)
        . "\nНапоминания: " . ['tg' => 'Telegram', 'max' => 'MAX', 'mail' => 'почта ' . h($email), 'none' => 'не нужны'][$remind]);
    if ($remind === 'mail') {  // подтверждение записи письмом
        $site = rtrim((string)cfg('site_url'), '/');
        send_mail($email, 'Вы записаны в студию «М.А» — ' . when_text($date, $start), 'Здравствуйте, ' . h($name) . "! 💅\n\nВы записаны в студию «М.А»:\n<b>" . h($svcName) . "</b>\n<b>" . when_text($date, $start)
            . "</b>\nМихайловка, ул. Обороны, 41\n" . way_link() . "\n\nНапомним за 2 дня и за 2 часа до визита.\nЕсли планы изменятся — отмените запись на сайте в разделе <a href=\"" . h($site) . "/#zapis\">«Мои записи»</a> или напишите Марине: +7 995 428‑47‑88.");
    }
    json_out(['ok' => true, 'v' => $v, 'doc' => doc_public($doc), 'tg' => 'https://t.me/' . cfg('bot_name') . '?start=b_' . $id . '_' . link_key($id)]);
}

if ($type === 'cancel') {
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($in['id'] ?? '')); $phone = phone_ok($in['phone'] ?? '');
    [$doc, $v] = doc_load(true);
    $hit = null;
    foreach ($doc['bookings'] as $i => $b) if (($b['id'] ?? '') === $id && digits_phone($b['phone'] ?? '') === $phone && $phone) $hit = $i;
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
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($in['id'] ?? '')); $phone = phone_ok($in['phone'] ?? '');
    $date = (string)($in['date'] ?? ''); $start = (int)($in['start'] ?? -1);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $start < 0 || $start > 1440 || strtotime($date) > strtotime('+70 days')) fail('Проверьте новую дату и время.');
    [$doc, $v] = doc_load(true);
    $hit = null; foreach ($doc['bookings'] as $i => $b) if (($b['id'] ?? '') === $id && $phone && digits_phone($b['phone'] ?? '') === $phone) $hit = $i;
    if ($hit === null) fail('Запись не найдена.');
    $b = $doc['bookings'][$hit];
    if (($b['status'] ?? '') !== 'booked') fail('Эта запись уже отменена.');
    if (booking_ts($b) - time() < 48 * 3600) fail('До визита меньше 2 дней — перенести можно только через Марину: +7 995 428‑47‑88.');
    $dur = (int)($b['dur'] ?? 0) ?: max(15, min(600, (int)($in['dur'] ?? 60)));
    foreach ($doc['bookings'] as $o) if (($o['id'] ?? '') !== $id && ($o['status'] ?? '') === 'booked' && digits_phone($o['phone'] ?? '') === $phone && ($o['date'] ?? '') === $date)
        fail('На этот день у вас уже есть другая запись.');
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
// Всё о клиентке: записи (будущие и история), обучение, сертификаты
if ($type === 'client_data') {
    $phone = client_phone($in['ctoken'] ?? ''); if (!$phone) json_out(['ok' => false, 'relogin' => true]);
    [$doc, $v] = doc_load(true); if (doc_expire($doc)) $v = doc_save($doc, $v); else doc_abort();
    $mine = fn($x) => digits_phone($x['phone'] ?? '') === $phone;
    $bk = array_values(array_map(fn($b) => array_intersect_key($b, array_flip(['id', 'date', 'start', 'dur', 'svc', 'remove', 'status', 'price', 'phone', 'name', 'remind', 'by', 'movedBy'])), array_filter($doc['bookings'], $mine)));
    $tr = array_values(array_map(fn($t) => array_intersect_key($t, array_flip(['id', 'course', 'date', 'days', 'status', 'price', 'pre', 'hold', 'claimed', 'phone', 'name'])), array_filter($doc['training'], $mine)));
    $ce = array_values(array_map(fn($c) => array_intersect_key($c, array_flip(['id', 'code', 'sum', 'balance', 'to', 'status', 'created', 'till', 'phone'])) + ['spent' => array_map(fn($x) => ['d' => $x['d'] ?? '', 'sum' => (int)($x['sum'] ?? 0)], $c['spent'] ?? [])], array_filter($doc['certs'], $mine)));
    json_out(['ok' => true, 'phone' => phone_nice($phone), 'bookings' => $bk, 'training' => $tr, 'certs' => $ce, 'v' => $v, 'doc' => doc_public($doc)]);
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
    [$doc, $v] = doc_load(true);
    foreach ($doc['certs'] as $c) if (cert_key($c['code'] ?? '') === cert_key($code)) fail('Попробуйте ещё раз.');
    $rec = ['id' => $id, 'code' => $code, 'sum' => $sum, 'balance' => $sum, 'to' => clean($r['to'] ?? '', 30), 'name' => $name, 'phone' => phone_nice($phone),
        'status' => 'wait', 'created' => (string)($r['created'] ?? date('Y-m-d')), 'till' => (string)($r['till'] ?? date('Y-m-d', strtotime('+1 year'))), 'hold' => now_ms() + 30 * 60 * 1000];
    $doc['certs'][] = $rec;
    doc_note($doc, '🎁', 'Новый сертификат — ждёт оплату', '№ ' . $code . ' на ' . number_format($sum, 0, ',', ' ') . ' ₽ · ' . $name, ['tab' => 'req']);
    $v = doc_save($doc, $v);
    notify_admins("🎁 <b>Новый сертификат № " . h($code) . "</b> на " . number_format($sum, 0, ',', ' ') . " ₽\n" . h($name) . ', ' . phone_nice($phone) . "\nЖдёт оплату по СБП.");
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
        notify_admins('<b>' . h($t) . "</b>\n" . h($txt) . "\nОтметьте в кабинете «Оплата пришла» до " . date('H:i', (int)($x['hold'] / 1000)) . " — иначе бронь удалится сама.");
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
    if (!$admin) json_out(['ok' => false, 'error' => 'Войдите в кабинет заново', 'relogin' => true]);
    require_once __DIR__ . '/push.php';
    $e = (string)($in['endpoint'] ?? '');
    if (!preg_match('#^https://[a-z0-9.-]+/#i', $e) || strlen($e) > 1000) json_out(['ok' => false, 'error' => 'Браузер не дал адрес для уведомлений']);
    push_db()->prepare('INSERT OR REPLACE INTO push_subs (endpoint, created, seen, ua) VALUES (?, ?, ?, ?)')->execute([$e, time(), now_ms(), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200)]);
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
    $q = push_db()->prepare('SELECT seen FROM push_subs WHERE endpoint = ?'); $q->execute([(string)($in['endpoint'] ?? '')]);
    $seen = $q->fetchColumn();
    if ($seen === false) json_out(['ok' => false]);
    [$doc] = doc_load();
    $new = array_values(array_filter($doc['notes'], fn($n) => ($n['t'] ?? 0) > (int)$seen));
    push_db()->prepare('UPDATE push_subs SET seen = ? WHERE endpoint = ?')->execute([now_ms(), (string)$in['endpoint']]);
    $out = array_map(fn($n) => ['title' => trim(($n['icon'] ?? '') . ' ' . ($n['title'] ?? '')), 'body' => (string)($n['text'] ?? ''), 'tag' => (string)($n['id'] ?? '')], array_slice($new, 0, 3));
    json_out(['ok' => true, 'notes' => $out]);
}

if ($type === 'poke') { reply_then_continue(['ok' => true]); poll_run(25); exit; }

json_out(['ok' => false, 'error' => 'unknown type']);
