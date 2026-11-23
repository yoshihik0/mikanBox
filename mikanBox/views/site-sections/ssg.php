<?php defined('MIKANBOX') or die(); ?>
    <div id="ssg-accordion">
        <div class="section-container section-tight">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('ssg_head') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                <div class="panel-card">
                    <form method="post" id="ssg-form">
                        <input type="hidden" name="save_action" value="ssg_build">
                        <?= csrfField() ?>
                        <?php
                        $ssgMode = $settings['ssg_mode'] ?? 'server';
                        $ssgLinkMode = $settings['ssg_link_mode'] ?? 'relative';
                        $ssgServerStructure = in_array($settings['ssg_server_structure'] ?? '', ['directory', 'file'], true)
                            ? $settings['ssg_server_structure']
                            : 'directory';
                        $ssgExportStructure = in_array($settings['ssg_export_structure'] ?? '', ['directory', 'file'], true)
                            ? $settings['ssg_export_structure']
                            : 'file';
                        $ssgStructure = $ssgMode === 'export' ? $ssgExportStructure : $ssgServerStructure;
                        $ssgDirWasDefaulted = $ssgMode === 'export' && trim((string)$ssgDir) === '';
                        $ssgDirValue = $ssgDirWasDefaulted ? 'export' : $ssgDir;
                        $manualMediaLocation = mikanBoxMediaLocationIsManual();
                        $mediaInOutput = !$manualMediaLocation && is_string($settings['media_output']['directory'] ?? null);
                        ?>
                        <input type="hidden" name="ssg_server_structure" value="<?= htmlspecialchars($ssgServerStructure, ENT_QUOTES) ?>">
                        <input type="hidden" name="ssg_export_structure" value="<?= htmlspecialchars($ssgExportStructure, ENT_QUOTES) ?>">
                        <div class="form-group">
                            <label style="display:block; margin:6px 0;">
                                <input type="radio" name="ssg_mode" value="server" <?= $ssgMode === 'server' ? 'checked' : '' ?>>
                                <?= t('label_ssg_mode_server') ?>
                                <small class="sub-text ssg-mode-hint"><?= t('hint_ssg_mode_server') ?></small>
                            </label>
                            <label style="display:block; margin:6px 0;">
                                <input type="radio" name="ssg_mode" value="export" <?= $ssgMode === 'export' ? 'checked' : '' ?>>
                                <?= t('label_ssg_mode_export') ?>
                                <small class="sub-text ssg-mode-hint"><?= t('hint_ssg_mode_export') ?></small>
                            </label>
                        </div>
                        <div class="flex-row items-end gap-20 flex-wrap">
                            <div class="form-group mb-0">
                                <label class="sub-label"><?= t('label_ssg_output_dir') ?></label>
                                <input type="text" name="ssg_dir" value="<?= htmlspecialchars($ssgDirValue) ?>" class="input-compact" placeholder="<?= htmlspecialchars(t('placeholder_ssg_site_root')) ?>" data-auto-default="<?= $ssgDirWasDefaulted ? '1' : '0' ?>">
                            </div>
                            <div class="form-group mb-0">
                                <label class="sub-label"><?= t('label_ssg_structure') ?></label>
                                <select name="ssg_structure" class="select-auto">
                                    <option value="directory" <?= $ssgStructure === 'directory' ? 'selected' : '' ?>><?= t('label_ssg_dir_based') ?></option>
                                    <option value="file" <?= $ssgStructure === 'file' ? 'selected' : '' ?>><?= t('label_ssg_file_based') ?></option>
                                </select>
                            </div>
                        </div>
                        <div id="ssg-export-options" style="<?= $ssgMode === 'export' ? '' : 'display:none;' ?> margin-top:15px;">
                            <div class="flex-row items-start gap-20 flex-wrap">
                                <div class="form-group mb-0">
                                    <label class="sub-label"><?= t('label_ssg_link_mode') ?></label>
                                    <select name="ssg_link_mode" class="select-auto">
                                        <option value="relative" <?= $ssgLinkMode === 'relative' ? 'selected' : '' ?>><?= t('label_ssg_link_relative') ?></option>
                                        <option value="absolute" <?= $ssgLinkMode === 'absolute' ? 'selected' : '' ?>><?= t('label_ssg_link_absolute') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:15px;">
                            <label class="sub-label" for="ssg-root-url"><?= t('label_ssg_root_url') ?></label>
                            <input type="url" id="ssg-root-url" name="ssg_root_url" value="<?= htmlspecialchars($settings['ssg_root_url'] ?? '', ENT_QUOTES) ?>" class="input-compact" placeholder="https://example.com/site">
                            <small class="sub-text" id="ssg-root-url-hint-fixed" style="<?= $ssgMode === 'export' && $ssgLinkMode === 'absolute' ? '' : 'display:none;' ?>"><?= t('hint_ssg_root_url_fixed') ?></small>
                            <small class="sub-text" id="ssg-root-url-hint-feed" style="<?= $ssgMode === 'export' && $ssgLinkMode !== 'absolute' ? '' : 'display:none;' ?>"><?= t('hint_ssg_root_url_feed') ?></small>
                            <small class="sub-text" id="ssg-root-url-hint-server" style="<?= $ssgMode === 'server' ? '' : 'display:none;' ?>"><?= t('hint_ssg_root_url_server') ?></small>
                        </div>
                        <div id="ssg-media-options" class="form-group" <?= $ssgMode === 'server' ? '' : 'hidden' ?>>
                            <?php if ($manualMediaLocation): ?>
                                <small class="sub-text"><?= t('hint_ssg_media_manual') ?></small>
                            <?php else: ?>
                                <input type="hidden" name="ssg_media_choice" value="1">
                                <label>
                                    <input type="checkbox" name="ssg_media_in_output" value="1" <?= $mediaInOutput ? 'checked' : '' ?>>
                                    <?= t('label_ssg_media_in_output') ?>
                                </label>
                                <small class="sub-text"><?= t('hint_ssg_media_in_output') ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="flex-row gap-10" style="margin-top:15px;">
                            <button type="submit" name="save_action" value="ssg_build" class="btn btn-blue"><?= getIcon('sparkles') ?> <?= t('btn_ssg_save_build') ?></button>
                        </div>
                    </form>
                    <script>
                    (() => {
                        const form = document.getElementById('ssg-form');
                        if (!form) return;
                        const exportOptions = document.getElementById('ssg-export-options');
                        const rootUrlInput = form.querySelector('input[name="ssg_root_url"]');
                        const rootUrlHintFixed = document.getElementById('ssg-root-url-hint-fixed');
                        const rootUrlHintFeed = document.getElementById('ssg-root-url-hint-feed');
                        const rootUrlHintServer = document.getElementById('ssg-root-url-hint-server');
                        const mediaOptions = document.getElementById('ssg-media-options');
                        const mediaChoice = form.querySelector('input[name="ssg_media_in_output"]');
                        const outputDirInput = form.querySelector('input[name="ssg_dir"]');
                        const structureSelect = form.querySelector('select[name="ssg_structure"]');
                        const serverStructureInput = form.querySelector('input[name="ssg_server_structure"]');
                        const exportStructureInput = form.querySelector('input[name="ssg_export_structure"]');
                        let currentMode = form.querySelector('input[name="ssg_mode"]:checked')?.value || 'server';
                        const rememberedOutput = {};
                        <?php if ($ssgMode === 'export' && $mediaInOutput): ?>
                        rememberedOutput.server = <?= json_encode([
                            'directory' => $settings['media_output']['directory'],
                            'url' => str_ends_with($settings['media_output']['url'], '/media/')
                                ? substr($settings['media_output']['url'], 0, -7) : '',
                        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                        <?php endif; ?>
                        let outputDirWasAutoDefaulted = outputDirInput.dataset.autoDefault === '1';
                        const refresh = () => {
                            const mode = form.querySelector('input[name="ssg_mode"]:checked')?.value || 'server';
                            const linkMode = form.querySelector('select[name="ssg_link_mode"]')?.value || 'relative';
                            // The root URL is required only for fixed-URL links, but it is still
                            // offered for relative links: sitemap.xml and rss.xml need absolute URLs.
                            const requiresRootUrl = mode === 'export' && linkMode === 'absolute';
                            const server = mode === 'server';
                            if (mode === 'export' && outputDirInput.value.trim() === '') {
                                outputDirInput.value = 'export';
                                outputDirWasAutoDefaulted = true;
                            } else if (mode === 'server' && outputDirWasAutoDefaulted && outputDirInput.value.trim() === 'export') {
                                outputDirInput.value = '';
                            }
                            outputDirInput.placeholder = mode === 'server'
                                ? <?= json_encode(t('placeholder_ssg_site_root'), JSON_UNESCAPED_UNICODE) ?>
                                : 'export';
                            exportOptions.style.display = mode === 'export' ? '' : 'none';
                            mediaOptions.hidden = !server;
                            mediaOptions.querySelectorAll('input').forEach(input => { input.disabled = !server; });
                            rootUrlInput.required = requiresRootUrl || (server && !!mediaChoice?.checked && outputDirInput.value.trim() !== '');
                            rootUrlHintFixed.style.display = requiresRootUrl ? '' : 'none';
                            rootUrlHintFeed.style.display = !server && !requiresRootUrl ? '' : 'none';
                            rootUrlHintServer.style.display = server ? '' : 'none';
                        };
                        form.querySelectorAll('input[name="ssg_mode"]').forEach(el => {
                            el.addEventListener('change', () => {
                                const previousStructureInput = currentMode === 'export' ? exportStructureInput : serverStructureInput;
                                previousStructureInput.value = structureSelect.value;
                                rememberedOutput[currentMode] = { directory: outputDirInput.value, url: rootUrlInput.value };
                                currentMode = el.value;
                                outputDirInput.value = rememberedOutput[currentMode]?.directory ?? '';
                                rootUrlInput.value = rememberedOutput[currentMode]?.url ?? rootUrlInput.value;
                                outputDirWasAutoDefaulted = false;
                                const nextStructureInput = currentMode === 'export' ? exportStructureInput : serverStructureInput;
                                structureSelect.value = nextStructureInput.value;
                                refresh();
                            });
                        });
                        form.querySelector('select[name="ssg_link_mode"]')?.addEventListener('change', refresh);
                        mediaChoice?.addEventListener('change', refresh);
                        structureSelect.addEventListener('change', () => {
                            const activeStructureInput = currentMode === 'export' ? exportStructureInput : serverStructureInput;
                            activeStructureInput.value = structureSelect.value;
                        });
                        outputDirInput.addEventListener('input', () => {
                            outputDirWasAutoDefaulted = false;
                            refresh();
                        });
                        // Build in place. A full reload would throw away the panel the user is
                        // looking at, and until now it also swallowed the result message.
                        form.addEventListener('submit', async (e) => {
                            if (typeof window.showToast !== 'function') return;
                            e.preventDefault();
                            const btn = form.querySelector('button[value="ssg_build"]');
                            const origHtml = btn ? btn.innerHTML : '';
                            if (btn) {
                                btn.disabled = true;
                                btn.textContent = <?= json_encode(t('msg_ssg_building'), JSON_UNESCAPED_UNICODE) ?>;
                            }
                            const formData = new FormData(form);
                            formData.append('save_action', 'ssg_build');
                            formData.append('ajax_request', '1');
                            try {
                                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                                const json = await res.json().catch(() => ({}));
                                window.showToast(json.message || '', !json.success);
                            } catch (err) {
                                window.showToast(String(err), true);
                            } finally {
                                if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
                            }
                        });
                        refresh();
                    })();
                    </script>
                </div>
                </div>
            </details>
        </div>
    </div>
