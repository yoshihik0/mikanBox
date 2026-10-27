<?php
// Users and their devices (SQLite edition). Included by views/site.php.
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
$devicesByUser = [];
foreach ($devices as $d) $devicesByUser[$d['username']][] = $d;
$codes = $me ? mikanBoxDeviceCodes() : [];
$deviceNames = [];
foreach ($devices as $d) $deviceNames[$d['id']] = $d['name'];
$probation = $me ? mikanBoxDeviceOnProbation($me) : false;
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

                    <!-- Users, each with their devices -->
                    <div id="users-list-wrap">
                        <h3 style="<?= $headingStyle ?>"><?= $e(t('device_users_devices_title')) ?></h3>
                        <?php foreach ($usersList as $u): $isSelf = $u['username'] === ($_SESSION['admin_username'] ?? ''); ?>
                            <div class="account-user">
                                <div class="account-user-head">
                                    <span class="account-user-name"><?= $e($u['display_name']) ?><code><?= $e($u['username']) ?></code></span>
                                    <?php if (!$isSelf && count($usersList) > 1 && !$probation): ?>
                                        <form method="post" class="delete-user-form" data-display-name="<?= $e($u['display_name']) ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="save_action" value="delete_user">
                                            <input type="hidden" name="username" value="<?= $e($u['username']) ?>">
                                            <button type="submit" class="btn btn-red btn-small"><?= $e(t('btn_delete')) ?></button>
                                        </form>
                                    <?php elseif ($isSelf): ?>
                                        <span class="sub-text"><?= $e(t('label_logged_in')) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($devicesByUser[$u['username']])): ?>
                                    <div class="account-table-wrap">
                                        <table class="account-table">
                                            <thead>
                                                <tr>
                                                    <th><?= $e(t('device_col_device')) ?></th>
                                                    <th><?= $e(t('device_col_added')) ?></th>
                                                    <th><?= $e(t('device_col_last_used')) ?></th>
                                                    <th><?= $e(t('device_col_expires')) ?></th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($devicesByUser[$u['username']] as $d): $isMe = $me && $d['id'] === $me['id']; ?>
                                                    <tr>
                                                        <td><?= $e($d['name']) ?><?php if ($isMe): ?> <span class="sub-text">(<?= $e(t('device_this_device')) ?>)</span><?php endif; ?></td>
                                                        <td><?= $e($when($d['created_at'])) ?></td>
                                                        <td><?= $e($when($d['last_used_at'])) ?></td>
                                                        <td><?= $e($when($d['expires_at'])) ?></td>
                                                        <td class="account-actions">
                                                            <?php if ($isMe || !$probation): ?>
                                                                <form method="post" class="inline" onsubmit="return confirm(<?= $e(json_encode($isMe ? t('device_confirm_stop_self') : t('device_confirm_stop_other'))) ?>)">
                                                                    <?= csrfField() ?>
                                                                    <input type="hidden" name="save_action" value="device_revoke">
                                                                    <input type="hidden" name="device_id" value="<?= $e($d['id']) ?>">
                                                                    <button type="submit" class="btn btn-red btn-small"><?= $e(t('device_btn_stop')) ?></button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="account-user-empty"><?= $e(t('device_no_devices')) ?></p>
                                <?php endif; ?>
                                <?php if ($isSelf): ?>
                                    <form method="post" class="account-name-form">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="save_action" value="update_my_account">
                                        <label for="my-display-name"><?= $e(t('device_display_name_label')) ?></label>
                                        <input type="text" id="my-display-name" name="display_name" required maxlength="120" value="<?= $e($u['display_name']) ?>">
                                        <button type="submit" class="btn btn-gray btn-small"><?= $e(t('btn_save')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

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
                            <p class="sub-text"><?= $e(t('device_invite_admin_intro')) ?></p>
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="save_action" value="device_code_create">
                                <input type="hidden" name="invite" value="1">
                                <button type="submit" class="btn btn-blue btn-small"><?= getIcon('add') ?> <?= $e(t('device_btn_invite')) ?></button>
                            </form>
                        </div>
                    </details>

                    <?php if ($codes): ?>
                        <h3 style="<?= $headingStyle ?>"><?= $e(t('device_codes_title')) ?></h3>
                        <div class="account-table-wrap">
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th><?= $e(t('device_col_user')) ?></th>
                                        <th><?= $e(t('device_col_issued_by')) ?></th>
                                        <th><?= $e(t('device_col_expires')) ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($codes as $c): ?>
                                        <tr>
                                            <td><?php if ($c['username'] === ''): ?><?= $e(t('device_invite_mark')) ?><?php else: ?><code><?= $e($c['username']) ?></code><?php if ($c['display_name'] !== ''): ?> (<?= $e(t('device_invite_mark')) ?>)<?php endif; ?><?php endif; ?></td>
                                            <td><?= $e($deviceNames[$c['created_by']] ?? '—') ?></td>
                                            <td><?= $e($when($c['expires_at'])) ?></td>
                                            <td class="account-actions">
                                                <form method="post" class="inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="save_action" value="device_code_cancel">
                                                    <input type="hidden" name="code_hash" value="<?= $e($c['code_hash']) ?>">
                                                    <button type="submit" class="btn btn-gray btn-small"><?= $e(t('device_btn_cancel_code')) ?></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="device-expiry-form">
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

                    <p class="sub-text"><?= $e(t('device_recovery_note')) ?></p>
                </div>
            </details>
        </div>
    </div>
