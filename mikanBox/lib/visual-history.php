<?php
// ビジュアル編集: 履歴。
//   元に戻す / やり直す … 直近 MBV_UNDO_MAX 回分。編集の作業（ページを開いてから離れるまで）が変わると捨てる。
//   復元ポイント      … 編集の作業ごとに1件（その作業を始める前の状態）。ページごとに MBV_POINTS_MAX 件まで。
// 中身は、ページのデータそのもの（loadData の中身）。保存先に依らない形で、JSON版・SQLite版の両方に使える。
// 置き場所は、データフォルダの中（visual-history/）。データフォルダは、外から読めないように保護されている。
// SQLite版は、復元ポイントに、管理画面のページ履歴（post_revisions）を使う。

const MBV_UNDO_MAX = 3;
const MBV_POINTS_MAX = 10;

function mbv_hist_root(): string {
    $root = DATA_DIR . '/visual-history';
    if (!is_dir($root)) {
        @mkdir($root, 0755, true);
        @file_put_contents($root . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        @file_put_contents($root . '/index.html', '');
    }
    return $root;
}
function mbv_hist_page_dir(string $id): string { return mbv_hist_root() . '/' . hash('sha256', $id); }   // ページIDは、そのままフォルダ名にしない
function mbv_hist_dir(string $id, string $kind): string {
    $d = mbv_hist_page_dir($id) . '/' . $kind;
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}
function mbv_hist_list(string $id, string $kind): array { $f = glob(mbv_hist_dir($id, $kind) . '/*.json') ?: []; sort($f); return $f; }
function mbv_hist_name(): string { return sprintf('%020d', (int)(microtime(true) * 1000000)) . '.json'; }
function mbv_hist_put(string $id, string $kind, array $data): void { file_put_contents(mbv_hist_dir($id, $kind) . '/' . mbv_hist_name(), json_encode($data, JSON_UNESCAPED_UNICODE)); }
function mbv_hist_clear(string $id, string $kind): void { foreach (mbv_hist_list($id, $kind) as $f) @unlink($f); }
/** ページを削除したとき・IDを変えたときに呼ぶ */
function mbv_hist_forget(string $id): void {
    $d = mbv_hist_page_dir($id); if (!is_dir($d)) return;
    foreach (['undo', 'redo', 'points'] as $k) { foreach (glob($d . '/' . $k . '/*') ?: [] as $f) @unlink($f); @rmdir($d . '/' . $k); }
    @unlink($d . '/session'); @rmdir($d);
}

function mbv_valid_sid(string $s): bool { return (bool)preg_match('/\A[A-Za-z0-9]{8,64}\z/', $s); }

// ---------------------------------------------------------------- 復元ポイント
function mbv_sqlite_points(): bool { return function_exists('createRevision') && function_exists('getRevisions'); }

function mbv_point_add(string $id, array $cur): void {
    if (mbv_sqlite_points()) { createRevision($id, $_SESSION['admin_username'] ?? 'admin'); return; }
    mbv_hist_put($id, 'points', ['at' => date('Y-m-d H:i:s'), 'data' => $cur]);
    $p = mbv_hist_list($id, 'points'); while (count($p) > MBV_POINTS_MAX) @unlink(array_shift($p));
}
/** [['id' => ..., 'at' => 'Y-m-d H:i:s'], ...] 新しい順 */
function mbv_points(string $id): array {
    $out = [];
    if (mbv_sqlite_points()) {
        foreach (array_slice(getRevisions($id), 0, MBV_POINTS_MAX) as $r) $out[] = ['id' => (string)$r['id'], 'at' => (string)$r['created_at']];
        return $out;
    }
    foreach (array_reverse(mbv_hist_list($id, 'points')) as $f) {
        $j = json_decode((string)file_get_contents($f), true);
        $out[] = ['id' => basename($f, '.json'), 'at' => (string)($j['at'] ?? '')];
    }
    return $out;
}
function mbv_point_data(string $id, string $pid): ?array {
    if (mbv_sqlite_points()) {
        if (!ctype_digit($pid)) return null;
        $ok = false; foreach (getRevisions($id) as $r) if ((string)$r['id'] === $pid) { $ok = true; break; }   // このページの履歴だけ
        return $ok ? getRevisionData((int)$pid) : null;
    }
    if (!preg_match('/\A\d{20}\z/', $pid)) return null;
    $f = mbv_hist_dir($id, 'points') . '/' . $pid . '.json'; if (!is_file($f)) return null;
    $j = json_decode((string)file_get_contents($f), true);
    return is_array($j['data'] ?? null) ? $j['data'] : null;
}

// ---------------------------------------------------------------- 元に戻す / やり直す
function mbv_counts(string $id): array {
    return ['undo' => count(mbv_hist_list($id, 'undo')), 'redo' => count(mbv_hist_list($id, 'redo')), 'points' => count(mbv_points($id))];
}

/**
 * 書き換える直前の状態を保存する。$sid は、ページを開くたびに画面が作る目印。
 * 目印が前回と違えば「新しい編集の作業」: 復元ポイントを1件作り、元に戻す履歴を空にする。
 */
function mbv_snapshot(string $id, string $sid): void {
    $cur = loadData(POSTS_DIR, $id); if (!$cur) return;
    $mark = mbv_hist_page_dir($id) . '/session';
    if (!mbv_valid_sid($sid)) $sid = 'none';
    if ((string)@file_get_contents($mark) !== $sid) {
        mbv_point_add($id, $cur);
        mbv_hist_clear($id, 'undo');
        if (!is_dir(dirname($mark))) @mkdir(dirname($mark), 0755, true);
        file_put_contents($mark, $sid);
    }
    mbv_hist_put($id, 'undo', $cur);
    mbv_hist_clear($id, 'redo');
    $u = mbv_hist_list($id, 'undo'); while (count($u) > MBV_UNDO_MAX) @unlink(array_shift($u));
}
/** $kind = 'undo' | 'redo'。成功したら true */
function mbv_history_move(string $id, string $kind): bool {
    $to = $kind === 'undo' ? 'redo' : 'undo';
    $list = mbv_hist_list($id, $kind); if (!$list) return false;
    $snap = array_pop($list);
    $data = json_decode((string)file_get_contents($snap), true);
    if (!is_array($data)) return false;
    $cur = loadData(POSTS_DIR, $id); if ($cur) mbv_hist_put($id, $to, $cur);
    if (!saveData(POSTS_DIR, $id, $data)) return false;
    unlink($snap);
    return true;
}
/** 復元ポイントに戻す。ステータスは今のまま。直前の状態は「元に戻す」で戻せる */
function mbv_point_restore(string $id, string $pid, string $sid): bool {
    $data = mbv_point_data($id, $pid); $cur = loadData(POSTS_DIR, $id);
    if (!$data || !$cur) return false;
    mbv_snapshot($id, $sid);
    $data['status'] = $cur['status'] ?? ($data['status'] ?? 'draft');
    $data['updated_at'] = date('Y-m-d H:i:s');
    return (bool)saveData(POSTS_DIR, $id, $data);
}
