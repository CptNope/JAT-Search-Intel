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

    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( empty( $settings['enable_seo'] ) ) {
            return;
        }

        add_action( 'wp_head', [ $this, 'render_meta_tags' ], 1 );
        add_action( 'wp_head', [ $this, 'render_canonical' ], 2 );
        add_action( 'wp_head', [ $this, 'render_robots_meta' ], 3 );

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
                    $desc = $post->post_excerpt ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
                } else {
                    $desc = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
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
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term && ! empty( $term->description ) ) {
                $description = wp_trim_words( $term->description, 30, '...' );
            }
        }

        if ( $description ) {
            printf(
                '<meta name="description" content="%s" />' . "\n",
                esc_attr( wp_strip_all_tags( $description ) )
            );
        }

        // Author meta for single posts
        if ( is_singular( 'post' ) ) {
            $author = get_the_author_meta( 'display_name', get_post_field( 'post_author', get_queried_object_id() ) );
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
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $canonical = get_term_link( get_queried_object() );
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

        printf(
            '<meta name="robots" content="%s" />' . "\n",
            esc_attr( implode( ', ', array_unique( $directives ) ) )
        );
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

                $categories = get_the_category( $post_id );
                if ( $categories ) {
                    $tags['article:section'] = $categories[0]->name;
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

            $social_title = get_post_meta( $post_id, '_jat_search_intel_social_title', true );
            $social_desc  = get_post_meta( $post_id, '_jat_search_intel_social_description', true );
            $tags['twitter:title']       = $social_title ?: $this->get_title( $post_id );
            $tags['twitter:description'] = $social_desc ?: $this->get_description( $post_id );

            $image = $this->get_og_image( $post_id );
            if ( $image ) {
                $tags['twitter:image']     = $image['url'];
                $tags['twitter:image:alt'] = $image['alt'];
            }

            // Per-post Twitter creator
            $author_id = get_post_field( 'post_author', $post_id );
            $twitter   = get_the_author_meta( 'twitter', $author_id );
            if ( $twitter ) {
                $tags['twitter:creator'] = '@' . ltrim( $twitter, '@' );
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
