<?php
// Резервные копии базы (записи, личные кабинеты клиенток, сертификаты, обучение, график — всё в одном файле bot.sqlite).
// 1) Каждую ночь — проверка базы и копия на хостинге в data/backups (хранится 30 дней); копия тоже проверяется.
//    Фото профиля — зеркало в data/backups/av.
// 2) Раз в неделю (понедельник) — полная копия Марине на почту: данные лежат ещё и вне хостинга.
// Запускается из poll.php (cron каждую минуту), сама решает, пора ли.
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/store.php';

const BACKUP_KEEP_DAYS = 30;

function backup_dir() { $d = dirname(cfg('db_path')) . '/backups'; if (!is_dir($d)) mkdir($d, 0700, true); return $d; }

// Целая и согласованная копия базы, даже если в этот момент кто-то записывается
function backup_make($file) {
    $db = db();
    @unlink($file);
    try { $db->exec('VACUUM INTO ' . $db->quote($file)); }
    catch (Throwable $e) { $db->exec('PRAGMA wal_checkpoint(TRUNCATE)'); copy(cfg('db_path'), $file); }
    return is_file($file) && filesize($file) > 0;
}

// Всё содержимое кабинета одним файлом JSON (читается без программ, можно открыть в любом редакторе): записи, сертификаты, обучение
// и личные кабинеты клиенток (без паролей — они есть только в полной копии базы, в зашифрованном виде)
function backup_json() {
    [$doc] = doc_load(false);
    unset($doc['notes']);
    $acc = client_db()->query('SELECT id, email, name, phone, verified, created, news FROM accounts ORDER BY id')->fetchAll();
    return json_encode(['site' => 'ma-nails.ru', 'made' => date('c'), 'doc' => $doc, 'accounts' => $acc], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// Проверка файла базы: открывается, не повреждён, сколько в нём кабинетов и записей
function backup_check($file) {
    try {
        $p = new PDO('sqlite:' . $file); $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $ok = $p->query('PRAGMA quick_check')->fetchColumn() === 'ok';
        $acc = (int)$p->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
        $doc = json_decode((string)$p->query("SELECT v FROM kv WHERE k = 'doc'")->fetchColumn(), true);
        return ['ok' => $ok && is_array($doc), 'acc' => $acc, 'book' => count($doc['bookings'] ?? [])];
    } catch (Throwable $e) { return ['ok' => false, 'acc' => 0, 'book' => 0, 'err' => $e->getMessage()]; }
}

// Фото профиля: зеркало папки data/av в data/backups/av (новые копируются, удалённые клиенткой — убираются)
function backup_avatars() {
    $src = dirname(cfg('db_path')) . '/av'; $dst = backup_dir() . '/av'; if (!is_dir($dst)) mkdir($dst, 0700, true);
    $have = []; foreach (glob($src . '/*') ?: [] as $f) { $n = basename($f); $have[$n] = 1; if (!is_file("$dst/$n") || filesize("$dst/$n") !== filesize($f)) copy($f, "$dst/$n"); }
    foreach (glob($dst . '/*') ?: [] as $f) if (!isset($have[basename($f)])) @unlink($f);
    return count($have);
}

// Раз в день (после 10:00), пока Марина не указала почту для уведомлений, — напоминание в кабинет и Telegram
function mail_nag_run() {
    if (mail_admin() || (int)date('G') < 10 || kv_get('mailnag_day') === date('Y-m-d')) return;
    kv_put('mailnag_day', date('Y-m-d'));
    $why = 'Зачем: 1) копии всех уведомлений о записях, отменах и заявках — даже если Telegram не работает; 2) каждый понедельник — резервная копия базы клиенток и записей, чтобы ничего не потерялось; 3) письма от клиенток и сайта будут у вас под рукой.';
    [$doc, $v] = doc_load(true);
    doc_note($doc, '✉️', 'Добавьте почту для уведомлений', 'Внизу кабинета нажмите «Почта для уведомлений» и впишите свою почту. ' . $why, ['tab' => 'day']);
    doc_save($doc, $v);
    foreach (db()->query('SELECT chat_id FROM admins') as $a)
        send($a['chat_id'], "✉️ <b>Добавьте почту для уведомлений</b>\nНа сайте в кабинете внизу нажмите «Почта для уведомлений» и впишите свою почту.\n\n" . h($why) . "\n\nНапоминание приходит раз в день, пока почта не указана.");
}

function backup_run() {
    $today = date('Y-m-d');
    if ((int)date('G') < 3 || kv_get('backup_day') === $today) return;  // ночью, после 03:00, раз в сутки
    kv_put('backup_day', $today);
    $dir = backup_dir();
    $live = backup_check(cfg('db_path'));  // сама база цела?
    if (!$live['ok']) { logline('db check failed'); notify_admins('⚠️ Проверка базы сайта нашла ошибку. Ничего не удаляйте — напишите разработчику: есть резервные копии за 30 дней.'); }
    $file = $dir . '/ma-' . $today . '.sqlite';
    $copy = backup_make($file) ? backup_check($file) : ['ok' => false];
    if (!$copy['ok']) { logline('backup failed'); notify_admins('⚠️ Не получилось сделать резервную копию базы сайта. Напишите разработчику.'); return; }
    kv_put('backup_last', json_encode(['day' => $today, 'acc' => $copy['acc'], 'book' => $copy['book']]));
    try { backup_avatars(); } catch (Throwable $e) { logline('avatars backup: ' . $e->getMessage()); }
    // старые копии удаляем, только если сегодняшняя и сама база в порядке
    if ($live['ok']) foreach (glob($dir . '/ma-*.sqlite') as $f) if (filemtime($f) < time() - BACKUP_KEEP_DAYS * 86400) @unlink($f);
    // раз в неделю — копия на почту Марине
    if (date('N') === '1' && ($to = mail_admin()))
        send_mail_file($to, 'Резервная копия базы сайта — ' . date('d.m.Y'),
            "Это еженедельная резервная копия базы сайта: записи, клиентки и их личные кабинеты, сертификаты, обучение и график.\nВ письме два файла: ma-base — полная копия базы (по ней разработчик восстановит всё, включая входы клиенток в кабинеты), ma-backup — то же в читаемом виде.\nПросто сохраните это письмо — если с хостингом что-то случится, по этим файлам всё можно восстановить.\nВ кабинете на сайте можно в любой момент скачать таблицы для Excel: вкладка «Клиентки» → «Выгрузить базу».",
            ['ma-backup-' . $today . '.json.gz' => gzencode(backup_json(), 9), 'ma-base-' . $today . '.sqlite.gz' => gzencode((string)file_get_contents($file), 9)]);
}
