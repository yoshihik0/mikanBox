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

    <!-- Version Info -->
    <?php
    $latestVersion = null;
    $vCacheKey = 'mikanbox_latest_ver';
    $vCacheTime = 'mikanbox_latest_ver_time';
    if (isset($_SESSION[$vCacheKey]) && (time() - ($_SESSION[$vCacheTime] ?? 0)) < 21600) {
        $latestVersion = $_SESSION[$vCacheKey];
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'header' => "User-Agent: mikanBox-admin\r\n"]]);
        $json = @file_get_contents('https://api.github.com/repos/yoshihik0/mikanBox/releases/latest', false, $ctx);
        if ($json) {
            $vData = json_decode($json, true);
            $latestVersion = $vData['tag_name'] ?? null;
        }
        if (!$latestVersion) {
            $json = @file_get_contents('https://api.github.com/repos/yoshihik0/mikanBox/tags', false, $ctx);
            if ($json) {
                $vData = json_decode($json, true);
                $latestVersion = $vData[0]['name'] ?? null;
            }
        }
        $_SESSION[$vCacheKey] = $latestVersion;
        $_SESSION[$vCacheTime] = time();
    }
    $isOutdated = $latestVersion && $latestVersion !== 'v' . MIKANBOX_VERSION && $latestVersion !== MIKANBOX_VERSION;
    ?>
    <div class="section-container section-tight">
        <div style="font-size:0.82em; color:var(--text-sub,#888); padding:6px 2px; display:flex; gap:1.5em; align-items:center; flex-wrap:wrap;">
            <span><?= t('version_current') ?>: <?= htmlspecialchars(MIKANBOX_VERSION) ?></span>
            <span><?= t('version_latest') ?>: <?= $latestVersion ? htmlspecialchars($latestVersion) : '—' ?><?php if ($isOutdated): ?> <span style="color:#e07000;">▲ <?= t('version_update_available') ?></span><?php endif; ?></span>
            <a href="https://github.com/yoshihik0/mikanBox" target="_blank" rel="noopener" style="color:inherit;">GitHub</a>
        </div>
    </div>

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

    <!-- MCP API Key Section -->
    <div id="mcp-api-key">
        <div class="section-container section-tight">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('mcp_key_head') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <small class="sub-text sub-text-intro"><?= t('mcp_key_hint') ?></small>
                    <?php if (!empty($settings['mcp_api_key'])): ?>
                    <div class="form-group mt-10">
                        <label><?= t('mcp_key_label') ?></label>
                        <input type="text" id="mcp-key-display" value="<?= htmlspecialchars($settings['mcp_api_key']) ?>" readonly style="font-family: monospace; width: 100%; box-sizing: border-box;">
                    </div>
                    <div class="flex-row gap-10 mt-10">
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="save_action" value="generate_mcp_key">
                            <button type="submit" class="btn btn-gray btn-small"><?= getIcon('sparkles') ?> <?= t('btn_regenerate_mcp_key') ?></button>
                        </form>
                        <button type="button" class="btn btn-gray btn-small" onclick="navigator.clipboard.writeText(document.getElementById('mcp-key-display').value).then(()=>showToast('<?= t('msg_copied') ?>'))"><?= getIcon('copy') ?> <?= t('btn_copy') ?></button>
                    </div>
                    <?php else: ?>
                    <form method="post" class="mt-10">
                        <?= csrfField() ?>
                        <input type="hidden" name="save_action" value="generate_mcp_key">
                        <button type="submit" class="btn btn-blue btn-small"><?= getIcon('sparkles') ?> <?= t('btn_generate_mcp_key') ?></button>
                    </form>
                    <?php endif; ?>
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

    <!-- Data Management Section -->
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
                        <form method="post"><?= csrfField() ?><input type="hidden" name="save_action" value="download_backup_sqlite"><button type="submit" class="btn btn-gray btn-small"><?= getIcon('download') ?> <?= t('backup_data_sqlite') ?></button></form>
                        <form method="post"><?= csrfField() ?><input type="hidden" name="save_action" value="download_backup_json"><button type="submit" class="btn btn-gray btn-small"><?= getIcon('download') ?> <?= t('backup_data_json') ?></button></form>
                        <form method="post"><?= csrfField() ?><input type="hidden" name="save_action" value="download_backup_media"><button type="submit" class="btn btn-gray btn-small"><?= getIcon('download') ?> <?= t('backup_media') ?></button></form>
                        <a href="convert.php" target="_blank" class="btn btn-orange btn-small" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px;"><?= getIcon('settings_backup_restore') ?> <?= t('btn_data_tool') ?></a>
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
                            <div class="form-group">
                                <label><?= t('label_pages_per_page') ?></label>
                                <input type="number" name="pages_per_page" value="<?= htmlspecialchars($settings['pages_per_page']??'30') ?>" min="1">
                            </div>
                            <div class="form-group">
                                <label><?= t('label_media_per_page') ?></label>
                                <input type="number" name="media_per_page" value="<?= htmlspecialchars($settings['media_per_page']??'100') ?>" min="1">
                            </div>
                            <div class="form-group grid-span-2" style="display: none;">
                                <label><?= t('label_register_cat') ?> <?= t('hint_comma_separated') ?></label>
                                <input type="text" name="category_candidates" value="<?= htmlspecialchars($settings['category_candidates']??'') ?>" placeholder="coffee, sample, gallery_item, dummy, news, nav, test">
                                <small class="sub-text"><?= t('hint_register_cat') ?></small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-blue mt-10"><?= getIcon('save') ?> <?= t('btn_save') ?></button>
                    </form>
                </div>
            </details>
        </div>
    </div>

    <!-- User Management Section -->
    <div id="users-mgmt" class="section-large-bottom">
        <div class="section-container section-tight">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('label_user_mgmt') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <?php
                    $db = getDb();
                    $usersList = [];
                    try {
                        $resUsers = $db->query("SELECT username, display_name FROM users ORDER BY username ASC");
                        if ($resUsers) {
                            while ($u = $resUsers->fetchArray(SQLITE3_ASSOC)) {
                                $usersList[] = $u;
                            }
                        }
                    } catch (Exception $e) {}
                    ?>
                    <!-- User List -->
                    <div id="users-list-wrap">
                        <h3 style="font-size: 0.95rem; margin-bottom: 12px; color: var(--text); border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;"><?= t('label_registered_users') ?></h3>
                        <table class="table-clean" style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
                            <thead>
                                <tr style="border-bottom: 1px solid #e2e8f0; text-align: left;">
                                    <th style="padding: 8px 4px; font-size: 0.8rem; color: #475569;"><?= t('label_username') ?></th>
                                    <th style="padding: 8px 4px; font-size: 0.8rem; color: #475569;"><?= t('label_display_name') ?></th>
                                    <th style="padding: 8px 4px; font-size: 0.8rem; color: #475569; text-align: right;"><?= t('label_operation_action') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersList as $u): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 8px 4px; font-size: 0.85rem;"><code><?= htmlspecialchars($u['username']) ?></code></td>
                                        <td style="padding: 8px 4px; font-size: 0.85rem;"><?= htmlspecialchars($u['display_name']) ?></td>
                                        <td style="padding: 8px 4px; text-align: right;">
                                            <?php if ($u['username'] !== ($_SESSION['admin_username'] ?? '') && count($usersList) > 1): ?>
                                                <form method="post" style="display: inline;" class="delete-user-form" data-display-name="<?= htmlspecialchars($u['display_name']) ?>">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="save_action" value="delete_user">
                                                    <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                                    <button type="submit" class="btn btn-red btn-small" style="padding: 2px 6px; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 3px;"><?= getIcon('delete') ?> <?= t('btn_delete') ?></button>
                                                </form>
                                            <?php else: ?>
                                                <span style="font-size: 0.75rem; color: #94a3b8;"><?= $u['username'] === ($_SESSION['admin_username'] ?? '') ? t('label_logged_in') : t('label_cannot_delete') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Add User Form -->
                    <details class="section-accordion" style="margin-top: 15px; margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fafafa;">
                        <summary class="header section-header accordion-summary" style="padding: 10px 15px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; border-bottom: none; background: transparent; outline: none; list-style: none;">
                            <h3 style="font-size: 0.95rem; margin: 0; color: var(--text); display: inline-flex; align-items: center; gap: 8px; pointer-events: none;">
                                <?= getIcon('add') ?> <?= t('label_add_new_user') ?>
                            </h3>
                            <span class="accordion-arrow" style="font-size: 0.75rem; opacity: 0.5;">▼</span>
                        </summary>
                        <div style="padding: 15px; border-top: 1px solid #e2e8f0; background: #fff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="save_action" value="add_user">
                                <div class="form-group">
                                    <label><?= t('hint_username_rules') ?></label>
                                    <input type="text" name="username" style="max-width:300px;" required placeholder="<?= t('placeholder_username_eg') ?>">
                                </div>
                                <div class="form-group">
                                    <label><?= t('hint_display_name_rules') ?></label>
                                    <input type="text" name="display_name" style="max-width:300px;" placeholder="<?= t('placeholder_display_name_eg') ?>">
                                </div>
                                <div class="form-group">
                                    <label><?= t('hint_password_rules') ?></label>
                                    <input type="password" name="password" style="max-width:300px;" required>
                                </div>
                                <button type="submit" class="btn btn-blue btn-small"><?= getIcon('add') ?> <?= t('btn_add_user') ?></button>
                            </form>
                        </div>
                    </details>

                    <!-- Change Logged-in User Password & Security Question Form -->
                    <?php
                    // Get current user's security question status
                    $currentUserInfo = null;
                    try {
                        $stmtCur = $db->prepare("SELECT security_question FROM users WHERE username = :username");
                        $stmtCur->bindValue(':username', $_SESSION['admin_username'] ?? '', SQLITE3_TEXT);
                        $resCur = $stmtCur->execute();
                        $currentUserInfo = $resCur->fetchArray(SQLITE3_ASSOC);
                    } catch (Exception $e) {}
                    
                    $hasQuestionSet = !empty($currentUserInfo['security_question']);
                    ?>
                    
                    <details class="section-accordion" style="margin-top: 15px; margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fafafa;">
                        <summary class="header section-header accordion-summary" style="padding: 10px 15px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; border-bottom: none; background: transparent; outline: none; list-style: none;">
                            <h3 style="font-size: 0.95rem; margin: 0; color: var(--text); display: inline-flex; align-items: center; gap: 8px; pointer-events: none;">
                                <?= getIcon('save') ?> <?= t('label_account_settings') ?>
                            </h3>
                            <span class="accordion-arrow" style="font-size: 0.75rem; opacity: 0.5;">▼</span>
                        </summary>
                        <div style="padding: 15px; border-top: 1px solid #e2e8f0; background: #fff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                            <?php if (!$hasQuestionSet): ?>
                                <div style="background-color: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; padding: 10px 15px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                                    <?= getIcon('reset') ?> <?= t('security_question_unset_warning') ?>
                                </div>
                            <?php endif; ?>

                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="save_action" value="change_my_password">
                                <div class="form-group">
                                    <label><?= t('hint_username_rules') ?></label>
                                    <input type="text" name="username" style="max-width:300px;" required value="<?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>" placeholder="<?= t('placeholder_username_eg') ?>">
                                </div>
                                <div class="form-group">
                                    <label><?= t('label_display_name') ?></label>
                                    <input type="text" name="display_name" style="max-width:300px;" required value="<?= htmlspecialchars($_SESSION['admin_display_name'] ?? '') ?>" placeholder="<?= t('placeholder_display_name_eg') ?>">
                                </div>
                                <div class="form-group">
                                    <label><?= t('hint_current_password_edit') ?></label>
                                    <input type="password" name="current_password" style="max-width:300px;">
                                </div>
                                <div class="form-group">
                                    <label><?= t('hint_new_password_edit') ?></label>
                                    <input type="password" name="new_password" style="max-width:300px;" placeholder="<?= t('placeholder_keep_password') ?>">
                                </div>
                                <div class="form-group">
                                    <label><?= t('security_question_label') ?></label>
                                    <input type="text" name="security_question" style="max-width:400px;" required value="<?= htmlspecialchars($currentUserInfo['security_question'] ?? '') ?>" placeholder="<?= t('security_question_placeholder') ?>">
                                </div>
                                <div class="form-group">
                                    <label><?= t('security_answer_label') ?> <?= t('hint_keep_current_blank') ?></label>
                                    <input type="text" name="security_answer" style="max-width:400px;" placeholder="<?= t('placeholder_security_answer') ?>">
                                </div>
                                <button type="submit" class="btn btn-blue btn-small"><?= getIcon('save') ?> <?= t('btn_update_settings') ?></button>
                            </form>
                        </div>
                    </details>
                </div>
            </details>
        </div>
    </div>


