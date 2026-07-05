<?php defined('MIKANBOX') or die(); ?>
    <div id="language">
        <div class="section-container section-tight">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('label_system_lang') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <form method="post">
                        <input type="hidden" name="save_action" value="save_settings">
                        <?= csrfField() ?>
                        <div class="form-group mb-10" style="max-width: 300px;">
                            <select name="system_lang">
                                <option value="" <?= empty($settings['system_lang'] ?? '') ? 'selected' : '' ?>><?= t('sett_lang_auto') ?></option>
                                <option value="ja" <?= ($settings['system_lang'] ?? '') === 'ja' ? 'selected' : '' ?>><?= t('sett_lang_ja') ?></option>
                                <option value="en" <?= ($settings['system_lang'] ?? '') === 'en' ? 'selected' : '' ?>><?= t('sett_lang_en') ?></option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-blue btn-small"><?= getIcon('save') ?> <?= t('btn_save') ?></button>
                    </form>
                </div>
            </details>
        </div>
    </div>
