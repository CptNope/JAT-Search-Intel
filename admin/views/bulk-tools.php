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
