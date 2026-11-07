<?php
namespace JAT_Search_Intel;

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

        // HTML sitemap shortcode works even when XML sitemaps are off.
        add_shortcode( 'jat_search_intel_html_sitemap', [ $this, 'html_sitemap_shortcode' ] );

        if ( empty( $settings['enable_sitemap'] ) ) {
            return;
        }

        add_action( 'init', [ $this, 'register_endpoints' ] );
        // Advertise the sitemaps in robots.txt (core only does this for its own sitemaps).
        add_filter( 'robots_txt', [ $this, 'add_sitemap_to_robots' ], 900, 2 );

        // Before redirect_canonical (priority 10), which would add a trailing slash to /sitemap.xml.
        add_action( 'template_redirect', [ $this, 'serve_sitemap' ], 1 );
        add_filter( 'redirect_canonical', [ $this, 'no_canonical_redirect' ] );

        // Disable WP core sitemaps to avoid conflicts
        add_filter( 'wp_sitemaps_enabled', '__return_false' );

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
        $like        = $wpdb->esc_like( '_transient_jat_search_intel_sitemap_' ) . '%';
        $like_timeout = $wpdb->esc_like( '_transient_timeout_jat_search_intel_sitemap_' ) . '%';
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
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?jat_search_intel_sitemap=index', 'top' );
        add_rewrite_rule( '^sitemap-([a-z]+)-?(\d*)\.xml$', 'index.php?jat_search_intel_sitemap=$matches[1]&jat_search_intel_sitemap_page=$matches[2]', 'top' );
        add_rewrite_rule( '^news-sitemap\.xml$', 'index.php?jat_search_intel_sitemap=_news', 'top' );
        add_rewrite_rule( '^video-sitemap\.xml$', 'index.php?jat_search_intel_sitemap=_video', 'top' );

        add_filter( 'query_vars', function ( $vars ) {
            $vars[] = 'jat_search_intel_sitemap';
            $vars[] = 'jat_search_intel_sitemap_page';
            return $vars;
        });
    }

    /**
     * Append Sitemap: lines to robots.txt.
     *
     * @param string $output robots.txt content.
     * @param mixed  $public Whether the site is public.
     * @return string
     */
    public function add_sitemap_to_robots( $output, $public ): string {
        $output = (string) $output;
        if ( ! $public ) {
            return $output;
        }
        $lines = [ home_url( '/sitemap.xml' ) ];
        if ( ! empty( $this->settings['enable_news_sitemap'] ) ) {
            $lines[] = home_url( '/news-sitemap.xml' );
        }
        if ( ! empty( $this->settings['enable_video_sitemap'] ) ) {
            $lines[] = home_url( '/video-sitemap.xml' );
        }
        foreach ( $lines as $url ) {
            if ( false === stripos( $output, 'Sitemap: ' . $url ) ) {
                $output = rtrim( $output ) . "\nSitemap: " . esc_url_raw( $url );
            }
        }
        return $output . "\n";
    }

    /**
     * Never canonical-redirect sitemap URLs.
     *
     * @param string|false $redirect_url Redirect target.
     * @return string|false
     */
    public function no_canonical_redirect( $redirect_url ) {
        return get_query_var( 'jat_search_intel_sitemap' ) ? false : $redirect_url;
    }

    /**
     * Serve the requested sitemap.
     */
    public function serve_sitemap(): void {
        $type = get_query_var( 'jat_search_intel_sitemap' );
        if ( ! $type ) {
            return;
        }

        $page = max( 1, absint( get_query_var( 'jat_search_intel_sitemap_page' ) ) );

        // News / video sitemaps only exist when switched on.
        if ( ( '_news' === $type && empty( $this->settings['enable_news_sitemap'] ) )
            || ( '_video' === $type && empty( $this->settings['enable_video_sitemap'] ) ) ) {
            $GLOBALS['wp_query']->set_404();
            status_header( 404 );
            nocache_headers();
            return;
        }

        header( 'Content-Type: application/xml; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, follow' );
        header( 'Cache-Control: public, max-age=3600' );

        $cache_key = 'jat_search_intel_sitemap_' . $type . '_' . $page;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            $output = (string) $cached;
        } else {
            if ( 'index' === $type ) {
                $output = $this->generate_sitemap_index();
            } elseif ( '_news' === $type ) {
                $output = $this->generate_news_sitemap();
            } elseif ( '_video' === $type ) {
                $output = $this->generate_video_sitemap();
            } else {
                $output = $this->generate_sitemap( $type, $page );
            }
            set_transient( $cache_key, $output, self::CACHE_TTL );
        }

        // XML document; every value is escaped with esc_url()/esc_xml() as it is built.
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

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
            if ( ! taxonomy_exists( $tax ) || in_array( $tax, (array) ( $this->settings['noindex_taxonomies'] ?? [] ), true ) ) {
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

        foreach ( [ 'enable_news_sitemap' => 'news-sitemap.xml', 'enable_video_sitemap' => 'video-sitemap.xml' ] as $flag => $file ) {
            if ( ! empty( $this->settings[ $flag ] ) ) {
                $xml .= '<sitemap>' . "\n";
                $xml .= '  <loc>' . esc_url( home_url( '/' . $file ) ) . '</loc>' . "\n";
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
                    'key'     => '_jat_search_intel_noindex',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_jat_search_intel_noindex',
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
            // Archives kept out of search, or canonicalised to another URL, don't belong here.
            if ( Term_SEO::is_noindex( $term, $this->settings ) || '' !== Term_SEO::get( (int) $term->term_id, 'canonical' ) ) {
                continue;
            }
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
            return (string) get_the_modified_date( 'c', $latest[0]->ID );
        }

        return gmdate( 'c' );
    }

    /**
     * Google News sitemap: articles published in the last two days.
     *
     * @return string
     */
    public function generate_news_sitemap(): string {
        $types = array_values( array_filter( (array) ( $this->settings['news_post_types'] ?? [ 'post' ] ), 'post_type_exists' ) );
        $name  = $this->settings['news_publication_name'] ?: ( $this->settings['site_name'] ?: get_bloginfo( 'name' ) );
        $lang  = $this->get_news_language();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

        if ( $types ) {
            $posts = get_posts( [
                'post_type'              => $types,
                'post_status'            => 'publish',
                'has_password'           => false,
                'numberposts'            => 1000,
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'date_query'             => [ [ 'after' => '2 days ago', 'inclusive' => true ] ],
            ] );

            foreach ( $posts as $post ) {
                if ( get_post_meta( $post->ID, '_jat_search_intel_noindex', true ) ) {
                    continue;
                }
                $xml .= '<url>' . "\n";
                $xml .= '  <loc>' . esc_url( get_permalink( $post->ID ) ) . '</loc>' . "\n";
                $xml .= '  <news:news>' . "\n";
                $xml .= '    <news:publication>' . "\n";
                $xml .= '      <news:name>' . esc_xml( $name ) . '</news:name>' . "\n";
                $xml .= '      <news:language>' . esc_xml( $lang ) . '</news:language>' . "\n";
                $xml .= '    </news:publication>' . "\n";
                $xml .= '    <news:publication_date>' . esc_xml( get_the_date( 'c', $post->ID ) ) . '</news:publication_date>' . "\n";
                $xml .= '    <news:title>' . esc_xml( get_the_title( $post->ID ) ) . '</news:title>' . "\n";
                $xml .= '  </news:news>' . "\n";
                $xml .= '</url>' . "\n";
            }
        }

        return $xml . '</urlset>';
    }

    /**
     * ISO 639 language code Google News expects (zh-cn / zh-tw kept).
     *
     * @return string
     */
    private function get_news_language(): string {
        $locale = strtolower( str_replace( '_', '-', get_locale() ) );
        if ( in_array( $locale, [ 'zh-cn', 'zh-tw' ], true ) ) {
            return $locale;
        }
        return substr( $locale, 0, 2 );
    }

    /**
     * Video sitemap built from YouTube, Vimeo and self-hosted videos in content.
     *
     * @return string
     */
    public function generate_video_sitemap(): string {
        global $wpdb;

        $types = array_values( array_filter( (array) ( $this->settings['sitemap_post_types'] ?? [ 'post', 'page' ] ), 'post_type_exists' ) );

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

        if ( ! $types ) {
            return $xml . '</urlset>';
        }

        $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a list of %s built above; values passed as an array.
        $ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Content scan; result is cached with the sitemap.
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_password = '' AND post_type IN ({$placeholders})
                AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s )
                ORDER BY post_modified_gmt DESC LIMIT 1000",
                array_merge( $types, [ '%youtube.com/%', '%youtu.be/%', '%vimeo.com/%', '%<video%' ] )
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        foreach ( (array) $ids as $post_id ) {
            $post_id = (int) $post_id;
            if ( get_post_meta( $post_id, '_jat_search_intel_noindex', true ) ) {
                continue;
            }
            $videos = self::extract_videos( (string) get_post_field( 'post_content', $post_id ) );
            if ( ! $videos ) {
                continue;
            }

            $title       = get_the_title( $post_id );
            $description = (string) get_post_meta( $post_id, '_jat_search_intel_description', true );
            if ( '' === $description ) {
                $description = wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) ), 40, '...' );
            }
            $fallback_thumb = (string) get_the_post_thumbnail_url( $post_id, 'large' );

            $entries = '';
            foreach ( $videos as $video ) {
                $thumb = $video['thumbnail'] ?: $fallback_thumb;
                if ( ! $thumb ) {
                    continue; // Thumbnail is required by the video sitemap spec.
                }
                $entries .= '  <video:video>' . "\n";
                $entries .= '    <video:thumbnail_loc>' . esc_url( $thumb ) . '</video:thumbnail_loc>' . "\n";
                $entries .= '    <video:title>' . esc_xml( $title ) . '</video:title>' . "\n";
                $entries .= '    <video:description>' . esc_xml( mb_substr( $description ?: $title, 0, 2048 ) ) . '</video:description>' . "\n";
                if ( ! empty( $video['content_loc'] ) ) {
                    $entries .= '    <video:content_loc>' . esc_url( $video['content_loc'] ) . '</video:content_loc>' . "\n";
                }
                if ( ! empty( $video['player_loc'] ) ) {
                    $entries .= '    <video:player_loc>' . esc_url( $video['player_loc'] ) . '</video:player_loc>' . "\n";
                }
                $entries .= '    <video:publication_date>' . esc_xml( get_the_date( 'c', $post_id ) ) . '</video:publication_date>' . "\n";
                $entries .= '  </video:video>' . "\n";
            }

            if ( '' !== $entries ) {
                $xml .= '<url>' . "\n" . '  <loc>' . esc_url( get_permalink( $post_id ) ) . '</loc>' . "\n" . $entries . '</url>' . "\n";
            }
        }

        return $xml . '</urlset>';
    }

    /**
     * Find embedded videos in post content.
     *
     * @param string $content Post content.
     * @return array<int,array{player_loc:string,content_loc:string,thumbnail:string}>
     */
    public static function extract_videos( string $content ): array {
        $videos = [];

        if ( preg_match_all( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $content, $m ) ) {
            foreach ( array_unique( $m[1] ) as $id ) {
                $videos[] = [
                    'player_loc'  => 'https://www.youtube.com/embed/' . $id,
                    'content_loc' => '',
                    'thumbnail'   => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
                ];
            }
        }

        if ( preg_match_all( '~(?:player\.)?vimeo\.com/(?:video/)?(\d{6,12})~', $content, $m ) ) {
            foreach ( array_unique( $m[1] ) as $id ) {
                $videos[] = [
                    'player_loc'  => 'https://player.vimeo.com/video/' . $id,
                    'content_loc' => '',
                    'thumbnail'   => '',
                ];
            }
        }

        if ( preg_match_all( '~<video[^>]*?\ssrc="([^"]+)"[^>]*?(?:\sposter="([^"]*)")?~i', $content, $m, PREG_SET_ORDER ) ) {
            foreach ( $m as $match ) {
                $poster = '';
                if ( preg_match( '~\sposter="([^"]+)"~i', $match[0], $pm ) ) {
                    $poster = $pm[1];
                }
                $videos[] = [
                    'player_loc'  => '',
                    'content_loc' => html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
                    'thumbnail'   => $poster ? html_entity_decode( $poster, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '',
                ];
            }
        }

        return $videos;
    }

    /**
     * [jat_search_intel_html_sitemap post_types="page,post" limit="500"]
     *
     * @param array|string $atts Shortcode attributes.
     * @return string
     */
    public function html_sitemap_shortcode( $atts ): string {
        $atts = shortcode_atts(
            [
                'post_types' => implode( ',', (array) ( $this->settings['sitemap_post_types'] ?? [ 'page', 'post' ] ) ),
                'limit'      => 500,
            ],
            $atts,
            'jat_search_intel_html_sitemap'
        );

        $types = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) $atts['post_types'] ) ), 'post_type_exists' ) );
        $limit = max( 1, min( 2000, absint( $atts['limit'] ) ) );
        $key   = 'jat_search_intel_sitemap_html_' . md5( implode( ',', $types ) . '|' . $limit );

        $cached = get_transient( $key );
        if ( false !== $cached ) {
            return (string) $cached;
        }

        $html = '<div class="jat-search-intel-html-sitemap">';
        foreach ( $types as $type ) {
            $object = get_post_type_object( $type );
            $posts  = get_posts( [
                'post_type'      => $type,
                'post_status'    => 'publish',
                'has_password'   => false,
                'numberposts'    => $limit,
                'orderby'        => 'page' === $type ? 'menu_order title' : 'date',
                'order'          => 'page' === $type ? 'ASC' : 'DESC',
                'no_found_rows'  => true,
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cached for 12 hours.
                    'relation' => 'OR',
                    [ 'key' => '_jat_search_intel_noindex', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_jat_search_intel_noindex', 'value' => '1', 'compare' => '!=' ],
                ],
            ] );
            if ( ! $posts ) {
                continue;
            }
            $html .= '<h2>' . esc_html( $object ? $object->labels->name : $type ) . '</h2><ul>';
            foreach ( $posts as $post ) {
                $html .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
            }
            $html .= '</ul>';
        }
        $html .= '</div>';

        set_transient( $key, $html, self::CACHE_TTL );
        return $html;
    }
}
