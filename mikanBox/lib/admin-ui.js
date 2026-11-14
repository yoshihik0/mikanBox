(() => {
  'use strict';
  // Shared admin behaviour (2.19.0): the one toast, the one confirmation, and the one way to
  // run an action without reloading the screen. Strings come from window.__MIKAN_UI (set by
  // admin.php from the language files), never from this file.
  const S = () => window.__MIKAN_UI || {};

  // ---- Toast (bottom right) -----------------------------------------------------
  let toastTimer;
  function toast(message, isError = false, ms = 0) {
    if (!message) return;
    let el = document.getElementById('ajax-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'ajax-toast';
      el.setAttribute('role', 'status');
      el.style.cssText = 'position:fixed; bottom:30px; right:30px; max-width:min(420px, calc(100vw - 60px)); background:rgba(0,0,0,0.8); color:white; padding:12px 24px; border-radius:8px; z-index:10000; transition:opacity 0.3s; opacity:0; pointer-events:none;';
      document.body.appendChild(el);
    }
    el.style.background = isError ? 'rgba(220,53,69,0.9)' : 'rgba(0,0,0,0.8)';
    el.textContent = message;
    el.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.style.opacity = '0'; }, ms || (isError ? 5000 : 3000));
  }

  // ---- Confirmation (in the screen, never the browser's dialog) ---------------------
  function ensureDialog() {
    let dialog = document.getElementById('global-confirm-dialog');
    if (dialog) return dialog;
    dialog = document.createElement('dialog');
    dialog.id = 'global-confirm-dialog';
    dialog.className = 'custom-dialog';
    dialog.innerHTML = '<h3 style="margin-top:0; margin-bottom:12px; font-size:1.05rem; display:flex; align-items:center; gap:8px; color:var(--text);">'
      + '<span class="material-symbols-outlined" style="color:#ef4444;">warning</span> <span id="global-confirm-title"></span></h3>'
      + '<p id="global-confirm-message" style="font-size:0.9rem; line-height:1.5; margin-bottom:20px; color:#475569; white-space:pre-line;"></p>'
      + '<div class="dialog-buttons" style="display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap;">'
      + '<button type="button" id="btn-global-confirm-cancel" class="btn btn-small" style="background:#f1f5f9; color:#1e293b; border:1px solid #cbd5e1;"></button>'
      + '<button type="button" id="btn-global-confirm-ok" class="btn btn-small btn-red"></button></div>';
    document.body.appendChild(dialog);
    return dialog;
  }

  function confirmDialog(message, title = '', okText = '', okClass = 'btn-red') {
    return new Promise((resolve) => {
      const dialog = ensureDialog();
      const cancel = dialog.querySelector('#btn-global-confirm-cancel');
      const ok = dialog.querySelector('#btn-global-confirm-ok');
      dialog.querySelector('#global-confirm-title').textContent = title || S().confirmTitle || '';
      dialog.querySelector('#global-confirm-message').textContent = message;
      cancel.textContent = S().cancel || 'Cancel';
      ok.textContent = okText || S().confirmOk || 'OK';
      ok.className = 'btn btn-small ' + okClass;
      // Answer at once on a click (not when the dialog's own "close" event arrives, which a
      // background tab delays); Escape closes the dialog and counts as Cancel.
      let settled = false;
      const settle = (value) => {
        if (settled) return;
        settled = true;
        cancel.removeEventListener('click', onCancel);
        ok.removeEventListener('click', onOk);
        dialog.removeEventListener('close', onClose);
        resolve(value);
      };
      const finish = (value) => { settle(value); if (dialog.open) dialog.close(); };
      const onCancel = () => finish(false);
      const onOk = () => finish(true);
      const onClose = () => settle(false);
      cancel.addEventListener('click', onCancel);
      ok.addEventListener('click', onOk);
      dialog.addEventListener('close', onClose);
      if (dialog.open) dialog.close();
      dialog.showModal();
      cancel.focus();
    });
  }

  // ---- Is this browser still signed in? -----------------------------------------------
  // The sign-in lapses after a day without activity, and signing in again (here or in another
  // tab) issues a new CSRF token. admin.php?action=session_check answers {logged_in, csrf_token}
  // for this site's own pages only. null means "could not tell" (network, or an edition without
  // the endpoint): callers then carry on as before and never block the user.
  async function sessionState() {
    try {
      const response = await fetch('admin.php?action=session_check', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
      if (!response.ok) return null;
      const json = await response.json();
      return json && typeof json.logged_in === 'boolean' ? json : null;
    } catch (_) { return null; }
  }
  function setCsrfToken(token) {
    if (typeof token !== 'string' || !token) return;
    document.querySelectorAll('input[name="csrf_token"]').forEach((input) => { input.value = token; });
  }
  function expiredMessage() { return S().sessionExpired || S().saveFailed || ''; }
  // true: go ahead (the form now carries the current token). false: signed out; the message
  // has been shown and the form is left exactly as it is.
  async function ensureSession() {
    const state = await sessionState();
    if (state && state.logged_in === false) { toast(expiredMessage(), true, 9000); return false; }
    if (state && state.csrf_token) setCsrfToken(state.csrf_token);
    return true;
  }

  // ---- Running an action without reloading --------------------------------------------
  // POST a form (and the button that sent it) as ajax_request; the server answers JSON
  // {success, message, ...}. The message goes to the toast. Anything else (a login page, an
  // error page, a non-2xx status, JSON without success:true) is a failure, never a silent success.
  async function post(form, submitter, extra = {}) {
    const state = await sessionState();
    if (state && state.logged_in === false) return { ok: false, json: { success: false, message: expiredMessage(), session_expired: true } };
    if (state && state.csrf_token) setCsrfToken(state.csrf_token);
    const data = new FormData(form);
    if (submitter && submitter.name) data.set(submitter.name, submitter.value);
    for (const [key, value] of Object.entries(extra)) data.set(key, value);
    data.set('ajax_request', '1');
    const response = await fetch(window.location.href, { method: 'POST', body: data, credentials: 'same-origin' });
    let json = null;
    try { json = await response.json(); } catch (_) { /* not JSON */ }
    if (!json || typeof json !== 'object') json = {};
    const ok = response.ok && json.success === true;
    if (!ok && (response.status === 401 || json.session_expired)) { json.session_expired = true; json.message = json.message || expiredMessage(); }
    if (!ok && !json.message) json.message = S().saveFailed || '';
    return { ok, json };
  }

  // Replace the listed elements with their fresh versions from the server, keeping which
  // <details> were open and the scroll position.
  async function refresh(selectors) {
    const list = Array.isArray(selectors) ? selectors : String(selectors || '').split(',').map(s => s.trim()).filter(Boolean);
    if (!list.length) return;
    const response = await fetch(window.location.href, { credentials: 'same-origin' });
    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
    const y = window.scrollY;
    for (const selector of list) {
      const fresh = doc.querySelector(selector);
      const old = document.querySelector(selector);
      if (!fresh || !old) continue;
      const open = [...old.querySelectorAll('details')].map(d => d.open);
      old.replaceWith(fresh);
      [...fresh.querySelectorAll('details')].forEach((d, i) => { if (open[i] !== undefined) d.open = open[i]; });
    }
    window.scrollTo({ top: y, behavior: 'instant' });
  }

  // Forms: data-confirm="message" asks first; data-ajax runs it without a reload and
  // data-refresh="#a, #b" updates those parts of the screen; data-busy-text swaps the
  // button text while a full-page action (update, restore) runs.
  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.uiHandled === '1') return;
    // A normal form POST: look at the sign-in first, refresh the form's CSRF token, and only
    // then let the submit go on (this listener is the first on the document, so the other
    // submit handlers, confirmations and in-place saves included, see only the re-sent event).
    // Signed out: nothing is sent, the form keeps what was typed. data-ajax forms check inside post().
    if (form.dataset.sessionChecked === '1') { delete form.dataset.sessionChecked; return; }
    if (!event.defaultPrevented && form.method === 'post' && form.dataset.ajax === undefined
        && form.querySelector('input[name="csrf_token"]') && typeof form.requestSubmit === 'function') {
      event.preventDefault();
      event.stopImmediatePropagation();
      const sender = event.submitter || null;
      if (!await ensureSession()) return;
      form.dataset.sessionChecked = '1';
      try { sender ? form.requestSubmit(sender) : form.requestSubmit(); } finally { delete form.dataset.sessionChecked; }
      return;
    }
    const wantsConfirm = form.dataset.confirm !== undefined || (event.submitter && event.submitter.dataset.confirm !== undefined);
    const ajax = form.dataset.ajax !== undefined;
    if (!wantsConfirm && !ajax) return;
    event.preventDefault();
    const submitter = event.submitter || null;
    if (wantsConfirm) {
      const message = (submitter && submitter.dataset.confirm) || form.dataset.confirm;
      const okText = (submitter && submitter.dataset.confirmOk) || form.dataset.confirmOk || '';
      if (!await confirmDialog(message, form.dataset.confirmTitle || '', okText)) return;
    }
    if (!ajax) {
      const button = form.querySelector('button[type=submit]');
      if (form.dataset.busyText && button) { button.disabled = true; button.textContent = form.dataset.busyText; }
      form.setAttribute('aria-busy', 'true');
      form.dataset.uiHandled = '1';
      if (submitter && submitter.name) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = submitter.name; hidden.value = submitter.value;
        form.appendChild(hidden);
      }
      form.submit();
      return;
    }
    const buttons = [...form.querySelectorAll('button')];
    buttons.forEach(b => { b.disabled = true; });
    try {
      const { ok, json } = await post(form, submitter);
      if (ok && json.redirect) { window.location.assign(json.redirect); return; }
      toast(json.message || (ok ? (S().done || '') : (S().saveFailed || '')), !ok);
      if (ok && form.dataset.refresh) await refresh(form.dataset.refresh);
      if (ok) form.dispatchEvent(new CustomEvent('mikan:done', { bubbles: true, detail: json }));
    } catch (_) {
      toast(S().networkError || '', true);
    } finally {
      buttons.forEach(b => { if (b.isConnected) b.disabled = false; });
    }
  });

  // ---- Opening and closing the editors (one motion for every editor) -----------------------
  // expand(): the editor grows in from nothing; collapse(): it shrinks away. Height and
  // opacity only, border-box throughout, so the page below never jumps when it ends.
  const reduced = () => !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  const EASE = 'cubic-bezier(0.4, 0, 0.2, 1)';
  const FLAT = { paddingTop: '0px', paddingBottom: '0px', marginTop: '0px', marginBottom: '0px' };
  // Animations do not advance in a background tab, so every step also ends on a timer:
  // the editor is never left half open or half closed.
  function run(el, keyframes, ms, fill) {
    return new Promise((resolve) => {
      const animation = el.animate(keyframes, { duration: ms, easing: EASE, fill });
      let done = false;
      const end = () => { if (done) return; done = true; clearTimeout(timer); resolve(animation); };
      const timer = setTimeout(end, ms + 250);
      animation.onfinish = end;
      animation.oncancel = end;
    });
  }
  function expand(el, ms = 320) {
    if (!el || !el.animate || reduced()) return Promise.resolve();
    const h = el.getBoundingClientRect().height;
    const saved = { overflow: el.style.overflow, boxSizing: el.style.boxSizing };
    el.style.overflow = 'hidden'; el.style.boxSizing = 'border-box';
    return run(el, [{ height: '0px', opacity: 0, ...FLAT }, { height: h + 'px', opacity: 1 }], ms, 'none').then((animation) => {
      animation.cancel();   // back to the natural height: the same size the animation ended at
      el.style.overflow = saved.overflow; el.style.boxSizing = saved.boxSizing;
    });
  }
  function collapse(el, ms = 300) {
    if (!el || !el.animate || reduced()) return Promise.resolve();
    const h = el.getBoundingClientRect().height;
    el.style.overflow = 'hidden'; el.style.boxSizing = 'border-box';
    return run(el, [{ height: h + 'px', opacity: 1 }, { height: '0px', opacity: 0, ...FLAT }], ms, 'forwards').then(() => {});
  }

  // fadeOut(): fades in place (opacity only, the height stays), so something visibly goes away.
  function fadeOut(el, ms = 200) {
    if (!el || !el.animate || reduced()) return Promise.resolve();
    return run(el, [{ opacity: 1 }, { opacity: 0 }], ms, 'forwards').then(() => {});
  }
  // scrollToY(): a smooth scroll whose length depends on the distance (300ms to 600ms). Done
  // with timers and frames of our own, so it can be checked and always ends at the exact
  // position, also in a background tab where frames do not run.
  function scrollToY(top, minMs = 300, maxMs = 600) {
    const target = Math.max(0, Math.round(top));
    const from = window.scrollY;
    const distance = Math.abs(target - from);
    if (reduced() || distance < 2) { window.scrollTo({ top: target, behavior: 'instant' }); return Promise.resolve(); }
    const ms = Math.min(maxMs, Math.max(minMs, 250 + distance * 0.08));
    return new Promise((resolve) => {
      const start = performance.now();
      let finished = false;
      const end = () => { if (finished) return; finished = true; clearTimeout(timer); window.scrollTo({ top: target, behavior: 'instant' }); resolve(); };
      const timer = setTimeout(end, ms + 150);
      const step = (now) => {
        if (finished) return;
        const t = Math.min(1, (now - start) / ms);
        const eased = t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
        window.scrollTo({ top: from + (target - from) * eased, behavior: 'instant' });
        if (t < 1) requestAnimationFrame(step); else end();
      };
      requestAnimationFrame(step);
    });
  }

  window.MikanUI = { toast, confirm: confirmDialog, post, ensureSession, refresh, expand, collapse, fadeOut, scrollToY };
  window.showToast = toast;
  window.showConfirmDialog = confirmDialog;
})();
