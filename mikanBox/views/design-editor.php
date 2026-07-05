<?php defined('MIKANBOX') or die(); ?>

            <!-- Design Editor State Background -->
            <div id="design-editor" class="editor-focus-bg section-anchor">
                <div class="editor-floating-card">
                    <!-- Component Editor -->
                    <div class="header section-header editor-card-header">
                        <h1 class="no-margin"><?= $editId ? t('comp_edit') : t('comp_new') ?><a href="<?= $helpFile ?>#design-edit" target="_blank" class="manual-link"><?= t('admin_help') ?></a></h1>
                    </div>
                    <div class="editor-container editor-container-transparent">
                        <form method="post" id="comp-form">
                    <input type="hidden" name="save_action" value="save_comp">
                    <input type="hidden" name="old_id" value="<?= htmlspecialchars($editId??'') ?>">
                    <?= csrfField() ?>
                <?php
                $currentType = 'part';
                if (!empty($editData['is_wrapper'])) {
                    $currentType = 'wrapper';
                } elseif (!empty($editData['is_ai_doc'])) {
                    $currentType = 'ai_doc';
                }
                ?>
                <div class="page-edit-grid">
                    <div class="grid-span-2">
                    <div class="form-group">
                        <label><?= t('label_component_id') ?></label>
                        <input type="text" name="id" value="<?= htmlspecialchars($editId??'') ?>" required placeholder="header">
                        <small class="sub-text sub-text-block"><?= t('component_id_hint') ?></small>
                    </div>
                    <div class="form-group">
                        <label><?= t('label_type') ?: 'タイプ' ?></label>
                        <div class="checkbox-flex" style="display: flex; gap: 20px; align-items: center; margin-top: 5px; flex-wrap: wrap;">
                            <label class="checkbox-label" style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="comp_type" value="part" <?= ($currentType === 'part') ? 'checked' : '' ?>>
                                <?= t('comp_type_part') ?: 'パーツ' ?>
                            </label>
                            <label class="checkbox-label" style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="comp_type" value="wrapper" <?= ($currentType === 'wrapper') ? 'checked' : '' ?>>
                                <?= t('comp_type_page') ?: 'ページ' ?>
                            </label>
                            <label class="checkbox-label" style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="comp_type" value="ai_doc" <?= ($currentType === 'ai_doc') ? 'checked' : '' ?>>
                                <?= t('comp_type_aidoc') ?: 'AI指示' ?>
                            </label>
                        </div>
                    </div>
                    </div>
                    <div class="form-group form-group-memo grid-span-2">
                        <label><?= t('label_memo') ?></label>
                        <textarea name="memo" class="textarea-xs textarea-memo" placeholder="<?= t('memo_hint') ?>"><?= htmlspecialchars($editData['memo']??'') ?></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label id="html-editor-label">HTML</label>
                    <textarea name="html" class="textarea-md textarea-mono" rows="18"><?= htmlspecialchars($editData['html']??'') ?></textarea>
                </div>
                <div class="form-group">
                    <label id="css-editor-label">CSS (<?= t('component_css_hint') ?>)</label>
                    <textarea name="css" class="textarea-md textarea-mono" rows="13"><?= htmlspecialchars($editData['css']??'') ?></textarea>
                </div>
                <div class="form-group mt-15" id="scope-css-group">
                    <label class="checkbox-label checkbox-flex">
                        <input type="checkbox" name="use_scope" value="1" <?= empty($editData['is_global']) ? 'checked' : '' ?>> <?= t('label_scope_css') ?>
                    </label>
                    <small class="sub-text sub-text-indent"><?= t('use_scope_hint') ?></small>
                </div>
                
                <div class="flex-row flex-between mt-25">
                    <div class="flex-row">
                        <button type="submit" form="comp-form" class="btn btn-blue"><?= getIcon('save') ?> <?= t('btn_save') ?></button>
                        <a href="admin.php#design" class="btn btn-gray"><?= getIcon('arrow_back') ?> <?= t('btn_back_to_list') ?></a>
                    </div>
                    <?php if ($editId): ?>
                        <button type="submit" form="comp-form" name="save_action" value="delete_comp" class="btn btn-red"><?= getIcon('delete') ?> <?= t('btn_delete') ?></button>
                    <?php endif; ?>
                </div>
            </form>

            <?php $renderTagGuide(); ?>

            <script>
            (function() {
                const radioButtons = document.querySelectorAll('input[name="comp_type"]');
                const htmlLabel = document.getElementById('html-editor-label');
                const cssLabel = document.getElementById('css-editor-label');
                const scopeGroup = document.getElementById('scope-css-group');
                const tagGuide = document.querySelector('.hint-accordion');

                function updateEditorLabels() {
                    const checkedRadio = document.querySelector('input[name="comp_type"]:checked');
                    if (!checkedRadio) return;
                    const selected = checkedRadio.value;
                    if (selected === 'ai_doc') {
                        if (htmlLabel) htmlLabel.textContent = 'Markdown';
                        if (cssLabel) cssLabel.textContent = '<?= addslashes(t('css_editor_label_aidoc')) ?>';
                        if (scopeGroup) scopeGroup.style.display = 'none';
                        if (tagGuide) tagGuide.style.display = 'none';
                    } else {
                        if (htmlLabel) htmlLabel.textContent = 'HTML';
                        if (cssLabel) cssLabel.textContent = 'CSS (<?= addslashes(t('component_css_hint')) ?>)';
                        if (scopeGroup) scopeGroup.style.display = 'block';
                        if (tagGuide) tagGuide.style.display = 'block';
                    }
                }

                radioButtons.forEach(radio => {
                    radio.addEventListener('change', updateEditorLabels);
                });

                // Trigger on load
                updateEditorLabels();
            })();
            </script>

            </div> <!-- /editor-container -->
        </div> <!-- /editor-floating-card -->
    </div> <!-- /editor-focus-bg -->
