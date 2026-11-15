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

// Не больше 20 действий за 10 минут с одного адреса (чтение базы и работа кабинета — без ограничения)
if (!in_array($type, ['state', 'cert_get'], true) && !$admin) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    $db = store_init();
    $db->prepare('DELETE FROM hits WHERE t < ?')->execute([time() - 600]);
    $n = $db->prepare('SELECT COUNT(*) FROM hits WHERE ip = ?'); $n->execute([$ip]);
    if ((int)$n->fetchColumn() >= 20) json_out(['ok' => false, 'error' => 'Слишком много запросов. Попробуйте через несколько минут.']);
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
    $pass = (string)cfg('cabinet_pass');
    if (strlen($pass) < 6 || !hash_equals($pass, (string)($in['pass'] ?? ''))) { sleep(1); json_out(['ok' => false, 'error' => 'Неверный пароль']); }
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
    $remind = in_array($r['remind'] ?? '', ['tg', 'max', 'none'], true) ? $r['remind'] : 'none';
    $svcName = clean($in['svcName'] ?? ($r['svcName'] ?? ''), 80);
    $rec = ['id' => $id, 'date' => $date, 'start' => $start, 'dur' => $dur, 'svc' => clean($r['svc'] ?? '', 30), 'name' => $name, 'phone' => phone_nice($phone),
        'note' => clean($r['note'] ?? '', 200), 'price' => max(0, (int)($r['price'] ?? 0)), 'remove' => !empty($r['remove']), 'remind' => $remind, 'status' => 'booked', 'created' => now_ms()];
    $doc['bookings'] = array_values(array_filter($doc['bookings'], fn($b) => ($b['id'] ?? '') !== $id));
    $doc['bookings'][] = $rec;
    doc_note($doc, '📅', 'Новая запись', $name . ' · ' . $svcName . ' · ' . when_text($date, $start), ['tab' => 'day', 'date' => $date]);
    // для бота и напоминаний
    db()->prepare('INSERT OR REPLACE INTO bookings (id, date, start, dur, svc, coat, name, phone, status, remind, created, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "booked", ?, ?, ?)')
        ->execute([$id, $date, $start, $dur, $svcName, empty($r['coat']) ? 0 : 1, $name, $phone, $remind, time(), time()]);
    $v = doc_save($doc, $v);
    notify_admins("📅 <b>Новая запись</b>\n" . h($name) . ', ' . phone_nice($phone) . "\n" . h($svcName) . "\n" . when_text($date, $start)
        . "\nНапоминания: " . ['tg' => 'Telegram', 'max' => 'MAX', 'none' => 'не нужны'][$remind]);
    json_out(['ok' => true, 'v' => $v, 'doc' => doc_public($doc), 'tg' => 'https://t.me/' . cfg('bot_name') . '?start=b_' . $id . '_' . link_key($id)]);
}

if ($type === 'cancel') {
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($in['id'] ?? '')); $phone = phone_ok($in['phone'] ?? '');
    [$doc, $v] = doc_load(true);
    $hit = null;
    foreach ($doc['bookings'] as $i => $b) if (($b['id'] ?? '') === $id && digits_phone($b['phone'] ?? '') === $phone && $phone) $hit = $i;
    if ($hit === null) fail('Запись не найдена.');
    $b = &$doc['bookings'][$hit];
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

// ---------- обучение ----------
if ($type === 'train') {
    $r = is_array($in['rec'] ?? null) ? $in['rec'] : [];
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($r['id'] ?? '')); $phone = phone_ok($r['phone'] ?? ''); $name = clean($r['name'] ?? '', 40);
    $days = array_values(array_filter((array)($r['days'] ?? []), fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)));
    if (!$id || !$phone || mb_strlen($name) < 2 || !$days || count($days) > 10 || $days[0] < date('Y-m-d')) json_out(['ok' => false, 'error' => 'Проверьте данные заявки.']);
    [$doc, $v] = doc_load(true); doc_expire($doc);
    foreach ($doc['training'] as $t) if (digits_phone($t['phone'] ?? '') === $phone && ($t['status'] ?? '') === 'wait' && ($t['hold'] ?? 0) > now_ms())
        fail('На этот номер уже есть неоплаченная бронь. Оплатите её или дождитесь, пока она отменится.');
    foreach ($days as $d) {
        if (!empty($doc['dayoff'][$d]) || day_taken_by_training($doc, $d)) fail('Эти даты только что заняли. Выберите другие.');
        foreach ($doc['bookings'] as $b) if (($b['date'] ?? '') === $d && ($b['status'] ?? '') === 'booked') fail('Эти даты только что заняли. Выберите другие.');
    }
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
    [$doc, $v] = doc_load(true);
    $hit = null; foreach ($doc[$kind] as $i => $x) if (($x['id'] ?? '') === $id && digits_phone($x['phone'] ?? '') === $phone && $phone) $hit = $i;
    if ($hit === null) fail('Заявка не найдена.');
    $x = &$doc[$kind][$hit];
    if (($x['status'] ?? '') === 'wait' && empty($x['claimed'])) {
        $x['claimed'] = now_ms(); $x['hold'] = now_ms() + 24 * 3600 * 1000;
        if ($kind === 'training') { $t = '💳 Ученица оплатила — проверьте'; $txt = ($x['name'] ?? '') . ' · предоплата ' . number_format((int)($x['pre'] ?? 0), 0, ',', ' ') . ' ₽ · проверьте поступление в Сбере'; }
        else { $t = '💳 Сертификат оплачен — проверьте'; $txt = '№ ' . ($x['code'] ?? '') . ' на ' . number_format((int)($x['sum'] ?? 0), 0, ',', ' ') . ' ₽ · ' . ($x['name'] ?? '') . ' · проверьте поступление в Сбере'; }
        doc_note($doc, '💳', mb_substr($t, 2), $txt, ['tab' => 'req']);
        notify_admins('<b>' . h($t) . "</b>\n" . h($txt) . "\nОтметьте в кабинете «Оплата пришла».");
    }
    unset($x);
    done($doc, $v);
}

// Страница подарка: сертификат по номеру (без данных покупателя)
if ($type === 'cert_get') {
    $key = cert_key($in['code'] ?? '');
    [$doc] = doc_load();
    foreach ($doc['certs'] as $c) if (strlen($key) >= 6 && cert_key($c['code'] ?? '') === $key)
        json_out(['ok' => true, 'cert' => array_intersect_key($c, array_flip(['id', 'code', 'sum', 'balance', 'to', 'status', 'created', 'till', 'spent']))]);
    json_out(['ok' => false]);
}

if ($type === 'poke') { reply_then_continue(['ok' => true]); poll_run(25); exit; }

json_out(['ok' => false, 'error' => 'unknown type']);
