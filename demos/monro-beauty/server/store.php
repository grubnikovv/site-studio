<?php
// Общая база сайта: все записи, график, сертификаты, обучение и уведомления кабинета.
// Хранится одним JSON-документом в SQLite (как раньше в памяти телефона), у документа есть номер версии.
// Кабинет мастера (по паролю) видит и меняет всё; посетители видят только занятость без имён и телефонов.
require_once __DIR__ . '/bot.php';

const LIST_KEYS = ['bookings', 'training', 'certs', 'notes', 'modelBase'];

function store_init() {
    $db = db();
    $db->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS sessions (token TEXT PRIMARY KEY, created INTEGER)');
    return $db;
}

// Прочитать документ (с блокировкой записи, если собираемся менять)
function doc_load($forWrite = false) {
    $db = store_init();
    if ($forWrite) $db->exec('BEGIN IMMEDIATE');
    $q = $db->query("SELECT v FROM kv WHERE k = 'doc'"); $raw = $q->fetchColumn();
    $doc = $raw ? json_decode($raw, true) : [];
    if (!is_array($doc)) $doc = [];
    $q = $db->query("SELECT v FROM kv WHERE k = 'doc_v'"); $v = (int)$q->fetchColumn();
    foreach (LIST_KEYS as $k) if (!isset($doc[$k]) || !is_array($doc[$k])) $doc[$k] = [];
    return [$doc, $v];
}

function doc_save($doc, $v) {
    $db = db(); $v++;
    $db->prepare("INSERT OR REPLACE INTO kv (k, v) VALUES ('doc', ?)")->execute([json_encode($doc, JSON_UNESCAPED_UNICODE)]);
    $db->prepare("INSERT OR REPLACE INTO kv (k, v) VALUES ('doc_v', ?)")->execute([(string)$v]);
    $db->exec('COMMIT');
    return $v;
}

function doc_abort() { try { db()->exec('ROLLBACK'); } catch (Throwable $e) {} }

function now_ms() { return (int)round(microtime(true) * 1000); }
function rid($p) { return $p . base_convert((string)now_ms(), 10, 36) . substr(bin2hex(random_bytes(3)), 0, 4); }

// Уведомление в колокольчик кабинета (храним 2 недели, не больше 200)
function doc_note(&$doc, $icon, $title, $text, $go = null) {
    array_unshift($doc['notes'], ['id' => rid('n'), 't' => now_ms(), 'icon' => $icon, 'title' => $title, 'text' => $text, 'go' => $go, 'read' => false]);
    $lim = now_ms() - 14 * 86400 * 1000;
    $doc['notes'] = array_slice(array_values(array_filter($doc['notes'], fn($n) => ($n['t'] ?? 0) > $lim)), 0, 200);
}

// Брони обучения и сертификаты держатся 30 минут: за это время нужно оплатить, а Марине — нажать «Оплата пришла».
// Не успели — бронь удаляется, Марине приходит сообщение с контактами (вдруг деньги всё же пришли).
function doc_expire(&$doc) {
    $now = now_ms(); $changed = false;
    foreach (['training', 'certs'] as $kind) {
        $keep = [];
        foreach ($doc[$kind] as $x) {
            if (($x['status'] ?? '') !== 'wait' || ($x['hold'] ?? PHP_INT_MAX) >= $now) { $keep[] = $x; continue; }
            $changed = true;
            $who = ($x['name'] ?? '') . ', ' . ($x['phone'] ?? '');
            $what = $kind === 'training' ? 'Бронь обучения' : 'Сертификат № ' . ($x['code'] ?? '');
            if (!empty($x['claimed'])) {
                $txt = $who . ' нажала «Я оплатил(а)», но оплату не подтвердили за 30 минут. Если деньги пришли — свяжитесь и оформите заново или верните.';
                doc_note($doc, '⚠️', $what . ' удалена', $txt, ['tab' => 'req']);
                notify_admins('⚠️ <b>' . h($what) . ' удалена</b>' . "\n" . h($txt));
            } else {
                doc_note($doc, '⌛', $what . ' отменена', $who . ' не оплатила за 30 минут' . ($kind === 'training' ? ' — даты снова свободны' : ''), ['tab' => 'req']);
            }
        }
        $doc[$kind] = $keep;
    }
    return $changed;
}

// Что видит посетитель сайта: занятость без имён, телефонов и денег
function doc_public($doc) {
    $out = $doc;
    $out['bookings'] = array_values(array_map(fn($b) => array_intersect_key($b, array_flip(['id', 'date', 'start', 'svc', 'remove', 'status', 'dur', 'example'])), $doc['bookings']));
    $out['training'] = array_values(array_map(fn($t) => array_intersect_key($t, array_flip(['id', 'course', 'date', 'days', 'status', 'hold', 'claimed', 'example'])), $doc['training']));
    $models = [];
    foreach (($doc['models'] ?? []) as $k => $m) $models[$k] = is_array($m) ? array_intersect_key($m, array_flip(['time'])) + (!empty($m['name']) ? ['taken' => true] : []) : $m;
    $out['models'] = (object)$models;
    unset($out['certs'], $out['notes'], $out['modelBase'], $out['deleted']);
    foreach (['blocks', 'dayoff', 'hours', 'modelTimes', 'workday', 'bought'] as $k) if (isset($out[$k])) $out[$k] = (object)$out[$k];
    return $out;
}

function doc_full($doc) {
    foreach (['models', 'blocks', 'dayoff', 'hours', 'modelTimes', 'workday', 'bought', 'deleted'] as $k) $doc[$k] = (object)($doc[$k] ?? []);
    return $doc;
}

// Изменения из кабинета: [{p:[ключ], v:значение} | {p:[ключ, id], v:запись} | {p:[...], d:1}]
function doc_apply(&$doc, $ops) {
    foreach ($ops as $op) {
        $p = $op['p'] ?? null; if (!is_array($p) || !$p || count($p) > 2) continue;
        $k = (string)$p[0]; if ($k === '' || $k === 'sent' || strlen($k) > 40) continue;
        $del = !empty($op['d']); $val = $op['v'] ?? null;
        if (count($p) === 1) { if ($del) unset($doc[$k]); else $doc[$k] = $val; continue; }
        $id = (string)$p[1];
        if (in_array($k, LIST_KEYS, true)) {
            if (!is_array($doc[$k])) $doc[$k] = [];
            $found = false;
            foreach ($doc[$k] as $i => $r) if (($r['id'] ?? null) === $id) {
                $found = true;
                if ($del) array_splice($doc[$k], $i, 1); else $doc[$k][$i] = $val;
                break;
            }
            if (!$found && !$del && is_array($val)) { if ($k === 'notes') array_unshift($doc[$k], $val); else $doc[$k][] = $val; }
        } else {
            if (!isset($doc[$k]) || !is_array($doc[$k])) $doc[$k] = [];
            if ($del) unset($doc[$k][$id]); else $doc[$k][$id] = $val;
        }
    }
}

// ---------- вход мастера ----------
function session_ok($token) {
    if (!is_string($token) || strlen($token) < 32) return false;
    $q = store_init()->prepare('SELECT created FROM sessions WHERE token = ?'); $q->execute([hash('sha256', $token)]);
    $c = $q->fetchColumn();
    return $c && $c > time() - 180 * 86400;  // вход держится полгода
}

function session_new() {
    $t = bin2hex(random_bytes(24));
    store_init()->prepare('INSERT INTO sessions (token, created) VALUES (?, ?)')->execute([hash('sha256', $t), time()]);
    return $t;
}

// ---------- проверки для посетителей ----------
function overlaps($a1, $a2, $b1, $b2) { return $a1 < $b2 && $b1 < $a2; }

function day_taken_by_training($doc, $date) {
    foreach ($doc['training'] as $t) {
        $active = ($t['status'] ?? '') === 'paid' || (($t['status'] ?? '') === 'wait' && ($t['hold'] ?? 0) > now_ms());
        if ($active && in_array($date, $t['days'] ?? [$t['date'] ?? ''], true)) return true;
    }
    return false;
}

// Те же правила, что на сайте: часы работы, выходные, обед, закрытые окна, обучение, другие записи
const OPEN_M = 600, CLOSE_M = 1140, STEP_M = 30, LUNCH_M = [720, 750];
function is_workday($doc, $date) {
    if (!empty($doc['dayoff'][$date])) return false;
    $wd = (int)date('w', strtotime($date . ' 12:00'));
    return ($wd === 0 || $wd === 6) ? !empty($doc['workday'][$date]) : true;
}
function day_free_for_training($doc, $date) {
    if (!is_workday($doc, $date) || day_taken_by_training($doc, $date) || !empty($doc['blocks'][$date])) return false;
    foreach ($doc['bookings'] as $b) if (($b['date'] ?? '') === $date && ($b['status'] ?? '') !== 'cancelled') return false;
    return true;
}
function slot_free($doc, $date, $start, $dur, $exceptId = '') {
    if (!is_workday($doc, $date) || day_taken_by_training($doc, $date)) return false;
    $h = $doc['hours'][$date] ?? [OPEN_M, CLOSE_M];
    if ($start < (int)$h[0] || $start + $dur > (int)$h[1] || ($start - OPEN_M) % STEP_M) return false;
    if (overlaps($start, $start + $dur, LUNCH_M[0], LUNCH_M[1])) return false;
    foreach (($doc['blocks'][$date] ?? []) as $m => $why) if (overlaps($start, $start + $dur, (int)$m, (int)$m + STEP_M)) return false;
    if ($date === date('Y-m-d') && $start < (int)date('G') * 60 + (int)date('i') + 60) return false;
    foreach ($doc['bookings'] as $b) {
        if (($b['id'] ?? '') === $exceptId || ($b['status'] ?? '') === 'cancelled' || ($b['date'] ?? '') !== $date) continue;
        if (overlaps($start, $start + $dur, (int)$b['start'], (int)$b['start'] + (int)($b['dur'] ?? 60))) return false;
    }
    return true;
}

function digits_phone($p) { $d = preg_replace('/\D/', '', (string)$p); $d = preg_replace('/^8/', '7', $d); if (strlen($d) === 10 && $d[0] === '9') $d = '7' . $d; return $d; }

// Изменения записей из кабинета (перенос, отмена, удаление) — в таблицу бота, чтобы напоминания шли на правильное время
function mirror_bookings($ops, $doc) {
    $db = db();
    foreach ($ops as $op) {
        $p = $op['p'] ?? [];
        if ($p === ['bookings']) {  // весь список заменён (например, очистка тестовых данных) — убрать лишние записи и у бота
            $ids = array_map(fn($b) => (string)($b['id'] ?? ''), is_array($op['v'] ?? null) ? $op['v'] : []);
            foreach ($db->query('SELECT id FROM bookings')->fetchAll(PDO::FETCH_COLUMN) as $id) if (!in_array($id, $ids, true)) $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
            continue;
        }
        if (($p[0] ?? '') !== 'bookings' || count($p) !== 2) continue;
        $id = (string)$p[1];
        $q = $db->prepare('SELECT date, start FROM bookings WHERE id = ?'); $q->execute([$id]); $old = $q->fetch();
        if (!$old) continue;
        $b = null; foreach ($doc['bookings'] as $x) if (($x['id'] ?? '') === $id) $b = $x;
        if (!$b || !empty($op['d'])) { $db->prepare('UPDATE bookings SET status = "cancelled", updated = ? WHERE id = ?')->execute([time(), $id]); continue; }
        $status = in_array($b['status'] ?? '', ['cancelled', 'noshow'], true) ? $b['status'] : 'booked';
        $moved = $old['date'] !== ($b['date'] ?? '') || (int)$old['start'] !== (int)($b['start'] ?? 0);
        $db->prepare('UPDATE bookings SET date = ?, start = ?, dur = ?, status = ?, updated = ?' . ($moved ? ', sent_2d = 0, sent_2h = 0, confirmed = 0' : '') . ' WHERE id = ?')
           ->execute([$b['date'], (int)$b['start'], (int)($b['dur'] ?? 60), $status, time(), $id]);
    }
}

// ---------- пароль кабинета ----------
// Пароль из config.php — начальный. После смены в кабинете хранится только его хэш в базе.
function kv_get($k) { $q = store_init()->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute([$k]); return $q->fetchColumn(); }
function kv_put($k, $v) { store_init()->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute([$k, (string)$v]); }
function kv_del($k) { store_init()->prepare('DELETE FROM kv WHERE k = ?')->execute([$k]); }

function pass_ok($pass) {
    $h = kv_get('pass_hash');
    if ($h) return password_verify((string)$pass, $h);
    $c = (string)cfg('cabinet_pass');
    return strlen($c) >= 6 && hash_equals($c, (string)$pass);
}
function pass_set($pass) { kv_put('pass_hash', password_hash((string)$pass, PASSWORD_DEFAULT)); }
function pass_weak($p) { return mb_strlen((string)$p) < 6 ? 'Пароль — не короче 6 символов.' : (mb_strlen((string)$p) > 64 ? 'Слишком длинный пароль.' : ''); }

// ---------- кабинет клиентки: вход по телефону и коду из Telegram или почты ----------
function client_db() {
    $db = store_init();
    $db->exec('CREATE TABLE IF NOT EXISTS client_sessions (token TEXT PRIMARY KEY, phone TEXT, created INTEGER)');
    $db->exec('CREATE TABLE IF NOT EXISTS client_codes (phone TEXT PRIMARY KEY, hash TEXT, until INTEGER, tries INTEGER, sent INTEGER)');
    return $db;
}
function client_phone($token) {
    if (!is_string($token) || strlen($token) < 32) return '';
    $q = client_db()->prepare('SELECT phone, created FROM client_sessions WHERE token = ?'); $q->execute([hash('sha256', $token)]);
    $r = $q->fetch();
    return ($r && $r['created'] > time() - 180 * 86400) ? $r['phone'] : '';
}
// Куда прислать код: последний чат бота с этим телефоном, иначе почта из записи
function client_channel($phone) {
    $q = db()->prepare('SELECT chat_id, email, remind FROM bookings WHERE phone = ? ORDER BY updated DESC'); $q->execute([$phone]);
    $mail = '';
    foreach ($q->fetchAll() as $r) {
        if (!empty($r['chat_id'])) return ['tg', $r['chat_id']];
        if (!$mail && !empty($r['email'])) $mail = $r['email'];
    }
    return $mail ? ['mail', $mail] : null;
}
function mask_mail($m) { [$a, $d] = explode('@', $m, 2) + ['', '']; return mb_substr($a, 0, 2) . str_repeat('•', max(1, mb_strlen($a) - 2)) . '@' . $d; }
