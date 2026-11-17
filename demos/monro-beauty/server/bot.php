<?php
// Логика бота: обработка сообщений и нажатий кнопок. Используют tg.php (вебхук) и poll.php (опрос раз в минуту).
require_once __DIR__ . '/lib.php';

// Ссылка для Марины из её кабинета на сайте (сам ключ кабинета в ссылку не попадает)
function admin_tg_code() { return 'adm_' . substr(hash_hmac('sha256', 'admin-tg', (string)cfg('admin_key')), 0, 16); }
function link_key($id) { return substr(hash_hmac('sha256', $id, cfg('admin_key')), 0, 10); }
function booking($id) { $q = db()->prepare('SELECT * FROM bookings WHERE id = ?'); $q->execute([$id]); return $q->fetch(); }

function booking_buttons($b) {
    return [[['text' => '✅ Подтверждаю', 'callback_data' => 'ok:' . $b['id']], ['text' => '🔁 Перенести', 'callback_data' => 'mv:' . $b['id']]]];
}

function handle_update($u) {
    $db = db();
    // ---------- сообщения ----------
    if (isset($u['message'])) {
        $m = $u['message']; $chat = $m['chat']['id']; $text = trim($m['text'] ?? '');
        $first = $m['from']['first_name'] ?? '';

        // Марина подключает себя: /start admin_КЛЮЧ
        if ($text === '/start admin_' . cfg('admin_key') || $text === '/start ' . admin_tg_code()) {
            $db->prepare('INSERT OR REPLACE INTO admins (chat_id, name, added) VALUES (?, ?, ?)')->execute([$chat, $first, time()]);
            send($chat, "Готово! Сюда будут приходить уведомления о новых записях, отменах, бронях обучения и сертификатах.");
            return;
        }

        // Клиентка подключает Telegram в личном кабинете: /start a_ID_КЛЮЧ
        if (preg_match('/^\/start a_(\d{1,9})_([0-9a-f]{10})$/', $text, $mm)) {
            require_once __DIR__ . '/store.php';
            $a = acc_get((int)$mm[1]);
            if (!$a || !hash_equals(substr(acc_tg_link($mm[1]), -10), $mm[2])) { send($chat, 'Ссылка устарела. Откройте личный кабинет на сайте и нажмите «Подключить Telegram» ещё раз.'); return; }
            $n = acc_tg_bind($a, $chat);
            send($chat, "Готово" . ($a['name'] ? ', ' . h(strtok($a['name'], ' ')) : '') . "! 💅\n\nТеперь сюда будут приходить напоминания о записях, переносы, сертификаты и новости студии «М.А»."
                . ($n ? "\nБудущих записей подключено: " . $n . '.' : '') . "\n\nОтключить можно в личном кабинете на сайте.");
            notify_admins('📲 Клиентка подключила Telegram: ' . h($a['name'] ?: $a['email']));
            return;
        }

        // Клиентка пришла по ссылке с сайта: /start b_ID_КЛЮЧ
        if (preg_match('/^\/start b_([A-Za-z0-9]{1,24})_([0-9a-f]{10})$/', $text, $mm)) {
            $b = booking($mm[1]);
            if (!$b || !hash_equals(link_key($mm[1]), $mm[2])) { send($chat, 'Не нашли такую запись. Напишите Марине: +7 995 428-47-88.'); return; }
            if ($b['status'] !== 'booked') { send($chat, 'Эта запись уже отменена. Записаться снова можно на сайте: ' . h(cfg('site_url'))); return; }
            $db->prepare('UPDATE bookings SET chat_id = ?, updated = ? WHERE id = ?')->execute([$chat, time(), $b['id']]);
            $soon = booking_ts($b) - time() < 48 * 3600;
            send($chat, "Здравствуйте" . ($b['name'] ? ', ' . h($b['name']) : '') . "! 💅\n\nВы записаны в студию «М.А»:\n<b>" . h($b['svc']) . "</b>\n" . when_text($b['date'], $b['start'])
                . "\nМихайловка, ул. Обороны, 41\n" . way_link() . "\n\nНапомним " . ($soon ? '' : 'за 2 дня и ') . "за 2 часа до визита. Если планы изменятся — нажмите «Перенести».", booking_buttons($b));
            return;
        }

        // Любое другое сообщение
        send($chat, "Здравствуйте! Это бот студии «М.А» — он присылает напоминания о записи.\n\nЗаписаться: " . h(cfg('site_url'))
            . "\nНаписать Марине: +7 995 428-47-88 (WhatsApp, Telegram, MAX)");
        return;
    }

    // ---------- нажатия кнопок ----------
    if (isset($u['callback_query'])) {
        $c = $u['callback_query']; $chat = $c['message']['chat']['id']; $data = $c['data'] ?? '';
        [$act, $id] = array_pad(explode(':', $data, 2), 2, '');
        $b = booking($id);
        if (!$b || (int)$b['chat_id'] !== (int)$chat) { tg('answerCallbackQuery', ['callback_query_id' => $c['id'], 'text' => 'Запись не найдена']); return; }

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
            $far = booking_ts($b) - time() >= 48 * 3600;
            send($chat, "Хорошо! Марина скоро свяжется с вами, чтобы подобрать новое время.\nИли напишите ей сами: +7 995 428-47-88."
                . ($far ? "\n\nМожно перенести и самой, на сайте: <a href=\"" . h(rtrim((string)cfg('site_url'), '/') . '/#moi-zapisi') . "\">Мои записи</a> → «Перенести»." : ''));
        }
        return;
    }
}

// Забрать новые сообщения Telegram и ответить на них. Работает $secs секунд, вторая копия не запускается.
function poll_run($secs) {
    $lock = fopen(dirname(cfg('db_path')) . '/poll.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return;
    $db = db();
    $db->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)');
    $get = function ($k) use ($db) { $q = $db->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute([$k]); return $q->fetchColumn(); };
    $put = function ($k, $v) use ($db) { $db->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute([$k, (string)$v]); };
    if (!$get('polling')) { tg('deleteWebhook', ['drop_pending_updates' => false]); $put('polling', 1); }
    flush_outbox();
    $offset = (int)$get('offset');
    $until = time() + $secs;
    while (time() < $until) {
        $r = tg('getUpdates', ['offset' => $offset, 'timeout' => max(1, min(20, $until - time())), 'allowed_updates' => ['message', 'callback_query']]);
        if (!$r || empty($r['ok'])) { sleep(3); continue; }
        foreach ($r['result'] as $u) {
            $offset = $u['update_id'] + 1; $put('offset', $offset);
            try { handle_update($u); } catch (Throwable $e) { logline('update error: ' . $e->getMessage()); }
        }
    }
    flock($lock, LOCK_UN);
}
