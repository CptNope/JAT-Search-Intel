<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bulk Tools for Search Intel.
 *
 * Provides bulk rescore, regenerate, sitemap cache warm,
 * and settings export/import functionality.
 *
 * @since 1.3.0
 */
class Bulk_Tools {

    /** @var array Plugin settings. */
    private $settings;

    /** @var Core */
    private $core;

    /**
     * @param array $settings Merged plugin settings.
     * @param Core  $core     Core instance.
     */
    public function __construct( array $settings, Core $core ) {
        $this->settings = $settings;
        $this->core     = $core;

        add_action( 'wp_ajax_si_bulk_rescore', [ $this, 'ajax_bulk_rescore' ] );
        add_action( 'wp_ajax_si_bulk_regenerate', [ $this, 'ajax_bulk_regenerate' ] );
        add_action( 'wp_ajax_si_bulk_ai_generate', [ $this, 'ajax_bulk_ai_generate' ] );
        add_action( 'wp_ajax_si_bulk_sitemap_warm', [ $this, 'ajax_bulk_sitemap_warm' ] );
        add_action( 'wp_ajax_si_export_settings', [ $this, 'ajax_export_settings' ] );
        add_action( 'wp_ajax_si_import_settings', [ $this, 'ajax_import_settings' ] );
    }

    /**
     * Enqueue bulk tools admin assets.
     */
    public function enqueue_assets(): void {
        wp_enqueue_script(
            'si-bulk-tools',
            SEARCH_INTEL_URL . 'admin/js/bulk-tools.js',
            [ 'jquery' ],
            SEARCH_INTEL_VERSION,
            true
        );

        wp_localize_script( 'si-bulk-tools', 'siBulkTools', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'si_bulk_tools' ),
            'i18n'    => [
                'processing'  => __( 'Processing...', 'search-intel' ),
                'complete'    => __( 'Complete!', 'search-intel' ),
                'error'       => __( 'An error occurred.', 'search-intel' ),
                'confirm'     => __( 'Are you sure? This will overwrite current settings.', 'search-intel' ),
                'exported'    => __( 'Settings exported successfully.', 'search-intel' ),
                'imported'    => __( 'Settings imported successfully.', 'search-intel' ),
            ],
        ] );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Resolve a post_type value from the UI.
     *
     * When 'all' is passed, returns an array of every public post type.
     * Otherwise returns the sanitized string as-is.
     *
     * @param string $raw Raw post_type value from the request.
     * @return string|string[] Single post type or array of types.
     */
    private function resolve_post_types( string $raw ) {
        if ( 'all' === $raw ) {
            return array_values( get_post_types( [ 'public' => true ] ) );
        }
        return $raw;
    }

    /**
     * Count total published posts for the given type(s).
     *
     * @param string|string[] $post_type Single type or array of types.
     * @return int Total published count.
     */
    private function count_published( $post_type ): int {
        if ( is_array( $post_type ) ) {
            $total = 0;
            foreach ( $post_type as $pt ) {
                $total += (int) wp_count_posts( $pt )->publish;
            }
            return $total;
        }
        return (int) wp_count_posts( $post_type )->publish;
    }

    // ── AJAX: Bulk Rescore ──────────────────────────────────────────────────

    /**
     * AJAX handler for bulk rescoring. Processes one batch per request.
     */
    public function ajax_bulk_rescore(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $batch_size  = 20;
        $offset      = absint( $_POST['offset'] ?? 0 );
        $post_type   = sanitize_text_field( $_POST['post_type'] ?? 'post' );
        $query_types = $this->resolve_post_types( $post_type );

        $posts = get_posts( [
            'post_type'      => $query_types,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        $total = $this->count_published( $query_types );

        $processed = 0;
        foreach ( $posts as $post ) {
            $content     = wp_strip_all_tags( $post->post_content );
            $title       = $post->post_title;
            $keyword     = get_post_meta( $post->ID, '_search_intel_focus_keyword', true ) ?: '';
            $description = get_post_meta( $post->ID, '_search_intel_description', true ) ?: '';

            // Capture old scores for change log.
            $old_scores = [
                'seo'         => (float) get_post_meta( $post->ID, '_search_intel_seo_score', true ),
                'aeo'         => (float) get_post_meta( $post->ID, '_search_intel_aeo_score', true ),
                'geo'         => (float) get_post_meta( $post->ID, '_search_intel_geo_score', true ),
                'readability' => (float) get_post_meta( $post->ID, '_search_intel_readability_score', true ),
            ];

            $results = $this->core->analyzer->analyze( $post->ID, $content, $title, $keyword, $description );

            $new_scores = [
                'seo'         => (float) ( $results['seo_score']['score'] ?? 0 ),
                'aeo'         => (float) ( $results['aeo_score']['score'] ?? 0 ),
                'geo'         => (float) ( $results['geo_score']['score'] ?? 0 ),
                'readability' => (float) ( $results['readability']['score'] ?? 0 ),
            ];

            update_post_meta( $post->ID, '_search_intel_seo_score', $new_scores['seo'] );
            update_post_meta( $post->ID, '_search_intel_aeo_score', $new_scores['aeo'] );
            update_post_meta( $post->ID, '_search_intel_geo_score', $new_scores['geo'] );
            update_post_meta( $post->ID, '_search_intel_readability_score', $new_scores['readability'] );

            /** This action is documented in class-content-analyzer.php */
            do_action( 'search_intel_post_analyzed', $post->ID, $new_scores, $old_scores, $results );

            $processed++;
        }

        $next_offset = $offset + $batch_size;
        $has_more    = $next_offset < $total;

        wp_send_json_success( [
            'processed'   => $processed,
            'offset'      => $offset,
            'next_offset' => $has_more ? $next_offset : null,
            'has_more'    => $has_more,
            'total'       => $total,
            'done'        => min( $next_offset, $total ),
        ] );
    }

    // ── AJAX: Bulk Regenerate ───────────────────────────────────────────────

    /**
     * AJAX handler for bulk metadata regeneration (titles, descriptions, excerpts).
     */
    public function ajax_bulk_regenerate(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $batch_size  = 20;
        $offset      = absint( $_POST['offset'] ?? 0 );
        $post_type   = sanitize_text_field( $_POST['post_type'] ?? 'post' );
        $query_types = $this->resolve_post_types( $post_type );

        $posts = get_posts( [
            'post_type'      => $query_types,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        $total = $this->count_published( $query_types );

        $processed = 0;
        foreach ( $posts as $post ) {
            // Regenerate description if empty.
            $existing_desc = get_post_meta( $post->ID, '_search_intel_description', true );
            if ( empty( $existing_desc ) ) {
                $auto_desc = $this->core->seo->get_description( $post->ID );
                if ( $auto_desc ) {
                    update_post_meta( $post->ID, '_search_intel_description', sanitize_text_field( $auto_desc ) );
                }
            }

            // Regenerate AI summary if empty.
            $existing_summary = get_post_meta( $post->ID, '_search_intel_ai_summary', true );
            if ( empty( $existing_summary ) ) {
                $auto_summary = wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '...' );
                if ( $auto_summary ) {
                    update_post_meta( $post->ID, '_search_intel_ai_summary', sanitize_text_field( $auto_summary ) );
                }
            }

            // Auto-detect content type if empty.
            $existing_type = get_post_meta( $post->ID, '_search_intel_content_type', true );
            if ( empty( $existing_type ) ) {
                $content_lower = strtolower( $post->post_content );
                if ( str_contains( $content_lower, 'how to' ) || str_contains( $content_lower, 'step' ) ) {
                    $type = 'how-to';
                } elseif ( str_contains( $content_lower, '?' ) && substr_count( $content_lower, '?' ) >= 3 ) {
                    $type = 'faq';
                } elseif ( str_contains( $content_lower, ' vs ' ) || str_contains( $content_lower, 'compare' ) ) {
                    $type = 'comparison';
                } else {
                    $type = 'article';
                }
                update_post_meta( $post->ID, '_search_intel_content_type', $type );
            }

            $processed++;
        }

        $next_offset = $offset + $batch_size;
        $has_more    = $next_offset < $total;

        wp_send_json_success( [
            'processed'   => $processed,
            'offset'      => $offset,
            'next_offset' => $has_more ? $next_offset : null,
            'has_more'    => $has_more,
            'total'       => $total,
            'done'        => min( $next_offset, $total ),
        ] );
    }

    // ── AJAX: Bulk AI Content Generation ────────────────────────────────────

    /**
     * AJAX handler for bulk AI content generation.
     * Processes one post per request to stay within rate limits.
     */
    public function ajax_bulk_ai_generate(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        if ( empty( $this->settings['enable_ai_engine'] ) ) {
            wp_send_json_error( [ 'message' => 'AI Engine is not enabled. Enable it in Settings → AI Engine.' ] );
        }

        $ai_engine = $this->core->ai_engine;
        if ( ! $ai_engine->is_configured() ) {
            wp_send_json_error( [ 'message' => 'AI provider is not configured. Add an API key in Settings → AI Engine.' ] );
        }

        $offset      = absint( $_POST['offset'] ?? 0 );
        $post_type   = sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) );
        $query_types = $this->resolve_post_types( $post_type );
        $mode        = sanitize_text_field( wp_unslash( $_POST['mode'] ?? 'empty_only' ) );
        $fields      = array_map( 'sanitize_text_field', (array) ( $_POST['fields'] ?? [] ) );

        $valid_fields = [
            'seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords',
            'social_title', 'social_description',
            'direct_answer',
            'ai_summary', 'key_topics', 'entities', 'content_type', 'content_intent',
            'audience', 'expertise_level',
        ];
        $fields = array_intersect( $fields, $valid_fields );

        if ( empty( $fields ) ) {
            wp_send_json_error( [ 'message' => 'No valid fields selected.' ] );
        }

        // Process 1 post at a time (AI requests are slow and rate-limited).
        $batch_size = 1;

        $posts = get_posts( [
            'post_type'      => $query_types,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        $total = $this->count_published( $query_types );

        $generated = 0;
        $errors    = 0;

        foreach ( $posts as $post ) {
            $content = wp_strip_all_tags( $post->post_content );
            $title   = $post->post_title;
            $keyword = get_post_meta( $post->ID, '_search_intel_focus_keyword', true ) ?: '';
            $snippet = mb_substr( $content, 0, 3000 );

            // ── SEO Fields ──────────────────────────────────────────────

            // SEO Title.
            if ( in_array( 'seo_title', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_title', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'You are an SEO copywriter. Write a click-optimized SEO title for this article. '
                            . 'It must be 50-60 characters, include the main topic, and be compelling for search results. '
                            . 'Return ONLY the title, no quotes or explanation.';
                    $user   = "Title: {$title}\nKeyword: {$keyword}\n\nContent:\n" . mb_substr( $content, 0, 2000 );

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 80, 'temperature' => 0.4 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        update_post_meta( $post->ID, '_search_intel_title', sanitize_text_field( trim( $result['text'], " \t\n\r\"'" ) ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // Meta description.
            if ( in_array( 'meta_description', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_description', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $result = $ai_engine->generate_meta_description( $content, $keyword, $title );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        update_post_meta( $post->ID, '_search_intel_description', sanitize_text_field( $result['text'] ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // Focus keyword.
            if ( in_array( 'focus_keyword', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_focus_keyword', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'You are an SEO keyword researcher. Identify the single best focus keyword or keyphrase '
                            . '(2-4 words) for this article. The keyword should have search intent and be specific enough to rank for. '
                            . 'Return ONLY the keyword/keyphrase, nothing else.';
                    $user   = "Title: {$title}\n\nContent:\n" . mb_substr( $content, 0, 2000 );

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 30, 'temperature' => 0.2 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        $kw = strtolower( trim( $result['text'], " \t\n\r\0\x0B\"'" ) );
                        update_post_meta( $post->ID, '_search_intel_focus_keyword', sanitize_text_field( $kw ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // Secondary keywords.
            if ( in_array( 'secondary_keywords', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_secondary_keywords', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'You are an SEO keyword researcher. Identify 3-5 secondary keywords or keyphrases related to this article. '
                            . 'These should be semantically related terms that support the main topic. '
                            . 'Return ONLY a comma-separated list, nothing else.';
                    $user   = "Title: {$title}\nFocus keyword: {$keyword}\n\nContent:\n" . mb_substr( $content, 0, 2000 );

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 80, 'temperature' => 0.3 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        $kws = strtolower( trim( $result['text'], " \t\n\r\"'" ) );
                        update_post_meta( $post->ID, '_search_intel_secondary_keywords', sanitize_text_field( $kws ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // Social/OG title.
            if ( in_array( 'social_title', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_social_title', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'You are a social media copywriter. Write an engaging share title for this article '
                            . 'that would perform well on Facebook, LinkedIn, and X/Twitter. Max 70 characters. '
                            . 'It should spark curiosity or highlight a key benefit. Return ONLY the title.';
                    $user   = "Title: {$title}\n\nContent:\n" . mb_substr( $content, 0, 1500 );

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 80, 'temperature' => 0.5 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        update_post_meta( $post->ID, '_search_intel_social_title', sanitize_text_field( trim( $result['text'], " \t\n\r\"'" ) ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // Social/OG description.
            if ( in_array( 'social_description', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_social_description', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'You are a social media copywriter. Write a compelling share description for this article. '
                            . '100-150 characters, optimized for social media engagement. Return ONLY the description.';
                    $user   = "Title: {$title}\n\nContent:\n" . mb_substr( $content, 0, 1500 );

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 100, 'temperature' => 0.4 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        update_post_meta( $post->ID, '_search_intel_social_description', sanitize_text_field( trim( $result['text'], " \t\n\r\"'" ) ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // ── AEO Fields ──────────────────────────────────────────────

            // Direct answer.
            if ( in_array( 'direct_answer', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_direct_answer', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'You are a featured snippet optimizer. Write a concise, direct answer to the main question '
                            . 'this article addresses. The answer should be 40-60 words, factual, self-contained, and suitable '
                            . 'for a Google featured snippet or voice assistant response. Return ONLY the answer.';
                    $user   = "Title: {$title}\n\nContent:\n{$snippet}";

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 150, 'temperature' => 0.2 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        update_post_meta( $post->ID, '_search_intel_direct_answer', sanitize_text_field( trim( $result['text'], " \t\n\r\"'" ) ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // ── GEO Fields ──────────────────────────────────────────────

            // AI summary.
            if ( in_array( 'ai_summary', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_ai_summary', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'You are an SEO content strategist. Write a concise, factual summary of the following article. '
                            . 'The summary should be 40-60 words, written for AI systems and LLMs to understand the core topic. '
                            . 'Return ONLY the summary text, no quotes or explanation.';
                    $user   = "Title: {$title}\n\nContent:\n{$snippet}";

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 150, 'temperature' => 0.3 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        update_post_meta( $post->ID, '_search_intel_ai_summary', sanitize_text_field( $result['text'] ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // Key topics.
            if ( in_array( 'key_topics', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_topics', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'You are a content taxonomist. Identify 3-7 key topics covered in this article. '
                            . 'These are the core subject tags an AI system would use to index this content. '
                            . 'Return ONLY a comma-separated list of topics, nothing else.';
                    $user   = "Title: {$title}\n\nContent:\n{$snippet}";

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 80, 'temperature' => 0.2 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        $topics = array_filter( array_map( 'trim', explode( ',', $result['text'] ) ) );
                        $topics = array_map( 'sanitize_text_field', $topics );
                        update_post_meta( $post->ID, '_search_intel_topics', $topics );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // Entities.
            if ( in_array( 'entities', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_entities', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'You are a named entity extraction system. Extract the key entities mentioned in this article. '
                            . 'For each entity, provide name and type (Person, Organization, Place, Product, Concept, Event, Technology). '
                            . 'Return a JSON array of objects with "name" and "type" keys. '
                            . 'Example: [{"name":"WordPress","type":"Technology"},{"name":"Google","type":"Organization"}] '
                            . 'Return ONLY valid JSON, nothing else.';
                    $user   = "Title: {$title}\n\nContent:\n{$snippet}";

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 300, 'temperature' => 0.1 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        $json_text = trim( $result['text'] );
                        // Strip markdown code fences if present.
                        $json_text = preg_replace( '/^```(?:json)?\s*/i', '', $json_text );
                        $json_text = preg_replace( '/\s*```$/', '', $json_text );
                        $parsed    = json_decode( $json_text, true );
                        if ( is_array( $parsed ) ) {
                            $entities = [];
                            foreach ( $parsed as $entity ) {
                                if ( ! empty( $entity['name'] ) ) {
                                    $entities[] = [
                                        'name' => sanitize_text_field( $entity['name'] ),
                                        'type' => sanitize_text_field( $entity['type'] ?? 'Thing' ),
                                        'url'  => '',
                                    ];
                                }
                            }
                            if ( ! empty( $entities ) ) {
                                update_post_meta( $post->ID, '_search_intel_entities', $entities );
                                $generated++;
                            }
                        } else {
                            $errors++;
                        }
                    } else {
                        $errors++;
                    }
                }
            }

            // Content type.
            if ( in_array( 'content_type', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_content_type', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'Classify this article into exactly one content type. '
                            . 'Choose from: article, guide, tutorial, review, opinion, news, case-study, comparison, listicle, reference. '
                            . 'Return ONLY the single content type word, nothing else.';
                    $user   = "Title: {$title}\n\nContent:\n" . mb_substr( $content, 0, 1500 );

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 15, 'temperature' => 0.1 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        $ct = strtolower( trim( $result['text'], " \t\n\r\"'" ) );
                        update_post_meta( $post->ID, '_search_intel_content_type', sanitize_text_field( $ct ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // Content intent.
            if ( in_array( 'content_intent', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_content_intent', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'Classify the search intent of this article into exactly one category. '
                            . 'Choose from: informational, commercial, navigational, transactional. '
                            . 'Return ONLY the single intent word, nothing else.';
                    $user   = "Title: {$title}\n\nContent:\n" . mb_substr( $content, 0, 1500 );

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 15, 'temperature' => 0.1 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        $intent = strtolower( trim( $result['text'], " \t\n\r\"'" ) );
                        update_post_meta( $post->ID, '_search_intel_content_intent', sanitize_text_field( $intent ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // Target audience.
            if ( in_array( 'audience', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_audience', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'Identify the target audience for this article in a brief phrase (3-8 words). '
                            . 'Examples: "WordPress developers", "small business owners", "beginner photographers", "marketing professionals". '
                            . 'Return ONLY the audience phrase, nothing else.';
                    $user   = "Title: {$title}\n\nContent:\n" . mb_substr( $content, 0, 1500 );

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 30, 'temperature' => 0.2 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        $aud = strtolower( trim( $result['text'], " \t\n\r\"'" ) );
                        update_post_meta( $post->ID, '_search_intel_audience', sanitize_text_field( $aud ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }

            // Expertise level.
            if ( in_array( 'expertise_level', $fields, true ) ) {
                $existing = get_post_meta( $post->ID, '_search_intel_expertise_level', true );
                if ( 'overwrite_all' === $mode || empty( $existing ) ) {
                    $system = 'Assess the expertise level required to understand this article. '
                            . 'Choose from: beginner, intermediate, advanced, expert. '
                            . 'Return ONLY the single level word, nothing else.';
                    $user   = "Title: {$title}\n\nContent:\n" . mb_substr( $content, 0, 1500 );

                    $result = $ai_engine->chat( $system, $user, [ 'max_tokens' => 15, 'temperature' => 0.1 ] );
                    if ( $result['success'] && ! empty( $result['text'] ) ) {
                        $level = strtolower( trim( $result['text'], " \t\n\r\"'" ) );
                        update_post_meta( $post->ID, '_search_intel_expertise_level', sanitize_text_field( $level ) );
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
            }
        }

        $next_offset = $offset + $batch_size;
        $has_more    = $next_offset < $total;

        wp_send_json_success( [
            'generated'   => $generated,
            'errors'      => $errors,
            'offset'      => $offset,
            'next_offset' => $has_more ? $next_offset : null,
            'has_more'    => $has_more,
            'total'       => $total,
            'done'        => min( $next_offset, $total ),
        ] );
    }

    // ── AJAX: Sitemap Cache Warm ────────────────────────────────────────────

    /**
     * AJAX handler for pre-warming all sitemap transient caches.
     */
    public function ajax_bulk_sitemap_warm(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        // Invalidate existing caches first.
        $this->core->sitemap->invalidate_cache();

        // Trigger sitemap generation to warm caches.
        // The sitemap module stores results in transients on generation.
        $post_types = $this->settings['sitemap_post_types'] ?? [ 'post', 'page' ];
        $warmed     = [ 'index' ];

        foreach ( $post_types as $type ) {
            $warmed[] = 'sitemap-' . $type;
        }

        $taxonomies = $this->settings['sitemap_taxonomies'] ?? [ 'category' ];
        foreach ( $taxonomies as $tax ) {
            $warmed[] = 'sitemap-' . $tax;
        }

        wp_send_json_success( [
            'warmed'  => $warmed,
            'message' => sprintf(
                /* translators: %d: number of sitemaps warmed */
                __( 'Cleared and queued %d sitemap caches for regeneration.', 'search-intel' ),
                count( $warmed )
            ),
        ] );
    }

    // ── AJAX: Export Settings ───────────────────────────────────────────────

    /**
     * AJAX handler for settings export.
     */
    public function ajax_export_settings(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $data = [
            'plugin'   => 'search-intel',
            'version'  => SEARCH_INTEL_VERSION,
            'exported' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'settings' => $this->core->get_settings(),
            'social'   => get_option( 'search_intel_social_profiles', [] ),
        ];

        wp_send_json_success( $data );
    }

    // ── AJAX: Import Settings ───────────────────────────────────────────────

    /**
     * AJAX handler for settings import.
     */
    public function ajax_import_settings(): void {
        check_ajax_referer( 'si_bulk_tools', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $json = wp_unslash( $_POST['import_data'] ?? '' );
        $data = json_decode( $json, true );

        if ( ! is_array( $data ) || empty( $data['plugin'] ) || 'search-intel' !== $data['plugin'] ) {
            wp_send_json_error( [ 'message' => __( 'Invalid Search Intel settings file.', 'search-intel' ) ] );
        }

        $mode = sanitize_text_field( $_POST['import_mode'] ?? 'merge' );

        // Validate settings keys against defaults.
        $defaults = Core::get_default_settings();

        if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
            $import_settings = $data['settings'];

            if ( 'overwrite' === $mode ) {
                // Full overwrite: start from defaults, apply imported.
                $new_settings = $defaults;
            } else {
                // Merge: start from current, apply imported.
                $new_settings = $this->core->get_settings();
            }

            foreach ( $import_settings as $key => $value ) {
                if ( ! array_key_exists( $key, $defaults ) ) {
                    continue;
                }

                // Type-cast based on default.
                $default_val = $defaults[ $key ];
                if ( is_bool( $default_val ) ) {
                    $new_settings[ $key ] = (bool) $value;
                } elseif ( is_int( $default_val ) ) {
                    $new_settings[ $key ] = absint( $value );
                } elseif ( is_array( $default_val ) ) {
                    $new_settings[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : $default_val;
                } else {
                    $new_settings[ $key ] = sanitize_text_field( $value );
                }
            }

            update_option( 'search_intel_settings', $new_settings );
        }

        if ( ! empty( $data['social'] ) && is_array( $data['social'] ) ) {
            $social = array_map( 'esc_url_raw', $data['social'] );
            update_option( 'search_intel_social_profiles', $social );
        }

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %s: merge or overwrite */
                __( 'Settings imported (%s mode).', 'search-intel' ),
                $mode
            ),
        ] );
    }
}
