<?php
/**
 * Sitemap cold benchmark — run inside wp-env via:
 * npx wp-env run cli -- wp eval-file /var/www/html/wp-content/plugins/jat-search-intel/tests/benchmark-sitemap.php
 */

// Clear any cached sitemaps.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%jat_search_intel_sitemap%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_si_%'" );

$post_count = wp_count_posts( 'post' );
echo 'Published posts: ' . $post_count->publish . PHP_EOL;

// Benchmark cold sitemap index generation.
$start    = microtime( true );
$response = wp_remote_get( home_url( '/sitemap.xml' ), array( 'timeout' => 30 ) );
$elapsed  = ( microtime( true ) - $start ) * 1000;

$status = wp_remote_retrieve_response_code( $response );
$body   = wp_remote_retrieve_body( $response );

echo 'Sitemap index cold fetch: ' . round( $elapsed, 2 ) . 'ms' . PHP_EOL;
echo 'HTTP status: ' . $status . PHP_EOL;
echo 'Body length: ' . strlen( $body ) . ' bytes' . PHP_EOL;

// Benchmark cold post sitemap page 1.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%jat_search_intel_sitemap%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_si_%'" );

$start2    = microtime( true );
$response2 = wp_remote_get( home_url( '/sitemap-post-1.xml' ), array( 'timeout' => 30 ) );
$elapsed2  = ( microtime( true ) - $start2 ) * 1000;

$status2 = wp_remote_retrieve_response_code( $response2 );
$body2   = wp_remote_retrieve_body( $response2 );

echo PHP_EOL;
echo 'Post sitemap page 1 cold fetch: ' . round( $elapsed2, 2 ) . 'ms' . PHP_EOL;
echo 'HTTP status: ' . $status2 . PHP_EOL;
echo 'Body length: ' . strlen( $body2 ) . ' bytes' . PHP_EOL;

// Summary.
$max = max( $elapsed, $elapsed2 );
echo PHP_EOL;
echo '--- RESULT ---' . PHP_EOL;
echo 'Max cold sitemap time: ' . round( $max, 2 ) . 'ms' . PHP_EOL;
echo 'Target: < 500ms' . PHP_EOL;
echo 'Status: ' . ( $max < 500 ? 'PASS' : 'FAIL' ) . PHP_EOL;
