<?php
// Один раз после установки: откройте в браузере
//   https://ваш-сайт/server/setup.php?key=ВАШ_admin_key
// Подключает вебхук Telegram и показывает ссылку, по которой Марина подключит себя к боту.
require __DIR__ . '/lib.php';
header('Content-Type: text/plain; charset=utf-8');
if (($_GET['key'] ?? '') !== cfg('admin_key') || strlen((string)cfg('admin_key')) < 16) { http_response_code(403); exit("Нет доступа (проверьте key и admin_key в config.php — не короче 16 символов)\n"); }

$hook = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/tg.php';
$r = tg('setWebhook', ['url' => $hook, 'secret_token' => substr(hash('sha256', cfg('admin_key') . 'wh'), 0, 32), 'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => true]);
echo 'Вебхук: ' . $hook . ' — ' . (($r['ok'] ?? false) ? 'подключён' : 'ошибка: ' . json_encode($r, JSON_UNESCAPED_UNICODE)) . "\n";
tg('setMyDescription', ['description' => 'Бот студии маникюра и педикюра «М.А» в Михайловке. Присылает напоминания о записи.']);
tg('setMyShortDescription', ['short_description' => 'Напоминания о записи в студию «М.А»']);
db();
echo "База: готова\n\nСсылка для Марины (открыть со своего Telegram и нажать «Старт»):\nhttps://t.me/" . cfg('bot_name') . '?start=admin_' . cfg('admin_key') . "\n";
