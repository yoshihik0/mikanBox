<?php defined('MIKANBOX') or die();
mikanBoxMcpKeyUpgrade();   // a key an older version stored in plain text is kept only as its fingerprint
$mcpDisplayKey = (string)($settings['mcp_api_key'] ?? '');   // the fingerprint (or an old plain key, converted above): never shown
$mcpJustIssued = (string)($_SESSION['mikanbox_mcp_new_key'] ?? '');   // shown once after a non-ajax issue
unset($_SESSION['mikanbox_mcp_new_key']);
?>
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
                    <form method="post" class="mt-10" data-ajax>
                        <?= csrfField() ?><input type="hidden" name="save_action" value="set_mcp_enabled">
                        <label><input type="checkbox" name="mcp_enabled" value="1" <?= mikanBoxMcpEnabled() ? 'checked' : '' ?>> <?= t('mcp_enable_label') ?></label>
                        <button type="submit" class="btn btn-gray btn-small"><?= t('btn_save') ?></button>
                    </form>
                    <?php $mcpUsedAt = mikanBoxMcpLastUsed($mcpDisplayKey); ?>
                    <p class="sub-text"><?= t('mcp_last_used_label') ?>: <span id="mcp-last-used"><?= $mcpUsedAt ? htmlspecialchars(gmdate('Y-m-d H:i', $mcpUsedAt) . ' UTC', ENT_QUOTES, 'UTF-8') : t('mcp_never_used') ?></span></p>
                    <?php $mcpHasKey = $mcpDisplayKey !== '';
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
                                <input type="text" id="mcp-key-display" value="<?= htmlspecialchars($mcpJustIssued) ?>" placeholder="<?= htmlspecialchars(t('mcp_key_hidden_placeholder'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                                <button type="button" class="btn btn-gray btn-small" onclick="navigator.clipboard.writeText(document.getElementById('mcp-key-display').value).then(()=>showToast('<?= t('msg_copied') ?>'))"><?= getIcon('copy') ?> <?= t('btn_copy') ?></button>
                            </div>
                            <small class="sub-text" id="mcp-key-once"<?= $mcpJustIssued === '' ? ' style="display:none;"' : '' ?>><?= t('mcp_key_once_note') ?></small>
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

<div class="section-container section-tight"><details class="section-accordion"><summary class="header section-header accordion-summary"><h2 class="accordion-title"><?= t('audit_heading') ?> <span class="accordion-arrow">▼</span></h2></summary><div class="editor-container editor-container-sub">
<?php if ((!empty($GLOBALS['mikanbox_audit_unavailable']) || !empty($_SESSION['mikanbox_audit_unavailable']))): ?><p role="alert"><?= t('audit_unavailable') ?></p><?php endif; ?>
<?php $auditGroups = ['important' => [], 'edit' => [], 'login' => []];
      $auditAll = array_reverse(mikanBoxAuditRecent());   // oldest first: "unusual" means not seen in any earlier record
      $auditUnusual = array_flip(mikanBoxAuditUnusual($auditAll));
      foreach (array_reverse($auditAll, true) as $auditIndex => $event) { $event['unusual'] = isset($auditUnusual[$auditIndex]); $auditGroups[mikanBoxAuditGroup((string)($event['event'] ?? ''))][] = $event; } ?>
<div class="audit-tabs">
<?php $n = 0; foreach ($auditGroups as $group => $events): $n++; ?><input type="radio" name="audit-tab" id="audit-tab-<?= $n ?>"<?= $n === 1 ? ' checked' : '' ?> aria-label="<?= htmlspecialchars(t('audit_tab_' . $group), ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?>
<div class="audit-tab-labels"><?php $n = 0; foreach ($auditGroups as $group => $events): $n++; ?><label for="audit-tab-<?= $n ?>"><?= t('audit_tab_' . $group) ?> (<?= count($events) ?>)</label><?php endforeach; ?></div>
<?php foreach ($auditGroups as $group => $events): ?><div class="audit-panel">
<?php if (!$events): ?><p><?= t('audit_empty') ?></p><?php else: ?>
<table><thead><tr><th><?= t('audit_time') ?></th><th><?= t('audit_action') ?></th><th><?= t('audit_actor') ?></th><th><?= t('audit_target') ?></th><th>IP</th></tr></thead><tbody>
<?php foreach ($events as $event): ?>
<tr<?= !empty($event['unusual']) ? ' class="audit-unusual" title="' . htmlspecialchars(t('audit_unusual_title'), ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?php foreach ([$event['at'] ?? '', t('audit_event_' . ($event['event'] ?? '')), $event['actor'] ?? '', mikanBoxAuditTargetText($event), $event['ip'] ?? ''] as $value): ?><td><?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?></td><?php endforeach; ?></tr>
<?php endforeach; ?></tbody></table><?php endif; ?></div><?php endforeach; ?>
</div></div></details></div>
