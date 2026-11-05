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
        // Only resolve variables the template uses (several need extra queries).
        $tokens = [ '%title%', '%sitename%', '%sep%', '%category%', '%tag%', '%date%', '%author%', '%page%', '%excerpt%', '%search_query%' ];

        $variables = [];
        foreach ( $tokens as $token ) {
            if ( false !== strpos( $template, $token ) ) {
                $variables[ $token ] = $this->resolve_token( $token );
            }
        }

        $resolved = strtr( $template, $variables );

        // Clean up double spaces and trailing separators
        $sep      = $this->get_var_sep();
        $resolved = preg_replace( '/\s+/', ' ', $resolved );
        $resolved = trim( $resolved, " {$sep}" );

        return $resolved;
    }

    /**
     * Value for one template token.
     *
     * @param string $token Token such as %title%.
     * @return string
     */
    private function resolve_token( string $token ): string {
        switch ( $token ) {
            case '%title%':
                return $this->get_var_title();
            case '%sitename%':
                return $this->get_var_sitename();
            case '%sep%':
                return $this->get_var_sep();
            case '%category%':
                return $this->get_var_category();
            case '%tag%':
                return $this->get_var_tag();
            case '%date%':
                return $this->get_var_date();
            case '%author%':
                return $this->get_var_author();
            case '%page%':
                return $this->get_var_page();
            case '%excerpt%':
                return $this->get_var_excerpt();
            case '%search_query%':
                return $this->get_var_search_query();
        }
        return '';
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
            return is_array( $tags ) && $tags ? $tags[0]->name : ''; // Can be a WP_Error.
        }

        return '';
    }

    private function get_var_date(): string {
        if ( is_singular() ) {
            return (string) get_the_date( '', get_queried_object_id() );
        }
        if ( is_date() ) {
            return (string) get_the_date();
        }

        return '';
    }

    private function get_var_author(): string {
        if ( is_author() ) {
            return get_the_author() ?: '';
        }
        if ( is_singular() ) {
            $author_id = (int) get_post_field( 'post_author', get_queried_object_id() );
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
