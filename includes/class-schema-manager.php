<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles JSON-LD structured data output: Article, Organization, WebSite, BreadcrumbList, Sitelinks SearchBox.
 */
class Schema_Manager {

    /** @var array */
    private $settings;

    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( empty( $settings['enable_schema'] ) ) {
            return;
        }

        add_action( 'wp_head', [ $this, 'render_schema' ], 10 );
    }

    /**
     * Collect and render all applicable JSON-LD blocks.
     */
    public function render_schema(): void {
        $graphs = [];

        // Detect which schema types are already rendered by Gutenberg blocks
        // to avoid duplicate output (block microdata + wp_head JSON-LD).
        $block_schemas = $this->detect_block_schemas();

        // Organization / Person (site-wide)
        $graphs[] = $this->get_knowledge_graph();

        // WebSite + Sitelinks SearchBox
        if ( is_front_page() && ! empty( $this->settings['enable_sitelinks'] ) ) {
            $graphs[] = $this->get_website_schema();
        }

        // Breadcrumbs — skip if breadcrumbs block is in the content.
        if ( ! empty( $this->settings['enable_breadcrumbs'] ) && ! is_front_page() && ! in_array( 'BreadcrumbList', $block_schemas, true ) ) {
            $breadcrumbs = $this->get_breadcrumbs_schema();
            if ( $breadcrumbs ) {
                $graphs[] = $breadcrumbs;
            }
        }

        // Article / WebPage on singular
        if ( is_singular() ) {
            $graphs[] = $this->get_singular_schema( get_queried_object_id() );
        }

        // Render
        $graphs = array_filter( $graphs );
        if ( empty( $graphs ) ) {
            return;
        }

        $output = [
            '@context' => 'https://schema.org',
            '@graph'   => $graphs,
        ];

        printf(
            "\n" . '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
        );
    }

    /**
     * Detect Search Intel blocks in the current post content that already output schema.
     *
     * Returns an array of schema type names that blocks handle via microdata,
     * so wp_head JSON-LD can skip those types to prevent duplication.
     *
     * @return array<string> Schema type names (e.g. 'FAQPage', 'HowTo', 'BreadcrumbList').
     */
    private function detect_block_schemas(): array {
        if ( ! is_singular() ) {
            return [];
        }

        $post = get_post();
        if ( ! $post || empty( $post->post_content ) ) {
            return [];
        }

        if ( ! function_exists( 'has_block' ) ) {
            return [];
        }

        $schemas = [];

        if ( has_block( 'search-intel/faq', $post ) ) {
            $schemas[] = 'FAQPage';
        }

        if ( has_block( 'search-intel/howto', $post ) ) {
            $schemas[] = 'HowTo';
        }

        if ( has_block( 'search-intel/breadcrumbs', $post ) ) {
            $schemas[] = 'BreadcrumbList';
        }

        return $schemas;
    }

    /**
     * Organization or Person knowledge graph entity.
     */
    private function get_knowledge_graph(): array {
        $type = $this->settings['knowledge_type'] ?: 'Organization';
        $name = $this->settings['knowledge_name'] ?: get_bloginfo( 'name' );

        $schema = [
            '@type' => $type,
            '@id'   => home_url( '/#' . strtolower( $type ) ),
            'name'  => $name,
            'url'   => home_url( '/' ),
        ];

        if ( ! empty( $this->settings['knowledge_logo'] ) ) {
            $schema['logo'] = [
                '@type'      => 'ImageObject',
                '@id'        => home_url( '/#logo' ),
                'url'        => $this->settings['knowledge_logo'],
                'contentUrl' => $this->settings['knowledge_logo'],
                'caption'    => $name,
            ];
            $schema['image'] = [ '@id' => home_url( '/#logo' ) ];
        }

        // Social profiles
        $social_urls = $this->get_social_urls();
        if ( $social_urls ) {
            $schema['sameAs'] = $social_urls;
        }

        return $schema;
    }

    /**
     * WebSite schema with SearchAction for sitelinks searchbox.
     */
    private function get_website_schema(): array {
        $schema = [
            '@type'       => 'WebSite',
            '@id'         => home_url( '/#website' ),
            'url'         => home_url( '/' ),
            'name'        => $this->settings['site_name'] ?: get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'publisher'   => [
                '@id' => home_url( '/#' . strtolower( $this->settings['knowledge_type'] ?: 'organization' ) ),
            ],
            'inLanguage'  => get_locale(),
        ];

        // Sitelinks SearchBox
        $schema['potentialAction'] = [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => home_url( '/?s={search_term_string}' ),
            ],
            'query-input' => 'required name=search_term_string',
        ];

        return $schema;
    }

    /**
     * BreadcrumbList schema.
     */
    private function get_breadcrumbs_schema(): ?array {
        $crumbs   = [];
        $position = 1;

        // Home
        $crumbs[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => __( 'Home', 'search-intel' ),
            'item'     => home_url( '/' ),
        ];

        if ( is_singular() ) {
            $post_id   = get_queried_object_id();
            $post_type = get_post_type( $post_id );

            // Category breadcrumb for posts
            if ( 'post' === $post_type ) {
                $categories = get_the_category( $post_id );
                if ( $categories ) {
                    $primary = $categories[0];
                    // Walk up parent categories
                    $ancestors = get_ancestors( $primary->term_id, 'category' );
                    $ancestors = array_reverse( $ancestors );
                    foreach ( $ancestors as $ancestor_id ) {
                        $ancestor = get_term( $ancestor_id, 'category' );
                        $crumbs[] = [
                            '@type'    => 'ListItem',
                            'position' => $position++,
                            'name'     => $ancestor->name,
                            'item'     => get_term_link( $ancestor ),
                        ];
                    }
                    $crumbs[] = [
                        '@type'    => 'ListItem',
                        'position' => $position++,
                        'name'     => $primary->name,
                        'item'     => get_term_link( $primary ),
                    ];
                }
            }

            // Page ancestors
            if ( 'page' === $post_type ) {
                $ancestors = get_post_ancestors( $post_id );
                $ancestors = array_reverse( $ancestors );
                foreach ( $ancestors as $ancestor_id ) {
                    $crumbs[] = [
                        '@type'    => 'ListItem',
                        'position' => $position++,
                        'name'     => get_the_title( $ancestor_id ),
                        'item'     => get_permalink( $ancestor_id ),
                    ];
                }
            }

            // Current post/page
            $crumbs[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'name'     => get_the_title( $post_id ),
            ];
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term     = get_queried_object();
            $crumbs[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'name'     => $term->name,
            ];
        }

        if ( count( $crumbs ) < 2 ) {
            return null;
        }

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => $this->get_current_url() . '#breadcrumb',
            'itemListElement' => $crumbs,
        ];
    }

    /**
     * Article / WebPage schema for singular content.
     */
    public function get_singular_schema( int $post_id ): array {
        $post      = get_post( $post_id );
        $post_type = $post->post_type;

        // Determine schema type
        $custom_type = get_post_meta( $post_id, '_search_intel_schema_type', true );
        if ( $custom_type ) {
            $type = $custom_type;
        } elseif ( 'post' === $post_type ) {
            $type = $this->settings['schema_article_type'] ?: 'Article';
        } else {
            $type = 'WebPage';
        }

        $schema = [
            '@type'            => $type,
            '@id'              => get_permalink( $post_id ) . '#' . strtolower( $type ),
            'headline'         => get_the_title( $post_id ),
            'description'      => wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' ),
            'url'              => get_permalink( $post_id ),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post_id ),
            ],
            'datePublished'    => get_the_date( 'c', $post_id ),
            'dateModified'     => get_the_modified_date( 'c', $post_id ),
            'inLanguage'       => get_locale(),
            'isPartOf'         => [
                '@id' => home_url( '/#website' ),
            ],
        ];

        // Author
        $author_id = (int) $post->post_author;
        $schema['author'] = [
            '@type' => 'Person',
            '@id'   => get_author_posts_url( $author_id ) . '#author',
            'name'  => get_the_author_meta( 'display_name', $author_id ),
            'url'   => get_author_posts_url( $author_id ),
        ];

        // Author description for E-E-A-T
        $author_desc = get_the_author_meta( 'description', $author_id );
        if ( $author_desc ) {
            $schema['author']['description'] = $author_desc;
        }

        // Publisher
        $schema['publisher'] = [
            '@id' => home_url( '/#' . strtolower( $this->settings['knowledge_type'] ?: 'organization' ) ),
        ];

        // Featured image
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            $image = wp_get_attachment_image_src( $thumbnail_id, 'full' );
            if ( $image ) {
                $schema['image'] = [
                    '@type'  => 'ImageObject',
                    'url'    => $image[0],
                    'width'  => $image[1],
                    'height' => $image[2],
                ];
            }
        }

        // Word count
        $word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
        if ( $word_count > 0 ) {
            $schema['wordCount'] = $word_count;
        }

        // Article-specific properties
        if ( in_array( $type, [ 'Article', 'NewsArticle', 'BlogPosting', 'TechArticle', 'ScholarlyArticle' ], true ) ) {
            $categories = get_the_category( $post_id );
            if ( $categories ) {
                $schema['articleSection'] = wp_list_pluck( $categories, 'name' );
            }

            $tags = get_the_tags( $post_id );
            if ( $tags ) {
                $schema['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
            }
        }

        // v1.1: Direct answer as abstract
        $direct_answer = get_post_meta( $post_id, '_search_intel_direct_answer', true );
        if ( $direct_answer ) {
            $schema['abstract'] = sanitize_text_field( $direct_answer );
        }

        // v1.1: Citations as CreativeWork references
        $citations = get_post_meta( $post_id, '_search_intel_citations', true );
        if ( ! empty( $citations ) && is_array( $citations ) ) {
            $cite_schemas = [];
            foreach ( $citations as $cite ) {
                if ( empty( $cite['source'] ) && empty( $cite['url'] ) ) {
                    continue;
                }
                $cite_schema = [
                    '@type' => 'CreativeWork',
                ];
                if ( ! empty( $cite['source'] ) ) {
                    $cite_schema['name'] = sanitize_text_field( $cite['source'] );
                }
                if ( ! empty( $cite['url'] ) ) {
                    $cite_schema['url'] = esc_url( $cite['url'] );
                }
                if ( ! empty( $cite['date'] ) ) {
                    $cite_schema['datePublished'] = sanitize_text_field( $cite['date'] );
                }
                if ( ! empty( $cite['quote'] ) ) {
                    $cite_schema['text'] = sanitize_text_field( $cite['quote'] );
                }
                $cite_schemas[] = $cite_schema;
            }
            if ( ! empty( $cite_schemas ) ) {
                $schema['citation'] = $cite_schemas;
            }
        }

        // v1.1: Content intent
        $content_intent = get_post_meta( $post_id, '_search_intel_content_intent', true );
        if ( $content_intent ) {
            $schema['creativeWorkStatus'] = sanitize_text_field( $content_intent );
        }

        // v1.1: Audience
        $audience = get_post_meta( $post_id, '_search_intel_audience', true );
        if ( $audience ) {
            $schema['audience'] = [
                '@type'         => 'Audience',
                'audienceType'  => sanitize_text_field( $audience ),
            ];
        }

        // v1.1: Author expertise notes
        $expertise_notes = get_post_meta( $post_id, '_search_intel_author_expertise_notes', true );
        if ( $expertise_notes && ! empty( $schema['author'] ) ) {
            $schema['author']['knowsAbout'] = sanitize_text_field( $expertise_notes );
        }

        // v1.1: Local relevance — contentLocation
        $local_relevance = get_post_meta( $post_id, '_search_intel_local_relevance', true );
        $local_location  = get_post_meta( $post_id, '_search_intel_local_location', true );
        if ( $local_relevance && $local_location ) {
            $schema['contentLocation'] = [
                '@type' => 'Place',
                'name'  => sanitize_text_field( $local_location ),
            ];
        }

        // Breadcrumb reference
        if ( ! empty( $this->settings['enable_breadcrumbs'] ) ) {
            $schema['breadcrumb'] = [
                '@id' => get_permalink( $post_id ) . '#breadcrumb',
            ];
        }

        return apply_filters( 'search_intel_singular_schema', $schema, $post_id );
    }

    /**
     * Get social profile URLs from customizer or options.
     */
    private function get_social_urls(): array {
        $urls     = [];
        $profiles = get_option( 'search_intel_social_profiles', [] );

        $platforms = [
            'facebook', 'twitter', 'instagram', 'linkedin',
            'youtube', 'pinterest', 'tiktok', 'github',
        ];

        foreach ( $platforms as $platform ) {
            if ( ! empty( $profiles[ $platform ] ) ) {
                $urls[] = $profiles[ $platform ];
            }
        }

        return $urls;
    }

    /**
     * Get current page URL.
     */
    private function get_current_url(): string {
        global $wp;
        return home_url( add_query_arg( [], $wp->request ) );
    }
}
