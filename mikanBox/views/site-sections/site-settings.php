<?php defined('MIKANBOX') or die(); ?>
<?php
$siteIdentityId = trim((string)($settings['site_id'] ?? ''));
if ($siteIdentityId === '') {
    $siteIdentityId = 'site-' . bin2hex(random_bytes(8));
}
$siteEnvironment = (string)($settings['site_environment'] ?? 'unspecified');
?>
    <div id="settings">
        <div class="section-container section-tight">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('nav_settings_title') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <form method="post" class="site-settings-form">
                        <input type="hidden" name="save_action" value="save_settings">
                        <?= csrfField() ?>
                        <div class="grid-2col">
                            <div class="site-identity-row grid-span-2">
                            <div class="form-group">
                                <label><?= t('label_site_name') ?></label>
                                <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name']??'') ?>">
                            </div>
                            <div class="form-group">
                                <label><?= t('label_site_id') ?></label>
                                <input type="text" name="site_id" value="<?= htmlspecialchars($siteIdentityId) ?>" readonly>
                                <small class="sub-text"><?= t('hint_site_id') ?></small>
                            </div>
                            </div>
                            <div class="form-group">
                                <label><?= t('label_site_url') ?></label>
                                <input type="url" name="site_url" value="<?= htmlspecialchars($settings['site_url'] ?? '') ?>" placeholder="https://example.com">
                                <small class="sub-text"><?= t('hint_site_url') ?></small>
                            </div>
                            <div class="form-group">
                                <label><?= t('label_site_environment') ?></label>
                                <select name="site_environment">
                                    <?php foreach (['production', 'staging', 'development', 'local', 'unspecified'] as $environment): ?>
                                        <option value="<?= $environment ?>" <?= $siteEnvironment === $environment ? 'selected' : '' ?>><?= t('site_environment_' . $environment) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="sub-text"><?= t('hint_site_environment') ?></small>
                            </div>
                            <div class="form-group grid-span-2">
                                <label><?= t('label_site_desc') ?></label>
                                <textarea name="description" rows="3" class="textarea-min-80"><?= htmlspecialchars($settings['description']??'') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label><?= t('label_site_keywords') ?></label>
                                <input type="text" name="keywords" value="<?= htmlspecialchars($settings['keywords']??'') ?>">
                            </div>
                            <div class="form-group">
                                <label><?= t('label_site_ogp') ?></label>
                                <input type="text" name="ogp_image" value="<?= htmlspecialchars($settings['ogp_image']??'') ?>">
                                <small class="sub-text"><?= t('recommended_size') ?></small>
                            </div>
                            <div class="form-group">
                                <label><?= t('label_pages_per_page') ?></label>
                                <input type="number" name="pages_per_page" value="<?= htmlspecialchars($settings['pages_per_page']??'30') ?>" min="1">
                            </div>
                            <div class="form-group">
                                <label><?= t('label_media_per_page') ?></label>
                                <input type="number" name="media_per_page" value="<?= htmlspecialchars($settings['media_per_page']??'100') ?>" min="1">
                            </div>
                            <div class="form-group grid-span-2" style="display: none;">
                                <label><?= t('label_register_cat') ?> <?= t('hint_comma_separated') ?></label>
                                <input type="text" name="category_candidates" value="<?= htmlspecialchars($settings['category_candidates']??'') ?>" placeholder="coffee, sample, gallery_item, dummy, news, nav, test">
                                <small class="sub-text"><?= t('hint_register_cat') ?></small>
                            </div>
                        </div>
                        <div class="form-group mt-15">
                            <h3><?= t('label_common_loading') ?></h3>
                            <?php
                            $trackingHeadCode = (string)($settings['tracking_head_code'] ?? '');
                            $trackingBodyCode = (string)($settings['tracking_body_code'] ?? '');
                            $trackingLegacyNotice = '';
                            if ($trackingHeadCode === '' && $trackingBodyCode === '' && empty($settings['tracking_id']) && !empty($settings['tracking_component'])) {
                                $trackingLegacyHtml = mikanBoxTrackingLegacyHtml($settings);
                                if ($trackingLegacyHtml !== null) $trackingHeadCode = $trackingLegacyHtml;
                                elseif (loadData(COMPONENTS_DIR, (string)$settings['tracking_component'])) $trackingLegacyNotice = (string)$settings['tracking_component'];
                            }
                            $trackingCodeInUse = trim($trackingHeadCode) !== '' || trim($trackingBodyCode) !== '';
                            ?>
                            <label><?= t('label_tracking_id') ?></label>
                            <input type="text" name="tracking_id" id="tracking-id" value="<?= htmlspecialchars($settings['tracking_id'] ?? '') ?>" placeholder="G-XXXXXXXXXX / GTM-XXXXXXX" pattern="([Gg][Tt][Mm]|[Gg])-[A-Za-z0-9]{4,}" <?= $trackingCodeInUse ? 'readonly' : '' ?>>
                            <small class="sub-text sub-text-block" id="tracking-id-hint"><?= t($trackingCodeInUse ? 'tracking_id_overridden' : 'tracking_id_hint') ?></small>
                            <?php if ($trackingLegacyNotice !== ''): ?>
                            <small class="sub-text sub-text-block"><?= t('tracking_legacy_notice', htmlspecialchars($trackingLegacyNotice)) ?></small>
                            <?php endif; ?>
                            <details class="mt-10" <?= $trackingCodeInUse ? 'open' : '' ?>>
                                <summary><?= t('label_tracking_code') ?></summary>
                                <label class="sub-label"><?= t('label_tracking_head_code') ?></label>
                                <textarea name="tracking_head_code" class="tracking-code" rows="5" spellcheck="false"><?= htmlspecialchars($trackingHeadCode) ?></textarea>
                                <label class="sub-label"><?= t('label_tracking_body_code') ?></label>
                                <textarea name="tracking_body_code" class="tracking-code" rows="3" spellcheck="false"><?= htmlspecialchars($trackingBodyCode) ?></textarea>
                                <small class="sub-text sub-text-block"><?= t('tracking_code_hint') ?></small>
                            </details>
                            <script>
                            (function () {
                                var id = document.getElementById('tracking-id'), hint = document.getElementById('tracking-id-hint');
                                var codes = document.querySelectorAll('textarea.tracking-code');
                                var messages = <?= json_encode(['idle' => t('tracking_id_hint'), 'busy' => t('tracking_id_overridden')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
                                function sync() {
                                    var used = Array.prototype.some.call(codes, function (c) { return c.value.trim() !== ''; });
                                    id.readOnly = used;
                                    hint.textContent = used ? messages.busy : messages.idle;
                                }
                                codes.forEach(function (c) { c.addEventListener('input', sync); });
                            })();
                            </script>
                        </div>
                        <button type="submit" class="btn btn-blue mt-10"><?= getIcon('save') ?> <?= t('btn_save') ?></button>
                    </form>
                </div>
            </details>
        </div>
    </div>
