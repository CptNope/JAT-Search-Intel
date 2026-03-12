<?php
namespace Search_Intel;

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
        add_action( 'wp_head', [ $this, 'render_speakable' ], 12 );

        // Register FAQ / HowTo shortcodes for easy content creation
        add_shortcode( 'search_intel_faq', [ $this, 'faq_shortcode' ] );
        add_shortcode( 'search_intel_howto', [ $this, 'howto_shortcode' ] );
    }

    /**
     * Render AEO-specific schema (FAQ, HowTo) from post meta.
     */
    public function render_aeo_schema(): void {
        if ( ! is_singular() ) {
            return;
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

        foreach ( $schemas as $schema ) {
            $output = array_merge( [ '@context' => 'https://schema.org' ], $schema );
            printf(
                '<script type="application/ld+json">%s</script>' . "\n",
                wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
            );
        }
    }

    /**
     * Build FAQPage schema from post meta.
     *
     * Meta format: array of [ 'question' => '...', 'answer' => '...' ]
     */
    public function get_faq_schema( int $post_id ): ?array {
        $faqs = get_post_meta( $post_id, '_search_intel_faqs', true );

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
        $howto = get_post_meta( $post_id, '_search_intel_howto', true );

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
                'name'  => ! empty( $step['name'] ) ? sanitize_text_field( $step['name'] ) : sprintf( __( 'Step %d', 'search-intel' ), $i + 1 ),
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

        $content = $post->post_content;
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
        if ( empty( $this->settings['enable_speakable'] ) || ! is_singular() ) {
            return;
        }

        $post_id   = get_queried_object_id();
        $selectors = $this->settings['speakable_selectors'];

        if ( empty( $selectors ) || ! is_array( $selectors ) ) {
            $selectors = [ '.entry-title', '.entry-content p:first-of-type' ];
        }

        // Custom per-post selectors
        $custom = get_post_meta( $post_id, '_search_intel_speakable_selectors', true );
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

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
        );
    }

    /**
     * [search_intel_faq] shortcode — renders an accessible FAQ block with schema.
     *
     * Usage: [search_intel_faq question="What is SEO?" answer="SEO stands for..."]
     * Or nested: [search_intel_faq]Q: What is SEO?\nA: SEO stands for...[/search_intel_faq]
     */
    public function faq_shortcode( $atts, $content = null ): string {
        $atts = shortcode_atts( [
            'question' => '',
            'answer'   => '',
        ], $atts, 'search_intel_faq' );

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
     * [search_intel_howto] shortcode — renders a HowTo block.
     *
     * Usage: [search_intel_howto name="How to do X" time="PT30M"]
     *          Step content parsed from nested shortcodes or structured content.
     *        [/search_intel_howto]
     */
    public function howto_shortcode( $atts, $content = null ): string {
        $atts = shortcode_atts( [
            'name' => '',
            'time' => '',
        ], $atts, 'search_intel_howto' );

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
        $faqs = get_post_meta( $post_id, '_search_intel_faqs', true );
        $auto_faqs = $this->auto_detect_faqs( $post_id );
        $has_faqs = ( ! empty( $faqs ) && is_array( $faqs ) ) || ! empty( $auto_faqs );
        if ( $has_faqs ) {
            $score += 20;
            $checks[] = [ 'pass' => true, 'label' => __( 'FAQ content detected', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add FAQ content with questions and answers', 'search-intel' ) ];
        }

        // Check 2: Direct answer in first paragraph (20 points)
        if ( preg_match( '/<p[^>]*>(.{50,300})<\/p>/is', $content, $match ) ) {
            $first_para = wp_strip_all_tags( $match[1] );
            // Check if it reads like a direct answer (starts with a statement, not a question)
            if ( ! str_ends_with( trim( $first_para ), '?' ) && strlen( $first_para ) >= 50 ) {
                $score += 20;
                $checks[] = [ 'pass' => true, 'label' => __( 'Direct answer in first paragraph', 'search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Provide a direct, concise answer in the first paragraph', 'search-intel' ) ];
            }
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'First paragraph should contain a direct answer (50-300 chars)', 'search-intel' ) ];
        }

        // Check 3: Uses heading hierarchy for structure (15 points)
        $has_h2 = (bool) preg_match( '/<h2/i', $content );
        $has_h3 = (bool) preg_match( '/<h3/i', $content );
        if ( $has_h2 && $has_h3 ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Good heading hierarchy (H2 + H3)', 'search-intel' ) ];
        } elseif ( $has_h2 ) {
            $score += 8;
            $checks[] = [ 'pass' => false, 'label' => __( 'Add H3 subheadings for better structure', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Use H2 and H3 headings to structure content', 'search-intel' ) ];
        }

        // Check 4: Contains a list (ordered or unordered) for snippet optimization (15 points)
        $has_list = (bool) preg_match( '/<[ou]l/i', $content );
        if ( $has_list ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Contains structured lists', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add numbered or bulleted lists for snippet eligibility', 'search-intel' ) ];
        }

        // Check 5: Concise meta description set (15 points)
        $desc = get_post_meta( $post_id, '_search_intel_description', true );
        if ( $desc && strlen( $desc ) >= 50 && strlen( $desc ) <= 160 ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Meta description is optimal length', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Set a meta description (50-160 characters)', 'search-intel' ) ];
        }

        // Check 6: Content includes a table for data snippets (15 points)
        $has_table = (bool) preg_match( '/<table/i', $content );
        if ( $has_table ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Contains data tables', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Consider adding tables for data-rich snippets', 'search-intel' ) ];
        }

        return [
            'score'  => min( $score, $max ),
            'max'    => $max,
            'checks' => $checks,
        ];
    }
}
