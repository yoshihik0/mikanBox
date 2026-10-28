(() => {
  'use strict';
  document.addEventListener('click', event => {
    const button = event.target.closest?.('[data-editor-preview]');
    if (!button) return;
    const editor = document.getElementById('page-form');
    if (!editor) return;
    event.preventDefault();
    // Submit a copy, so neither the editor nor its dirty state is changed.
    const snapshot = new FormData(editor);
    snapshot.set('save_action', 'preview_page');
    const form = document.createElement('form');
    form.method = 'post';
    form.action = button.dataset.editorPreview;
    form.target = '_blank';
    form.hidden = true;
    for (const [name, value] of snapshot) {
      if (typeof value !== 'string') continue;
      const field = document.createElement('input');
      field.type = 'hidden'; field.name = name; field.value = value;
      form.appendChild(field);
    }
    document.body.appendChild(form);
    form.submit();
    form.remove();
  });
})();
