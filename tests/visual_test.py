#!/usr/bin/env python3
"""ビジュアル編集（mikanBox/visual.php）の、使い捨てサイトでの結合テスト。
ログイン・CSRF・同一オリジンの確認、保存・元に戻す・新規作成・画像の貼り付けの、正常系と拒否を確かめる。"""
import base64, http.cookiejar, json, re, secrets, shutil, socket, subprocess, tempfile, time
import urllib.request, urllib.error, urllib.parse
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
PHP = shutil.which('php')
checks = 0


def check(value, label):
    global checks
    if not value:
        raise AssertionError(label)
    checks += 1


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def php(site, code):
    r = subprocess.run([PHP, '-r', "require $argv[1].'/mikanBox/config.php'; require_once $argv[1].'/mikanBox/lib/functions.php'; " + code, str(site)], capture_output=True, text=True)
    if r.returncode:
        raise RuntimeError((r.stdout + r.stderr)[-3000:])
    return r.stdout


# ---- 0. 言語ファイル（サーバーなし）: 使うキーがすべてあり、日英で同じ、直書きの日本語がない
langs = {l: json.loads((PROJECT / 'mikanBox' / 'lang' / (l + '.json')).read_text()) for l in ('ja', 'en')}
sources = {n: (PROJECT / 'mikanBox' / n).read_text() for n in ('visual.php', 'visual/assets/visual.js')}
used = set(re.findall(r"""\b(?:T|t)\('([a-z_0-9]+)'""", ''.join(sources.values()))) | {'visual_js_confirm_undo', 'visual_js_confirm_redo'}
for l in ('ja', 'en'):
    check(all(k in langs[l] for k in used), 'every language key used by visual.php / visual.js exists in ' + l + ': ' + str(sorted(k for k in used if k not in langs[l])))
vis = {k for k in langs['ja'] if k.startswith('visual_')}
check(vis == {k for k in langs['en'] if k.startswith('visual_')}, 'ja and en define the same visual_ keys')
check(all(sorted(re.findall(r'%[s%]', langs['ja'][k])) == sorted(re.findall(r'%[s%]', langs['en'][k])) for k in vis), 'placeholders match between ja and en')
check(all(k in used for k in vis), 'no unused visual_ keys: ' + str(sorted(k for k in vis if k not in used)))
for n, src in sources.items():
    code = [l for l in src.split('\n') if re.search(r'[\u3040-\u30ff\u4e00-\u9fff]', l) and not re.search(r'(^\s*(//|\*|/\*))|(//\s)', l)]
    check(not code, n + ' has no hard-coded Japanese text: ' + str(code[:2]))
check('（' not in ''.join(l for l in (PROJECT / 'mikanBox/visual/assets/visual.css').read_text().split('\n') if not l.strip().startswith('/*')), 'visual.css has no hard-coded text')

with tempfile.TemporaryDirectory(prefix='mikanbox-visual-test-') as tmp:
    site = Path(tmp) / 'site'
    site.mkdir()
    shutil.copy2(PROJECT / 'index.php', site / 'index.php')
    shutil.copytree(PROJECT / 'mikanBox', site / 'mikanBox', ignore=shutil.ignore_patterns('.DS_Store', 'docs'))
    password = secrets.token_hex(16)
    SQLITE = (PROJECT / 'mikanBox/lib/device-auth.php').exists()      # 端末認証があるのがSQLite版
    if not SQLITE:
        php(site, 'saveSettings(["password_hash"=>password_hash(' + json.dumps(password) + ',PASSWORD_DEFAULT)]);')
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    base = 'http://127.0.0.1:%d' % port
    host = '127.0.0.1:%d' % port
    VIS = '/mikanBox/visual.php'
    ADM = '/mikanBox/admin.php'

    def mk():
        jar = http.cookiejar.CookieJar()
        return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)

    def req(opener, path, data=None, headers=None, method=None):
        r = urllib.request.Request(base + path, data=data, headers=headers or {}, method=method)
        try:
            res = opener.open(r, timeout=20)
        except urllib.error.HTTPError as e:
            res = e
        return res.status, res.read().decode('utf-8', 'replace'), dict(res.headers)

    def api(opener, name, body=None, token=None, extra=None, query=''):
        h = {'Content-Type': 'application/json'}
        if token is not None:
            h['X-CSRF-Token'] = token
        h.update(extra or {})
        data = None if body is None else json.dumps(body).encode()
        st, txt, hd = req(opener, VIS + '?api=' + name + query, data, h, 'POST' if body is not None else 'GET')
        try:
            return st, json.loads(txt), hd
        except ValueError:
            return st, {'raw': txt}, hd

    log = tempfile.TemporaryFile(mode='w+')
    server = subprocess.Popen([PHP, '-S', '127.0.0.1:%d' % port, '-t', str(site), str(site / 'index.php')], stdout=log, stderr=log)
    try:
        anon = mk()
        for _ in range(100):
            try:
                req(anon, ADM)
                break
            except OSError:
                time.sleep(.05)

        # ---- 1. ログインなし: すべて断る
        st, txt, hd = req(anon, VIS)
        check(st == 302 and 'admin.php' in hd.get('Location', ''), 'anonymous page request redirects to the admin login')
        st, body, _ = api(anon, 'source', query='&id=index')
        check(st == 401, 'anonymous API read is refused')
        st, body, _ = api(anon, 'save', {'id': 'index', 'start': 0, 'end': 0, 'old': 'x', 'new': 'y'}, token='anything')
        check(st == 401, 'anonymous API write is refused')
        st, body, _ = api(anon, 'upload', None)
        check(st == 401, 'anonymous upload is refused')

        # ---- 2. ログイン（管理画面と同じ）
        ed = mk()
        def form(opener, data):
            return req(opener, ADM, urllib.parse.urlencode(data).encode(), {'Content-Type': 'application/x-www-form-urlencoded'}, 'POST')
        if SQLITE:
            tok = secrets.token_hex(24)
            (site / 'setup-token').mkdir(exist_ok=True)
            (site / 'setup-token/setup-token.txt').write_text(tok)
            _, adm, _ = req(ed, ADM)
            form(ed, {'login_action': 'setup', 'csrf_token': re.search(r'name="csrf_token" value="([^"]+)"', adm)[1], 'username': 'editor', 'display_name': 'Editor', 'device_name': 'Test', 'setup_token': tok})
        else:
            form(ed, {'login_action': 'login', 'password': password})
        st, txt, hd = req(ed, VIS)
        check(st == 200 and 'ビジュアル編集' in txt and 'class="row"' in txt, 'logged-in editor sees the page picker')
        csp = hd.get('Content-Security-Policy', '')
        check("script-src 'self' 'nonce-" in csp and "object-src 'none'" in csp and "frame-ancestors 'self'" in csp, 'CSP blocks page scripts and framing')
        check(hd.get('X-Frame-Options') == 'SAMEORIGIN' and hd.get('Cache-Control') == 'no-store', 'framing / caching headers')

        st, lst, _ = req(ed, ADM + '?view=pages')
        check('href="visual.php?id=' in lst and 'edit_square' in lst, 'page list shows the visual edit button with its icon')
        st, page, hd = req(ed, VIS + '?id=index')
        check(st == 200 and 'window.__VISUAL_CSRF' in page and '<!--fv:' in page, 'editing page has CSRF token and block markers')
        nonce = re.search(r"nonce-([A-Za-z0-9_-]+)", hd['Content-Security-Policy'])[1]
        check(page.count('nonce="' + nonce + '"') >= 2, 'inline script carries the CSP nonce')
        token = json.loads(re.search(r'window\.__VISUAL_CSRF=("[^"]+")', page)[1])
        check(len(token) >= 32, 'CSRF token present')

        # ---- 3. 読み取り
        st, src, _ = api(ed, 'source', query='&id=index')
        check(st == 200 and src['text'], 'source readable when logged in')
        st, ms, _ = api(ed, 'mtime', query='&id=index')
        check(st == 200 and len(ms['t']) == 32, 'mtime token')
        st, bad, _ = api(ed, 'source', query='&id=../../config')
        check(st == 400, 'invalid page id refused')

        # ---- 4. 書き込みの拒否（CSRF・同一オリジン）
        orig = src['text']
        first = orig.split('\n')[0]
        body = {'id': 'index', 'start': 0, 'end': 0, 'old': first, 'mode': 'replace', 'new': first + ' CHANGED'}
        st, _, _ = api(ed, 'save', body)
        check(st == 403, 'write without CSRF token is refused')
        st, _, _ = api(ed, 'save', body, token='0' * 64)
        check(st == 403, 'write with a wrong CSRF token is refused')
        st, _, _ = api(ed, 'save', body, token=token, extra={'Origin': 'http://evil.example.com'})
        check(st == 403, 'cross-origin write is refused even with the token')
        st, _, _ = api(ed, 'save', body, token=token, extra={'Origin': 'http://' + host.replace('127.0.0.1', 'evil.example.com')})
        check(st == 403, 'origin with another host is refused')
        st, cur, _ = api(ed, 'source', query='&id=index')
        check(cur['text'] == orig, 'refused writes changed nothing')

        # ---- 5. 書き込み・元に戻す・やり直す
        st, r, _ = api(ed, 'save', body, token=token, extra={'Origin': base})
        check(st == 200 and r['ok'] and r['undo'] == 1, 'save with token and same origin works')
        st, cur, _ = api(ed, 'source', query='&id=index')
        check(cur['text'].split('\n')[0] == first + ' CHANGED', 'saved text visible')
        st, r, _ = api(ed, 'save', body, token=token)
        check(st == 409, 'stale write (old text mismatch) is refused')
        st, r, _ = api(ed, 'undo', {'id': 'index'}, token=token)
        check(st == 200 and r['redo'] == 1, 'undo')
        st, cur, _ = api(ed, 'source', query='&id=index')
        check(cur['text'] == orig, 'undo restores the original text')
        st, r, _ = api(ed, 'redo', {'id': 'index'}, token=token)
        check(st == 200, 'redo')
        st, r, _ = api(ed, 'undo', {'id': 'index'}, token=token)
        st, r, _ = api(ed, 'undo', {'id': 'index'}, token=token)
        check(st == 409, 'nothing left to undo')
        st, _, _ = api(ed, 'undo', {'id': 'index'})
        check(st == 403, 'undo without token is refused')

        # ---- 5b. 履歴: 編集の作業ごとに復元ポイント1件（10件まで）、元に戻すは直近3回分
        def cur_text():
            return api(ed, 'source', query='&id=index')[1]['text']
        base_text = cur_text()
        p0 = len(api(ed, 'points', query='&id=index')[1]['points'])
        def edit(sid, n):
            t = cur_text()
            return api(ed, 'save-md', {'id': 'index', 'old': t, 'new': t + '\nsid-' + str(n), 'sid': sid}, token=token)
        st, r, _ = edit('sessionAAAA1', 1)
        check(st == 200 and r['points'] == p0 + 1 and r['undo'] == 1, 'first save of an editing session makes one restore point')
        for n in range(2, 7):
            st, r, _ = edit('sessionAAAA1', n)
        check(r['points'] == p0 + 1, 'more saves in the same session do not add restore points')
        check(r['undo'] == 3, 'undo history keeps only the last 3 steps')
        st, r, _ = edit('sessionBBBB2', 7)
        check(r['points'] == p0 + 2 and r['undo'] == 1, 'a new session adds a restore point and drops the old undo steps')
        for k in range(12):
            edit('sess%08d' % k, 100 + k)
        st, r, _ = api(ed, 'points', query='&id=index')
        check(st == 200 and len(r['points']) == 10, 'restore points are limited to 10')
        st, r, _ = api(anon, 'points', query='&id=index')
        check(st == 401, 'restore point list needs login')
        st, r, _ = api(ed, 'restore', {'id': 'index', 'point': '12345678901234567890', 'sid': 'zzzzzzzzzz'}, token=token)
        check(st == 409, 'unknown restore point is refused')
        st, r, _ = api(ed, 'restore', {'id': 'index', 'point': '../../settings', 'sid': 'zzzzzzzzzz'}, token=token)
        check(st == 409, 'restore point id with path characters is refused')
        st, pts, _ = api(ed, 'points', query='&id=index')
        oldest = pts['points'][-1]['id']
        st, r, _ = api(ed, 'restore', {'id': 'index', 'point': oldest}, token=None)
        check(st == 403, 'restore without token is refused')
        before = cur_text()
        st, r, _ = api(ed, 'restore', {'id': 'index', 'point': oldest, 'sid': 'restoresess1'}, token=token)
        check(st == 200 and cur_text() != before and 'sid-102' not in cur_text() and 'sid-101' in cur_text(), 'restoring a point goes back to the state before that session')
        st, r, _ = api(ed, 'undo', {'id': 'index'}, token=token)
        check(st == 200 and cur_text() == before, 'a restore can be undone')
        php(site, "mbv_hist_forget('index');" if False else "require_once $argv[1].'/mikanBox/lib/visual-history.php'; mbv_hist_forget('index');")
        st, r, _ = api(ed, 'history', query='&id=index')
        check(r['undo'] == 0 and r['redo'] == 0, 'forgetting a page removes its undo history')
        if not SQLITE:   # SQLite版の復元ポイントは、管理画面のページ履歴（ページ削除のときに管理画面が消す）
            check(api(ed, 'points', query='&id=index')[1]['points'] == [], 'forgetting a page removes its restore points')
        # 元の本文に戻しておく（以降のテストのため）
        t = cur_text()
        api(ed, 'save-md', {'id': 'index', 'old': t, 'new': base_text, 'sid': 'resetsession1'}, token=token)

        # ---- 6. 本文全体・CSV・描画
        st, r, _ = api(ed, 'save-css', {'id': 'index', 'old': '', 'new': 'h1{color:red}'}, token=token)
        check(st == 200 and r['ok'], 'save css')
        st, r, _ = api(ed, 'save-css', {'id': 'index', 'old': 'wrong', 'new': 'x'}, token=token)
        check(st == 409, 'css conflict refused')
        st, r, _ = api(ed, 'render', {'id': 'index', 'text': '## 見出し\n\n本文'}, token=token)
        check(st == 200 and '見出し' in r['html'] and '<!--fv:' in r['html'], 'render a draft text')
        st, r, _ = api(ed, 'render', {'id': 'index', 'text': 'x'})
        check(st == 403, 'render without token is refused')
        st, r, _ = api(ed, 'scope-css', {'css': 'h1{color:red}'}, token=token)
        check(st == 200 and '.mikan-content-scope' in r['css'], 'scope css')

        # ---- 7. 新規作成・複製
        st, r, _ = api(ed, 'create', {'id': 'vt_new', 'title': 'テスト'}, token=token)
        check(st == 200 and r['id'] == 'vt_new', 'create page')
        status = php(site, 'echo loadData(POSTS_DIR,"vt_new")["status"];')
        check(status == 'draft', 'new page is a draft')
        st, r, _ = api(ed, 'create', {'id': 'vt_new'}, token=token)
        check(st == 409, 'duplicate id refused')
        st, r, _ = api(ed, 'create', {'id': 'bad id'}, token=token)
        check(st == 400, 'invalid id refused')
        for rid in ['mikanBox', 'mikanData/x', 'media', 'api/x']:
            st, r, _ = api(ed, 'create', {'id': rid}, token=token)
            check(st == 400, 'reserved id refused: ' + rid)
        st, r, _ = api(ed, 'create', {'id': 'vt_copy', 'from': 'vt_new', 'title': 'コピー'}, token=token)
        check(st == 200, 'duplicate page')
        st, r, _ = api(ed, 'create', {'id': 'vt_x', 'from': 'missing'}, token=token)
        check(st == 400, 'missing source refused')
        st, r, _ = api(ed, 'create', {'id': 'vt_csrf'})
        check(st == 403, 'create without token refused')

        # ---- 8. 画像
        png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
        def upload(data, tok=token):
            st, txt, _ = req(ed, VIS + '?api=upload', data, {'X-CSRF-Token': tok} if tok else {}, 'POST')
            return st, json.loads(txt)
        st, r = upload(png)
        check(st == 200 and re.match(r'^img-\d{8}-\d{6}-[0-9a-f]{4}\.png$', r['name']), 'png upload')
        check((site / 'media' / r['name']).is_file(), 'uploaded file exists in media')
        st, r = upload(b'<?php echo 1; ?>')
        check(st == 400, 'php payload refused')
        st, r = upload(b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')
        check(st == 400, 'svg refused')
        st, r = upload(b'GIF89a' + b'\x00' * 10 + b'<?php ?>')
        check(st == 400, 'fake image refused')
        st, r = upload(png, tok='')
        check(st == 403, 'upload without token refused')
        st, r = upload(b'')
        check(st == 400, 'empty upload refused')

        # ---- 9. 履歴フォルダは外から読めない
        hist = site / 'mikanData' / 'visual-history'
        check(hist.is_dir() and (hist / '.htaccess').is_file(), 'history folder is inside the protected data folder')
        files = [p for p in hist.rglob('*.json')]
        check(files, 'history snapshots exist')
        # 履歴のフォルダ名に、ページIDを使わない
        check(not any('index' in p.parts for p in files), 'history folder names do not leak page ids')
        rel = '/mikanData/visual-history/' + files[0].relative_to(hist).as_posix()
        st, txt, _ = req(ed, rel)
        check(st in (403, 404), 'history snapshot is not downloadable over HTTP')
        st, txt, _ = req(ed, '/mikanData/settings.json')
        check(st in (403, 404), 'data folder is not downloadable over HTTP')

        # ---- 9b. 言語: 英語にすると、画面もエラーも英語になる
        php(site, '$s=loadSettings(); $s["system_lang"]="en"; saveSettings($s);')
        st, txt, hd = req(ed, VIS)
        check(st == 200 and '<html lang="en">' in txt and 'Visual edit' in txt and 'Search pages' in txt, 'picker is in English')
        st, page, hd = req(ed, VIS + '?id=index')
        tdict = json.loads(re.search(r'window\.__VISUAL_T=(\{.*?\});', page)[1])
        check(tdict['visual_js_saved'] == 'Saved' and tdict['btn_close'] == 'Close' and len(tdict) >= 100, 'JS strings are delivered in English')
        st, r, _ = api(ed, 'save-css', {'id': 'index', 'old': 'wrong', 'new': 'x'}, token=token)
        check(st == 409 and r['error'].startswith('The CSS has been changed'), 'server errors are in English')
        st, r, _ = api(ed, 'create', {'id': 'bad id'}, token=token)
        check(r['error'].startswith('IDs may only contain'), 'create error is in English')
        php(site, '$s=loadSettings(); $s["system_lang"]="ja"; saveSettings($s);')
        st, page, hd = req(ed, VIS + '?id=index')
        check('"visual_js_saved":"保存しました"' in page, 'JS strings are delivered in Japanese')

        # ---- 10. ログアウト後は、すべて断る
        if not SQLITE:   # SQLite版には、ログアウトがない（端末を停止する）
            req(ed, ADM + '?action=logout')
            st, _, _ = api(ed, 'source', query='&id=index')
            check(st == 401, 'API is refused after logout')
            st, txt, hd = req(ed, VIS + '?id=index')
            check(st == 302, 'page is refused after logout')

        # ---- 11. 出力が汚れていない（PHPの警告・通知がない）
        log.seek(0)
        out = log.read()
        check(not re.search(r'Warning|Fatal|Deprecated|Notice', out), 'PHP runtime is clean\n' + out[-1500:])
    finally:
        server.terminate()
        server.wait(timeout=10)
        log.close()

print('visual tests passed: %d checks' % checks)
