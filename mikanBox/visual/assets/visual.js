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
  var DOC = !!window.__VISUAL_DOC;   // Raw HTML の、<html>…<body> を持つ文書のページ（ブロックの目印は入れず、文書の木をたどって選ぶ）
  // 最近開いたページ（このブラウザだけ。サイトごと）
  var RKEY = 'fv-recent:' + (ADMIN.site || '/');
  function loadRecent() { try { return JSON.parse(localStorage.getItem(RKEY) || '[]'); } catch (e) { return []; } }
  function pushRecent(id) { try { var l = loadRecent().filter(function (x) { return x !== id; }); l.unshift(id); localStorage.setItem(RKEY, JSON.stringify(l.slice(0, 20))); } catch (e) {} }
  pushRecent(PAGE);
  /** ページの本文を差し替える。本文の入れ物が <body> のとき（Raw HTML の文書）は、この画面の部品（.fv-ui など）を残す */
  function swapScope(sc, neu) {
    var d = sc.ownerDocument;
    if (sc !== d.body) { sc.innerHTML = neu.innerHTML; return; }
    var keep = [];
    [].slice.call(sc.childNodes).forEach(function (n) {
      var mine = n.nodeType === 1 && (n.classList.contains('fv-ui') || (n.tagName === 'SCRIPT' && n.hasAttribute('nonce')) || (n.tagName === 'LINK' && /visual\.css|fonts\.googleapis/.test(n.getAttribute('href') || '')));
      if (mine) keep.push(n); else sc.removeChild(n);
    });
    var first = keep[0] || null;
    [].slice.call(neu.childNodes).forEach(function (n) { sc.insertBefore(d.importNode(n, true), first); });
  }
  /** 目印のコメント <!--fv:a-b--> … <!--/fv--> の間にある要素に、data-ln="a-b" を付ける */
  function applyLn(rootEl) {
    var w = rootEl.ownerDocument.createTreeWalker(rootEl, NodeFilter.SHOW_COMMENT), list = [], n;
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
      function out() { a.target = '_blank'; a.rel = 'noopener'; a.setAttribute('data-fv-out', '1'); }   // 編集画面ではないところへ行くリンクは、別タブ（編集画面から出ない）
      if (u.origin !== location.origin) { out(); return; }
      if (ADMIN.pick && u.pathname.indexOf(ADMIN.pick) === 0) return;
      var base = ADMIN.site || '/', path = decodeURIComponent(u.pathname);
      if (u.pathname === location.pathname && u.hash) return;   // 同じページの中（#…）
      if (path.indexOf(base) !== 0) { out(); return; }
      path = path.slice(base.length).replace(/^\/+/, '').replace(/\.html$/, '').replace(/\/index$/, '').replace(/\/+$/, '');
      var id = (path === '' || path === 'index.php') ? (u.searchParams.get('page') || 'index') : path;
      if (ids[id]) a.setAttribute('href', ADMIN.view + id.split('/').map(encodeURIComponent).join('/') + u.hash); else out();
    });
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
  var tgInspect = ibtn('search', T('visual_js_inspect_title'), 'fv-toggle', T('visual_js_inspect_label'));
  var tgMd = ibtn('edit_note', T('visual_js_md_title'), 'fv-toggle', T('visual_js_md_label'));
  var tgCss = ibtn('palette', T('visual_js_css_title'), 'fv-toggle', 'CSS');
  dock.append(tgPages, lnkList, lnkPage, sep1, tgUndo, tgRedo, tgPoints, sep2, tgView, tgInspect, tgMd, tgCss); document.body.appendChild(dock);
  // 使い方は1つだけ: ふだんは閲覧（クリックでリンクをたどる。サイト内のリンクは、そのページのビジュアル編集で開く）。直したいところは、虫眼鏡で選ぶ
  var pageNote = null;
  if (!DOC && scopeEl() && !scopeEl().querySelector('[data-ln]')) {   // 本文がクリックで選べないページ（Raw HTML など）
    pageNote = el('div', 'fv-pagenote fv-ui'); pageNote.innerHTML = ic('info') + '<span>' + H(T('visual_js_page_not_clickable')) + '</span>'; document.body.appendChild(pageNote);
  }
  /** 別タブで開くリンク（編集画面ではないところ）に、触れたときだけ、目印を出す */
  var outChip = null;
  document.addEventListener('mouseover', function (e) {
    var a = e.target.closest && e.target.closest('a[data-fv-out]');
    if (!a || a.closest('.fv-ui')) { if (outChip) { outChip.remove(); outChip = null; } return; }
    if (!outChip) { outChip = el('div', 'fv-outchip fv-ui'); outChip.innerHTML = ic('open_in_new') + '<span>' + H(T('visual_js_out_chip')) + '</span>'; document.body.appendChild(outChip); }
    var r = a.getBoundingClientRect(); outChip.style.left = Math.max(8, Math.min(r.left, innerWidth - 300)) + 'px'; outChip.style.top = Math.max(8, r.top - 34) + 'px';
  });

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
      swapScope(cur, neu); applyLn(cur); rewriteLinks(cur); window.scrollTo(0, y);
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
    var btns = sizes.map(function (s) { var b = ibtn(s[0], s[2] ? T('visual_js_show_at', s[1]) : T('visual_js_vp_off'), 'fv-vp-b', s[1]); b.addEventListener('click', function () { if (s[2]) setWidth(s[2]); else closeViewport(); }); return b; });   // 全幅は、確認を終えること
    var close = ibtn('close', T('btn_close'), 'fv-vp-close'); close.addEventListener('click', closeViewport);
    bar.append.apply(bar, btns.concat([el('span', 'fv-vp-note', T('visual_js_vp_note')), close]));
    var stage = el('div', 'fv-vp-stage');
    vpFrame = el('iframe'); vpFrame.src = location.pathname + (location.search ? location.search + '&' : '?') + 'fv-frame=1';
    stage.appendChild(vpFrame); vp.append(bar, stage); document.body.appendChild(vp);
    function setWidth(w) { vpWidth = w; vpFrame.style.width = w + 'px'; btns.forEach(function (b, i) { b.classList.toggle('on', sizes[i][2] === w); }); }
    setWidth(vpWidth || 375);
    vpFrame.addEventListener('load', function () { bindFrame(); if (active && active.dirty()) active.live(); markTarget(); });
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
      mediaCache = null; insertImage(ta, x.j.name); say('');
    }).catch(function () { say(T('visual_js_upload_failed')); });
  }
  /** 画像を入れる: いつも Markdown の ![](名前) */
  function insertImage(ta, name) {
    var tag = '![](' + name + ')', s0 = ta.selectionStart;
    replaceRange(ta, s0, ta.selectionEnd, tag, s0 + tag.length, s0 + tag.length);
  }
  // 画像ピッカー: media の画像を、新しい順にサムネイルで並べる。選ぶと、カーソルの位置に入る
  var mediaCache = null, mediaPop = null, mediaAnchor = null;
  function closeMedia() { if (mediaPop) { mediaPop.remove(); mediaPop = null; mediaAnchor = null; document.removeEventListener('mousedown', mediaOutside, true); document.removeEventListener('keydown', mediaKey, true); } }
  function mediaOutside(e) { if (mediaPop && !mediaPop.contains(e.target) && !(mediaAnchor && mediaAnchor.contains(e.target))) closeMedia(); }
  function mediaKey(e) { if (e.key === 'Escape' && mediaPop) { e.stopPropagation(); closeMedia(); } }
  function openMedia(anchor, ta, say, pickFile) {
    if (mediaPop && mediaAnchor === anchor) { closeMedia(); return; }
    closeMedia(); mediaAnchor = anchor;
    mediaPop = el('div', 'fv-pop fv-media fv-ui');
    var search = el('input', 'fv-media-q'); search.type = 'search'; search.placeholder = T('visual_js_media_search');
    var up = ibtn('upload', T('visual_js_media_upload'), 'fv-media-up', T('visual_js_media_upload'));
    var head = el('div', 'fv-media-head'); head.append(search, up);
    var grid = el('div', 'fv-media-grid'), note = el('div', 'fv-pop-note fv-media-note');
    mediaPop.append(head, grid, note); document.body.appendChild(mediaPop);
    var r = anchor.getBoundingClientRect(), w = mediaPop.offsetWidth, h = mediaPop.offsetHeight;
    mediaPop.style.left = Math.max(8, Math.min(r.left, innerWidth - w - 8)) + 'px';
    mediaPop.style.top = Math.max(8, r.top - h - 8 > 8 ? r.top - h - 8 : Math.min(r.bottom + 8, innerHeight - h - 8)) + 'px';
    document.addEventListener('mousedown', mediaOutside, true); document.addEventListener('keydown', mediaKey, true);
    up.addEventListener('click', function () { closeMedia(); pickFile(); });
    function paint(list, base) {
      var q = search.value.trim().toLowerCase(); grid.textContent = ''; note.textContent = '';
      var shown = list.filter(function (it) { return !q || it.name.toLowerCase().indexOf(q) >= 0; });
      if (!shown.length) note.textContent = list.length ? T('visual_js_media_no_match') : T('visual_js_media_empty');
      shown.slice(0, 120).forEach(function (it) {
        var b = el('button', 'fv-media-item'); b.type = 'button'; b.title = it.name;
        var im = el('img'); im.loading = 'lazy'; im.alt = ''; im.src = base + encodeURIComponent(it.name);
        b.append(im, el('span', '', it.name));
        b.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
        b.addEventListener('click', function () { closeMedia(); insertImage(ta, it.name); say(''); });
        grid.append(b);
      });
    }
    var cached = mediaCache ? Promise.resolve(mediaCache) : jfetch(API('media'), { cache: 'no-store' }).then(function (x) { if (!x.ok) throw 0; mediaCache = x.j; return x.j; });
    cached.then(function (m) { if (!mediaPop) return; paint(m.items, m.base); search.addEventListener('input', function () { paint(m.items, m.base); }); search.focus(); })
      .catch(function () { if (mediaPop) note.textContent = T('visual_js_media_failed'); });
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
    var imgBtn = b('image', T('visual_js_tb_image'), function () { openMedia(imgBtn, ta, say, function () { file.click(); }); });
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
      live: function (text) { return post(API('render'), { id: PAGE, text: text }).then(function (x) { if (!x.ok) return; var neu = new DOMParser().parseFromString(x.j.html, 'text/html').querySelector('.mikan-content-scope'); if (!neu) return; liveDocs().forEach(function (d) { var sc = d.querySelector('.mikan-content-scope'); if (!sc) return; swapScope(sc, neu); applyLn(sc); if (d === document) rewriteLinks(sc); }); markTarget(); }); }
    }
  };
  function closePanel(force) {
    if (!active) return true;
    if (!force && active.dirty() && !confirm(T('visual_js_confirm_close'))) return false;
    var was = active.dirty();
    if (active.kind === 'md') { tgt = null; tbar = null; clearMarks(); } else cssbar = null;
    active.node.remove(); active.btn.classList.remove('active'); active = null;
    sessionStorage.setItem('fv-panel', ''); root.classList.remove('fv-panel-open');
    if (was) { sessionStorage.setItem('fv-scroll', String(window.scrollY)); location.reload(); }
    return true;
  }
  function openPanel(kind, after) {
    if (active && active.kind === kind) return;
    if (active && !closePanel(false)) return;
    var K = KINDS[kind];
    sessionStorage.setItem('fv-panel', kind); K.btn.classList.add('active'); root.classList.add('fv-panel-open');
    K.load().then(function (old) {
      // 全体のテキストは full に持ち、入力欄には、選んだ部分（view）だけを入れる。入力のたびに、全体へ戻す
      var full = old, view = null, dirty = false, timer = null;
      var node = el('div', 'fv-panel fv-ui');
      var head = el('div', 'fv-panel-head'), msg = el('span', 'fv-msg');
      var title = el('strong'); title.innerHTML = ic(K.icon) + '<span>' + K.title + '</span>';
      var save = ibtn('save', T('visual_js_save_title'), 'fv-save'), close = ibtn('close', T('btn_close'));
      head.append(title, el('span', 'fv-panel-note', K.note), msg, save, close);
      var ta = el('textarea'); ta.value = old; ta.spellcheck = false; ta.placeholder = K.ph;
      if (kind === 'md') { tbar = buildTbar(); node.append(head, mdToolbar(ta, function (t) { msg.style.color = '#fcd34d'; msg.textContent = t; }, true), tbar.el, ta); }
      else { cssbar = buildCssBar(); node.append(head, cssbar.el, ta); }
      document.body.appendChild(node);
      function setDirty(v) { dirty = v; msg.textContent = v ? T('visual_js_unsaved') : ''; msg.style.color = v ? '#fcd34d' : '#86efac'; }
      function changed() { setDirty(full !== old); clearTimeout(timer); timer = setTimeout(function () { K.live(full); }, 200); }
      active = {
        kind: kind, btn: K.btn, node: node, ta: ta, dirty: function () { return dirty; }, live: function () { K.live(full); },
        getFull: function () { return full; },
        setFull: function (f) { full = f; changed(); },                                   // 追加・削除など、選んだ部分の外も含む変更
        showRange: function (s, e) { view = { s: s, e: e }; ta.value = full.slice(s, e); ta.scrollTop = 0; ta.setSelectionRange(0, 0); },
        showAll: function () { view = null; ta.value = full; ta.scrollTop = 0; ta.setSelectionRange(0, 0); },
        view: function () { return view; }
      };
      ta.addEventListener('input', function () {
        var oldEnd = view ? view.e : 0;
        if (view) { full = full.slice(0, view.s) + ta.value + full.slice(view.e); view.e = view.s + ta.value.length; } else full = ta.value;
        if (kind === 'md') onMdInput(); else onCssInput(view ? view.e - oldEnd : 0);
        changed();
      });
      function doSave() {
        K.save(old, full).then(function (x) {
          if (!x.ok) { msg.style.color = '#fca5a5'; msg.textContent = x.j.error || T('visual_js_save_failed'); return; }
          old = full; setDirty(false); msg.textContent = T('visual_js_saved'); syncMtime(); refreshHistory();
          if (kind === 'md') refreshContent().then(markTarget);
        }).catch(function () { msg.style.color = '#fca5a5'; msg.textContent = T('visual_js_save_failed'); });
      }
      save.addEventListener('click', doSave); close.addEventListener('click', function () { closePanel(false); });
      ta.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closePanel(false);
        if (ev.key === 'Enter' && (ev.metaKey || ev.ctrlKey)) { ev.preventDefault(); doSave(); }
        if (ev.key === 'Tab') { ev.preventDefault(); var s = ta.selectionStart; replaceRange(ta, s, ta.selectionEnd, '  ', s + 2, s + 2); }
      });
      ta.setSelectionRange(0, 0); ta.focus({ preventScroll: true });
      if (after) after();
    });
  }
  tgCss.addEventListener('click', function () { if (active && active.kind === 'css') closePanel(false); else openPanel('css'); });
  tgMd.addEventListener('click', function () { if (active && active.kind === 'md') closePanel(false); else openPanel('md'); });
  if (KINDS[sessionStorage.getItem('fv-panel')]) openPanel(sessionStorage.getItem('fv-panel'));

  // ---------------------------------------------------------------- セレクタ検査（調べるだけ）
  var inspecting = false, hl = el('div', 'fv-hl fv-ui'), pop = null;
  document.body.appendChild(hl);
  function closePop() { if (pop) { pop.remove(); pop = null; } }
  function setInspect(v) { inspecting = v; tgInspect.classList.toggle('active', v); root.classList.toggle('fv-inspect', v); hl.style.display = 'none'; if (!v) closePop(); frameInspect(v); }
  tgInspect.addEventListener('click', function () { setInspect(!inspecting); });
  function cls(e) { return [].slice.call(e.classList).filter(function (c) { return !/^fv-|^mikan-content-scope$/.test(c); }); }
  function simple(e) { return e.tagName.toLowerCase() + cls(e).map(function (c) { return '.' + CSS.escape(c); }).join(''); }
  function count(rootEl, sel) { try { return rootEl.querySelectorAll(sel).length; } catch (e) { return 0; } }
  function selectorsFor(target) {
    var doc = target.ownerDocument, scope = doc.querySelector('.mikan-content-scope'), inScope = !!(scope && scope.contains(target) && target !== scope);
    var rootEl = inScope ? scope : doc.body, out = [], seen = {};
    function add(sel, at) { if (!sel || seen[sel]) return; seen[sel] = 1; out.push({ sel: sel, n: count(rootEl, sel), at: at || target }); }   // at: セレクタの先頭の要素（その階層）
    var c = cls(target);
    if (c.length) add('.' + CSS.escape(c[0]));
    add(simple(target));
    var parts = [simple(target)], cur = target.parentElement;
    for (var i = 0; i < 5 && cur && cur !== rootEl && cur !== doc.body; i++) {
      parts.unshift(simple(cur)); var sel = parts.join(' ');
      add(sel, cur); if (count(rootEl, sel) === 1) break; cur = cur.parentElement;
    }
    return { list: out, inScope: inScope };
  }
  /** クリックした要素の、本文ブロックの中での位置（同じタグの何番目か、を外から順に） */
  function editPath(target) {
    if (DOC) {   // 文書全体が1つのまとまり。<html> から、タグと「同じタグの何番目か」で、選んだ要素までをたどる
      if (target.closest('.fv-ui')) return null;
      var rootEl = target.ownerDocument.documentElement, dpath = [], dcur = target;
      while (dcur && dcur !== rootEl && dcur.parentElement) {
        var dtag = dcur.tagName.toLowerCase(), dn = 0;
        for (var dsib = dcur.parentElement.firstElementChild; dsib && dsib !== dcur; dsib = dsib.nextElementSibling) if (dsib.tagName.toLowerCase() === dtag) dn++;
        dpath.unshift({ tag: dtag, nth: dn }); dcur = dcur.parentElement;
      }
      return { blk: rootEl, path: dpath, doc: true };
    }
    var blk = target.closest('[data-ln]'); if (!blk || blk.classList.contains('fv-editing') || blk.closest('.fv-ui')) return null;
    var path = [], cur = target;
    while (cur !== blk && cur.parentElement) {
      var tag = cur.tagName.toLowerCase(), n = 0;
      for (var sib = cur.parentElement.firstElementChild; sib && sib !== cur; sib = sib.nextElementSibling) if (sib.tagName.toLowerCase() === tag) n++;
      path.unshift({ tag: tag, nth: n }); cur = cur.parentElement;
    }
    return { blk: blk, path: path };
  }
  function showPop(target, x, y) {
    closePop();
    var r = selectorsFor(target);
    pop = el('div', 'fv-pop fv-ui');
    pop.appendChild(el('div', 'fv-pop-title', T('visual_js_selectors_of', target.tagName.toLowerCase())));
    var anyEditable = false;
    r.list.forEach(function (it) {
      var row = el('div', 'fv-pop-row'), editable = !!editPath(it.at); anyEditable = anyEditable || editable;
      var path = el('code', editable ? 'fv-pop-path' : '', it.sel);
      var cp = ibtn('content_copy', T('btn_copy'), '');
      cp.addEventListener('click', function () { if (navigator.clipboard) navigator.clipboard.writeText(it.sel).then(function () { toast(T('visual_js_copied')); }); });
      var body = ibtn('edit_note', editable ? T('visual_js_open_body') : T('visual_js_not_editable_title'), ''); body.disabled = !editable;
      var css = ibtn('palette', T('visual_js_open_css'), '');
      if (editable) { path.title = T('visual_js_open_body'); path.addEventListener('click', function () { openEditAt(it.at); }); body.addEventListener('click', function () { openEditAt(it.at); }); }
      css.addEventListener('click', function () { pop.showCssChoices(it); });
      row.append(path, el('small', '', T('visual_js_n_matches', it.n)), body, css, cp); pop.appendChild(row);
    });
    if (!anyEditable) pop.appendChild(el('div', 'fv-pop-note', T('visual_js_not_editable_note')));
    if (!r.inScope) pop.appendChild(el('div', 'fv-pop-note', T('visual_js_outside_note')));
    document.body.appendChild(pop);
    place();
    /** CSSボタン: その行の要素に当たる、または、その要素のクラス名・idを含むルールを、番号つきの選択肢で出す。選んだものを、CSSパネルに切り出す */
    function showCssChoices(it) {
      var P = active && active.kind === 'css' ? active : null;
      (P ? Promise.resolve(P.getFull()) : jfetch(API('css') + '&id=' + encodeURIComponent(PAGE), { cache: 'no-store' }).then(function (x) { return x.ok ? x.j.css : ''; })).then(function (css) {
        var list = cssCandidates(css, it.at);
        pop.textContent = '';
        var head = el('div', 'fv-pop-title'), back = ibtn('arrow_back', T('visual_js_css_back'), 'fv-pop-back');
        back.addEventListener('click', function () { showPop(target, x, y); });
        head.append(back, el('span', '', T('visual_js_css_choices_of', simple(it.at)))); pop.appendChild(head);
        if (!list.length) pop.appendChild(el('div', 'fv-pop-note', T('visual_js_css_not_found')));
        list.forEach(function (c, i) {
          var b = el('button', 'fv-choice'); b.type = 'button';
          var top = el('span', 'fv-choice-top'), sub = el('span', 'fv-choice-sub', [c.ctx, 'L' + c.line, c.preview].filter(Boolean).join(' · '));
          top.append(el('code', '', c.label), el('small', '', c.kind === 'match' ? T('visual_js_css_applies') : T('visual_js_css_mentions')));
          var col = el('span', 'fv-choice-col'); col.append(top, sub);
          b.append(el('b', '', String(i + 1)), col);
          b.addEventListener('click', function () { openCssAt(list, i); });
          pop.appendChild(b);
        });
        place();
      });
    }
    function place() {
      var w = pop.offsetWidth, h = pop.offsetHeight;
      pop.style.left = Math.max(8, Math.min(x, innerWidth - w - 8)) + 'px';
      pop.style.top = Math.max(8, Math.min(y + 12, innerHeight - h - 8)) + 'px';
    }
    pop.showCssChoices = showCssChoices;
  }
  // 画面幅の確認（iframe）の中でも、虫眼鏡で選べるようにする。位置は、iframe の位置を足して、親の画面の座標にする
  function frameBox(e, b) { var f = vpFrame && vpFrame.getBoundingClientRect(); return f ? { left: b.left + f.left, top: b.top + f.top, width: b.width, height: b.height } : b; }
  function bindFrame() {
    var d; try { d = vpFrame.contentDocument; } catch (e) { return; }
    if (!d || !d.body || d.documentElement.hasAttribute('data-fv-bound')) return;
    d.documentElement.setAttribute('data-fv-bound', '1');
    var st = d.createElement('style'); st.textContent = 'html.fv-inspect *{cursor:crosshair!important}.fv-target{outline:2px solid #3b9bd8!important;outline-offset:2px}'; d.head.appendChild(st);
    var sc = d.querySelector('.mikan-content-scope'); if (sc) { applyLn(sc); }
    d.addEventListener('mouseover', function (e) {
      if (!inspecting) return;
      var b = frameBox(e, e.target.getBoundingClientRect());
      hl.style.cssText = 'display:block;left:' + b.left + 'px;top:' + b.top + 'px;width:' + b.width + 'px;height:' + b.height + 'px';
    }, true);
    d.addEventListener('click', function (e) {
      if (!inspecting) return;
      e.preventDefault(); e.stopPropagation(); var f = vpFrame.getBoundingClientRect(); showPop(e.target, f.left + e.clientX, f.top + e.clientY);
    }, true);
    d.documentElement.classList.toggle('fv-inspect', !!inspecting);
  }
  function frameInspect(v) { try { if (vpFrame && vpFrame.contentDocument) vpFrame.contentDocument.documentElement.classList.toggle('fv-inspect', !!v); } catch (e) {} }
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

  // ---------------------------------------------------------------- 本文ブロックの編集（虫眼鏡で選んだところを開く）
  /** テキストの、いちばん外の要素の直下の子要素を、文字の位置（先頭からの相対。e は終わりの次）で返す。1行に書かれたHTMLでも、同じ行の要素どうしでも、分けられる */
  function childRanges(text) {
    var VOID = /^(area|base|br|col|embed|hr|img|input|link|meta|source|track|wbr)$/i;
    var re = /<!--[\s\S]*?-->|<(\/?)([a-zA-Z][\w-]*)\b[^>]*?(\/?)>/g, m, stack = [], top = [], kids = [], lower = null;
    while ((m = re.exec(text))) {
      if (m[0].indexOf('<!--') === 0) continue;
      var name = m[2].toLowerCase(), at = m.index, end = m.index + m[0].length;
      if (!m[1] && m[3] !== '/' && /^(script|style|textarea|title)$/.test(name)) {   // 中身は、タグとして読まない
        lower = lower || text.toLowerCase();
        var closeAt = lower.indexOf('</' + name, re.lastIndex); if (closeAt >= 0) re.lastIndex = closeAt;
      }
      if (m[1]) {
        var o = stack.pop(); if (!o || o.name !== name) return null;
        o.e = end; o.closeAt = at;
        if (!stack.length) top.push(o); else if (stack.length === 1) kids.push(o);
      } else {
        var node = { name: name, s: at, openEnd: end };
        if (m[3] === '/' || VOID.test(name)) { node.e = end; node.closeAt = at; if (!stack.length) top.push(node); else if (stack.length === 1) kids.push(node); }
        else stack.push(node);
      }
    }
    if (stack.length || top.length !== 1) return null;
    var root = top[0], all = kids.map(function (k) { return { s: k.s, e: k.e, name: k.name, usable: true }; });
    return { name: root.name, kids: all, all: all };
  }

  // ---------------------------------------------------------------- 編集対象の選択（下のパネルに、選んだ部分だけを切り出して出す）
  var LOADING_TAG = /\{\{\s*(NAV_LINKS|NAV_CARDS|POST_MD|EXT_MD|SEARCH_RESULTS)\b/;
  var tgt = null;     // { s, e, path:[{tag,nth}], crumbs:[タグ名], trail:[[s,e]], root, doc }  s,e は、本文全体の中の文字の位置（e は終わりの次）
  var tbar = null;    // 本文パネルの、編集対象のバー
  var cssbar = null;  // CSSパネルの、該当ルールのバー
  function mdPanel() { return active && active.kind === 'md' ? active : null; }
  function lineStart(text, n) { var p = 0; for (var i = 0; i < n; i++) { var q = text.indexOf('\n', p); if (q < 0) return text.length; p = q + 1; } return p; }
  function lineEnd(text, n) { var q = text.indexOf('\n', lineStart(text, n)); return q < 0 ? text.length : q; }
  function lineOfPos(text, pos) { var n = 0, p = text.indexOf('\n'); while (p >= 0 && p < pos) { n++; p = text.indexOf('\n', p + 1); } return n; }
  function targetText() { var P = mdPanel(); return P && tgt ? P.getFull().slice(tgt.s, tgt.e) : ''; }

  /** 編集対象の要素を、ページ（と画面幅の確認）の中から探す */
  function findTargetEls() {
    var out = []; if (!tgt) return out;
    var P = mdPanel(), full = P ? P.getFull() : '', la = lineOfPos(full, tgt.s), lb = lineOfPos(full, Math.max(tgt.s, tgt.e - 1));
    liveDocs().forEach(function (d) {
      var cur;
      if (tgt.doc) cur = d.documentElement;
      else {
        var sc = d.querySelector('.mikan-content-scope'); if (!sc) return;
        var blocks = [].slice.call(sc.querySelectorAll('[data-ln]')).filter(function (x) { var m = /^(\d+)-(\d+)$/.exec(x.getAttribute('data-ln')); return m && +m[1] <= la && lb <= +m[2]; });
        cur = blocks[Math.min(tgt.root, blocks.length - 1)];
      }
      for (var i = 0; cur && i < tgt.path.length; i++) {
        var st = tgt.path[i], n = 0, nx = null;
        for (var c = cur.firstElementChild; c; c = c.nextElementSibling) { if (c.tagName.toLowerCase() === st.tag) { if (n === st.nth) { nx = c; break; } n++; } }
        cur = nx;
      }
      if (cur) out.push(cur);
    });
    return out;
  }
  function clearMarks() { liveDocs().forEach(function (d) { [].slice.call(d.querySelectorAll('.fv-target')).forEach(function (x) { x.classList.remove('fv-target'); }); }); }
  function markTarget() { clearMarks(); findTargetEls().forEach(function (x) { x.classList.add('fv-target'); }); }
  /** 編集対象が、下のパネルに隠れないよう、ページ（と画面幅の確認）をスクロールする */
  function revealOnPage() {
    var ph = active && active.node ? active.node.offsetHeight : 0;
    findTargetEls().forEach(function (x) {
      var d = x.ownerDocument, w = d.defaultView, r = x.getBoundingClientRect(), vh = d === document ? innerHeight - ph : w.innerHeight;
      if (r.top < 48 || r.bottom > vh - 12) w.scrollBy({ top: r.top - Math.min(96, vh / 4), behavior: 'instant' });
    });
  }
  /** 入力欄に出す部分を、編集対象に合わせる（選んでいなければ、全体） */
  function applyTarget(reveal) {
    var P = mdPanel();
    if (!P) { clearMarks(); return; }
    if (!tgt) { P.showAll(); clearMarks(); tbar.paint(); return; }
    P.showRange(tgt.s, tgt.e); P.ta.focus({ preventScroll: true }); tbar.paint(); markTarget(); if (reveal) revealOnPage();
  }
  /** 入力欄を直したとき: 編集対象の終わり（と、外側の要素の終わり）を、増減した文字数だけずらす */
  function onMdInput() {
    var P = mdPanel(); if (!P || !tgt || !P.view()) return;
    var ne = P.view().e, d = ne - tgt.e;
    if (d !== 0) { tgt.e = ne; tgt.trail.forEach(function (r) { r[1] += d; }); }
    tbar.paint();
  }
  /** 選んだ要素（ページの中の位置）を、編集対象にする。中の要素なら、文字の範囲を、外から順に掘っていく */
  function setTarget(a, b, path, root, doc) {
    var P = mdPanel(); if (!P) return;
    var full = P.getFull(), s = doc ? 0 : lineStart(full, a), e = doc ? full.length : lineEnd(full, b);
    tgt = { s: s, e: e, path: [], crumbs: [], trail: [], root: root, doc: !!doc };
    for (var i = 0; i < path.length; i++) {
      var info = childRanges(full.slice(tgt.s, tgt.e)); if (!info) break;
      var same = info.all.filter(function (k) { return k.name === path[i].tag; }), k = same[path[i].nth];
      if (!k) break;
      tgt.trail.push([tgt.s, tgt.e]); tgt.crumbs.push(info.name); tgt.path.push(path[i]);
      var s0 = tgt.s; tgt.s = s0 + k.s; tgt.e = s0 + k.e;
    }
    tbar.reset(); applyTarget(true);
  }
  /** 編集対象のバー（本文パネルの上）: 階層・中へ/外へ・上下に追加・複製・削除・追加などを元に戻す・全体を表示 */
  function buildTbar() {
    var bar = el('div', 'fv-tbar'), where = el('span', 'fv-where'), kids = el('div', 'fv-kids'), hint = el('div', 'fv-hint');
    var into = ibtn('subdirectory_arrow_right', T('visual_js_into_title'), ''), outo = ibtn('arrow_back', T('visual_js_out_title'), '');
    var above = ibtn('add_row_above', T('visual_js_add_above'), ''), below = ibtn('add_row_below', T('visual_js_add_below'), '');
    var dup = ibtn('content_copy', T('visual_js_dup_block'), ''), del = ibtn('delete', T('visual_js_delete_block'), 'fv-del');
    var undo = ibtn('undo', T('visual_js_op_undo'), ''), off = ibtn('close', T('visual_js_show_all'), '');
    var row = el('div', 'fv-tbar-row'); row.append(where, into, outo, above, below, dup, del, undo, off);
    hint.textContent = T('visual_js_tag_hint'); bar.append(row, kids, hint);
    var delTimer = null, stack = [];
    function inner() { return tgt && tgt.path.length > 0; }
    function innerTag() { return tgt.path[tgt.path.length - 1].tag; }
    function paint() {
      bar.style.display = tgt ? '' : 'none'; if (!tgt) return;
      var full = mdPanel().getFull(), info = childRanges(targetText()), n = info ? info.kids.length : 0;
      var la = lineOfPos(full, tgt.s) + 1, lb = lineOfPos(full, Math.max(tgt.s, tgt.e - 1)) + 1;
      outo.disabled = !tgt.trail.length; into.disabled = !n; undo.disabled = !stack.length;
      into.innerHTML = ic('subdirectory_arrow_right') + (n ? '<span class="fv-badge">' + n + '</span>' : '');
      where.innerHTML = ic('account_tree') + tgt.crumbs.concat(info ? [info.name] : []).map(function (t) { return '<code>' + H(t) + '</code>'; }).join('<span class="fv-sep">›</span>')
        + '<small>' + la + (lb > la ? '–' + lb : '') + '</small>';
      hint.style.display = LOADING_TAG.test(targetText()) ? '' : 'none';
    }
    function go(k, info) {
      var s0 = tgt.s, nth = info.all.filter(function (x) { return x.name === k.name; }).indexOf(k);
      tgt.trail.push([tgt.s, tgt.e]); tgt.crumbs.push(info.name); tgt.path.push({ tag: k.name, nth: nth }); tgt.s = s0 + k.s; tgt.e = s0 + k.e; kids.textContent = ''; applyTarget(true);
    }
    into.addEventListener('click', function () {
      var info = childRanges(targetText()); if (!info || !info.kids.length) return;
      if (info.kids.length === 1) { go(info.kids[0], info); return; }
      kids.textContent = '';
      var text = targetText();
      info.kids.forEach(function (k, i) {
        var t = text.slice(k.s, k.e).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 24);
        var kb = el('button', 'fv-kid', (i + 1) + ' <' + k.name + '> ' + t); kb.type = 'button'; kb.addEventListener('click', function () { go(k, info); }); kids.append(kb);
      });
    });
    outo.addEventListener('click', function () {
      if (!tgt || !tgt.trail.length) return; var p = tgt.trail.pop(); tgt.crumbs.pop(); tgt.path.pop(); tgt.s = p[0]; tgt.e = p[1]; kids.textContent = ''; applyTarget(true);
    });
    off.addEventListener('click', function () { tgt = null; kids.textContent = ''; applyTarget(false); });
    // 追加・複製・削除は、全体のテキストを直す。入力欄の取り消しは効かないので、専用の「元に戻す」を持つ
    function snap() { stack.push({ full: mdPanel().getFull(), tgt: tgt ? JSON.parse(JSON.stringify(tgt)) : null }); if (stack.length > 30) stack.shift(); }
    undo.addEventListener('click', function () {
      var s = stack.pop(); if (!s || !mdPanel()) return; tgt = s.tgt; mdPanel().setFull(s.full); kids.textContent = ''; applyTarget(true);
    });
    function newPara() { return inner() ? '<' + innerTag() + '>' + T('visual_js_new_paragraph') + '</' + innerTag() + '>' : T('visual_js_new_paragraph'); }
    /** 要素が、行をまるごと使っているか（前後が改行か、端か）。使っていれば改行で区切り、同じ行の中なら、区切らずに並べる */
    function wholeLines(f) {
      var ls = f.lastIndexOf('\n', tgt.s - 1) + 1, le = f.indexOf('\n', tgt.e); if (le < 0) le = f.length;
      return f.slice(ls, tgt.s).trim() === '' && f.slice(tgt.e, le).trim() === '';
    }
    function sepFor(f) { return inner() ? (wholeLines(f) ? '\n' : '') : '\n\n'; }
    /** newFull にして、[s, e) を新しい編集対象にする。外側の要素の終わりは、増えた文字数だけずらす */
    function edit(newFull, s, e, nthDelta) {
      var P = mdPanel(), added = newFull.length - P.getFull().length;
      snap(); P.setFull(newFull);
      tgt.trail.forEach(function (r) { r[1] += added; });
      tgt.s = s; tgt.e = e; if (nthDelta && tgt.path.length) tgt.path[tgt.path.length - 1].nth += nthDelta; if (!inner()) tgt.root = 0;
      applyTarget(true);
    }
    above.addEventListener('click', function () {
      var P = mdPanel(); if (!P || !tgt) return; var f = P.getFull(), t = newPara(), sep = sepFor(f), s = tgt.s;
      edit(f.slice(0, s) + t + sep + f.slice(s), s, s + t.length, 0);
    });
    below.addEventListener('click', function () {
      var P = mdPanel(); if (!P || !tgt) return; var f = P.getFull(), t = newPara(), sep = sepFor(f), e = tgt.e;
      edit(f.slice(0, e) + sep + t + f.slice(e), e + sep.length, e + sep.length + t.length, 1);
    });
    dup.addEventListener('click', function () {
      var P = mdPanel(); if (!P || !tgt) return; var f = P.getFull(), src = targetText(), sep = sepFor(f), e = tgt.e;
      edit(f.slice(0, e) + sep + src + f.slice(e), e + sep.length, e + sep.length + src.length, 1);
    });
    del.addEventListener('click', function () {
      var P = mdPanel(); if (!P || !tgt) return;
      if (del.dataset.armed === '1') {
        clearTimeout(delTimer); del.dataset.armed = '0'; del.classList.remove('armed'); del.innerHTML = ic('delete');
        var f = P.getFull(), s = tgt.s, e = tgt.e;
        if (wholeLines(f)) {   // 行をまるごと使っているときは、行ごと（ページ直下のブロックは、後ろの空行も）消す
          s = f.lastIndexOf('\n', tgt.s - 1) + 1; e = f.indexOf('\n', tgt.e); e = e < 0 ? f.length : e + 1;
          if (!inner() && /^[ \t]*\n/.test(f.slice(e))) e += f.slice(e).indexOf('\n') + 1;
        }
        snap(); P.setFull(f.slice(0, s) + f.slice(e)); tgt = null; kids.textContent = ''; applyTarget(false); return;
      }
      del.dataset.armed = '1'; del.innerHTML = ic('delete') + '<span class="fv-lb">' + H(T('visual_js_delete_really')) + '</span>'; del.classList.add('armed');
      delTimer = setTimeout(function () { del.dataset.armed = '0'; del.innerHTML = ic('delete'); del.classList.remove('armed'); }, 3500);
    });
    bar.style.display = 'none';
    return { el: bar, paint: paint, reset: function () { stack = []; kids.textContent = ''; } };
  }

  // ---------------------------------------------------------------- CSS: 選んだ要素に当たっているルールだけを切り出す
  function escRe(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  /** コメントと文字列の中を、同じ長さの空白にする（位置は変えない） */
  function maskCss(css) {
    return css.replace(/\/\*[\s\S]*?(\*\/|$)|"(?:[^"\\\n]|\\.)*"|'(?:[^'\\\n]|\\.)*'/g, function (x) { return x.replace(/[^\n]/g, ' '); });
  }
  function matchBrace(plain, i) {   // plain[i] は {。対応する } の次の位置
    var depth = 0;
    for (var k = i; k < plain.length; k++) { var c = plain.charAt(k); if (c === '{') depth++; else if (c === '}' && --depth === 0) return k + 1; }
    return plain.length;
  }
  function splitSelectors(head) {
    var out = [], depth = 0, cur = '';
    for (var i = 0; i < head.length; i++) {
      var c = head.charAt(i);
      if (c === '(' || c === '[') depth++; else if (c === ')' || c === ']') depth--;
      if (c === ',' && depth === 0) { out.push(cur.trim()); cur = ''; } else cur += c;
    }
    out.push(cur.trim()); return out.filter(Boolean);
  }
  /** CSSのテキストの、すべてのルール { s, e, sels } を、出てきた順に返す（@media などの中も読む） */
  function cssRuleList(css) {
    var plain = maskCss(css), out = [], i = 0, start = 0, n = plain.length, ctx = [];
    function lineAt(pos) { var c = 1, p = css.indexOf('\n'); while (p >= 0 && p < pos) { c++; p = css.indexOf('\n', p + 1); } return c; }
    while (i < n) {
      while (ctx.length && i >= ctx[ctx.length - 1].end) ctx.pop();
      var ch = plain.charAt(i);
      if (ch === ';' || ch === '}') { start = i + 1; i++; continue; }
      if (ch === '{') {
        var head = plain.slice(start, i).trim(), end = matchBrace(plain, i);
        if (head.charAt(0) === '@') {
          if (/^@(media|supports|layer|container|document|scope|starting-style)\b/i.test(head)) { ctx.push({ head: head.replace(/\s+/g, ' '), end: end }); start = i + 1; i++; continue; }   // 中のルールを、続けて読む
          start = end; i = end; continue;   // @keyframes・@font-face など
        }
        var s = start; while (s < i && /\s/.test(plain.charAt(s))) s++;
        var first = plain.slice(i + 1, end - 1).split(';')[0].replace(/\s+/g, ' ').trim();
        out.push({ s: s, e: end, sels: splitSelectors(head), ctx: ctx.map(function (c) { return c.head; }).join(' '), preview: first.slice(0, 44), line: lineAt(s) });
        start = end; i = end; continue;
      }
      i++;
    }
    return out;
  }
  /** 見た目のきっかけ（:hover など）と疑似要素を外して、要素に当たるかを調べられる形にする */
  function cleanSel(sel) {
    var c = sel.replace(/:global\(([^)]*)\)/g, '$1').replace(/::?(before|after|first-line|first-letter|placeholder|marker|selection|backdrop)\b/gi, '')
      .replace(/:(hover|focus|focus-within|focus-visible|active|visited|target)\b/gi, '').trim();
    return c && !/[>+~]$/.test(c) && c.indexOf('&') < 0 ? c : '';
  }
  function buildCssBar() {
    var bar = el('div', 'fv-tbar'), row = el('div', 'fv-tbar-row'), what = el('span', 'fv-where');
    var prev = ibtn('arrow_upward', T('visual_js_css_prev'), ''), next = ibtn('arrow_downward', T('visual_js_css_next'), ''), off = ibtn('close', T('visual_js_show_all'), '');
    row.append(what, prev, next, off); bar.append(row); bar.style.display = 'none';
    var rules = [], cur = 0;
    function paint() {
      var P = active && active.kind === 'css' ? active : null;
      bar.style.display = P && P.view() ? '' : 'none'; if (!P || !P.view()) return;
      what.innerHTML = ic('palette') + '<code>' + H(rules[cur].label || '') + '</code>' + (rules[cur].ctx ? '<small>' + H(rules[cur].ctx) + '</small>' : '') + '<small>' + (cur + 1) + ' / ' + rules.length + '</small>';
      prev.disabled = cur <= 0; next.disabled = cur >= rules.length - 1;
    }
    function show(i) { var P = active; cur = i; P.showRange(rules[i].s, rules[i].e); P.ta.focus({ preventScroll: true }); paint(); }
    prev.addEventListener('click', function () { if (cur > 0) show(cur - 1); });
    next.addEventListener('click', function () { if (cur < rules.length - 1) show(cur + 1); });
    off.addEventListener('click', function () { rules = []; active.showAll(); paint(); });
    return {
      el: bar, paint: paint,
      set: function (r, first) { rules = r; show(first || 0); },
      /** 入力欄を直したあと: 切り出している範囲より後ろの、ほかのルールを、増減に合わせてずらす */
      shift: function (delta) { if (!delta) return; for (var j = cur + 1; j < rules.length; j++) { rules[j].s += delta; rules[j].e += delta; } rules[cur].e = active.view() ? active.view().e : rules[cur].e; paint(); }
    };
  }
  function onCssInput(delta) { if (cssbar) cssbar.shift(delta); }
  /** 要素に関係するルール（出てきた順）: 要素に当たるもの（match）と、要素のクラス名・idをセレクタに含むもの（mention。たとえば .a-mikan p は、.a-mikan の中の p に当たる） */
  function cssCandidates(css, at) {
    var tokens = [].slice.call(at.classList).filter(function (c) { return !/^fv-/.test(c); }).map(function (c) { return '.' + c; });
    if (at.id) tokens.push('#' + at.id);
    var res = tokens.map(function (t) { return new RegExp('(^|[^\\w-])' + escRe(t) + '(?![\\w-])'); });
    var out = [];
    cssRuleList(css).forEach(function (r) {
      var matched = null, mentioned = null;
      r.sels.forEach(function (sel) {
        var c = cleanSel(sel), ok = false;
        if (c) { try { ok = at.matches(c); } catch (e) { ok = false; } }
        if (ok && !matched) matched = sel;
        if (!mentioned && res.some(function (re) { return re.test(sel); })) mentioned = sel;
      });
      var info = { s: r.s, e: r.e, ctx: r.ctx, preview: r.preview, line: r.line };
      if (matched) out.push(Object.assign({ label: matched, kind: 'match' }, info));
      else if (mentioned) out.push(Object.assign({ label: mentioned, kind: 'mention' }, info));
    });
    // 実際に当たっているものを先に（それぞれ、CSSに出てきた順）
    return out.filter(function (c) { return c.kind === 'match'; }).concat(out.filter(function (c) { return c.kind === 'mention'; }));
  }
  /** 選択肢の中の i 番目を、CSSパネルに切り出す（前後の矢印で、ほかの選択肢にも移れる） */
  function openCssAt(list, i) {
    closePop(); setInspect(false);
    function go() { cssbar.set(list, i); }
    if (active && active.kind === 'css') go(); else openPanel('css', go);
  }

  /** 虫眼鏡で選んだ要素を、本文パネルの編集対象にする（画面幅の確認の中で選んでも、確認は閉じない） */
  function openEditAt(at) {
    var ep = editPath(at); if (!ep) return false;
    var ln = ep.doc ? '' : ep.blk.getAttribute('data-ln'), m = ep.doc ? [0, 0, 0] : /^(\d+)-(\d+)$/.exec(ln); if (!m) return false;
    var root = ep.doc ? 0 : Math.max(0, [].slice.call(ep.blk.ownerDocument.querySelectorAll('[data-ln="' + ln + '"]')).indexOf(ep.blk));
    setInspect(false);
    function go() { setTarget(+m[1], +m[2], ep.path, root, !!ep.doc); }
    if (active && active.kind === 'md') go(); else openPanel('md', go);
    return true;
  }
})();
