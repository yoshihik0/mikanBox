<?php defined('MIKANBOX') or die(); ?>

    <div id="design" class="section-anchor">
        <div class="section-container section-large-bottom">
            <div class="header">
            <h1><?= getIcon('component') ?> <?= t('nav_design') ?><a href="<?= $helpFile ?>#design-mgmt" target="_blank" class="manual-link"><?= t('admin_help') ?></a></h1>
            <a href="?view=design&new=1#design-editor" class="btn btn-blue"><?= getIcon('add') ?> <?= t('btn_create_new') ?></a>
        </div>
        <div class="table-responsive <?= ($view === 'components' && ($editId !== null || isset($_GET['new']))) ? 'mb-0' : 'ssg-build-row no-editor' ?>">
        <table>
            <tr><th class="td-narrow"><?= t('btn_edit') ?></th><th class="td-narrow"><?= t('label_component_id') ?></th><th class="td-narrow"><?= t('label_type') ?></th><th><?= t('label_tag_name') ?></th></tr>
            <?php
            $compsListDesign = getFileList(COMPONENTS_DIR);
            $compDataListDesign = [];
            foreach($compsListDesign as $cid) {
                $cd = loadData(COMPONENTS_DIR, $cid);
                $compDataListDesign[] = ['id' => $cid, 'is_wrapper' => !empty($cd['is_wrapper'])];
            }
            usort($compDataListDesign, function($a, $b) {
                if ($a['is_wrapper'] !== $b['is_wrapper']) return $a['is_wrapper'] ? -1 : 1;
                $priority = ['global_head' => 1, 'header' => 2, 'footer' => 3];
                $pA = $priority[$a['id']] ?? 99;
                $pB = $priority[$b['id']] ?? 99;
                if ($pA !== $pB) return $pA - $pB;
                return strcmp($a['id'], $b['id']);
            });
            foreach($compDataListDesign as $cItem):
                $cid = $cItem['id'];
                $cData = loadData(COMPONENTS_DIR, $cid);
                $typeLabel = !empty($cData['is_wrapper']) ? t('comp_type_page') : t('comp_type_part');
                $typeClass = !empty($cData['is_wrapper']) ? 'type-badge wrapper' : 'type-badge';
            ?>
            <tr>
                <td class="td-narrow">
                    <div class="flex-center">
                         <a href="?view=design&edit=<?= $cid ?>#design-editor" class="btn btn-sm btn-blue"><?= getIcon('edit') ?> <?= t('btn_edit') ?></a>
                    </div>
                </td>
                <td class="td-narrow">
                    <a href="?view=design&edit=<?= $cid ?>#design-editor" class="comp-id-link"><code><?= $cid ?></code></a>
                </td>
                <td class="td-narrow"><span class="<?= $typeClass ?>"><?= $typeLabel ?></span></td>
                <td><input type="text" value="{{COMPONENT:<?= $cid ?>}}" readonly onclick="copyToClipboard(this.value); this.select();" class="copy-input" title="<?= t('click_to_copy') ?>"></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
        </div> <!-- /.section-container -->
    </div> <!-- /#design -->

    <div id="design-editor-slot">
    <?php if ($view === 'components' && ($editId !== null || isset($_GET['new']))): ?>
        <?php include __DIR__ . '/design-editor.php'; ?>
    <?php endif; ?>
    </div>
