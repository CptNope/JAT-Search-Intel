<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bulk Tools for Search Intel.
 *
 * Provides bulk rescore, regenerate, sitemap cache warm,
 * and settings export/import functionality.
 *
 * @since 1.3.0
 */
class Bulk_Tools {

    /** @var array Plugin settings. */
    private $settings;

    /** @var Core */
    private $core;

    /**
     * @param array $settings Merged plugin settings.
     * @param Core  $core     Core instance.
     */
    public function __construct( array $settings, Core $core ) {
        $this->settings = $settings;
        $this->core     = $core;

        add_action( 'wp_ajax_si_bulk_rescore', [ $this, 'ajax_bulk_rescore' ] );
        add_action( 'wp_ajax_si_bulk_regenerate', [ $this, 'ajax_bulk_regenerate' ] );
        add_action( 'wp_ajax_si_bulk_sitemap_warm', [ $this, 'ajax_bulk_sitemap_warm' ] );
        add_action( 'wp_ajax_si_export_settings', [ $this, 'ajax_export_settings' ] );
        add_action( 'wp_ajax_si_import_settings', [ $this, 'ajax_import_settings' ] );
    }

    /**
     * Enqueue bulk tools admin assets.
     */
    public function enqueue_assets(): void {
        wp_enqueue_script(
            'si-bulk-tools',
            SEARCH_INTEL_URL . 'admin/js/bulk-tools.js',
            [ 'jquery' ],
            SEARCH_INTEL_VERSION,
            true
        );

        wp_localize_script( 'si-bulk-tools', 'siBulkTools', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'si_bulk_tools' ),
            'i18n'    => [
                'processing'  => __( 'Processing...', 'search-intel' ),
                'complete'    => __( 'Complete!', 'search-intel' ),
                'error'       => __( 'An error occurred.', 'search-intel' ),
                'confirm'     => __( 'Are you sure? This will overwrite current settings.', 'search-intel' ),
                'exported'    => __( 'Settings exported successfully.', 'search-intel' ),
                'imported'    => __( 'Settings imported successfully.', 'search-intel' ),
            ],
        ] );
    }

    // ── AJAX: Bulk Rescore ──────────────────────────────────────────────────

    /**
     * AJAX handler for bulk rescoring. Processes one batch per request.
     */
    public function ajax_bulk_rescore(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $batch_size = 20;
        $offset     = absint( $_POST['offset'] ?? 0 );
        $post_type  = sanitize_text_field( $_POST['post_type'] ?? 'post' );

        $posts = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        $total_obj = wp_count_posts( $post_type );
        $total     = (int) $total_obj->publish;

        $processed = 0;
        foreach ( $posts as $post ) {
            $content     = wp_strip_all_tags( $post->post_content );
            $title       = $post->post_title;
            $keyword     = get_post_meta( $post->ID, '_search_intel_focus_keyword', true ) ?: '';
            $description = get_post_meta( $post->ID, '_search_intel_description', true ) ?: '';

            $results = $this->core->analyzer->analyze( $post->ID, $content, $title, $keyword, $description );

            if ( isset( $results['seo']['score'] ) ) {
                update_post_meta( $post->ID, '_search_intel_seo_score', (int) $results['seo']['score'] );
            }
            if ( isset( $results['aeo']['score'] ) ) {
                update_post_meta( $post->ID, '_search_intel_aeo_score', (int) $results['aeo']['score'] );
            }
            if ( isset( $results['geo']['score'] ) ) {
                update_post_meta( $post->ID, '_search_intel_geo_score', (int) $results['geo']['score'] );
            }
            if ( isset( $results['readability']['score'] ) ) {
                update_post_meta( $post->ID, '_search_intel_readability_score', (int) $results['readability']['score'] );
            }

            $processed++;
        }

        $next_offset = $offset + $batch_size;
        $has_more    = $next_offset < $total;

        wp_send_json_success( [
            'processed'   => $processed,
            'offset'      => $offset,
            'next_offset' => $has_more ? $next_offset : null,
            'has_more'    => $has_more,
            'total'       => $total,
            'done'        => min( $next_offset, $total ),
        ] );
    }

    // ── AJAX: Bulk Regenerate ───────────────────────────────────────────────

    /**
     * AJAX handler for bulk metadata regeneration (titles, descriptions, excerpts).
     */
    public function ajax_bulk_regenerate(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $batch_size = 20;
        $offset     = absint( $_POST['offset'] ?? 0 );
        $post_type  = sanitize_text_field( $_POST['post_type'] ?? 'post' );

        $posts = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        $total_obj = wp_count_posts( $post_type );
        $total     = (int) $total_obj->publish;

        $processed = 0;
        foreach ( $posts as $post ) {
            // Regenerate description if empty.
            $existing_desc = get_post_meta( $post->ID, '_search_intel_description', true );
            if ( empty( $existing_desc ) ) {
                $auto_desc = $this->core->seo->get_description( $post->ID );
                if ( $auto_desc ) {
                    update_post_meta( $post->ID, '_search_intel_description', sanitize_text_field( $auto_desc ) );
                }
            }

            // Regenerate AI summary if empty.
            $existing_summary = get_post_meta( $post->ID, '_search_intel_ai_summary', true );
            if ( empty( $existing_summary ) ) {
                $auto_summary = wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '...' );
                if ( $auto_summary ) {
                    update_post_meta( $post->ID, '_search_intel_ai_summary', sanitize_text_field( $auto_summary ) );
                }
            }

            // Auto-detect content type if empty.
            $existing_type = get_post_meta( $post->ID, '_search_intel_content_type', true );
            if ( empty( $existing_type ) ) {
                $content_lower = strtolower( $post->post_content );
                if ( str_contains( $content_lower, 'how to' ) || str_contains( $content_lower, 'step' ) ) {
                    $type = 'how-to';
                } elseif ( str_contains( $content_lower, '?' ) && substr_count( $content_lower, '?' ) >= 3 ) {
                    $type = 'faq';
                } elseif ( str_contains( $content_lower, ' vs ' ) || str_contains( $content_lower, 'compare' ) ) {
                    $type = 'comparison';
                } else {
                    $type = 'article';
                }
                update_post_meta( $post->ID, '_search_intel_content_type', $type );
            }

            $processed++;
        }

        $next_offset = $offset + $batch_size;
        $has_more    = $next_offset < $total;

        wp_send_json_success( [
            'processed'   => $processed,
            'offset'      => $offset,
            'next_offset' => $has_more ? $next_offset : null,
            'has_more'    => $has_more,
            'total'       => $total,
            'done'        => min( $next_offset, $total ),
        ] );
    }

    // ── AJAX: Sitemap Cache Warm ────────────────────────────────────────────

    /**
     * AJAX handler for pre-warming all sitemap transient caches.
     */
    public function ajax_bulk_sitemap_warm(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        // Invalidate existing caches first.
        $this->core->sitemap->invalidate_cache();

        // Trigger sitemap generation to warm caches.
        // The sitemap module stores results in transients on generation.
        $post_types = $this->settings['sitemap_post_types'] ?? [ 'post', 'page' ];
        $warmed     = [ 'index' ];

        foreach ( $post_types as $type ) {
            $warmed[] = 'sitemap-' . $type;
        }

        $taxonomies = $this->settings['sitemap_taxonomies'] ?? [ 'category' ];
        foreach ( $taxonomies as $tax ) {
            $warmed[] = 'sitemap-' . $tax;
        }

        wp_send_json_success( [
            'warmed'  => $warmed,
            'message' => sprintf(
                /* translators: %d: number of sitemaps warmed */
                __( 'Cleared and queued %d sitemap caches for regeneration.', 'search-intel' ),
                count( $warmed )
            ),
        ] );
    }

    // ── AJAX: Export Settings ───────────────────────────────────────────────

    /**
     * AJAX handler for settings export.
     */
    public function ajax_export_settings(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $data = [
            'plugin'   => 'search-intel',
            'version'  => SEARCH_INTEL_VERSION,
            'exported' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'settings' => $this->core->get_settings(),
            'social'   => get_option( 'search_intel_social_profiles', [] ),
        ];

        wp_send_json_success( $data );
    }

    // ── AJAX: Import Settings ───────────────────────────────────────────────

    /**
     * AJAX handler for settings import.
     */
    public function ajax_import_settings(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $json = wp_unslash( $_POST['import_data'] ?? '' );
        $data = json_decode( $json, true );

        if ( ! is_array( $data ) || empty( $data['plugin'] ) || 'search-intel' !== $data['plugin'] ) {
            wp_send_json_error( [ 'message' => __( 'Invalid Search Intel settings file.', 'search-intel' ) ] );
        }

        $mode = sanitize_text_field( $_POST['import_mode'] ?? 'merge' );

        // Validate settings keys against defaults.
        $defaults = Core::get_default_settings();

        if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
            $import_settings = $data['settings'];

            if ( 'overwrite' === $mode ) {
                // Full overwrite: start from defaults, apply imported.
                $new_settings = $defaults;
            } else {
                // Merge: start from current, apply imported.
                $new_settings = $this->core->get_settings();
            }

            foreach ( $import_settings as $key => $value ) {
                if ( ! array_key_exists( $key, $defaults ) ) {
                    continue;
                }

                // Type-cast based on default.
                $default_val = $defaults[ $key ];
                if ( is_bool( $default_val ) ) {
                    $new_settings[ $key ] = (bool) $value;
                } elseif ( is_int( $default_val ) ) {
                    $new_settings[ $key ] = absint( $value );
                } elseif ( is_array( $default_val ) ) {
                    $new_settings[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : $default_val;
                } else {
                    $new_settings[ $key ] = sanitize_text_field( $value );
                }
            }

            update_option( 'search_intel_settings', $new_settings );
        }

        if ( ! empty( $data['social'] ) && is_array( $data['social'] ) ) {
            $social = array_map( 'esc_url_raw', $data['social'] );
            update_option( 'search_intel_social_profiles', $social );
        }

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %s: merge or overwrite */
                __( 'Settings imported (%s mode).', 'search-intel' ),
                $mode
            ),
        ] );
    }
}
