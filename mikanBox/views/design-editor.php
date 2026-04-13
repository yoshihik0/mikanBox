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
                <div class="form-group">
                    <label><?= t('label_component_id') ?></label>
                    <input type="text" name="id" value="<?= htmlspecialchars($editId??'') ?>" required placeholder="header">
                    <small class="sub-text sub-text-block"><?= t('component_id_hint') ?></small>
                </div>
                <div class="form-group">
                    <label>HTML</label>
                    <textarea name="html" class="textarea-md textarea-mono" rows="18"><?= htmlspecialchars($editData['html']??'') ?></textarea>
                </div>
                <div class="form-group">
                    <label>CSS (<?= t('component_css_hint') ?>)</label>
                    <textarea name="css" class="textarea-md textarea-mono" rows="13"><?= htmlspecialchars($editData['css']??'') ?></textarea>
                </div>
                <div class="form-group mt-15">
                    <div class="form-group">
                        <label class="checkbox-label checkbox-flex">
                            <input type="checkbox" name="use_scope" value="1" <?= empty($editData['is_global']) ? 'checked' : '' ?>> <?= t('label_scope_css') ?>
                        </label>
                        <small class="sub-text sub-text-indent"><?= t('use_scope_hint') ?></small>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label checkbox-flex">
                            <input type="checkbox" name="is_wrapper" value="1" <?= !empty($editData['is_wrapper']) ? 'checked' : '' ?>> <?= t('label_use_wrapper') ?>
                        </label>
                        <small class="sub-text sub-text-indent"><?= t('use_wrapper_hint') ?></small>
                    </div>
                </div>
                
                <div class="flex-row flex-between mt-25">
                    <div class="flex-row">
                        <button type="submit" form="comp-form" class="btn btn-blue"><?= getIcon('save') ?> <?= t('btn_save') ?></button>
                        <a href="admin.php#design" class="btn btn-gray"><?= getIcon('arrow_back') ?> <?= t('btn_back_to_list') ?></a>
                    </div>
                    <?php if ($editId): ?>
                        <button type="submit" form="comp-form" name="save_action" value="delete_comp" class="btn btn-red" onclick="return confirm('<?= t('hint_confirm_delete') ?>')"><?= getIcon('delete') ?> <?= t('btn_delete') ?></button>
                    <?php endif; ?>
                </div>
            </form>

            <?php $renderTagGuide(); ?>

            </div> <!-- /editor-container -->
        </div> <!-- /editor-floating-card -->
    </div> <!-- /editor-focus-bg -->
