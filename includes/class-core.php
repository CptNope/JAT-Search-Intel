<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Core orchestrator class for JAT Search Intel plugin.
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


    /** @var Scale */
    public $scale;


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

    /** @var Rank_Tracker */
    public $rank_tracker;

    /** @var AI_Search */
    public $ai_search;

    /** @var Media_SEO */
    public $media_seo;

    /** @var Feed_SEO */
    public $feed_seo;

    /** @var Custom_Schema */
    public $custom_schema;

    /** @var AI_Optimizer */
    public $ai_optimizer;

    /** @var Passage_Coach */
    public $passage_coach;

    /** @var Meta_Sync */
    public $meta_sync;

    /** @var Readiness */
    public $readiness;

    /** @var Admin_Menu */
    public $admin_menu;

    /** @var Setup_Wizard */
    public $setup_wizard;

    /** @var Performance_Report */
    public $performance_report;

    /** @var Abilities */
    public $abilities;

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

    /** @var array<string,string> Deferred modules: property => class name. */
    private $lazy_modules = [];

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
        $this->sitemap      = new Sitemap( $settings );

        if ( $is_admin ) {
            $this->admin      = new Admin( $settings, $this );
            $this->bulk_tools = new Bulk_Tools( $settings, $this );
        }

        // Block editor integration — sidebar, custom blocks, meta registration.
        $this->blocks = new Blocks();

        // Redirect Manager — also needed for slug-change detection on every save.
        $this->redirects = new Redirect_Manager( $settings );

        /*
         * Conditionally-booted modules: property => [ boot now?, class, boot-on hooks ].
         * "Boot now" mirrors exactly when the module's constructor would add hooks.
         * A deferred module with boot-on hooks is built by a one-line listener that runs
         * just before the hook it serves; its constructor then registers the real callback
         * at a later priority of the same hook, so it runs in the same pass. Page views
         * therefore never load admin-, REST- or save-only code.
         */
        $analyzer_on = $on( 'enable_analyzer' );
        $modules     = [
            'analyzer'                => [ $is_admin, Content_Analyzer::class, $analyzer_on ? [ 'wp_after_insert_post' => 19 ] : [] ],
            'rest_api'                => [ $is_admin, Rest_Api::class, [ 'rest_api_init' => 9 ] ],
            'ai_optimizer'            => [ $is_admin, AI_Optimizer::class, [ 'jat_search_intel_post_analyzed' => 19 ] ],
            'woocommerce'             => [ class_exists( 'WooCommerce' ), WooCommerce::class ],
            'privacy'                 => [ $is_admin, Privacy::class ],
            'migration'               => [ $is_admin, Migration::class ],
            'robots_txt'              => [ $is_admin || $on( 'enable_robots_editor' ), Robots_Txt::class ],
            'faq_generator'           => [ $is_admin, FAQ_Generator::class ],
            'multisite'               => [ is_multisite(), Multisite::class ],
            'indexnow'                => [ $on( 'enable_indexnow' ) || $on( 'indexnow_api_key' ), IndexNow::class ],
            'local_business'          => [ $on( 'enable_local_business' ), Local_Business::class ],
            'audit_log'               => [ $on( 'enable_audit_log' ), Audit_Log::class ],
            'link_checker'            => [ $on( 'enable_link_checker' ), Link_Checker::class ],
            'internal_linker'         => [ $on( 'enable_internal_linker' ), Internal_Linker::class ],
            'schema_types'            => [ $on( 'enable_schema_types' ), Schema_Types::class ],
            'qa_detector'             => [ $on( 'enable_qa_detector' ), QA_Detector::class ],
            'visibility_dashboard'    => [ $on( 'enable_visibility_dashboard' ), Visibility_Dashboard::class ],
            'citation_graph'          => [ $on( 'enable_citation_graph' ), Citation_Graph::class ],
            'authority_map'           => [ $on( 'enable_authority_map' ), Authority_Map::class ],
            'rbac'                    => [ $on( 'enable_rbac' ), RBAC::class ],
            'multilang'               => [ $on( 'enable_multilang' ), Multilang::class ],
            'scale'                   => [ $on( 'enable_scale' ), Scale::class ],
            'notifications'           => [ $on( 'enable_notifications' ), Notifications::class ],
            'ai_engine'               => [ false, AI_Engine::class ],
            'content_decay'           => [ $on( 'enable_content_decay' ), Content_Decay::class ],
            'keyword_cannibalization' => [ $on( 'enable_cannibalization' ), Keyword_Cannibalization::class ],
            'advanced_linking'        => [ $on( 'enable_advanced_linking' ), Advanced_Linking::class ],
            'historical_analytics'    => [ $on( 'enable_historical_analytics' ), Historical_Analytics::class ],
            'ga4_integration'         => [ $on( 'enable_ga4' ), GA4_Integration::class ],
            'core_web_vitals'         => [ $on( 'enable_cwv' ), Core_Web_Vitals::class ],
            'custom_reports'          => [ $on( 'enable_custom_reports' ), Custom_Reports::class ],
            'media_seo'               => [ $on( 'enable_image_alt' ) || $on( 'redirect_attachments' ), Media_SEO::class ],
            'feed_seo'                => [ $on( 'enable_rss_footer' ), Feed_SEO::class ],
            'custom_schema'           => [ $on( 'enable_schema' ), Custom_Schema::class ],
            'ai_search'               => [ true, AI_Search::class ],
            'meta_sync'               => [ true, Meta_Sync::class ],
            'passage_coach'           => [ $is_admin && $analyzer_on, Passage_Coach::class, $analyzer_on ? [ 'rest_api_init' => 9 ] : [] ],
            'rank_tracker'            => [ $is_admin || $on( 'enable_rank_tracking' ), Rank_Tracker::class ],
            'readiness'               => [ $is_admin, Readiness::class ],
            'admin_menu'              => [ $is_admin, Admin_Menu::class ],
            'setup_wizard'            => [ $is_admin, Setup_Wizard::class ],
            'performance_report'      => [ $is_admin, Performance_Report::class ],
            'abilities'               => [ false, Abilities::class, function_exists( 'wp_register_ability' ) ? [ 'wp_abilities_api_categories_init' => 9, 'wp_abilities_api_init' => 9 ] : [] ],
        ];

        foreach ( $modules as $prop => $module ) {
            list( $boot, $class ) = $module;
            if ( $boot ) {
                $this->{$prop} = $this->build_module( $class );
                continue;
            }
            unset( $this->{$prop} );
            $this->lazy_modules[ $prop ] = $class;
            foreach ( (array) ( $module[2] ?? [] ) as $hook => $priority ) {
                add_action(
                    $hook,
                    function () use ( $prop ) {
                        $this->boot( $prop );
                    },
                    $priority,
                    0
                );
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
     * Instantiate a module class with the arguments it expects.
     *
     * @param string $class Fully-qualified class name.
     * @return object
     */
    private function build_module( string $class ) {
        switch ( $class ) {
            case Privacy::class:
                return new Privacy();
            case Rest_Api::class:
            case AI_Optimizer::class:
                return new $class( $this->boot_settings, $this );
            case FAQ_Generator::class:
                return new FAQ_Generator( $this->boot_settings, $this->qa_detector );
            default:
                return new $class( $this->boot_settings );
        }
    }

    /**
     * Build a deferred module now, if it has not been built yet.
     *
     * @param string $name Property name.
     */
    public function boot( string $name ): void {
        if ( isset( $this->lazy_modules[ $name ] ) ) {
            $class = $this->lazy_modules[ $name ];
            unset( $this->lazy_modules[ $name ] );
            $this->{$name} = $this->build_module( $class );
        }
    }

    /**
     * Build a deferred module on first access.
     *
     * @param string $name Property name.
     * @return mixed|null Module instance, or null for unknown properties.
     */
    public function __get( string $name ) {
        if ( isset( $this->lazy_modules[ $name ] ) ) {
            $class = $this->lazy_modules[ $name ];
            unset( $this->lazy_modules[ $name ] );
            $this->{$name} = $this->build_module( $class );
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
        return isset( $this->lazy_modules[ $name ] );
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
                echo '<strong>' . esc_html__( 'JAT Search Intel:', 'jat-search-intel' ) . '</strong> ';
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
     * Detect active plugins that may conflict with JAT Search Intel.
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
        $saved = get_option( 'jat_search_intel_settings', [] );
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

            // Subresource Integrity

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

            // v3.5 — SEO
            'enable_news_sitemap'    => false,
            'news_post_types'        => [ 'post' ],
            'news_publication_name'  => '',
            'enable_video_sitemap'   => false,
            'enable_image_alt'       => true,
            'redirect_attachments'   => true,
            'enable_rss_footer'      => true,
            'rss_after_content'      => 'The post %%POSTLINK%% appeared first on %%BLOGLINK%%.',

            // v3.5 — AEO/GEO
            'enable_markdown_endpoints' => true,
            'enable_ai_crawler_log'  => false,
            'ai_crawler_rules'       => [],
            'content_signals'        => [ 'search' => '', 'ai-input' => '', 'ai-train' => '' ],

            // v3.5 — Rank tracking
            'enable_rank_tracking'   => false,
            'tracked_keywords'       => [],
        ];
    }
}
