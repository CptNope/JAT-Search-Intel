<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Core orchestrator class for Search Intel plugin.
 */
class Core {

    /** @var Core|null Singleton instance */
    private static $instance = null;

    /** @var SEO_Manager */
    public $seo;

    /** @var Schema_Manager */
    public $schema;

    /** @var AEO_Manager */
    public $aeo;

    /** @var GEO_Manager */
    public $geo;

    /** @var Content_Analyzer */
    public $analyzer;

    /** @var Sitemap */
    public $sitemap;

    /** @var Title_Engine */
    public $title_engine;

    /** @var Admin */
    public $admin;

    /** @var Rest_Api */
    public $rest_api;

    /** @var Bulk_Tools */
    public $bulk_tools;

    /** @var Blocks */
    public $blocks;

    /** @var Privacy */
    public $privacy;

    /** @var Redirect_Manager */
    public $redirects;

    /** @var IndexNow */
    public $indexnow;

    /** @var WooCommerce */
    public $woocommerce;

    /** @var Local_Business */
    public $local_business;

    /** @var Robots_Txt */
    public $robots_txt;

    /** @var Audit_Log */
    public $audit_log;

    /**
     * Get singleton instance.
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — boot all modules.
     */
    private function __construct() {
        $this->load_textdomain();

        $settings = $this->get_settings();

        $this->seo          = new SEO_Manager( $settings );
        $this->title_engine  = new Title_Engine( $settings );
        $this->schema        = new Schema_Manager( $settings );
        $this->aeo           = new AEO_Manager( $settings );
        $this->geo           = new GEO_Manager( $settings );
        $this->analyzer      = new Content_Analyzer( $settings );
        $this->sitemap       = new Sitemap( $settings );

        if ( is_admin() ) {
            $this->admin      = new Admin( $settings, $this );
            $this->bulk_tools = new Bulk_Tools( $settings, $this );
        }

        // REST API — registers on rest_api_init.
        $this->rest_api = new Rest_Api( $settings, $this );

        // Block editor integration — sidebar, custom blocks, meta registration.
        $this->blocks = new Blocks();

        // Privacy & GDPR compliance — exporters, erasers, privacy policy.
        $this->privacy = new Privacy();

        // Redirect Manager — 301/302/307/410 with exact, prefix, regex matching.
        $this->redirects = new Redirect_Manager( $settings );

        // IndexNow — ping search engines on publish/update.
        $this->indexnow = new IndexNow( $settings );

        // WooCommerce — product schema, breadcrumbs, sitemap (only when WC active).
        $this->woocommerce = new WooCommerce( $settings );

        // Local Business schema — address, hours, geo, multi-location.
        $this->local_business = new Local_Business( $settings );

        // Robots.txt visual editor.
        $this->robots_txt = new Robots_Txt( $settings );

        // Audit log for settings changes.
        $this->audit_log = new Audit_Log( $settings );

        // Auto-redirect on slug change.
        add_action( 'post_updated', [ $this->redirects, 'detect_slug_change' ], 10, 3 );

        // Prefetch all post meta on singular pages to avoid N+1 queries
        add_action( 'wp', [ $this, 'prefetch_post_meta' ] );

        // Compatibility checks
        add_action( 'admin_init', [ $this, 'check_compatibility' ] );
    }

    /**
     * Prime the WP post meta cache for the current singular post.
     *
     * On a typical singular page, SEO_Manager, Schema_Manager, AEO_Manager,
     * and GEO_Manager each call get_post_meta() for 5-10 different keys.
     * WordPress caches meta per-post after the first call, but only if the
     * full meta for that post has been loaded. update_meta_cache() ensures
     * a single SELECT loads all meta keys at once — saving ~20 individual
     * queries down to 1.
     */
    public function prefetch_post_meta(): void {
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id ) {
                update_meta_cache( 'post', [ $post_id ] );
            }
        }
    }

    /**
     * Run compatibility checks and show admin notices for conflicts.
     */
    public function check_compatibility(): void {
        // Only show to admins
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $conflicts = $this->get_conflicting_plugins();
        if ( ! empty( $conflicts ) ) {
            add_action( 'admin_notices', function () use ( $conflicts ) {
                echo '<div class="notice notice-warning is-dismissible"><p>';
                echo '<strong>' . esc_html__( 'Search Intel:', 'search-intel' ) . '</strong> ';
                printf(
                    /* translators: %s: comma-separated list of plugin names */
                    esc_html__( 'Potential SEO plugin conflicts detected: %s. Consider disabling overlapping features to avoid duplicate meta tags.', 'search-intel' ),
                    '<strong>' . esc_html( implode( ', ', $conflicts ) ) . '</strong>'
                );
                echo '</p></div>';
            } );
        }
    }

    /**
     * Detect active plugins that may conflict with Search Intel.
     *
     * @return string[] Names of conflicting plugins.
     */
    private function get_conflicting_plugins(): array {
        $known = [
            'wordpress-seo/wp-seo.php'              => 'Yoast SEO',
            'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
            'seo-by-rank-math/rank-math.php'         => 'Rank Math',
            'autodescription/autodescription.php'     => 'The SEO Framework',
            'flavor/flavor.php'                       => 'flavor',
        ];

        $conflicts = [];

        if ( function_exists( 'is_plugin_active' ) ) {
            foreach ( $known as $slug => $name ) {
                if ( is_plugin_active( $slug ) ) {
                    $conflicts[] = $name;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Load plugin text domain.
     */
    private function load_textdomain(): void {
        load_plugin_textdomain( 'search-intel', false, dirname( SEARCH_INTEL_BASENAME ) . '/languages' );
    }

    /**
     * Get merged settings.
     */
    public function get_settings(): array {
        $saved = get_option( 'search_intel_settings', [] );
        return wp_parse_args( $saved, self::get_default_settings() );
    }

    /**
     * Default settings for the plugin.
     */
    public static function get_default_settings(): array {
        return [
            // General
            'separator'              => '|',
            'site_name'              => get_bloginfo( 'name' ),
            'knowledge_type'         => 'Organization',
            'knowledge_name'         => get_bloginfo( 'name' ),
            'knowledge_logo'         => '',

            // SEO
            'enable_seo'             => true,
            'enable_og'              => true,
            'enable_twitter'         => true,
            'twitter_card_type'      => 'summary_large_image',
            'twitter_site'           => '',
            'enable_canonical'       => true,
            'enable_robots_meta'     => true,
            'noindex_archives'       => false,
            'noindex_search'         => true,
            'noindex_404'            => true,

            // Schema
            'enable_schema'          => true,
            'schema_article_type'    => 'Article',
            'enable_breadcrumbs'     => true,
            'enable_sitelinks'       => true,

            // AEO
            'enable_aeo'             => true,
            'enable_faq_schema'      => true,
            'enable_howto_schema'    => true,
            'enable_speakable'       => true,
            'speakable_selectors'    => [ '.entry-title', '.entry-content p:first-of-type' ],

            // SEO — expanded
            'default_og_image'       => '',
            'description_fallback'   => 'excerpt',
            'description_max_length' => 160,
            'robots_default'         => 'index',
            'ping_search_engines'    => false,

            // GEO
            'enable_geo'             => true,
            'enable_llms_txt'        => true,
            'enable_ai_meta'         => true,
            'enable_entity_markup'   => true,
            'author_expertise'       => '',
            'content_freshness'      => true,
            'ai_summary_word_count'  => 50,
            'default_content_intent' => '',
            'default_audience'       => '',
            'enable_rest_api'        => true,

            // Sitemap
            'enable_sitemap'         => true,
            'sitemap_post_types'     => [ 'post', 'page' ],
            'sitemap_taxonomies'     => [ 'category' ],
            'sitemap_max_urls'       => 2000,

            // Content Analyzer
            'enable_analyzer'        => true,
            'target_readability'     => 60,

            // Title Templates
            'title_templates'        => Title_Engine::get_default_templates(),

            // Post types to enable on
            'enabled_post_types'     => [ 'post', 'page' ],

            // Redirect Manager
            'enable_redirects'       => true,

            // IndexNow
            'enable_indexnow'        => false,
            'indexnow_api_key'       => '',
            'indexnow_providers'     => [ 'bing', 'yandex', 'naver' ],

            // Local Business
            'enable_local_business'  => false,
            'local_business_locations' => [],

            // Robots.txt Editor
            'enable_robots_editor'   => false,

            // Audit Log
            'enable_audit_log'       => true,
        ];
    }
}
