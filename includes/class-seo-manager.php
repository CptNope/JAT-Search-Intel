<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles traditional SEO: meta tags, Open Graph, Twitter Cards, canonical URLs, robots directives.
 */
class SEO_Manager {

    /** @var array */
    private $settings;

    /** Verification service => meta tag name, printed on the home page. */
    public const VERIFICATION_TAGS = [
        'google'    => 'google-site-verification',
        'bing'      => 'msvalidate.01',
        'yandex'    => 'yandex-verification',
        'baidu'     => 'baidu-site-verification',
        'pinterest' => 'p:domain_verify',
        'facebook'  => 'facebook-domain-verification',
        'naver'     => 'naver-site-verification',
        'seznam'    => 'seznam-wmt',
    ];

    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( empty( $settings['enable_seo'] ) ) {
            return;
        }

        add_action( 'wp_head', [ $this, 'render_meta_tags' ], 1 );
        add_action( 'wp_head', [ $this, 'render_canonical' ], 2 );
        if ( function_exists( 'wp_robots' ) ) {
            // Merge into core's single robots tag instead of printing a second one.
            add_filter( 'wp_robots', [ $this, 'filter_wp_robots' ], 20 );
        } else {
            add_action( 'wp_head', [ $this, 'render_robots_meta' ], 3 );
        }

        if ( ! empty( $settings['enable_og'] ) ) {
            add_action( 'wp_head', [ $this, 'render_open_graph' ], 4 );
        }

        if ( ! empty( $settings['enable_twitter'] ) ) {
            add_action( 'wp_head', [ $this, 'render_twitter_card' ], 5 );
        }

        // Filter document title
        add_filter( 'pre_get_document_title', [ $this, 'filter_title' ], 20 );
        add_filter( 'document_title_separator', [ $this, 'filter_separator' ] );
    }

    /**
     * Get the SEO title for a post/page/archive.
     */
    public function get_title( $post_id = null ): string {
        if ( $post_id ) {
            $custom = get_post_meta( $post_id, '_jat_search_intel_title', true );
            if ( $custom ) {
                return $custom;
            }
            return get_the_title( $post_id );
        }
        return '';
    }

    /**
     * Get the meta description for a post/page.
     */
    public function get_description( $post_id = null ): string {
        if ( $post_id ) {
            $custom = get_post_meta( $post_id, '_jat_search_intel_description', true );
            if ( $custom ) {
                return $this->truncate_description( $custom );
            }

            $fallback = $this->settings['description_fallback'] ?? 'excerpt';
            $post     = get_post( $post_id );

            if ( 'empty' === $fallback ) {
                return '';
            }

            if ( $post ) {
                if ( 'excerpt' === $fallback ) {
                    $desc = Content_Guard::excerpt( $post ) ?: wp_trim_words( wp_strip_all_tags( Content_Guard::content( $post ) ), 30, '...' );
                } else {
                    $desc = wp_trim_words( wp_strip_all_tags( Content_Guard::content( $post ) ), 30, '...' );
                }
                return $this->truncate_description( $desc );
            }
        }
        return get_bloginfo( 'description' );
    }

    /**
     * Truncate description to the configured max length.
     */
    private function truncate_description( string $desc ): string {
        $max = absint( $this->settings['description_max_length'] ?? 160 );
        if ( $max > 0 && mb_strlen( $desc ) > $max ) {
            $desc = mb_substr( $desc, 0, $max - 3 ) . '...';
        }
        return $desc;
    }

    /**
     * Filter the document <title> tag.
     */
    public function filter_title( string $title ): string {
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $custom  = $this->get_title( $post_id );
            if ( $custom ) {
                return $custom;
            }
        }
        return $title;
    }

    /**
     * Filter the title separator.
     */
    public function filter_separator( string $sep ): string {
        return $this->settings['separator'] ?: $sep;
    }

    /**
     * Render basic meta tags (description, author).
     */
    public function render_meta_tags(): void {
        echo "\n<!-- JAT Search Intel v" . esc_attr( JAT_SEARCH_INTEL_VERSION ) . " -->\n";

        $description = '';
        $robots      = '';

        if ( is_singular() ) {
            $post_id     = get_queried_object_id();
            $description = $this->get_description( $post_id );
        } elseif ( is_front_page() ) {
            $description = get_bloginfo( 'description' );
        } elseif ( $this->current_term() ) {
            $description = $this->get_term_description( $this->current_term() );
        }

        if ( $description ) {
            printf(
                '<meta name="description" content="%s" />' . "\n",
                esc_attr( wp_strip_all_tags( $description ) )
            );
        }

        // Search engine and social network ownership verification (they only look at the home page).
        if ( is_front_page() ) {
            foreach ( self::VERIFICATION_TAGS as $service => $tag_name ) {
                $code = (string) ( $this->settings[ 'verify_' . $service ] ?? '' );
                if ( '' !== $code ) {
                    printf( '<meta name="%s" content="%s" />' . "\n", esc_attr( $tag_name ), esc_attr( $code ) );
                }
            }
        }

        // Author meta for single posts
        if ( is_singular( 'post' ) ) {
            $author = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', get_queried_object_id() ) );
            if ( $author ) {
                printf( '<meta name="author" content="%s" />' . "\n", esc_attr( $author ) );
            }
        }
    }

    /**
     * Render canonical URL.
     */
    public function render_canonical(): void {
        if ( empty( $this->settings['enable_canonical'] ) ) {
            return;
        }

        $canonical = '';

        if ( is_singular() ) {
            $post_id   = get_queried_object_id();
            $custom    = get_post_meta( $post_id, '_jat_search_intel_canonical', true );
            $canonical = $custom ?: get_permalink( $post_id );
        } elseif ( is_front_page() ) {
            $canonical = home_url( '/' );
        } elseif ( $this->current_term() ) {
            $term      = $this->current_term();
            $custom    = Term_SEO::get( (int) $term->term_id, 'canonical' );
            $paged     = max( 1, (int) get_query_var( 'paged' ) );
            // Each page of an archive is its own canonical URL; page 2 is not a duplicate of page 1.
            $canonical = $custom ?: ( $paged > 1 ? get_pagenum_link( $paged, false ) : get_term_link( $term ) );
        }

        if ( $canonical && ! is_wp_error( $canonical ) ) {
            // Remove WP default canonical to avoid duplication
            remove_action( 'wp_head', 'rel_canonical' );
            printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
        }
    }

    /**
     * Render robots meta tag.
     */
    public function render_robots_meta(): void {
        if ( empty( $this->settings['enable_robots_meta'] ) ) {
            return;
        }

        printf(
            '<meta name="robots" content="%s" />' . "\n",
            esc_attr( implode( ', ', $this->get_robots_directives() ) )
        );
    }

    /**
     * Merge our directives into core's wp_robots array (WordPress 5.7+).
     *
     * @param array<string,bool|string> $robots Core robots directives.
     * @return array<string,bool|string>
     */
    public function filter_wp_robots( $robots ): array {
        $robots = (array) $robots;
        if ( empty( $this->settings['enable_robots_meta'] ) ) {
            return $robots;
        }

        foreach ( $this->get_robots_directives() as $directive ) {
            if ( false !== strpos( $directive, ':' ) ) {
                list( $key, $value ) = explode( ':', $directive, 2 );
                $robots[ $key ]      = $value;
            } else {
                $robots[ $directive ] = true;
            }
        }

        // noindex wins over core's "index"/"follow" defaults when both are present.
        if ( ! empty( $robots['noindex'] ) ) {
            unset( $robots['index'] );
        }
        if ( ! empty( $robots['nofollow'] ) ) {
            unset( $robots['follow'] );
        }

        return $robots;
    }

    /**
     * Robots directives for the current request.
     *
     * @return string[]
     */
    public function get_robots_directives(): array {
        $directives = [];

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $noindex = get_post_meta( $post_id, '_jat_search_intel_noindex', true );
            $nofollow = get_post_meta( $post_id, '_jat_search_intel_nofollow', true );

            if ( $noindex ) {
                $directives[] = 'noindex';
            }
            if ( $nofollow ) {
                $directives[] = 'nofollow';
            }
        }

        $term = $this->current_term();
        if ( $term ) {
            if ( Term_SEO::is_noindex( $term, $this->settings ) ) {
                $directives[] = 'noindex';
            }
            if ( '1' === Term_SEO::get( (int) $term->term_id, 'nofollow' ) ) {
                $directives[] = 'nofollow';
            }
        }

        if ( is_search() && ! empty( $this->settings['noindex_search'] ) ) {
            $directives[] = 'noindex';
        }

        if ( is_404() && ! empty( $this->settings['noindex_404'] ) ) {
            $directives[] = 'noindex';
        }

        if ( ( is_date() || is_author() ) && ! empty( $this->settings['noindex_archives'] ) ) {
            $directives[] = 'noindex';
        }

        // Always add max-snippet and max-image-preview for rich results
        $directives[] = 'max-snippet:-1';
        $directives[] = 'max-image-preview:large';
        $directives[] = 'max-video-preview:-1';

        return array_values( array_unique( $directives ) );
    }

    /**
     * Render Open Graph meta tags.
     */
    public function render_open_graph(): void {
        $tags = [];

        $tags['og:site_name'] = $this->settings['site_name'] ?: get_bloginfo( 'name' );
        $tags['og:locale']    = get_locale();

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $post    = get_post( $post_id );

            $tags['og:type']        = ( 'post' === $post->post_type ) ? 'article' : 'website';
            $social_title = get_post_meta( $post_id, '_jat_search_intel_social_title', true );
            $social_desc  = get_post_meta( $post_id, '_jat_search_intel_social_description', true );
            $tags['og:title']       = $social_title ?: $this->get_title( $post_id );
            $tags['og:description'] = $social_desc ?: $this->get_description( $post_id );
            $tags['og:url']         = get_permalink( $post_id );

            // Featured image
            $image = $this->get_og_image( $post_id );
            if ( $image ) {
                $tags['og:image']        = $image['url'];
                $tags['og:image:width']  = $image['width'];
                $tags['og:image:height'] = $image['height'];
                $tags['og:image:alt']    = $image['alt'];
            }

            if ( 'article' === $tags['og:type'] ) {
                $tags['article:published_time'] = get_the_date( 'c', $post_id );
                $tags['article:modified_time']  = get_the_modified_date( 'c', $post_id );
                $tags['article:author']         = get_author_posts_url( (int) $post->post_author );

                $section = Primary_Term::get_term( $post_id, 'category' );
                if ( $section ) {
                    $tags['article:section'] = $section->name;
                }

                $post_tags = get_the_tags( $post_id );
                if ( $post_tags ) {
                    foreach ( array_slice( $post_tags, 0, 5 ) as $tag ) {
                        $tags['article:tag'][] = $tag->name;
                    }
                }
            }
        } elseif ( is_front_page() ) {
            $tags['og:type']        = 'website';
            $tags['og:title']       = get_bloginfo( 'name' );
            $tags['og:description'] = get_bloginfo( 'description' );
            $tags['og:url']         = home_url( '/' );
        } elseif ( $this->current_term() ) {
            $term                   = $this->current_term();
            $term_id                = (int) $term->term_id;
            $tags['og:type']        = 'website';
            $tags['og:title']       = Term_SEO::get( $term_id, 'social_title' ) ?: wp_get_document_title();
            $tags['og:description'] = Term_SEO::get( $term_id, 'social_description' ) ?: $this->get_term_description( $term );
            $link                   = get_term_link( $term );
            $tags['og:url']         = is_wp_error( $link ) ? '' : $link;
            $image                  = $this->get_term_image( $term );
            if ( $image ) {
                $tags['og:image'] = $image;
            }
        } elseif ( is_archive() || is_home() ) {
            $tags['og:type']  = 'website';
            $tags['og:title'] = wp_get_document_title();
            if ( is_home() && get_option( 'page_for_posts' ) ) {
                $tags['og:url'] = get_permalink( (int) get_option( 'page_for_posts' ) );
            } elseif ( is_post_type_archive() ) {
                $tags['og:url'] = get_post_type_archive_link( (string) get_query_var( 'post_type' ) );
            }
        }

        foreach ( $tags as $property => $content ) {
            if ( is_array( $content ) ) {
                foreach ( $content as $value ) {
                    printf(
                        '<meta property="%s" content="%s" />' . "\n",
                        esc_attr( $property ),
                        esc_attr( $value )
                    );
                }
            } elseif ( $content ) {
                printf(
                    '<meta property="%s" content="%s" />' . "\n",
                    esc_attr( $property ),
                    esc_attr( $content )
                );
            }
        }
    }

    /**
     * Render Twitter Card tags.
     */
    public function render_twitter_card(): void {
        $tags = [];

        $tags['twitter:card'] = $this->settings['twitter_card_type'] ?: 'summary_large_image';

        if ( ! empty( $this->settings['twitter_site'] ) ) {
            $tags['twitter:site'] = $this->settings['twitter_site'];
        }

        if ( is_singular() ) {
            $post_id = get_queried_object_id();

            // X-specific overrides from the editor sidebar, then the shared social fields.
            $social_title = get_post_meta( $post_id, '_jat_search_intel_twitter_title', true ) ?: get_post_meta( $post_id, '_jat_search_intel_social_title', true );
            $social_desc  = get_post_meta( $post_id, '_jat_search_intel_twitter_description', true ) ?: get_post_meta( $post_id, '_jat_search_intel_social_description', true );
            $tags['twitter:title']       = $social_title ?: $this->get_title( $post_id );
            $tags['twitter:description'] = $social_desc ?: $this->get_description( $post_id );

            $twitter_image = (string) get_post_meta( $post_id, '_jat_search_intel_twitter_image', true );
            $image         = $twitter_image ? [ 'url' => $twitter_image, 'alt' => '' ] : $this->get_og_image( $post_id );
            if ( $image ) {
                $tags['twitter:image']     = $image['url'];
                $tags['twitter:image:alt'] = $image['alt'];
            }

            // Per-post Twitter creator
            $author_id = (int) get_post_field( 'post_author', $post_id );
            $twitter   = get_the_author_meta( 'twitter', $author_id );
            if ( $twitter ) {
                $tags['twitter:creator'] = '@' . ltrim( $twitter, '@' );
            }

            // Slack, X, Discord and others show these two labels in link previews.
            if ( is_singular( 'post' ) ) {
                $author_name = get_the_author_meta( 'display_name', $author_id );
                if ( $author_name ) {
                    $tags['twitter:label1'] = __( 'Written by', 'jat-search-intel' );
                    $tags['twitter:data1']  = $author_name;
                }
                $minutes = Reading_Time::minutes( $post_id );
                if ( $minutes > 0 ) {
                    $tags['twitter:label2'] = __( 'Est. reading time', 'jat-search-intel' );
                    $tags['twitter:data2']  = Reading_Time::label( $minutes );
                }
            }
        } elseif ( $this->current_term() ) {
            $term                        = $this->current_term();
            $term_id                     = (int) $term->term_id;
            $tags['twitter:title']       = Term_SEO::get( $term_id, 'social_title' ) ?: wp_get_document_title();
            $tags['twitter:description'] = Term_SEO::get( $term_id, 'social_description' ) ?: $this->get_term_description( $term );
            $image                       = $this->get_term_image( $term );
            if ( $image ) {
                $tags['twitter:image'] = $image;
            }
        }

        foreach ( $tags as $name => $content ) {
            if ( $content ) {
                printf(
                    '<meta name="%s" content="%s" />' . "\n",
                    esc_attr( $name ),
                    esc_attr( $content )
                );
            }
        }
    }

    /**
     * The term being viewed on a category, tag or custom taxonomy archive.
     *
     * @return \WP_Term|null
     */
    private function current_term(): ?\WP_Term {
        if ( ! ( is_category() || is_tag() || is_tax() ) ) {
            return null;
        }
        $term = get_queried_object();
        return $term instanceof \WP_Term ? $term : null;
    }

    /**
     * Meta description for a term: its SEO description, else its own description.
     *
     * @param \WP_Term $term Term.
     * @return string
     */
    public function get_term_description( \WP_Term $term ): string {
        $custom = Term_SEO::get( (int) $term->term_id, 'description' );
        if ( '' !== $custom ) {
            return $this->truncate_description( $custom );
        }
        if ( '' === trim( (string) $term->description ) ) {
            return '';
        }
        return $this->truncate_description( wp_trim_words( wp_strip_all_tags( (string) $term->description ), 30, '...' ) );
    }

    /**
     * Social image for a term: its own, else the site default.
     *
     * @param \WP_Term $term Term.
     * @return string
     */
    private function get_term_image( \WP_Term $term ): string {
        $image = Term_SEO::get( (int) $term->term_id, 'social_image' );
        if ( '' !== $image ) {
            return $image;
        }
        return (string) ( $this->settings['default_og_image'] ?? '' );
    }

    /**
     * Get Open Graph image data for a post.
     */
    private function get_og_image( int $post_id ): array {
        // Check for social image override (v1.1), then legacy OG image
        $custom_image = get_post_meta( $post_id, '_jat_search_intel_social_image', true );
        if ( ! $custom_image ) {
            $custom_image = get_post_meta( $post_id, '_jat_search_intel_og_image', true );
        }
        if ( $custom_image ) {
            $attachment_id = attachment_url_to_postid( $custom_image );
            $meta          = wp_get_attachment_metadata( $attachment_id );
            return [
                'url'    => $custom_image,
                'width'  => $meta['width'] ?? 1200,
                'height' => $meta['height'] ?? 630,
                'alt'    => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: $this->get_title( $post_id ),
            ];
        }

        // Fallback to featured image
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            $image = wp_get_attachment_image_src( $thumbnail_id, 'full' );
            if ( $image ) {
                return [
                    'url'    => $image[0],
                    'width'  => $image[1],
                    'height' => $image[2],
                    'alt'    => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: $this->get_title( $post_id ),
                ];
            }
        }

        // Fallback to default OG image setting (v1.1)
        if ( ! empty( $this->settings['default_og_image'] ) ) {
            return [
                'url'    => $this->settings['default_og_image'],
                'width'  => 1200,
                'height' => 630,
                'alt'    => $this->settings['site_name'] ?: get_bloginfo( 'name' ),
            ];
        }

        // Fallback to knowledge logo
        if ( ! empty( $this->settings['knowledge_logo'] ) ) {
            return [
                'url'    => $this->settings['knowledge_logo'],
                'width'  => 1200,
                'height' => 630,
                'alt'    => $this->settings['site_name'],
            ];
        }

        return [];
    }
}
