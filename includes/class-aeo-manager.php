<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Answer Engine Optimization (AEO) Manager.
 *
 * Handles FAQ schema, HowTo schema, Q&A schema, Speakable markup,
 * and featured snippet optimization signals.
 */
class AEO_Manager {

    /** @var array */
    private $settings;

    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( empty( $settings['enable_aeo'] ) ) {
            return;
        }

        add_action( 'wp_head', [ $this, 'render_aeo_schema' ], 11 );
        add_filter( 'jat_search_intel_schema_graph', [ $this, 'add_graph_nodes' ], 10, 2 );
        add_action( 'wp_head', [ $this, 'render_speakable' ], 12 );
        add_action( 'wp_head', [ $this, 'render_direct_answer_meta' ], 13 );

        // Register FAQ / HowTo shortcodes for easy content creation
        add_shortcode( 'jat_search_intel_faq', [ $this, 'faq_shortcode' ] );
        add_shortcode( 'jat_search_intel_howto', [ $this, 'howto_shortcode' ] );

        // Legacy shortcode names from the GitHub edition, kept so existing content renders.
        add_shortcode( 'search_intel_faq', [ $this, 'faq_shortcode' ] );
        add_shortcode( 'search_intel_howto', [ $this, 'howto_shortcode' ] );

        // Auto-generate lead summary when no excerpt exists
        add_filter( 'get_the_excerpt', [ $this, 'auto_lead_summary' ], 10, 2 );
    }

    /**
     * Render direct-answer meta tag for featured snippet targeting.
     */
    public function render_direct_answer_meta(): void {
        if ( is_singular() && Content_Guard::is_protected( get_queried_object_id() ) ) {
            return;
        }
        if ( ! is_singular() ) {
            return;
        }

        $post_id       = get_queried_object_id();
        $direct_answer = get_post_meta( $post_id, '_jat_search_intel_direct_answer', true );

        if ( $direct_answer ) {
            printf(
                '<meta name="direct-answer" content="%s" />' . "\n",
                esc_attr( $direct_answer )
            );
        }
    }

    /**
     * Auto-generate a lead summary from the first 2-3 sentences when no excerpt exists.
     */
    public function auto_lead_summary( string $excerpt, $post ): string {
        if ( $excerpt || ! $post ) {
            return $excerpt;
        }

        $content = $post->post_content;
        if ( empty( $content ) ) {
            return $excerpt;
        }

        $text = wp_strip_all_tags( $content );
        // Split into sentences and take first 3
        $sentences = preg_split( '/(?<=[.!?])\s+/', $text, 4 );
        if ( count( $sentences ) > 3 ) {
            $sentences = array_slice( $sentences, 0, 3 );
        }

        return implode( ' ', $sentences );
    }

    /**
     * Detect Q&A-style patterns beyond headings (inline question-answer pairs).
     */
    public function detect_qa_patterns( string $content ): array {
        $patterns = [];

        // Pattern 1: Bold question followed by answer text
        // e.g., <strong>What is SEO?</strong> SEO is...
        if ( preg_match_all( '/<strong>(.*?\?)<\/strong>\s*(.*?)(?=<strong>|<h[2-6]|<\/div|$)/is', $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $m ) {
                $answer = wp_strip_all_tags( trim( $m[2] ) );
                if ( strlen( $answer ) > 20 ) {
                    $patterns[] = [
                        'question' => wp_strip_all_tags( $m[1] ),
                        'answer'   => $answer,
                        'type'     => 'bold_qa',
                    ];
                }
            }
        }

        // Pattern 2: "Q:" / "A:" pattern
        if ( preg_match_all( '/(?:Q|Question):\s*(.*?\?)\s*(?:A|Answer):\s*(.*?)(?=(?:Q|Question):|<h[2-6]|$)/is', wp_strip_all_tags( $content ), $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $m ) {
                if ( strlen( trim( $m[2] ) ) > 20 ) {
                    $patterns[] = [
                        'question' => trim( $m[1] ),
                        'answer'   => trim( $m[2] ),
                        'type'     => 'qa_prefix',
                    ];
                }
            }
        }

        return $patterns;
    }

    /**
     * Calculate answer snippet recommendation score.
     * Checks for 40-word blocks, lists, and definitions suitable for featured snippets.
     */
    public function get_snippet_score( string $content ): array {
        $score  = 0;
        $max    = 100;
        $checks = [];
        $text   = wp_strip_all_tags( $content );

        // 1. Concise answer blocks (20-50 words) — 25 pts
        $has_concise_block = false;
        if ( preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $paras ) ) {
            foreach ( $paras[1] as $p ) {
                $wc = str_word_count( wp_strip_all_tags( $p ) );
                if ( $wc >= 20 && $wc <= 50 ) {
                    $has_concise_block = true;
                    break;
                }
            }
        }
        if ( $has_concise_block ) {
            $score += 25;
            $checks[] = [ 'pass' => true, 'label' => __( 'Contains concise answer block (20-50 words)', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a 20-50 word paragraph as a direct answer block', 'jat-search-intel' ) ];
        }

        // 2. Ordered/unordered lists — 25 pts
        if ( preg_match( '/<[ou]l/i', $content ) ) {
            $score += 25;
            $checks[] = [ 'pass' => true, 'label' => __( 'Contains structured lists for list snippets', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add lists (steps, items, features) for list snippet eligibility', 'jat-search-intel' ) ];
        }

        // 3. Definition patterns — 25 pts
        if ( preg_match( '/(is defined as|refers to|is a type of|is the process of|means that)/i', $text ) ) {
            $score += 25;
            $checks[] = [ 'pass' => true, 'label' => __( 'Contains definition patterns for definition snippets', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Include "X is defined as..." or "X refers to..." patterns', 'jat-search-intel' ) ];
        }

        // 4. Table data — 25 pts
        if ( preg_match( '/<table/i', $content ) ) {
            $score += 25;
            $checks[] = [ 'pass' => true, 'label' => __( 'Contains tables for table snippet eligibility', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add data tables for table snippet opportunities', 'jat-search-intel' ) ];
        }

        return [
            'score'  => min( $score, $max ),
            'max'    => $max,
            'checks' => $checks,
        ];
    }

    /**
     * Render AEO-specific schema (FAQ, HowTo) from post meta.
     */
    public function render_aeo_schema(): void {
        if ( $this->in_graph() ) {
            return; // Added to the shared graph via add_graph_nodes().
        }

        foreach ( $this->get_aeo_nodes() as $schema ) {
            $output = array_merge( [ '@context' => 'https://schema.org' ], $schema );
            printf(
                '<script type="application/ld+json">%s</script>' . "\n",
                wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT )
            );
        }
    }

    /**
     * FAQPage / HowTo nodes for the current singular request.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_aeo_nodes(): array {
        if ( ! is_singular() ) {
            return [];
        }

        $post_id = get_queried_object_id();
        $schemas = [];

        // FAQ Schema
        if ( ! empty( $this->settings['enable_faq_schema'] ) ) {
            $faq = $this->get_faq_schema( $post_id );
            if ( $faq ) {
                $schemas[] = $faq;
            }
        }

        // HowTo Schema
        if ( ! empty( $this->settings['enable_howto_schema'] ) ) {
            $howto = $this->get_howto_schema( $post_id );
            if ( $howto ) {
                $schemas[] = $howto;
            }
        }

        return $schemas;
    }

    /**
     * Add FAQ, HowTo and Speakable data to the shared JSON-LD graph.
     *
     * @param array $nodes   Graph nodes.
     * @param array $context Graph context (page_id, ...).
     * @return array
     */
    public function add_graph_nodes( array $nodes, array $context = [] ): array {
        $page_id = $context['page_id'] ?? '';

        foreach ( $this->get_aeo_nodes() as $node ) {
            unset( $node['@context'] );
            $type        = is_array( $node['@type'] ) ? reset( $node['@type'] ) : $node['@type'];
            $node['@id'] = $page_id . '#' . strtolower( (string) $type );
            if ( $page_id ) {
                $node['isPartOf'] = [ '@id' => $page_id ];
            }
            $nodes[] = $node;
        }

        $speakable = $this->get_speakable_node();
        if ( $speakable && $page_id ) {
            // Same @id as the WebPage node, so it is merged into it.
            $nodes[] = [
                '@type'     => 'WebPage',
                '@id'       => $page_id,
                'speakable' => $speakable['speakable'],
            ];
        }

        return $nodes;
    }

    /**
     * Build FAQPage schema from post meta.
     *
     * Meta format: array of [ 'question' => '...', 'answer' => '...' ]
     */
    public function get_faq_schema( int $post_id ): ?array {
        if ( Content_Guard::is_protected( $post_id ) ) {
            return null;
        }
        $faqs = get_post_meta( $post_id, '_jat_search_intel_faqs', true );

        if ( empty( $faqs ) || ! is_array( $faqs ) ) {
            // Auto-detect FAQ from content headings
            $faqs = $this->auto_detect_faqs( $post_id );
        }

        if ( empty( $faqs ) ) {
            return null;
        }

        $entities = [];
        foreach ( $faqs as $faq ) {
            if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
                continue;
            }
            $entities[] = [
                '@type'          => 'Question',
                'name'           => sanitize_text_field( $faq['question'] ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_kses_post( $faq['answer'] ),
                ],
            ];
        }

        if ( empty( $entities ) ) {
            return null;
        }

        return [
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * Build HowTo schema from post meta.
     *
     * Meta format:
     * [
     *   'name'        => '...',
     *   'description' => '...',
     *   'totalTime'   => 'PT30M',
     *   'supply'      => [ 'item1', 'item2' ],
     *   'tool'        => [ 'tool1', 'tool2' ],
     *   'steps'       => [
     *       [ 'name' => '...', 'text' => '...', 'image' => '...' ],
     *   ],
     * ]
     */
    public function get_howto_schema( int $post_id ): ?array {
        if ( Content_Guard::is_protected( $post_id ) ) {
            return null;
        }
        $howto = get_post_meta( $post_id, '_jat_search_intel_howto', true );

        if ( empty( $howto ) || empty( $howto['steps'] ) ) {
            return null;
        }

        $schema = [
            '@type' => 'HowTo',
            'name'  => ! empty( $howto['name'] ) ? sanitize_text_field( $howto['name'] ) : get_the_title( $post_id ),
        ];

        if ( ! empty( $howto['description'] ) ) {
            $schema['description'] = sanitize_text_field( $howto['description'] );
        }

        if ( ! empty( $howto['totalTime'] ) ) {
            $schema['totalTime'] = sanitize_text_field( $howto['totalTime'] );
        }

        // Supplies
        if ( ! empty( $howto['supply'] ) && is_array( $howto['supply'] ) ) {
            $schema['supply'] = array_map( function ( $item ) {
                return [
                    '@type' => 'HowToSupply',
                    'name'  => sanitize_text_field( $item ),
                ];
            }, $howto['supply'] );
        }

        // Tools
        if ( ! empty( $howto['tool'] ) && is_array( $howto['tool'] ) ) {
            $schema['tool'] = array_map( function ( $item ) {
                return [
                    '@type' => 'HowToTool',
                    'name'  => sanitize_text_field( $item ),
                ];
            }, $howto['tool'] );
        }

        // Steps
        $steps = [];
        foreach ( $howto['steps'] as $i => $step ) {
            if ( empty( $step['text'] ) ) {
                continue;
            }
            $step_schema = [
                '@type' => 'HowToStep',
                /* translators: %d: number. */
                'name'  => ! empty( $step['name'] ) ? sanitize_text_field( $step['name'] ) : sprintf( __( 'Step %d', 'jat-search-intel' ), $i + 1 ),
                'text'  => wp_kses_post( $step['text'] ),
                'url'   => get_permalink( $post_id ) . '#step-' . ( $i + 1 ),
            ];

            if ( ! empty( $step['image'] ) ) {
                $step_schema['image'] = esc_url( $step['image'] );
            }

            $steps[] = $step_schema;
        }

        if ( empty( $steps ) ) {
            return null;
        }

        $schema['step'] = $steps;

        return $schema;
    }

    /**
     * Auto-detect FAQ patterns from post content.
     * Looks for H2/H3 headings that end with "?" followed by paragraph content.
     */
    private function auto_detect_faqs( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [];
        }

        $content = Content_Guard::content( $post );
        $faqs    = [];

        // Match headings ending with ? followed by content
        $pattern = '/<h[2-3][^>]*>\s*(.*?\?)\s*<\/h[2-3]>\s*(?:<p[^>]*>(.*?)<\/p>)/is';

        if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $question = wp_strip_all_tags( $match[1] );
                $answer   = wp_strip_all_tags( $match[2] );
                if ( $question && $answer ) {
                    $faqs[] = [
                        'question' => $question,
                        'answer'   => $answer,
                    ];
                }
            }
        }

        return $faqs;
    }

    /**
     * Render Speakable schema for voice assistant optimization.
     */
    public function render_speakable(): void {
        if ( $this->in_graph() ) {
            return; // Merged into the WebPage node of the shared graph.
        }

        $schema = $this->get_speakable_node();
        if ( ! $schema ) {
            return;
        }

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT )
        );
    }

    /**
     * Speakable WebPage node for the current singular request.
     *
     * @return array<string,mixed>|null
     */
    public function get_speakable_node(): ?array {
        if ( is_singular() && Content_Guard::is_protected( get_queried_object_id() ) ) {
            return null;
        }
        if ( empty( $this->settings['enable_speakable'] ) || ! is_singular() ) {
            return null;
        }

        $post_id   = get_queried_object_id();
        $selectors = $this->settings['speakable_selectors'];

        if ( empty( $selectors ) || ! is_array( $selectors ) ) {
            $selectors = [ '.entry-title', '.entry-content p:first-of-type' ];
        }

        // Custom per-post selectors
        $custom = get_post_meta( $post_id, '_jat_search_intel_speakable_selectors', true );
        if ( ! empty( $custom ) && is_array( $custom ) ) {
            $selectors = $custom;
        }

        $schema = [
            '@context'  => 'https://schema.org',
            '@type'     => 'WebPage',
            'name'      => get_the_title( $post_id ),
            'url'       => get_permalink( $post_id ),
            'speakable' => [
                '@type'     => 'SpeakableSpecification',
                'cssSelector' => $selectors,
            ],
        ];

        return $schema;
    }

    /**
     * [jat_search_intel_faq] shortcode — renders an accessible FAQ block with schema.
     *
     * Usage: [jat_search_intel_faq question="What is SEO?" answer="SEO stands for..."]
     * Or nested: [jat_search_intel_faq]Q: What is SEO?\nA: SEO stands for...[/jat_search_intel_faq]
     */
    public function faq_shortcode( $atts, $content = null ): string {
        // Deprecation notice when shortcode is used in block editor content.
        if ( function_exists( 'has_blocks' ) && has_blocks( get_the_ID() ) ) {
            _deprecated_function(
                '[jat_search_intel_faq] shortcode',
                '1.4.0',
                'the jat-search-intel/faq Gutenberg block'
            );
        }

        $atts = shortcode_atts( [
            'question' => '',
            'answer'   => '',
        ], $atts, 'jat_search_intel_faq' );

        $question = $atts['question'];
        $answer   = $atts['answer'];

        // Parse from content if not in attributes
        if ( empty( $question ) && $content ) {
            if ( preg_match( '/Q:\s*(.+?)(?:\n|<br)/', $content, $q_match ) ) {
                $question = trim( $q_match[1] );
            }
            if ( preg_match( '/A:\s*(.+)/s', $content, $a_match ) ) {
                $answer = trim( $a_match[1] );
            }
        }

        if ( empty( $question ) || empty( $answer ) ) {
            return '';
        }

        $html  = '<div class="si-faq-item" itemscope itemtype="https://schema.org/Question">';
        $html .= '<h3 class="si-faq-question" itemprop="name">' . esc_html( $question ) . '</h3>';
        $html .= '<div class="si-faq-answer" itemscope itemtype="https://schema.org/Answer" itemprop="acceptedAnswer">';
        $html .= '<div itemprop="text">' . wp_kses_post( $answer ) . '</div>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * [jat_search_intel_howto] shortcode — renders a HowTo block.
     *
     * Usage: [jat_search_intel_howto name="How to do X" time="PT30M"]
     *          Step content parsed from nested shortcodes or structured content.
     *        [/jat_search_intel_howto]
     */
    public function howto_shortcode( $atts, $content = null ): string {
        // Deprecation notice when shortcode is used in block editor content.
        if ( function_exists( 'has_blocks' ) && has_blocks( get_the_ID() ) ) {
            _deprecated_function(
                '[jat_search_intel_howto] shortcode',
                '1.4.0',
                'the jat-search-intel/howto Gutenberg block'
            );
        }

        $atts = shortcode_atts( [
            'name' => '',
            'time' => '',
        ], $atts, 'jat_search_intel_howto' );

        $html  = '<div class="si-howto" itemscope itemtype="https://schema.org/HowTo">';

        if ( $atts['name'] ) {
            $html .= '<h2 class="si-howto-title" itemprop="name">' . esc_html( $atts['name'] ) . '</h2>';
        }

        if ( $content ) {
            $html .= '<div class="si-howto-steps">' . do_shortcode( $content ) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get the AEO score for content analysis.
     */
    public function get_aeo_score( int $post_id ): array {
        $score   = 0;
        $max     = 100;
        $checks  = [];

        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'score' => 0, 'checks' => [] ];
        }

        $content = $post->post_content;

        // Check 1: Has FAQ content (20 points)
        $faqs = get_post_meta( $post_id, '_jat_search_intel_faqs', true );
        $auto_faqs = $this->auto_detect_faqs( $post_id );
        $has_faqs = ( ! empty( $faqs ) && is_array( $faqs ) ) || ! empty( $auto_faqs );
        if ( $has_faqs ) {
            $score += 20;
            $checks[] = [ 'pass' => true, 'label' => __( 'FAQ content detected', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add FAQ content with questions and answers', 'jat-search-intel' ) ];
        }

        // Check 2: Direct answer in first paragraph (20 points)
        if ( preg_match( '/<p[^>]*>(.{50,300})<\/p>/is', $content, $match ) ) {
            $first_para = wp_strip_all_tags( $match[1] );
            // Check if it reads like a direct answer (starts with a statement, not a question)
            if ( ! str_ends_with( trim( $first_para ), '?' ) && strlen( $first_para ) >= 50 ) {
                $score += 20;
                $checks[] = [ 'pass' => true, 'label' => __( 'Direct answer in first paragraph', 'jat-search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Provide a direct, concise answer in the first paragraph', 'jat-search-intel' ) ];
            }
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'First paragraph should contain a direct answer (50-300 chars)', 'jat-search-intel' ) ];
        }

        // Check 3: Uses heading hierarchy for structure (15 points)
        $has_h2 = (bool) preg_match( '/<h2/i', $content );
        $has_h3 = (bool) preg_match( '/<h3/i', $content );
        if ( $has_h2 && $has_h3 ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Good heading hierarchy (H2 + H3)', 'jat-search-intel' ) ];
        } elseif ( $has_h2 ) {
            $score += 8;
            $checks[] = [ 'pass' => false, 'label' => __( 'Add H3 subheadings for better structure', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Use H2 and H3 headings to structure content', 'jat-search-intel' ) ];
        }

        // Check 4: Contains a list (ordered or unordered) for snippet optimization (15 points)
        $has_list = (bool) preg_match( '/<[ou]l/i', $content );
        if ( $has_list ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Contains structured lists', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add numbered or bulleted lists for snippet eligibility', 'jat-search-intel' ) ];
        }

        // Check 5: Concise meta description set (15 points)
        $desc = get_post_meta( $post_id, '_jat_search_intel_description', true );
        if ( $desc && strlen( $desc ) >= 50 && strlen( $desc ) <= 160 ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Meta description is optimal length', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Set a meta description (50-160 characters)', 'jat-search-intel' ) ];
        }

        // Check 6: Content includes a table for data snippets (15 points)
        $has_table = (bool) preg_match( '/<table/i', $content );
        if ( $has_table ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Contains data tables', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Consider adding tables for data-rich snippets', 'jat-search-intel' ) ];
        }

        return [
            'score'  => min( $score, $max ),
            'max'    => $max,
            'checks' => $checks,
        ];
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
