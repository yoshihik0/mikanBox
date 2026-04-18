<?php defined('MIKANBOX') or die(); ?>

            <!-- Page Editor State Background -->
            <div id="page-editor" class="editor-focus-bg section-anchor">
                <div class="editor-floating-card">
                    <!-- Page Editor -->
                    <div class="header section-header editor-card-header">
                        <div class="header-title-group">
                            <h1 class="no-margin"><?= $editId ? t('page_edit') : t('page_new') ?><a href="<?= $helpFile ?>#page-edit" target="_blank" class="manual-link"><?= t('admin_help') ?></a></h1>
                            <?php if($editId): ?>
                                <div class="last-updated-group mt-5">
                                    <span class="updated-label"><?= t('label_updated_at') ?>:</span>
                                    <input type="text" name="updated_at" form="page-form" value="<?= htmlspecialchars($editData['updated_at'] ?? date('Y-m-d H:i:s')) ?>" class="last-updated-input" title="YYYY-MM-DD HH:MM:SS">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="editor-container editor-container-transparent">
                        <form method="post" id="page-form">
                    <input type="hidden" name="save_action" value="save_page">
                    <input type="hidden" name="old_id" value="<?= htmlspecialchars($editId??'') ?>">
                    <?= csrfField() ?>
                    <div class="page-edit-grid">
                        <div class="form-group grid-span-2">
                             <label><?= t('label_title') ?></label>
                             <input type="text" name="title" value="<?= htmlspecialchars($editData['title']??'') ?>" required placeholder="<?= t('label_title') ?>">
                         </div>
                         <div class="form-group grid-span-2">
                             <label class="sub-label"><?= t('label_status') ?></label>
                             <div class="status-selector-group">
                                 <?php $s = $editData['status'] ?? 'draft'; ?>
                                 <label class="radio-label <?= $s==='draft'?'active draft':'' ?>">
                                     <input type="radio" name="status" value="draft" <?= $s==='draft'?'checked':'' ?> onchange="document.querySelectorAll('.radio-label').forEach(l=>l.classList.remove('active','draft','dynamic','static','db')); this.parentElement.classList.add('active','draft')"> <?= t('page_status_draft') ?>
                                 </label>
                                 <label class="radio-label <?= $s==='public_dynamic'?'active dynamic':'' ?>">
                                     <input type="radio" name="status" value="public_dynamic" <?= $s==='public_dynamic'?'checked':'' ?> onchange="document.querySelectorAll('.radio-label').forEach(l=>l.classList.remove('active','draft','dynamic','static')); this.parentElement.classList.add('active','dynamic')"> <?= t('page_status_public_dynamic') ?>
                                 </label>
                                 <label class="radio-label <?= $s==='public_static'?'active static':'' ?>">
                                     <input type="radio" name="status" value="public_static" <?= $s==='public_static'?'checked':'' ?> onchange="document.querySelectorAll('.radio-label').forEach(l=>l.classList.remove('active','draft','dynamic','static','db')); this.parentElement.classList.add('active','static')"> <?= t('page_status_public_static') ?>
                                 </label>
                                 <label class="radio-label <?= $s==='db'?'active db':'' ?>">
                                     <input type="radio" name="status" value="db" <?= $s==='db'?'checked':'' ?> onchange="document.querySelectorAll('.radio-label').forEach(l=>l.classList.remove('active','draft','dynamic','static','db')); this.parentElement.classList.add('active','db')"> <?= t('page_status_db') ?>
                                 </label>
                             </div>
                         </div>
                    </div>

                    <div class="page-edit-grid">
                        <div class="form-group grid-span-1">
                            <label class="sub-label"><?= t('label_slug') ?></label>
                            <input type="text" name="id" value="<?= htmlspecialchars($editId??'') ?>" <?= ($editId && $editId === 'index') ? 'readonly' : '' ?> placeholder="profile" class="input-height">
                            <small class="sub-text sub-text-block"><?= t('page_id_hint') ?> <?= $editId === 'index' ? t('hint_slug_index') : '' ?></small>
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
                            <input type="text" name="category" value="<?= htmlspecialchars($editData['category']??'') ?>" placeholder="news, blog, ...">
                            <small class="sub-text sub-text-block">
                                <?= t('hint_existing_categories') ?>:
                                <?php
                                $all_pids = getFileList(POSTS_DIR);
                                $all_cats = [];
                                foreach($all_pids as $_pid) {
                                    $_pdata = loadData(POSTS_DIR, $_pid);
                                    $_p_cats = array_filter(array_map('trim', explode(',', $_pdata['category'] ?? '')));
                                    $all_cats = array_unique(array_merge($all_cats, $_p_cats));
                                }
                                echo !empty($all_cats) ? htmlspecialchars(implode(', ', $all_cats)) : '(なし)';
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
                    </div>
                    <div class="form-group">
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
                                <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" class="btn btn-blue preview-btn"><?= getIcon('view') ?> プレビュー</a>
                            <?php endif; ?>
                            <a href="admin.php#pages" class="btn btn-gray"><?= getIcon('arrow_back') ?> <?= t('btn_back_to_list') ?></a>
                        </div>
                        <?php if ($editId && $editId !== 'index'): ?>
                            <button type="submit" form="page-form" name="save_action" value="delete_page" class="btn btn-red" onclick="return confirm('<?= t('hint_confirm_delete') ?>')"><?= getIcon('delete') ?> <?= t('btn_delete') ?></button>
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
                            <code>table</code> <code>blockquote</code> <code>pre</code> <code>form</code> <code>&lt;!--</code> <span class="hint-desc">(コメント)</span><br>
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
