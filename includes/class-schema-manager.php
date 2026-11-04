<?php
namespace JAT_Search_Intel;

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
     * WebPage-family types: when the singular schema type is one of these it
     * describes the page itself and is merged into the WebPage node.
     */
    private const WEBPAGE_TYPES = [
        'WebPage', 'AboutPage', 'ContactPage', 'FAQPage', 'QAPage', 'ProfilePage',
        'CollectionPage', 'ItemPage', 'MedicalWebPage', 'CheckoutPage', 'SearchResultsPage',
    ];

    /**
     * Render the connected JSON-LD graph for the current request.
     */
    public function render_schema(): void {
        $graph = $this->build_graph();
        if ( empty( $graph ) ) {
            return;
        }

        $output = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];

        printf(
            "\n" . '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT )
        );
    }

    /**
     * Build one connected @graph: publisher, WebSite, WebPage, primary image,
     * breadcrumb, main entity (Article etc.) and author, all linked by @id.
     * Other modules add nodes through the `jat_search_intel_schema_graph`
     * filter; nodes sharing an @id are merged.
     *
     * @return array<int,array<string,mixed>>
     */
    public function build_graph(): array {
        $nodes         = [];
        $block_schemas = $this->detect_block_schemas();
        $publisher     = $this->get_knowledge_graph();
        $nodes[]       = $publisher;

        $website = $this->get_website_schema();
        if ( empty( $this->settings['enable_sitelinks'] ) ) {
            unset( $website['potentialAction'] );
        }
        $nodes[] = $website;

        $page    = $this->get_webpage_node();
        $page_id = $page['@id'];

        // Breadcrumbs — skip if the breadcrumbs block already marks them up.
        if ( ! empty( $this->settings['enable_breadcrumbs'] ) && ! is_front_page() && ! in_array( 'BreadcrumbList', $block_schemas, true ) ) {
            $breadcrumbs = $this->get_breadcrumbs_schema();
            if ( $breadcrumbs ) {
                $breadcrumbs['@id'] = $page_id . '#breadcrumb';
                $page['breadcrumb'] = [ '@id' => $breadcrumbs['@id'] ];
                $nodes[]            = $breadcrumbs;
            }
        }

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $main    = $this->get_singular_schema( $post_id );

            // Primary image becomes its own node.
            if ( ! empty( $main['image'] ) && is_array( $main['image'] ) && isset( $main['image']['url'] ) ) {
                $image                      = $main['image'];
                $image['@id']               = $page_id . '#primaryimage';
                $image['contentUrl']        = $image['url'];
                $nodes[]                    = $image;
                $main['image']              = [ '@id' => $image['@id'] ];
                $page['primaryImageOfPage'] = [ '@id' => $image['@id'] ];
                $page['image']              = [ '@id' => $image['@id'] ];
            }

            // Author becomes its own Person node.
            if ( ! empty( $main['author']['@id'] ) ) {
                $nodes[]        = $main['author'];
                $main['author'] = [ '@id' => $main['author']['@id'] ];
            }

            unset( $main['breadcrumb'] );
            $main_type = is_array( $main['@type'] ) ? reset( $main['@type'] ) : (string) $main['@type'];

            if ( in_array( $main_type, self::WEBPAGE_TYPES, true ) ) {
                // The main entity *is* the page.
                $main['@id'] = $page_id;
                unset( $main['mainEntityOfPage'], $main['isPartOf'] );
                $page = $this->merge_node( $page, $main, true );
            } else {
                $main['@id']              = $page_id . '#' . strtolower( $main_type );
                $main['isPartOf']         = [ '@id' => $page_id ];
                $main['mainEntityOfPage'] = [
                    '@type' => 'WebPage',
                    '@id'   => $page_id,
                ];
                $nodes[] = $main;
            }
        }

        $nodes[] = $page;

        /**
         * Filters the JSON-LD graph nodes before output.
         *
         * Nodes that share an @id are merged afterwards, so modules can extend
         * the WebPage or author nodes by emitting a node with the same @id.
         *
         * @param array $nodes   Graph nodes.
         * @param array $context { page_id: string, publisher_id: string, website_id: string }.
         */
        $nodes = apply_filters(
            'jat_search_intel_schema_graph',
            $nodes,
            [
                'page_id'      => $page_id,
                'publisher_id' => $publisher['@id'],
                'website_id'   => $website['@id'],
            ]
        );

        return $this->merge_nodes_by_id( array_values( array_filter( (array) $nodes ) ) );
    }

    /**
     * WebPage-family node describing the current URL.
     *
     * @return array<string,mixed>
     */
    private function get_webpage_node(): array {
        $type = 'WebPage';
        $url  = $this->get_current_url();
        $name = wp_get_document_title();

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $url     = (string) get_permalink( $post_id );
        } elseif ( is_front_page() || is_home() ) {
            $url  = is_front_page() ? home_url( '/' ) : (string) get_permalink( (int) get_option( 'page_for_posts' ) );
            $type = is_home() && ! is_front_page() ? 'CollectionPage' : 'WebPage';
        } elseif ( is_author() ) {
            $type = 'ProfilePage';
        } elseif ( is_search() ) {
            $type = 'SearchResultsPage';
        } elseif ( is_archive() ) {
            $type = 'CollectionPage';
        }

        $node = [
            '@type'      => $type,
            '@id'        => $url,
            'url'        => $url,
            'name'       => $name,
            'isPartOf'   => [ '@id' => home_url( '/#website' ) ],
            'inLanguage' => get_bloginfo( 'language' ),
        ];

        if ( is_front_page() ) {
            $node['about'] = [ '@id' => $this->get_publisher_id() ];
        }

        if ( is_singular() ) {
            $post_id               = get_queried_object_id();
            $node['datePublished'] = get_the_date( 'c', $post_id );
            $node['dateModified']  = get_the_modified_date( 'c', $post_id );
            $description           = $this->get_description( $post_id );
            if ( $description ) {
                $node['description'] = $description;
            }
            $node['potentialAction'] = [
                [
                    '@type'  => 'ReadAction',
                    'target' => [ $url ],
                ],
            ];
        }

        if ( is_author() ) {
            $author = get_queried_object();
            if ( $author instanceof \WP_User ) {
                $node['mainEntity'] = [ '@id' => get_author_posts_url( $author->ID ) . '#author' ];
            }
        }

        return $node;
    }

    /**
     * Meta description (or excerpt) for a post.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    private function get_description( int $post_id ): string {
        $description = (string) get_post_meta( $post_id, '_jat_search_intel_description', true );
        if ( '' === $description ) {
            $post        = get_post( $post_id );
            $description = $post ? ( Content_Guard::excerpt( $post ) ?: wp_trim_words( wp_strip_all_tags( strip_shortcodes( Content_Guard::content( $post ) ) ), 30, '...' ) ) : '';
        }
        return wp_strip_all_tags( $description );
    }

    /**
     * @id of the site publisher (Organization or Person).
     *
     * @return string
     */
    private function get_publisher_id(): string {
        return home_url( '/#' . strtolower( $this->settings['knowledge_type'] ?: 'organization' ) );
    }

    /**
     * Merge nodes that share an @id; first occurrence keeps its position.
     *
     * @param array<int,array<string,mixed>> $nodes Nodes.
     * @return array<int,array<string,mixed>>
     */
    public function merge_nodes_by_id( array $nodes ): array {
        $merged = [];
        $index  = [];

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $id = isset( $node['@id'] ) && is_string( $node['@id'] ) ? $node['@id'] : null;
            if ( null !== $id && isset( $index[ $id ] ) ) {
                $merged[ $index[ $id ] ] = $this->merge_node( $merged[ $index[ $id ] ], $node );
                continue;
            }
            if ( null !== $id ) {
                $index[ $id ] = count( $merged );
            }
            $merged[] = $node;
        }

        return $merged;
    }

    /**
     * Merge two descriptions of the same entity.
     *
     * @param array<string,mixed> $base      Existing node.
     * @param array<string,mixed> $extra     Additional node.
     * @param bool                $overwrite Whether $extra wins on scalar conflicts.
     * @return array<string,mixed>
     */
    private function merge_node( array $base, array $extra, bool $overwrite = false ): array {
        foreach ( $extra as $key => $value ) {
            if ( '@type' === $key ) {
                $types = array_values( array_unique( array_merge( (array) $base['@type'], (array) $value ) ) );
                if ( $overwrite ) {
                    $types = array_values( array_unique( array_merge( (array) $value, (array) $base['@type'] ) ) );
                }
                $base['@type'] = 1 === count( $types ) ? $types[0] : $types;
                continue;
            }
            if ( ! array_key_exists( $key, $base ) || $overwrite ) {
                $base[ $key ] = $value;
            } elseif ( is_array( $base[ $key ] ) && is_array( $value ) && array_is_list( $base[ $key ] ) && array_is_list( $value ) ) {
                $base[ $key ] = array_values( array_unique( array_merge( $base[ $key ], $value ), SORT_REGULAR ) );
            } elseif ( is_array( $base[ $key ] ) && is_array( $value ) && ! array_is_list( $base[ $key ] ) ) {
                $base[ $key ] = array_merge( $value, $base[ $key ] );
            }
        }
        return $base;
    }

    /**
     * Detect JAT Search Intel blocks in the current post content that already output schema.
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
        if ( ! $post || '' === Content_Guard::content( $post ) ) {
            return [];
        }

        if ( ! function_exists( 'has_block' ) ) {
            return [];
        }

        $schemas = [];

        if ( has_block( 'jat-search-intel/faq', $post ) ) {
            $schemas[] = 'FAQPage';
        }

        if ( has_block( 'jat-search-intel/howto', $post ) ) {
            $schemas[] = 'HowTo';
        }

        if ( has_block( 'jat-search-intel/breadcrumbs', $post ) ) {
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
                '@id' => $this->get_publisher_id(),
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
            'name'     => __( 'Home', 'jat-search-intel' ),
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
        $custom_type = get_post_meta( $post_id, '_jat_search_intel_schema_type', true );
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
            'description'      => wp_trim_words( wp_strip_all_tags( Content_Guard::content( $post ) ), 30, '...' ),
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
        $direct_answer = get_post_meta( $post_id, '_jat_search_intel_direct_answer', true );
        if ( $direct_answer && ! Content_Guard::is_protected( $post ) ) {
            $schema['abstract'] = sanitize_text_field( $direct_answer );
        }

        // v1.1: Citations as CreativeWork references
        $citations = get_post_meta( $post_id, '_jat_search_intel_citations', true );
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
        $content_intent = get_post_meta( $post_id, '_jat_search_intel_content_intent', true );
        if ( $content_intent ) {
            $schema['creativeWorkStatus'] = sanitize_text_field( $content_intent );
        }

        // v1.1: Audience
        $audience = get_post_meta( $post_id, '_jat_search_intel_audience', true );
        if ( $audience ) {
            $schema['audience'] = [
                '@type'         => 'Audience',
                'audienceType'  => sanitize_text_field( $audience ),
            ];
        }

        // v1.1: Author expertise notes
        $expertise_notes = get_post_meta( $post_id, '_jat_search_intel_author_expertise_notes', true );
        if ( $expertise_notes && ! empty( $schema['author'] ) ) {
            $schema['author']['knowsAbout'] = sanitize_text_field( $expertise_notes );
        }

        // v1.1: Local relevance — contentLocation
        $local_relevance = get_post_meta( $post_id, '_jat_search_intel_local_relevance', true );
        $local_location  = get_post_meta( $post_id, '_jat_search_intel_local_location', true );
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

        return apply_filters( 'jat_search_intel_singular_schema', $schema, $post_id );
    }

    /**
     * Get social profile URLs from customizer or options.
     */
    private function get_social_urls(): array {
        $urls     = [];
        $profiles = get_option( 'jat_search_intel_social_profiles', [] );

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
