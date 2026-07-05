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
                        <div class="flex-row items-end gap-20 flex-wrap">
                            <div class="form-group mb-0">
                                <label class="sub-label"><?= t('label_ssg_output_dir') ?></label>
                                <input type="text" name="ssg_dir" value="<?= htmlspecialchars($ssgDir) ?>" class="input-compact">
                            </div>
                            <div class="form-group mb-0">
                                <label class="sub-label"><?= t('label_ssg_structure') ?></label>
                                <select name="ssg_structure" class="select-auto">
                                    <option value="directory" <?= ($settings['ssg_structure'] ?? 'directory') === 'directory' ? 'selected' : '' ?>><?= t('label_ssg_dir_based') ?></option>
                                    <option value="file" <?= ($settings['ssg_structure'] ?? 'directory') === 'file' ? 'selected' : '' ?>><?= t('label_ssg_file_based') ?></option>
                                </select>
                            </div>
                            <div class="flex-row gap-10 self-end">
                                <button type="submit" name="save_action" value="ssg_build" class="btn btn-blue"><?= getIcon('sparkles') ?> <?= t('btn_ssg_save_build') ?></button>
                            </div>
                        </div>
                        <small class="sub-text sub-text-hint"><?= t('ssg_hint') ?></small>
                    </form>
                </div>
            </details>
        </div>
    </div>
