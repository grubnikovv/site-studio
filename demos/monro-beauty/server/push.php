<?php
// Уведомления от браузера (Web Push) для кабинета мастера.
// Текст уведомления шифруем и кладём прямо в пуш (стандарт RFC 8291, aes128gcm) — телефону не нужно ходить на сайт.
// Старые подписки без ключей шифрования получают «пустой» пуш, и sw.js сам спрашивает у сервера, что нового (push_peek).
// Подпись запроса — ключ VAPID (создаётся один раз).

function push_db() {
    $db = db();
    $db->exec('CREATE TABLE IF NOT EXISTS push_subs (endpoint TEXT PRIMARY KEY, created INTEGER, seen INTEGER DEFAULT 0, ua TEXT)');
    foreach (['acc INTEGER DEFAULT 0', 'p256dh TEXT', 'auth TEXT'] as $col)  // acc: 0 — телефон мастера, иначе — кабинет клиентки
        try { $db->exec('ALTER TABLE push_subs ADD COLUMN ' . $col); } catch (Throwable $e) {}
    $db->exec('CREATE TABLE IF NOT EXISTS push_vapid (id INTEGER PRIMARY KEY CHECK (id = 1), priv TEXT, pub TEXT)');
    return $db;
}

function b64u($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64ud($s) { return (string)base64_decode(strtr((string)$s, '-_', '+/')); }

// Шифрование содержимого пуша для браузера (RFC 8291): общий секрет ECDH с ключом браузера + HKDF + AES-128-GCM
function push_encrypt($payload, $p256dh, $auth) {
    $ua = b64ud($p256dh); $sec = b64ud($auth);
    if (strlen($ua) !== 65 || strlen($sec) !== 16) return null;
    $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $d = openssl_pkey_get_details($k)['ec'];
    $as = "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $ua), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $pub = openssl_pkey_get_public($pem); if (!$pub) return null;
    $shared = openssl_pkey_derive($pub, $k, 32); if (!$shared) return null;
    $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $ua . $as, $sec);
    $salt = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $salt . pack('N', 4096) . chr(65) . $as . $ct . $tag;
}

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

function push_send_one($endpoint, $v, $body = null) {
    $u = parse_url($endpoint);
    $jwt0 = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.' . b64u(json_encode(['aud' => $u['scheme'] . '://' . $u['host'], 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . (cfg('mail_from') ?: 'marina@ma-nails.ru')]));
    $jwt = $jwt0 . '.' . b64u(es256($jwt0, $v['priv']));
    $h = ['TTL: 86400', 'Urgency: high', 'Authorization: vapid t=' . $jwt . ', k=' . $v['pub']];
    if ($body !== null) array_push($h, 'Content-Encoding: aes128gcm', 'Content-Type: application/octet-stream');
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => (string)$body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_HTTPHEADER => $h]);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    if ($code >= 300 || $code === 0) logline('push ' . ($u['host'] ?? '?') . ' → ' . $code . ' ' . mb_substr(trim((string)$res), 0, 150));
    return $code;
}

// Что показать: заголовок, текст и куда открыть по нажатию
function push_note($title, $body, $url = './#admin') {
    $clip = fn($s, $n) => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)$s))), 0, $n);
    return ['title' => $clip($title, 80), 'body' => $clip($body, 300), 'tag' => 'p' . now_ms(), 'url' => $url];
}

// Разбудить телефоны: мастера (push_all), одной клиентки (push_acc) или всех клиенток (push_clients).
// Подписки, которые браузер отменил (404/410), удаляем.
function push_all($note = null) { push_where('acc = 0', [], $note); }
function push_acc($accId, $note = null) { if ($accId) push_where('acc = ?', [(int)$accId], $note); }
function push_clients($note = null) { push_where('acc > 0', [], $note); }
// Уведомления от браузера пока выключены (остались почта и Telegram). Включить снова — true.
const PUSH_ON = false;
function push_where($cond, $args, $note = null) {
    if (!PUSH_ON) return;
    try {
        $db = push_db(); $q = $db->prepare('SELECT endpoint, p256dh, auth FROM push_subs WHERE ' . $cond); $q->execute($args); $subs = $q->fetchAll();
        if (!$subs) return;
        $v = vapid();
        foreach ($subs as $s) {
            $body = ($note && $s['p256dh'] && $s['auth']) ? push_encrypt(json_encode(['notes' => [$note]], JSON_UNESCAPED_UNICODE), $s['p256dh'], $s['auth']) : null;
            $code = push_send_one($s['endpoint'], $v, $body);
            if ($code === 404 || $code === 410) $db->prepare('DELETE FROM push_subs WHERE endpoint = ?')->execute([$s['endpoint']]);
        }
    } catch (Throwable $e) { logline('push error: ' . $e->getMessage()); }
}
