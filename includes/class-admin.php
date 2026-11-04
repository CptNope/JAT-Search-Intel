<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin interface — settings page, post editor metaboxes, and dashboard widgets.
 */
class Admin {

    /** @var array */
    private $settings;

    /** @var Core */
    private $core;

    public function __construct( array $settings, Core $core ) {
        $this->settings = $settings;
        $this->core     = $core;

        // Admin menu
        add_action( 'admin_menu', [ $this, 'register_menu' ] );

        // Settings registration
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Post editor metabox
        add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
        add_action( 'save_post', [ $this, 'save_metabox_data' ], 10, 2 );

        // Enqueue admin assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Admin columns
        add_filter( 'manage_posts_columns', [ $this, 'add_admin_columns' ] );
        add_filter( 'manage_pages_columns', [ $this, 'add_admin_columns' ] );
        add_action( 'manage_posts_custom_column', [ $this, 'render_admin_columns' ], 10, 2 );
        add_action( 'manage_pages_custom_column', [ $this, 'render_admin_columns' ], 10, 2 );

        // Add settings link to plugins page
        add_filter( 'plugin_action_links_' . JAT_SEARCH_INTEL_BASENAME, [ $this, 'plugin_action_links' ] );

        // User profile fields for E-E-A-T
        add_action( 'show_user_profile', [ $this, 'render_user_eeat_fields' ] );
        add_action( 'edit_user_profile', [ $this, 'render_user_eeat_fields' ] );
        add_action( 'personal_options_update', [ $this, 'save_user_eeat_fields' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_user_eeat_fields' ] );

        // AI Engine AJAX handlers
        add_action( 'wp_ajax_jat_search_intel_save_ai_api_key', [ $this, 'ajax_save_ai_api_key' ] );
        add_action( 'wp_ajax_jat_search_intel_test_ai_connection', [ $this, 'ajax_test_ai_connection' ] );
    }

    /**
     * Register admin menu pages.
     */
    public function register_menu(): void {
        add_menu_page(
            __( 'JAT Search Intel', 'jat-search-intel' ),
            __( 'JAT Search Intel', 'jat-search-intel' ),
            'manage_options',
            'jat-search-intel',
            [ $this, 'render_settings_page' ],
            'dashicons-chart-area',
            80
        );

        add_submenu_page(
            'jat-search-intel',
            __( 'General Settings', 'jat-search-intel' ),
            __( 'General', 'jat-search-intel' ),
            'manage_options',
            'jat-search-intel',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'jat-search-intel',
            __( 'SEO Settings', 'jat-search-intel' ),
            __( 'SEO', 'jat-search-intel' ),
            'manage_options',
            'jat-search-intel-seo',
            [ $this, 'render_seo_settings_page' ]
        );

        add_submenu_page(
            'jat-search-intel',
            __( 'AEO Settings', 'jat-search-intel' ),
            __( 'AEO', 'jat-search-intel' ),
            'manage_options',
            'jat-search-intel-aeo',
            [ $this, 'render_aeo_settings_page' ]
        );

        add_submenu_page(
            'jat-search-intel',
            __( 'GEO Settings', 'jat-search-intel' ),
            __( 'GEO', 'jat-search-intel' ),
            'manage_options',
            'jat-search-intel-geo',
            [ $this, 'render_geo_settings_page' ]
        );

        add_submenu_page(
            'jat-search-intel',
            __( 'Sitemap', 'jat-search-intel' ),
            __( 'Sitemap', 'jat-search-intel' ),
            'manage_options',
            'jat-search-intel-sitemap',
            [ $this, 'render_sitemap_settings_page' ]
        );

        add_submenu_page(
            'jat-search-intel',
            __( 'Social Profiles', 'jat-search-intel' ),
            __( 'Social', 'jat-search-intel' ),
            'manage_options',
            'jat-search-intel-social',
            [ $this, 'render_social_settings_page' ]
        );

        add_submenu_page(
            'jat-search-intel',
            __( 'AI Engine', 'jat-search-intel' ),
            __( 'AI Engine', 'jat-search-intel' ),
            'manage_options',
            'jat-search-intel-ai',
            [ $this, 'render_ai_settings_page' ]
        );

        add_submenu_page(
            'jat-search-intel',
            __( 'Bulk Tools', 'jat-search-intel' ),
            __( 'Bulk Tools', 'jat-search-intel' ),
            'manage_options',
            'jat-search-intel-bulk-tools',
            [ $this, 'render_bulk_tools_page' ]
        );
    }

    /**
     * Register settings.
     */
    public function register_settings(): void {
        register_setting( 'jat_search_intel_settings_group', 'jat_search_intel_settings', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ] );

        register_setting( 'jat_search_intel_social_group', 'jat_search_intel_social_profiles', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_social_profiles' ],
        ] );
    }

    /**
     * Sanitize main settings.
     */
    public function sanitize_settings( $input ): array {
        $defaults  = Core::get_default_settings();
        $existing  = get_option( 'jat_search_intel_settings', [] );
        $existing  = wp_parse_args( $existing, $defaults );
        $sanitized = [];

        // Determine which settings page is submitting.
        $page = $input['_si_page'] ?? '';

        // Map each page to the boolean checkboxes it manages.
        // Booleans NOT on the current page preserve their existing saved value.
        $page_bools = [
            'general' => [
                'enable_seo', 'enable_schema', 'enable_aeo', 'enable_geo',
                'enable_sitemap', 'enable_analyzer',
                'enable_redirects', 'enable_indexnow', 'enable_robots_editor',
                'enable_link_checker', 'enable_internal_linker', 'enable_local_business',
                'enable_audit_log', 'enable_csp_headers', 'enable_sri',
                'delete_data_on_uninstall',
                'enable_schema_types', 'enable_qa_detector', 'enable_faq_generator',
                'enable_visibility_dashboard', 'enable_citation_graph', 'enable_authority_map',
                'enable_ai_engine', 'enable_content_decay', 'enable_cannibalization',
                'enable_advanced_linking',
                'enable_historical_analytics', 'enable_ga4', 'enable_cwv',
                'enable_custom_reports', 'enable_notifications',
                'enable_rbac', 'enable_multilang', 'enable_white_label', 'enable_scale',
            ],
            'seo' => [
                'enable_og', 'enable_twitter', 'enable_canonical', 'enable_robots_meta',
                'noindex_archives', 'noindex_search', 'noindex_404',
                'enable_breadcrumbs', 'enable_sitelinks',
                'enable_image_alt', 'redirect_attachments', 'enable_rss_footer',
            ],
            'aeo' => [
                'enable_faq_schema', 'enable_howto_schema', 'enable_speakable',
            ],
            'geo' => [
                'enable_llms_txt', 'enable_ai_meta', 'enable_entity_markup',
                'content_freshness', 'enable_rest_api',
                'enable_markdown_endpoints', 'enable_ai_crawler_log',
            ],
            'sitemap' => [
                'enable_news_sitemap', 'enable_video_sitemap',
            ],
            'ai' => [
                'enable_ai_engine',
            ],
            'rank' => [
                'enable_rank_tracking',
            ],
        ];

        // A known page manages only its own checkboxes; an unknown page marker
        // manages none (all preserved); no marker (import/REST) manages all.
        $has_page      = '' !== $page;
        $managed_bools = $page_bools[ $page ] ?? [];

        // All boolean keys.
        $bool_keys = [
            // Core
            'enable_seo', 'enable_og', 'enable_twitter', 'enable_canonical',
            'enable_robots_meta', 'noindex_archives', 'noindex_search', 'noindex_404',
            'enable_schema', 'enable_breadcrumbs', 'enable_sitelinks',
            'enable_aeo', 'enable_faq_schema', 'enable_howto_schema', 'enable_speakable',
            'enable_geo', 'enable_llms_txt', 'enable_ai_meta', 'enable_entity_markup',
            'content_freshness', 'enable_rest_api', 'enable_sitemap', 'enable_analyzer',
            'ping_search_engines', 'delete_data_on_uninstall',
            // Advanced SEO (v2.0)
            'enable_redirects', 'enable_indexnow', 'enable_robots_editor',
            'enable_link_checker', 'enable_internal_linker', 'enable_local_business',
            'enable_audit_log',
            'enable_csp_headers', 'enable_sri',
            // Advanced AEO (v2.1)
            'enable_schema_types', 'enable_qa_detector', 'enable_faq_generator',
            // Advanced GEO (v2.5)
            'enable_visibility_dashboard', 'enable_citation_graph', 'enable_authority_map',
            // Enterprise (v3.0)
            'enable_rbac', 'enable_settings_lock', 'enable_multilang',
            'enable_white_label', 'wl_hide_branding', 'enable_scale',
            // AI & Content Intelligence (v3.2)
            'enable_ai_engine', 'enable_content_decay', 'decay_auto_queue',
            'enable_cannibalization', 'enable_advanced_linking',
            // Analytics & Reporting (v3.3)
            'enable_historical_analytics', 'enable_ga4', 'enable_cwv',
            'enable_custom_reports', 'enable_notifications',
            // v3.5
            'enable_news_sitemap', 'enable_video_sitemap', 'enable_image_alt',
            'redirect_attachments', 'enable_rss_footer', 'enable_markdown_endpoints',
            'enable_ai_crawler_log', 'enable_rank_tracking',
        ];

        foreach ( $bool_keys as $key ) {
            if ( $has_page && ! in_array( $key, $managed_bools, true ) ) {
                // Not on this page — preserve existing value.
                $sanitized[ $key ] = ! empty( $existing[ $key ] );
            } else {
                // On this page (or no page marker) — use submitted value.
                $sanitized[ $key ] = ! empty( $input[ $key ] );
            }
        }

        // Text fields — use submitted value if present, otherwise preserve existing.
        $text_keys = [
            'separator', 'site_name', 'knowledge_type', 'knowledge_name',
            'knowledge_logo', 'twitter_card_type', 'twitter_site',
            'schema_article_type', 'author_expertise',
            'default_og_image', 'description_fallback', 'robots_default',
            'default_content_intent', 'default_audience',
            'ai_provider', 'ai_model', 'ai_endpoint',
            // v2.0+
            'indexnow_api_key', 'faq_ai_api_key',
            'gsc_access_token', 'gsc_site_url', 'bing_api_key', 'bing_site_url',
            'wl_plugin_name', 'wl_plugin_uri', 'wl_author_name', 'wl_author_uri',
            'wl_description', 'wl_logo_url', 'wl_brand_color',
            'notification_email', 'slack_webhook_url',
            'ga4_property_id', 'psi_api_key',
            // v3.5
            'news_publication_name',
        ];

        foreach ( $text_keys as $key ) {
            if ( isset( $input[ $key ] ) ) {
                $sanitized[ $key ] = sanitize_text_field( $input[ $key ] );
            } else {
                $sanitized[ $key ] = $existing[ $key ] ?? ( $defaults[ $key ] ?? '' );
            }
        }

        // Arrays — use submitted value if present, otherwise preserve existing.
        $array_keys = [
            'enabled_post_types', 'sitemap_post_types', 'sitemap_taxonomies',
            'indexnow_providers', 'local_business_locations',
            'news_post_types',
        ];
        foreach ( $array_keys as $key ) {
            if ( isset( $input[ $key ] ) && is_array( $input[ $key ] ) ) {
                if ( 'local_business_locations' === $key ) {
                    $sanitized[ $key ] = map_deep( wp_unslash( $input[ $key ] ), 'sanitize_text_field' );
                } else {
                    $sanitized[ $key ] = array_map( 'sanitize_text_field', $input[ $key ] );
                }
            } else {
                $sanitized[ $key ] = $existing[ $key ] ?? $defaults[ $key ];
            }
        }

        // Numeric (int) — use submitted value if present, otherwise preserve existing.
        $int_keys = [
            'sitemap_max_urls'    => 2000,
            'target_readability'  => 60,
            'ai_rate_limit'       => 30,
            'ai_token_budget'     => 0,
            'description_max_length' => 160,
            'ai_summary_word_count'  => 50,
            'alert_threshold_seo'    => 50,
            'alert_threshold_aeo'    => 50,
            'alert_threshold_geo'    => 50,
            'decay_lookback_days'    => 30,
        ];
        foreach ( $int_keys as $key => $fallback ) {
            if ( isset( $input[ $key ] ) ) {
                $sanitized[ $key ] = absint( $input[ $key ] );
            } else {
                $sanitized[ $key ] = (int) ( $existing[ $key ] ?? $fallback );
            }
        }

        // Numeric (float)
        if ( isset( $input['decay_threshold'] ) ) {
            $sanitized['decay_threshold'] = (float) $input['decay_threshold'];
        } else {
            $sanitized['decay_threshold'] = (float) ( $existing['decay_threshold'] ?? 10.0 );
        }

        // Speakable selectors
        if ( isset( $input['speakable_selectors'] ) ) {
            $raw = $input['speakable_selectors'];
            if ( is_array( $raw ) ) {
                $sanitized['speakable_selectors'] = array_filter( array_map( 'sanitize_text_field', $raw ) );
            } else {
                $sanitized['speakable_selectors'] = array_filter( array_map( 'sanitize_text_field', explode( "\n", $raw ) ) );
            }
        } else {
            $sanitized['speakable_selectors'] = $existing['speakable_selectors'] ?? $defaults['speakable_selectors'];
        }

        // RSS footer (limited HTML allowed).
        if ( isset( $input['rss_after_content'] ) ) {
            $sanitized['rss_after_content'] = wp_kses_post( $input['rss_after_content'] );
        } else {
            $sanitized['rss_after_content'] = $existing['rss_after_content'] ?? $defaults['rss_after_content'];
        }

        // AI crawler rules: bot token => allow|block.
        if ( isset( $input['ai_crawler_rules'] ) && is_array( $input['ai_crawler_rules'] ) ) {
            $rules = [];
            foreach ( $input['ai_crawler_rules'] as $bot => $rule ) {
                $bot = preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $bot );
                if ( '' !== $bot ) {
                    $rules[ $bot ] = 'block' === $rule ? 'block' : 'allow';
                }
            }
            $sanitized['ai_crawler_rules'] = $rules;
        } else {
            $sanitized['ai_crawler_rules'] = $existing['ai_crawler_rules'] ?? $defaults['ai_crawler_rules'];
        }

        // Tracked keywords: one per line.
        if ( isset( $input['tracked_keywords'] ) ) {
            $raw  = is_array( $input['tracked_keywords'] ) ? $input['tracked_keywords'] : explode( "\n", (string) $input['tracked_keywords'] );
            $list = array_values( array_unique( array_filter( array_map( static function ( $kw ) {
                return mb_strtolower( trim( sanitize_text_field( $kw ) ) );
            }, $raw ) ) ) );
            $sanitized['tracked_keywords'] = array_slice( $list, 0, 500 );
        } else {
            $sanitized['tracked_keywords'] = $existing['tracked_keywords'] ?? $defaults['tracked_keywords'];
        }

        // Title templates (associative array of template strings).
        if ( isset( $input['title_templates'] ) && is_array( $input['title_templates'] ) ) {
            // Not sanitize_text_field(): it strips "%ca"/"%se"-style sequences as
            // URL-encoded octets, which mangles %category%, %search_query% and %sep%.
            $sanitized['title_templates'] = array_map( static function ( $template ) {
                $template = wp_strip_all_tags( (string) $template );
                return trim( (string) preg_replace( '/[\r\n\t]+/', ' ', $template ) );
            }, wp_unslash( $input['title_templates'] ) );
        } else {
            $sanitized['title_templates'] = $existing['title_templates'] ?? $defaults['title_templates'];
        }

        return $sanitized;
    }

    /**
     * Sanitize social profiles.
     */
    public function sanitize_social_profiles( $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }
        return array_map( 'esc_url_raw', $input );
    }

    /**
     * Enqueue admin CSS and JS.
     */
    public function enqueue_assets( string $hook ): void {
        // Settings pages
        if ( strpos( $hook, 'jat-search-intel' ) !== false ) {
            wp_enqueue_style(
                'jat-search-intel-admin',
                JAT_SEARCH_INTEL_URL . 'admin/css/admin-style.css',
                [],
                JAT_SEARCH_INTEL_VERSION
            );
            wp_enqueue_script(
                'jat-search-intel-settings',
                JAT_SEARCH_INTEL_URL . 'admin/js/settings.js',
                [],
                JAT_SEARCH_INTEL_VERSION,
                true
            );
        }

        // Bulk Tools page — enqueue batch processing JS.
        if ( strpos( $hook, 'jat-search-intel-bulk-tools' ) !== false && $this->core->bulk_tools ) {
            $this->core->bulk_tools->enqueue_assets();
        }

        // Post editor — only on post.php / post-new.php for enabled post types
        $screen = get_current_screen();
        if ( $screen && in_array( $screen->base, [ 'post', 'post-new' ], true )
            && in_array( $screen->post_type, $this->settings['enabled_post_types'] ?? [ 'post', 'page' ], true ) ) {
            wp_enqueue_style(
                'jat-search-intel-admin',
                JAT_SEARCH_INTEL_URL . 'admin/css/admin-style.css',
                [],
                JAT_SEARCH_INTEL_VERSION
            );

            wp_enqueue_media();

            wp_enqueue_script(
                'jat-search-intel-admin',
                JAT_SEARCH_INTEL_URL . 'admin/js/admin-script.js',
                [ 'jquery', 'wp-data' ],
                JAT_SEARCH_INTEL_VERSION,
                true
            );

            wp_localize_script( 'jat-search-intel-admin', 'jatSearchIntel', [
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'jat_search_intel_nonce' ),
                'faqNonce'     => wp_create_nonce( 'si_faq_nonce' ),
                'postId'       => get_the_ID(),
                'aiConfigured' => $this->core->ai_engine->is_configured(),
                'i18n'         => [
                    'analyzing' => __( 'Analyzing...', 'jat-search-intel' ),
                    'seoScore'  => __( 'SEO Score', 'jat-search-intel' ),
                    'aeoScore'  => __( 'AEO Score', 'jat-search-intel' ),
                    'geoScore'  => __( 'GEO Score', 'jat-search-intel' ),
                ],
            ] );
        }
    }

    /**
     * Register post editor metaboxes.
     */
    public function register_metaboxes(): void {
        $post_types = $this->settings['enabled_post_types'] ?? [ 'post', 'page' ];

        foreach ( $post_types as $pt ) {
            add_meta_box(
                'jat-search-intel-seo',
                __( 'JAT Search Intel — SEO, AEO & GEO', 'jat-search-intel' ),
                [ $this, 'render_metabox' ],
                $pt,
                'normal',
                'high'
            );
        }
    }

    /**
     * Render the post editor metabox.
     */
    public function render_metabox( $post ): void {
        wp_nonce_field( 'jat_search_intel_metabox', 'jat_search_intel_metabox_nonce' );

        $meta = [
            'title'                 => get_post_meta( $post->ID, '_jat_search_intel_title', true ),
            'description'           => get_post_meta( $post->ID, '_jat_search_intel_description', true ),
            'focus_keyword'         => get_post_meta( $post->ID, '_jat_search_intel_focus_keyword', true ),
            'canonical'             => get_post_meta( $post->ID, '_jat_search_intel_canonical', true ),
            'noindex'               => get_post_meta( $post->ID, '_jat_search_intel_noindex', true ),
            'nofollow'              => get_post_meta( $post->ID, '_jat_search_intel_nofollow', true ),
            'schema_type'           => get_post_meta( $post->ID, '_jat_search_intel_schema_type', true ),
            'ai_summary'            => get_post_meta( $post->ID, '_jat_search_intel_ai_summary', true ),
            'ai_noindex'            => get_post_meta( $post->ID, '_jat_search_intel_ai_noindex', true ),
            'expertise_level'       => get_post_meta( $post->ID, '_jat_search_intel_expertise_level', true ),
            'content_type'          => get_post_meta( $post->ID, '_jat_search_intel_content_type', true ),
            'fact_checked'          => get_post_meta( $post->ID, '_jat_search_intel_fact_checked', true ),
            'social_title'          => get_post_meta( $post->ID, '_jat_search_intel_social_title', true ),
            'social_description'    => get_post_meta( $post->ID, '_jat_search_intel_social_description', true ),
            'social_image'          => get_post_meta( $post->ID, '_jat_search_intel_social_image', true ),
            'secondary_keywords'    => get_post_meta( $post->ID, '_jat_search_intel_secondary_keywords', true ),
            'direct_answer'         => get_post_meta( $post->ID, '_jat_search_intel_direct_answer', true ),
            'author_expertise_notes'=> get_post_meta( $post->ID, '_jat_search_intel_author_expertise_notes', true ),
            'content_intent'        => get_post_meta( $post->ID, '_jat_search_intel_content_intent', true ),
            'audience'              => get_post_meta( $post->ID, '_jat_search_intel_audience', true ),
            'local_relevance'       => get_post_meta( $post->ID, '_jat_search_intel_local_relevance', true ),
            'local_location'        => get_post_meta( $post->ID, '_jat_search_intel_local_location', true ),
        ];

        include JAT_SEARCH_INTEL_DIR . 'admin/views/metabox.php';
    }

    /**
     * Save metabox data.
     */
    public function save_metabox_data( int $post_id, $post ): void {
        if ( ! isset( $_POST['jat_search_intel_metabox_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jat_search_intel_metabox_nonce'] ) ), 'jat_search_intel_metabox' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Text fields
        $text_fields = [
            '_jat_search_intel_title', '_jat_search_intel_description', '_jat_search_intel_focus_keyword',
            '_jat_search_intel_canonical', '_jat_search_intel_schema_type', '_jat_search_intel_ai_summary',
            '_jat_search_intel_expertise_level', '_jat_search_intel_content_type', '_jat_search_intel_fact_checked',
            '_jat_search_intel_social_title', '_jat_search_intel_social_description', '_jat_search_intel_social_image',
            '_jat_search_intel_secondary_keywords', '_jat_search_intel_direct_answer',
            '_jat_search_intel_author_expertise_notes', '_jat_search_intel_content_intent',
            '_jat_search_intel_audience', '_jat_search_intel_local_location',
        ];

        foreach ( $text_fields as $field ) {
            $key = str_replace( '_jat_search_intel_', 'si_', $field );
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
            }
        }

        // Checkboxes
        $checkboxes = [ '_jat_search_intel_noindex', '_jat_search_intel_nofollow', '_jat_search_intel_ai_noindex', '_jat_search_intel_local_relevance' ];
        foreach ( $checkboxes as $field ) {
            $key = str_replace( '_jat_search_intel_', 'si_', $field );
            update_post_meta( $post_id, $field, ! empty( $_POST[ $key ] ) ? '1' : '' );
        }

        // FAQs (JSON from repeater)
        if ( isset( $_POST['si_faqs'] ) ) {
            $faqs = json_decode( wp_unslash( $_POST['si_faqs'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded/split, then every element is sanitized.
            if ( is_array( $faqs ) ) {
                $sanitized_faqs = array_map( function ( $faq ) {
                    return [
                        'question' => sanitize_text_field( $faq['question'] ?? '' ),
                        'answer'   => wp_kses_post( $faq['answer'] ?? '' ),
                    ];
                }, $faqs );
                update_post_meta( $post_id, '_jat_search_intel_faqs', $sanitized_faqs );
            }
        }

        // Topics (comma-separated)
        if ( isset( $_POST['si_topics'] ) ) {
            $topics = array_filter( array_map( 'sanitize_text_field', explode( ',', wp_unslash( $_POST['si_topics'] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded/split, then every element is sanitized.
            update_post_meta( $post_id, '_jat_search_intel_topics', $topics );
        }

        // Entities (JSON)
        if ( isset( $_POST['si_entities'] ) ) {
            $entities = json_decode( wp_unslash( $_POST['si_entities'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded/split, then every element is sanitized.
            if ( is_array( $entities ) ) {
                $sanitized = array_map( function ( $entity ) {
                    return [
                        'name' => sanitize_text_field( $entity['name'] ?? '' ),
                        'type' => sanitize_text_field( $entity['type'] ?? 'Thing' ),
                        'url'  => esc_url_raw( $entity['url'] ?? '' ),
                    ];
                }, $entities );
                update_post_meta( $post_id, '_jat_search_intel_entities', $sanitized );
            }
        }

        // Citations (JSON from repeater)
        if ( isset( $_POST['si_citations'] ) ) {
            $citations = json_decode( wp_unslash( $_POST['si_citations'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded/split, then every element is sanitized.
            if ( is_array( $citations ) ) {
                $sanitized_citations = array_map( function ( $cite ) {
                    return [
                        'source' => sanitize_text_field( $cite['source'] ?? '' ),
                        'url'    => esc_url_raw( $cite['url'] ?? '' ),
                        'date'   => sanitize_text_field( $cite['date'] ?? '' ),
                        'quote'  => sanitize_text_field( $cite['quote'] ?? '' ),
                    ];
                }, $citations );
                update_post_meta( $post_id, '_jat_search_intel_citations', $sanitized_citations );
            }
        }
    }

    /**
     * Add custom columns to post lists.
     */
    public function add_admin_columns( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['si_scores'] = __( 'JAT Search Intel', 'jat-search-intel' );
            }
        }
        return $new;
    }

    /**
     * Render custom column content.
     */
    public function render_admin_columns( string $column, int $post_id ): void {
        if ( 'si_scores' !== $column ) {
            return;
        }

        $keyword = get_post_meta( $post_id, '_jat_search_intel_focus_keyword', true );
        $title   = get_post_meta( $post_id, '_jat_search_intel_title', true ) ?: get_the_title( $post_id );
        $desc    = get_post_meta( $post_id, '_jat_search_intel_description', true );

        // Quick score calculation
        $has_title = ! empty( get_post_meta( $post_id, '_jat_search_intel_title', true ) );
        $has_desc  = ! empty( $desc );
        $has_kw    = ! empty( $keyword );
        $has_ai    = ! empty( get_post_meta( $post_id, '_jat_search_intel_ai_summary', true ) );

        $items = array_filter( [ $has_title, $has_desc, $has_kw, $has_ai ] );
        $fill  = count( $items );

        $color = $fill >= 3 ? '#00a32a' : ( $fill >= 2 ? '#dba617' : '#d63638' );

        echo '<span style="color:' . esc_attr( $color ) . '; font-weight:600;">';
        echo esc_html( $fill . '/4' );
        echo '</span>';

        if ( $keyword ) {
            echo '<br><small>' . esc_html( $keyword ) . '</small>';
        }
    }

    /**
     * Plugin action links.
     */
    public function plugin_action_links( array $links ): array {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=jat-search-intel' ) . '">' . __( 'Settings', 'jat-search-intel' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Render user E-E-A-T fields on profile page.
     */
    public function render_user_eeat_fields( $user ): void {
        ?>
        <h2><?php esc_html_e( 'JAT Search Intel — E-E-A-T Author Data', 'jat-search-intel' ); ?></h2>
        <?php wp_nonce_field( 'jat_search_intel_user_eeat', 'jat_search_intel_user_eeat_nonce' ); ?>
        <p class="description"><?php esc_html_e( 'This information enhances your author authority signals for search engines and AI systems.', 'jat-search-intel' ); ?></p>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Data Consent', 'jat-search-intel' ); ?></th>
                <td>
                    <label for="si_eeat_consent">
                        <input type="checkbox" name="si_eeat_consent" id="si_eeat_consent" value="1" <?php checked( get_user_meta( $user->ID, 'jat_search_intel_eeat_consent', true ), '1' ); ?> />
                        <?php esc_html_e( 'I consent to JAT Search Intel storing and displaying my E-E-A-T author data as structured data (JSON-LD) on published content.', 'jat-search-intel' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Your credentials, expertise, and profile URLs will appear in the HTML source of posts you author. You can withdraw consent at any time by unchecking this box.', 'jat-search-intel' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="si_credentials"><?php esc_html_e( 'Credentials / Qualifications', 'jat-search-intel' ); ?></label></th>
                <td>
                    <textarea name="si_credentials" id="si_credentials" rows="3" cols="50"><?php echo esc_textarea( implode( "\n", (array) get_user_meta( $user->ID, 'jat_search_intel_credentials', true ) ) ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'One per line (e.g., "PhD in Computer Science", "Certified Financial Planner")', 'jat-search-intel' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="si_expertise_areas"><?php esc_html_e( 'Areas of Expertise', 'jat-search-intel' ); ?></label></th>
                <td>
                    <textarea name="si_expertise_areas" id="si_expertise_areas" rows="3" cols="50"><?php echo esc_textarea( implode( "\n", (array) get_user_meta( $user->ID, 'jat_search_intel_expertise_areas', true ) ) ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'One per line (e.g., "Machine Learning", "Digital Marketing")', 'jat-search-intel' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="si_years_experience"><?php esc_html_e( 'Years of Experience', 'jat-search-intel' ); ?></label></th>
                <td>
                    <input type="number" name="si_years_experience" id="si_years_experience" value="<?php echo esc_attr( get_user_meta( $user->ID, 'jat_search_intel_years_experience', true ) ); ?>" min="0" max="99" />
                </td>
            </tr>
            <tr>
                <th><label for="si_social_profiles"><?php esc_html_e( 'Social / Professional Profiles', 'jat-search-intel' ); ?></label></th>
                <td>
                    <textarea name="si_social_profiles" id="si_social_profiles" rows="4" cols="50"><?php echo esc_textarea( implode( "\n", (array) get_user_meta( $user->ID, 'jat_search_intel_social_profiles', true ) ) ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'One URL per line (LinkedIn, GitHub, Google Scholar, etc.)', 'jat-search-intel' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user E-E-A-T fields.
     */
    public function save_user_eeat_fields( int $user_id ): void {
        if ( ! isset( $_POST['jat_search_intel_user_eeat_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jat_search_intel_user_eeat_nonce'] ) ), 'jat_search_intel_user_eeat' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        // E-E-A-T consent.
        update_user_meta( $user_id, 'jat_search_intel_eeat_consent', isset( $_POST['si_eeat_consent'] ) ? '1' : '' );

        // Credentials
        if ( isset( $_POST['si_credentials'] ) ) {
            $creds = array_filter( array_map( 'sanitize_text_field', explode( "\n", wp_unslash( $_POST['si_credentials'] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded/split, then every element is sanitized.
            update_user_meta( $user_id, 'jat_search_intel_credentials', $creds );
        }

        // Expertise areas
        if ( isset( $_POST['si_expertise_areas'] ) ) {
            $areas = array_filter( array_map( 'sanitize_text_field', explode( "\n", wp_unslash( $_POST['si_expertise_areas'] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded/split, then every element is sanitized.
            update_user_meta( $user_id, 'jat_search_intel_expertise_areas', $areas );
        }

        // Years experience
        if ( isset( $_POST['si_years_experience'] ) ) {
            update_user_meta( $user_id, 'jat_search_intel_years_experience', absint( wp_unslash( $_POST['si_years_experience'] ) ) );
        }

        // Social profiles
        if ( isset( $_POST['si_social_profiles'] ) ) {
            $profiles = array_filter( array_map( 'esc_url_raw', explode( "\n", wp_unslash( $_POST['si_social_profiles'] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded/split, then every element is sanitized.
            update_user_meta( $user_id, 'jat_search_intel_social_profiles', $profiles );
        }
    }

    /**
     * Render the main settings page.
     */
    public function render_settings_page(): void {
        include JAT_SEARCH_INTEL_DIR . 'admin/views/settings-general.php';
    }

    /**
     * Render SEO settings page.
     */
    public function render_seo_settings_page(): void {
        include JAT_SEARCH_INTEL_DIR . 'admin/views/settings-seo.php';
    }

    /**
     * Render AEO settings page.
     */
    public function render_aeo_settings_page(): void {
        include JAT_SEARCH_INTEL_DIR . 'admin/views/settings-aeo.php';
    }

    /**
     * Render GEO settings page.
     */
    public function render_geo_settings_page(): void {
        include JAT_SEARCH_INTEL_DIR . 'admin/views/settings-geo.php';
    }

    /**
     * Render Sitemap settings page.
     */
    public function render_sitemap_settings_page(): void {
        include JAT_SEARCH_INTEL_DIR . 'admin/views/settings-sitemap.php';
    }

    /**
     * Render Social settings page.
     */
    public function render_social_settings_page(): void {
        include JAT_SEARCH_INTEL_DIR . 'admin/views/settings-social.php';
    }

    /**
     * AJAX: Save an AI API key (encrypted storage).
     */
    public function ajax_save_ai_api_key(): void {
        check_ajax_referer( 'si_ai_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );
        $api_key  = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

        $valid_providers = [ 'openai', 'anthropic', 'ollama' ];
        if ( ! in_array( $provider, $valid_providers, true ) ) {
            wp_send_json_error( [ 'message' => 'Invalid provider.' ] );
        }

        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => 'API key cannot be empty.' ] );
        }

        $this->core->ai_engine->save_api_key( $provider, $api_key );

        wp_send_json_success( [ 'message' => 'API key saved.' ] );
    }

    /**
     * AJAX: Test AI provider connection with a simple prompt.
     */
    public function ajax_test_ai_connection(): void {
        check_ajax_referer( 'si_ai_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );

        // Temporarily override the active provider for this test.
        $settings                = $this->settings;
        $settings['ai_provider'] = $provider;
        $test_engine             = new AI_Engine( $settings );

        if ( 'ollama' !== $provider && $test_engine->get_api_key( $provider ) === '' ) {
            wp_send_json_error( [ 'message' => 'No API key saved for this provider. Save a key first.' ] );
        }

        $result = $test_engine->chat(
            'You are a helpful assistant. Respond with exactly: OK',
            'Say OK.',
            [ 'max_tokens' => 10, 'temperature' => 0 ]
        );

        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => 'Connected! Response: ' . mb_substr( $result['text'], 0, 50 ) ] );
        } else {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }
    }

    /**
     * Render AI Engine settings page.
     */
    public function render_ai_settings_page(): void {
        include JAT_SEARCH_INTEL_DIR . 'admin/views/settings-ai.php';
    }

    /**
     * Render Bulk Tools page.
     */
    public function render_bulk_tools_page(): void {
        include JAT_SEARCH_INTEL_DIR . 'admin/views/bulk-tools.php';
    }
}
