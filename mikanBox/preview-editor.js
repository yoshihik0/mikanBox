(() => {
  'use strict';
  // Editor preview: post the current (unsaved) editor state and show the result in a
  // panel over the editor. HTML runs in a sandboxed srcdoc iframe without
  // allow-same-origin, so it cannot reach the admin page; Markdown versions and data
  // pages are shown as the plain text they publish. Opening a new tab is avoided:
  // some browsers turn a form POST into a new tab into a plain GET.
  let overlay = null;

  function close() {
    if (!overlay) return;
    overlay.remove();
    overlay = null;
    document.removeEventListener('keydown', onKey);
  }
  function onKey(event) {
    if (event.key === 'Escape') close();
  }

  function open(button, kind, body) {
    close();
    overlay = document.createElement('div');
    overlay.className = 'editor-preview-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', button.dataset.previewTitle || '');
    const box = document.createElement('div');
    box.className = 'editor-preview-box';
    const bar = document.createElement('div');
    bar.className = 'editor-preview-bar';
    const title = document.createElement('span');
    title.className = 'editor-preview-title';
    title.textContent = (button.dataset.previewTitle || '') + (button.dataset.previewUrl ? ' — ' + button.dataset.previewUrl : '');
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'btn btn-gray btn-sm';
    closeButton.textContent = button.dataset.previewClose || '×';
    closeButton.addEventListener('click', close);
    bar.append(title, closeButton);
    let view;
    if (kind === 'html') {
      view = document.createElement('iframe');
      view.className = 'editor-preview-frame';
      view.setAttribute('sandbox', 'allow-scripts allow-forms allow-popups allow-modals');
      view.setAttribute('referrerpolicy', 'no-referrer');
      view.srcdoc = body;
    } else {
      view = document.createElement('pre');
      view.className = 'editor-preview-text';
      view.textContent = body;
    }
    box.append(bar, view);
    overlay.append(box);
    overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
    document.body.appendChild(overlay);
    document.addEventListener('keydown', onKey);
    closeButton.focus();
  }

  document.addEventListener('click', async (event) => {
    const button = event.target.closest?.('[data-editor-preview]');
    if (!button) return;
    const editor = document.getElementById('page-form');
    if (!editor) return;
    event.preventDefault();
    // Send a copy, so neither the editor nor its dirty state is changed.
    const snapshot = new FormData(editor);
    snapshot.set('save_action', 'preview_page');
    const params = new URLSearchParams();
    for (const [name, value] of snapshot) {
      if (typeof value === 'string') params.append(name, value);
    }
    const urlCode = document.getElementById('output-format-url');
    button.dataset.previewUrl = urlCode ? urlCode.textContent : '';
    button.disabled = true;
    try {
      const response = await fetch(button.dataset.editorPreview, {
        method: 'POST',
        body: params,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'mikanbox-preview' },
      });
      const body = await response.text();
      if (!response.ok) throw new Error(body || String(response.status));
      const kind = response.headers.get('X-MikanBox-Preview') === 'text' ? 'text' : 'html';
      open(button, kind, body);
    } catch (error) {
      open(button, 'text', (button.dataset.previewError || '') + '\n' + (error && error.message ? error.message : ''));
    } finally {
      button.disabled = false;
    }
  });
})();
