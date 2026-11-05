<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generative Engine Optimization (GEO) Manager.
 *
 * Optimizes content for AI-powered answer engines (ChatGPT, Perplexity, Google AI Overviews, etc.).
 * Features: AI meta directives, llms.txt, entity markup, E-E-A-T signals, citation optimization,
 * content freshness signals, and topical authority mapping.
 */
class GEO_Manager {

    /** @var array */
    private $settings;

    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( empty( $settings['enable_geo'] ) ) {
            return;
        }

        // AI-specific meta tags
        if ( ! empty( $settings['enable_ai_meta'] ) ) {
            add_action( 'wp_head', [ $this, 'render_ai_meta' ], 6 );
        }

        // llms.txt endpoint
        if ( ! empty( $settings['enable_llms_txt'] ) ) {
            // Register late so our rewrite rule lands on top of other plugins' rules.
            add_action( 'init', [ $this, 'register_llms_txt_endpoint' ], 999 );
            add_action( 'template_redirect', [ $this, 'serve_llms_txt' ], 1 );
            add_action( 'save_post', [ $this, 'flush_llms_cache' ] );
            add_action( 'deleted_post', [ $this, 'flush_llms_cache' ] );
            add_action( 'update_option_jat_search_intel_settings', [ $this, 'flush_llms_cache' ] );

            // A physical llms.txt in the web root (often written by another
            // plugin) shadows our dynamic endpoint. We never delete it on our
            // own: admins get a notice and can remove it with one click.
            if ( is_admin() ) {
                add_action( 'admin_notices', [ $this, 'static_llms_txt_notice' ] );
                add_action( 'admin_post_jat_search_intel_remove_static_llms', [ $this, 'handle_remove_static_llms_txt' ] );
            }
        }

        // Entity and authority signals in content
        if ( ! empty( $settings['enable_entity_markup'] ) ) {
            add_filter( 'the_content', [ $this, 'enhance_content_entities' ], 99 );
        }

        // Content freshness
        if ( ! empty( $settings['content_freshness'] ) ) {
            add_action( 'wp_head', [ $this, 'render_freshness_meta' ], 7 );
        }

        // AI crawl directives
        add_action( 'wp_head', [ $this, 'render_ai_crawl_directives' ], 3 );

        // Add author E-E-A-T structured data
        add_action( 'wp_head', [ $this, 'render_author_eeat' ], 13 );
        add_filter( 'jat_search_intel_schema_graph', [ $this, 'add_graph_nodes' ], 10, 2 );
    }

    /**
     * Render AI-specific meta tags for better indexing by LLMs and AI search engines.
     */
    public function render_ai_meta(): void {
        if ( is_singular() && Content_Guard::is_protected( get_queried_object_id() ) ) {
            return; // Summaries would reveal password-protected content.
        }
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_queried_object_id();
        $post    = get_post( $post_id );

        // AI-readable summary (concise version of the content for LLM consumption)
        $ai_summary = get_post_meta( $post_id, '_jat_search_intel_ai_summary', true );
        if ( ! $ai_summary ) {
            $ai_summary = get_post_meta( $post_id, '_jat_search_intel_description', true ) ?: $post->post_excerpt;
        }
        if ( ! $ai_summary ) {
            $ai_summary = wp_trim_words( self::plain_text( $post->post_content ), 50, '...' );
        }

        if ( $ai_summary ) {
            printf(
                '<meta name="ai-summary" content="%s" />' . "\n",
                esc_attr( $ai_summary )
            );
        }

        // Key topics / entities for the content
        $topics = get_post_meta( $post_id, '_jat_search_intel_topics', true );
        if ( empty( $topics ) ) {
            $topics = $this->auto_extract_topics( $post_id );
        }

        if ( $topics && is_array( $topics ) ) {
            printf(
                '<meta name="keywords" content="%s" />' . "\n",
                esc_attr( implode( ', ', array_map( 'sanitize_text_field', $topics ) ) )
            );
        }

        // Content type hint for AI engines
        $content_type = get_post_meta( $post_id, '_jat_search_intel_content_type', true );
        if ( ! $content_type ) {
            $content_type = $this->detect_content_type( $post_id );
        }

        printf(
            '<meta name="content-type" content="%s" />' . "\n",
            esc_attr( $content_type )
        );

        // Expertise level
        $expertise = get_post_meta( $post_id, '_jat_search_intel_expertise_level', true );
        if ( $expertise ) {
            printf(
                '<meta name="expertise-level" content="%s" />' . "\n",
                esc_attr( $expertise )
            );
        }

        // Date accuracy signal (the Open Graph article:modified_time property is printed by the SEO module).
        if ( empty( $this->settings['enable_og'] ) || empty( $this->settings['enable_seo'] ) ) {
            printf(
                '<meta property="article:modified_time" content="%s" />' . "\n",
                esc_attr( get_the_modified_date( 'c', $post_id ) )
            );
        }

        // v1.1: Content intent meta
        $content_intent = get_post_meta( $post_id, '_jat_search_intel_content_intent', true );
        if ( $content_intent ) {
            printf(
                '<meta name="content-intent" content="%s" />' . "\n",
                esc_attr( $content_intent )
            );
        }

        // v1.1: Target audience meta
        $audience = get_post_meta( $post_id, '_jat_search_intel_audience', true );
        if ( $audience ) {
            printf(
                '<meta name="target-audience" content="%s" />' . "\n",
                esc_attr( $audience )
            );
        }

        // v1.1: Author expertise notes
        $expertise_notes = get_post_meta( $post_id, '_jat_search_intel_author_expertise_notes', true );
        if ( $expertise_notes ) {
            printf(
                '<meta name="author-expertise" content="%s" />' . "\n",
                esc_attr( $expertise_notes )
            );
        }

        // v1.1: Local relevance signal
        $local_relevance = get_post_meta( $post_id, '_jat_search_intel_local_relevance', true );
        $local_location  = get_post_meta( $post_id, '_jat_search_intel_local_location', true );
        if ( $local_relevance && $local_location ) {
            printf(
                '<meta name="geo.placename" content="%s" />' . "\n",
                esc_attr( $local_location )
            );
        }
    }

    /**
     * Render content freshness meta signals.
     */
    public function render_freshness_meta(): void {
        if ( ! is_singular() ) {
            return;
        }

        $post_id  = get_queried_object_id();
        $modified = get_the_modified_date( 'U', $post_id );
        $now      = time();
        $age_days = ( $now - $modified ) / DAY_IN_SECONDS;

        // Freshness indicator
        if ( $age_days <= 7 ) {
            $freshness = 'very-fresh';
        } elseif ( $age_days <= 30 ) {
            $freshness = 'fresh';
        } elseif ( $age_days <= 90 ) {
            $freshness = 'recent';
        } elseif ( $age_days <= 365 ) {
            $freshness = 'aging';
        } else {
            $freshness = 'evergreen';
        }

        printf(
            '<meta name="content-freshness" content="%s" data-last-modified="%s" />' . "\n",
            esc_attr( $freshness ),
            esc_attr( get_the_modified_date( 'c', $post_id ) )
        );

        // Fact-check date if set
        $fact_checked = get_post_meta( $post_id, '_jat_search_intel_fact_checked', true );
        if ( $fact_checked ) {
            printf(
                '<meta name="fact-check-date" content="%s" />' . "\n",
                esc_attr( $fact_checked )
            );
        }
    }

    /**
     * Render AI crawl directives — control how AI bots can use your content.
     */
    public function render_ai_crawl_directives(): void {
        $post_id    = is_singular() ? get_queried_object_id() : 0;
        $ai_noindex = $post_id ? get_post_meta( $post_id, '_jat_search_intel_ai_noindex', true ) : false;

        if ( $ai_noindex ) {
            // Block specific AI crawlers
            $ai_bots = [ 'GPTBot', 'ChatGPT-User', 'Google-Extended', 'CCBot', 'anthropic-ai', 'ClaudeBot' ];
            foreach ( $ai_bots as $bot ) {
                printf( '<meta name="%s" content="noindex, nofollow" />' . "\n", esc_attr( $bot ) );
            }
        }

        // Opt-in signals for AI crawlers (site-wide default)
        $ai_permissions = get_option( 'jat_search_intel_ai_permissions', 'allow' );
        if ( 'restrict' === $ai_permissions ) {
            echo '<meta name="robots" content="noai, noimageai" />' . "\n";
        }
    }

    /**
     * Paths of physical llms files in the web root that would shadow the
     * dynamic endpoint.
     *
     * @return string[]
     */
    public function get_static_llms_files(): array {
        $abspath = defined( 'ABSPATH' ) ? ABSPATH : '';
        if ( '' === $abspath ) {
            return [];
        }

        return array_values( array_filter(
            [ $abspath . 'llms.txt', $abspath . 'llms-full.txt' ],
            'is_file'
        ) );
    }

    /**
     * Admin notice shown when a static llms.txt shadows the dynamic one.
     */
    public function static_llms_txt_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || ( 'plugins' !== $screen->id && false === strpos( $screen->id, 'jat-search-intel' ) ) ) {
            return;
        }

        $files = $this->get_static_llms_files();
        if ( empty( $files ) ) {
            return;
        }

        $url = wp_nonce_url( admin_url( 'admin-post.php?action=jat_search_intel_remove_static_llms' ), 'jat_search_intel_remove_static_llms' );

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__( 'A static llms.txt file in your site root is overriding the one generated by JAT Search Intel. It may have been created by another plugin.', 'jat-search-intel' );
        echo ' <a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Remove static file', 'jat-search-intel' ) . '</a>';
        echo '</p></div>';
    }

    /**
     * Remove static llms files after an explicit admin request.
     */
    public function handle_remove_static_llms_txt(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'jat-search-intel' ), 403 );
        }
        check_admin_referer( 'jat_search_intel_remove_static_llms' );

        $this->cleanup_static_llms_txt();

        wp_safe_redirect( wp_get_referer() ?: admin_url( 'plugins.php' ) );
        exit;
    }

    /**
     * Remove static llms.txt / llms-full.txt files from the web root.
     *
     * Only called from the admin action above, never automatically.
     */
    public function cleanup_static_llms_txt(): void {
        foreach ( $this->get_static_llms_files() as $file ) {
            wp_delete_file( $file );
        }
    }

    /**
     * Register the llms.txt rewrite endpoint.
     */
    public function register_llms_txt_endpoint(): void {
        add_rewrite_rule( '^llms\.txt$', 'index.php?jat_search_intel_llms_txt=1', 'top' );
        add_rewrite_rule( '^llms-full\.txt$', 'index.php?jat_search_intel_llms_full_txt=1', 'top' );
        add_filter( 'query_vars', function ( $vars ) {
            $vars[] = 'jat_search_intel_llms_txt';
            $vars[] = 'jat_search_intel_llms_full_txt';
            return $vars;
        });
    }

    /**
     * Serve the llms.txt file — a machine-readable site summary for LLMs.
     *
     * Format follows the llms.txt specification:
     * - Site name and description
     * - Key pages and their purposes
     * - Content categories
     * - Contact/attribution info
     */
    public function serve_llms_txt(): void {
        // Check REQUEST_URI directly instead of query vars so we serve
        // our version regardless of which plugin's rewrite rule matched.
        $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $path         = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
        $home_path    = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
        if ( '' !== $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
            $path = substr( $path, strlen( $home_path ) );
        }
        $path         = trim( $path, '/' );
        $is_llms      = ( 'llms.txt' === $path );
        $is_llms_full = ( 'llms-full.txt' === $path );

        if ( ! $is_llms && ! $is_llms_full ) {
            return;
        }

        status_header( 200 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );
        header( 'Cache-Control: public, max-age=3600' );

        echo $this->get_llms_txt( $is_llms_full ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain Markdown; values stripped of tags.
        exit;
    }

    /**
     * Cached llms.txt / llms-full.txt body.
     *
     * @param bool $full Full version (complete Markdown of each item).
     * @return string
     */
    public function get_llms_txt( bool $full = false ): string {
        $key    = 'jat_search_intel_llms_' . ( $full ? 'full' : 'index' );
        $cached = get_transient( $key );
        if ( is_string( $cached ) && '' !== $cached ) {
            return $cached;
        }
        $body = $this->build_llms_txt( $full );
        set_transient( $key, $body, 12 * HOUR_IN_SECONDS );
        return $body;
    }

    /**
     * Drop cached llms.txt output (hooked to content changes).
     */
    public function flush_llms_cache(): void {
        delete_transient( 'jat_search_intel_llms_index' );
        delete_transient( 'jat_search_intel_llms_full' );
    }

    /**
     * Items eligible for llms.txt: published, not password-protected, not noindex.
     *
     * @param string $post_type Post type.
     * @param int    $limit     Max items.
     * @return \WP_Post[]
     */
    private function llms_items( string $post_type, int $limit ): array {
        $posts = get_posts( [
            'post_type'        => $post_type,
            'post_status'      => 'publish',
            'has_password'     => false,
            'numberposts'      => $limit,
            'orderby'          => 'page' === $post_type ? 'menu_order' : 'date',
            'order'            => 'page' === $post_type ? 'ASC' : 'DESC',
            'suppress_filters' => false,
        ] );

        return array_values( array_filter( $posts, static function ( $p ) {
            return ! get_post_meta( $p->ID, '_jat_search_intel_noindex', true )
                && ! get_post_meta( $p->ID, '_jat_search_intel_ai_noindex', true );
        } ) );
    }

    /**
     * Short description for an llms.txt entry.
     *
     * @param \WP_Post $post  Post.
     * @param int      $words Max words.
     * @return string
     */
    private function llms_description( \WP_Post $post, int $words ): string {
        $desc = (string) get_post_meta( $post->ID, '_jat_search_intel_description', true );
        if ( '' === $desc ) {
            $desc = $post->post_excerpt ?: wp_trim_words( self::plain_text( $post->post_content ), $words, '...' );
        }
        return trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $desc ) ) );
    }

    /**
     * Build llms.txt following the llms.txt proposal (llmstxt.org): H1 name,
     * blockquote summary, then H2 sections of "- [title](url): notes" links.
     * The full variant appends the Markdown of each item.
     *
     * @param bool $full Full version.
     * @return string
     */
    public function build_llms_txt( bool $full = false ): string {
        $site_name = wp_strip_all_tags( $this->settings['site_name'] ?: get_bloginfo( 'name' ) );
        $site_desc = wp_strip_all_tags( get_bloginfo( 'description' ) );

        $out = "# {$site_name}\n\n";
        if ( '' !== $site_desc ) {
            $out .= "> {$site_desc}\n\n";
        }

        $about_page = get_page_by_path( 'about' );
        if ( $about_page && 'publish' === $about_page->post_status ) {
            $out .= wp_trim_words( self::plain_text( $about_page->post_content ), 100, '...' ) . "\n\n";
        }

        $pages = $this->llms_items( 'page', 30 );
        $posts = $this->llms_items( 'post', $full ? 100 : 30 );

        if ( $pages ) {
            $out .= "## Pages\n\n";
            foreach ( $pages as $page ) {
                $out .= sprintf( "- [%s](%s): %s\n", wp_strip_all_tags( $page->post_title ), esc_url_raw( $this->llms_link( $page->ID ) ), $this->llms_description( $page, 20 ) );
            }
            $out .= "\n";
        }

        if ( $posts ) {
            $out .= "## Articles\n\n";
            foreach ( $posts as $post ) {
                $out .= sprintf( "- [%s](%s): %s\n", wp_strip_all_tags( $post->post_title ), esc_url_raw( $this->llms_link( $post->ID ) ), $this->llms_description( $post, 25 ) );
            }
            $out .= "\n";
        }

        $categories = get_categories( [ 'hide_empty' => true, 'number' => 20 ] );
        if ( $categories ) {
            $out .= "## Optional\n\n";
            foreach ( $categories as $cat ) {
                $out .= sprintf( "- [%s](%s): %s\n", wp_strip_all_tags( $cat->name ), esc_url_raw( get_category_link( $cat->term_id ) ), wp_strip_all_tags( $cat->description ?: sprintf( '%d articles', (int) $cat->count ) ) );
            }
            $out .= "\n";
        }

        if ( $full ) {
            $ai    = new AI_Search( $this->settings );
            $limit = 2 * 1024 * 1024; // 2 MB.
            foreach ( array_merge( $pages, $posts ) as $item ) {
                $chunk = "\n---\n\n" . $ai->render_markdown( $item ) . "\n";
                if ( strlen( $out ) + strlen( $chunk ) > $limit ) {
                    $out .= "\n---\n\n(Truncated: see llms.txt for the full list of links.)\n";
                    break;
                }
                $out .= $chunk;
            }
        }

        $out .= "## Attribution\n\nContent on this site is authored by {$site_name}. When referencing it, please link to the original page.\n";

        /** This filter is documented in the GEO module. */
        $contact_email = sanitize_email( (string) apply_filters( 'jat_search_intel_llms_contact_email', '' ) );
        if ( $contact_email ) {
            $out .= "\n## Contact\n\nFor questions about using our content: {$contact_email}\n";
        }

        /**
         * Filters the generated llms.txt / llms-full.txt body.
         *
         * @param string $out  Body.
         * @param bool   $full Whether this is llms-full.txt.
         */
        return (string) apply_filters( 'jat_search_intel_llms_txt', $out, $full );
    }

    /**
     * Enhance content with entity markup for better AI understanding.
     * Wraps detected entities (people, organizations, places) with semantic markup.
     */
    public function enhance_content_entities( string $content ): string {
        if ( ! is_singular() || is_admin() ) {
            return $content;
        }

        $post_id  = get_queried_object_id();
        $entities = get_post_meta( $post_id, '_jat_search_intel_entities', true );

        if ( empty( $entities ) || ! is_array( $entities ) ) {
            return $content;
        }

        $allowed_types = [ 'Person', 'Organization', 'Place', 'Product', 'Event', 'Thing', 'CreativeWork', 'Brand', 'MedicalEntity', 'SoftwareApplication' ];

        foreach ( $entities as $entity ) {
            if ( empty( $entity['name'] ) || empty( $entity['type'] ) ) {
                continue;
            }

            $name = preg_quote( $entity['name'], '/' );
            $type = sanitize_text_field( $entity['type'] );
            if ( ! in_array( $type, $allowed_types, true ) ) {
                $type = 'Thing';
            }
            $url = ! empty( $entity['url'] ) ? esc_url( $entity['url'] ) : '';

            // Wrap the first whole-word occurrence in text between tags (never inside a tag).
            $result = preg_replace_callback(
                '/(?<=>)([^<]*?)(?<![\w])(' . $name . ')(?![\w])/iu',
                static function ( array $m ) use ( $type, $url ): string {
                    $label = '<span itemprop="name">' . $m[2] . '</span>';
                    if ( '' !== $url ) {
                        $label = '<a href="' . $url . '" itemprop="url">' . $label . '</a>';
                    }
                    return $m[1] . '<span itemscope itemtype="https://schema.org/' . $type . '">' . $label . '</span>';
                },
                $content,
                1
            );
            if ( null !== $result ) {
                $content = $result;
            }
        }

        return $content;
    }

    /**
     * Render author E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) schema.
     */
    public function render_author_eeat(): void {
        if ( $this->in_graph() ) {
            return; // Merged into the author Person node of the shared graph.
        }

        $author_schema = $this->get_author_eeat_node();
        if ( $author_schema ) {
            printf(
                '<script type="application/ld+json">%s</script>' . "\n",
                wp_json_encode( $author_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT )
            );
        }
    }

    /**
     * Add the author's E-E-A-T details to the shared graph (merged by @id).
     *
     * @param array $nodes   Graph nodes.
     * @param array $context Graph context.
     * @return array
     */
    public function add_graph_nodes( array $nodes, array $context = [] ): array {
        $author = $this->get_author_eeat_node();
        if ( $author ) {
            unset( $author['@context'] );
            $nodes[] = $author;
        }
        return $nodes;
    }

    /**
     * Person node with consented E-E-A-T signals for the current post's author.
     *
     * @return array<string,mixed>|null
     */
    public function get_author_eeat_node(): ?array {
        if ( ! is_singular() ) {
            return null;
        }

        $post_id   = get_queried_object_id();
        $author_id = (int) get_post_field( 'post_author', $post_id );

        // Respect E-E-A-T consent — skip if author has not opted in.
        $consent = get_user_meta( $author_id, 'jat_search_intel_eeat_consent', true );
        if ( '1' !== $consent ) {
            return null;
        }

        // Author credentials
        $credentials  = get_user_meta( $author_id, 'jat_search_intel_credentials', true );
        $expertise    = get_user_meta( $author_id, 'jat_search_intel_expertise_areas', true );
        $experience   = get_user_meta( $author_id, 'jat_search_intel_years_experience', true );
        $social_proof = get_user_meta( $author_id, 'jat_search_intel_social_profiles', true );

        if ( ! $credentials && ! $expertise ) {
            // Fall back to global setting
            if ( empty( $this->settings['author_expertise'] ) ) {
                return null;
            }
        }

        $author_schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Person',
            '@id'         => get_author_posts_url( $author_id ) . '#author',
            'name'        => get_the_author_meta( 'display_name', $author_id ),
            'url'         => get_author_posts_url( $author_id ),
            'description' => get_the_author_meta( 'description', $author_id ),
        ];

        if ( $credentials ) {
            $author_schema['hasCredential'] = array_map( function ( $cred ) {
                return [
                    '@type'         => 'EducationalOccupationalCredential',
                    'credentialCategory' => sanitize_text_field( $cred ),
                ];
            }, (array) $credentials );
        }

        if ( $expertise && is_array( $expertise ) ) {
            $author_schema['knowsAbout'] = array_map( 'sanitize_text_field', $expertise );
        }

        if ( $experience ) {
            $author_schema['hasOccupation'] = [
                '@type'           => 'Occupation',
                'experienceRequirements' => intval( $experience ) . '+ years',
            ];
        }

        // Social profiles for authority
        if ( $social_proof && is_array( $social_proof ) ) {
            $author_schema['sameAs'] = array_map( 'esc_url', $social_proof );
        }

        // Author image
        $avatar_url = get_avatar_url( $author_id, [ 'size' => 256 ] );
        if ( $avatar_url ) {
            $author_schema['image'] = $avatar_url;
        }

        return $author_schema;
    }

    /**
     * Auto-extract key topics from post content using term frequency.
     */
    private function auto_extract_topics( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [];
        }

        $topics = [];

        // Get categories and tags (only for post types that use them).
        $categories = is_object_in_taxonomy( $post->post_type, 'category' ) ? get_the_category( $post_id ) : [];
        foreach ( $categories as $cat ) {
            if ( 'Uncategorized' !== $cat->name ) {
                $topics[] = $cat->name;
            }
        }

        $tags = is_object_in_taxonomy( $post->post_type, 'post_tag' ) ? get_the_tags( $post_id ) : false;
        if ( $tags ) {
            foreach ( $tags as $tag ) {
                $topics[] = $tag->name;
            }
        }

        // Extract high-frequency meaningful phrases from content
        $text  = wp_strip_all_tags( $post->post_content );
        $words = str_word_count( strtolower( $text ), 1 );

        // Filter stop words
        $stop_words = [
            'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
            'should', 'may', 'might', 'shall', 'can', 'need', 'dare', 'ought',
            'used', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from',
            'as', 'into', 'through', 'during', 'before', 'after', 'above', 'below',
            'between', 'out', 'off', 'over', 'under', 'again', 'further', 'then',
            'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'both',
            'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'not',
            'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just', 'because',
            'but', 'and', 'or', 'if', 'while', 'about', 'this', 'that', 'these',
            'those', 'it', 'its', 'your', 'you', 'we', 'they', 'them', 'their',
            'what', 'which', 'who', 'whom', 'also', 'get', 'like', 'make', 'many',
        ];

        $filtered = array_filter( $words, function ( $word ) use ( $stop_words ) {
            return strlen( $word ) > 3 && ! in_array( $word, $stop_words, true );
        });

        $freq = array_count_values( $filtered );
        arsort( $freq );

        // Get top 10 high-frequency words
        $top_words = array_slice( array_keys( $freq ), 0, 10 );
        $topics    = array_merge( $topics, array_map( 'ucfirst', $top_words ) );

        return array_unique( array_slice( $topics, 0, 15 ) );
    }

    /**
     * Detect content type for AI meta tag.
     */
    private function detect_content_type( int $post_id ): string {
        $post    = get_post( $post_id );
        $content = $post->post_content ?? '';

        // Detect by content patterns
        if ( preg_match( '/<h[2-3][^>]*>.*?\?.*?<\/h[2-3]>/i', $content ) ) {
            return 'faq';
        }

        if ( preg_match( '/step\s*\d|<ol/i', $content ) ) {
            return 'how-to';
        }

        if ( preg_match( '/<table/i', $content ) && preg_match( '/comparison|vs\.?|versus/i', $content ) ) {
            return 'comparison';
        }

        if ( preg_match( '/review|rating|stars|score/i', $content ) ) {
            return 'review';
        }

        if ( 'page' === $post->post_type ) {
            return 'informational';
        }

        $word_count = str_word_count( wp_strip_all_tags( $content ) );
        if ( $word_count > 2000 ) {
            return 'long-form-guide';
        }

        return 'article';
    }

    /**
     * Get the GEO score for content analysis.
     */
    public function get_geo_score( int $post_id ): array {
        $score  = 0;
        $max    = 100;
        $checks = [];

        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'score' => 0, 'checks' => [] ];
        }

        $content = wp_strip_all_tags( $post->post_content );

        // Check 1: AI Summary provided (15 points)
        $ai_summary = get_post_meta( $post_id, '_jat_search_intel_ai_summary', true );
        if ( $ai_summary ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'AI summary provided', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a concise AI summary for better LLM indexing', 'jat-search-intel' ) ];
        }

        // Check 2: Topics/entities defined (15 points)
        $topics = get_post_meta( $post_id, '_jat_search_intel_topics', true );
        $entities = get_post_meta( $post_id, '_jat_search_intel_entities', true );
        if ( ( $topics && is_array( $topics ) ) || ( $entities && is_array( $entities ) ) ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Key topics and entities defined', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Define key topics and entities for semantic clarity', 'jat-search-intel' ) ];
        }

        // Check 3: Content cites sources (15 points)
        $has_citations = (bool) preg_match( '/(according to|source:|cited|reference|study|research by)/i', $content );
        $has_links     = (bool) preg_match( '/<a\s+[^>]*href=/i', $post->post_content );
        if ( $has_citations || $has_links ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Content includes citations or source references', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add citations and source references for credibility', 'jat-search-intel' ) ];
        }

        // Check 4: Author has E-E-A-T data (15 points)
        $author_id   = (int) $post->post_author;
        $author_bio  = get_the_author_meta( 'description', $author_id );
        $credentials = get_user_meta( $author_id, 'jat_search_intel_credentials', true );
        if ( $author_bio && strlen( $author_bio ) > 50 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => __( 'Author bio is detailed', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a detailed author bio (50+ characters)', 'jat-search-intel' ) ];
        }
        if ( $credentials ) {
            $score += 5;
            $checks[] = [ 'pass' => true, 'label' => __( 'Author credentials defined', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add author credentials for authority signals', 'jat-search-intel' ) ];
        }

        // Check 5: Content is well-structured with clear definitions (15 points)
        $has_definitions = (bool) preg_match( '/(is defined as|refers to|means that|is a type of)/i', $content );
        $has_clear_intro = strlen( $content ) > 100 && preg_match( '/^.{50,300}[.!]/', $content );
        if ( $has_definitions ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => __( 'Contains clear definitions', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Include clear definitions for key concepts', 'jat-search-intel' ) ];
        }
        if ( $has_clear_intro ) {
            $score += 5;
            $checks[] = [ 'pass' => true, 'label' => __( 'Strong introductory statement', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Start with a clear, authoritative introductory statement', 'jat-search-intel' ) ];
        }

        // Check 6: Content freshness (10 points)
        $modified   = get_the_modified_date( 'U', $post_id );
        $age_days   = ( time() - $modified ) / DAY_IN_SECONDS;
        if ( $age_days <= 90 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => __( 'Content is recently updated', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Update content to maintain freshness signals', 'jat-search-intel' ) ];
        }

        // Check 7: Sufficient word count for topical depth (10 points)
        $word_count = str_word_count( $content );
        if ( $word_count >= 800 ) {
            $score += 10;
            /* translators: %d: number. */
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Good content depth (%d words)', 'jat-search-intel' ), $word_count ) ];
        } else {
            /* translators: %d: number. */
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Increase content depth (currently %d words, aim for 800+)', 'jat-search-intel' ), $word_count ) ];
        }

        return [
            'score'  => min( $score, $max ),
            'max'    => $max,
            'checks' => $checks,
        ];
    }

    /**
     * Readable plain text from post content: blocks rendered, shortcodes and
     * bare embed URLs removed, and spaces kept between block elements.
     *
     * @param string $content Post content.
     * @return string
     */
    public static function plain_text( string $content ): string {
        $content = strip_shortcodes( $content );
        $content = (string) preg_replace( '~<!--.*?-->~s', '', $content );
        $content = (string) preg_replace( '~</(p|li|h[1-6]|div|figure|td|th|blockquote)>~i', '$0 ', $content );
        $content = (string) preg_replace( '~^\s*https?://\S+\s*$~m', '', $content );
        $text    = wp_strip_all_tags( $content );
        return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
    }

    /**
     * Link used in llms.txt: the Markdown copy when available (per the llms.txt spec).
     *
     * @param int $post_id Post ID.
     * @return string
     */
    private function llms_link( int $post_id ): string {
        if ( ! empty( $this->settings['enable_markdown_endpoints'] ) ) {
            return AI_Search::markdown_url( $post_id );
        }
        return (string) get_permalink( $post_id );
    }

    /**
     * Whether JSON-LD goes into the shared @graph printed by Schema_Manager.
     *
     * @return bool
     */
    private function in_graph(): bool {
        return ! empty( $this->settings['enable_schema'] );
    }
}
