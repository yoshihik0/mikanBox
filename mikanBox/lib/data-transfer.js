// Data tool "Read": the same in all three editions (canonical in shared/; shared/sync.sh copies it to the
// PHP editions' lib/ and to Cloud's public/mikanbox/). The browser opens the chosen files — ZIPs saved by
// "Save" (any edition, or an older "Download data"/"Download media"), ZIPs of media only, WordPress XML,
// or media files — and sends the data in parts of about 1 MB, then each media file, then "finish".
// The server side is lib/data-transfer.php (PHP) or src/data-transfer.ts (Cloud).
//
// <form data-transfer="import" data-messages="{...}"> with <input type="file" multiple> and radio "mode"
// (add | overwrite | replace). Texts come from the server (lang/*.json, keys transfer_*).
// Cloud sets window.MikanTransferPublish(source) to put the media on the public site afterwards.
(() => {
  'use strict';
  const MEDIA = /\.(?:jpe?g|png|gif|webp|svg|mp3|m4a|mp4|js|css)$/i;
  const PART_BYTES = 1000000;
  const decoder = new TextDecoder();
  const endpoint = () => window.MIKANBOX_ADMIN_ENDPOINT || 'admin.php';

  function words(form) {
    let table = {};
    try { table = JSON.parse(form.dataset.messages || '{}'); } catch {}
    return (key, values = {}) => String(table[key] ?? key).replace(/\{(\w+)\}/g, (_, name) => values[name] ?? '');
  }

  async function send(form, action, values, file) {
    const data = new FormData();
    data.set('save_action', action); data.set('ajax_request', '1');
    data.set('csrf_token', form.querySelector('[name="csrf_token"]')?.value || window.CSRF_TOKEN || '');
    for (const [key, value] of Object.entries(values)) data.set(key, typeof value === 'string' ? value : JSON.stringify(value));
    if (file) data.set('file', file, values.name || 'file');
    const response = await fetch(endpoint(), { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
    let result = null;
    try { result = await response.json(); } catch {}
    if (!response.ok || !result || !result.success) throw new Error(result?.message || result?.error || ('HTTP ' + response.status));
    return result;
  }

  // WordPress export (WXR) → pages, as the former WordPress import did.
  function htmlToMarkdown(html) {
    const root = new DOMParser().parseFromString(html, 'text/html').body;
    const convert = element => Array.from(element.childNodes).map(node => {
      if (node.nodeType === Node.TEXT_NODE) return node.nodeValue || '';
      if (node.nodeType !== Node.ELEMENT_NODE) return '';
      const tag = node.tagName.toLowerCase(), content = convert(node);
      if (/^h[1-6]$/.test(tag)) return `\n${'#'.repeat(Number(tag[1]))} ${content}\n`;
      if (tag === 'strong' || tag === 'b') return `**${content}**`;
      if (tag === 'em' || tag === 'i') return `*${content}*`;
      if (tag === 'a') return `[${content}](${node.getAttribute('href') || ''})`;
      if (tag === 'img') return `![${node.getAttribute('alt') || ''}](${node.getAttribute('src') || ''})`;
      if (tag === 'li') return `* ${content}\n`;
      if (tag === 'blockquote') return `\n> ${content}\n`;
      if (tag === 'br') return '\n';
      if (tag === 'p' || tag === 'div') return `\n${content}\n`;
      if (tag === 'script' || tag === 'style') return '';
      return content;
    }).join('');
    return convert(root).replace(/\n{3,}/g, '\n\n').trim();
  }
  function wordpressPages(text) {
    const doc = new DOMParser().parseFromString(text, 'application/xml');
    if (doc.querySelector('parsererror')) return null;
    const field = (node, name) => node.getElementsByTagNameNS('*', name)[0]?.textContent || '';
    const pages = [];
    Array.from(doc.getElementsByTagName('item')).forEach((item, index) => {
      const type = field(item, 'post_type');
      if (type !== 'post' && type !== 'page') return;
      const slug = field(item, 'post_name').replace(/[^A-Za-z0-9_-]/g, '_').replace(/^_+|_+$/g, '');
      const id = slug || 'post_' + (field(item, 'post_id') || index + 1).replace(/[^A-Za-z0-9_-]/g, '_');
      const status = { publish: 'public_static', pending: 'pending' }[field(item, 'status')] || 'draft';
      const date = field(item, 'post_date');
      const category = Array.from(item.getElementsByTagName('category')).find(node => node.getAttribute('domain') === 'category')?.textContent || '';
      pages.push({ id, data: { title: item.getElementsByTagName('title')[0]?.textContent || '', content_md: htmlToMarkdown(field(item, 'encoded')), category, status, sort_order: 0, updated_at: !date || date.startsWith('0000') ? new Date().toISOString() : date } });
    });
    return pages;
  }

  // Everything the chosen files hold.
  async function collect(files, say) {
    const found = { settings: null, pages: new Map(), components: new Map(), revisions: new Map(), media: new Map(), ignored: 0, hasData: false };
    const json = bytes => { try { return JSON.parse(decoder.decode(bytes)); } catch { return null; } };
    for (const file of files) {
      const lower = file.name.toLowerCase();
      if (lower.endsWith('.zip')) {
        let zip;
        try { zip = await window.MikanZip.open(file); } catch { throw new Error(say('transfer_not_zip', { name: file.name })); }
        const names = zip.names().filter(name => !name.split('/').some(part => part === '__MACOSX' || part.startsWith('._')));
        // The data folder: where posts/, components/ or settings.json are (data/ in saved files).
        let root = null;
        for (const name of names) {
          const match = name.match(/^(.*?)(?:posts\/|components\/|revisions\/|settings\.json$)/);
          if (match && name.endsWith('.json') && (root === null || match[1].length < root.length)) root = match[1];
        }
        for (const name of names) {
          const inData = root !== null && name.startsWith(root) && /^(?:posts|components|revisions)\/.+\.json$|^settings\.json$/.test(name.slice(root.length));
          if (inData) {
            const rel = name.slice(root.length), value = json(await zip.read(name));
            if (value === null || typeof value !== 'object') throw new Error(say('transfer_bad_json', { name }));
            found.hasData = true;
            if (rel === 'settings.json') found.settings = value;
            else if (rel.startsWith('posts/')) found.pages.set(rel.slice(6, -5), value);
            else if (rel.startsWith('components/')) found.components.set(rel.slice(11, -5), value);
            else if (Array.isArray(value)) found.revisions.set(rel.slice(10, -5), value);
          } else if (MEDIA.test(name)) {
            const base = name.split('/').pop();
            found.media.set(base, async () => new Blob([await zip.read(name)]));
          } else found.ignored++;
        }
      } else if (lower.endsWith('.xml')) {
        const pages = wordpressPages(await file.text());
        if (!pages || !pages.length) { found.ignored++; continue; }
        found.hasData = true;
        for (const page of pages) found.pages.set(page.id, page.data);
      } else if (MEDIA.test(lower)) {
        found.media.set(file.name, async () => file);
      } else found.ignored++;
    }
    return found;
  }

  // The data in parts of about 1 MB; a page's history travels with the page.
  function parts(found) {
    const list = [], first = found.settings ? { settings: found.settings } : {};
    let part = { ...first, pages: [], components: [], revisions: [] }, size = JSON.stringify(first).length;
    const push = (kind, item) => {
      const bytes = JSON.stringify(item).length;
      if (size + bytes > PART_BYTES && (part.pages.length || part.components.length)) { list.push(part); part = { pages: [], components: [], revisions: [] }; size = 0; }
      part[kind].push(item); size += bytes;
    };
    for (const [id, data] of found.pages) {
      push('pages', { id, data });
      if (found.revisions.has(id)) push('revisions', { id, items: found.revisions.get(id) });
    }
    for (const [id, data] of found.components) push('components', { id, data });
    if (part.pages.length || part.components.length || part.settings) list.push(part);
    return list;
  }

  document.addEventListener('submit', async event => {
    const form = event.target;
    if (form.dataset?.transfer !== 'import') return;
    event.preventDefault(); event.stopImmediatePropagation();
    const say = words(form), status = form.querySelector('[data-transfer-status]'), button = form.querySelector('button[type="submit"]');
    const progress = text => { if (status) status.textContent = text; };
    const files = [...(form.querySelector('input[type="file"]')?.files || [])];
    const mode = form.querySelector('[name="mode"]:checked')?.value || 'add';
    if (!files.length) return;
    if (button) button.disabled = true;
    try {
      progress(say('transfer_reading'));
      const found = await collect(files, say);
      if (!found.hasData && !found.media.size) throw new Error(say('transfer_nothing'));
      if (mode === 'replace' && !await window.MikanUI.confirm(say('transfer_confirm_replace'), say('transfer_confirm_title'), say('transfer_btn_replace'), 'btn-red')) { progress(''); return; }
      const total = { pages: 0, components: 0, media: 0, skipped: 0, refused: [], failed: [] };
      const list = found.hasData ? parts(found) : [];
      for (let i = 0; i < list.length; i++) {
        progress(say('transfer_sending_data', { done: i + 1, total: list.length }));
        const result = await send(form, 'transfer_import_data', { mode, part: list[i] });
        total.pages += result.pages; total.components += result.components; total.skipped += result.skipped; total.refused.push(...(result.refused || []));
      }
      const media = [...found.media.entries()];
      for (let i = 0; i < media.length; i++) {
        progress(say('transfer_sending_media', { done: i + 1, total: media.length }));
        try {
          const result = await send(form, 'transfer_import_media', { mode, name: media[i][0] }, await media[i][1]());
          if (result.outcome === 'saved') total.media++; else total.skipped++;
        } catch (error) { total.failed.push(media[i][0] + ': ' + error.message); }
      }
      const keep = {};
      // Only the kinds the file holds: a file of pages leaves the components alone, and so on.
      if (found.pages.size) keep.pages = [...found.pages.keys()].filter(id => !total.refused.includes(id));
      if (found.components.size) keep.components = [...found.components.keys()];
      if (found.media.size) keep.media = media.map(([name]) => name);
      const finish = await send(form, 'transfer_import_finish', { mode, keep });
      const deleted = Object.values(finish.deleted || {}).reduce((a, b) => a + b, 0);
      // Cloud: media files live on the public site, so read or deleted media are published at once
      // (a full build, which also brings the pages up to date). Pages alone wait for a build, as in PHP.
      const published = !!window.MikanTransferPublish && (total.media > 0 || (finish.deleted?.media || 0) > 0);
      if (published) {
        progress(say('transfer_publishing'));
        const source = async path => {
          const name = decodeURIComponent(String(path).replace(/^\/media\//, ''));
          return found.media.has(name) ? { bytes: new Uint8Array(await (await found.media.get(name)()).arrayBuffer()) } : null;
        };
        try { await window.MikanTransferPublish(source); } catch (error) { throw new Error(say('transfer_publish_failed') + ' ' + (error.message || '')); }
      }
      progress('');
      const summary = say('transfer_done', { pages: total.pages, components: total.components, media: total.media, skipped: total.skipped, deleted });
      const notes = [];
      if (total.refused.length) notes.push(say('transfer_refused', { ids: total.refused.join(', ') }));
      if (total.failed.length) notes.push(say('transfer_media_failed', { list: total.failed.join(' / ') }));
      if (!published && (total.pages || total.components || deleted)) notes.push(say('transfer_build_hint'));
      window.MikanUI?.toast([summary, ...notes].join(' '), notes.length > 0 && (total.refused.length + total.failed.length) > 0);
      form.reset();
    } catch (error) {
      progress('');
      window.MikanUI?.toast(error.message, true);
    } finally { if (button) button.disabled = false; }
  }, true);
})();
