<?php defined('MIKANBOX') or die(); ?>
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
