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
    button.disabled = true;
    try {
      const response = await fetch('admin.php', { method: 'POST', body: data, credentials: 'same-origin' });
      const json = await response.json();
      if (!response.ok || !json.token) throw new Error('preview');
      const id = (form.querySelector('input[name="id"]')?.value || '').trim();
      const url = 'admin.php?page_preview=' + encodeURIComponent(id || '_') + '&stash=' + encodeURIComponent(json.token);
      if (tab) tab.location.href = url; else window.location.assign(url);
    } catch (error) {
      if (tab) tab.close();
      if (window.MikanUI) window.MikanUI.toast(button.dataset.error || '', true);
    } finally {
      button.disabled = false;
    }
  });
})();
