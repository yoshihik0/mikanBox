<?php defined('MIKANBOX') or die(); ?>
<script src="ai-question.js?v=<?php echo rawurlencode((string)@filemtime(__DIR__ . '/../ai-question.js')); ?>" defer></script>

    <div id="pages" class="section-anchor">
        <div class="section-container section-large-bottom">
            <div class="header">
            <h1>
                <?= getIcon('page') ?> <?= t('nav_pages') ?>
                <a href="?view=pages&new=1<?= ($selectedCat ?? '') !== '' ? '&cat=' . urlencode($selectedCat) : '' ?>#page-editor" class="btn btn-sm btn-blue" style="margin-left: 15px; display: inline-flex; align-items: center; gap: 5px; font-size: 0.85rem; padding: 4px 10px; vertical-align: middle;">
                    <?= getIcon('add') ?> <?= t('btn_create_new') ?>
                </a>
                <a href="<?= $helpFile ?>#page-mgmt" target="_blank" class="manual-link"><?= t('admin_help') ?></a>
            </h1>

            <?php if (function_exists('searchPosts')): ?>
            <!-- Admin Page Search Bar (Same height, client-side AJAX driven) -->
            <div class="search-bar-wrap" style="display: flex; align-items: center;">
                <div style="position: relative; display: flex; align-items: center;">
                    <input type="text" id="admin-page-search" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="<?= t('search_placeholder') ?>" style="padding: 6px 32px 6px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; width: 220px; box-sizing: border-box; outline: none; transition: border-color 0.2s;">
                    <span id="admin-page-search-icon" style="position: absolute; right: 10px; color: var(--text-sub); display: <?= empty($_GET['q']) ? 'flex' : 'none' ?>; align-items: center; pointer-events: none;">
                        <?= getIcon('search') ?>
                    </span>
                    <button type="button" id="admin-page-search-clear" style="position: absolute; right: 10px; background: none; border: none; padding: 0; cursor: pointer; color: var(--text-sub); display: <?= !empty($_GET['q']) ? 'flex' : 'none' ?>; align-items: center;" title="<?= t('btn_clear_search') ?>">
                        <?= getIcon('close') ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive" id="pages-table-wrap">
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
            
            // Search Filtering
            $searchQuery = $_GET['q'] ?? '';
            if (function_exists('searchPosts') && $searchQuery !== '') {
                $searchResults = searchPosts($searchQuery, true); // true = admin search
                $searchIds = array_column($searchResults, 'id');
                $pageDataAll = array_filter($pageDataAll, function($p) use ($searchIds) {
                    return in_array($p['id'], $searchIds);
                });
            }

            if (function_exists('getPreviewToken')) {
                // SQLite backend: pending-status pages sort first, ahead of the normal ordering.
                usort($pageDataAll, function($a, $b) {
                    $statusA = $a['data']['status'] ?? 'draft';
                    $statusB = $b['data']['status'] ?? 'draft';

                    $isPendingA = ($statusA === 'pending');
                    $isPendingB = ($statusB === 'pending');
                    if ($isPendingA && !$isPendingB) return -1;
                    if (!$isPendingA && $isPendingB) return 1;

                    if ($isPendingA && $isPendingB) {
                        $sa = (int)($a['data']['sort_order'] ?? 0);
                        $sb = (int)($b['data']['sort_order'] ?? 0);
                        if ($sa !== $sb) return $sa - $sb;
                        return strcmp($a['id'], $b['id']);
                    }

                    if ($a['id'] === 'index') return -1;
                    if ($b['id'] === 'index') return 1;

                    $sa = (int)($a['data']['sort_order'] ?? 0);
                    $sb = (int)($b['data']['sort_order'] ?? 0);
                    if ($sa !== $sb) return $sa - $sb;
                    return strcmp($a['id'], $b['id']);
                });
            } else {
                // Flat backend: original ordering, no pending status concept.
                usort($pageDataAll, function($a, $b) {
                    if ($a['id'] === 'index') return -1;
                    if ($b['id'] === 'index') return 1;
                    $sa = (int)($a['data']['sort_order'] ?? 0);
                    $sb = (int)($b['data']['sort_order'] ?? 0);
                    if ($sa !== $sb) return $sa - $sb;
                    return strcmp($a['id'], $b['id']);
                });
            }

            // Category Filtering
            $selectedCat = $_GET['cat'] ?? '';
            if ($selectedCat !== '') {
                $pageDataAll = array_filter($pageDataAll, function($p) use ($selectedCat) {
                    $cats = array_filter(array_map('trim', explode(',', $p['data']['category'] ?? '')));
                    return in_array($selectedCat, $cats);
                });
            }

            // Pagination
            $itemsPerPage = isset($settings['pages_per_page']) ? (int)$settings['pages_per_page'] : 30;
            $totalItems = count($pageDataAll);
            $totalPages = max(1, ceil($totalItems / $itemsPerPage));
            $currentPage = isset($_GET['p_pages']) ? max(1, min($totalPages, (int)$_GET['p_pages'])) : 1;
            $offset = ($currentPage - 1) * $itemsPerPage;
            $pageDataPage = array_slice($pageDataAll, $offset, $itemsPerPage);

            if (empty($pageDataPage)): ?>
            <tr><td colspan="7" class="td-empty"><?= t('empty_pages') ?></td></tr>
            <?php else: 
                foreach ($pageDataPage as $pItem):
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
                         <a href="?view=pages&edit=<?= $pid ?><?= ($searchQuery !== '') ? '&q=' . urlencode($searchQuery) : '' ?>#page-editor" class="btn btn-sm btn-blue"><?= getIcon('edit') ?> <?= t('btn_edit') ?></a>
                          <?php
                          $ssgStruct = $settings['ssg_structure'] ?? 'directory';
                          if ($pid === 'index') {
                              $previewUrl = '../';
                              if (function_exists('getPreviewToken') && $status === 'pending') {
                                  $previewUrl .= '?preview=' . getPreviewToken($pid);
                              }
                          } elseif (function_exists('getPreviewToken') && $status === 'pending') {
                              $previewUrl = '../' . $pid . '?preview=' . getPreviewToken($pid);
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
                             'pending'        => 'pending',
                             default          => 'draft',
                         };
                         ?>
                         <select onchange="changePageStatus('<?= $pid ?>', this.value)" class="status-select-inline <?= $statusClass ?>">
                             <option value="draft" <?= $status==='draft'?'selected':'' ?>><?= t('page_status_draft') ?></option>
                             <?php if (function_exists('getPreviewToken')): ?>
                             <option value="pending" <?= $status==='pending'?'selected':'' ?>><?= t('page_status_pending') ?></option>
                             <?php endif; ?>
                             <option value="public_dynamic" <?= $status==='public_dynamic'?'selected':'' ?>><?= t('page_status_public_dynamic') ?></option>
                             <option value="public_static" <?= $status==='public_static'?'selected':'' ?>><?= t('page_status_public_static') ?></option>
                             <option value="db" <?= $status==='db'?'selected':'' ?>><?= t('page_status_db') ?></option>
                         </select>
                    </div>
                </td>
                <td class="td-narrow"><code><?= $pid ?></code></td>
                <td>
                    <div class="title-cell-flex">
                         <a href="?view=pages&edit=<?= $pid ?><?= ($searchQuery !== '') ? '&q=' . urlencode($searchQuery) : '' ?>#page-editor" class="title-link"><?= htmlspecialchars($d['title']??'No Title') ?></a>
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

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?view=pages&p_pages=<?= $i ?><?= $selectedCat !== '' ? '&cat=' . urlencode($selectedCat) : '' ?>#pages" class="pagination-link <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

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
