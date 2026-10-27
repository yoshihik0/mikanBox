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
                    <?php $mcpHasKey = !empty($settings['mcp_api_key']);
                          // The admin MCP endpoint sits beside admin.php: {site URL}/{admin folder}/mcp
                          $mcpHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
                          $mcpUrl = ($mcpHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                                  . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/mikanBox/admin.php')), '/') . '/mcp'; ?>
                    <div class="form-group mt-10">
                        <label for="mcp-url-display"><?= t('mcp_url_label') ?></label>
                        <div class="copy-row">
                            <input type="text" id="mcp-url-display" value="<?= htmlspecialchars($mcpUrl) ?>" readonly>
                            <button type="button" class="btn btn-gray btn-small" onclick="navigator.clipboard.writeText(document.getElementById('mcp-url-display').value).then(()=>showToast('<?= t('msg_copied') ?>'))"><?= getIcon('copy') ?> <?= t('btn_copy') ?></button>
                        </div>
                    </div>
                    <?php // Both states are always rendered so that a freshly issued key can be
                          // shown in place. Reloading instead would collapse this accordion and
                          // hide the key the moment it is created. ?>
                    <div id="mcp-key-issued"<?= $mcpHasKey ? '' : ' style="display:none;"' ?>>
                        <div class="form-group mt-10">
                            <label for="mcp-key-display"><?= t('mcp_key_label') ?></label>
                            <div class="copy-row">
                                <input type="text" id="mcp-key-display" value="<?= $mcpHasKey ? htmlspecialchars($settings['mcp_api_key']) : '' ?>" readonly>
                                <button type="button" class="btn btn-gray btn-small" onclick="navigator.clipboard.writeText(document.getElementById('mcp-key-display').value).then(()=>showToast('<?= t('msg_copied') ?>'))"><?= getIcon('copy') ?> <?= t('btn_copy') ?></button>
                            </div>
                        </div>
                        <form method="post" class="mt-10">
                            <?= csrfField() ?>
                            <input type="hidden" name="save_action" value="generate_mcp_key">
                            <button type="submit" class="btn btn-gray btn-small"><?= getIcon('sparkles') ?> <?= t('btn_regenerate_mcp_key') ?></button>
                        </form>
                    </div>
                    <form method="post" class="mt-10" id="mcp-key-generate"<?= $mcpHasKey ? ' style="display:none;"' : '' ?>>
                        <?= csrfField() ?>
                        <input type="hidden" name="save_action" value="generate_mcp_key">
                        <button type="submit" class="btn btn-blue btn-small"><?= getIcon('sparkles') ?> <?= t('btn_generate_mcp_key') ?></button>
                    </form>
                </div>
            </details>
        </div>
    </div>
