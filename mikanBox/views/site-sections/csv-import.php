<?php defined('MIKANBOX') or die(); ?>
    <div id="csv-import">
        <div class="section-container section-tight">
            <details class="section-accordion">
                <summary class="header section-header accordion-summary">
                    <h2 class="accordion-title">
                        <?= t('csv_head') ?> <span class="accordion-arrow">▼</span>
                    </h2>
                </summary>
                <div class="editor-container editor-container-sub">
                    <small class="sub-text sub-text-intro"><?= t('csv_hint') ?></small>
                    <div class="mt-10">
                        <input type="file" id="csv-file-input" accept=".csv,text/csv">
                    </div>
                    <div class="mt-10">
                        <button type="button" class="btn btn-gray btn-small" onclick="csvConvertAndCopy()" id="csv-copy-btn"><?= getIcon('copy') ?> <?= t('btn_csv_convert') ?></button>
                    </div>
                </div>
            </details>
        </div>
    </div>
