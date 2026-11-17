<?php
// Фото профиля клиенток: лежат в закрытой папке data/av, отдаём по случайному имени (его знают только сама клиентка и мастер)
$f = (string)($_GET['f'] ?? '');
if (!preg_match('/^[a-f0-9]{32}(_t)?\.(webp|jpg)$/', $f) || !is_file($p = __DIR__ . '/data/av/' . $f)) { http_response_code(404); exit; }
header('Content-Type: image/' . (substr($f, -4) === 'webp' ? 'webp' : 'jpeg'));
header('Cache-Control: private, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($p));
readfile($p);
