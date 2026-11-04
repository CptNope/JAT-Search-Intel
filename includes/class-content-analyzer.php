<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Content Analyzer — provides real-time scoring for SEO, AEO, and GEO optimization.
 * Includes readability scoring (Flesch-Kincaid), keyword density analysis, and unified scoring.
 */
class Content_Analyzer {

    /** @var array */
    private $settings;

    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( empty( $settings['enable_analyzer'] ) ) {
            return;
        }

        // AJAX endpoint for live analysis
        add_action( 'wp_ajax_search_intel_analyze', [ $this, 'ajax_analyze' ] );

        // Analysis-on-Save
        add_action( 'wp_after_insert_post', [ $this, 'analyze_on_save' ], 20, 2 );
        add_action( 'admin_notices', [ $this, 'render_save_notice' ] );
    }

    /**
     * Run analysis when a post is saved and store scores in post meta.
     */
    public function analyze_on_save( int $post_id, $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        $allowed_types = $this->settings['enabled_post_types'] ?? [ 'post', 'page' ];
        if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $content     = $post->post_content;
        $title       = get_post_meta( $post_id, '_search_intel_title', true ) ?: $post->post_title;
        $keyword     = get_post_meta( $post_id, '_search_intel_focus_keyword', true );
        $description = get_post_meta( $post_id, '_search_intel_description', true );

        $results = $this->analyze( $post_id, $content, $title, $keyword, $description );

        // Capture old scores before overwriting — used by change log.
        $old_scores = [
            'seo'         => (float) get_post_meta( $post_id, '_search_intel_seo_score', true ),
            'aeo'         => (float) get_post_meta( $post_id, '_search_intel_aeo_score', true ),
            'geo'         => (float) get_post_meta( $post_id, '_search_intel_geo_score', true ),
            'readability' => (float) get_post_meta( $post_id, '_search_intel_readability_score', true ),
        ];

        $new_scores = [
            'seo'         => (float) ( $results['seo_score']['score'] ?? 0 ),
            'aeo'         => (float) ( $results['aeo_score']['score'] ?? 0 ),
            'geo'         => (float) ( $results['geo_score']['score'] ?? 0 ),
            'readability' => (float) ( $results['readability']['score'] ?? 0 ),
        ];

        update_post_meta( $post_id, '_search_intel_seo_score', $new_scores['seo'] );
        update_post_meta( $post_id, '_search_intel_aeo_score', $new_scores['aeo'] );
        update_post_meta( $post_id, '_search_intel_geo_score', $new_scores['geo'] );
        update_post_meta( $post_id, '_search_intel_readability_score', $new_scores['readability'] );

        /**
         * Fires after a post has been analyzed and scores saved.
         *
         * @param int   $post_id    Post ID.
         * @param array $new_scores New scores (seo, aeo, geo, readability).
         * @param array $old_scores Previous scores (seo, aeo, geo, readability).
         * @param array $results    Full analysis results including checks.
         */
        do_action( 'search_intel_post_analyzed', $post_id, $new_scores, $old_scores, $results );

        // Flag critical issues for admin notice
        $critical = [];
        if ( ( $results['seo_score']['score'] ?? 0 ) < 30 ) {
            $critical[] = __( 'SEO score is critically low', 'jat-search-intel' );
        }
        if ( empty( $keyword ) ) {
            $critical[] = __( 'No focus keyword set', 'jat-search-intel' );
        }
        if ( ( $results['word_count'] ?? 0 ) < 100 ) {
            $critical[] = __( 'Content is very thin (under 100 words)', 'jat-search-intel' );
        }

        if ( ! empty( $critical ) ) {
            set_transient( "search_intel_notice_{$post_id}_{$post->post_author}", $critical, 60 );
        }
    }

    /**
     * Display admin notice after save if critical issues were found.
     */
    public function render_save_notice(): void {
        $screen = get_current_screen();
        if ( ! $screen || 'post' !== $screen->base ) {
            return;
        }

        $post_id = get_the_ID();
        $user_id = get_current_user_id();
        if ( ! $post_id || ! $user_id ) {
            return;
        }

        $issues = get_transient( "search_intel_notice_{$post_id}_{$user_id}" );
        if ( empty( $issues ) ) {
            return;
        }

        delete_transient( "search_intel_notice_{$post_id}_{$user_id}" );

        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo '<strong>' . esc_html__( 'Search Intel:', 'jat-search-intel' ) . '</strong> ';
        echo esc_html( implode( ' · ', $issues ) );
        echo '</p></div>';
    }

    /**
     * AJAX handler for live content analysis.
     */
    public function ajax_analyze(): void {
        check_ajax_referer( 'search_intel_nonce', 'nonce' );

        $post_id = absint( $_POST['post_id'] ?? 0 );

        if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to analyze this post.', 'jat-search-intel' ) ], 403 );
        }

        // Rate limiting: max 10 analysis requests per minute per user
        $user_id   = get_current_user_id();
        $rate_key  = 'si_analyze_rate_' . $user_id;
        $rate_count = (int) get_transient( $rate_key );
        if ( $rate_count >= 10 ) {
            wp_send_json_error( [ 'message' => __( 'Too many requests. Please wait a moment.', 'jat-search-intel' ) ], 429 );
        }
        set_transient( $rate_key, $rate_count + 1, MINUTE_IN_SECONDS );

        $content = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
        $title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $keyword = sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ?? '' ) );
        $description = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );

        $results = $this->analyze( $post_id, $content, $title, $keyword, $description );

        wp_send_json_success( $results );
    }

    /**
     * Run full analysis on content.
     */
    public function analyze( int $post_id, string $content, string $title, string $keyword, string $description ): array {
        $text       = wp_strip_all_tags( $content );
        $word_count = str_word_count( $text );

        return [
            'seo_score'       => $this->get_seo_score( $post_id, $content, $title, $keyword, $description, $text, $word_count ),
            'aeo_score'       => $this->get_aeo_score( $post_id, $content, $text, $word_count ),
            'geo_score'       => $this->get_geo_score( $post_id, $content, $text, $word_count ),
            'readability'     => $this->get_readability_score( $text ),
            'word_count'      => $word_count,
            'keyword_data'    => $keyword ? $this->get_keyword_analysis( $text, $content, $title, $keyword, $description ) : null,
            'content_checks'  => $this->get_content_checks( $post_id, $content, $text, $word_count ),
        ];
    }

    /**
     * SEO Score — traditional on-page SEO checks.
     */
    private function get_seo_score( int $post_id, string $content, string $title, string $keyword, string $description, string $text, int $word_count ): array {
        $score  = 0;
        $checks = [];

        // 1. Focus keyword in title (15 pts)
        if ( $keyword ) {
            if ( stripos( $title, $keyword ) !== false ) {
                $score += 15;
                $checks[] = [ 'pass' => true, 'label' => __( 'Focus keyword found in title', 'jat-search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Add focus keyword to the title', 'jat-search-intel' ) ];
            }
        }

        // 2. Focus keyword in meta description (10 pts)
        if ( $keyword && $description ) {
            if ( stripos( $description, $keyword ) !== false ) {
                $score += 10;
                $checks[] = [ 'pass' => true, 'label' => __( 'Focus keyword found in meta description', 'jat-search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Add focus keyword to meta description', 'jat-search-intel' ) ];
            }
        }

        // 3. Meta description length (10 pts)
        if ( $description ) {
            $desc_len = strlen( $description );
            if ( $desc_len >= 50 && $desc_len <= 160 ) {
                $score += 10;
                $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Meta description length is good (%d chars)', 'jat-search-intel' ), $desc_len ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Meta description should be 50-160 chars (currently %d)', 'jat-search-intel' ), $desc_len ) ];
            }
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a meta description', 'jat-search-intel' ) ];
        }

        // 4. Title length (10 pts)
        $title_len = strlen( $title );
        if ( $title_len >= 30 && $title_len <= 60 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Title length is optimal (%d chars)', 'jat-search-intel' ), $title_len ) ];
        } elseif ( $title_len > 0 ) {
            $score += 3;
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Title should be 30-60 chars (currently %d)', 'jat-search-intel' ), $title_len ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a title', 'jat-search-intel' ) ];
        }

        // 5. Content length (10 pts)
        if ( $word_count >= 300 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Content length is sufficient (%d words)', 'jat-search-intel' ), $word_count ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Content should be 300+ words (currently %d)', 'jat-search-intel' ), $word_count ) ];
        }

        // 6. Focus keyword in first paragraph (10 pts)
        if ( $keyword ) {
            $first_para = '';
            if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
                $first_para = wp_strip_all_tags( $m[1] );
            }
            if ( $first_para && stripos( $first_para, $keyword ) !== false ) {
                $score += 10;
                $checks[] = [ 'pass' => true, 'label' => __( 'Focus keyword in first paragraph', 'jat-search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Include focus keyword in the first paragraph', 'jat-search-intel' ) ];
            }
        }

        // 7. Keyword density (10 pts)
        if ( $keyword && $word_count > 0 ) {
            $kw_count = substr_count( strtolower( $text ), strtolower( $keyword ) );
            $density  = ( $kw_count / $word_count ) * 100;
            if ( $density >= 0.5 && $density <= 2.5 ) {
                $score += 10;
                $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Keyword density is good (%.1f%%)', 'jat-search-intel' ), $density ) ];
            } elseif ( $density > 0 ) {
                $score += 3;
                $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Keyword density is %.1f%% (aim for 0.5-2.5%%)', 'jat-search-intel' ), $density ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Focus keyword not found in content', 'jat-search-intel' ) ];
            }
        }

        // 8. Headings present (10 pts)
        $has_headings = (bool) preg_match( '/<h[2-6]/i', $content );
        if ( $has_headings ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => __( 'Content uses subheadings', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add subheadings (H2, H3) to structure content', 'jat-search-intel' ) ];
        }

        // 9. Images with alt text (8 pts)
        if ( preg_match_all( '/<img[^>]+alt=["\']([^"\']*)["\'][^>]*>/i', $content, $img_matches ) ) {
            $total_imgs = count( $img_matches[0] );
            $with_alt   = count( array_filter( $img_matches[1], function ( $alt ) {
                return strlen( trim( $alt ) ) > 0;
            } ) );
            if ( $with_alt === $total_imgs ) {
                $score += 8;
                $checks[] = [ 'pass' => true, 'label' => __( 'All images have alt text', 'jat-search-intel' ) ];
            } else {
                $score += 3;
                $checks[] = [ 'pass' => false, 'label' => sprintf( __( '%d of %d images missing alt text', 'jat-search-intel' ), $total_imgs - $with_alt, $total_imgs ) ];
            }
        } elseif ( $word_count > 300 ) {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add images to make content more engaging', 'jat-search-intel' ) ];
        }

        // 10. Internal links (7 pts)
        $internal_links = 0;
        if ( preg_match_all( '/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $content, $link_matches ) ) {
            $site_url = home_url();
            foreach ( $link_matches[1] as $href ) {
                if ( strpos( $href, $site_url ) === 0 || strpos( $href, '/' ) === 0 ) {
                    $internal_links++;
                }
            }
        }
        if ( $internal_links >= 2 ) {
            $score += 7;
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( '%d internal links found', 'jat-search-intel' ), $internal_links ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add at least 2 internal links', 'jat-search-intel' ) ];
        }

        return [
            'score'  => min( $score, 100 ),
            'max'    => 100,
            'checks' => $checks,
        ];
    }

    /**
     * AEO Score — answer engine optimization checks.
     */
    private function get_aeo_score( int $post_id, string $content, string $text, int $word_count ): array {
        $score  = 0;
        $checks = [];

        // 1. Direct answer in first paragraph (20 pts)
        if ( preg_match( '/<p[^>]*>(.{50,300})<\/p>/is', $content, $m ) ) {
            $first = wp_strip_all_tags( $m[1] );
            if ( ! str_ends_with( trim( $first ), '?' ) ) {
                $score += 20;
                $checks[] = [ 'pass' => true, 'label' => __( 'Direct answer in first paragraph', 'jat-search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'First paragraph should provide a direct answer', 'jat-search-intel' ) ];
            }
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a concise first paragraph (50-300 chars)', 'jat-search-intel' ) ];
        }

        // 2. Question-heading pattern (20 pts)
        $q_headings = preg_match_all( '/<h[2-3][^>]*>.*?\?.*?<\/h[2-3]>/i', $content );
        if ( $q_headings >= 2 ) {
            $score += 20;
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( '%d question-style headings found', 'jat-search-intel' ), $q_headings ) ];
        } elseif ( $q_headings === 1 ) {
            $score += 10;
            $checks[] = [ 'pass' => false, 'label' => __( 'Add more question-style headings (aim for 2+)', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Use question-style headings for featured snippets', 'jat-search-intel' ) ];
        }

        // 3. Structured lists (15 pts)
        if ( preg_match( '/<[ou]l/i', $content ) ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Structured lists present', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add ordered/unordered lists', 'jat-search-intel' ) ];
        }

        // 4. Table content (15 pts)
        if ( preg_match( '/<table/i', $content ) ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Data tables present', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Consider adding comparison/data tables', 'jat-search-intel' ) ];
        }

        // 5. FAQ data (15 pts)
        $faqs = get_post_meta( $post_id, '_search_intel_faqs', true );
        if ( ! empty( $faqs ) ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'FAQ schema data configured', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add FAQ schema for voice/answer engine results', 'jat-search-intel' ) ];
        }

        // 6. Concise paragraphs (15 pts)
        if ( preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $paras ) ) {
            $short_count = 0;
            foreach ( $paras[1] as $p ) {
                if ( str_word_count( wp_strip_all_tags( $p ) ) <= 50 ) {
                    $short_count++;
                }
            }
            $ratio = $short_count / max( count( $paras[1] ), 1 );
            if ( $ratio >= 0.6 ) {
                $score += 15;
                $checks[] = [ 'pass' => true, 'label' => __( 'Paragraphs are concise and scannable', 'jat-search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Keep paragraphs under 50 words for scannability', 'jat-search-intel' ) ];
            }
        }

        return [
            'score'  => min( $score, 100 ),
            'max'    => 100,
            'checks' => $checks,
        ];
    }

    /**
     * GEO Score — generative engine optimization checks.
     */
    private function get_geo_score( int $post_id, string $content, string $text, int $word_count ): array {
        $score  = 0;
        $checks = [];

        // 1. AI Summary (15 pts)
        $ai_summary = get_post_meta( $post_id, '_search_intel_ai_summary', true );
        if ( $ai_summary ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'AI summary provided', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add an AI summary for LLM indexing', 'jat-search-intel' ) ];
        }

        // 2. Source citations (15 pts)
        $has_citations = (bool) preg_match( '/(according to|source:|study shows|research from|data from)/i', $text );
        if ( $has_citations ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Content includes source citations', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add source citations for AI credibility', 'jat-search-intel' ) ];
        }

        // 3. Definitions present (15 pts)
        $has_defs = (bool) preg_match( '/(is defined as|refers to|is a |means that)/i', $text );
        if ( $has_defs ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Clear definitions found', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Include clear definitions of key terms', 'jat-search-intel' ) ];
        }

        // 4. Author bio (10 pts)
        $author_id  = get_post_field( 'post_author', $post_id );
        $author_bio = get_the_author_meta( 'description', $author_id );
        if ( $author_bio && strlen( $author_bio ) > 50 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => __( 'Detailed author bio present', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a detailed author bio for E-E-A-T', 'jat-search-intel' ) ];
        }

        // 5. Content depth (15 pts)
        if ( $word_count >= 800 ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Good content depth (%d words)', 'jat-search-intel' ), $word_count ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Aim for 800+ words for topical depth (%d)', 'jat-search-intel' ), $word_count ) ];
        }

        // 6. Entities defined (15 pts)
        $entities = get_post_meta( $post_id, '_search_intel_entities', true );
        if ( ! empty( $entities ) ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Entities marked up for AI understanding', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Define entities (people, orgs, places) for semantic clarity', 'jat-search-intel' ) ];
        }

        // 7. Content freshness (15 pts)
        $modified = get_the_modified_date( 'U', $post_id );
        $age_days = $modified ? ( time() - $modified ) / DAY_IN_SECONDS : 999;
        if ( $age_days <= 90 ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Content recently updated', 'jat-search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Update content for freshness signals', 'jat-search-intel' ) ];
        }

        return [
            'score'  => min( $score, 100 ),
            'max'    => 100,
            'checks' => $checks,
        ];
    }

    /**
     * Flesch-Kincaid readability analysis.
     */
    public function get_readability_score( string $text ): array {
        if ( empty( $text ) ) {
            return [ 'score' => 0, 'grade' => 'N/A', 'label' => __( 'No content to analyze', 'jat-search-intel' ) ];
        }

        $sentences   = max( preg_match_all( '/[.!?]+/', $text ), 1 );
        $words       = max( str_word_count( $text ), 1 );
        $syllables   = $this->count_syllables( $text );

        // Flesch Reading Ease
        $score = 206.835 - ( 1.015 * ( $words / $sentences ) ) - ( 84.6 * ( $syllables / $words ) );
        $score = max( 0, min( 100, round( $score, 1 ) ) );

        // Grade level
        $grade = 0.39 * ( $words / $sentences ) + 11.8 * ( $syllables / $words ) - 15.59;
        $grade = max( 0, round( $grade, 1 ) );

        // Label
        if ( $score >= 80 ) {
            $label = __( 'Very Easy — 6th grade level', 'jat-search-intel' );
        } elseif ( $score >= 60 ) {
            $label = __( 'Standard — 8th-9th grade level', 'jat-search-intel' );
        } elseif ( $score >= 40 ) {
            $label = __( 'Somewhat Difficult — college level', 'jat-search-intel' );
        } elseif ( $score >= 20 ) {
            $label = __( 'Difficult — graduate level', 'jat-search-intel' );
        } else {
            $label = __( 'Very Difficult — professional level', 'jat-search-intel' );
        }

        $target = $this->settings['target_readability'] ?? 60;

        return [
            'score'           => $score,
            'grade'           => $grade,
            'label'           => $label,
            'target'          => $target,
            'meets_target'    => $score >= $target,
            'sentence_count'  => $sentences,
            'avg_sentence_len'=> round( $words / $sentences, 1 ),
        ];
    }

    /**
     * Focus keyword analysis.
     */
    private function get_keyword_analysis( string $text, string $content, string $title, string $keyword, string $description ): array {
        $text_lower = strtolower( $text );
        $kw_lower   = strtolower( $keyword );
        $word_count = max( str_word_count( $text ), 1 );

        $occurrences = substr_count( $text_lower, $kw_lower );
        $density     = ( $occurrences / $word_count ) * 100;

        // Check locations
        $in_title       = stripos( $title, $keyword ) !== false;
        $in_description = $description && stripos( $description, $keyword ) !== false;
        $in_h2          = (bool) preg_match( '/<h2[^>]*>.*?' . preg_quote( $keyword, '/' ) . '.*?<\/h2>/is', $content );
        $in_first_para  = false;

        if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
            $in_first_para = stripos( $m[1], $keyword ) !== false;
        }

        // Check URL slug
        $in_slug = false;
        if ( is_singular() ) {
            $slug    = get_post_field( 'post_name', get_queried_object_id() );
            $in_slug = $slug && stripos( $slug, sanitize_title( $keyword ) ) !== false;
        }

        return [
            'keyword'        => $keyword,
            'occurrences'    => $occurrences,
            'density'        => round( $density, 2 ),
            'in_title'       => $in_title,
            'in_description' => $in_description,
            'in_h2'          => $in_h2,
            'in_first_para'  => $in_first_para,
            'in_slug'        => $in_slug,
        ];
    }

    /**
     * Approximate syllable count for English text.
     */
    private function count_syllables( string $text ): int {
        $words    = str_word_count( strtolower( $text ), 1 );
        $total    = 0;

        foreach ( $words as $word ) {
            $word = preg_replace( '/[^a-z]/', '', $word );
            if ( strlen( $word ) <= 3 ) {
                $total += 1;
                continue;
            }

            // Count vowel groups
            $syllables = preg_match_all( '/[aeiouy]+/', $word );

            // Subtract silent e
            if ( preg_match( '/[^le]e$/', $word ) ) {
                $syllables--;
            }

            // Ensure at least 1
            $total += max( 1, $syllables );
        }

        return $total;
    }

    /**
     * v1.1 Content Checks — additional quality and completeness checks.
     */
    private function get_content_checks( int $post_id, string $content, string $text, int $word_count ): array {
        $checks = [];

        // 1. H1 presence detection (exactly one H1 or post title as H1)
        $h1_count = preg_match_all( '/<h1/i', $content );
        if ( $h1_count === 0 ) {
            $checks[] = [ 'pass' => true, 'label' => __( 'No H1 in content (post title used as H1)', 'jat-search-intel' ), 'group' => 'structure' ];
        } elseif ( $h1_count === 1 ) {
            $checks[] = [ 'pass' => true, 'label' => __( 'Exactly one H1 heading found', 'jat-search-intel' ), 'group' => 'structure' ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Multiple H1 tags found (%d) — use only one', 'jat-search-intel' ), $h1_count ), 'group' => 'structure' ];
        }

        // 2. Heading hierarchy validation (no skipped levels)
        $heading_levels = [];
        if ( preg_match_all( '/<h([1-6])/i', $content, $h_matches ) ) {
            $heading_levels = array_map( 'intval', $h_matches[1] );
        }
        $hierarchy_ok = true;
        for ( $i = 1; $i < count( $heading_levels ); $i++ ) {
            if ( $heading_levels[ $i ] > $heading_levels[ $i - 1 ] + 1 ) {
                $hierarchy_ok = false;
                break;
            }
        }
        if ( ! empty( $heading_levels ) ) {
            if ( $hierarchy_ok ) {
                $checks[] = [ 'pass' => true, 'label' => __( 'Heading hierarchy is correct (no skipped levels)', 'jat-search-intel' ), 'group' => 'structure' ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Heading levels are skipped — use sequential order (H2→H3→H4)', 'jat-search-intel' ), 'group' => 'structure' ];
            }
        }

        // 3. Internal link opportunity suggestions
        $internal_links = 0;
        $external_links = 0;
        if ( preg_match_all( '/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $content, $link_matches ) ) {
            $site_url = home_url();
            foreach ( $link_matches[1] as $href ) {
                if ( strpos( $href, $site_url ) === 0 || ( strpos( $href, '/' ) === 0 && strpos( $href, '//' ) !== 0 ) ) {
                    $internal_links++;
                } elseif ( strpos( $href, 'http' ) === 0 ) {
                    $external_links++;
                }
            }
        }
        $recommended_internal = max( 2, intval( $word_count / 500 ) );
        if ( $internal_links >= $recommended_internal ) {
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( '%d internal links (recommended: %d+)', 'jat-search-intel' ), $internal_links, $recommended_internal ), 'group' => 'links' ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Add more internal links (%d found, recommend %d+)', 'jat-search-intel' ), $internal_links, $recommended_internal ), 'group' => 'links' ];
        }

        // 4. External citation presence scoring
        if ( $external_links >= 1 ) {
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( '%d external citations/links found', 'jat-search-intel' ), $external_links ), 'group' => 'credibility' ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add external links to authoritative sources', 'jat-search-intel' ), 'group' => 'credibility' ];
        }

        // 5. Answer block opportunity detection
        $has_answer_block = false;
        if ( preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $paras ) ) {
            foreach ( $paras[1] as $p ) {
                $p_text = wp_strip_all_tags( $p );
                $p_words = str_word_count( $p_text );
                if ( $p_words >= 20 && $p_words <= 50 ) {
                    $has_answer_block = true;
                    break;
                }
            }
        }
        $has_definition = (bool) preg_match( '/(is defined as|refers to|is a |is the )/i', $text );
        $has_list_block = (bool) preg_match( '/<[ou]l[^>]*>.*?<\/[ou]l>/is', $content );
        if ( $has_answer_block || $has_definition || $has_list_block ) {
            $checks[] = [ 'pass' => true, 'label' => __( 'Content contains answer-friendly blocks (definitions, lists, or concise paragraphs)', 'jat-search-intel' ), 'group' => 'aeo' ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a concise answer block (20-50 words), definition, or list for snippet targeting', 'jat-search-intel' ), 'group' => 'aeo' ];
        }

        // 6. Entity clarity scoring (key entities in repeater)
        $entities = get_post_meta( $post_id, '_search_intel_entities', true );
        $entity_count = is_array( $entities ) ? count( $entities ) : 0;
        if ( $entity_count >= 3 ) {
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( '%d entities defined for semantic clarity', 'jat-search-intel' ), $entity_count ), 'group' => 'geo' ];
        } elseif ( $entity_count > 0 ) {
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Only %d entity defined — aim for 3+ key entities', 'jat-search-intel' ), $entity_count ), 'group' => 'geo' ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Define key entities (people, organizations, concepts) in the GEO tab', 'jat-search-intel' ), 'group' => 'geo' ];
        }

        // 7. AI summary presence check
        $ai_summary = get_post_meta( $post_id, '_search_intel_ai_summary', true );
        if ( $ai_summary && str_word_count( $ai_summary ) >= 10 ) {
            $checks[] = [ 'pass' => true, 'label' => __( 'AI summary is set and substantive', 'jat-search-intel' ), 'group' => 'geo' ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add an AI summary (10+ words) in the GEO tab', 'jat-search-intel' ), 'group' => 'geo' ];
        }

        // 8. Schema completeness check
        $schema_type = get_post_meta( $post_id, '_search_intel_schema_type', true );
        $faqs        = get_post_meta( $post_id, '_search_intel_faqs', true );
        $schema_score = 0;
        if ( $schema_type ) { $schema_score++; }
        if ( ! empty( $faqs ) ) { $schema_score++; }
        if ( ! empty( $entities ) ) { $schema_score++; }
        if ( $schema_score >= 2 ) {
            $checks[] = [ 'pass' => true, 'label' => __( 'Schema data is well-configured', 'jat-search-intel' ), 'group' => 'schema' ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Improve schema: set schema type, FAQs, or entities', 'jat-search-intel' ), 'group' => 'schema' ];
        }

        // 9. Secondary keyword usage in content
        $secondary_kw = get_post_meta( $post_id, '_search_intel_secondary_keywords', true );
        if ( $secondary_kw ) {
            $keywords_arr = array_filter( array_map( 'trim', explode( ',', $secondary_kw ) ) );
            $found = 0;
            foreach ( $keywords_arr as $kw ) {
                if ( stripos( $text, $kw ) !== false ) {
                    $found++;
                }
            }
            $total_kw = count( $keywords_arr );
            if ( $found === $total_kw ) {
                $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'All %d secondary keywords found in content', 'jat-search-intel' ), $total_kw ), 'group' => 'seo' ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => sprintf( __( '%d of %d secondary keywords found — add missing ones', 'jat-search-intel' ), $found, $total_kw ), 'group' => 'seo' ];
            }
        }

        // 10. Social metadata completeness check
        $social_title = get_post_meta( $post_id, '_search_intel_social_title', true );
        $social_desc  = get_post_meta( $post_id, '_search_intel_social_description', true );
        $social_image = get_post_meta( $post_id, '_search_intel_social_image', true );
        $social_count = ( $social_title ? 1 : 0 ) + ( $social_desc ? 1 : 0 ) + ( $social_image ? 1 : 0 );
        if ( $social_count === 3 ) {
            $checks[] = [ 'pass' => true, 'label' => __( 'Social sharing metadata is complete', 'jat-search-intel' ), 'group' => 'social' ];
        } elseif ( $social_count > 0 ) {
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Social metadata partially set (%d/3) — add title, description, and image', 'jat-search-intel' ), $social_count ), 'group' => 'social' ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Set custom social sharing title, description, and image in the SEO tab', 'jat-search-intel' ), 'group' => 'social' ];
        }

        // 11. Image alt text presence check (detailed)
        $total_imgs = 0;
        $missing_alt = 0;
        if ( preg_match_all( '/<img[^>]*>/i', $content, $img_tags ) ) {
            $total_imgs = count( $img_tags[0] );
            foreach ( $img_tags[0] as $img ) {
                if ( ! preg_match( '/alt=["\']([^"\']+)["\']/i', $img ) ) {
                    $missing_alt++;
                }
            }
        }
        if ( $total_imgs > 0 ) {
            if ( $missing_alt === 0 ) {
                $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'All %d images have alt text', 'jat-search-intel' ), $total_imgs ), 'group' => 'accessibility' ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => sprintf( __( '%d of %d images missing alt text', 'jat-search-intel' ), $missing_alt, $total_imgs ), 'group' => 'accessibility' ];
            }
        }

        return $checks;
    }
}
