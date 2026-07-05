<?php defined('MIKANBOX') or die(); ?>
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
