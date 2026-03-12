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

    /** @var Admin */
    public $admin;

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

        $this->seo      = new SEO_Manager( $settings );
        $this->schema   = new Schema_Manager( $settings );
        $this->aeo      = new AEO_Manager( $settings );
        $this->geo      = new GEO_Manager( $settings );
        $this->analyzer = new Content_Analyzer( $settings );
        $this->sitemap  = new Sitemap( $settings );

        if ( is_admin() ) {
            $this->admin = new Admin( $settings, $this );
        }
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

            // GEO
            'enable_geo'             => true,
            'enable_llms_txt'        => true,
            'enable_ai_meta'         => true,
            'enable_entity_markup'   => true,
            'author_expertise'       => '',
            'content_freshness'      => true,

            // Sitemap
            'enable_sitemap'         => true,
            'sitemap_post_types'     => [ 'post', 'page' ],
            'sitemap_max_urls'       => 2000,

            // Content Analyzer
            'enable_analyzer'        => true,
            'target_readability'     => 60,

            // Post types to enable on
            'enabled_post_types'     => [ 'post', 'page' ],
        ];
    }
}
