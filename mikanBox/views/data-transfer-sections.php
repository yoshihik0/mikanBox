<?php
/**
 * The site screen's "Data management": the site's figures, Save, Read and the public site export, the same
 * in the flat and SQLite editions (the Cloud edition draws the same in src/admin/import-view.ts).
 */
$transferTexts = [];
foreach (['transfer_reading', 'transfer_nothing', 'transfer_not_zip', 'transfer_bad_json', 'transfer_confirm_title', 'transfer_confirm_replace', 'transfer_btn_replace', 'transfer_sending_data', 'transfer_sending_media', 'transfer_publishing', 'transfer_publish_failed', 'transfer_done', 'transfer_refused', 'transfer_media_failed', 'transfer_build_hint'] as $key) $transferTexts[$key] = t($key);
$hasDatabase = function_exists('mikanBoxDownloadDatabase');
?>
            <ul class="transfer-stats">
                <?php foreach (mikanBoxTransferStats() as $key => $value): ?>
                <li><?= htmlspecialchars(t('transfer_stats_' . $key)) ?> <strong><?= htmlspecialchars((string)$value) ?></strong></li>
                <?php endforeach; ?>
            </ul>
            <section id="transfer-export" class="data-tool-card">
                <h2><?= getIcon('download') ?> <?= htmlspecialchars(t('transfer_export_title')) ?></h2>
                <p><?= htmlspecialchars(t('transfer_export_hint')) ?></p>
                <form method="post" action="admin.php" class="transfer-export-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_action" value="transfer_export">
                    <div class="flex-row flex-row-wrap items-center">
                        <label class="transfer-field"><?= htmlspecialchars(t('transfer_contents_label')) ?>
                            <select name="contents">
                                <option value="both"><?= htmlspecialchars(t('transfer_contents_both')) ?></option>
                                <option value="data"><?= htmlspecialchars(t('transfer_contents_data')) ?></option>
                                <option value="media"><?= htmlspecialchars(t('transfer_contents_media')) ?></option>
                                <?php if ($hasDatabase): ?><option value="database"><?= htmlspecialchars(t('transfer_contents_database')) ?></option><?php endif; ?>
                            </select>
                        </label>
                        <?php if (mikanBoxTransferHasHistory()): ?>
                        <label class="transfer-field transfer-revisions"><input type="checkbox" name="include_revisions" value="1"> <?= htmlspecialchars(t('transfer_include_revisions')) ?></label>
                        <?php endif; ?>
                        <label class="transfer-field transfer-match-filter" title="<?= htmlspecialchars(t('transfer_match_filter_hint')) ?>"><input type="checkbox" name="match_filter" value="1"> <?= htmlspecialchars(t('transfer_match_filter')) ?></label>
                        <input type="hidden" name="filter_json" value="">
                        <button type="submit" class="btn btn-gray btn-small"><?= getIcon('download') ?> <?= htmlspecialchars(t('transfer_btn_export')) ?></button>
                    </div>
                </form>
            </section>

            <section id="transfer-import" class="data-tool-card">
                <h2><?= getIcon('upload') ?> <?= htmlspecialchars(t('transfer_import_title')) ?></h2>
                <p><?= htmlspecialchars(t('transfer_import_hint')) ?></p>
                <form data-transfer="import" data-messages="<?= htmlspecialchars(json_encode($transferTexts, JSON_UNESCAPED_UNICODE)) ?>">
                    <?= csrfField() ?>
                    <input type="file" name="files" multiple required accept=".zip,.xml,.jpg,.jpeg,.png,.gif,.webp,.svg,.mp3,.m4a,.mp4,.js,.css">
                    <fieldset class="transfer-modes">
                        <legend><?= htmlspecialchars(t('transfer_mode_label')) ?></legend>
                        <label><input type="radio" name="mode" value="add" checked> <?= htmlspecialchars(t('transfer_mode_add')) ?></label>
                        <label><input type="radio" name="mode" value="overwrite"> <?= htmlspecialchars(t('transfer_mode_overwrite')) ?></label>
                        <label><input type="radio" name="mode" value="replace"> <?= htmlspecialchars(t('transfer_mode_replace')) ?></label>
                    </fieldset>
                    <p class="sub-text"><?= htmlspecialchars(t('transfer_import_note')) ?></p>
                    <button type="submit" class="btn btn-blue btn-small"><?= getIcon('upload') ?> <?= htmlspecialchars(t('transfer_btn_import')) ?></button>
                    <span data-transfer-status role="status"></span>
                </form>
            </section>

            <section id="site-export" class="data-tool-card">
                <h2><?= getIcon('download') ?> <?= htmlspecialchars(t('site_export_title')) ?></h2>
                <p><?= htmlspecialchars(t('site_export_hint')) ?></p>
                <form method="post" action="admin.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_action" value="transfer_site_export">
                    <div class="flex-row flex-row-wrap items-center">
                        <label class="transfer-field"><input type="checkbox" name="relative" value="1"> <?= htmlspecialchars(t('site_export_relative')) ?></label>
                        <button type="submit" class="btn btn-gray btn-small"><?= getIcon('download') ?> <?= htmlspecialchars(t('site_export_btn')) ?></button>
                    </div>
                </form>
            </section>
<?= mikanBoxInlineAdminScript('zip.js') ?>
<?= mikanBoxInlineAdminScript('data-transfer.js') ?>
