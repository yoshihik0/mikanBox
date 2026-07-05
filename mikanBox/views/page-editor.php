<?php defined('MIKANBOX') or die(); ?>

            <!-- Page Editor State Background -->
            <div id="page-editor" class="editor-focus-bg section-anchor">
                <div class="editor-floating-card">
                    <!-- Page Editor -->
                    <div class="header section-header editor-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <div class="header-title-group">
                            <h1 class="no-margin"><?= $editId ? t('page_edit') : t('page_new') ?><a href="<?= $helpFile ?>#page-edit" target="_blank" class="manual-link"><?= t('admin_help') ?></a></h1>
                            <?php if($editId): ?>
                                <div class="last-updated-group mt-5">
                                    <span class="updated-label"><?= t('label_updated_at') ?>:</span>
                                    <input type="text" name="updated_at" form="page-form" value="<?= htmlspecialchars($editData['updated_at'] ?? date('Y-m-d H:i:s')) ?>" class="last-updated-input" title="YYYY-MM-DD HH:MM:SS">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (function_exists('getPreviewToken')): ?>
                        <div id="editor-revisions-container">
                            <?php if($editId): ?>
                                <?php $revisions = getRevisions($editId); ?>
                                <?php if(!empty($revisions)): ?>
                                    <div class="revisions-select-group" style="display: flex; align-items: center; gap: 8px; white-space: nowrap; flex-shrink: 0;">
                                        <span style="font-size: 0.85rem; color: var(--text-sub); font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">
                                            <?= getIcon('history') ?> <?= t('label_revisions') ?>:
                                        </span>
                                        <select id="revision-select" onchange="spaChangeRevision(this);" style="padding: 6px 12px; font-size: 0.85rem; border: 1px solid var(--border); border-radius: 6px; background-color: #fff; cursor: pointer; min-width: 180px; max-width: 280px;">
                                            <option value="?view=pages&edit=<?= $editId ?>#page-editor"><?= t('select_revision') ?></option>
                                            <?php foreach ($revisions as $rev): ?>
                                                <option value="?view=pages&edit=<?= $editId ?>&rev=<?= $rev['id'] ?>#page-editor" <?= ($selectedRevId == $rev['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($rev['created_at']) ?> (<?= htmlspecialchars($rev['editor_id']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="editor-container editor-container-transparent">
                        <?php if (function_exists('getPreviewToken') && isset($selectedRevId) && $selectedRevId > 0 && !empty($selectedRevInfo)): ?>
                            <div id="revision-warning-banner" class="alert alert-warning" style="background-color: #fffbeb; border: 1px solid #fef3c7; color: #b45309; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; line-height: 1.5; box-shadow: 0 1px 3px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box;">
                                <span style="font-size: 1.2rem; line-height: 1; flex-shrink: 0; color: #d97706;"><?= getIcon('warning') ?></span>
                                <div>
                                    <strong style="font-weight: 700; color: #92400e;"><?= t('viewing_revision') ?>:</strong>
                                    <?= htmlspecialchars($selectedRevInfo['created_at']) ?> (<?= htmlspecialchars($selectedRevInfo['editor_id']) ?>)
                                    <br>
                                    <span style="font-size: 0.85rem; color: #b45309;"><?= t('msg_viewing_revision_hint') ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <form method="post" id="page-form">
                    <input type="hidden" name="save_action" value="save_page">
                    <input type="hidden" name="old_id" value="<?= htmlspecialchars($editId??'') ?>">
                    <?= csrfField() ?>
                    <div class="page-edit-grid">
                        <div class="grid-span-2">
                        <div class="form-group">
                             <label><?= t('label_title') ?></label>
                             <input type="text" name="title" value="<?= htmlspecialchars($editData['title']??'') ?>" required placeholder="<?= t('label_title') ?>">
                         </div>
                         <div class="form-group">
                              <label class="sub-label"><?= t('label_status') ?></label>
                              <div class="status-selector-group">
                                  <?php
                                  $s = $editData['status'] ?? 'draft';
                                  $pendingCls = function_exists('getPreviewToken') ? ",'pending'" : '';
                                  $previewCall = function_exists('getPreviewToken') ? ' updatePreviewBannerVisibility();' : '';
                                  ?>
                                  <?php if (function_exists('getPreviewToken')): ?>
                                  <?php
                                  $previewUrl = '';
                                  if ($editId) {
                                      global $settings;
                                      $renderer = new MikanBoxRenderer($settings);
                                      $siteUrl = rtrim($renderer->getSiteUrl(), '/');
                                      $token = getPreviewToken($editId);
                                      $previewUrl = $siteUrl . '/' . ($editId === 'index' ? '' : $editId) . '?preview=' . $token;
                                  }
                                  ?>
                                  <?php endif; ?>
                                  <label class="radio-label <?= $s==='draft'?'active draft':'' ?>">
                                      <input type="radio" name="status" value="draft" <?= $s==='draft'?'checked':'' ?> onchange="document.querySelectorAll('.radio-label').forEach(l=>l.classList.remove('active','draft','dynamic','static','db'<?= $pendingCls ?>)); this.parentElement.classList.add('active','draft');<?= $previewCall ?>"> <?= t('page_status_draft') ?>
                                  </label>
                                  <?php if (function_exists('getPreviewToken')): ?>
                                  <label class="radio-label <?= $s==='pending'?'active pending':'' ?>">
                                      <input type="radio" name="status" value="pending" <?= $s==='pending'?'checked':'' ?> onchange="document.querySelectorAll('.radio-label').forEach(l=>l.classList.remove('active','draft','dynamic','static','db','pending')); this.parentElement.classList.add('active','pending'); updatePreviewBannerVisibility();"> <?= t('page_status_pending') ?>
                                  </label>
                                  <?php endif; ?>
                                  <label class="radio-label <?= $s==='public_dynamic'?'active dynamic':'' ?>">
                                      <input type="radio" name="status" value="public_dynamic" <?= $s==='public_dynamic'?'checked':'' ?> onchange="document.querySelectorAll('.radio-label').forEach(l=>l.classList.remove('active','draft','dynamic','static','db'<?= $pendingCls ?>)); this.parentElement.classList.add('active','dynamic');<?= $previewCall ?>"> <?= t('page_status_public_dynamic') ?>
                                  </label>
                                  <label class="radio-label <?= $s==='public_static'?'active static':'' ?>">
                                      <input type="radio" name="status" value="public_static" <?= $s==='public_static'?'checked':'' ?> onchange="document.querySelectorAll('.radio-label').forEach(l=>l.classList.remove('active','draft','dynamic','static','db'<?= $pendingCls ?>)); this.parentElement.classList.add('active','static');<?= $previewCall ?>"> <?= t('page_status_public_static') ?>
                                  </label>
                                  <label class="radio-label <?= $s==='db'?'active db':'' ?>">
                                      <input type="radio" name="status" value="db" <?= $s==='db'?'checked':'' ?> onchange="document.querySelectorAll('.radio-label').forEach(l=>l.classList.remove('active','draft','dynamic','static','db'<?= $pendingCls ?>)); this.parentElement.classList.add('active','db');<?= $previewCall ?>"> <?= t('page_status_db') ?>
                                  </label>
                              </div>
                              <?php if (function_exists('getPreviewToken')): ?>
                              <div id="pending-preview-block" class="preview-url-container" style="<?= ($s === 'pending') ? 'display: block;' : 'display: none;' ?> margin-top: 10px; background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 6px;">
                                  <?php if ($editId): ?>
                                      <span style="font-size: 0.8rem; color: #64748b; font-weight: 500; display: block; margin-bottom: 5px;"><?= t('preview_share_url_label') ?></span>
                                      <div style="display: flex; gap: 8px; align-items: center;">
                                          <input type="text" id="pending-preview-url" value="<?= htmlspecialchars($previewUrl) ?>" readonly style="flex: 1; padding: 6px 10px; font-size: 0.85rem; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;" onclick="this.select()">
                                          <button type="button" class="btn btn-gray btn-small" style="padding: 6px 12px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;" onclick="copyPendingPreviewUrl()">
                                              <?= getIcon('copy') ?> <span id="copy-btn-text"><?= t('btn_copy') ?></span>
                                          </button>
                                      </div>
                                  <?php else: ?>
                                      <span style="font-size: 0.85rem; color: #64748b;"><?= t('preview_url_new_page_hint') ?></span>
                                  <?php endif; ?>
                              </div>
                              <?php endif; ?>
                         </div>
                        </div>
                        <div class="form-group form-group-memo grid-span-2">
                            <label class="sub-label"><?= t('label_memo') ?></label>
                            <textarea name="memo" class="textarea-xs textarea-memo" placeholder="<?= t('memo_hint') ?>"><?= htmlspecialchars($editData['memo']??'') ?></textarea>
                        </div>
                    </div>

                    <div class="page-edit-grid">
                        <div class="form-group grid-span-1">
                            <label class="sub-label"><?= t('label_slug') ?></label>
                            <input type="text" name="id" value="<?= htmlspecialchars($editId??'') ?>" placeholder="profile" class="input-height">
                            <small class="sub-text sub-text-block"><?= t('page_id_hint') ?></small>
                        </div>
                        <div class="form-group grid-span-1">
                            <label class="sub-label"><?= t('label_design_component') ?></label>
                            <select name="wrapper_comp">
                                <?php
                                $comps = getFileList(COMPONENTS_DIR);
                                $currentWrapper = $editData['wrapper_comp'] ?? 'layout';
                                foreach($comps as $id) {
                                    $d = loadData(COMPONENTS_DIR, $id);
                                    if (empty($d['is_wrapper'])) continue;
                                    $selected = ($id === $currentWrapper) ? 'selected' : '';
                                    echo "<option value='{$id}' {$selected}>{$id}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group grid-span-1">
                            <label class="sub-label"><?= t('label_category') ?> <?= t('hint_comma_separated') ?></label>
                            <input type="text" name="category" value="<?= htmlspecialchars($editData['category'] ?? ($_GET['cat'] ?? '')) ?>" placeholder="news, blog, ...">
                            <small class="sub-text sub-text-block">
                                <?= t('hint_existing_categories') ?>:
                                <?php
                                $all_pids = getFileList(POSTS_DIR);
                                $all_cats = [];
                                foreach($all_pids as $_pid) {
                                    $_pdata = loadData(POSTS_DIR, $_pid);
                                    $_p_cats = array_filter(array_map('trim', explode(',', $_pdata['category'] ?? '')));
                                    $all_cats = array_merge($all_cats, $_p_cats);
                                }
                                global $settings;
                                if (!empty($settings['category_candidates'])) {
                                    $_reg_cats = array_filter(array_map('trim', explode(',', $settings['category_candidates'])));
                                    $all_cats = array_merge($all_cats, $_reg_cats);
                                }
                                $all_cats = array_unique(array_filter($all_cats));
                                sort($all_cats);
                                echo !empty($all_cats) ? htmlspecialchars(implode(', ', $all_cats)) : t('label_none');
                                ?>
                            </small>
                        </div>
                         <div class="form-group grid-span-1">
                             <label class="sub-label"><?= t('label_sort_order') ?></label>
                             <input type="number" name="sort_order" value="<?= htmlspecialchars($editData['sort_order']??'0') ?>" placeholder="0" class="input-full">
                         </div>
                        <div class="form-group grid-span-2">
                            <label class="sub-label"><?= t('label_ogp_image') ?></label>
                            <input type="text" name="ogp_image" value="<?= htmlspecialchars($editData['ogp_image']??'') ?>" placeholder="ogp.jpg">
                        </div>
                        <div class="form-group grid-span-2">
                            <label class="sub-label"><?= t('label_keywords') ?></label>
                            <input type="text" name="keywords" value="<?= htmlspecialchars($editData['keywords']??'') ?>" placeholder="AI, CMS, mikanBox...">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="sub-label"><?= t('label_description') ?></label>
                        <textarea name="description" class="textarea-xs textarea-description"><?= htmlspecialchars($editData['description']??'') ?></textarea>

                        <label><?= t('label_content') ?></label>
                        <label class="checkbox-label" style="font-size:0.85em; margin-bottom:4px; display:flex; align-items:center; gap:6px; font-weight:normal;">
                            <input type="checkbox" name="is_html" value="1" <?= !empty($editData['is_html']) ? 'checked' : '' ?>>
                            <?= t('label_raw_html') ?>
                        </label>
                        <textarea name="content_md" class="textarea-lg textarea-mono"><?= htmlspecialchars($editData['content_md']??'') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label><?= t('label_css') ?></label>
                        <textarea name="css" class="textarea-sm textarea-mono"><?= htmlspecialchars($editData['css']??'') ?></textarea>
                    </div>
                    <div class="flex-row flex-between mt-10">
                        <div class="flex-row">
                            <button type="submit" form="page-form" name="save_action" value="save_page" class="btn btn-blue"><?= getIcon('save') ?> <?= t('btn_save') ?></button>
                            <?php if($editId): ?>
                                <?php
                                $previewStatus = $editData['status'] ?? 'draft';
                                $ssgStruct = $settings['ssg_structure'] ?? 'directory';
                                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                $siteDir = dirname(dirname($_SERVER['SCRIPT_NAME']));
                                if ($siteDir === '/' || $siteDir === '.') $siteDir = '';
                                $siteBaseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $siteDir;
                                if ($editId === 'index') {
                                    $previewUrl = $siteBaseUrl . '/';
                                } elseif ($previewStatus === 'public_static') {
                                    $staticRoot = !empty($settings['ssg_root_url'])
                                        ? rtrim($settings['ssg_root_url'], '/')
                                        : $siteBaseUrl . (($ssgDir !== '') ? '/' . trim($ssgDir, '/') : '');
                                    $previewUrl = $staticRoot . '/' . $editId . ($ssgStruct === 'directory' ? '/' : '.html');
                                } else {
                                    $previewUrl = $siteBaseUrl . '/' . $editId;
                                }
                                ?>
                                <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" class="btn btn-blue preview-btn"><?= getIcon('view') ?> <?= t('admin_view_site') ?></a>
                            <?php endif; ?>
                            <a href="admin.php#pages" class="btn btn-gray"><?= getIcon('arrow_back') ?> <?= t('btn_back_to_list') ?></a>
                        </div>
                        <?php if ($editId): ?>
                            <button type="submit" form="page-form" name="save_action" value="delete_page" class="btn btn-red"><?= getIcon('delete') ?> <?= t('btn_delete') ?></button>
                        <?php endif; ?>
                    </div>
                </form>
            
            <details class="hint-accordion">
                <summary><h3 class="accordion-title"><?= t('markdown_head') ?> <span class="accordion-arrow">▼</span></h3></summary>
                <div class="hint-accordion-body">
                    <div class="hint-grid hint-grid-md4">
                        <div class="hint-list">
                            <code>(<?= t('md_paragraph') ?>)</code> <span class="hint-desc">&lt;p&gt;</span><br>
                            <br>
                            <span class="hint-desc"><?= t('md_html_mix_short') ?></span><br>
                            <span class="hint-desc"><?= t('md_html_block_intro') ?></span><br>
                            <code>html</code> <code>head</code> <code>body</code> <code>link</code> <code>meta</code> <code>script</code> <code>style</code><br>
                            <code>div</code> <code>section</code> <code>article</code> <code>aside</code> <code>header</code> <code>footer</code><br>
                            <code>p</code> <code>h1〜h6</code> <code>ul</code> <code>ol</code> <code>li</code><br>
                            <code>table</code> <code>blockquote</code> <code>pre</code> <code>form</code> <code>&lt;!--</code> <span class="hint-desc">(<?= t('md_comment') ?>)</span><br>
                            <span class="hint-desc"><?= t('md_html_block_note') ?></span>
                        </div>
                        <div class="hint-list">
                            <span class="hint-section-label"><?= t('md_block_head') ?></span><br>
                            <code># <?= t('md_heading') ?></code> <span class="hint-desc">&lt;h1&gt;</span><br>
                            <code>## <?= t('md_heading') ?></code> <span class="hint-desc">&lt;h2&gt;</span><br>
                            <code>### <?= t('md_subheading') ?></code> <span class="hint-desc">&lt;h3&gt;</span><br>
                            <code>#### <?= t('md_subheading') ?></code> <span class="hint-desc">&lt;h4&gt;</span><br>
                            <code>##### <?= t('md_subheading') ?></code> <span class="hint-desc">&lt;h5&gt;</span><br>
                            <code>###### <?= t('md_subheading') ?></code> <span class="hint-desc">&lt;h6&gt;</span><br>
                            <code>- <?= t('md_list') ?></code> <span class="hint-desc">&lt;ul&gt;&lt;li&gt;</span><br>
                            <code>* <?= t('md_list') ?></code> <span class="hint-desc">&lt;ul&gt;&lt;li&gt;</span><br>
                            <code>1. <?= t('md_num_list') ?></code> <span class="hint-desc">&lt;ol&gt;&lt;li&gt;</span><br>
                            <span class="hint-desc"><?= t('md_list_space_note') ?>。</span><br>
                            <br>
                            <code>&gt; <?= t('md_quote') ?></code> <span class="hint-desc">&lt;blockquote&gt;</span><br>
                            <span class="hint-desc"><?= t('md_quote_space_note') ?></span><br>
                            <br>
                            <code>---</code> <span class="hint-desc"><?= t('md_hr') ?> &lt;hr&gt;</span><br>
                            <code>```</code> <span class="hint-desc"><?= t('md_code_block') ?> &lt;pre&gt;&lt;code&gt;</span>
                        </div>
                        <div class="hint-list">
                            <span class="hint-section-label"><?= t('md_table_head') ?></span><br>
                            <code>| <?= t('md_col_default') ?> | <?= t('md_col_left') ?> | <?= t('md_col_center') ?> | <?= t('md_col_right') ?> |</code><br>
                            <code>|---|:---|:---:|---:|</code><br>
                            <code>|  A  |  B  |  C  |  D  |</code><br>
                            <span class="hint-desc"><?= t('md_table_th_note') ?></span><br>
                            <span class="hint-desc"><?= t('md_table_td_note') ?></span><br>
                            <span class="hint-desc"><?= t('md_table_row2_required') ?></span><br>
                            <br>
                            <span class="hint-section-label"><?= t('md_inline_head') ?></span><br>
                            <code>**<?= t('md_bold') ?>**</code> <span class="hint-desc">&lt;strong&gt;</span><br>
                            <code>*<?= t('md_italic') ?>*</code> <span class="hint-desc">&lt;em&gt;</span><br>
                            <code>`<?= t('md_inline_code') ?>`</code> <span class="hint-desc">&lt;code&gt;</span><br>
                            <code>[<?= t('md_link_text') ?>](URL)</code><br>
                            <code>![<?= t('md_image_alt') ?>](<?= t('filename') ?>)</code>
                        </div>
                        <div class="hint-list">
                            <span class="hint-section-label"><?= t('md_unique_tags') ?></span><br>
                            <code>{.className}</code><span class="hint-desc"> <?= t('md_class') ?></span><br>
                            <code>{#idName}</code><span class="hint-desc"> <?= t('md_id') ?></span><br>
                            <code>[text]{.className}</code><span class="hint-desc"> <?= t('md_span_class_c') ?></span><br>
                            <code>[text]{#idName}</code><span class="hint-desc"> <?= t('md_span_class_i') ?></span>
                        </div>
                    </div>
                </div>
            </details>

                <?php $renderTagGuide(); ?>
                    </div> <!-- /editor-container -->
                </div> <!-- /editor-floating-card -->
            </div> <!-- /editor-focus-bg -->

<?php if (function_exists('getPreviewToken')): ?>
<script>
function updatePreviewBannerVisibility() {
    const block = document.getElementById('pending-preview-block');
    if (!block) return;
    const isPendingChecked = document.querySelector('input[name="status"][value="pending"]')?.checked;
    block.style.display = isPendingChecked ? 'block' : 'none';
    
    // Update the View Site preview button dynamically
    const editor = document.getElementById('page-editor');
    if (editor) {
        let previewBtn = editor.querySelector('.preview-btn');
        if (previewBtn) {
            if (isPendingChecked) {
                const pendingUrlInput = document.getElementById('pending-preview-url');
                if (pendingUrlInput && pendingUrlInput.value) {
                    previewBtn.href = pendingUrlInput.value;
                }
            } else {
                const publicStaticChecked = document.querySelector('input[name="status"][value="public_static"]')?.checked;
                const idInput = editor.querySelector('input[name="id"]');
                const editId = idInput ? idInput.value.trim() : '';
                const siteUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                
                if (editId) {
                    if (publicStaticChecked) {
                        const ssgStruct = editor.querySelector('input[name="ssg_structure"]')?.value || 'directory';
                        const ssgDir = editor.querySelector('input[name="ssg_dir"]')?.value || '';
                        const ssgRoot = editor.querySelector('input[name="ssg_root_url"]')?.value || '';
                        const staticRoot = ssgRoot ? ssgRoot.replace(/\/$/, '') : siteUrl + (ssgDir ? '/' + ssgDir.replace(/^\/|\/$/g, '') : '');
                        previewBtn.href = staticRoot + '/' + editId + (ssgStruct === 'directory' ? '/' : '.html');
                    } else {
                        previewBtn.href = siteUrl + '/' + editId;
                    }
                }
            }
        }
    }
}
function copyPendingPreviewUrl() {
    const input = document.getElementById('pending-preview-url');
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    try {
        navigator.clipboard.writeText(input.value).then(() => {
            const btnText = document.getElementById('copy-btn-text');
            if (btnText) {
                const orig = btnText.textContent;
                btnText.textContent = '<?= t('msg_copied_simple') ?>';
                setTimeout(() => { btnText.textContent = orig; }, 2000);
            }
        });
    } catch(err) {
        // Fallback
        document.execCommand('copy');
        const btnText = document.getElementById('copy-btn-text');
        if (btnText) {
            const orig = btnText.textContent;
            btnText.textContent = '<?= t('msg_copied_simple') ?>';
            setTimeout(() => { btnText.textContent = orig; }, 2000);
        }
    }
}

// Search keyword editor highlight
function highlightSearchKeyword() {
    const params = new URLSearchParams(window.location.search);
    const q = params.get('q');
    if (!q) return;

    const lowerQ = q.toLowerCase();
    
    // Priority list of selectors to search and highlight within
    const selectors = [
        'input[name="title"]',
        'input[name="id"]',
        'textarea[name="description"]',
        'input[name="category"]',
        'textarea[name="content_md"]'
    ];

    for (const selector of selectors) {
        const el = document.querySelector(selector);
        if (el) {
            const val = el.value;
            const index = val.toLowerCase().indexOf(lowerQ);
            if (index !== -1) {
                // Focus and select the matching keyword range
                setTimeout(() => {
                    el.focus();
                    el.setSelectionRange(index, index + q.length);
                    
                    // If it's a textarea, scroll to the matched position
                    if (el.tagName.toLowerCase() === 'textarea') {
                        const lineCount = val.substring(0, index).split("\n").length;
                        const lineHeight = parseFloat(window.getComputedStyle(el).lineHeight) || 16;
                        el.scrollTop = Math.max(0, (lineCount - 4) * lineHeight);
                    }
                }, 300);
                break; // Stop after highlighting the first matching field
            }
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", highlightSearchKeyword);
} else {
    highlightSearchKeyword();
}
</script>
<?php endif; ?>
