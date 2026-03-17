<?php
namespace Search_Intel;

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
            add_action( 'init', [ $this, 'register_llms_txt_endpoint' ] );
            add_action( 'template_redirect', [ $this, 'serve_llms_txt' ] );
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
    }

    /**
     * Render AI-specific meta tags for better indexing by LLMs and AI search engines.
     */
    public function render_ai_meta(): void {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_queried_object_id();
        $post    = get_post( $post_id );

        // AI-readable summary (concise version of the content for LLM consumption)
        $ai_summary = get_post_meta( $post_id, '_search_intel_ai_summary', true );
        if ( ! $ai_summary ) {
            $ai_summary = wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '...' );
        }

        if ( $ai_summary ) {
            printf(
                '<meta name="ai-summary" content="%s" />' . "\n",
                esc_attr( $ai_summary )
            );
        }

        // Key topics / entities for the content
        $topics = get_post_meta( $post_id, '_search_intel_topics', true );
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
        $content_type = get_post_meta( $post_id, '_search_intel_content_type', true );
        if ( ! $content_type ) {
            $content_type = $this->detect_content_type( $post_id );
        }

        printf(
            '<meta name="content-type" content="%s" />' . "\n",
            esc_attr( $content_type )
        );

        // Expertise level
        $expertise = get_post_meta( $post_id, '_search_intel_expertise_level', true );
        if ( $expertise ) {
            printf(
                '<meta name="expertise-level" content="%s" />' . "\n",
                esc_attr( $expertise )
            );
        }

        // Date accuracy signal
        printf(
            '<meta name="article:modified_time" content="%s" />' . "\n",
            esc_attr( get_the_modified_date( 'c', $post_id ) )
        );

        // v1.1: Content intent meta
        $content_intent = get_post_meta( $post_id, '_search_intel_content_intent', true );
        if ( $content_intent ) {
            printf(
                '<meta name="content-intent" content="%s" />' . "\n",
                esc_attr( $content_intent )
            );
        }

        // v1.1: Target audience meta
        $audience = get_post_meta( $post_id, '_search_intel_audience', true );
        if ( $audience ) {
            printf(
                '<meta name="target-audience" content="%s" />' . "\n",
                esc_attr( $audience )
            );
        }

        // v1.1: Author expertise notes
        $expertise_notes = get_post_meta( $post_id, '_search_intel_author_expertise_notes', true );
        if ( $expertise_notes ) {
            printf(
                '<meta name="author-expertise" content="%s" />' . "\n",
                esc_attr( $expertise_notes )
            );
        }

        // v1.1: Local relevance signal
        $local_relevance = get_post_meta( $post_id, '_search_intel_local_relevance', true );
        $local_location  = get_post_meta( $post_id, '_search_intel_local_location', true );
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
        $fact_checked = get_post_meta( $post_id, '_search_intel_fact_checked', true );
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
        $ai_noindex = $post_id ? get_post_meta( $post_id, '_search_intel_ai_noindex', true ) : false;

        if ( $ai_noindex ) {
            // Block specific AI crawlers
            $ai_bots = [ 'GPTBot', 'ChatGPT-User', 'Google-Extended', 'CCBot', 'anthropic-ai', 'ClaudeBot' ];
            foreach ( $ai_bots as $bot ) {
                printf( '<meta name="%s" content="noindex, nofollow" />' . "\n", esc_attr( $bot ) );
            }
        }

        // Opt-in signals for AI crawlers (site-wide default)
        $ai_permissions = get_option( 'search_intel_ai_permissions', 'allow' );
        if ( 'restrict' === $ai_permissions ) {
            echo '<meta name="robots" content="noai, noimageai" />' . "\n";
        }
    }

    /**
     * Register the llms.txt rewrite endpoint.
     */
    public function register_llms_txt_endpoint(): void {
        add_rewrite_rule( '^llms\.txt$', 'index.php?search_intel_llms_txt=1', 'top' );
        add_rewrite_rule( '^llms-full\.txt$', 'index.php?search_intel_llms_full_txt=1', 'top' );
        add_filter( 'query_vars', function ( $vars ) {
            $vars[] = 'search_intel_llms_txt';
            $vars[] = 'search_intel_llms_full_txt';
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
        $is_llms      = get_query_var( 'search_intel_llms_txt' );
        $is_llms_full = get_query_var( 'search_intel_llms_full_txt' );

        if ( ! $is_llms && ! $is_llms_full ) {
            return;
        }

        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );
        header( 'Cache-Control: public, max-age=3600' );

        $site_name = wp_strip_all_tags( $this->settings['site_name'] ?: get_bloginfo( 'name' ) );
        $site_desc = wp_strip_all_tags( get_bloginfo( 'description' ) );

        // Header
        echo "# {$site_name}\n\n";
        echo "> {$site_desc}\n\n";

        // About section
        $about_page = get_page_by_path( 'about' );
        if ( $about_page ) {
            echo "## About\n\n";
            echo wp_trim_words( wp_strip_all_tags( $about_page->post_content ), 100, '...' ) . "\n\n";
        }

        // Key pages
        echo "## Key Pages\n\n";
        $pages = get_pages( [
            'sort_order'  => 'ASC',
            'sort_column' => 'menu_order',
            'number'      => 20,
            'post_status' => 'publish',
        ] );

        foreach ( $pages as $page ) {
            $desc = get_post_meta( $page->ID, '_search_intel_description', true );
            if ( ! $desc ) {
                $desc = wp_trim_words( wp_strip_all_tags( $page->post_content ), 20, '...' );
            }
            printf( "- [%s](%s): %s\n", wp_strip_all_tags( $page->post_title ), esc_url( get_permalink( $page->ID ) ), wp_strip_all_tags( $desc ) );
        }

        echo "\n";

        // Content categories
        $categories = get_categories( [ 'hide_empty' => true, 'number' => 20 ] );
        if ( $categories ) {
            echo "## Topics\n\n";
            foreach ( $categories as $cat ) {
                printf( "- [%s](%s): %s (%d articles)\n",
                    wp_strip_all_tags( $cat->name ),
                    esc_url( get_category_link( $cat->term_id ) ),
                    wp_strip_all_tags( $cat->description ?: 'Topic category' ),
                    $cat->count
                );
            }
            echo "\n";
        }

        // For full version, include recent posts with summaries
        if ( $is_llms_full ) {
            echo "## Recent Content\n\n";
            $recent = get_posts( [
                'numberposts' => 50,
                'post_status' => 'publish',
                'post_type'   => 'post',
            ] );

            foreach ( $recent as $rpost ) {
                $desc = get_post_meta( $rpost->ID, '_search_intel_description', true );
                if ( ! $desc ) {
                    $desc = wp_trim_words( wp_strip_all_tags( $rpost->post_content ), 30, '...' );
                }
                printf(
                    "### [%s](%s)\n%s\nPublished: %s | Modified: %s\n\n",
                    wp_strip_all_tags( $rpost->post_title ),
                    esc_url( get_permalink( $rpost->ID ) ),
                    wp_strip_all_tags( $desc ),
                    get_the_date( 'Y-m-d', $rpost->ID ),
                    get_the_modified_date( 'Y-m-d', $rpost->ID )
                );
            }
        }

        // Attribution
        echo "## Attribution\n\n";
        echo "Content on this site is authored by {$site_name}.\n";
        echo "When referencing content from this site, please include a link to the original source.\n";

        // Contact
        $admin_email = sanitize_email( get_option( 'admin_email' ) );
        if ( $admin_email ) {
            echo "\n## Contact\n\n";
            echo "For questions about using our content: {$admin_email}\n";
        }

        exit;
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
        $entities = get_post_meta( $post_id, '_search_intel_entities', true );

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
            $url  = ! empty( $entity['url'] ) ? esc_url( $entity['url'] ) : '';

            // Only wrap first occurrence to avoid over-marking
            $replacement = sprintf(
                '<span itemscope itemtype="https://schema.org/%s"><span itemprop="name">%s</span></span>',
                $type,
                '$0'
            );

            if ( $url ) {
                $replacement = sprintf(
                    '<span itemscope itemtype="https://schema.org/%s"><a href="%s" itemprop="url"><span itemprop="name">%s</span></a></span>',
                    $type,
                    $url,
                    '$0'
                );
            }

            // Replace first occurrence only, avoiding existing HTML tags
            $content = preg_replace(
                '/(?<=>)([^<]*?)(' . $name . ')([^<]*?)(?=<)/i',
                '$1' . $replacement . '$3',
                $content,
                1
            );
        }

        return $content;
    }

    /**
     * Render author E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) schema.
     */
    public function render_author_eeat(): void {
        if ( ! is_singular() ) {
            return;
        }

        $post_id   = get_queried_object_id();
        $author_id = get_post_field( 'post_author', $post_id );

        // Respect E-E-A-T consent — skip if author has not opted in.
        $consent = get_user_meta( $author_id, 'search_intel_eeat_consent', true );
        if ( '1' !== $consent ) {
            return;
        }

        // Author credentials
        $credentials  = get_user_meta( $author_id, 'search_intel_credentials', true );
        $expertise    = get_user_meta( $author_id, 'search_intel_expertise_areas', true );
        $experience   = get_user_meta( $author_id, 'search_intel_years_experience', true );
        $social_proof = get_user_meta( $author_id, 'search_intel_social_profiles', true );

        if ( ! $credentials && ! $expertise ) {
            // Fall back to global setting
            if ( empty( $this->settings['author_expertise'] ) ) {
                return;
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

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode( $author_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
        );
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

        // Get categories and tags
        $categories = get_the_category( $post_id );
        foreach ( $categories as $cat ) {
            if ( 'Uncategorized' !== $cat->name ) {
                $topics[] = $cat->name;
            }
        }

        $tags = get_the_tags( $post_id );
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
        $ai_summary = get_post_meta( $post_id, '_search_intel_ai_summary', true );
        if ( $ai_summary ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'AI summary provided', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a concise AI summary for better LLM indexing', 'search-intel' ) ];
        }

        // Check 2: Topics/entities defined (15 points)
        $topics = get_post_meta( $post_id, '_search_intel_topics', true );
        $entities = get_post_meta( $post_id, '_search_intel_entities', true );
        if ( ( $topics && is_array( $topics ) ) || ( $entities && is_array( $entities ) ) ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Key topics and entities defined', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Define key topics and entities for semantic clarity', 'search-intel' ) ];
        }

        // Check 3: Content cites sources (15 points)
        $has_citations = (bool) preg_match( '/(according to|source:|cited|reference|study|research by)/i', $content );
        $has_links     = (bool) preg_match( '/<a\s+[^>]*href=/i', $post->post_content );
        if ( $has_citations || $has_links ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Content includes citations or source references', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add citations and source references for credibility', 'search-intel' ) ];
        }

        // Check 4: Author has E-E-A-T data (15 points)
        $author_id   = (int) $post->post_author;
        $author_bio  = get_the_author_meta( 'description', $author_id );
        $credentials = get_user_meta( $author_id, 'search_intel_credentials', true );
        if ( $author_bio && strlen( $author_bio ) > 50 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => __( 'Author bio is detailed', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a detailed author bio (50+ characters)', 'search-intel' ) ];
        }
        if ( $credentials ) {
            $score += 5;
            $checks[] = [ 'pass' => true, 'label' => __( 'Author credentials defined', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add author credentials for authority signals', 'search-intel' ) ];
        }

        // Check 5: Content is well-structured with clear definitions (15 points)
        $has_definitions = (bool) preg_match( '/(is defined as|refers to|means that|is a type of)/i', $content );
        $has_clear_intro = strlen( $content ) > 100 && preg_match( '/^.{50,300}[.!]/', $content );
        if ( $has_definitions ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => __( 'Contains clear definitions', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Include clear definitions for key concepts', 'search-intel' ) ];
        }
        if ( $has_clear_intro ) {
            $score += 5;
            $checks[] = [ 'pass' => true, 'label' => __( 'Strong introductory statement', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Start with a clear, authoritative introductory statement', 'search-intel' ) ];
        }

        // Check 6: Content freshness (10 points)
        $modified   = get_the_modified_date( 'U', $post_id );
        $age_days   = ( time() - $modified ) / DAY_IN_SECONDS;
        if ( $age_days <= 90 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => __( 'Content is recently updated', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Update content to maintain freshness signals', 'search-intel' ) ];
        }

        // Check 7: Sufficient word count for topical depth (10 points)
        $word_count = str_word_count( $content );
        if ( $word_count >= 800 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Good content depth (%d words)', 'search-intel' ), $word_count ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Increase content depth (currently %d words, aim for 800+)', 'search-intel' ), $word_count ) ];
        }

        return [
            'score'  => min( $score, $max ),
            'max'    => $max,
            'checks' => $checks,
        ];
    }
}
