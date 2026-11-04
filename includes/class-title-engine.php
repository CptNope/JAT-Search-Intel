<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Title Template Engine — resolves template variables for dynamic SEO titles.
 *
 * Supported variables:
 *   %title%, %sitename%, %sep%, %category%, %tag%, %date%, %author%, %page%, %excerpt%
 *
 * Templates can be set per-context: single posts, pages, categories, tags,
 * author archives, search, 404, homepage.
 */
class Title_Engine {

    /** @var array */
    private $settings;

    /** @var string[] Default templates per context */
    private static $default_templates = [
        'single'   => '%title% %sep% %sitename%',
        'page'     => '%title% %sep% %sitename%',
        'category' => '%category% %sep% %sitename%',
        'tag'      => '%tag% %sep% %sitename%',
        'author'   => '%author% %sep% %sitename%',
        'search'   => 'Search: %search_query% %sep% %sitename%',
        '404'      => 'Page Not Found %sep% %sitename%',
        'homepage' => '%sitename%',
    ];

    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( empty( $settings['enable_seo'] ) ) {
            return;
        }

        // Override the existing SEO Manager title filter at a later priority
        add_filter( 'pre_get_document_title', [ $this, 'resolve_title' ], 25 );
    }

    /**
     * Get the default templates.
     */
    public static function get_default_templates(): array {
        return self::$default_templates;
    }

    /**
     * Resolve the title for the current context using templates.
     */
    public function resolve_title( string $title ): string {
        $templates = $this->settings['title_templates'] ?? [];
        $context   = $this->detect_context();
        $template  = $templates[ $context ] ?? self::$default_templates[ $context ] ?? '%title% %sep% %sitename%';

        // For single posts/pages, check for a manual SEO title override first
        if ( in_array( $context, [ 'single', 'page' ], true ) && is_singular() ) {
            $post_id   = get_queried_object_id();
            $seo_title = get_post_meta( $post_id, '_jat_search_intel_title', true );
            if ( $seo_title ) {
                return $seo_title;
            }
        }

        return $this->resolve_variables( $template );
    }

    /**
     * Detect the current page context.
     */
    private function detect_context(): string {
        if ( is_front_page() || is_home() ) {
            return 'homepage';
        }
        if ( is_singular( 'page' ) ) {
            return 'page';
        }
        if ( is_singular() ) {
            return 'single';
        }
        if ( is_category() ) {
            return 'category';
        }
        if ( is_tag() ) {
            return 'tag';
        }
        if ( is_author() ) {
            return 'author';
        }
        if ( is_search() ) {
            return 'search';
        }
        if ( is_404() ) {
            return '404';
        }

        return 'single';
    }

    /**
     * Resolve all template variables in a string.
     */
    public function resolve_variables( string $template ): string {
        $variables = [
            '%title%'        => $this->get_var_title(),
            '%sitename%'     => $this->get_var_sitename(),
            '%sep%'          => $this->get_var_sep(),
            '%category%'     => $this->get_var_category(),
            '%tag%'          => $this->get_var_tag(),
            '%date%'         => $this->get_var_date(),
            '%author%'       => $this->get_var_author(),
            '%page%'         => $this->get_var_page(),
            '%excerpt%'      => $this->get_var_excerpt(),
            '%search_query%' => $this->get_var_search_query(),
        ];

        $resolved = str_replace( array_keys( $variables ), array_values( $variables ), $template );

        // Clean up double spaces and trailing separators
        $sep      = $this->get_var_sep();
        $resolved = preg_replace( '/\s+/', ' ', $resolved );
        $resolved = trim( $resolved, " {$sep}" );

        return $resolved;
    }

    /**
     * Variable resolvers.
     */
    private function get_var_title(): string {
        if ( is_singular() ) {
            return get_the_title( get_queried_object_id() );
        }
        if ( is_category() || is_tag() || is_tax() ) {
            return single_term_title( '', false ) ?: '';
        }
        if ( is_author() ) {
            return get_the_author() ?: '';
        }
        if ( is_post_type_archive() ) {
            return post_type_archive_title( '', false ) ?: '';
        }

        return '';
    }

    private function get_var_sitename(): string {
        return $this->settings['site_name'] ?: get_bloginfo( 'name' );
    }

    private function get_var_sep(): string {
        return $this->settings['separator'] ?? '|';
    }

    private function get_var_category(): string {
        if ( is_category() ) {
            return single_cat_title( '', false ) ?: '';
        }
        if ( is_singular() ) {
            $cats = get_the_category( get_queried_object_id() );
            return ! empty( $cats ) ? $cats[0]->name : '';
        }

        return '';
    }

    private function get_var_tag(): string {
        if ( is_tag() ) {
            return single_tag_title( '', false ) ?: '';
        }
        if ( is_singular() ) {
            $tags = get_the_tags( get_queried_object_id() );
            return ! empty( $tags ) ? $tags[0]->name : '';
        }

        return '';
    }

    private function get_var_date(): string {
        if ( is_singular() ) {
            return get_the_date( '', get_queried_object_id() ) ?: '';
        }
        if ( is_date() ) {
            return get_the_date() ?: '';
        }

        return '';
    }

    private function get_var_author(): string {
        if ( is_author() ) {
            return get_the_author() ?: '';
        }
        if ( is_singular() ) {
            $author_id = get_post_field( 'post_author', get_queried_object_id() );
            return get_the_author_meta( 'display_name', $author_id ) ?: '';
        }

        return '';
    }

    private function get_var_page(): string {
        $paged = get_query_var( 'paged', 0 );
        if ( $paged > 1 ) {
            /* translators: %d: number. */
            return sprintf( __( 'Page %d', 'jat-search-intel' ), $paged );
        }

        return '';
    }

    private function get_var_excerpt(): string {
        if ( is_singular() ) {
            $post = get_post( get_queried_object_id() );
            if ( $post && $post->post_excerpt ) {
                return wp_strip_all_tags( $post->post_excerpt );
            }
            return wp_trim_words( wp_strip_all_tags( $post->post_content ), 15, '...' );
        }

        return '';
    }

    private function get_var_search_query(): string {
        return is_search() ? get_search_query() : '';
    }
}
