<?php
// Резервные копии базы (записи, клиентки, сертификаты, обучение, график — всё в одном файле bot.sqlite).
// 1) Каждую ночь — копия на хостинге в data/backups, хранится 30 дней: можно откатиться, если что-то испортилось.
// 2) Раз в неделю (понедельник) — копия Марине на почту: данные лежат ещё и вне хостинга.
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

// Всё содержимое кабинета одним файлом JSON (читается без программ, можно открыть в любом редакторе)
function backup_json() {
    [$doc] = doc_load(false);
    unset($doc['notes']);
    return json_encode(['site' => 'ma-nails.ru', 'made' => date('c'), 'doc' => $doc], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
    $ok = backup_make($dir . '/ma-' . $today . '.sqlite');
    if (!$ok) { logline('backup failed'); notify_admins('⚠️ Не получилось сделать резервную копию базы сайта. Напишите разработчику.'); return; }
    foreach (glob($dir . '/ma-*.sqlite') as $f) if (filemtime($f) < time() - BACKUP_KEEP_DAYS * 86400) @unlink($f);
    // раз в неделю — копия на почту Марине
    if (date('N') === '1' && ($to = mail_admin()))
        send_mail_file($to, 'Резервная копия базы сайта — ' . date('d.m.Y'),
            "Это еженедельная резервная копия базы сайта: записи, клиентки, сертификаты, обучение и график.\nПросто сохраните это письмо — если с хостингом что-то случится, по этому файлу всё можно восстановить.\nВ кабинете на сайте можно в любой момент скачать таблицы для Excel: вкладка «Клиентки» → «Выгрузить базу».",
            'ma-backup-' . $today . '.json.gz', gzencode(backup_json(), 9));
}
