(() => {
  'use strict';
  // The editor's Preview shows what is being edited, saved or not (2.19.0). The tab is opened
  // right at the click (so pop-up blockers allow it), the editor's fields are sent to the
  // server, and the tab is sent to a plain GET address that renders them in the same sandboxed
  // way as before. Nothing is saved.
  document.addEventListener('click', async (event) => {
    const button = event.target.closest?.('[data-editor-preview]');
    if (!button) return;
    const form = document.getElementById('page-form');
    if (!form) return;
    event.preventDefault();
    const tab = window.open('', '_blank');
    if (tab) { try { tab.opener = null; } catch (_) { /* ignore */ } }
    const data = new FormData(form);
    data.set('save_action', 'preview_stash');
    data.set('ajax_request', '1'); // signed out: JSON 401 with a message, not the login page
    button.disabled = true;
    try {
      const response = await fetch('admin.php', { method: 'POST', body: data, credentials: 'same-origin' });
      let json = null;
      try { json = await response.json(); } catch (_) { /* not JSON (an error page) */ }
      if (!response.ok || !json || !json.token) {
        const error = new Error('preview');
        error.serverMessage = json && typeof json.message === 'string' ? json.message : '';
        throw error;
      }
      const id = (form.querySelector('input[name="id"]')?.value || '').trim();
      const url = 'admin.php?page_preview=' + encodeURIComponent(id || '_') + '&stash=' + encodeURIComponent(json.token);
      if (tab) tab.location.href = url; else window.location.assign(url);
    } catch (error) {
      if (tab) tab.close();
      if (window.MikanUI) window.MikanUI.toast((error && error.serverMessage) || button.dataset.error || '', true);
    } finally {
      button.disabled = false;
    }
  });
})();
