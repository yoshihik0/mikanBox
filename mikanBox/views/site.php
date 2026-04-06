<?php defined('MIKANBOX') or die(); ?>
    <!-- Site Settings & Management Memo -->
    <div id="site" class="section-anchor">
        <div class="section-container section-tight">
            <div class="header mb-10">
                <h1 class="mb-0"><?= getIcon('save') ?> <?= t('nav_settings') ?><a href="<?= $helpFile ?>#site" target="_blank" class="manual-link"><?= t('admin_help') ?></a></h1>
            </div>
            
            <div class="header section-header mt-0">
                <h2 class="section-sub-title"><?= t('memo_head') ?></h2>
            </div>
            <div class="editor-container editor-container-sub">
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_action" value="save_memo">
                    <textarea name="memo" class="textarea-memo mb-10"><?= htmlspecialchars($settings['memo'] ?? '') ?></textarea>
                    <div class="flex-row">
                        <button type="submit" class="btn btn-gray btn-small"><?= getIcon('save') ?> <?= t('btn_save') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div> <!-- /#site -->

    <!-- SSG Build Section -->
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

    <!-- CSV Import Section -->
    <div id="csv-import">
        <div class="section-container section-tight">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('csv_head') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <small class="sub-text sub-text-intro"><?= t('csv_hint') ?></small>
                    <div class="mt-10">
                        <input type="file" id="csv-file-input" accept=".csv,text/csv">
                    </div>
                    <div class="mt-10">
                        <button type="button" class="btn btn-gray btn-small" onclick="csvConvertAndCopy()" id="csv-copy-btn"><?= getIcon('copy') ?> <?= t('btn_csv_convert') ?></button>
                    </div>
                </div>
            </details>
        </div>
    </div>

    <!-- Site Settings Section -->
    <div id="settings">
        <div class="section-container section-tight">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('nav_settings_title') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <form method="post">
                        <input type="hidden" name="save_action" value="save_settings">
                        <?= csrfField() ?>
                        <div class="grid-2col">
                            <div class="form-group">
                                <label><?= t('label_site_name') ?></label>
                                <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name']??'') ?>">
                            </div>
                            <div class="form-group grid-span-2">
                                <label><?= t('label_site_desc') ?></label>
                                <textarea name="description" rows="3" class="textarea-min-80"><?= htmlspecialchars($settings['description']??'') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label><?= t('label_site_keywords') ?></label>
                                <input type="text" name="keywords" value="<?= htmlspecialchars($settings['keywords']??'') ?>">
                            </div>
                            <div class="form-group">
                                <label><?= t('label_site_ogp') ?></label>
                                <input type="text" name="ogp_image" value="<?= htmlspecialchars($settings['ogp_image']??'') ?>">
                                <small class="sub-text"><?= t('recommended_size') ?></small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-blue mt-10"><?= getIcon('save') ?> <?= t('btn_save') ?></button>
                    </form>
                </div>
            </details>
        </div>
    </div>

    <!-- Language Section -->
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
                                <option value="" <?= empty($settings['system_lang'] ?? '') ? 'selected' : '' ?>>ブラウザの設定に合わせる</option>
                                <option value="ja" <?= ($settings['system_lang'] ?? '') === 'ja' ? 'selected' : '' ?>>日本語</option>
                                <option value="en" <?= ($settings['system_lang'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-blue btn-small"><?= getIcon('save') ?> <?= t('btn_save') ?></button>
                    </form>
                </div>
            </details>
        </div>
    </div>

    <!-- Password Section -->
    <div id="password">
        <div class="section-container section-tight">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('label_change_password') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <form method="post">
                        <input type="hidden" name="save_action" value="save_settings">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label><?= t('label_current_password') ?></label>
                            <input type="password" name="current_password" style="max-width:300px;">
                        </div>
                        <div class="form-group">
                            <label><?= t('admin_new_password') ?></label>
                            <input type="password" name="new_password" style="max-width:300px;">
                        </div>
                        <button type="submit" class="btn btn-blue btn-small"><?= getIcon('save') ?> <?= t('btn_save') ?></button>
                    </form>
                </div>
            </details>
        </div>
    </div>

    <!-- Backup Section -->
    <div id="backup">
        <div class="section-container section-tight">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('backup_head') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <small class="sub-text sub-text-intro"><?= t('backup_hint') ?></small>
                    <div class="flex-row gap-10">
                        <form method="post"><?= csrfField() ?><input type="hidden" name="save_action" value="download_backup_data"><button type="submit" class="btn btn-gray btn-small"><?= getIcon('download') ?> <?= t('backup_data') ?></button></form>
                        <form method="post"><?= csrfField() ?><input type="hidden" name="save_action" value="download_backup_media"><button type="submit" class="btn btn-gray btn-small"><?= getIcon('download') ?> <?= t('backup_media') ?></button></form>
                    </div>
                </div>
            </details>
        </div>
    </div>

    <!-- AI Prompt Section -->
    <div id="ai-prompt">
        <div class="section-container section-large-bottom">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('ai_prompt_head') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <small class="sub-text sub-text-intro"><?= t('ai_prompt_hint') ?></small>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="save_action" value="save_prompt">
                        <textarea name="ai_prompt" id="ai-prompt-editor" class="textarea-sm textarea-prompt"><?= htmlspecialchars($settings['ai_prompt'] ?? '') ?></textarea>
                        <div class="flex-row">
                            <button type="submit" class="btn btn-gray btn-small"><?= getIcon('save') ?> <?= t('btn_save_prompt') ?></button>
                            <button type="button" class="btn btn-gray btn-small" onclick="copyAiPrompt()"><?= getIcon('copy') ?> <?= t('btn_copy_prompt') ?></button>
                        </div>
                    </form>
                </div>
            </details>
        </div>
    </div>
