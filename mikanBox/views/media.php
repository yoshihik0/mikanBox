<?php defined('MIKANBOX') or die(); ?>

    <div id="media" class="section-anchor">
        <div class="section-container section-large-bottom">
            <div class="header">
            <h1><?= getIcon('media') ?> <?= t('nav_media') ?><a href="<?= $helpFile ?>#media-mgmt" target="_blank" class="manual-link"><?= t('admin_help') ?></a></h1>
        </div>
        
        <div class="editor-container" id="media-upload-area">
            <form method="post" enctype="multipart/form-data" id="upload-form">
                <input type="hidden" name="save_action" value="upload_media">
                <?= csrfField() ?>
                <div class="form-group mb-0">
                    <div class="flex-row items-center">
                        <input type="file" name="image" id="file-input" accept="image/*,video/*,audio/*" required>
                        <button type="submit" class="btn btn-blue" id="upload-btn"><?= getIcon('upload') ?> <?= t('btn_upload') ?></button>
                    </div>
                    <div class="upload-info">
                        <?= t('media_support_types') ?>: jpg, png, gif, webp, svg, mp3, m4a, mp4<br>
                        <?= t('media_max_size') ?>: <?= ini_get('upload_max_filesize') ?> / <?= t('media_post_limit') ?>: <?= ini_get('post_max_size') ?> (<?= t('media_server_limit') ?>)<br>
                        <br>
                        <?= t('hint_media_display') ?><br>
                        <?= t('hint_media_resize') ?>
                    </div>
                </div>
            </form>
        </div>

        <div id="media-list-wrap">
        <?php
        $selectedCat = $_GET['cat'] ?? '';
        $ignoreMediaCat = isset($_GET['media_all']) && $_GET['media_all'] === '1';
        ?>

        <?php if ($selectedCat !== ''): ?>
            <div class="media-filter-status" style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px; justify-content: space-between; background: var(--bg-card); padding: 10px 15px; border-radius: 6px; border: 1px solid var(--border-color);">
                <div style="font-size: 0.9rem; color: var(--text-sub); display: flex; align-items: center; gap: 5px;">
                    <?php if ($ignoreMediaCat): ?>
                        <?= getIcon('visibility') ?> <?= t('all_media_shown_hint', htmlspecialchars($selectedCat)) ?>
                    <?php else: ?>
                        <?= getIcon('filter_list') ?> <?= t('media_filtered_hint', htmlspecialchars($selectedCat)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($ignoreMediaCat): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['media_all' => 0])) ?>" class="btn btn-sm btn-blue media-filter-toggle-btn" data-media-all="0" style="font-size: 0.8rem; padding: 4px 10px; display: inline-flex; align-items: center; gap: 5px;">
                            <?= getIcon('filter_list') ?> <?= t('btn_apply_filter') ?>
                        </a>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['media_all' => 1])) ?>" class="btn btn-sm btn-orange media-filter-toggle-btn" data-media-all="1" style="font-size: 0.8rem; padding: 4px 10px; display: inline-flex; align-items: center; gap: 5px;">
                            <?= getIcon('visibility_off') ?> <?= t('btn_ignore_filter') ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="media-grid">
            <?php
            $files = glob(MEDIA_DIR . '/*.{jpg,jpeg,png,gif,webp,svg,mp3,m4a,mp4}', GLOB_BRACE);
            if (empty($files)):
                echo "<p class='td-empty'>No media found.</p>";
            else:
                usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                
                // Category Filtering (Multiple prefixes & g_ global images)
                if ($selectedCat !== '' && !$ignoreMediaCat) {
                    $files = array_filter($files, function($file) use ($selectedCat) {
                        $fname = basename($file);
                        if (strpos($fname, 'g_') === 0) return true; // Always show global
                        $parts = explode('_', $fname);
                        array_pop($parts); // Remove file extension and name end
                        return in_array($selectedCat, $parts);
                    });
                }
                
                // Pagination
                $itemsPerPage = isset($settings['media_per_page']) ? (int)$settings['media_per_page'] : 100;
                $totalItems = count($files);
                $totalPages = max(1, ceil($totalItems / $itemsPerPage));
                $currentPage = isset($_GET['p_media']) ? max(1, min($totalPages, (int)$_GET['p_media'])) : 1;
                $offset = ($currentPage - 1) * $itemsPerPage;
                $filesPage = array_slice($files, $offset, $itemsPerPage);

                foreach ($filesPage as $file):
                    $fname = basename($file);
                    $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                    $webPath = '../media/' . $fname;
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                    $canResize = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    $isAudio = in_array($ext, ['mp3', 'm4a']);
                    $isVideo = ($ext === 'mp4');
                    
                    $dims = "";
                    if ($isImage && $ext !== 'svg') {
                        $info = @getimagesize($file);
                        $dims = $info ? "{$info[0]}x{$info[1]}" : "";
                    }
            ?>
            <div class="media-card">
                <div class="media-card-thumb">
                    <?php if ($isImage): ?>
                        <img src="<?= $webPath ?>" alt="<?= $fname ?>" loading="lazy">
                    <?php elseif ($isVideo): ?>
                        <div class="media-card-icon"><?= getIcon('video') ?></div>
                    <?php elseif ($isAudio): ?>
                        <div class="media-card-icon"><?= getIcon('audio') ?></div>
                    <?php endif; ?>
                </div>
                <div class="media-card-body">
                    <div class="media-card-title hint-click-to-copy" onclick="copyToClipboard('<?= htmlspecialchars($fname) ?>')" title="<?= t('hint_click_to_copy') ?>">
                        <?= htmlspecialchars($fname) ?>
                    </div>
                    
                    <!-- Meta + Delete Row -->
                    <div class="media-meta-row">
                        <div class="media-card-meta media-meta-label">
                            <?= strtoupper($ext) ?> <?= $dims ? "($dims)" : "" ?>
                        </div>
                        <form method="post" class="inline">
                            <input type="hidden" name="save_action" value="delete_media">
                            <input type="hidden" name="filename" value="<?= htmlspecialchars($fname) ?>">
                            <?= csrfField() ?>
                            <button type="submit" class="media-delete-btn" title="<?= t('btn_delete') ?>">
                                <?= getIcon('delete') ?> <?= t('btn_delete') ?>
                            </button>
                        </form>
                    </div>

                    <!-- Action Area: Resize Only -->
                    <div class="media-card-action-container media-action-border">
                        <?php if ($canResize): ?>
                        <details class="resize-details">
                            <summary class="resize-summary">
                                <span class="resize-arrow">▼</span><?= t('btn_resize') ?>...
                            </summary>
                            <form method="post" class="resize-form">
                                <input type="hidden" name="save_action" value="resize_media">
                                <input type="hidden" name="filename" value="<?= htmlspecialchars($fname) ?>">
                                <?= csrfField() ?>
                                <div class="resize-input-group">
                                    <input type="text" name="new_width" placeholder="W" class="resize-input" inputmode="numeric" pattern="[0-9]*">
                                    <span class="resize-separator">×</span>
                                    <input type="text" name="new_height" placeholder="H" class="resize-input" inputmode="numeric" pattern="[0-9]*">
                                </div>
                                <button type="submit" class="btn btn-sm btn-blue resize-submit" title="<?= t('btn_save') ?>"><?= getIcon('save') ?></button>
                            </form>
                        </details>
                        <?php endif; ?>

                        <details class="resize-details" style="margin-top: 5px;">
                            <summary class="resize-summary">
                                <span class="resize-arrow">▼</span><?= t('btn_rename') ?>...
                            </summary>
                            <form method="post" class="rename-form" style="display: flex; gap: 5px; margin-top: 5px;">
                                <input type="hidden" name="save_action" value="rename_media">
                                <input type="hidden" name="old_filename" value="<?= htmlspecialchars($fname) ?>">
                                <?= csrfField() ?>
                                <input type="text" name="new_filename" value="<?= htmlspecialchars($fname) ?>" required class="resize-input" style="flex: 1; font-family: monospace; font-size: 0.8rem; height: 24px; padding: 2px 5px;">
                                <button type="submit" class="btn btn-sm btn-blue" style="height: 24px; width: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="保存"><?= getIcon('save') ?></button>
                            </form>
                        </details>
                    </div>
                </div>
            </div>
            <?php 
                endforeach;
            endif; ?>
        </div>

        <?php if (isset($totalPages) && $totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?p_media=<?= $i ?><?= $selectedCat !== '' ? '&cat=' . urlencode($selectedCat) : '' ?>#media" class="pagination-link <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        </div> <!-- /#media-list-wrap -->
    </div> <!-- /.section-container -->
    </div> <!-- /#media -->
