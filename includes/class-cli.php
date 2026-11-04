<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP-CLI commands for JAT Search Intel.
 *
 * ## EXAMPLES
 *
 *     wp jat-search-intel analyze 42
 *     wp jat-search-intel rescore --all
 *     wp jat-search-intel regenerate --all --post_type=page
 *     wp jat-search-intel sitemap cache
 *     wp jat-search-intel export > settings.json
 *     wp jat-search-intel import < settings.json
 *     wp jat-search-intel scores --format=table
 *     wp jat-search-intel migrate --from=yoast --dry-run
 *     wp jat-search-intel rbac grant 5 seo
 *     wp jat-search-intel rbac list
 *     wp jat-search-intel multisite push-defaults
 *     wp jat-search-intel multisite report --format=table
 *     wp jat-search-intel queue status
 *     wp jat-search-intel queue process --batch=10
 *     wp jat-search-intel white-label export > brand.json
 *     wp jat-search-intel white-label import brand.json
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
     *     wp jat-search-intel analyze 42
     *     wp jat-search-intel analyze 42 --save
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
        $keyword     = get_post_meta( $post_id, '_jat_search_intel_focus_keyword', true ) ?: '';
        $description = get_post_meta( $post_id, '_jat_search_intel_description', true ) ?: '';

        \WP_CLI::log( "Analyzing: \"{$title}\" (ID: {$post_id})..." );

        $results = $core->analyzer->analyze( $post_id, $content, $title, $keyword, $description );

        $scores = [
            'SEO'         => $results['seo']['score'] ?? 0,
            'AEO'         => $results['aeo']['score'] ?? 0,
            'GEO'         => $results['geo']['score'] ?? 0,
            'Readability' => $results['readability']['score'] ?? 0,
        ];

        if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'save', false ) ) {
            update_post_meta( $post_id, '_jat_search_intel_seo_score', (int) $scores['SEO'] );
            update_post_meta( $post_id, '_jat_search_intel_aeo_score', (int) $scores['AEO'] );
            update_post_meta( $post_id, '_jat_search_intel_geo_score', (int) $scores['GEO'] );
            update_post_meta( $post_id, '_jat_search_intel_readability_score', (int) $scores['Readability'] );
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
     *     wp jat-search-intel rescore --all
     *     wp jat-search-intel rescore --all --post_type=page
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
                $keyword     = get_post_meta( $post->ID, '_jat_search_intel_focus_keyword', true ) ?: '';
                $description = get_post_meta( $post->ID, '_jat_search_intel_description', true ) ?: '';

                $results = $core->analyzer->analyze( $post->ID, $content, $title, $keyword, $description );

                if ( isset( $results['seo']['score'] ) ) {
                    update_post_meta( $post->ID, '_jat_search_intel_seo_score', (int) $results['seo']['score'] );
                }
                if ( isset( $results['aeo']['score'] ) ) {
                    update_post_meta( $post->ID, '_jat_search_intel_aeo_score', (int) $results['aeo']['score'] );
                }
                if ( isset( $results['geo']['score'] ) ) {
                    update_post_meta( $post->ID, '_jat_search_intel_geo_score', (int) $results['geo']['score'] );
                }
                if ( isset( $results['readability']['score'] ) ) {
                    update_post_meta( $post->ID, '_jat_search_intel_readability_score', (int) $results['readability']['score'] );
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
     *     wp jat-search-intel regenerate --all
     *     wp jat-search-intel regenerate --all --post_type=page
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
                $desc = get_post_meta( $post->ID, '_jat_search_intel_description', true );
                if ( empty( $desc ) ) {
                    $auto = $core->seo->get_description( $post->ID );
                    if ( $auto ) {
                        update_post_meta( $post->ID, '_jat_search_intel_description', sanitize_text_field( $auto ) );
                    }
                }

                // Auto-fill AI summary.
                $summary = get_post_meta( $post->ID, '_jat_search_intel_ai_summary', true );
                if ( empty( $summary ) ) {
                    $auto = wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '...' );
                    if ( $auto ) {
                        update_post_meta( $post->ID, '_jat_search_intel_ai_summary', sanitize_text_field( $auto ) );
                    }
                }

                // Auto-detect content type.
                $type = get_post_meta( $post->ID, '_jat_search_intel_content_type', true );
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
                    update_post_meta( $post->ID, '_jat_search_intel_content_type', $detected );
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
     *     wp jat-search-intel sitemap cache
     *     wp jat-search-intel sitemap clear
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
     *     wp jat-search-intel export
     *     wp jat-search-intel export > settings.json
     *
     * @subcommand export
     */
    public function export_settings() {
        $core = Core::get_instance();

        $data = [
            'plugin'   => 'jat-search-intel',
            'version'  => JAT_SEARCH_INTEL_VERSION,
            'exported' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'settings' => $core->get_settings(),
            'social'   => get_option( 'jat_search_intel_social_profiles', [] ),
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
     *     wp jat-search-intel import settings.json
     *     wp jat-search-intel import settings.json --mode=overwrite
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

        $json = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local file passed on the command line.
        $data = json_decode( $json, true );

        if ( ! is_array( $data ) || empty( $data['plugin'] ) || 'jat-search-intel' !== $data['plugin'] ) {
            \WP_CLI::error( 'Invalid JAT Search Intel settings file.' );
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

            update_option( 'jat_search_intel_settings', $new );
        }

        if ( ! empty( $data['social'] ) && is_array( $data['social'] ) ) {
            $social = array_map( 'esc_url_raw', $data['social'] );
            update_option( 'jat_search_intel_social_profiles', $social );
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
     *     wp jat-search-intel scores
     *     wp jat-search-intel scores --format=csv --limit=100
     *     wp jat-search-intel scores --orderby=seo
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
            'seo'         => '_jat_search_intel_seo_score',
            'aeo'         => '_jat_search_intel_aeo_score',
            'geo'         => '_jat_search_intel_geo_score',
            'readability' => '_jat_search_intel_readability_score',
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
                'SEO'         => (int) get_post_meta( $post->ID, '_jat_search_intel_seo_score', true ),
                'AEO'         => (int) get_post_meta( $post->ID, '_jat_search_intel_aeo_score', true ),
                'GEO'         => (int) get_post_meta( $post->ID, '_jat_search_intel_geo_score', true ),
                'Readability' => (int) get_post_meta( $post->ID, '_jat_search_intel_readability_score', true ),
                'Keyword'     => get_post_meta( $post->ID, '_jat_search_intel_focus_keyword', true ) ?: '—',
            ];
        }

        \WP_CLI\Utils\format_items( $format, $rows, [ 'ID', 'Title', 'SEO', 'AEO', 'GEO', 'Readability', 'Keyword' ] );
    }

    /**
     * Migrate SEO data from another plugin.
     *
     * ## OPTIONS
     *
     * --from=<source>
     * : Source plugin to migrate from.
     * ---
     * options:
     *   - yoast
     *   - rankmath
     *   - aioseo
     *   - seopress
     *   - seoframework
     * ---
     *
     * [--dry-run]
     * : Preview what will be migrated without making changes.
     *
     * [--overwrite]
     * : Overwrite existing JAT Search Intel data (default is merge).
     *
     * [--post_type=<type>]
     * : Comma-separated post types to migrate.
     * ---
     * default: post,page
     * ---
     *
     * ## EXAMPLES
     *
     *     wp jat-search-intel migrate --from=yoast
     *     wp jat-search-intel migrate --from=yoast --dry-run
     *     wp jat-search-intel migrate --from=rankmath --overwrite
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function migrate( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis -- $args required by WP-CLI.
        unset( $args );
        $source    = $assoc_args['from'] ?? '';
        $dry_run   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
        $overwrite = \WP_CLI\Utils\get_flag_value( $assoc_args, 'overwrite', false );

        if ( empty( $source ) ) {
            \WP_CLI::error( 'Please specify --from=<source>. Options: yoast, rankmath, aioseo, seopress, seoframework' );
        }

        $core      = Core::get_instance();
        $migration = $core->migration;

        // Verify source is detected.
        $sources = $migration->detect_sources();
        $found   = false;
        foreach ( $sources as $s ) {
            if ( strtolower( $s['slug'] ) === strtolower( $source ) ) {
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            \WP_CLI::error( "Source plugin '{$source}' not detected. Run without --from to see available sources, or ensure the plugin's data exists in the database." );
        }

        // Dry run — show preview.
        if ( $dry_run ) {
            \WP_CLI::log( "Dry-run migration from {$source}..." );
            $preview = $migration->dry_run( $source );

            if ( empty( $preview ) ) {
                \WP_CLI::warning( 'No data found to migrate.' );
                return;
            }

            $rows = [];
            foreach ( $preview as $item ) {
                $rows[] = [
                    'Post ID' => $item['post_id'] ?? '—',
                    'Field'   => $item['field'] ?? '—',
                    'Source'  => mb_strimwidth( (string) ( $item['source_value'] ?? '' ), 0, 60, '...' ),
                    'Current' => mb_strimwidth( (string) ( $item['current_value'] ?? '' ), 0, 60, '...' ),
                    'Action'  => $item['action'] ?? 'import',
                ];
            }

            \WP_CLI\Utils\format_items( 'table', $rows, [ 'Post ID', 'Field', 'Source', 'Current', 'Action' ] );
            \WP_CLI::log( sprintf( '%d field(s) would be migrated.', count( $rows ) ) );
            return;
        }

        // Full migration.
        \WP_CLI::log( sprintf( 'Migrating from %s (%s mode)...', $source, $overwrite ? 'overwrite' : 'merge' ) );
        $result = $migration->migrate( $source, $overwrite );

        if ( empty( $result ) || empty( $result['migrated'] ) ) {
            \WP_CLI::warning( 'No data was migrated.' );
            return;
        }

        \WP_CLI::success( sprintf(
            'Migration complete: %d field(s) migrated from %s.',
            $result['migrated'],
            $source
        ) );

        if ( ! empty( $result['redirects'] ) ) {
            \WP_CLI::log( sprintf( '%d redirect(s) imported.', $result['redirects'] ) );
        }

        if ( ! empty( $result['skipped'] ) ) {
            \WP_CLI::log( sprintf( '%d field(s) skipped (existing data, merge mode).', $result['skipped'] ) );
        }
    }

    /**
     * Manage role-based access control.
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform: 'grant', 'revoke', or 'list'.
     *
     * [<user_id>]
     * : User ID (required for grant/revoke).
     *
     * [<module>]
     * : Module key: seo, aeo, geo, schema, sitemap, redirects, analytics (required for grant/revoke).
     *
     * ## EXAMPLES
     *
     *     wp jat-search-intel rbac list
     *     wp jat-search-intel rbac grant 5 seo
     *     wp jat-search-intel rbac revoke 5 aeo
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function rbac( $args, $assoc_args ) {
        unset( $assoc_args );
        $action = $args[0] ?? '';
        $core   = Core::get_instance();
        $rbac   = $core->rbac;

        if ( 'list' === $action ) {
            $summary = $rbac->get_permissions_summary();

            if ( empty( $summary ) ) {
                \WP_CLI::log( 'No users with custom JAT Search Intel permissions found.' );
                return;
            }

            $rows = [];
            foreach ( $summary as $entry ) {
                $rows[] = [
                    'User ID'  => $entry['user_id'] ?? 0,
                    'Login'    => $entry['login'] ?? '—',
                    'Role'     => $entry['role'] ?? '—',
                    'Modules'  => implode( ', ', $entry['modules'] ?? [] ),
                    'View Only'=> ! empty( $entry['view_only'] ) ? 'Yes' : 'No',
                ];
            }

            \WP_CLI\Utils\format_items( 'table', $rows, [ 'User ID', 'Login', 'Role', 'Modules', 'View Only' ] );
            return;
        }

        if ( 'grant' === $action || 'revoke' === $action ) {
            $user_id = absint( $args[1] ?? 0 );
            $module  = $args[2] ?? '';

            if ( $user_id <= 0 ) {
                \WP_CLI::error( 'Please provide a valid user ID.' );
            }
            if ( empty( $module ) ) {
                \WP_CLI::error( 'Please provide a module key (seo, aeo, geo, schema, sitemap, redirects, analytics).' );
            }

            if ( 'grant' === $action ) {
                $ok = $rbac->grant_module( $user_id, $module );
                if ( $ok ) {
                    \WP_CLI::success( "Granted '{$module}' to user {$user_id}." );
                } else {
                    \WP_CLI::error( "Failed to grant '{$module}'. Check module key and user ID." );
                }
            } else {
                $ok = $rbac->revoke_module( $user_id, $module );
                if ( $ok ) {
                    \WP_CLI::success( "Revoked '{$module}' from user {$user_id}." );
                } else {
                    \WP_CLI::error( "Failed to revoke '{$module}'. Check module key and user ID." );
                }
            }
            return;
        }

        \WP_CLI::error( "Unknown action '{$action}'. Use 'grant', 'revoke', or 'list'." );
    }

    /**
     * Manage multisite network settings and reports.
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform: 'push-defaults' or 'report'.
     *
     * [--format=<format>]
     * : Output format for report.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp jat-search-intel multisite push-defaults
     *     wp jat-search-intel multisite report
     *     wp jat-search-intel multisite report --format=json
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function multisite( $args, $assoc_args ) {
        if ( ! is_multisite() ) {
            \WP_CLI::error( 'This command requires a WordPress multisite installation.' );
        }

        $action = $args[0] ?? '';
        $core   = Core::get_instance();
        $ms     = $core->multisite;

        if ( 'push-defaults' === $action ) {
            $defaults = $ms->get_network_defaults();

            if ( empty( $defaults ) ) {
                \WP_CLI::error( 'No network defaults configured. Set them in Network Admin > JAT Search Intel first.' );
            }

            $sites = get_sites( [ 'number' => 0 ] );
            $count = 0;

            foreach ( $sites as $site ) {
                switch_to_blog( (int) $site->blog_id );
                update_option( 'jat_search_intel_settings', $defaults );
                restore_current_blog();
                $count++;
            }

            \WP_CLI::success( sprintf( 'Pushed network defaults to %d site(s).', $count ) );
            return;
        }

        if ( 'report' === $action ) {
            $format = $assoc_args['format'] ?? 'table';
            $report = $ms->get_network_report();

            if ( empty( $report ) ) {
                \WP_CLI::warning( 'No sites found in the network.' );
                return;
            }

            $rows = [];
            foreach ( $report as $site ) {
                $rows[] = [
                    'Blog ID'     => $site['blog_id'] ?? 0,
                    'URL'         => $site['url'] ?? '—',
                    'Posts'       => $site['post_count'] ?? 0,
                    'Avg SEO'     => $site['avg_seo'] ?? 0,
                    'Avg AEO'     => $site['avg_aeo'] ?? 0,
                    'Avg GEO'     => $site['avg_geo'] ?? 0,
                    'Active'      => ! empty( $site['active'] ) ? 'Yes' : 'No',
                ];
            }

            \WP_CLI\Utils\format_items( $format, $rows, [ 'Blog ID', 'URL', 'Posts', 'Avg SEO', 'Avg AEO', 'Avg GEO', 'Active' ] );
            return;
        }

        \WP_CLI::error( "Unknown action '{$action}'. Use 'push-defaults' or 'report'." );
    }

    /**
     * Manage the background processing queue.
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform: 'status' or 'process'.
     *
     * [--batch=<size>]
     * : Number of jobs to process.
     * ---
     * default: 10
     * ---
     *
     * ## EXAMPLES
     *
     *     wp jat-search-intel queue status
     *     wp jat-search-intel queue process
     *     wp jat-search-intel queue process --batch=25
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function queue( $args, $assoc_args ) {
        $action = $args[0] ?? '';
        $core   = Core::get_instance();
        $scale  = $core->scale;

        if ( 'status' === $action ) {
            $queue = $scale->get_queue();

            if ( empty( $queue ) ) {
                \WP_CLI::log( 'Queue is empty.' );
                return;
            }

            $pending    = 0;
            $processing = 0;
            $completed  = 0;
            $failed     = 0;

            foreach ( $queue as $job ) {
                switch ( $job['status'] ?? '' ) {
                    case 'pending':
                        $pending++;
                        break;
                    case 'processing':
                        $processing++;
                        break;
                    case 'completed':
                        $completed++;
                        break;
                    case 'failed':
                        $failed++;
                        break;
                }
            }

            $rows = [
                [ 'Status' => 'Pending',    'Count' => $pending ],
                [ 'Status' => 'Processing', 'Count' => $processing ],
                [ 'Status' => 'Completed',  'Count' => $completed ],
                [ 'Status' => 'Failed',     'Count' => $failed ],
                [ 'Status' => 'Total',      'Count' => count( $queue ) ],
            ];

            \WP_CLI\Utils\format_items( 'table', $rows, [ 'Status', 'Count' ] );
            return;
        }

        if ( 'process' === $action ) {
            $batch = absint( $assoc_args['batch'] ?? 10 );

            \WP_CLI::log( "Processing up to {$batch} queued jobs..." );
            $processed = $scale->process_queue( $batch );

            if ( $processed > 0 ) {
                \WP_CLI::success( sprintf( 'Processed %d job(s).', $processed ) );
            } else {
                \WP_CLI::log( 'No pending jobs to process.' );
            }
            return;
        }

        \WP_CLI::error( "Unknown action '{$action}'. Use 'status' or 'process'." );
    }
}
