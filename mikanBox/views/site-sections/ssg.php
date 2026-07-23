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
                        ?>
                        <input type="hidden" name="ssg_server_structure" value="<?= htmlspecialchars($ssgServerStructure, ENT_QUOTES) ?>">
                        <input type="hidden" name="ssg_export_structure" value="<?= htmlspecialchars($ssgExportStructure, ENT_QUOTES) ?>">
                        <div class="form-group">
                            <label class="sub-label"><?= t('label_ssg_mode') ?></label>
                            <label style="display:block; margin:6px 0;">
                                <input type="radio" name="ssg_mode" value="server" <?= $ssgMode === 'server' ? 'checked' : '' ?>>
                                <?= t('label_ssg_mode_server') ?>
                                <small class="sub-text" style="display:block; margin-left:24px;"><?= t('hint_ssg_mode_server') ?></small>
                            </label>
                            <label style="display:block; margin:6px 0;">
                                <input type="radio" name="ssg_mode" value="export" <?= $ssgMode === 'export' ? 'checked' : '' ?>>
                                <?= t('label_ssg_mode_export') ?>
                                <small class="sub-text" style="display:block; margin-left:24px;"><?= t('hint_ssg_mode_export') ?></small>
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
                            <div class="flex-row items-end gap-20 flex-wrap">
                                <div class="form-group mb-0">
                                    <label class="sub-label"><?= t('label_ssg_link_mode') ?></label>
                                    <select name="ssg_link_mode" class="select-auto">
                                        <option value="relative" <?= $ssgLinkMode === 'relative' ? 'selected' : '' ?>><?= t('label_ssg_link_relative') ?></option>
                                        <option value="absolute" <?= $ssgLinkMode === 'absolute' ? 'selected' : '' ?>><?= t('label_ssg_link_absolute') ?></option>
                                    </select>
                                </div>
                                <div class="form-group mb-0" id="ssg-root-url-group" style="<?= $ssgLinkMode === 'absolute' ? '' : 'display:none;' ?>">
                                    <label class="sub-label"><?= t('label_ssg_root_url') ?></label>
                                    <input type="url" name="ssg_root_url" value="<?= htmlspecialchars($settings['ssg_root_url'] ?? '', ENT_QUOTES) ?>" class="input-compact" placeholder="https://example.com/site">
                                    <small class="sub-text"><?= t('hint_ssg_root_url_fixed') ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="flex-row gap-10" style="margin-top:15px;">
                            <button type="submit" name="save_action" value="ssg_build" class="btn btn-blue"><?= getIcon('sparkles') ?> <?= t('btn_ssg_save_build') ?></button>
                        </div>
                        <small class="sub-text sub-text-hint"><?= t('ssg_hint') ?></small>
                    </form>
                    <script>
                    (() => {
                        const form = document.getElementById('ssg-form');
                        if (!form) return;
                        const exportOptions = document.getElementById('ssg-export-options');
                        const rootUrlGroup = document.getElementById('ssg-root-url-group');
                        const rootUrlInput = form.querySelector('input[name="ssg_root_url"]');
                        const outputDirInput = form.querySelector('input[name="ssg_dir"]');
                        const structureSelect = form.querySelector('select[name="ssg_structure"]');
                        const serverStructureInput = form.querySelector('input[name="ssg_server_structure"]');
                        const exportStructureInput = form.querySelector('input[name="ssg_export_structure"]');
                        let currentMode = form.querySelector('input[name="ssg_mode"]:checked')?.value || 'server';
                        let outputDirWasAutoDefaulted = outputDirInput.dataset.autoDefault === '1';
                        const refresh = () => {
                            const mode = form.querySelector('input[name="ssg_mode"]:checked')?.value || 'server';
                            const linkMode = form.querySelector('select[name="ssg_link_mode"]')?.value || 'relative';
                            const needsRootUrl = mode === 'export' && linkMode === 'absolute';
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
                            rootUrlGroup.style.display = needsRootUrl ? '' : 'none';
                            rootUrlInput.disabled = !needsRootUrl;
                            rootUrlInput.required = needsRootUrl;
                        };
                        form.querySelectorAll('input[name="ssg_mode"]').forEach(el => {
                            el.addEventListener('change', () => {
                                const previousStructureInput = currentMode === 'export' ? exportStructureInput : serverStructureInput;
                                previousStructureInput.value = structureSelect.value;
                                currentMode = el.value;
                                const nextStructureInput = currentMode === 'export' ? exportStructureInput : serverStructureInput;
                                structureSelect.value = nextStructureInput.value;
                                refresh();
                            });
                        });
                        form.querySelector('select[name="ssg_link_mode"]')?.addEventListener('change', refresh);
                        structureSelect.addEventListener('change', () => {
                            const activeStructureInput = currentMode === 'export' ? exportStructureInput : serverStructureInput;
                            activeStructureInput.value = structureSelect.value;
                        });
                        outputDirInput.addEventListener('input', () => {
                            outputDirWasAutoDefaulted = false;
                        });
                        refresh();
                    })();
                    </script>
                </div>
            </details>
        </div>
    </div>
