<?php
/**
 * Block theme (TT4) compatibility test — run inside wp-env via:
 * npx wp-env run cli -- wp eval-file /var/www/html/wp-content/plugins/jat-search-intel/tests/benchmark-blocktheme.php
 *
 * Simulates a singular post request and captures wp_head output to verify
 * JAT Search Intel meta tags render correctly with an FSE block theme.
 */

echo '=== Block Theme (Twenty Twenty-Four) Compatibility Test ===' . PHP_EOL . PHP_EOL;

// Verify active theme.
$theme = wp_get_theme();
echo 'Active theme: ' . $theme->get( 'Name' ) . ' v' . $theme->get( 'Version' ) . PHP_EOL;
echo 'Is block theme: ' . ( wp_is_block_theme() ? 'YES' : 'NO' ) . PHP_EOL . PHP_EOL;

// Get a published post with content.
$posts = get_posts( array(
    'numberposts' => 1,
    'post_status' => 'publish',
    'orderby'     => 'ID',
    'order'       => 'ASC',
) );
if ( empty( $posts ) ) {
    echo 'ERROR: No published posts found.' . PHP_EOL;
    exit( 1 );
}
$post = $posts[0];
echo 'Test post: #' . $post->ID . ' — ' . $post->post_title . PHP_EOL . PHP_EOL;

// Simulate a singular post query so wp_head hooks fire correctly.
global $wp_query, $wp_the_query;
$wp_query     = new WP_Query( array( 'p' => $post->ID, 'post_type' => 'post' ) );
$wp_the_query = $wp_query;
setup_postdata( $post );

// Capture wp_head output.
ob_start();
do_action( 'wp_head' );
$head = ob_get_clean();

echo 'wp_head output length: ' . strlen( $head ) . ' bytes' . PHP_EOL . PHP_EOL;

// Check for JAT Search Intel meta tags.
$checks = array(
    'SI comment marker'   => '<!-- JAT Search Intel',
    'meta description'    => 'meta name="description"',
    'canonical link'      => 'rel="canonical"',
    'og:title'            => 'property="og:title"',
    'og:description'      => 'property="og:description"',
    'og:type'             => 'property="og:type"',
    'twitter:card'        => 'name="twitter:card"',
    'application/ld+json' => 'application/ld+json',
    'ai-summary'          => 'name="ai-summary"',
    'content-type meta'   => 'name="content-type"',
);

$pass = 0;
$fail = 0;
foreach ( $checks as $label => $needle ) {
    $found = ( strpos( $head, $needle ) !== false );
    echo ( $found ? 'PASS' : 'FAIL' ) . '  ' . $label . PHP_EOL;
    if ( $found ) {
        $pass++;
    } else {
        $fail++;
    }
}

$count = $pass + $fail;
echo PHP_EOL . '--- RESULT ---' . PHP_EOL;
echo "Passed: {$pass}/{$count}" . PHP_EOL;
echo 'Status: ' . ( $fail === 0 ? 'ALL PASS' : "{$fail} FAILED" ) . PHP_EOL;
