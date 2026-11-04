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

    /** @var Link_Checker */
    public $link_checker;

    /** @var Internal_Linker */
    public $internal_linker;

    /** @var CSP_Headers */
    public $csp_headers;

    /** @var SRI */
    public $sri;

    /** @var Schema_Types */
    public $schema_types;

    /** @var QA_Detector */
    public $qa_detector;

    /** @var FAQ_Generator */
    public $faq_generator;

    /** @var Visibility_Dashboard */
    public $visibility_dashboard;

    /** @var Citation_Graph */
    public $citation_graph;

    /** @var Authority_Map */
    public $authority_map;

    /** @var Multisite */
    public $multisite;

    /** @var Migration */
    public $migration;

    /** @var RBAC */
    public $rbac;

    /** @var Multilang */
    public $multilang;

    /** @var White_Label */
    public $white_label;

    /** @var Scale */
    public $scale;

    /** @var MainWP_Reporter */
    public $mainwp_reporter;

    /** @var Notifications */
    public $notifications;

    /** @var AI_Engine */
    public $ai_engine;

    /** @var Content_Decay */
    public $content_decay;

    /** @var Keyword_Cannibalization */
    public $keyword_cannibalization;

    /** @var Advanced_Linking */
    public $advanced_linking;

    /** @var Historical_Analytics */
    public $historical_analytics;

    /** @var GA4_Integration */
    public $ga4_integration;

    /** @var Core_Web_Vitals */
    public $core_web_vitals;

    /** @var Custom_Reports */
    public $custom_reports;

    /** @var AI_Optimizer */
    public $ai_optimizer;

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
    /** @var array<string,mixed> Settings snapshot used to build lazy modules. */
    private $boot_settings = [];

    /** @var array<string,callable> Factories for modules not built at boot. */
    private $lazy_factories = [];

    /**
     * Constructor — boot modules.
     *
     * Modules whose feature toggle is off register no hooks, so constructing
     * them on every request only costs file loads and allocations. Those are
     * deferred: they are built the first time something reads the property
     * (see __get()), which keeps $core->module access working everywhere.
     */
    private function __construct() {
        $settings            = $this->get_settings();
        $this->boot_settings = $settings;
        $is_admin            = is_admin();
        $on                  = static function ( string $key ) use ( $settings ): bool {
            return ! empty( $settings[ $key ] );
        };

        // Always-on front-end output and editor integration.
        $this->seo          = new SEO_Manager( $settings );
        $this->title_engine = new Title_Engine( $settings );
        $this->schema       = new Schema_Manager( $settings );
        $this->aeo          = new AEO_Manager( $settings );
        $this->geo          = new GEO_Manager( $settings );
        $this->analyzer     = new Content_Analyzer( $settings );
        $this->sitemap      = new Sitemap( $settings );

        if ( $is_admin ) {
            $this->admin      = new Admin( $settings, $this );
            $this->bulk_tools = new Bulk_Tools( $settings, $this );
        }

        // REST API — registers on rest_api_init.
        $this->rest_api = new Rest_Api( $settings, $this );

        // Block editor integration — sidebar, custom blocks, meta registration.
        $this->blocks = new Blocks();

        // Redirect Manager — also needed for slug-change detection on every save.
        $this->redirects = new Redirect_Manager( $settings );

        // WooCommerce / MainWP / Multisite gate themselves on their environment.
        $this->woocommerce     = new WooCommerce( $settings );
        $this->mainwp_reporter = new MainWP_Reporter( $settings );

        // AI Optimizer is on unless explicitly disabled.
        $this->ai_optimizer = new AI_Optimizer( $settings, $this );

        /*
         * Conditionally-booted modules: property => [ boot now?, factory ].
         * "Boot now" mirrors exactly when the module's constructor would add hooks.
         */
        $modules = [
            'privacy'                 => [ $is_admin, function () { return new Privacy(); } ],
            'migration'               => [ $is_admin, function () use ( $settings ) { return new Migration( $settings ); } ],
            'robots_txt'              => [ $is_admin || $on( 'enable_robots_editor' ), function () use ( $settings ) { return new Robots_Txt( $settings ); } ],
            'faq_generator'           => [ $is_admin, function () use ( $settings ) { return new FAQ_Generator( $settings, $this->qa_detector ); } ],
            'multisite'               => [ is_multisite(), function () use ( $settings ) { return new Multisite( $settings ); } ],
            'indexnow'                => [ $on( 'enable_indexnow' ) || $on( 'indexnow_api_key' ), function () use ( $settings ) { return new IndexNow( $settings ); } ],
            'local_business'          => [ $on( 'enable_local_business' ), function () use ( $settings ) { return new Local_Business( $settings ); } ],
            'audit_log'               => [ $on( 'enable_audit_log' ), function () use ( $settings ) { return new Audit_Log( $settings ); } ],
            'link_checker'            => [ $on( 'enable_link_checker' ), function () use ( $settings ) { return new Link_Checker( $settings ); } ],
            'internal_linker'         => [ $on( 'enable_internal_linker' ), function () use ( $settings ) { return new Internal_Linker( $settings ); } ],
            'csp_headers'             => [ $on( 'enable_csp_headers' ), function () use ( $settings ) { return new CSP_Headers( $settings ); } ],
            'sri'                     => [ $on( 'enable_sri' ), function () use ( $settings ) { return new SRI( $settings ); } ],
            'schema_types'            => [ $on( 'enable_schema_types' ), function () use ( $settings ) { return new Schema_Types( $settings ); } ],
            'qa_detector'             => [ $on( 'enable_qa_detector' ), function () use ( $settings ) { return new QA_Detector( $settings ); } ],
            'visibility_dashboard'    => [ $on( 'enable_visibility_dashboard' ), function () use ( $settings ) { return new Visibility_Dashboard( $settings ); } ],
            'citation_graph'          => [ $on( 'enable_citation_graph' ), function () use ( $settings ) { return new Citation_Graph( $settings ); } ],
            'authority_map'           => [ $on( 'enable_authority_map' ), function () use ( $settings ) { return new Authority_Map( $settings ); } ],
            'rbac'                    => [ $on( 'enable_rbac' ), function () use ( $settings ) { return new RBAC( $settings ); } ],
            'multilang'               => [ $on( 'enable_multilang' ), function () use ( $settings ) { return new Multilang( $settings ); } ],
            'white_label'             => [ $on( 'enable_white_label' ), function () use ( $settings ) { return new White_Label( $settings ); } ],
            'scale'                   => [ $on( 'enable_scale' ), function () use ( $settings ) { return new Scale( $settings ); } ],
            'notifications'           => [ $on( 'enable_notifications' ), function () use ( $settings ) { return new Notifications( $settings ); } ],
            'ai_engine'               => [ false, function () use ( $settings ) { return new AI_Engine( $settings ); } ],
            'content_decay'           => [ $on( 'enable_content_decay' ), function () use ( $settings ) { return new Content_Decay( $settings ); } ],
            'keyword_cannibalization' => [ $on( 'enable_cannibalization' ), function () use ( $settings ) { return new Keyword_Cannibalization( $settings ); } ],
            'advanced_linking'        => [ $on( 'enable_advanced_linking' ), function () use ( $settings ) { return new Advanced_Linking( $settings ); } ],
            'historical_analytics'    => [ $on( 'enable_historical_analytics' ), function () use ( $settings ) { return new Historical_Analytics( $settings ); } ],
            'ga4_integration'         => [ $on( 'enable_ga4' ), function () use ( $settings ) { return new GA4_Integration( $settings ); } ],
            'core_web_vitals'         => [ $on( 'enable_cwv' ), function () use ( $settings ) { return new Core_Web_Vitals( $settings ); } ],
            'custom_reports'          => [ $on( 'enable_custom_reports' ), function () use ( $settings ) { return new Custom_Reports( $settings ); } ],
        ];

        foreach ( $modules as $prop => list( $boot, $factory ) ) {
            if ( $boot ) {
                $this->{$prop} = $factory();
            } else {
                unset( $this->{$prop} );
                $this->lazy_factories[ $prop ] = $factory;
            }
        }

        // Auto-redirect on slug change.
        add_action( 'post_updated', [ $this->redirects, 'detect_slug_change' ], 10, 3 );

        // Prefetch all post meta on singular pages to avoid N+1 queries
        add_action( 'wp', [ $this, 'prefetch_post_meta' ] );

        // Compatibility checks (admin_init only fires in wp-admin).
        add_action( 'admin_init', [ $this, 'check_compatibility' ] );
    }

    /**
     * Build a deferred module on first access.
     *
     * @param string $name Property name.
     * @return mixed|null Module instance, or null for unknown properties.
     */
    public function __get( string $name ) {
        if ( isset( $this->lazy_factories[ $name ] ) ) {
            $factory = $this->lazy_factories[ $name ];
            unset( $this->lazy_factories[ $name ] );
            $this->{$name} = $factory();
            return $this->{$name};
        }
        return null;
    }

    /**
     * Report deferred modules as set so isset( $core->module ) keeps working.
     *
     * @param string $name Property name.
     * @return bool
     */
    public function __isset( string $name ): bool {
        return isset( $this->lazy_factories[ $name ] );
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
                echo '<strong>' . esc_html__( 'Search Intel:', 'jat-search-intel' ) . '</strong> ';
                printf(
                    /* translators: %s: comma-separated list of plugin names */
                    esc_html__( 'Potential SEO plugin conflicts detected: %s. Consider disabling overlapping features to avoid duplicate meta tags.', 'jat-search-intel' ),
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

            // Broken Link Checker
            'enable_link_checker'    => false,

            // Internal Linking Suggestions
            'enable_internal_linker' => false,

            // Content Security Policy Headers
            'enable_csp_headers'     => false,

            // Subresource Integrity
            'enable_sri'             => false,

            // Additional Schema Types (v2.1)
            'enable_schema_types'    => false,

            // Q&A Detection Engine (v2.1)
            'enable_qa_detector'     => false,

            // FAQ Generator (v2.1)
            'enable_faq_generator'   => false,
            'faq_ai_api_key'         => '',

            // AI Visibility Dashboard (v2.5)
            'enable_visibility_dashboard' => false,
            'gsc_access_token'       => '',
            'gsc_site_url'           => '',
            'bing_api_key'           => '',
            'bing_site_url'          => '',

            // Citation Graph Builder (v2.5)
            'enable_citation_graph'  => false,

            // Topical Authority Map (v2.5)
            'enable_authority_map'   => false,

            // Role-Based Access Control (v3.0)
            'enable_rbac'            => false,
            'enable_settings_lock'   => false,

            // Multi-Language Support (v3.0)
            'enable_multilang'       => false,

            // White-Label (v3.0)
            'enable_white_label'     => false,
            'wl_plugin_name'         => '',
            'wl_plugin_uri'          => '',
            'wl_author_name'         => '',
            'wl_author_uri'          => '',
            'wl_description'         => '',
            'wl_logo_url'            => '',
            'wl_brand_color'         => '#2271b1',
            'wl_hide_branding'       => false,

            // Scale (v3.0)
            'enable_scale'           => false,

            // Notifications & Reports (v3.1)
            'enable_notifications'   => false,
            'notification_email'     => '',
            'alert_threshold_seo'    => 50,
            'alert_threshold_aeo'    => 50,
            'alert_threshold_geo'    => 50,
            'slack_webhook_url'      => '',

            // AI Engine (v3.2)
            'enable_ai_engine'       => false,
            'ai_provider'            => 'openai',
            'ai_model'               => '',
            'ai_endpoint'            => '',
            'ai_rate_limit'          => 30,
            'ai_token_budget'        => 0,

            // Content Decay Detection (v3.2)
            'enable_content_decay'   => false,
            'decay_threshold'        => 10.0,
            'decay_lookback_days'    => 30,
            'decay_auto_queue'       => true,

            // Keyword Cannibalization (v3.2)
            'enable_cannibalization' => false,

            // Advanced Internal Linking (v3.2)
            'enable_advanced_linking' => false,

            // Historical Analytics (v3.3)
            'enable_historical_analytics' => false,

            // Google Analytics 4 Integration (v3.3)
            'enable_ga4'             => false,
            'ga4_property_id'        => '',

            // Core Web Vitals Monitoring (v3.3)
            'enable_cwv'             => false,
            'psi_api_key'            => '',

            // Custom Reports & Export (v3.3)
            'enable_custom_reports'  => false,

            // Data management
            'delete_data_on_uninstall' => false,
        ];
    }
}
