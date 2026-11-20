<?php defined('MIKANBOX') or die(); ?>
    <div class="section-container section-tight">
        <div class="integrity-box" id="integrity-box">
            <form method="post" id="integrity-form" onsubmit="return false;">
                <?= csrfField() ?>
                <input type="hidden" name="save_action" value="integrity_check">
                <button type="button" class="btn btn-gray btn-small" id="integrity-run"><?= t('integrity_btn') ?></button>
                <small class="sub-text sub-text-block"><?= t('integrity_hint') ?></small>
            </form>
            <div id="integrity-output" aria-live="polite"></div>
        </div>
    </div>
    <script>
    (() => {
      const form = document.getElementById('integrity-form'), out = document.getElementById('integrity-output'), run = document.getElementById('integrity-run');
      if (!form || !out || !run) return;
      const label = run.innerHTML, busy = <?= json_encode(t('integrity_checking'), JSON_UNESCAPED_UNICODE) ?>;
      async function go(action) {
        run.disabled = true; run.textContent = busy;
        out.querySelectorAll('button').forEach(b => { b.disabled = true; });
        try {
          const { ok, json } = await window.MikanUI.post(form, null, { save_action: action });
          if (ok && typeof json.html === 'string') out.innerHTML = json.html; else window.MikanUI.toast(json.message || '', true);
        } catch (_) { window.MikanUI.toast(<?= json_encode(t('err_network_error'), JSON_UNESCAPED_UNICODE) ?>, true); }
        finally { run.disabled = false; run.innerHTML = label; out.querySelectorAll('button').forEach(b => { b.disabled = false; }); }
      }
      run.addEventListener('click', () => go('integrity_check'));
      out.addEventListener('click', (event) => { if (event.target instanceof Element && event.target.closest('[data-integrity-accept]')) go('integrity_accept'); });
    })();
    </script>
