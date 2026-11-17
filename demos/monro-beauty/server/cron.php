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

// «Пора на коррекцию»: как список в кабинете мастера — последний визит с покрытием, прошло 3–4 недели, новой записи нет.
// Раз в день (11:00–19:00): Марине — о новых клиентках в списке (колокольчик, Telegram, почта); клиенткам с кабинетом — напоминание.
try {
    require_once __DIR__ . '/store.php';
    $hr = (int)date('G'); $today = date('Y-m-d');
    if ((($hr >= 11 && $hr < 19) || (PHP_SAPI === 'cli' && getenv('CORR_FORCE'))) && kv_get('corr_day') !== $today) {
        kv_put('corr_day', $today);
        $CORR = ['gel' => 21, 'str' => 21, 'cor' => 21, 'cor4' => 21, 'ext' => 21, 'ext4' => 21, 'pedg' => 28, 'pedp' => 28];
        $SVN = ['gel' => 'маникюра с гель-лаком', 'str' => 'маникюра с укреплением', 'cor' => 'коррекции', 'cor4' => 'коррекции', 'ext' => 'наращивания', 'ext4' => 'наращивания', 'pedg' => 'педикюра с гель-лаком', 'pedp' => 'педикюра'];
        [$doc] = doc_load(false); $cl = [];
        foreach ($doc['bookings'] as $b) {
            if (!empty($b['anon']) || !empty($b['example']) || empty($b['date'])) continue;
            $key = digits_phone($b['phone'] ?? '') ?: trim((string)($b['name'] ?? '')); if ($key === '') continue;
            $c = &$cl[$key]; $c = $c ?? ['last' => null, 'future' => false];
            if (($b['status'] ?? '') === 'booked' && $b['date'] >= $today) $c['future'] = true;
            if (($b['status'] ?? '') === 'came' && (!$c['last'] || $b['date'] . str_pad((string)$b['start'], 4, '0', STR_PAD_LEFT) > $c['last']['date'] . str_pad((string)$c['last']['start'], 4, '0', STR_PAD_LEFT))) $c['last'] = $b;
            unset($c);
        }
        $seen = json_decode((string)kv_get('corr_seen'), true) ?: []; $new = [];
        foreach ($cl as $key => $c) {
            $l = $c['last']; if (!$l || $c['future'] || empty($CORR[$l['svc'] ?? ''])) continue;
            $days = (int)round((strtotime($today . ' 12:00') - strtotime($l['date'] . ' 12:00')) / 86400); $need = $CORR[$l['svc']];
            if ($days < $need - 3) continue;
            if (($seen[$key] ?? '') !== $l['date']) { $seen[$key] = $l['date']; $new[] = [$l, $days]; }
            // клиентке с личным кабинетом — напоминание (если бот по этой записи уже не напомнил)
            if ($days >= $need && !kv_get('ccorr_' . $l['id']) && ($acc = acc_of($l))) {
                kv_put('ccorr_' . $l['id'], 1);
                $q = db()->prepare('SELECT sent_corr FROM bookings WHERE id = ? AND (chat_id IS NOT NULL OR remind = "mail")'); $q->execute([(string)$l['id']]);
                if (!$q->fetchColumn()) cnote($acc, '💅', 'Пора обновить покрытие', 'Прошло ' . round($days / 7) . ' нед. после ' . ($SVN[$l['svc']] ?? 'визита') . '. Самое время записаться, пока ногти не отросли.', '#zapis');
            }
        }
        // старые отметки (клиентка давно не в списке) — не храним
        foreach ($seen as $k => $d) if ($d < date('Y-m-d', strtotime('-120 days'))) unset($seen[$k]);
        kv_put('corr_seen', json_encode($seen, JSON_UNESCAPED_UNICODE));
        if ($new) {
            usort($new, fn($a, $b) => $b[1] <=> $a[1]);
            $lines = array_map(fn($x) => '• ' . h($x[0]['name'] ?? '') . ', ' . (digits_phone($x[0]['phone'] ?? '') ? phone_nice(digits_phone($x[0]['phone'])) : '') . ' — ' . $x[1] . ' дн. после ' . ($SVN[$x[0]['svc']] ?? 'визита'), $new);
            notify_admins('💅 <b>Пора на коррекцию: ' . count($new) . "</b>\n" . implode("\n", $lines) . "\n\nНаписать клиентке или записать — в кабинете, вкладка «Клиентки»: " . h(rtrim((string)cfg('site_url'), '/') . '/#admin'));
            [$doc2, $v2] = doc_load(true);
            doc_note($doc2, '💅', 'Пора на коррекцию: ' . count($new), implode(', ', array_map(fn($x) => ($x[0]['name'] ?? '') . ' (' . $x[1] . ' дн.)', array_slice($new, 0, 6))) . (count($new) > 6 ? ' и ещё ' . (count($new) - 6) : '') . '. Список — во вкладке «Клиентки».', ['tab' => 'clients']);
            doc_save($doc2, $v2);
        }
    }
} catch (Throwable $e) { logline('cron corr: ' . $e->getMessage()); }

// Срок хранения данных — 3 года после визита (политика конфиденциальности)
$db->prepare('DELETE FROM bookings WHERE date < ?')->execute([date('Y-m-d', strtotime('-3 years'))]);
if (PHP_SAPI === 'cli') echo "ok, отправлено: $sent\n";
