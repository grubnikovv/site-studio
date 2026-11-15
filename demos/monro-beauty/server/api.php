<?php
// Приём событий с сайта: новая запись, отмена, брони обучения и сертификаты.
// Сайт шлёт POST с JSON. Ответ — JSON.
require __DIR__ . '/bot.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allow = array_filter(array_map('trim', explode(',', (string)cfg('allow_origin'))));
if (!$allow || in_array($origin, $allow, true)) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);

// Не больше 20 запросов за 10 минут с одного адреса
$ip = $_SERVER['REMOTE_ADDR'] ?? '?';
$db = db();
$db->prepare('DELETE FROM hits WHERE t < ?')->execute([time() - 600]);
$n = $db->prepare('SELECT COUNT(*) FROM hits WHERE ip = ?'); $n->execute([$ip]);
if ((int)$n->fetchColumn() >= 20) json_out(['ok' => false, 'error' => 'Слишком много запросов. Попробуйте через несколько минут.'], 429);
$db->prepare('INSERT INTO hits (ip, t) VALUES (?, ?)')->execute([$ip, time()]);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!empty($in['website'])) json_out(['ok' => true]);  // ловушка для ботов

// Ключ ссылки на бота: без него чужую запись к себе не привязать
function clean($s, $max) { return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)$s))), 0, $max); }

$type = $in['type'] ?? '';

if ($type === 'booking') {
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($in['id'] ?? ''));
    $date = (string)($in['date'] ?? '');
    $start = (int)($in['start'] ?? -1);
    $phone = phone_ok($in['phone'] ?? '');
    $name = clean($in['name'] ?? '', 40);
    if (!$id || strlen($id) > 24 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $start < 0 || $start > 1440 || !$phone || mb_strlen($name) < 2)
        json_out(['ok' => false, 'error' => 'Проверьте данные записи.'], 400);
    if (strtotime($date) < strtotime('today') || strtotime($date) > strtotime('+70 days'))
        json_out(['ok' => false, 'error' => 'Дата вне диапазона записи.'], 400);
    // Одна запись в день на номер
    $q = $db->prepare('SELECT start FROM bookings WHERE phone = ? AND date = ? AND status = "booked" AND id <> ?');
    $q->execute([$phone, $date, $id]);
    if ($q->fetch()) json_out(['ok' => false, 'error' => 'На этот день у вас уже есть запись.'], 409);
    $remind = in_array($in['remind'] ?? '', ['tg', 'max', 'none'], true) ? $in['remind'] : 'none';
    $db->prepare('INSERT OR REPLACE INTO bookings (id, date, start, dur, svc, coat, name, phone, status, remind, created, updated)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, "booked", ?, ?, ?)')
       ->execute([$id, $date, $start, (int)($in['dur'] ?? 60), clean($in['svc'] ?? '', 80), empty($in['coat']) ? 0 : 1, $name, $phone, $remind, time(), time()]);
    notify_admins("📅 <b>Новая запись</b>\n" . h($name) . ', ' . phone_nice($phone) . "\n" . h(clean($in['svc'] ?? '', 80)) . "\n" . when_text($date, $start)
        . "\nНапоминания: " . ['tg' => 'Telegram', 'max' => 'MAX', 'none' => 'не нужны'][$remind]);
    json_out(['ok' => true, 'tg' => 'https://t.me/' . cfg('bot_name') . '?start=b_' . $id . '_' . link_key($id)]);
}

if ($type === 'cancel') {
    $id = preg_replace('/[^A-Za-z0-9]/', '', (string)($in['id'] ?? ''));
    $phone = phone_ok($in['phone'] ?? '');
    $q = $db->prepare('SELECT * FROM bookings WHERE id = ? AND phone = ?'); $q->execute([$id, $phone]);
    $b = $q->fetch();
    if (!$b) json_out(['ok' => false, 'error' => 'Запись не найдена.'], 404);
    $db->prepare('UPDATE bookings SET status = "cancelled", updated = ? WHERE id = ?')->execute([time(), $id]);
    notify_admins("❌ <b>Клиентка отменила запись</b>\n" . h($b['name']) . ', ' . phone_nice($b['phone']) . "\n" . h($b['svc']) . "\n" . when_text($b['date'], $b['start']) . "\nВремя освободилось.");
    json_out(['ok' => true]);
}

// Клиентка открывает бота с сайта: послушать Telegram полминуты, чтобы бот ответил сразу (если на хостинге нет cron)
if ($type === 'poke') { reply_then_continue(['ok' => true]); poll_run(25); exit; }

// Прочие события (обучение, сертификаты): просто уведомление Марине
if ($type === 'note') {
    $title = clean($in['title'] ?? '', 80); $text = clean($in['text'] ?? '', 300); $icon = clean($in['icon'] ?? '🔔', 4);
    if (!$title) json_out(['ok' => false], 400);
    notify_admins($icon . ' <b>' . h($title) . "</b>\n" . h($text));
    json_out(['ok' => true]);
}

json_out(['ok' => false, 'error' => 'unknown type'], 400);
