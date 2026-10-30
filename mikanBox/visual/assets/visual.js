// flat-visual: 描画されたページを、その場で直す（ローカル専用）
//  ・本文のブロックをクリック → 元のMarkdownを直す（画像の貼り付け/ドラッグ/ボタン。保存しても再読み込みしない）
//  ・本文全体のMarkdown / ページ専用CSS を、入力しながらページへ反映
//  ・元に戻す / やり直す（本文・CSSの保存ごと）
//  ・要素をクリックしてセレクタを知る
//  ・画面幅（スマホ/タブレット/PC）で確かめる
//  アイコンは、管理画面と同じ Material Symbols Outlined（線のアイコン）を使う。
(function () {
  if (/[?&]fv-frame=1/.test(location.search)) return;   // 画面幅の確認用 iframe の中では、編集機能を出さない
  var PAGE = window.__VISUAL_PAGE;
  var ADMIN = window.__VISUAL_ADMIN || {};
  // 画面の文字（サーバーが、いまの言語で渡す）。%s は、順に引数で置き換える
  var TT = window.__VISUAL_T || {};
  function T(key) {
    var a = arguments, i = 1, s = TT[key] !== undefined ? String(TT[key]) : key;
    return s.replace(/%([s%])/g, function (m, c) { if (c === '%') return '%'; var v = a[i++]; return v === undefined ? '' : v; });
  }
  function H(t) { return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
  function API(name) { return ADMIN.api + '?api=' + name; }
  var root = document.documentElement;
  var on = sessionStorage.getItem('fv-on') !== '0';
  root.classList.toggle('fv-on', on);

  function ic(name) { return '<span class="material-symbols-outlined fv-ic">' + name + '</span>'; }
  function el(tag, cls, text, title) {
    var x = document.createElement(tag); if (cls) x.className = cls; if (text) x.textContent = text;
    if (title) { x.title = title; x.setAttribute('aria-label', title); } if (tag === 'button') x.type = 'button'; return x;
  }
  /** アイコン（＋任意のラベル）つきボタン */
  function ibtn(icon, title, cls, label) {
    var b = el('button', cls || '', '', title); b.innerHTML = ic(icon) + (label ? '<span class="fv-lb">' + H(label) + '</span>' : ''); return b;
  }
  function jfetch(url, opts) { return fetch(url, opts).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); }); }
  var CSRF = window.__VISUAL_CSRF || '';
  var SID = (function () { var a = new Uint8Array(12); crypto.getRandomValues(a); return Array.prototype.map.call(a, function (b) { return (b % 36).toString(36); }).join(''); })();   // ページを開くたびに変わる「編集の作業」の目印
  function post(url, body) { if (body && typeof body === 'object') body.sid = SID; return jfetch(url, { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: JSON.stringify(body) }); }
  function scopeEl() { return document.querySelector('.mikan-content-scope'); }
  var PAGES = window.__VISUAL_PAGES || [];
  // 最近開いたページ（このブラウザだけ。サイトごと）
  var RKEY = 'fv-recent:' + (ADMIN.site || '/');
  function loadRecent() { try { return JSON.parse(localStorage.getItem(RKEY) || '[]'); } catch (e) { return []; } }
  function pushRecent(id) { try { var l = loadRecent().filter(function (x) { return x !== id; }); l.unshift(id); localStorage.setItem(RKEY, JSON.stringify(l.slice(0, 20))); } catch (e) {} }
  pushRecent(PAGE);
  /** 目印のコメント <!--fv:a-b--> … <!--/fv--> の間にある要素に、data-ln="a-b" を付ける */
  function applyLn(rootEl) {
    var w = document.createTreeWalker(rootEl, NodeFilter.SHOW_COMMENT), list = [], n;
    while ((n = w.nextNode())) list.push(n);
    list.forEach(function (c) {
      var m = /^fv:(\d+-\d+)$/.exec(c.nodeValue); if (!m) return;
      for (var x = c.nextSibling; x; x = x.nextSibling) {
        if (x.nodeType === 8 && /^\/?fv/.test(x.nodeValue)) break;
        if (x.nodeType === 1 && !x.hasAttribute('data-ln')) x.setAttribute('data-ln', m[1]);
      }
    });
  }
  /** サイト内のページへのリンクを、ビジュアル編集の同じページへ向ける（普通のURLでは404になることがあるため） */
  function rewriteLinks(rootEl) {
    var ids = {}; PAGES.forEach(function (p) { ids[p.id] = 1; });
    [].slice.call(rootEl.querySelectorAll('a[href]')).forEach(function (a) {
      if (a.closest('.fv-ui')) return;
      var u; try { u = new URL(a.getAttribute('href'), location.href); } catch (e) { return; }
      if (u.origin !== location.origin || (ADMIN.pick && u.pathname.indexOf(ADMIN.pick) === 0)) return;
      var base = ADMIN.site || '/', path = decodeURIComponent(u.pathname);
      if (path.indexOf(base) !== 0) return;
      path = path.slice(base.length).replace(/^\/+/, '').replace(/\.html$/, '').replace(/\/index$/, '').replace(/\/+$/, '');
      var id = (path === '' || path === 'index.php') ? (u.searchParams.get('page') || 'index') : path;
      if (ids[id]) a.setAttribute('href', ADMIN.view + id.split('/').map(encodeURIComponent).join('/') + u.hash);
    });
  }
  function linkPageId(a) {
    try { var u = new URL(a.getAttribute('href'), location.href); var m = (ADMIN.view || '').split('?')[0]; if (u.pathname !== m) return null; return u.searchParams.get('id'); } catch (e) { return null; }
  }
  applyLn(document.body); rewriteLinks(document.body);

  // ---------------------------------------------------------------- ドック（右下のボタン）
  var dock = el('div', 'fv-dock fv-ui');
  var tgPages = ibtn('menu_open', T('visual_js_pages_title'), 'fv-toggle fv-icon');
  var lnkList = el('a', 'fv-toggle fv-icon', '', T('visual_js_admin_list_title')); lnkList.innerHTML = ic('dashboard'); lnkList.href = ADMIN.list || '#'; lnkList.target = '_blank'; lnkList.rel = 'noopener';
  var lnkPage = el('a', 'fv-toggle fv-icon', '', T('visual_js_admin_page_title')); lnkPage.innerHTML = ic('tune'); lnkPage.href = ADMIN.page || '#'; lnkPage.target = '_blank'; lnkPage.rel = 'noopener';
  var sep1 = el('span', 'fv-sep');
  var tgUndo = ibtn('undo', T('visual_js_undo_title'), 'fv-toggle fv-icon');
  var tgRedo = ibtn('redo', T('visual_js_redo_title'), 'fv-toggle fv-icon');
  var tgPoints = ibtn('history', T('visual_js_points_title'), 'fv-toggle fv-icon');
  var sep2 = el('span', 'fv-sep');
  var tgView = ibtn('devices', T('visual_js_view_title'), 'fv-toggle fv-icon');
  var tgInspect = ibtn('search', T('visual_js_inspect_title'), 'fv-toggle fv-icon');
  var tgMd = ibtn('edit_note', T('visual_js_md_title'), 'fv-toggle', T('visual_js_md_label'));
  var tgCss = ibtn('palette', T('visual_js_css_title'), 'fv-toggle', 'CSS');
  var tgEdit = ibtn('edit', T('visual_js_edit_toggle_title'), 'fv-toggle');
  dock.append(tgPages, lnkList, lnkPage, sep1, tgUndo, tgRedo, tgPoints, sep2, tgView, tgInspect, tgMd, tgCss, tgEdit); document.body.appendChild(dock);
  function paintEdit() { tgEdit.innerHTML = ic('edit') + '<span class="fv-lb">' + H(on ? T('visual_js_editing') : T('visual_js_edit_off')) + '</span>'; tgEdit.classList.toggle('active', on); }
  paintEdit();
  tgEdit.addEventListener('click', function () { on = !on; sessionStorage.setItem('fv-on', on ? '1' : '0'); root.classList.toggle('fv-on', on); paintEdit(); });

  var toastEl = null, toastTimer = null;
  function toast(t) {
    if (!toastEl) { toastEl = el('div', 'fv-toast fv-ui'); document.body.appendChild(toastEl); }
    toastEl.textContent = t; toastEl.style.opacity = '1'; clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.style.opacity = '0'; }, 2200);
  }

  // ---------------------------------------------------------------- 元に戻す / やり直す
  var active = null;
  function refreshHistory() {
    jfetch(API('history') + '&id=' + encodeURIComponent(PAGE), { cache: 'no-store' }).then(function (x) {
      tgUndo.disabled = !x.j.undo; tgRedo.disabled = !x.j.redo;
      tgUndo.title = T('visual_js_undo_title') + (x.j.undo ? T('visual_js_times_left', x.j.undo) : '');
      tgRedo.title = T('visual_js_redo_title') + (x.j.redo ? T('visual_js_times_left', x.j.redo) : '');
    }).catch(function () {});
  }
  function history(kind) {
    if (active && active.dirty() && !confirm(T(kind === 'undo' ? 'visual_js_confirm_undo' : 'visual_js_confirm_redo'))) return;
    post(API(kind), { id: PAGE }).then(function (x) {
      if (!x.ok) { toast(x.j.error || T('visual_js_failed')); return; }
      sessionStorage.setItem('fv-scroll', String(window.scrollY)); location.reload();
    });
  }
  tgUndo.addEventListener('click', function () { history('undo'); });
  tgRedo.addEventListener('click', function () { history('redo'); });
  document.addEventListener('keydown', function (e) {
    if (!(e.metaKey || e.ctrlKey) || e.key.toLowerCase() !== 'z') return;
    if (e.target.closest && e.target.closest('textarea,input')) return;   // 入力欄の中では、ブラウザ標準の取り消し
    e.preventDefault(); history(e.shiftKey ? 'redo' : 'undo');
  });
  refreshHistory();

  // ---------------------------------------------------------------- 編集の履歴（作業の前の状態に戻す）
  var pointsBox = null;
  function closePoints() { if (pointsBox) { pointsBox.remove(); pointsBox = null; } tgPoints.classList.remove('active'); }
  tgPoints.addEventListener('click', function () {
    if (pointsBox) { closePoints(); return; }
    tgPoints.classList.add('active');
    pointsBox = el('div', 'fv-drawer fv-ui');
    var head = el('div', 'fv-drawer-head'), ttl = el('div', 'fv-create-title', T('visual_js_points_head')); ttl.style.flex = '1';
    var close = ibtn('close', T('btn_close')); close.addEventListener('click', closePoints); head.append(ttl, close);
    var note = el('div', 'fv-drawer-more', T('visual_js_points_note')), list = el('div', 'fv-drawer-list');
    pointsBox.append(head, note, list); document.body.appendChild(pointsBox);
    jfetch(API('points') + '&id=' + encodeURIComponent(PAGE), { cache: 'no-store' }).then(function (x) {
      var pts = (x.j && x.j.points) || [];
      if (!pts.length) { list.append(el('div', 'fv-drawer-row', T('visual_js_points_empty'))); return; }
      pts.forEach(function (p) {
        var r = el('a', 'fv-drawer-row'); r.href = '#'; r.append(el('span', 't', p.at));
        r.addEventListener('click', function (e) {
          e.preventDefault();
          if (active && active.dirty() && !confirm(T('visual_js_confirm_undo'))) return;
          if (!confirm(T('visual_js_point_confirm', p.at))) return;
          post(API('restore'), { id: PAGE, point: p.id }).then(function (y) {
            if (!y.ok) { toast(y.j.error || T('visual_js_failed')); return; }
            sessionStorage.setItem('fv-scroll', String(window.scrollY)); location.reload();
          });
        });
        list.append(r);
      });
    }).catch(function () {});
  });

  // ---------------------------------------------------------------- ショートカット（入力欄の外で）: g l = 管理画面（ページ一覧） / g e = このページの設定（管理画面） / g p = ページ選択
  var gPending = false, gTimer = null;
  function goUrl(url) {
    if (!url) return;
    if (active && active.dirty() && !confirm(T('visual_js_confirm_leave'))) return;
    location.href = url;
  }
  document.addEventListener('keydown', function (e) {
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    var t = e.target; if (t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable)) return;
    if (!gPending) {
      if (e.key === 'g') { gPending = true; toast(T('visual_js_g_hint')); clearTimeout(gTimer); gTimer = setTimeout(function () { gPending = false; }, 1000); }
      return;
    }
    gPending = false; clearTimeout(gTimer);
    if (e.key === 'l') { e.preventDefault(); window.open(ADMIN.list, '_blank', 'noopener'); }      // 管理画面は、別のウィンドウ（タブ）で開く
    else if (e.key === 'e') { e.preventDefault(); window.open(ADMIN.page, '_blank', 'noopener'); }
    else if (e.key === 'p') { e.preventDefault(); goUrl(ADMIN.pick); }
  });

  // ---------------------------------------------------------------- ページの切り替え（横から出る一覧。⌘/Ctrl+K）
  var drawer = null;
  function closeDrawer() { if (drawer) { drawer.remove(); drawer = null; } tgPages.classList.remove('active'); }
  function goPage(id) {
    if (id === PAGE) { closeDrawer(); return; }      // いま開いているページを選んだら、閉じるだけ
    if (active && active.dirty() && !confirm(T('visual_js_confirm_leave'))) return;
    location.href = ADMIN.view + id.split('/').map(encodeURIComponent).join('/');
  }
  function suggestId(base) {
    var have = {}; PAGES.forEach(function (p) { have[p.id] = 1; });
    var cand = base; for (var i = 2; have[cand]; i++) cand = base + '-' + i; return cand;
  }
  function openDrawer() {
    if (drawer) { closeDrawer(); return; }
    tgPages.classList.add('active');
    drawer = el('div', 'fv-drawer fv-ui');
    var head = el('div', 'fv-drawer-head'), input = el('input'); input.type = 'search'; input.placeholder = T('visual_search_placeholder');
    var close = ibtn('close', T('btn_close')); close.addEventListener('click', closeDrawer);
    head.append(input, close);
    var tools = el('div', 'fv-drawer-tools');
    var bNew = ibtn('add', T('visual_js_new_page_title'), 'fv-dt', T('visual_js_new_page')), bDup = ibtn('content_copy', T('visual_js_dup_current_title'), 'fv-dt', T('visual_js_dup_current'));
    tools.append(bNew, bDup);
    var list = el('div', 'fv-drawer-list'), form = el('div', 'fv-create'), more = el('a', 'fv-drawer-more', T('visual_js_all_pages_link')); more.href = ADMIN.pick || '#';
    form.style.display = 'none';
    drawer.append(head, tools, list, form, more); document.body.appendChild(drawer);

    // ---- 新規作成 / 複製のフォーム
    function showForm(from) {
      var src = from ? PAGES.filter(function (p) { return p.id === from; })[0] : null;
      list.style.display = 'none'; tools.style.display = 'none'; head.style.display = 'none'; form.style.display = ''; form.textContent = '';
      form.append(el('div', 'fv-create-title', src ? T('visual_js_dup_title', src.title || src.id) : T('visual_js_new_page_title')));
      var lid = el('label', '', T('visual_js_field_id')), iid = el('input'); iid.type = 'text'; iid.spellcheck = false;
      iid.placeholder = src ? '' : T('visual_js_id_placeholder'); iid.value = src ? suggestId(src.id + '-copy') : '';
      lid.appendChild(iid);
      var ltt = el('label', '', T('label_title')), itt = el('input'); itt.type = 'text'; itt.value = src ? (src.title || src.id) + T('visual_copy_suffix') : ''; ltt.appendChild(itt);
      var note = el('div', 'fv-create-note', T('visual_js_create_note'));
      var err = el('div', 'fv-msg');
      var row = el('div', 'fv-create-btns'), ok = ibtn('check', T('visual_js_create'), 'fv-save', src ? T('visual_js_duplicate') : T('visual_js_create')), cancel = ibtn('close', T('visual_js_cancel_esc'), '', T('btn_cancel'));
      row.append(ok, cancel); form.append(lid, ltt, note, err, row);
      function back() { form.style.display = 'none'; list.style.display = ''; tools.style.display = ''; head.style.display = ''; input.focus(); }
      function submit() {
        var id = iid.value.trim().replace(/^\/+|\/+$/g, '');
        if (id && !/^[A-Za-z0-9_-]+(\/[A-Za-z0-9_-]+)*$/.test(id)) { err.textContent = T('visual_err_id_chars'); return; }
        if (id && PAGES.some(function (p) { return p.id === id; })) { err.textContent = T('visual_err_id_exists'); return; }
        ok.disabled = true;
        post(API('create'), { id: id, title: itt.value, from: src ? src.id : '' }).then(function (x) {
          if (!x.ok) { err.textContent = x.j.error || T('visual_js_create_failed'); ok.disabled = false; return; }
          location.href = ADMIN.view + x.j.id.split('/').map(encodeURIComponent).join('/');
        }).catch(function () { err.textContent = T('visual_js_create_failed'); ok.disabled = false; });
      }
      ok.addEventListener('click', submit); cancel.addEventListener('click', back);
      form.onkeydown = function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); submit(); } else if (ev.key === 'Escape') { ev.stopPropagation(); back(); } };
      (src ? itt : iid).focus(); (src ? itt : iid).select();
    }
    bNew.addEventListener('click', function () { showForm(null); });
    bDup.addEventListener('click', function () { showForm(PAGE); });

    // ---- 一覧
    var sel = 0, rows = [];
    function render() {
      var q = input.value.trim().toLowerCase(); list.textContent = '';
      rows = PAGES.filter(function (p) { return !q || (p.title + ' ' + p.id).toLowerCase().indexOf(q) >= 0; });
      // 並び順: いま開いているページ → 最近開いたページ（新しい順）→ そのほか（管理画面と同じ順）
      var byId = {}; rows.forEach(function (p) { byId[p.id] = p; });
      var cur = rows.filter(function (p) { return p.id === PAGE; });
      var recs = loadRecent().filter(function (id) { return id !== PAGE && byId[id]; }).map(function (id) { return byId[id]; });
      var seen = {}; recs.forEach(function (p) { seen[p.id] = 1; });
      var rest = rows.filter(function (p) { return p.id !== PAGE && !seen[p.id]; });
      rows = cur.concat(recs, rest);
      if (sel >= rows.length) sel = Math.max(0, rows.length - 1);
      rows.forEach(function (p, i) {
        if (!q && recs.length) {   // 検索していないときだけ、見出しを出す
          if (i === cur.length) list.appendChild(el('div', 'fv-drawer-label', T('visual_js_recent')));
          if (i === cur.length + recs.length && rest.length) list.appendChild(el('div', 'fv-drawer-label', T('visual_js_all')));
        }
        var item = el('div', 'fv-drawer-item');
        var a = el('a', 'fv-drawer-row' + (p.id === PAGE ? ' current' : '') + (i === sel ? ' sel' : ''));
        a.href = ADMIN.view + p.id.split('/').map(encodeURIComponent).join('/');
        a.append((function () { var t = el('span', 't', p.title || T('visual_untitled')); if (p.id === PAGE) t.setAttribute('data-current', T('visual_js_current')); return t; })(), el('span', 'i', p.id));
        a.addEventListener('click', function (ev) { if (ev.metaKey || ev.ctrlKey) return; ev.preventDefault(); goPage(p.id); });
        var dup = ibtn('content_copy', T('visual_js_dup_title', p.title || p.id), 'fv-row-dup'); dup.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); showForm(p.id); });
        item.append(a, dup); list.appendChild(item);
      });
      var cur = list.querySelector('.sel'); if (cur && cur.scrollIntoView) cur.scrollIntoView({ block: 'nearest' });
    }
    input.addEventListener('input', function () { sel = 0; render(); });
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowDown') { ev.preventDefault(); sel = Math.min(rows.length - 1, sel + 1); render(); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); sel = Math.max(0, sel - 1); render(); }
      else if (ev.key === 'Enter') { ev.preventDefault(); if (rows[sel]) goPage(rows[sel].id); }
      else if (ev.key === 'Escape') { closeDrawer(); }
    });
    render(); input.focus();
  }
  tgPages.addEventListener('click', openDrawer);
  document.addEventListener('keydown', function (e) {
    if (!(e.metaKey || e.ctrlKey) || e.shiftKey || e.altKey || e.key.toLowerCase() !== 'k') return;
    if (e.target.closest && e.target.closest('textarea')) return;       // 入力欄の中の ⌘K は、リンクの挿入
    e.preventDefault(); openDrawer();
  });

  // ---------------------------------------------------------------- 外部での変更を検知して再読み込み
  var last = null;
  setInterval(function () {
    fetch(API('mtime') + '&id=' + encodeURIComponent(PAGE), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
      if (last !== null && j.t !== last) { sessionStorage.setItem('fv-scroll', String(window.scrollY)); location.reload(); }
      last = j.t;
    }).catch(function () {});
  }, 1000);
  function syncMtime() { fetch(API('mtime') + '&id=' + encodeURIComponent(PAGE), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) { last = j.t; }); }
  var y0 = sessionStorage.getItem('fv-scroll');
  if (y0) { sessionStorage.removeItem('fv-scroll'); window.addEventListener('load', function () { window.scrollTo(0, +y0); }); }

  // ---------------------------------------------------------------- 本文だけを、再読み込みなしで差し替える
  function refreshContent() {
    var y = window.scrollY;
    return fetch(location.href, { cache: 'no-store' }).then(function (r) { return r.text(); }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var neu = doc.querySelector('.mikan-content-scope'), cur = scopeEl();
      if (!neu || !cur) { location.reload(); return; }
      cur.innerHTML = neu.innerHTML; applyLn(cur); rewriteLinks(cur); window.scrollTo(0, y);
      syncMtime(); refreshHistory();
    });
  }

  // ---------------------------------------------------------------- 画面幅の確認
  var vp = null, vpFrame = null, vpWidth = 375;
  function liveDocs() { var d = [document]; try { if (vpFrame && vpFrame.contentDocument) d.push(vpFrame.contentDocument); } catch (e) {} return d; }
  function closeViewport() { if (vp) { vp.remove(); vp = null; vpFrame = null; } tgView.classList.remove('active'); root.classList.remove('fv-vp-open'); }
  function openViewport() {
    if (vp) { closeViewport(); return; }
    tgView.classList.add('active'); root.classList.add('fv-vp-open');
    vp = el('div', 'fv-vp fv-ui');
    var bar = el('div', 'fv-vp-bar');
    var sizes = [['smartphone', '375', 375], ['tablet_mac', '768', 768], ['desktop_windows', '1200', 1200], ['width_full', T('visual_js_full_width'), 0]];
    var btns = sizes.map(function (s) { var b = ibtn(s[0], T('visual_js_show_at', s[1]), 'fv-vp-b', s[1]); b.addEventListener('click', function () { setWidth(s[2]); }); return b; });
    var close = ibtn('close', T('btn_close'), 'fv-vp-close'); close.addEventListener('click', closeViewport);
    bar.append.apply(bar, btns.concat([close]));
    var stage = el('div', 'fv-vp-stage');
    vpFrame = el('iframe'); vpFrame.src = location.pathname + (location.search ? location.search + '&' : '?') + 'fv-frame=1';
    stage.appendChild(vpFrame); vp.append(bar, stage); document.body.appendChild(vp);
    function setWidth(w) { vpWidth = w; vpFrame.style.width = w ? w + 'px' : '100%'; btns.forEach(function (b, i) { b.classList.toggle('on', sizes[i][2] === w); }); }
    setWidth(vpWidth);
    vpFrame.addEventListener('load', function () { if (active && active.dirty()) active.live(); });
  }
  tgView.addEventListener('click', openViewport);

  // ---------------------------------------------------------------- テキスト編集の共通部品（元に戻す履歴を壊さない）
  function fire(ta) { ta.dispatchEvent(new Event('input')); }
  /** [start,end) を text に置き換える。execCommand を使うので、入力欄の ⌘/Ctrl+Z がそのまま効く。 */
  function replaceRange(ta, start, end, text, selS, selE) {
    ta.focus(); ta.setSelectionRange(start, end);
    var ok = false; try { ok = document.execCommand('insertText', false, text); } catch (e) {}
    if (!ok) { ta.setRangeText(text, start, end, 'end'); fire(ta); }
    ta.setSelectionRange(selS, selE);
  }
  function wrapSel(ta, pre, post, ph) {
    var v = ta.value, s = ta.selectionStart, e = ta.selectionEnd, sel = v.slice(s, e);
    if (sel && v.slice(s - pre.length, s) === pre && v.slice(e, e + post.length) === post) {          // すでに囲まれていれば外す
      replaceRange(ta, s - pre.length, e + post.length, sel, s - pre.length, e - pre.length);
    } else if (sel && sel.indexOf(pre) === 0 && sel.slice(-post.length) === post && sel.length >= pre.length + post.length) {
      var inner = sel.slice(pre.length, sel.length - post.length); replaceRange(ta, s, e, inner, s, s + inner.length);
    } else {
      var t = sel || ph; replaceRange(ta, s, e, pre + t + post, s + pre.length, s + pre.length + t.length);
    }
  }
  function mapLines(ta, fn) {
    var v = ta.value, s = v.lastIndexOf('\n', ta.selectionStart - 1) + 1, eIdx = v.indexOf('\n', ta.selectionEnd); if (eIdx < 0) eIdx = v.length;
    var text = fn(v.slice(s, eIdx).split('\n')).join('\n'); replaceRange(ta, s, eIdx, text, s, s + text.length);
  }
  function headingCycle(ta) {
    mapLines(ta, function (ls) {
      var m = /^(#{1,6})\s/.exec(ls[0]), next = !m ? 2 : (m[1].length < 4 ? m[1].length + 1 : 0);
      return ls.map(function (l) { var b = l.replace(/^#{1,6}\s+/, ''); return next ? '#'.repeat(next) + ' ' + b : b; });
    });
  }
  function listToggle(ta, ordered) {
    mapLines(ta, function (ls) {
      var all = ls.every(function (l) { return ordered ? /^\d+\.\s/.test(l) : /^[-*+]\s/.test(l); });
      return ls.map(function (l, i) { var b = l.replace(/^(\d+\.|[-*+])\s+/, ''); return all ? b : (ordered ? (i + 1) + '. ' : '- ') + b; });
    });
  }
  function quoteToggle(ta) {
    mapLines(ta, function (ls) { var all = ls.every(function (l) { return /^>\s?/.test(l); }); return ls.map(function (l) { return all ? l.replace(/^>\s?/, '') : '> ' + l; }); });
  }
  function linkInsert(ta) {
    var v = ta.value, s = ta.selectionStart, e = ta.selectionEnd, sel = v.slice(s, e);
    if (/^https?:\/\/\S+$/.test(sel)) { var t = T('visual_js_link_text'); replaceRange(ta, s, e, '[' + t + '](' + sel + ')', s + 1, s + 1 + t.length); }
    else { var label = sel || T('visual_js_link_text'); replaceRange(ta, s, e, '[' + label + '](https://)', s + label.length + 3, s + label.length + 3 + 8); }
  }
  function uploadImage(file, ta, say) {
    say(T('visual_js_uploading'));
    return fetch(API('upload'), { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: file }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); }).then(function (x) {
      if (!x.ok) { say(x.j.error || T('visual_js_upload_failed')); return; }
      var tag = '![](' + x.j.name + ')', s0 = ta.selectionStart; replaceRange(ta, s0, ta.selectionEnd, tag, s0 + tag.length, s0 + tag.length); say('');
    }).catch(function () { say(T('visual_js_upload_failed')); });
  }
  function firstImage(list) { for (var i = 0; i < list.length; i++) { if (list[i].type && list[i].type.indexOf('image/') === 0) return list[i]; } return null; }
  /** Markdownのクイック操作バー（左から: 見出し・太字・斜体・リンク・箇条書き・番号・引用・コード・画像） */
  function mdToolbar(ta, say, dark) {
    var tb = el('div', 'fv-tb' + (dark ? ' fv-tb-dark' : ''));
    function b(icon, title, fn) { var x = ibtn(icon, title, ''); x.tabIndex = -1; x.addEventListener('mousedown', function (ev) { ev.preventDefault(); }); x.addEventListener('click', function () { fn(); }); tb.appendChild(x); return x; }
    b('title', T('visual_js_tb_heading'), function () { headingCycle(ta); });
    b('format_bold', T('visual_js_tb_bold'), function () { wrapSel(ta, '**', '**', T('visual_js_ph_bold')); });
    b('format_italic', T('visual_js_tb_italic'), function () { wrapSel(ta, '*', '*', T('visual_js_ph_italic')); });
    b('link', T('visual_js_tb_link'), function () { linkInsert(ta); });
    b('format_list_bulleted', T('visual_js_tb_bullets'), function () { listToggle(ta, false); });
    b('format_list_numbered', T('visual_js_tb_numbered'), function () { listToggle(ta, true); });
    b('format_quote', T('visual_js_tb_quote'), function () { quoteToggle(ta); });
    b('code', T('visual_js_tb_code'), function () { wrapSel(ta, '`', '`', T('visual_js_ph_code')); });
    var file = el('input'); file.type = 'file'; file.accept = 'image/png,image/jpeg,image/gif,image/webp'; file.style.display = 'none';
    file.addEventListener('change', function () { if (file.files[0]) uploadImage(file.files[0], ta, say); file.value = ''; });
    b('image', T('visual_js_tb_image'), function () { file.click(); });
    tb.appendChild(file);
    ta.addEventListener('keydown', function (ev) {
      if (!(ev.metaKey || ev.ctrlKey) || ev.shiftKey || ev.altKey) return;
      var k = ev.key.toLowerCase();
      if (k === 'b') { ev.preventDefault(); wrapSel(ta, '**', '**', T('visual_js_ph_bold')); }
      else if (k === 'i') { ev.preventDefault(); wrapSel(ta, '*', '*', T('visual_js_ph_italic')); }
      else if (k === 'k') { ev.preventDefault(); linkInsert(ta); }
    });
    ta.addEventListener('paste', function (ev) { var f = ev.clipboardData && firstImage(ev.clipboardData.files); if (f) { ev.preventDefault(); uploadImage(f, ta, say); } });
    ta.addEventListener('dragover', function (ev) { ev.preventDefault(); });
    ta.addEventListener('drop', function (ev) { var f = ev.dataTransfer && firstImage(ev.dataTransfer.files); if (f) { ev.preventDefault(); uploadImage(f, ta, say); } });
    return tb;
  }

  // ---------------------------------------------------------------- 下から出る編集パネル（本文 / CSS）
  function applyScoped(doc, scoped) {
    var re = /[^{}]*\.fv-css-start\s*\{\s*\}[\s\S]*?\.fv-css-end\s*\{\s*\}/;
    var st = [].slice.call(doc.querySelectorAll('style')).filter(function (x) { return re.test(x.textContent); })[0];
    if (!st) return;
    st.textContent = st.textContent.replace(re, function () { return scoped; });
  }
  var KINDS = {
    css: {
      btn: tgCss, icon: 'palette', title: T('visual_js_css_panel'), note: T('visual_js_panel_note'), ph: T('visual_js_css_placeholder'),
      load: function () { return jfetch(API('css') + '&id=' + encodeURIComponent(PAGE), { cache: 'no-store' }).then(function (x) { return x.j.css; }); },
      save: function (o, n) { return post(API('save-css'), { id: PAGE, old: o, new: n }); },
      live: function (text) { return post(API('scope-css'), { css: text }).then(function (x) { if (x.ok) liveDocs().forEach(function (d) { applyScoped(d, x.j.css); }); }); }
    },
    md: {
      btn: tgMd, icon: 'edit_note', title: T('visual_js_md_panel'), note: T('visual_js_panel_note'), ph: T('visual_js_md_placeholder'),
      load: function () { return jfetch(API('source') + '&id=' + encodeURIComponent(PAGE), { cache: 'no-store' }).then(function (x) { return x.j.text; }); },
      save: function (o, n) { return post(API('save-md'), { id: PAGE, old: o, new: n }); },
      live: function (text) { return post(API('render'), { id: PAGE, text: text }).then(function (x) { if (!x.ok || !scopeEl()) return; var neu = new DOMParser().parseFromString(x.j.html, 'text/html').querySelector('.mikan-content-scope'); if (neu) { scopeEl().innerHTML = neu.innerHTML; applyLn(scopeEl()); rewriteLinks(scopeEl()); } }); }
    }
  };
  function closePanel(force) {
    if (!active) return true;
    if (!force && active.dirty() && !confirm(T('visual_js_confirm_close'))) return false;
    var was = active.dirty();
    active.node.remove(); active.btn.classList.remove('active'); active = null;
    sessionStorage.setItem('fv-panel', ''); root.classList.remove('fv-panel-open');
    if (was) { sessionStorage.setItem('fv-scroll', String(window.scrollY)); location.reload(); }
    return true;
  }
  function openPanel(kind) {
    if (active && active.kind === kind) return;
    if (active && !closePanel(false)) return;
    var K = KINDS[kind];
    sessionStorage.setItem('fv-panel', kind); K.btn.classList.add('active'); root.classList.add('fv-panel-open');
    K.load().then(function (old) {
      var dirty = false, timer = null;
      var node = el('div', 'fv-panel fv-ui');
      var head = el('div', 'fv-panel-head'), msg = el('span', 'fv-msg');
      var title = el('strong'); title.innerHTML = ic(K.icon) + '<span>' + K.title + '</span>';
      var save = ibtn('save', T('visual_js_save_title'), 'fv-save'), close = ibtn('close', T('btn_close'));
      head.append(title, el('span', 'fv-panel-note', K.note), msg, save, close);
      var ta = el('textarea'); ta.value = old; ta.spellcheck = false; ta.placeholder = K.ph;
      if (kind === 'md') node.append(head, mdToolbar(ta, function (t) { msg.style.color = '#fcd34d'; msg.textContent = t; }, true), ta); else node.append(head, ta);
      document.body.appendChild(node);
      active = { kind: kind, btn: K.btn, node: node, ta: ta, dirty: function () { return dirty; }, live: function () { K.live(ta.value); } };
      function setDirty(v) { dirty = v; msg.textContent = v ? T('visual_js_unsaved') : ''; msg.style.color = v ? '#fcd34d' : '#86efac'; }
      ta.addEventListener('input', function () { setDirty(ta.value !== old); clearTimeout(timer); timer = setTimeout(function () { K.live(ta.value); }, 200); });
      function doSave() {
        K.save(old, ta.value).then(function (x) {
          if (!x.ok) { msg.style.color = '#fca5a5'; msg.textContent = x.j.error || T('visual_js_save_failed'); return; }
          old = ta.value; setDirty(false); msg.textContent = T('visual_js_saved'); syncMtime(); refreshHistory();
          if (kind === 'md') refreshContent();
        }).catch(function () { msg.style.color = '#fca5a5'; msg.textContent = T('visual_js_save_failed'); });
      }
      save.addEventListener('click', doSave); close.addEventListener('click', function () { closePanel(false); });
      ta.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closePanel(false);
        if (ev.key === 'Enter' && (ev.metaKey || ev.ctrlKey)) { ev.preventDefault(); doSave(); }
        if (ev.key === 'Tab') { ev.preventDefault(); var s = ta.selectionStart; replaceRange(ta, s, ta.selectionEnd, '  ', s + 2, s + 2); }
      });
      ta.focus();
    });
  }
  tgCss.addEventListener('click', function () { if (active && active.kind === 'css') closePanel(false); else openPanel('css'); });
  tgMd.addEventListener('click', function () { if (active && active.kind === 'md') closePanel(false); else openPanel('md'); });
  if (KINDS[sessionStorage.getItem('fv-panel')]) openPanel(sessionStorage.getItem('fv-panel'));

  // ---------------------------------------------------------------- セレクタ検査（調べるだけ）
  var inspecting = false, hl = el('div', 'fv-hl fv-ui'), pop = null;
  document.body.appendChild(hl);
  function closePop() { if (pop) { pop.remove(); pop = null; } }
  function setInspect(v) { inspecting = v; tgInspect.classList.toggle('active', v); root.classList.toggle('fv-inspect', v); hl.style.display = 'none'; if (!v) closePop(); }
  tgInspect.addEventListener('click', function () { setInspect(!inspecting); });
  function cls(e) { return [].slice.call(e.classList).filter(function (c) { return !/^fv-|^mikan-content-scope$/.test(c); }); }
  function simple(e) { return e.tagName.toLowerCase() + cls(e).map(function (c) { return '.' + CSS.escape(c); }).join(''); }
  function count(rootEl, sel) { try { return rootEl.querySelectorAll(sel).length; } catch (e) { return 0; } }
  function selectorsFor(target) {
    var scope = scopeEl(), inScope = !!(scope && scope.contains(target) && target !== scope);
    var rootEl = inScope ? scope : document.body, out = [], seen = {};
    function add(sel) { if (!sel || seen[sel]) return; seen[sel] = 1; out.push({ sel: sel, n: count(rootEl, sel) }); }
    var c = cls(target);
    if (c.length) add('.' + CSS.escape(c[0]));
    add(simple(target));
    var parts = [simple(target)], cur = target.parentElement;
    for (var i = 0; i < 5 && cur && cur !== rootEl && cur !== document.body; i++) {
      parts.unshift(simple(cur)); var sel = parts.join(' ');
      add(sel); if (count(rootEl, sel) === 1) break; cur = cur.parentElement;
    }
    return { list: out, inScope: inScope };
  }
  function showPop(target, x, y) {
    closePop();
    var r = selectorsFor(target);
    pop = el('div', 'fv-pop fv-ui');
    pop.appendChild(el('div', 'fv-pop-title', T('visual_js_selectors_of', target.tagName.toLowerCase())));
    r.list.forEach(function (it) {
      var row = el('div', 'fv-pop-row');
      var cp = ibtn('content_copy', T('btn_copy'), '');
      cp.addEventListener('click', function () { if (navigator.clipboard) navigator.clipboard.writeText(it.sel).then(function () { toast(T('visual_js_copied')); }); });
      row.append(el('code', '', it.sel), el('small', '', T('visual_js_n_matches', it.n)), cp); pop.appendChild(row);
    });
    if (!r.inScope) pop.appendChild(el('div', 'fv-pop-note', T('visual_js_outside_note')));
    document.body.appendChild(pop);
    var w = pop.offsetWidth, h = pop.offsetHeight;
    pop.style.left = Math.max(8, Math.min(x, innerWidth - w - 8)) + 'px';
    pop.style.top = Math.max(8, Math.min(y + 12, innerHeight - h - 8)) + 'px';
  }
  document.addEventListener('mouseover', function (e) {
    if (!inspecting || e.target.closest('.fv-ui')) { if (inspecting) hl.style.display = 'none'; return; }
    var b = e.target.getBoundingClientRect();
    hl.style.cssText = 'display:block;left:' + b.left + 'px;top:' + b.top + 'px;width:' + b.width + 'px;height:' + b.height + 'px';
  }, true);
  document.addEventListener('click', function (e) {
    if (!inspecting || e.target.closest('.fv-ui')) return;
    e.preventDefault(); e.stopPropagation(); showPop(e.target, e.clientX, e.clientY);
  }, true);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && inspecting) { if (pop) closePop(); else setInspect(false); } });

  // ---------------------------------------------------------------- 本文ブロックのクリック編集
  // リンク: 編集中は、本文の中のリンクを押しても移動せず、その部分の編集を開く（⌘/Ctrl+クリックで、リンク先を開く）
  document.addEventListener('click', function (e) {
    if (!on || inspecting) return;
    var link = e.target.closest('a'); if (!link || link.closest('.fv-ui')) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    var blk = link.closest('[data-ln]'); if (!blk || blk.classList.contains('fv-editing')) return;
    e.preventDefault(); e.stopPropagation(); startEdit(blk, link);
  }, true);
  document.addEventListener('click', function (e) {
    if (!on || inspecting) return;
    if (e.target.closest('a,button,input,select,textarea,label,.fv-ui')) return;
    if (String(window.getSelection()) !== '') return;
    var node = e.target.closest('[data-ln]'); if (!node || node.classList.contains('fv-editing')) return;
    e.preventDefault(); startEdit(node);
  });
  function startEdit(node, fromLink) {
    var m = /^(\d+)-(\d+)$/.exec(node.getAttribute('data-ln')); if (!m) return;
    var a = +m[1], b = +m[2];
    fetch(API('source') + '&id=' + encodeURIComponent(PAGE), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (src) {
      var old = src.text.split('\n').slice(a, b + 1).join('\n');
      var box = el('div', 'fv-box fv-ui');
      var ta = el('textarea'); ta.value = old; ta.spellcheck = false;
      var msg = el('div', 'fv-msg');
      var hint = /\{\{[^}]+\}\}/.test(old) ? el('div', 'fv-hint', T('visual_js_tag_hint')) : null;
      if (hint && fromLink) {   // クリックしたカードのリンク先ページへの案内
        var lid = linkPageId(fromLink);
        if (lid) {
          var pg = PAGES.filter(function (p) { return p.id === lid; })[0], nm = pg && pg.title ? pg.title : lid;
          var acts = el('div', 'fv-hint-acts');
          var a1 = el('a', '', T('visual_js_open_visual', nm)); a1.href = ADMIN.view + lid.split('/').map(encodeURIComponent).join('/');
          var a2 = el('a', '', T('visual_js_open_settings')); a2.href = ADMIN.editBase + encodeURIComponent(lid) + '#page-editor';
          acts.append(a1, a2); hint.append(acts);
        }
      }
      var bar = el('div', 'fv-bar');
      var save = ibtn('save', T('visual_js_save_title'), 'fv-save'), cancel = ibtn('close', T('visual_js_cancel_esc'));
      var up = ibtn('add_row_above', T('visual_js_add_above')), down = ibtn('add_row_below', T('visual_js_add_below'));
      var dup = ibtn('content_copy', T('visual_js_dup_block')), del = ibtn('delete', T('visual_js_delete_block'), 'fv-del');
      bar.append(save, cancel, up, down, dup, del);
      box.append(mdToolbar(ta, function (t) { msg.textContent = t; }, false), ta, bar); if (hint) box.append(hint); box.append(msg);
      node.classList.add('fv-editing'); node.style.display = 'none'; node.parentNode.insertBefore(box, node);
      function fit() { ta.style.height = 'auto'; ta.style.height = (ta.scrollHeight + 4) + 'px'; }
      ta.addEventListener('input', fit); fit(); ta.focus();
      function close() { box.remove(); node.classList.remove('fv-editing'); node.style.display = ''; }
      function send(mode, text) {
        post(API('save'), { id: PAGE, start: a, end: b, old: old, mode: mode, new: text }).then(function (x) {
          if (!x.ok) { msg.textContent = x.j.error || T('visual_js_failed'); return; }
          close(); refreshContent();
        }).catch(function () { msg.textContent = T('visual_js_failed'); });
      }
      var delTimer = null;
      save.addEventListener('click', function () { send('replace', ta.value); });
      cancel.addEventListener('click', close);
      up.addEventListener('click', function () { send('insert-before', T('visual_js_new_paragraph')); });
      down.addEventListener('click', function () { send('insert-after', T('visual_js_new_paragraph')); });
      dup.addEventListener('click', function () { send('insert-after', ta.value); });
      del.addEventListener('click', function () {
        if (del.dataset.armed === '1') { clearTimeout(delTimer); send('delete', ''); return; }
        del.dataset.armed = '1'; del.innerHTML = ic('delete') + '<span class="fv-lb">' + H(T('visual_js_delete_really')) + '</span>'; del.classList.add('armed'); msg.textContent = T('visual_js_delete_armed');
        delTimer = setTimeout(function () { del.dataset.armed = '0'; del.innerHTML = ic('delete'); del.classList.remove('armed'); msg.textContent = ''; }, 3500);
      });
      ta.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') close();
        if (ev.key === 'Enter' && (ev.metaKey || ev.ctrlKey)) { ev.preventDefault(); send('replace', ta.value); }
      });
    });
  }
})();
