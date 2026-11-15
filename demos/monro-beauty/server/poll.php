<?php
// Опрос Telegram без https: запускать каждую минуту (cron). Забирает новые сообщения и сразу отвечает,
// держит связь ~55 секунд, поэтому ответ приходит почти мгновенно. Заодно отправляет напоминания (cron.php).
require __DIR__ . '/bot.php';
if (PHP_SAPI !== 'cli' && ($_GET['key'] ?? '') !== cfg('admin_key')) { http_response_code(403); exit; }

// Не запускать две копии одновременно
$lock = fopen(dirname(cfg('db_path')) . '/poll.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;

$db = db();
$db->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)');
$get = function ($k) use ($db) { $q = $db->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute([$k]); return $q->fetchColumn(); };
$put = function ($k, $v) use ($db) { $db->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute([$k, (string)$v]); };

if (!$get('polling')) { tg('deleteWebhook', ['drop_pending_updates' => false]); $put('polling', 1); }
$offset = (int)$get('offset');
$until = time() + (getenv("POLL_SECS") ?: 50);
while (time() < $until) {
    $r = tg('getUpdates', ['offset' => $offset, 'timeout' => 20, 'allowed_updates' => ['message', 'callback_query']]);
    if (!$r || empty($r['ok'])) { sleep(3); continue; }
    foreach ($r['result'] as $u) {
        $offset = $u['update_id'] + 1; $put('offset', $offset);
        try { handle_update($u); } catch (Throwable $e) { logline('update error: ' . $e->getMessage()); }
    }
}
// Раз в минуту — напоминания
include __DIR__ . '/cron.php';
