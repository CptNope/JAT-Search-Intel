<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * XML Sitemap generator with support for standard sitemaps, news sitemaps, and image sitemaps.
 */
class Sitemap {

    /** @var array */
    private $settings;

    /** @var int Cache TTL in seconds (12 hours) */
    private const CACHE_TTL = 43200;

    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( empty( $settings['enable_sitemap'] ) ) {
            return;
        }

        add_action( 'init', [ $this, 'register_endpoints' ] );
        add_action( 'template_redirect', [ $this, 'serve_sitemap' ] );

        // Disable WP core sitemaps to avoid conflicts
        add_filter( 'wp_sitemaps_enabled', '__return_false' );

        // Ping search engines on post publish (respect setting)
        if ( ! empty( $settings['ping_search_engines'] ) ) {
            add_action( 'publish_post', [ $this, 'ping_search_engines' ] );
            add_action( 'publish_page', [ $this, 'ping_search_engines' ] );
        }

        // Cache invalidation on content changes
        add_action( 'save_post', [ $this, 'invalidate_cache' ] );
        add_action( 'delete_post', [ $this, 'invalidate_cache' ] );
        add_action( 'created_term', [ $this, 'invalidate_cache' ] );
        add_action( 'edited_term', [ $this, 'invalidate_cache' ] );
        add_action( 'delete_term', [ $this, 'invalidate_cache' ] );
    }

    /**
     * Invalidate all sitemap transient caches.
     */
    public function invalidate_cache(): void {
        global $wpdb;
        $like        = $wpdb->esc_like( '_transient_si_sitemap_' ) . '%';
        $like_timeout = $wpdb->esc_like( '_transient_timeout_si_sitemap_' ) . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $like,
                $like_timeout
            )
        );
    }

    /**
     * Register sitemap rewrite rules.
     */
    public function register_endpoints(): void {
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?search_intel_sitemap=index', 'top' );
        add_rewrite_rule( '^sitemap-([a-z]+)-?(\d*)\.xml$', 'index.php?search_intel_sitemap=$matches[1]&search_intel_sitemap_page=$matches[2]', 'top' );

        add_filter( 'query_vars', function ( $vars ) {
            $vars[] = 'search_intel_sitemap';
            $vars[] = 'search_intel_sitemap_page';
            return $vars;
        });
    }

    /**
     * Serve the requested sitemap.
     */
    public function serve_sitemap(): void {
        $type = get_query_var( 'search_intel_sitemap' );
        if ( ! $type ) {
            return;
        }

        $page = max( 1, absint( get_query_var( 'search_intel_sitemap_page' ) ) );

        header( 'Content-Type: application/xml; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, follow' );
        header( 'Cache-Control: public, max-age=3600' );

        $cache_key = 'si_sitemap_' . $type . '_' . $page;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            echo $cached;
        } elseif ( 'index' === $type ) {
            $output = $this->generate_sitemap_index();
            set_transient( $cache_key, $output, self::CACHE_TTL );
            echo $output;
        } else {
            $output = $this->generate_sitemap( $type, $page );
            set_transient( $cache_key, $output, self::CACHE_TTL );
            echo $output;
        }

        exit;
    }

    /**
     * Generate the sitemap index XML.
     */
    private function generate_sitemap_index(): string {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $post_types = $this->settings['sitemap_post_types'] ?? [ 'post', 'page' ];
        $max_urls   = $this->settings['sitemap_max_urls'] ?? 2000;

        foreach ( $post_types as $pt ) {
            $count = wp_count_posts( $pt );
            $total = isset( $count->publish ) ? $count->publish : 0;
            $pages = max( 1, ceil( $total / $max_urls ) );

            for ( $i = 1; $i <= $pages; $i++ ) {
                $suffix = $pages > 1 ? $i : '';
                $xml .= '<sitemap>' . "\n";
                $xml .= '  <loc>' . esc_url( home_url( "/sitemap-{$pt}{$suffix}.xml" ) ) . '</loc>' . "\n";
                $xml .= '  <lastmod>' . $this->get_last_modified_for_type( $pt ) . '</lastmod>' . "\n";
                $xml .= '</sitemap>' . "\n";
            }
        }

        // Taxonomy sitemaps (v1.1: configurable via settings)
        $sitemap_taxonomies = $this->settings['sitemap_taxonomies'] ?? [ 'category' ];
        foreach ( $sitemap_taxonomies as $tax ) {
            if ( ! taxonomy_exists( $tax ) ) {
                continue;
            }
            $terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => true, 'number' => 1 ] );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $xml .= '<sitemap>' . "\n";
                $xml .= '  <loc>' . esc_url( home_url( "/sitemap-{$tax}.xml" ) ) . '</loc>' . "\n";
                $xml .= '  <lastmod>' . gmdate( 'c' ) . '</lastmod>' . "\n";
                $xml .= '</sitemap>' . "\n";
            }
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    /**
     * Generate a specific sitemap.
     */
    private function generate_sitemap( string $type, int $page ): string {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        // Check if it's a taxonomy
        $sitemap_taxonomies = $this->settings['sitemap_taxonomies'] ?? [ 'category' ];
        if ( in_array( $type, $sitemap_taxonomies, true ) || taxonomy_exists( $type ) ) {
            $xml .= $this->get_taxonomy_urls( $type );
        } else {
            $xml .= $this->get_post_type_urls( $type, $page );
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Get URLs for a post type.
     */
    private function get_post_type_urls( string $post_type, int $page ): string {
        $xml      = '';
        $max_urls = $this->settings['sitemap_max_urls'] ?? 2000;
        $offset   = ( $page - 1 ) * $max_urls;

        // Add homepage for page type, page 1
        if ( 'page' === $post_type && 1 === $page ) {
            $xml .= '<url>' . "\n";
            $xml .= '  <loc>' . esc_url( home_url( '/' ) ) . '</loc>' . "\n";
            $xml .= '  <changefreq>daily</changefreq>' . "\n";
            $xml .= '  <priority>1.0</priority>' . "\n";
            $xml .= '</url>' . "\n";
        }

        $posts = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $max_urls,
            'offset'         => $offset,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_search_intel_noindex',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_search_intel_noindex',
                    'value'   => '1',
                    'compare' => '!=',
                ],
            ],
        ] );

        foreach ( $posts as $post ) {
            $priority   = ( 'page' === $post_type ) ? '0.8' : '0.6';
            $changefreq = $this->get_changefreq( $post );

            $xml .= '<url>' . "\n";
            $xml .= '  <loc>' . esc_url( get_permalink( $post->ID ) ) . '</loc>' . "\n";
            $xml .= '  <lastmod>' . get_the_modified_date( 'c', $post->ID ) . '</lastmod>' . "\n";
            $xml .= '  <changefreq>' . $changefreq . '</changefreq>' . "\n";
            $xml .= '  <priority>' . $priority . '</priority>' . "\n";

            // Image sitemap entries
            $images = $this->get_post_images( $post );
            foreach ( $images as $image ) {
                $xml .= '  <image:image>' . "\n";
                $xml .= '    <image:loc>' . esc_url( $image['url'] ) . '</image:loc>' . "\n";
                if ( ! empty( $image['alt'] ) ) {
                    $xml .= '    <image:title>' . esc_xml( $image['alt'] ) . '</image:title>' . "\n";
                }
                $xml .= '  </image:image>' . "\n";
            }

            $xml .= '</url>' . "\n";
        }

        return $xml;
    }

    /**
     * Get URLs for a taxonomy.
     */
    private function get_taxonomy_urls( string $taxonomy ): string {
        $xml   = '';
        $terms = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'number'     => $this->settings['sitemap_max_urls'] ?? 2000,
        ] );

        if ( is_wp_error( $terms ) ) {
            return $xml;
        }

        foreach ( $terms as $term ) {
            $link = get_term_link( $term );
            if ( is_wp_error( $link ) ) {
                continue;
            }

            $xml .= '<url>' . "\n";
            $xml .= '  <loc>' . esc_url( $link ) . '</loc>' . "\n";
            $xml .= '  <changefreq>weekly</changefreq>' . "\n";
            $xml .= '  <priority>0.4</priority>' . "\n";
            $xml .= '</url>' . "\n";
        }

        return $xml;
    }

    /**
     * Determine change frequency based on post age.
     */
    private function get_changefreq( $post ): string {
        $modified = strtotime( $post->post_modified_gmt );
        $age_days = ( time() - $modified ) / DAY_IN_SECONDS;

        if ( $age_days <= 1 ) {
            return 'daily';
        }
        if ( $age_days <= 7 ) {
            return 'weekly';
        }
        if ( $age_days <= 30 ) {
            return 'weekly';
        }
        if ( $age_days <= 365 ) {
            return 'monthly';
        }

        return 'yearly';
    }

    /**
     * Get images from a post for image sitemap.
     */
    private function get_post_images( $post ): array {
        $images = [];

        // Featured image
        $thumb_id = get_post_thumbnail_id( $post->ID );
        if ( $thumb_id ) {
            $src = wp_get_attachment_image_src( $thumb_id, 'full' );
            if ( $src ) {
                $images[] = [
                    'url' => $src[0],
                    'alt' => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
                ];
            }
        }

        // Content images (up to 10)
        if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*(?:alt=["\']([^"\']*)["\'])?/i', $post->post_content, $matches, PREG_SET_ORDER ) ) {
            foreach ( array_slice( $matches, 0, 10 ) as $match ) {
                $images[] = [
                    'url' => $match[1],
                    'alt' => $match[2] ?? '',
                ];
            }
        }

        return $images;
    }

    /**
     * Get last modified date for a post type.
     */
    private function get_last_modified_for_type( string $post_type ): string {
        $latest = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );

        if ( $latest ) {
            return get_the_modified_date( 'c', $latest[0]->ID );
        }

        return gmdate( 'c' );
    }

    /**
     * Ping search engines after new content is published.
     */
    public function ping_search_engines( int $post_id ): void {
        if ( wp_is_post_revision( $post_id ) || defined( 'DOING_AUTOSAVE' ) ) {
            return;
        }

        $sitemap_url = home_url( '/sitemap.xml' );

        // Ping Google
        wp_remote_get( 'https://www.google.com/ping?sitemap=' . urlencode( $sitemap_url ), [
            'blocking' => false,
            'timeout'  => 3,
        ] );

        // Ping Bing / IndexNow
        wp_remote_get( 'https://www.bing.com/ping?sitemap=' . urlencode( $sitemap_url ), [
            'blocking' => false,
            'timeout'  => 3,
        ] );
    }
}
