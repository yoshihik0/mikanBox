<?php defined('MIKANBOX') or die();
mikanBoxMcpKeyUpgrade();   // a key an older version stored in plain text is kept only as its fingerprint
$mcpKeys = mikanBoxMcpKeyList($settings);   // fingerprints only: a key is never shown again after it is issued
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
                <div class="panel-card">
                    <small class="sub-text sub-text-intro"><?= t('mcp_key_hint') ?></small>
                    <form method="post" class="mt-10" data-ajax>
                        <?= csrfField() ?><input type="hidden" name="save_action" value="set_mcp_enabled">
                        <label><input type="checkbox" name="mcp_enabled" value="1" <?= mikanBoxMcpEnabled() ? 'checked' : '' ?>> <?= t('mcp_enable_label') ?></label>
                        <button type="submit" class="btn btn-gray btn-small"><?= t('btn_save') ?></button>
                    </form>
                    <?php
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
                    <?php // The new key is shown here once, in place: reloading would collapse this accordion and hide it. ?>
                    <div id="mcp-key-issued" style="display:none;">
                        <div class="form-group mt-10">
                            <label for="mcp-key-display"><?= t('mcp_key_label') ?></label>
                            <div class="copy-row">
                                <input type="text" id="mcp-key-display" value="" readonly>
                                <button type="button" class="btn btn-gray btn-small" onclick="navigator.clipboard.writeText(document.getElementById('mcp-key-display').value).then(()=>showToast('<?= t('msg_copied') ?>'))"><?= getIcon('copy') ?> <?= t('btn_copy') ?></button>
                            </div>
                            <small class="sub-text" id="mcp-key-once"><?= t('mcp_key_once_note') ?></small>
                        </div>
                    </div>
                    <div id="mcp-key-list" class="mt-10">
                        <?php if (!$mcpKeys): ?>
                            <p class="sub-text"><?= t('mcp_key_none') ?></p>
                        <?php else: ?>
                            <table class="mcp-key-table">
                                <thead><tr><th><?= t('mcp_key_col_name') ?></th><th><?= t('mcp_key_col_created') ?></th><th><?= t('mcp_key_col_last_used') ?></th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($mcpKeys as $mcpKey):
                                    $mcpUsedAt = mikanBoxMcpLastUsed($mcpKey['id']);
                                    $mcpKeyName = $mcpKey['name'] !== '' ? $mcpKey['name'] : t('mcp_key_unnamed'); ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mcpKeyName, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= $mcpKey['created'] ? htmlspecialchars(gmdate('Y-m-d', $mcpKey['created']), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                        <td><?= $mcpUsedAt ? htmlspecialchars(gmdate('Y-m-d H:i', $mcpUsedAt) . ' UTC', ENT_QUOTES, 'UTF-8') : t('mcp_never_used') ?></td>
                                        <td>
                                            <form method="post" data-ajax data-refresh="#mcp-key-list" data-confirm="<?= htmlspecialchars(t('mcp_key_revoke_confirm', $mcpKeyName), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="save_action" value="revoke_mcp_key">
                                                <input type="hidden" name="mcp_key_id" value="<?= htmlspecialchars($mcpKey['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn btn-gray btn-small"><?= t('btn_revoke_mcp_key') ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    <form method="post" class="mt-10" id="mcp-key-generate" data-ajax data-refresh="#mcp-key-list">
                        <?= csrfField() ?>
                        <input type="hidden" name="save_action" value="generate_mcp_key">
                        <div class="form-group">
                            <label for="mcp-key-name"><?= t('mcp_key_name_label') ?></label>
                            <input type="text" id="mcp-key-name" name="mcp_key_name" maxlength="<?= MIKANBOX_MCP_KEY_NAME_MAX ?>" placeholder="<?= htmlspecialchars(t('mcp_key_name_placeholder'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" style="max-width:300px;">
                            <small class="sub-text"><?= t('mcp_key_split_hint') ?></small>
                        </div>
                        <button type="submit" class="btn btn-blue btn-small"><?= getIcon('sparkles') ?> <?= t('btn_generate_mcp_key') ?></button>
                    </form>
                    <script>
                    (() => {
                        const form = document.getElementById('mcp-key-generate');
                        if (!form) return;
                        form.addEventListener('mikan:done', (event) => {
                            const key = event.detail && event.detail.mcp_api_key;
                            if (!key) return;
                            document.getElementById('mcp-key-display').value = key;
                            document.getElementById('mcp-key-issued').style.display = '';
                            const name = document.getElementById('mcp-key-name');
                            if (name) name.value = '';
                        });
                    })();
                    </script>
                </div>
                </div>
            </details>
        </div>
    </div>

<div class="section-container section-tight"><details class="section-accordion"><summary class="header section-header accordion-summary"><h2 class="accordion-title"><?= t('audit_heading') ?> <span class="accordion-arrow">▼</span></h2></summary><div class="editor-container editor-container-sub">
<?php if ((!empty($GLOBALS['mikanbox_audit_unavailable']) || !empty($_SESSION['mikanbox_audit_unavailable']))): ?><p role="alert"><?= t('audit_unavailable') ?></p><?php endif; ?>
<?php $auditGroups = ['important' => [], 'edit' => [], 'login' => []];
      $auditAll = array_reverse(mikanBoxAuditRecent());   // oldest first: "unusual" means not seen in any earlier record
      $auditUnusual = array_flip(mikanBoxAuditUnusual($auditAll, function_exists('mikanBoxIntegrityUnusualAcceptedUntil') ? mikanBoxIntegrityUnusualAcceptedUntil() : ''));
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
