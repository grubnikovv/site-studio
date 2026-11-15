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

// Брони обучения и сертификаты, не оплаченные за 30 минут, отменяются сами
function doc_expire(&$doc) {
    $now = now_ms(); $changed = false;
    foreach ($doc['training'] as &$t) {
        if (($t['status'] ?? '') === 'wait' && empty($t['claimed']) && ($t['hold'] ?? 0) < $now) {
            $t['status'] = 'nopay'; $changed = true;
            doc_note($doc, '⌛', 'Бронь обучения отменилась', ($t['name'] ?? '') . ' не оплатила за 30 минут — даты снова свободны', ['tab' => 'req']);
        }
    }
    unset($t);
    foreach ($doc['certs'] as &$c) {
        if (($c['status'] ?? '') === 'wait' && empty($c['claimed']) && ($c['hold'] ?? PHP_INT_MAX) < $now) { $c['status'] = 'nopay'; $changed = true; }
    }
    unset($c);
    return $changed;
}

// Что видит посетитель сайта: занятость без имён, телефонов и денег
function doc_public($doc) {
    $out = $doc;
    $out['bookings'] = array_values(array_map(fn($b) => array_intersect_key($b, array_flip(['id', 'date', 'start', 'svc', 'remove', 'status', 'dur', 'example'])), $doc['bookings']));
    $out['training'] = array_values(array_map(fn($t) => array_intersect_key($t, array_flip(['id', 'course', 'date', 'days', 'status', 'hold', 'claimed', 'example'])), $doc['training']));
    $models = [];
    foreach (($doc['models'] ?? []) as $k => $m) $models[$k] = is_array($m) ? array_intersect_key($m, array_flip(['time'])) : $m;
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

function slot_free($doc, $date, $start, $dur, $exceptId = '') {
    if (!empty($doc['dayoff'][$date])) return false;
    if (day_taken_by_training($doc, $date)) return false;
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
        $p = $op['p'] ?? []; if (($p[0] ?? '') !== 'bookings' || count($p) !== 2) continue;
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
