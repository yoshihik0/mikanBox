<?php defined('MIKANBOX') or die(); ?>

    <div id="pages" class="section-anchor">
        <div class="section-container section-large-bottom">
            <div class="header">
            <h1><?= getIcon('page') ?> <?= t('nav_pages') ?><a href="<?= $helpFile ?>#page-mgmt" target="_blank" class="manual-link"><?= t('admin_help') ?></a></h1>
            <a href="?view=pages&new=1#page-editor" class="btn btn-blue"><?= getIcon('add') ?> <?= t('btn_create_new') ?></a>
        </div>
        <div class="table-responsive">
        <form id="ssg-build-form" method="post">
        <?= csrfField() ?>
        <table>
            <tr><th class="td-narrow"><?= t('label_operation') ?></th><th class="td-narrow"><?= t('label_status') ?></th><th class="td-narrow"><?= t('label_slug') ?></th><th><?= t('label_title') ?></th><th class="td-narrow"><?= t('label_updated_at') ?></th><th class="td-narrow"><?= t('label_sort_order') ?></th><th><?= t('label_category') ?></th></tr>
            <?php
            $pageIds = getFileList(POSTS_DIR);
            $pageDataAll = [];
            foreach($pageIds as $pid) {
                $d = loadData(POSTS_DIR, $pid);
                $pageDataAll[] = ['id' => $pid, 'data' => $d];
            }
            usort($pageDataAll, function($a, $b) {
                if ($a['id'] === 'index') return -1;
                if ($b['id'] === 'index') return 1;
                $sa = (int)($a['data']['sort_order'] ?? 0);
                $sb = (int)($b['data']['sort_order'] ?? 0);
                if ($sa !== $sb) return $sa - $sb;
                return strcmp($a['id'], $b['id']);
            });

            if (empty($pageDataAll)): ?>
            <tr><td colspan="6" class="td-empty"><?= t('empty_pages') ?></td></tr>
            <?php else: 
                foreach ($pageDataAll as $pItem):
                    $pid = $pItem['id'];
                    $d = $pItem['data'];
                    
                    $status = $d['status'] ?? 'draft';
                    
                    // HTML sync indicator
                    $htmlPath = $ssgDir . '/' . ($pid==='index'?'':$pid) . '/index.html';
                    $hasHtml = file_exists($htmlPath);
                    $htmlIcon = $hasHtml ? ' <span class="html-indicator" title="HTML Built">○</span>' : '';
            ?>
            <tr>
                <td class="td-narrow">
                    <div class="flex-center" style="gap: 5px;">
                         <a href="?view=pages&edit=<?= $pid ?>#page-editor" class="btn btn-sm btn-blue"><?= getIcon('edit') ?> <?= t('btn_edit') ?></a>
                         <?php
                         $ssgStruct = $settings['ssg_structure'] ?? 'directory';
                         if ($pid === 'index') {
                             $previewUrl = '../';
                         } elseif ($status === 'public_dynamic') {
                             $previewUrl = '../' . $pid;
                         } elseif ($status === 'public_static') {
                             $previewUrl = $lastSsgRelPath . $pid . ($ssgStruct === 'directory' ? '/index.html' : '.html');
                         } else {
                             $previewUrl = '../' . $pid;
                         }
                         ?>
                         <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" class="btn btn-sm btn-orange" title="<?= t('btn_preview') ?>"><?php echo getIcon('open_in_new'); ?></a>
                    </div>
                </td>
                <td class="td-narrow">
                    <div class="flex-center">
                        <?php 
                            $statusClass = match($status) {
                            'public_static'  => 'static',
                            'public_dynamic' => 'dynamic',
                            'db'             => 'db',
                            default          => 'draft',
                        };
                        ?>
                        <select onchange="changePageStatus('<?= $pid ?>', this.value)" class="status-select-inline <?= $statusClass ?>">
                            <option value="draft" <?= $status==='draft'?'selected':'' ?>><?= t('page_status_draft') ?></option>
                            <option value="public_dynamic" <?= $status==='public_dynamic'?'selected':'' ?>><?= t('page_status_public_dynamic') ?></option>
                            <option value="public_static" <?= $status==='public_static'?'selected':'' ?>><?= t('page_status_public_static') ?></option>
                            <option value="db" <?= $status==='db'?'selected':'' ?>><?= t('page_status_db') ?></option>
                        </select>
                    </div>
                </td>
                <td class="td-narrow"><code><?= $pid ?></code></td>
                <td>
                    <div class="title-cell-flex">
                         <a href="?view=pages&edit=<?= $pid ?>#page-editor" class="title-link"><?= htmlspecialchars($d['title']??'No Title') ?></a>
                    </div>
                </td>
                <td class="td-narrow text-sub"><?= substr($d['updated_at'] ?? '', 0, 10) ?></td>
                <td class="td-narrow text-sub text-center"><?= htmlspecialchars($d['sort_order'] ?? '0') ?></td>
                <td style="font-size: 0.85rem; color: var(--text-sub);">
                    <?php 
                    $cats = array_filter(array_map('trim', explode(',', $d['category'] ?? '')));
                    echo htmlspecialchars(implode(', ', $cats));
                    ?>
                </td>
            </tr>
            <?php endforeach;
            endif; ?>
        </table>
        </form>
        </div>

        <div class="flex-row ssg-build-row <?= ($view === 'pages' && ($editId !== null || isset($_GET['new']))) ? 'has-editor' : 'no-editor' ?>">
            <form method="post">
                <input type="hidden" name="save_action" value="ssg_build">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-blue"><?= getIcon('sparkles') ?> <?= t('btn_ssg_build') ?></button>
            </form>
        </div>
        </div> <!-- /.section-container -->
    </div> <!-- /#pages -->

    <div id="page-editor-slot">
    <?php if ($view === 'pages' && ($editId !== null || isset($_GET['new']))): ?>
        <?php include __DIR__ . '/page-editor.php'; ?>
    <?php endif; ?>
    </div>
