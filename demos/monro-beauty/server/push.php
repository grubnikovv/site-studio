<?php
// Уведомления от браузера (Web Push) для кабинета мастера.
// Шлём «пустой» пуш (без шифрованного содержимого) — телефон просыпается, а sw.js сам спрашивает у сервера,
// что нового (push_peek), и показывает уведомление. Подпись запроса — ключ VAPID (создаётся один раз).

function push_db() {
    $db = db();
    $db->exec('CREATE TABLE IF NOT EXISTS push_subs (endpoint TEXT PRIMARY KEY, created INTEGER, seen INTEGER DEFAULT 0, ua TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS push_vapid (id INTEGER PRIMARY KEY CHECK (id = 1), priv TEXT, pub TEXT)');
    return $db;
}

function b64u($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

// Ключ VAPID: приватный (PEM) хранится в базе, публичный отдаём браузеру при подписке
function vapid() {
    $db = push_db();
    $row = $db->query('SELECT priv, pub FROM push_vapid WHERE id = 1')->fetch();
    if ($row) return $row;
    $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    openssl_pkey_export($k, $priv);
    $d = openssl_pkey_get_details($k)['ec'];
    $pub = b64u("\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT));
    $db->prepare('INSERT INTO push_vapid (id, priv, pub) VALUES (1, ?, ?)')->execute([$priv, $pub]);
    return ['priv' => $priv, 'pub' => $pub];
}

// Подпись ES256: openssl отдаёт DER, а JWT нужны 64 байта r||s
function es256($data, $priv) {
    openssl_sign($data, $der, $priv, OPENSSL_ALGO_SHA256);
    $p = 2; $p += (ord($der[1]) & 0x80) ? (ord($der[1]) & 0x7f) : 0;
    $rl = ord($der[$p + 1]); $r = substr($der, $p + 2, $rl); $p += 2 + $rl;
    $sl = ord($der[$p + 1]); $s = substr($der, $p + 2, $sl);
    $fix = fn($x) => str_pad(ltrim($x, "\0"), 32, "\0", STR_PAD_LEFT);
    return $fix($r) . $fix($s);
}

function push_send_one($endpoint, $v) {
    $u = parse_url($endpoint);
    $jwt0 = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.' . b64u(json_encode(['aud' => $u['scheme'] . '://' . $u['host'], 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . (cfg('mail_from') ?: 'marina@ma-nails.ru')]));
    $jwt = $jwt0 . '.' . b64u(es256($jwt0, $v['priv']));
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['TTL: 86400', 'Urgency: high', 'Content-Length: 0', 'Authorization: vapid t=' . $jwt . ', k=' . $v['pub']]]);
    curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return $code;
}

// Разбудить все телефоны мастера. Подписки, которые браузер отменил (404/410), удаляем.
function push_all() {
    try {
        $db = push_db(); $subs = $db->query('SELECT endpoint FROM push_subs')->fetchAll(PDO::FETCH_COLUMN);
        if (!$subs) return;
        $v = vapid();
        foreach ($subs as $e) {
            $code = push_send_one($e, $v);
            if ($code === 404 || $code === 410) $db->prepare('DELETE FROM push_subs WHERE endpoint = ?')->execute([$e]);
            elseif ($code >= 300 || $code === 0) logline('push ' . parse_url($e, PHP_URL_HOST) . ' → ' . $code);
        }
    } catch (Throwable $e) { logline('push error: ' . $e->getMessage()); }
}
