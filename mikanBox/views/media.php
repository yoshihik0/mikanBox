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

        <div class="media-grid">
            <?php
            $files = glob(MEDIA_DIR . '/*.{jpg,jpeg,png,gif,webp,svg,mp3,m4a,mp4}', GLOB_BRACE);
            if (empty($files)):
                echo "<p class='td-empty'>No media found.</p>";
            else:
                usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                foreach ($files as $file):
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
                        <form method="post" onsubmit="return confirm('<?= t('hint_confirm_delete') ?>')" class="inline">
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
                                    <input type="number" name="new_width" placeholder="W" class="resize-input">
                                    <span class="resize-separator">×</span>
                                    <input type="number" name="new_height" placeholder="H" class="resize-input">
                                </div>
                                <button type="submit" class="btn btn-sm btn-blue resize-submit" title="<?= t('btn_save') ?>"><?= getIcon('save') ?></button>
                            </form>
                        </details>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php 
                endforeach;
            endif; ?>
        </div>
    </div> <!-- /.section-container -->
    </div> <!-- /#media -->
