<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$settings   = get_option( 'search_intel_settings', [] );
$settings   = wp_parse_args( $settings, \Search_Intel\Core::get_default_settings() );
$post_types = get_post_types( [ 'public' => true ], 'objects' );
?>
<div class="wrap si-settings si-bulk-tools">
    <h1><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Search Intel — Bulk Tools', 'search-intel' ); ?></h1>
    <p class="si-settings-intro"><?php esc_html_e( 'Run batch operations across your content, manage sitemap caches, and export/import plugin settings.', 'search-intel' ); ?></p>

    <!-- Post Type Selector -->
    <div class="si-settings-card">
        <h2><?php esc_html_e( 'Post Type', 'search-intel' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="si-bulk-post-type"><?php esc_html_e( 'Target Post Type', 'search-intel' ); ?></label></th>
                <td>
                    <select id="si-bulk-post-type">
                        <?php foreach ( $post_types as $pt ) : ?>
                            <option value="<?php echo esc_attr( $pt->name ); ?>"><?php echo esc_html( $pt->labels->name ); ?> (<?php echo esc_html( wp_count_posts( $pt->name )->publish ); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Select which post type to process for bulk operations.', 'search-intel' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Bulk Rescore -->
    <div class="si-settings-card">
        <h2><?php esc_html_e( 'Bulk Rescore Content', 'search-intel' ); ?></h2>
        <p><?php esc_html_e( 'Re-run SEO, AEO, GEO, and readability analysis on all published posts. Scores are stored in post meta and displayed in admin columns.', 'search-intel' ); ?></p>
        <div class="si-bulk-action">
            <button type="button" class="button button-primary" id="si-btn-rescore">
                <span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Rescore All', 'search-intel' ); ?>
            </button>
            <div class="si-progress" id="si-progress-rescore" style="display:none;">
                <div class="si-progress-bar"><div class="si-progress-fill" id="si-fill-rescore"></div></div>
                <span class="si-progress-text" id="si-text-rescore"></span>
            </div>
        </div>
    </div>

    <!-- Bulk Regenerate Metadata -->
    <div class="si-settings-card">
        <h2><?php esc_html_e( 'Bulk Regenerate Metadata', 'search-intel' ); ?></h2>
        <p><?php esc_html_e( 'Auto-fill empty fields: meta descriptions (from excerpt/content), AI summaries, and content type detection. Existing values are not overwritten.', 'search-intel' ); ?></p>
        <div class="si-bulk-action">
            <button type="button" class="button button-primary" id="si-btn-regenerate">
                <span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Regenerate Empty Fields', 'search-intel' ); ?>
            </button>
            <div class="si-progress" id="si-progress-regenerate" style="display:none;">
                <div class="si-progress-bar"><div class="si-progress-fill" id="si-fill-regenerate"></div></div>
                <span class="si-progress-text" id="si-text-regenerate"></span>
            </div>
        </div>
    </div>

    <!-- Bulk AI Content Generation -->
    <div class="si-settings-card">
        <h2><span class="dashicons dashicons-admin-generic" style="margin-right:4px;"></span> <?php esc_html_e( 'Bulk AI Content Generation', 'search-intel' ); ?></h2>
        <?php if ( empty( $settings['enable_ai_engine'] ) ) : ?>
            <div class="si-notice si-notice-warning">
                <p><?php esc_html_e( 'AI Engine is disabled.', 'search-intel' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=search-intel-ai' ) ); ?>"><?php esc_html_e( 'Enable it and add an API key →', 'search-intel' ); ?></a></p>
            </div>
        <?php else : ?>
            <p><?php esc_html_e( 'Use AI to generate content for all published posts across SEO, AEO, and GEO fields. Select which fields to fill below.', 'search-intel' ); ?></p>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Generate', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px; font-weight:600;">
                            <input type="checkbox" id="si-ai-gen-select-all" />
                            <?php esc_html_e( 'Select / Deselect All', 'search-intel' ); ?>
                        </label>

                        <fieldset style="margin:8px 0 12px; padding:8px 12px; border:1px solid #ccd0d4; border-radius:4px;">
                            <legend style="font-weight:600; padding:0 6px;"><?php esc_html_e( 'SEO', 'search-intel' ); ?></legend>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="seo_title" />
                                <?php esc_html_e( 'SEO Title (click-optimized, ≤60 chars)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="meta_description" checked />
                                <?php esc_html_e( 'Meta Description (120-155 chars, keyword-optimized)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="focus_keyword" checked />
                                <?php esc_html_e( 'Focus Keyword (primary SEO target keyword)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="secondary_keywords" />
                                <?php esc_html_e( 'Secondary Keywords (3-5 related terms)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="social_title" />
                                <?php esc_html_e( 'Social/OG Title (engaging share title)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="social_description" />
                                <?php esc_html_e( 'Social/OG Description (share-optimized summary)', 'search-intel' ); ?>
                            </label>
                        </fieldset>

                        <fieldset style="margin:0 0 12px; padding:8px 12px; border:1px solid #ccd0d4; border-radius:4px;">
                            <legend style="font-weight:600; padding:0 6px;"><?php esc_html_e( 'AEO (Answer Engine)', 'search-intel' ); ?></legend>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="direct_answer" />
                                <?php esc_html_e( 'Direct Answer (featured snippet / voice assistant answer, ≤300 chars)', 'search-intel' ); ?>
                            </label>
                        </fieldset>

                        <fieldset style="margin:0 0 8px; padding:8px 12px; border:1px solid #ccd0d4; border-radius:4px;">
                            <legend style="font-weight:600; padding:0 6px;"><?php esc_html_e( 'GEO (Generative Engine)', 'search-intel' ); ?></legend>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="ai_summary" checked />
                                <?php esc_html_e( 'AI Summary (concise factual overview for LLMs)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="key_topics" />
                                <?php esc_html_e( 'Key Topics (comma-separated topic tags for AI indexing)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="entities" />
                                <?php esc_html_e( 'Entities (people, orgs, places, concepts mentioned)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="content_type" />
                                <?php esc_html_e( 'Content Type (article, guide, tutorial, review, etc.)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="content_intent" />
                                <?php esc_html_e( 'Content Intent (informational, commercial, navigational, transactional)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="audience" />
                                <?php esc_html_e( 'Target Audience (who this content is written for)', 'search-intel' ); ?>
                            </label>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox" class="si-ai-gen-field" value="expertise_level" />
                                <?php esc_html_e( 'Expertise Level (beginner, intermediate, advanced, expert)', 'search-intel' ); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Mode', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="radio" name="si_ai_gen_mode" value="empty_only" checked />
                            <?php esc_html_e( 'Only fill empty fields (safe — never overwrites)', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="radio" name="si_ai_gen_mode" value="overwrite_all" />
                            <?php esc_html_e( 'Regenerate all (overwrites existing AI-generated content)', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <div class="si-bulk-action">
                <button type="button" class="button button-primary" id="si-btn-ai-generate">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Generate AI Content', 'search-intel' ); ?>
                </button>
                <div class="si-progress" id="si-progress-ai-generate" style="display:none;">
                    <div class="si-progress-bar"><div class="si-progress-fill" id="si-fill-ai-generate"></div></div>
                    <span class="si-progress-text" id="si-text-ai-generate"></span>
                </div>
                <div class="si-bulk-result" id="si-result-ai-generate" style="display:none;"></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sitemap Cache Warm -->
    <div class="si-settings-card">
        <h2><?php esc_html_e( 'Sitemap Cache', 'search-intel' ); ?></h2>
        <p><?php esc_html_e( 'Clear all sitemap transient caches and queue them for regeneration on next request.', 'search-intel' ); ?></p>
        <div class="si-bulk-action">
            <button type="button" class="button" id="si-btn-sitemap-warm">
                <span class="dashicons dashicons-networking"></span> <?php esc_html_e( 'Clear & Regenerate Sitemap Cache', 'search-intel' ); ?>
            </button>
            <div class="si-bulk-result" id="si-result-sitemap" style="display:none;"></div>
        </div>
    </div>

    <!-- Export Settings -->
    <div class="si-settings-card">
        <h2><?php esc_html_e( 'Export Settings', 'search-intel' ); ?></h2>
        <p><?php esc_html_e( 'Download all Search Intel settings as a JSON file. Use this to back up your configuration or transfer it to another site.', 'search-intel' ); ?></p>
        <div class="si-bulk-action">
            <button type="button" class="button" id="si-btn-export">
                <span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export Settings', 'search-intel' ); ?>
            </button>
        </div>
    </div>

    <!-- Import Settings -->
    <div class="si-settings-card">
        <h2><?php esc_html_e( 'Import Settings', 'search-intel' ); ?></h2>
        <p><?php esc_html_e( 'Upload a previously exported JSON file to restore or transfer settings.', 'search-intel' ); ?></p>
        <div class="si-bulk-action">
            <table class="form-table">
                <tr>
                    <th><label for="si-import-file"><?php esc_html_e( 'Settings File', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="file" id="si-import-file" accept=".json" />
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Import Mode', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="radio" name="si_import_mode" value="merge" checked />
                            <?php esc_html_e( 'Merge — keep current settings, apply imported values on top', 'search-intel' ); ?>
                        </label>
                        <br />
                        <label>
                            <input type="radio" name="si_import_mode" value="overwrite" />
                            <?php esc_html_e( 'Overwrite — reset to defaults, then apply imported values', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <button type="button" class="button button-primary" id="si-btn-import" disabled>
                <span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import Settings', 'search-intel' ); ?>
            </button>
            <div class="si-bulk-result" id="si-result-import" style="display:none;"></div>
        </div>
    </div>
</div>
