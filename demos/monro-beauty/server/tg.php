<?php
// Вебхук Telegram: сюда Telegram присылает сообщения и нажатия кнопок бота.
require __DIR__ . '/lib.php';

// Запрос должен прийти от Telegram с нашим секретом (задаётся в setup.php)
if (($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '') !== substr(hash('sha256', cfg('admin_key') . 'wh'), 0, 32)) { http_response_code(403); exit; }

$u = json_decode(file_get_contents('php://input'), true) ?: [];
$db = db();
function link_key($id) { return substr(hash_hmac('sha256', $id, cfg('admin_key')), 0, 10); }
function booking($id) { $q = db()->prepare('SELECT * FROM bookings WHERE id = ?'); $q->execute([$id]); return $q->fetch(); }

function booking_buttons($b) {
    return [[['text' => '✅ Подтверждаю', 'callback_data' => 'ok:' . $b['id']], ['text' => '🔁 Перенести', 'callback_data' => 'mv:' . $b['id']]]];
}

// ---------- сообщения ----------
if (isset($u['message'])) {
    $m = $u['message']; $chat = $m['chat']['id']; $text = trim($m['text'] ?? '');
    $first = $m['from']['first_name'] ?? '';

    // Марина подключает себя: /start admin_КЛЮЧ
    if ($text === '/start admin_' . cfg('admin_key')) {
        $db->prepare('INSERT OR REPLACE INTO admins (chat_id, name, added) VALUES (?, ?, ?)')->execute([$chat, $first, time()]);
        send($chat, "Готово! Сюда будут приходить уведомления о новых записях, отменах, бронях обучения и сертификатах.");
        exit;
    }

    // Клиентка пришла по ссылке с сайта: /start b_ID_КЛЮЧ
    if (preg_match('/^\/start b_([A-Za-z0-9]{1,24})_([0-9a-f]{10})$/', $text, $mm)) {
        $b = booking($mm[1]);
        if (!$b || !hash_equals(link_key($mm[1]), $mm[2])) { send($chat, 'Не нашли такую запись. Напишите Марине: +7 995 428-47-88.'); exit; }
        if ($b['status'] !== 'booked') { send($chat, 'Эта запись уже отменена. Записаться снова можно на сайте: ' . h(cfg('site_url'))); exit; }
        $db->prepare('UPDATE bookings SET chat_id = ?, updated = ? WHERE id = ?')->execute([$chat, time(), $b['id']]);
        $soon = booking_ts($b) - time() < 48 * 3600;
        send($chat, "Здравствуйте" . ($b['name'] ? ', ' . h($b['name']) : '') . "! 💅\n\nВы записаны в студию «М.А»:\n<b>" . h($b['svc']) . "</b>\n" . when_text($b['date'], $b['start'])
            . "\nМихайловка, ул. Обороны, 41\n\nНапомним " . ($soon ? '' : 'за 2 дня и ') . "за 2 часа до визита. Если планы изменятся — нажмите «Перенести».", booking_buttons($b));
        exit;
    }

    // Любое другое сообщение
    send($chat, "Здравствуйте! Это бот студии «М.А» — он присылает напоминания о записи.\n\nЗаписаться: " . h(cfg('site_url'))
        . "\nНаписать Марине: +7 995 428-47-88 (WhatsApp, Telegram, MAX)");
    exit;
}

// ---------- нажатия кнопок ----------
if (isset($u['callback_query'])) {
    $c = $u['callback_query']; $chat = $c['message']['chat']['id']; $data = $c['data'] ?? '';
    [$act, $id] = array_pad(explode(':', $data, 2), 2, '');
    $b = booking($id);
    if (!$b || (int)$b['chat_id'] !== (int)$chat) { tg('answerCallbackQuery', ['callback_query_id' => $c['id'], 'text' => 'Запись не найдена']); exit; }

    if ($act === 'ok') {
        if (!$b['confirmed']) {
            $db->prepare('UPDATE bookings SET confirmed = 1, updated = ? WHERE id = ?')->execute([time(), $id]);
            notify_admins('✅ <b>Клиентка подтвердила визит</b>' . "\n" . h($b['name']) . ', ' . when_text($b['date'], $b['start']));
        }
        tg('answerCallbackQuery', ['callback_query_id' => $c['id'], 'text' => 'Спасибо! Ждём вас 💗']);
        tg('editMessageReplyMarkup', ['chat_id' => $chat, 'message_id' => $c['message']['message_id'], 'reply_markup' => ['inline_keyboard' => []]]);
        send($chat, 'Спасибо, визит подтверждён! Ждём вас: ' . when_text($b['date'], $b['start']) . ' 💗');
    }
    if ($act === 'mv') {
        notify_admins('🔁 <b>Клиентка просит перенести запись</b>' . "\n" . h($b['name']) . ', ' . phone_nice($b['phone']) . "\nСейчас: " . when_text($b['date'], $b['start']) . "\nСвяжитесь с ней, чтобы выбрать новое время.");
        tg('answerCallbackQuery', ['callback_query_id' => $c['id'], 'text' => 'Марина свяжется с вами']);
        send($chat, "Хорошо! Марина скоро свяжется с вами, чтобы подобрать новое время.\nИли напишите ей сами: +7 995 428-47-88.");
    }
    exit;
}
