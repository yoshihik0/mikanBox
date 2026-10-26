<?php
// Devices and users (SQLite edition). Included by views/site.php.
// Wording and structure follow mikanBox-cloud's "Devices and users" section.
$me = mikanBoxCurrentDevice();
$e = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$when = static fn($ts) => date('Y-m-d H:i', (int)$ts);
$db = getDb();

$usersList = [];
try {
    $resUsers = $db->query("SELECT username, display_name FROM users ORDER BY username ASC");
    while ($resUsers && ($u = $resUsers->fetchArray(SQLITE3_ASSOC))) $usersList[] = $u;
} catch (Exception $ex) {}

$devices = $me ? mikanBoxDeviceList() : [];
$codes = $me ? mikanBoxDeviceCodes() : [];
$deviceNames = [];
foreach ($devices as $d) $deviceNames[$d['id']] = $d['name'];
$probation = $me ? mikanBoxDeviceOnProbation($me) : false;
$cellStyle = 'padding: 8px 4px; font-size: 0.85rem;';
$headStyle = 'padding: 8px 4px; font-size: 0.8rem; color: #475569;';
$headingStyle = 'font-size: 0.95rem; margin: 18px 0 12px; color: var(--text); border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;';
$boxStyle = 'margin-top: 15px; margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fafafa;';
$boxSummaryStyle = 'padding: 10px 15px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; border-bottom: none; background: transparent;';
$boxBodyStyle = 'padding: 15px; border-top: 1px solid #e2e8f0; background: #fff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;';
?>
    <!-- Devices and Users Section -->
    <div id="users-mgmt" class="section-large-bottom">
        <div class="section-container section-tight">
            <details class="section-accordion" open>
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= $e(t('device_section_title')) ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <?php if (!empty($_SESSION['device_code_flash'])): ?>
                        <div role="status" style="background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 8px; padding: 14px 16px; margin-bottom: 18px;">
                            <div style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 1.6rem; letter-spacing: 0.12em; font-weight: 700; user-select: all;"><?= $e($_SESSION['device_code_flash']) ?></div>
                            <div class="sub-text" style="margin-top: 6px;"><?= $e(t('device_code_panel_note', MIKANBOX_DEVICE_CODE_HOURS)) ?></div>
                        </div>
                        <?php unset($_SESSION['device_code_flash']); ?>
                    <?php endif; ?>
                    <p class="sub-text sub-text-intro"><?= $e(t('device_section_intro')) ?></p>
                    <?php if ($probation): ?>
                        <p class="error" role="status"><?= $e(t('device_probation_notice', MIKANBOX_DEVICE_PROBATION_DAYS)) ?></p>
                    <?php endif; ?>

                    <h3 style="<?= $headingStyle ?>"><?= $e(t('device_list_title')) ?></h3>
                    <table class="table-clean" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; text-align: left;">
                                <th style="<?= $headStyle ?>"><?= $e(t('device_col_device')) ?></th>
                                <th style="<?= $headStyle ?>"><?= $e(t('device_col_user')) ?></th>
                                <th style="<?= $headStyle ?>"><?= $e(t('device_col_added')) ?></th>
                                <th style="<?= $headStyle ?>"><?= $e(t('device_col_last_used')) ?></th>
                                <th style="<?= $headStyle ?>"><?= $e(t('device_col_expires')) ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $d): $isMe = $me && $d['id'] === $me['id']; ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="<?= $cellStyle ?>"><?= $e($d['name']) ?><?php if ($isMe): ?> <span class="sub-text">(<?= $e(t('device_this_device')) ?>)</span><?php endif; ?></td>
                                    <td style="<?= $cellStyle ?>"><code><?= $e($d['username']) ?></code></td>
                                    <td style="<?= $cellStyle ?>"><?= $e($when($d['created_at'])) ?></td>
                                    <td style="<?= $cellStyle ?>"><?= $e($when($d['last_used_at'])) ?></td>
                                    <td style="<?= $cellStyle ?>"><?= $e($when($d['expires_at'])) ?></td>
                                    <td style="padding: 8px 4px; text-align: right;">
                                        <?php if ($isMe || !$probation): ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm(<?= $e(json_encode($isMe ? t('device_confirm_stop_self') : t('device_confirm_stop_other'))) ?>)">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="save_action" value="device_revoke">
                                                <input type="hidden" name="device_id" value="<?= $e($d['id']) ?>">
                                                <button type="submit" class="btn btn-red btn-small" style="padding: 2px 6px; font-size: 0.7rem;"><?= $e(t('device_btn_stop')) ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Add a device -->
                    <details class="section-accordion" style="<?= $boxStyle ?>">
                        <summary class="header section-header accordion-summary" style="<?= $boxSummaryStyle ?>">
                            <h3 style="font-size: 0.95rem; margin: 0; color: var(--text); display: inline-flex; align-items: center; gap: 8px; pointer-events: none;">
                                <?= getIcon('add') ?> <?= $e(t('device_add_summary')) ?>
                            </h3>
                            <span class="accordion-arrow" style="font-size: 0.75rem; opacity: 0.5;">▼</span>
                        </summary>
                        <div style="<?= $boxBodyStyle ?>">
                            <p class="sub-text"><?= $e(t('device_add_intro')) ?></p>
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="save_action" value="device_code_create">
                                <button type="submit" class="btn btn-blue btn-small"><?= getIcon('add') ?> <?= $e(t('device_btn_issue_code')) ?></button>
                            </form>
                            <h4 style="margin: 20px 0 10px; font-size: 0.9rem;"><?= $e(t('device_invite_title')) ?></h4>
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="save_action" value="device_code_create">
                                <input type="hidden" name="invite" value="1">
                                <div class="form-group">
                                    <label><?= $e(t('hint_username_rules')) ?></label>
                                    <input type="text" name="username" autocomplete="off" style="max-width:300px;" required pattern="[A-Za-z0-9_\-]{1,80}" placeholder="<?= $e(t('placeholder_username_eg')) ?>">
                                </div>
                                <div class="form-group">
                                    <label><?= $e(t('label_display_name')) ?></label>
                                    <input type="text" name="display_name" style="max-width:300px;" required maxlength="120" placeholder="<?= $e(t('placeholder_display_name_eg')) ?>">
                                </div>
                                <button type="submit" class="btn btn-blue btn-small"><?= getIcon('add') ?> <?= $e(t('device_btn_invite')) ?></button>
                            </form>
                        </div>
                    </details>

                    <?php if ($codes): ?>
                        <h3 style="<?= $headingStyle ?>"><?= $e(t('device_codes_title')) ?></h3>
                        <table class="table-clean" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                            <thead>
                                <tr style="border-bottom: 1px solid #e2e8f0; text-align: left;">
                                    <th style="<?= $headStyle ?>"><?= $e(t('device_col_user')) ?></th>
                                    <th style="<?= $headStyle ?>"><?= $e(t('device_col_issued_by')) ?></th>
                                    <th style="<?= $headStyle ?>"><?= $e(t('device_col_expires')) ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($codes as $c): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="<?= $cellStyle ?>"><code><?= $e($c['username']) ?></code><?php if ($c['display_name'] !== ''): ?> (<?= $e(t('device_invite_mark')) ?>)<?php endif; ?></td>
                                        <td style="<?= $cellStyle ?>"><?= $e($deviceNames[$c['created_by']] ?? '—') ?></td>
                                        <td style="<?= $cellStyle ?>"><?= $e($when($c['expires_at'])) ?></td>
                                        <td style="padding: 8px 4px; text-align: right;">
                                            <form method="post" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="save_action" value="device_code_cancel">
                                                <input type="hidden" name="code_hash" value="<?= $e($c['code_hash']) ?>">
                                                <button type="submit" class="btn btn-gray btn-small" style="padding: 2px 6px; font-size: 0.7rem;"><?= $e(t('device_btn_cancel_code')) ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <form method="post" style="display: flex; align-items: center; gap: 10px; margin: 10px 0 20px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="save_action" value="device_expiry">
                        <label><?= $e(t('device_expiry_label')) ?>
                            <select name="days">
                                <?php foreach (MIKANBOX_DEVICE_EXPIRY_CHOICES as $days): ?>
                                    <option value="<?= $days ?>"<?= $days === mikanBoxDeviceExpiryDays() ? ' selected' : '' ?>><?= $e(t('device_days', $days)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit" class="btn btn-gray btn-small"><?= $e(t('btn_save')) ?></button>
                    </form>

                    <!-- User List -->
                    <div id="users-list-wrap">
                        <h3 style="<?= $headingStyle ?>"><?= $e(t('device_users_title')) ?></h3>
                        <table class="table-clean" style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
                            <thead>
                                <tr style="border-bottom: 1px solid #e2e8f0; text-align: left;">
                                    <th style="<?= $headStyle ?>"><?= $e(t('label_username')) ?></th>
                                    <th style="<?= $headStyle ?>"><?= $e(t('label_display_name')) ?></th>
                                    <th style="<?= $headStyle ?> text-align: right;"><?= $e(t('label_operation_action')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersList as $u): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="<?= $cellStyle ?>"><code><?= $e($u['username']) ?></code></td>
                                        <td style="<?= $cellStyle ?>"><?= $e($u['display_name']) ?></td>
                                        <td style="padding: 8px 4px; text-align: right;">
                                            <?php if ($u['username'] !== ($_SESSION['admin_username'] ?? '') && count($usersList) > 1): ?>
                                                <form method="post" style="display: inline;" class="delete-user-form" data-display-name="<?= $e($u['display_name']) ?>">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="save_action" value="delete_user">
                                                    <input type="hidden" name="username" value="<?= $e($u['username']) ?>">
                                                    <button type="submit" class="btn btn-red btn-small" style="padding: 2px 6px; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 3px;"><?= getIcon('delete') ?> <?= $e(t('btn_delete')) ?></button>
                                                </form>
                                            <?php else: ?>
                                                <span style="font-size: 0.75rem; color: #94a3b8;"><?= $e($u['username'] === ($_SESSION['admin_username'] ?? '') ? t('label_logged_in') : t('label_cannot_delete')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Account settings of the signed-in user -->
                    <details class="section-accordion" style="<?= $boxStyle ?>">
                        <summary class="header section-header accordion-summary" style="<?= $boxSummaryStyle ?>">
                            <h3 style="font-size: 0.95rem; margin: 0; color: var(--text); display: inline-flex; align-items: center; gap: 8px; pointer-events: none;">
                                <?= getIcon('save') ?> <?= $e(t('device_account_settings')) ?>
                            </h3>
                            <span class="accordion-arrow" style="font-size: 0.75rem; opacity: 0.5;">▼</span>
                        </summary>
                        <div style="<?= $boxBodyStyle ?>">
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="save_action" value="update_my_account">
                                <div class="form-group">
                                    <label><?= $e(t('hint_username_rules')) ?></label>
                                    <input type="text" name="username" autocomplete="off" style="max-width:300px;" required value="<?= $e($_SESSION['admin_username'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label><?= $e(t('label_display_name')) ?></label>
                                    <input type="text" name="display_name" style="max-width:300px;" required value="<?= $e($_SESSION['admin_display_name'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-blue btn-small"><?= getIcon('save') ?> <?= $e(t('btn_update_settings')) ?></button>
                            </form>
                        </div>
                    </details>

                    <p class="sub-text"><?= $e(t('device_recovery_note')) ?></p>
                </div>
            </details>
        </div>
    </div>
