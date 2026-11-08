<?php defined('MIKANBOX') or die(); ?>
    <details class="hint-accordion">
        <summary><h3 class="accordion-title"><?= t('available_tags_content_css') ?> <span class="accordion-arrow">▼</span></h3></summary>
        <div class="hint-accordion-body">
            <div class="hint-grid hint-grid-tag">
                <div>
                    <strong><?= t('standard_info') ?></strong>
                    <ul class="hint-list hint-list-sm">
                        <li><code>{{TITLE}}</code> : <?= t('page_title') ?></li>
                        <li><code>{{FULL_TITLE}}</code> : <?= t('page_title') ?> - <?= t('site_name') ?></li>
                        <li><code>{{UPDATE_DATE}}</code> : <?= t('hint_update_date_ymd') ?></li>
                        <li><code>{{UPDATE_DATE:JP}}</code> : <?= t('hint_update_date_jp') ?></li>
                        <li><code>{{UPDATE_DATE:SLASH}}</code> : <?= t('hint_update_date_slash') ?></li>
                        <li><code>{{IS_NEW:30}}</code> : <?= t('hint_is_new') ?></li>
                        <li><code>{{DESCRIPTION}}</code> : <?= t('page_description') ?></li>
                        <li><code>{{KEYWORDS}}</code> : <?= t('label_keywords') ?></li>
                        <li><code>{{OGP_IMAGE}}</code> : <?= t('page_thumbnail_ogp_image') ?></li>
                        <li><code>{{PAGE_URL}}</code> : <?= t('page_full_url') ?></li>
                        <li><code>{{PAGE_URL:ID}}</code> : <?= t('hint_page_url_id') ?></li>
                        <li><code>{{CATEGORY}}</code> : <?= t('hint_category') ?></li>
                        <li><code>{{CATEGORY:all}}</code> : <?= t('hint_category_all') ?></li>
                        <li><code>{{SITE_URL}}</code> : <?= t('site_root_url') ?></li>
                        <li><code>{{SITE_NAME}}</code> : <?= t('site_title') ?></li>
                        <li><code>{{SITE_DESCRIPTION}}</code> : <?= t('site_description') ?></li>
                        <li><code>{{SITE_OGP_IMAGE}}</code> : <?= t('site_common_ogp_image') ?></li>
                    </ul>
                    <strong style="display:block;margin-top:12px"><?= t('special_wrapper_design') ?></strong>
                    <ul class="hint-list hint-list-sm">
                        <li><code>{{CONTENT}}</code> : <?= t('page_main_content') ?></li>
                        <li><code>{{HEAD_CSS}}</code> : <?= t('combined_css_components') ?></li>
                        <li><code>{{COMPONENT:_global_head}}</code> : <?= t('common_head_section') ?></li>
                        <li><code>{{COMPONENT:_header}}</code> : <?= t('page_header') ?></li>
                        <li><code>{{COMPONENT:_footer}}</code> : <?= t('page_footer') ?></li>

                    </ul>
                </div>
                <div>
                    <strong><?= t('navigation_components') ?></strong>
                    <ul class="hint-list hint-list-sm">
                        <li><code>{{COMPONENT:ID}}</code> : <?= t('embed_registered_component') ?></li>
                        <li><code>{{IMAGE:<?= t('filename') ?>}}</code> : <?= t('display_static_image') ?></li>
                        <li><code>{{AUDIO:<?= t('filename') ?>}}</code> : <?= t('insert_audio_module') ?></li>
                        <li><code>{{VIDEO:<?= t('filename') ?>}}</code> : <?= t('display_video') ?></li>
                        <li><code>{{POST_MD:pageID}}</code> : <?= t('hint_post_md') ?></li>
                        <li><code>{{EXT_MD:url}}</code> : <?= t('hint_ext_md') ?></li>
                        <li><code>{{NAV_LINKS:category}}</code> : <?= t('link_list') ?><span class="hint-desc"> — li.active</span></li>
                        <li><code>{{NAV_CARDS:category:componentID}}</code> : <?= t('card_list') ?></li>
                        <li><code>{{NAV_LINKS:@same}}</code> : <?= t('hint_nav_same') ?></li>
                        <li><code>{{NAV_CARDS:news:_nav_card:sort=new:limit=5}}</code></li>
                        <li class="hint-note"><?= t('hint_nav_options') ?></li>
                        <li class="hint-note"><?= t('nav_links_cards_hint') ?></li>
                        <li style="margin-top:8px"><span class="hint-section-label"><?= t('nav_cards_template_vars') ?></span></li>
                        <li><code>{{PAGE_URL}}</code> <code>{{TITLE}}</code> <code>{{CATEGORY}}</code> <code>{{DESCRIPTION}}</code> <code>{{OGP_IMAGE}}</code> <code>{{UPDATE_DATE}}</code> <code>{{IS_NEW:N}}</code> <code>{{POST_MD::key}}</code> <code>{{POST_MD::#rowID:key}}</code></li>
                        <li><code>{{IS_ACTIVE}}</code> : <?= t('hint_is_active') ?></li>
                    </ul>
                </div>
                <div>
                    <strong><?= t('label_database') ?></strong>
                    <ul class="hint-list hint-list-sm">
                        <li>
                            <span class="hint-section-label"><?= t('hint_datarow_def') ?></span><br>
                            <code>{{DATA:key}}<?= t('hint_data_value') ?>{{/DATA}}</code> : <?= t('data_block_visible') ?><br>
                            <code>{{DATA:key:GHOST}}<?= t('hint_data_value') ?>{{/DATA}}</code> : <?= t('data_block_hidden') ?><br>
                            <span class="hint-desc"><?= t('hint_data_ascii_rule') ?></span>
                        </li>
                        <li style="margin-top:8px">
                            <span class="hint-section-label"><?= t('hint_datarow_table_def') ?></span><br>
                            <code>{{DATAROW:rowID}}</code><br>
                            <code>{{DATA:key}}<?= t('hint_data_value') ?>{{/DATA}}</code><br>
                            <code>{{/DATAROW}}</code>
                        </li>
                        <li style="margin-top:8px"><span class="hint-section-label"><?= t('hint_datarow_usage') ?></span></li>
                        <li><code>{{POST_MD::key}}</code> : <?= t('data_from_self') ?></li>
                        <li><code>{{POST_MD:pageID:key}}</code> : <?= t('data_from_page') ?></li>
                        <li><code>{{EXT_MD:url:key}}</code> : <?= t('hint_ext_md_key') ?></li>
                        <li style="margin-top:6px"><code>{{POST_MD::#rowID:key}}</code> : <?= t('hint_datarow_self_table') ?></li>
                        <li><code>{{POST_MD:pageID#rowID:key}}</code> : <?= t('hint_datarow_page_table') ?></li>
                        <li><code>{{EXT_MD:url#rowID:key}}</code> : <?= t('hint_datarow_ext_table') ?></li>
                        <li style="margin-top:6px" class="hint-note"><?= t('hint_db_api_hidden') ?></li>
                        <li class="hint-note"><?= t('hint_db_api_public') ?></li>
                        <li style="margin-top:8px"><span class="hint-section-label"><?= t('label_output_formats_head') ?></span></li>
                        <li class="hint-note"><?= t('hint_output_formats') ?> <a href="<?= htmlspecialchars((string)$helpFile) ?>#output-formats" target="_blank"><?= t('admin_help') ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </details>
