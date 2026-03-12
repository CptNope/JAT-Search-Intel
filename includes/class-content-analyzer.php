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
    }

    /**
     * AJAX handler for live content analysis.
     */
    public function ajax_analyze(): void {
        check_ajax_referer( 'search_intel_nonce', 'nonce' );

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $content = wp_kses_post( $_POST['content'] ?? '' );
        $title   = sanitize_text_field( $_POST['title'] ?? '' );
        $keyword = sanitize_text_field( $_POST['focus_keyword'] ?? '' );
        $description = sanitize_text_field( $_POST['description'] ?? '' );

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
            'seo_score'    => $this->get_seo_score( $post_id, $content, $title, $keyword, $description, $text, $word_count ),
            'aeo_score'    => $this->get_aeo_score( $post_id, $content, $text, $word_count ),
            'geo_score'    => $this->get_geo_score( $post_id, $content, $text, $word_count ),
            'readability'  => $this->get_readability_score( $text ),
            'word_count'   => $word_count,
            'keyword_data' => $keyword ? $this->get_keyword_analysis( $text, $content, $title, $keyword, $description ) : null,
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
                $checks[] = [ 'pass' => true, 'label' => __( 'Focus keyword found in title', 'search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Add focus keyword to the title', 'search-intel' ) ];
            }
        }

        // 2. Focus keyword in meta description (10 pts)
        if ( $keyword && $description ) {
            if ( stripos( $description, $keyword ) !== false ) {
                $score += 10;
                $checks[] = [ 'pass' => true, 'label' => __( 'Focus keyword found in meta description', 'search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Add focus keyword to meta description', 'search-intel' ) ];
            }
        }

        // 3. Meta description length (10 pts)
        if ( $description ) {
            $desc_len = strlen( $description );
            if ( $desc_len >= 50 && $desc_len <= 160 ) {
                $score += 10;
                $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Meta description length is good (%d chars)', 'search-intel' ), $desc_len ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Meta description should be 50-160 chars (currently %d)', 'search-intel' ), $desc_len ) ];
            }
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a meta description', 'search-intel' ) ];
        }

        // 4. Title length (10 pts)
        $title_len = strlen( $title );
        if ( $title_len >= 30 && $title_len <= 60 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Title length is optimal (%d chars)', 'search-intel' ), $title_len ) ];
        } elseif ( $title_len > 0 ) {
            $score += 3;
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Title should be 30-60 chars (currently %d)', 'search-intel' ), $title_len ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a title', 'search-intel' ) ];
        }

        // 5. Content length (10 pts)
        if ( $word_count >= 300 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Content length is sufficient (%d words)', 'search-intel' ), $word_count ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Content should be 300+ words (currently %d)', 'search-intel' ), $word_count ) ];
        }

        // 6. Focus keyword in first paragraph (10 pts)
        if ( $keyword ) {
            $first_para = '';
            if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
                $first_para = wp_strip_all_tags( $m[1] );
            }
            if ( $first_para && stripos( $first_para, $keyword ) !== false ) {
                $score += 10;
                $checks[] = [ 'pass' => true, 'label' => __( 'Focus keyword in first paragraph', 'search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Include focus keyword in the first paragraph', 'search-intel' ) ];
            }
        }

        // 7. Keyword density (10 pts)
        if ( $keyword && $word_count > 0 ) {
            $kw_count = substr_count( strtolower( $text ), strtolower( $keyword ) );
            $density  = ( $kw_count / $word_count ) * 100;
            if ( $density >= 0.5 && $density <= 2.5 ) {
                $score += 10;
                $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Keyword density is good (%.1f%%)', 'search-intel' ), $density ) ];
            } elseif ( $density > 0 ) {
                $score += 3;
                $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Keyword density is %.1f%% (aim for 0.5-2.5%%)', 'search-intel' ), $density ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Focus keyword not found in content', 'search-intel' ) ];
            }
        }

        // 8. Headings present (10 pts)
        $has_headings = (bool) preg_match( '/<h[2-6]/i', $content );
        if ( $has_headings ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => __( 'Content uses subheadings', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add subheadings (H2, H3) to structure content', 'search-intel' ) ];
        }

        // 9. Images with alt text (8 pts)
        if ( preg_match_all( '/<img[^>]+alt=["\']([^"\']*)["\'][^>]*>/i', $content, $img_matches ) ) {
            $total_imgs = count( $img_matches[0] );
            $with_alt   = count( array_filter( $img_matches[1], function ( $alt ) { return strlen( trim( $alt ) ) > 0; } ) );
            if ( $with_alt === $total_imgs ) {
                $score += 8;
                $checks[] = [ 'pass' => true, 'label' => __( 'All images have alt text', 'search-intel' ) ];
            } else {
                $score += 3;
                $checks[] = [ 'pass' => false, 'label' => sprintf( __( '%d of %d images missing alt text', 'search-intel' ), $total_imgs - $with_alt, $total_imgs ) ];
            }
        } elseif ( $word_count > 300 ) {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add images to make content more engaging', 'search-intel' ) ];
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
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( '%d internal links found', 'search-intel' ), $internal_links ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add at least 2 internal links', 'search-intel' ) ];
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
                $checks[] = [ 'pass' => true, 'label' => __( 'Direct answer in first paragraph', 'search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'First paragraph should provide a direct answer', 'search-intel' ) ];
            }
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a concise first paragraph (50-300 chars)', 'search-intel' ) ];
        }

        // 2. Question-heading pattern (20 pts)
        $q_headings = preg_match_all( '/<h[2-3][^>]*>.*?\?.*?<\/h[2-3]>/i', $content );
        if ( $q_headings >= 2 ) {
            $score += 20;
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( '%d question-style headings found', 'search-intel' ), $q_headings ) ];
        } elseif ( $q_headings === 1 ) {
            $score += 10;
            $checks[] = [ 'pass' => false, 'label' => __( 'Add more question-style headings (aim for 2+)', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Use question-style headings for featured snippets', 'search-intel' ) ];
        }

        // 3. Structured lists (15 pts)
        if ( preg_match( '/<[ou]l/i', $content ) ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Structured lists present', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add ordered/unordered lists', 'search-intel' ) ];
        }

        // 4. Table content (15 pts)
        if ( preg_match( '/<table/i', $content ) ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Data tables present', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Consider adding comparison/data tables', 'search-intel' ) ];
        }

        // 5. FAQ data (15 pts)
        $faqs = get_post_meta( $post_id, '_search_intel_faqs', true );
        if ( ! empty( $faqs ) ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'FAQ schema data configured', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add FAQ schema for voice/answer engine results', 'search-intel' ) ];
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
                $checks[] = [ 'pass' => true, 'label' => __( 'Paragraphs are concise and scannable', 'search-intel' ) ];
            } else {
                $checks[] = [ 'pass' => false, 'label' => __( 'Keep paragraphs under 50 words for scannability', 'search-intel' ) ];
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
            $checks[] = [ 'pass' => true, 'label' => __( 'AI summary provided', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add an AI summary for LLM indexing', 'search-intel' ) ];
        }

        // 2. Source citations (15 pts)
        $has_citations = (bool) preg_match( '/(according to|source:|study shows|research from|data from)/i', $text );
        if ( $has_citations ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Content includes source citations', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add source citations for AI credibility', 'search-intel' ) ];
        }

        // 3. Definitions present (15 pts)
        $has_defs = (bool) preg_match( '/(is defined as|refers to|is a |means that)/i', $text );
        if ( $has_defs ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Clear definitions found', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Include clear definitions of key terms', 'search-intel' ) ];
        }

        // 4. Author bio (10 pts)
        $author_id  = get_post_field( 'post_author', $post_id );
        $author_bio = get_the_author_meta( 'description', $author_id );
        if ( $author_bio && strlen( $author_bio ) > 50 ) {
            $score += 10;
            $checks[] = [ 'pass' => true, 'label' => __( 'Detailed author bio present', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Add a detailed author bio for E-E-A-T', 'search-intel' ) ];
        }

        // 5. Content depth (15 pts)
        if ( $word_count >= 800 ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => sprintf( __( 'Good content depth (%d words)', 'search-intel' ), $word_count ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => sprintf( __( 'Aim for 800+ words for topical depth (%d)', 'search-intel' ), $word_count ) ];
        }

        // 6. Entities defined (15 pts)
        $entities = get_post_meta( $post_id, '_search_intel_entities', true );
        if ( ! empty( $entities ) ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Entities marked up for AI understanding', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Define entities (people, orgs, places) for semantic clarity', 'search-intel' ) ];
        }

        // 7. Content freshness (15 pts)
        $modified = get_the_modified_date( 'U', $post_id );
        $age_days = $modified ? ( time() - $modified ) / DAY_IN_SECONDS : 999;
        if ( $age_days <= 90 ) {
            $score += 15;
            $checks[] = [ 'pass' => true, 'label' => __( 'Content recently updated', 'search-intel' ) ];
        } else {
            $checks[] = [ 'pass' => false, 'label' => __( 'Update content for freshness signals', 'search-intel' ) ];
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
            return [ 'score' => 0, 'grade' => 'N/A', 'label' => __( 'No content to analyze', 'search-intel' ) ];
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
            $label = __( 'Very Easy — 6th grade level', 'search-intel' );
        } elseif ( $score >= 60 ) {
            $label = __( 'Standard — 8th-9th grade level', 'search-intel' );
        } elseif ( $score >= 40 ) {
            $label = __( 'Somewhat Difficult — college level', 'search-intel' );
        } elseif ( $score >= 20 ) {
            $label = __( 'Difficult — graduate level', 'search-intel' );
        } else {
            $label = __( 'Very Difficult — professional level', 'search-intel' );
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
}
