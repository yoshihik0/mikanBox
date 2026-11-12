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
                <a href="<?= $helpFile ?>#page-mgmt" target="_blank" class="manual-link"><?= t('admin_help') ?></a><?php
                $pageFormatFilter = (string)($_GET['fmt'] ?? '');
                if (!in_array($pageFormatFilter, ['', 'html', 'html_md', 'md', 'data', 'db'], true)) $pageFormatFilter = '';
                ?><span class="component-filter-control"><select class="select-auto list-filter-select" id="page-format-filter" aria-label="<?= t('label_output_format') ?>"><?php foreach (['' => t('page_format_filter_all'), 'html' => t('output_format_html'), 'html_md' => t('output_format_html_md'), 'md' => t('output_format_md'), 'data' => t('output_format_data_group'), 'db' => t('output_format_db')] as $_fmtValue => $_fmtLabel): ?><option value="<?= $_fmtValue ?>" <?= $pageFormatFilter === $_fmtValue ? 'selected' : '' ?>><?= htmlspecialchars($_fmtLabel) ?></option><?php endforeach; ?></select></span><?php
                $pageSort = (string)($_GET['sort'] ?? '');
                if (!in_array($pageSort, ['', 'updated_desc', 'updated_asc', 'id', 'title'], true)) $pageSort = '';
                ?><span class="component-filter-control"><select class="select-auto list-filter-select" id="page-sort-order" aria-label="<?= t('page_sort_label') ?>" title="<?= htmlspecialchars(t('page_sort_hint')) ?>"><?php foreach (['' => t('page_sort_display'), 'updated_desc' => t('page_sort_updated_desc'), 'updated_asc' => t('page_sort_updated_asc'), 'id' => t('page_sort_id'), 'title' => t('page_sort_title')] as $_sortValue => $_sortLabel): ?><option value="<?= $_sortValue ?>" <?= $pageSort === $_sortValue ? 'selected' : '' ?>><?= htmlspecialchars($_sortLabel) ?></option><?php endforeach; ?></select></span>
            </h1>

            <?php if (function_exists('searchPosts')): ?>
            <!-- Admin page search: live (AJAX), the browser's own clear button only -->
            <form method="get" action="admin.php#pages" class="search-bar-wrap" role="search">
                <input type="hidden" name="view" value="pages"><input type="hidden" name="cat" value="<?= htmlspecialchars($selectedCat ?? '') ?>"><input type="hidden" name="fmt" value="<?= htmlspecialchars($pageFormatFilter) ?>"><input type="hidden" name="sort" value="<?= htmlspecialchars($pageSort) ?>">
                <div class="admin-search">
                    <input type="search" name="q" id="admin-page-search" class="admin-search-input" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="<?= t('search_placeholder') ?>" aria-label="<?= t('search_placeholder') ?>">
                    <span id="admin-page-search-icon" class="admin-search-icon"><?= getIcon('search') ?></span>
                </div>
            </form>
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

            // Order. Default ("display order") = the order of the lists on the public side:
            // display order, then newest update first, then ID; pending pages (SQLite) come first.
            // The other orders are exactly that order; index always stays first.
            $isPendingPage = fn($p) => function_exists('getPreviewToken') && (($p['data']['status'] ?? 'draft') === 'pending');
            $updatedTs = fn($p) => (int)strtotime((string)($p['data']['updated_at'] ?? '')) ?: 0;
            $collator = class_exists('Collator') ? new Collator('ja') : null;
            $titleKey = fn($p) => (string)(($p['data']['title'] ?? '') !== '' ? $p['data']['title'] : $p['id']);
            usort($pageDataAll, function($a, $b) use ($pageSort, $isPendingPage, $updatedTs, $collator, $titleKey) {
                if ($a['id'] === 'index' && $b['id'] !== 'index') return -1;
                if ($b['id'] === 'index' && $a['id'] !== 'index') return 1;
                if ($pageSort === '') {
                    $pa = $isPendingPage($a); $pb = $isPendingPage($b);
                    if ($pa !== $pb) return $pa ? -1 : 1;
                    $sa = (int)($a['data']['sort_order'] ?? 0);
                    $sb = (int)($b['data']['sort_order'] ?? 0);
                    if ($sa !== $sb) return $sa <=> $sb;
                    $ta = $updatedTs($a); $tb = $updatedTs($b);
                    if ($ta !== $tb) return $tb <=> $ta;
                    return strcmp($a['id'], $b['id']);
                }
                if ($pageSort === 'updated_desc' || $pageSort === 'updated_asc') {
                    $ta = $updatedTs($a); $tb = $updatedTs($b);
                    if ($ta !== $tb) return $pageSort === 'updated_desc' ? $tb <=> $ta : $ta <=> $tb;
                } elseif ($pageSort === 'title') {
                    $ka = $titleKey($a); $kb = $titleKey($b);
                    $c = $collator ? $collator->compare($ka, $kb) : strcmp(mb_strtolower($ka, 'UTF-8'), mb_strtolower($kb, 'UTF-8'));
                    if ($c !== 0) return $c;
                }
                return strcmp($a['id'], $b['id']);
            });

            // Output format filtering ("data" = every data format)
            if ($pageFormatFilter !== '') {
                $pageDataAll = array_filter($pageDataAll, function($p) use ($pageFormatFilter) {
                    $format = mikanBoxPageOutputFormat($p['data']);
                    return $pageFormatFilter === 'data' ? mikanBoxIsDataFormat($format) : $format === $pageFormatFilter;
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
            <tr data-page-id="<?= htmlspecialchars($pid, ENT_QUOTES) ?>">
                <td class="td-narrow">
                    <div class="flex-center" style="gap: 5px;">
                         <a href="?view=pages&edit=<?= $pid ?><?= ($searchQuery !== '') ? '&q=' . urlencode($searchQuery) : '' ?>#page-editor" class="btn btn-sm btn-blue" title="<?= t('btn_edit') ?>"><?= getIcon('edit') ?></a>
                         <?php $pageFormat = mikanBoxPageOutputFormat($d); $pageExt = mikanBoxFormatExtension($pageFormat); ?>
                         <?php if (is_file(CORE_DIR . '/visual.php')): ?>
                         <?php if (mikanBoxFormatHasHtml($pageFormat)): ?>
                         <a href="visual.php?id=<?= rawurlencode($pid) ?>" class="btn btn-sm btn-blue" title="<?= t('btn_visual_edit') ?>"><?= getIcon('visual') ?></a>
                         <?php else: ?>
                         <span class="btn btn-sm btn-gray btn-disabled" aria-disabled="true" title="<?= htmlspecialchars(t('visual_err_not_html')) ?>"><?= getIcon('visual') ?></span>
                         <?php endif; ?>
                         <?php endif; ?>
                          <?php $publicUrl = mikanBoxPublicPageUrl($pid, $d, $settings, $lastSsgRelPath); ?>
                          <?php if ($publicUrl !== null): ?>
                          <a href="<?= htmlspecialchars($publicUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-blue" title="<?= t('btn_open_public_page') ?>"><?= getIcon('globe') ?></a>
                          <?php else: ?>
                          <span class="btn btn-sm btn-gray btn-disabled" aria-disabled="true" title="<?= t('msg_not_public_yet') ?>"><?= getIcon('globe') ?></span>
                          <?php endif; ?>
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
                         </select>
                    </div>
                </td>
                <td class="td-narrow"><code><?= $pid ?></code></td>
                <td>
                    <div class="title-cell-flex">
                         <a href="?view=pages&edit=<?= $pid ?><?= ($searchQuery !== '') ? '&q=' . urlencode($searchQuery) : '' ?>#page-editor" class="title-link"><?= htmlspecialchars($d['title']??'No Title') ?></a>
                         <?php if ($pageFormat !== 'html'): ?>
                         <span class="format-badge <?= mikanBoxIsDataFormat($pageFormat) || $pageFormat === 'db' ? 'format-data' : 'format-md' ?>" title="<?= htmlspecialchars(t('label_output_format') . ': ' . t('output_format_' . $pageFormat)) ?>"><?= $pageFormat === 'html_md' ? '+MD' : ($pageFormat === 'db' ? 'DB' : strtoupper($pageExt)) ?></span>
                         <?php endif; ?>
                    </div>
                </td>
                <td class="td-narrow text-sub"><?= substr($d['updated_at'] ?? '', 0, 10) ?></td>
                <td class="td-narrow text-sub text-center"><?= htmlspecialchars($d['sort_order'] ?? '0') ?></td>
                <td style="font-size: 0.85rem; color: var(--text-sub);">
                    <?php 
                    $cats = array_filter(array_map('trim', explode(',', $d['category'] ?? '')));
                    echo implode(', ', array_map(fn($c) => mikanBoxIsStructuralCategory($c) ? '<span class="category-structural">' . htmlspecialchars($c) . '</span>' : htmlspecialchars($c), $cats));
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
                <a href="?view=pages&p_pages=<?= $i ?><?= $selectedCat !== '' ? '&cat=' . urlencode($selectedCat) : '' ?><?= $pageFormatFilter !== '' ? '&fmt=' . urlencode($pageFormatFilter) : '' ?><?= $pageSort !== '' ? '&sort=' . urlencode($pageSort) : '' ?><?= (string)($_GET['q'] ?? '') !== '' ? '&q=' . urlencode((string)$_GET['q']) : '' ?>#pages" class="pagination-link <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <div class="flex-row ssg-build-row <?= ($view === 'pages' && ($editId !== null || isset($_GET['new']))) ? 'has-editor' : 'no-editor' ?>">
            <form method="post" data-ajax>
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

<script>
(function () {
    // Output format filter: same control as the Design screen's filter; kept in the URL (fmt) like cat and q.
    const filter = document.getElementById('page-format-filter');
    const sortSelect = document.getElementById('page-sort-order');
    if (sortSelect) sortSelect.addEventListener('change', function () {
        // The sort order is kept in the URL (sort) like fmt, and applies before paging.
        const url = new URL(window.location.href);
        if (sortSelect.value) url.searchParams.set('sort', sortSelect.value); else url.searchParams.delete('sort');
        url.searchParams.delete('p_pages');
        url.searchParams.set('view', 'pages');
        history.replaceState(null, '', url.pathname + url.search + url.hash);
        const hidden = document.querySelector('#pages form[role="search"] input[name="sort"]');
        if (hidden) hidden.value = sortSelect.value;
        if (typeof refreshPageList === 'function') refreshPageList(); else window.location.assign(url.href);
    });
    if (!filter) return;
    filter.addEventListener('change', function () {
        const url = new URL(window.location.href);
        if (filter.value) url.searchParams.set('fmt', filter.value); else url.searchParams.delete('fmt');
        url.searchParams.delete('p_pages');
        url.searchParams.set('view', 'pages');
        history.replaceState(null, '', url.pathname + url.search + url.hash);
        const hidden = document.querySelector('#pages form[role="search"] input[name="fmt"]');
        if (hidden) hidden.value = filter.value;
        if (typeof refreshPageList === 'function') refreshPageList(); else window.location.assign(url.href);
    });
})();
</script>
