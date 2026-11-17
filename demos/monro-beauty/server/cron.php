<?php
// Напоминания клиенткам. Запускать каждые 10 минут (крон на хостинге):
//   php /путь/к/server/cron.php
require_once __DIR__ . '/lib.php';
if (PHP_SAPI !== 'cli' && ($_GET['key'] ?? '') !== cfg('admin_key')) { http_response_code(403); exit; }

$db = db(); $now = time(); $sent = 0;
$site = h(cfg('site_url'));
$q = $db->query('SELECT * FROM bookings WHERE status = "booked" AND (chat_id IS NOT NULL OR (remind = "mail" AND email <> "")) AND (sent_2d = 0 OR sent_2h = 0 OR sent_corr = 0)');
foreach ($q as $b) {
    $ts = booking_ts($b); $id = $b['id'];
    $btn = [[['text' => '✅ Подтверждаю', 'callback_data' => 'ok:' . $id], ['text' => '🔁 Перенести', 'callback_data' => 'mv:' . $id]]];

    // За 2 дня — только если записались заранее, больше чем за 2 дня
    if (!$b['sent_2d'] && $now >= $ts - 48 * 3600 && $now < $ts - 6 * 3600) {
        if ($b['created'] <= $ts - 48 * 3600 + 600)
            notify_client($b, 'Через 2 дня ждём вас в студии «М.А»', "Напоминаем 💅\nЧерез 2 дня вы записаны в студию «М.А»:\n<b>" . h($b['svc']) . "</b>\n" . when_text($b['date'], $b['start']) . "\nМихайловка, ул. Обороны, 41\n" . way_link(), $b['confirmed'] ? null : $btn);
        $db->prepare('UPDATE bookings SET sent_2d = 1 WHERE id = ?')->execute([$id]); $sent++;
    }
    // За 2 часа
    if (!$b['sent_2h'] && $now >= $ts - 2 * 3600 && $now < $ts) {
        notify_client($b, 'Ждём вас сегодня в ' . hm((int)$b['start']), "Ждём вас сегодня в <b>" . hm((int)$b['start']) . "</b> 💗\n" . h($b['svc']) . "\nМихайловка, ул. Обороны, 41\n" . way_link() . "\n\nЕсли опаздываете — напишите Марине: +7 995 428-47-88.");
        $db->prepare('UPDATE bookings SET sent_2h = 1 WHERE id = ?')->execute([$id]); $sent++;
    }
    // О коррекции — через 3 недели после визита с покрытием, днём, если клиентка ещё не записалась снова
    if (!$b['sent_corr'] && $b['coat'] && $now >= $ts + 21 * 86400 && (int)date('G') >= 11 && (int)date('G') < 19) {
        $n = $db->prepare('SELECT COUNT(*) FROM bookings WHERE phone = ? AND date > ? AND status = "booked"'); $n->execute([$b['phone'], $b['date']]);
        if (!(int)$n->fetchColumn())
            notify_client($b, 'Пора обновить покрытие 💅', "Прошло 3 недели после вашего визита 💅\nСамое время обновить покрытие, пока ногти не отросли.\n\nЗаписаться онлайн: " . $site . "#zapis");
        $db->prepare('UPDATE bookings SET sent_corr = 1 WHERE id = ?')->execute([$id]); $sent++;
    }
}

// Клиенткам с личным кабинетом — напоминания в колокольчик и на телефон (даже если запись внесла Марина вручную)
try {
    require_once __DIR__ . '/store.php';
    [$doc] = doc_load(false); client_db();
    foreach ($doc['bookings'] as $b) {
        if (($b['status'] ?? '') !== 'booked' || !empty($b['anon']) || empty($b['date'])) continue;
        $ts = strtotime($b['date'] . ' 00:00') + (int)$b['start'] * 60; if ($ts < $now) continue;
        $acc = null; $id = (string)$b['id'];
        foreach ([['2d', 48 * 3600, 6 * 3600], ['2h', 2 * 3600, 0]] as [$k, $from, $to]) {
            if ($now < $ts - $from || $now >= $ts - $to || kv_get('cr' . $k . '_' . $id)) continue;
            kv_put('cr' . $k . '_' . $id, 1);
            $acc = $acc ?? acc_of($b); if (!$acc) break;
            $q = $db->prepare('SELECT chat_id FROM bookings WHERE id = ?'); $q->execute([$id]); $tg = !$q->fetchColumn();  // в Telegram уже напомнит бот по записи — не дублировать
            if ($k === '2d') cnote($acc, '🔔', 'Через 2 дня ждём вас', when_text($b['date'], (int)$b['start']) . '. Михайловка, ул. Обороны, 41. Перенести или отменить — в кабинете сегодня, позже — только через Марину.', '#kabinet', $tg);
            else cnote($acc, '💗', 'Ждём вас сегодня в ' . hm((int)$b['start']), 'Михайловка, ул. Обороны, 41. Если опаздываете — напишите Марине: +7 995 428-47-88.', '#kabinet', $tg);
            $sent++;
        }
    }
} catch (Throwable $e) { logline('cron cnote: ' . $e->getMessage()); }

// Срок хранения данных — 3 года после визита (политика конфиденциальности)
$db->prepare('DELETE FROM bookings WHERE date < ?')->execute([date('Y-m-d', strtotime('-3 years'))]);
if (PHP_SAPI === 'cli') echo "ok, отправлено: $sent\n";
