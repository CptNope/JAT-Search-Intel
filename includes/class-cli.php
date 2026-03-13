<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP-CLI commands for Search Intel.
 *
 * ## EXAMPLES
 *
 *     wp search-intel analyze 42
 *     wp search-intel rescore --all
 *     wp search-intel regenerate --all --post_type=page
 *     wp search-intel sitemap cache
 *     wp search-intel export > settings.json
 *     wp search-intel import < settings.json
 *     wp search-intel scores --format=table
 *
 * @since 1.3.0
 */
class CLI extends \WP_CLI_Command {

    /**
     * Analyze a single post and display scores.
     *
     * ## OPTIONS
     *
     * <post_id>
     * : The ID of the post to analyze.
     *
     * [--save]
     * : Save scores to post meta.
     *
     * ## EXAMPLES
     *
     *     wp search-intel analyze 42
     *     wp search-intel analyze 42 --save
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function analyze( $args, $assoc_args ) {
        $post_id = absint( $args[0] );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            \WP_CLI::error( "Post {$post_id} not found." );
        }

        $core        = Core::get_instance();
        $content     = wp_strip_all_tags( $post->post_content );
        $title       = $post->post_title;
        $keyword     = get_post_meta( $post_id, '_search_intel_focus_keyword', true ) ?: '';
        $description = get_post_meta( $post_id, '_search_intel_description', true ) ?: '';

        \WP_CLI::log( "Analyzing: \"{$title}\" (ID: {$post_id})..." );

        $results = $core->analyzer->analyze( $post_id, $content, $title, $keyword, $description );

        $scores = [
            'SEO'         => $results['seo']['score'] ?? 0,
            'AEO'         => $results['aeo']['score'] ?? 0,
            'GEO'         => $results['geo']['score'] ?? 0,
            'Readability' => $results['readability']['score'] ?? 0,
        ];

        if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'save', false ) ) {
            update_post_meta( $post_id, '_search_intel_seo_score', (int) $scores['SEO'] );
            update_post_meta( $post_id, '_search_intel_aeo_score', (int) $scores['AEO'] );
            update_post_meta( $post_id, '_search_intel_geo_score', (int) $scores['GEO'] );
            update_post_meta( $post_id, '_search_intel_readability_score', (int) $scores['Readability'] );
            \WP_CLI::log( 'Scores saved to post meta.' );
        }

        $table = [];
        foreach ( $scores as $label => $score ) {
            $table[] = [
                'Category' => $label,
                'Score'    => $score,
                'Rating'   => $score >= 70 ? 'Good' : ( $score >= 40 ? 'Needs Work' : 'Poor' ),
            ];
        }

        \WP_CLI\Utils\format_items( 'table', $table, [ 'Category', 'Score', 'Rating' ] );
    }

    /**
     * Rescore all published posts.
     *
     * ## OPTIONS
     *
     * [--all]
     * : Required flag to confirm bulk operation.
     *
     * [--post_type=<type>]
     * : Post type to rescore.
     * ---
     * default: post
     * ---
     *
     * [--batch_size=<size>]
     * : Number of posts per batch.
     * ---
     * default: 50
     * ---
     *
     * ## EXAMPLES
     *
     *     wp search-intel rescore --all
     *     wp search-intel rescore --all --post_type=page
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function rescore( $args, $assoc_args ) {
        if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false ) ) {
            \WP_CLI::error( 'Please pass --all to confirm bulk rescore.' );
        }

        $post_type  = $assoc_args['post_type'] ?? 'post';
        $batch_size = absint( $assoc_args['batch_size'] ?? 50 );
        $core       = Core::get_instance();

        $total_obj = wp_count_posts( $post_type );
        $total     = (int) $total_obj->publish;

        if ( 0 === $total ) {
            \WP_CLI::warning( "No published {$post_type} posts found." );
            return;
        }

        \WP_CLI::log( "Rescoring {$total} published {$post_type}(s) in batches of {$batch_size}..." );
        $progress = \WP_CLI\Utils\make_progress_bar( 'Rescoring', $total );

        $offset  = 0;
        $scored  = 0;

        while ( $offset < $total ) {
            $posts = get_posts( [
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => $batch_size,
                'offset'         => $offset,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ] );

            foreach ( $posts as $post ) {
                $content     = wp_strip_all_tags( $post->post_content );
                $title       = $post->post_title;
                $keyword     = get_post_meta( $post->ID, '_search_intel_focus_keyword', true ) ?: '';
                $description = get_post_meta( $post->ID, '_search_intel_description', true ) ?: '';

                $results = $core->analyzer->analyze( $post->ID, $content, $title, $keyword, $description );

                if ( isset( $results['seo']['score'] ) ) {
                    update_post_meta( $post->ID, '_search_intel_seo_score', (int) $results['seo']['score'] );
                }
                if ( isset( $results['aeo']['score'] ) ) {
                    update_post_meta( $post->ID, '_search_intel_aeo_score', (int) $results['aeo']['score'] );
                }
                if ( isset( $results['geo']['score'] ) ) {
                    update_post_meta( $post->ID, '_search_intel_geo_score', (int) $results['geo']['score'] );
                }
                if ( isset( $results['readability']['score'] ) ) {
                    update_post_meta( $post->ID, '_search_intel_readability_score', (int) $results['readability']['score'] );
                }

                $scored++;
                $progress->tick();
            }

            $offset += $batch_size;
        }

        $progress->finish();
        \WP_CLI::success( "Rescored {$scored} posts." );
    }

    /**
     * Regenerate metadata for all published posts.
     *
     * Fills in empty descriptions, AI summaries, and content types.
     * Existing values are never overwritten.
     *
     * ## OPTIONS
     *
     * [--all]
     * : Required flag to confirm bulk operation.
     *
     * [--post_type=<type>]
     * : Post type to process.
     * ---
     * default: post
     * ---
     *
     * ## EXAMPLES
     *
     *     wp search-intel regenerate --all
     *     wp search-intel regenerate --all --post_type=page
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function regenerate( $args, $assoc_args ) {
        if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false ) ) {
            \WP_CLI::error( 'Please pass --all to confirm bulk regeneration.' );
        }

        $post_type  = $assoc_args['post_type'] ?? 'post';
        $batch_size = 50;
        $core       = Core::get_instance();

        $total_obj = wp_count_posts( $post_type );
        $total     = (int) $total_obj->publish;

        if ( 0 === $total ) {
            \WP_CLI::warning( "No published {$post_type} posts found." );
            return;
        }

        \WP_CLI::log( "Regenerating metadata for {$total} published {$post_type}(s)..." );
        $progress = \WP_CLI\Utils\make_progress_bar( 'Regenerating', $total );

        $offset    = 0;
        $processed = 0;

        while ( $offset < $total ) {
            $posts = get_posts( [
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => $batch_size,
                'offset'         => $offset,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ] );

            foreach ( $posts as $post ) {
                // Auto-fill description.
                $desc = get_post_meta( $post->ID, '_search_intel_description', true );
                if ( empty( $desc ) ) {
                    $auto = $core->seo->get_description( $post->ID );
                    if ( $auto ) {
                        update_post_meta( $post->ID, '_search_intel_description', sanitize_text_field( $auto ) );
                    }
                }

                // Auto-fill AI summary.
                $summary = get_post_meta( $post->ID, '_search_intel_ai_summary', true );
                if ( empty( $summary ) ) {
                    $auto = wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '...' );
                    if ( $auto ) {
                        update_post_meta( $post->ID, '_search_intel_ai_summary', sanitize_text_field( $auto ) );
                    }
                }

                // Auto-detect content type.
                $type = get_post_meta( $post->ID, '_search_intel_content_type', true );
                if ( empty( $type ) ) {
                    $lower = strtolower( $post->post_content );
                    if ( str_contains( $lower, 'how to' ) || str_contains( $lower, 'step' ) ) {
                        $detected = 'how-to';
                    } elseif ( str_contains( $lower, '?' ) && substr_count( $lower, '?' ) >= 3 ) {
                        $detected = 'faq';
                    } elseif ( str_contains( $lower, ' vs ' ) || str_contains( $lower, 'compare' ) ) {
                        $detected = 'comparison';
                    } else {
                        $detected = 'article';
                    }
                    update_post_meta( $post->ID, '_search_intel_content_type', $detected );
                }

                $processed++;
                $progress->tick();
            }

            $offset += $batch_size;
        }

        $progress->finish();
        \WP_CLI::success( "Regenerated metadata for {$processed} posts." );
    }

    /**
     * Manage sitemap cache.
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform: 'cache' to warm, 'clear' to invalidate.
     *
     * ## EXAMPLES
     *
     *     wp search-intel sitemap cache
     *     wp search-intel sitemap clear
     *
     * @param array $args Positional arguments.
     */
    public function sitemap( $args ) {
        $action = $args[0] ?? 'cache';
        $core   = Core::get_instance();

        if ( 'clear' === $action ) {
            $core->sitemap->invalidate_cache();
            \WP_CLI::success( 'Sitemap cache cleared.' );
            return;
        }

        if ( 'cache' === $action ) {
            $core->sitemap->invalidate_cache();
            \WP_CLI::success( 'Sitemap cache cleared and queued for regeneration on next request.' );
            return;
        }

        \WP_CLI::error( "Unknown action '{$action}'. Use 'cache' or 'clear'." );
    }

    /**
     * Export plugin settings as JSON.
     *
     * ## EXAMPLES
     *
     *     wp search-intel export
     *     wp search-intel export > settings.json
     *
     * @subcommand export
     */
    public function export_settings() {
        $core = Core::get_instance();

        $data = [
            'plugin'   => 'search-intel',
            'version'  => SEARCH_INTEL_VERSION,
            'exported' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'settings' => $core->get_settings(),
            'social'   => get_option( 'search_intel_social_profiles', [] ),
        ];

        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
    }

    /**
     * Import plugin settings from JSON.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the JSON settings file.
     *
     * [--mode=<mode>]
     * : Import mode: 'merge' or 'overwrite'.
     * ---
     * default: merge
     * options:
     *   - merge
     *   - overwrite
     * ---
     *
     * ## EXAMPLES
     *
     *     wp search-intel import settings.json
     *     wp search-intel import settings.json --mode=overwrite
     *
     * @subcommand import
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function import_settings( $args, $assoc_args ) {
        $file = $args[0];

        if ( ! file_exists( $file ) ) {
            \WP_CLI::error( "File not found: {$file}" );
        }

        $json = file_get_contents( $file );
        $data = json_decode( $json, true );

        if ( ! is_array( $data ) || empty( $data['plugin'] ) || 'search-intel' !== $data['plugin'] ) {
            \WP_CLI::error( 'Invalid Search Intel settings file.' );
        }

        $mode     = $assoc_args['mode'] ?? 'merge';
        $core     = Core::get_instance();
        $defaults = Core::get_default_settings();

        if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
            $import = $data['settings'];

            if ( 'overwrite' === $mode ) {
                $new = $defaults;
            } else {
                $new = $core->get_settings();
            }

            foreach ( $import as $key => $value ) {
                if ( ! array_key_exists( $key, $defaults ) ) {
                    continue;
                }

                $default_val = $defaults[ $key ];
                if ( is_bool( $default_val ) ) {
                    $new[ $key ] = (bool) $value;
                } elseif ( is_int( $default_val ) ) {
                    $new[ $key ] = absint( $value );
                } elseif ( is_array( $default_val ) ) {
                    $new[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : $default_val;
                } else {
                    $new[ $key ] = sanitize_text_field( $value );
                }
            }

            update_option( 'search_intel_settings', $new );
        }

        if ( ! empty( $data['social'] ) && is_array( $data['social'] ) ) {
            $social = array_map( 'esc_url_raw', $data['social'] );
            update_option( 'search_intel_social_profiles', $social );
        }

        \WP_CLI::success( "Settings imported ({$mode} mode) from {$file}." );
    }

    /**
     * Display scores for all published posts.
     *
     * ## OPTIONS
     *
     * [--post_type=<type>]
     * : Post type to display.
     * ---
     * default: post
     * ---
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     * ---
     *
     * [--limit=<limit>]
     * : Maximum number of posts.
     * ---
     * default: 50
     * ---
     *
     * [--orderby=<field>]
     * : Sort by score field: seo, aeo, geo, readability.
     * ---
     * default: ID
     * ---
     *
     * ## EXAMPLES
     *
     *     wp search-intel scores
     *     wp search-intel scores --format=csv --limit=100
     *     wp search-intel scores --orderby=seo
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function scores( $args, $assoc_args ) {
        $post_type = $assoc_args['post_type'] ?? 'post';
        $limit     = absint( $assoc_args['limit'] ?? 50 );
        $format    = $assoc_args['format'] ?? 'table';
        $orderby   = $assoc_args['orderby'] ?? 'ID';

        $query_args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ];

        // Sort by meta value if a score field is specified.
        $score_fields = [
            'seo'         => '_search_intel_seo_score',
            'aeo'         => '_search_intel_aeo_score',
            'geo'         => '_search_intel_geo_score',
            'readability' => '_search_intel_readability_score',
        ];

        if ( isset( $score_fields[ $orderby ] ) ) {
            $query_args['meta_key'] = $score_fields[ $orderby ];
            $query_args['orderby']  = 'meta_value_num';
            $query_args['order']    = 'DESC';
        }

        $posts = get_posts( $query_args );

        if ( empty( $posts ) ) {
            \WP_CLI::warning( "No published {$post_type} posts found." );
            return;
        }

        $rows = [];
        foreach ( $posts as $post ) {
            $rows[] = [
                'ID'          => $post->ID,
                'Title'       => mb_strimwidth( $post->post_title, 0, 50, '...' ),
                'SEO'         => (int) get_post_meta( $post->ID, '_search_intel_seo_score', true ),
                'AEO'         => (int) get_post_meta( $post->ID, '_search_intel_aeo_score', true ),
                'GEO'         => (int) get_post_meta( $post->ID, '_search_intel_geo_score', true ),
                'Readability' => (int) get_post_meta( $post->ID, '_search_intel_readability_score', true ),
                'Keyword'     => get_post_meta( $post->ID, '_search_intel_focus_keyword', true ) ?: '—',
            ];
        }

        \WP_CLI\Utils\format_items( $format, $rows, [ 'ID', 'Title', 'SEO', 'AEO', 'GEO', 'Readability', 'Keyword' ] );
    }
}
