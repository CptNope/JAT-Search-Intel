<?php
/**
 * PHPUnit bootstrap for Search Intel unit tests.
 *
 * Provides WordPress function stubs so unit tests can run
 * without a full WordPress installation.
 */

// Plugin constants
define( 'SEARCH_INTEL_VERSION', '1.1.0' );
define( 'SEARCH_INTEL_DIR', dirname( __DIR__ ) . '/' );
define( 'SEARCH_INTEL_URL', 'https://example.com/wp-content/plugins/search-intel/' );
define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

define( 'SEARCH_INTEL_BASENAME', 'search-intel/search-intel.php' );

// ── WordPress function stubs ────────────────────────────────────────────────

/**
 * Global store for mocked data.
 */
global $si_test_state;
$si_test_state = [
    'post_meta'    => [],
    'user_meta'    => [],
    'options'      => [],
    'transients'   => [],
    'actions'      => [],
    'filters'      => [],
    'posts'        => [],
    'current_user' => 1,
];

// ── Hooks ────────────────────────────────────────────────────────────────────

function add_action( string $tag, $callback, int $priority = 10, int $accepted_args = 1 ): void {
    global $si_test_state;
    $si_test_state['actions'][ $tag ][] = [
        'callback'      => $callback,
        'priority'      => $priority,
        'accepted_args' => $accepted_args,
    ];
}

function add_filter( string $tag, $callback, int $priority = 10, int $accepted_args = 1 ): void {
    global $si_test_state;
    $si_test_state['filters'][ $tag ][] = [
        'callback'      => $callback,
        'priority'      => $priority,
        'accepted_args' => $accepted_args,
    ];
}

function remove_action( string $tag, $callback, int $priority = 10 ): bool {
    return true;
}

function apply_filters( string $tag, $value, ...$args ) {
    global $si_test_state;
    if ( ! empty( $si_test_state['filters'][ $tag ] ) ) {
        foreach ( $si_test_state['filters'][ $tag ] as $filter ) {
            $value = call_user_func( $filter['callback'], $value, ...$args );
        }
    }
    return $value;
}

function do_action( string $tag, ...$args ): void {}

function has_filter( string $tag, $callback = false ) {
    global $si_test_state;
    return ! empty( $si_test_state['filters'][ $tag ] );
}

// ── Post Meta ────────────────────────────────────────────────────────────────

function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
    global $si_test_state;
    if ( $key ) {
        $value = $si_test_state['post_meta'][ $post_id ][ $key ] ?? '';
        return $single ? $value : (array) $value;
    }
    return $si_test_state['post_meta'][ $post_id ] ?? [];
}

function update_post_meta( int $post_id, string $key, $value ): bool {
    global $si_test_state;
    $si_test_state['post_meta'][ $post_id ][ $key ] = $value;
    return true;
}

function delete_post_meta( int $post_id, string $key ): bool {
    global $si_test_state;
    unset( $si_test_state['post_meta'][ $post_id ][ $key ] );
    return true;
}

// ── User Meta ────────────────────────────────────────────────────────────────

function get_user_meta( int $user_id, string $key = '', bool $single = false ) {
    global $si_test_state;
    $value = $si_test_state['user_meta'][ $user_id ][ $key ] ?? '';
    return $single ? $value : (array) $value;
}

function update_user_meta( int $user_id, string $key, $value ): bool {
    global $si_test_state;
    $si_test_state['user_meta'][ $user_id ][ $key ] = $value;
    return true;
}

// ── Options ──────────────────────────────────────────────────────────────────

function get_option( string $option, $default = false ) {
    global $si_test_state;
    return $si_test_state['options'][ $option ] ?? $default;
}

function update_option( string $option, $value ): bool {
    global $si_test_state;
    $si_test_state['options'][ $option ] = $value;
    return true;
}

function delete_option( string $option ): bool {
    global $si_test_state;
    unset( $si_test_state['options'][ $option ] );
    return true;
}

// ── Transients ───────────────────────────────────────────────────────────────

function get_transient( string $key ) {
    global $si_test_state;
    return $si_test_state['transients'][ $key ] ?? false;
}

function set_transient( string $key, $value, int $expiration = 0 ): bool {
    global $si_test_state;
    $si_test_state['transients'][ $key ] = $value;
    return true;
}

function delete_transient( string $key ): bool {
    global $si_test_state;
    unset( $si_test_state['transients'][ $key ] );
    return true;
}

// ── Posts ─────────────────────────────────────────────────────────────────────

function get_post( $post_id = null ) {
    global $si_test_state;
    if ( isset( $si_test_state['posts'][ $post_id ] ) ) {
        return (object) $si_test_state['posts'][ $post_id ];
    }
    return null;
}

function get_post_field( string $field, $post_id = 0 ) {
    global $si_test_state;
    if ( isset( $si_test_state['posts'][ $post_id ][ $field ] ) ) {
        return $si_test_state['posts'][ $post_id ][ $field ];
    }
    return '';
}

function get_the_ID(): int {
    return 1;
}

function get_queried_object_id(): int {
    return 1;
}

function get_queried_object() {
    return get_post( 1 );
}

function wp_is_post_revision( $post ): bool {
    return false;
}

function wp_is_post_autosave( $post ): bool {
    return false;
}

// ── Sanitization ─────────────────────────────────────────────────────────────

function sanitize_text_field( $str ): string {
    return trim( strip_tags( (string) $str ) );
}

function wp_kses_post( string $data ): string {
    return $data; // Simplified for testing
}

function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
    return strip_tags( $text );
}

function wp_trim_words( string $text, int $num_words = 55, string $more = '&hellip;' ): string {
    $words = explode( ' ', $text );
    if ( count( $words ) > $num_words ) {
        return implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
    }
    return $text;
}

function absint( $value ): int {
    return abs( (int) $value );
}

function wp_unslash( $value ) {
    if ( is_string( $value ) ) {
        return stripslashes( $value );
    }
    return $value;
}

function esc_html( string $text ): string {
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( string $text ): string {
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( string $url ): string {
    return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
}

function esc_url_raw( string $url ): string {
    return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
}

function esc_textarea( string $text ): string {
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function sanitize_email( string $email ): string {
    return filter_var( $email, FILTER_SANITIZE_EMAIL ) ?: '';
}

function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
    return json_encode( $data, $options, $depth );
}

// ── Internationalization ─────────────────────────────────────────────────────

function __( string $text, string $domain = 'default' ): string {
    return $text;
}

function esc_html__( string $text, string $domain = 'default' ): string {
    return esc_html( $text );
}

function esc_html_e( string $text, string $domain = 'default' ): void {
    echo esc_html( $text );
}

function esc_attr_e( string $text, string $domain = 'default' ): void {
    echo esc_attr( $text );
}

// ── URL / Site ───────────────────────────────────────────────────────────────

function home_url( string $path = '' ): string {
    return 'https://example.com' . $path;
}

function site_url( string $path = '' ): string {
    return 'https://example.com' . $path;
}

function admin_url( string $path = '' ): string {
    return 'https://example.com/wp-admin/' . $path;
}

function get_permalink( $post_id = 0 ): string {
    return 'https://example.com/?p=' . (int) $post_id;
}

function get_bloginfo( string $show ): string {
    $info = [
        'name'        => 'Test Site',
        'description' => 'A test site for unit testing',
        'url'         => 'https://example.com',
    ];
    return $info[ $show ] ?? '';
}

function is_singular( $post_types = '' ): bool {
    return true;
}

function is_admin(): bool {
    return false;
}

function is_front_page(): bool {
    return false;
}

function is_home(): bool {
    return false;
}

function is_archive(): bool {
    return false;
}

function is_search(): bool {
    return false;
}

function is_404(): bool {
    return false;
}

function is_category(): bool {
    return false;
}

function is_tag(): bool {
    return false;
}

function is_author(): bool {
    return false;
}

function is_page(): bool {
    return false;
}

function is_single(): bool {
    return true;
}

// ── Capabilities ─────────────────────────────────────────────────────────────

function current_user_can( string $capability, ...$args ): bool {
    return true;
}

function get_current_user_id(): int {
    global $si_test_state;
    return $si_test_state['current_user'];
}

// ── Misc ─────────────────────────────────────────────────────────────────────

function get_the_author_meta( string $field, int $user_id = 0 ): string {
    return 'Test Author';
}

function get_the_date( string $format = '', $post_id = 0 ): string {
    return date( $format ?: 'Y-m-d' );
}

function get_the_modified_date( string $format = '', $post_id = 0 ): string {
    return date( $format ?: 'Y-m-d' );
}

function get_the_title( $post_id = 0 ): string {
    $post = get_post( $post_id );
    return $post ? $post->post_title : '';
}

function get_the_excerpt( $post_id = 0 ): string {
    $post = get_post( $post_id );
    return $post ? ( $post->post_excerpt ?? '' ) : '';
}

function single_cat_title( string $prefix = '', bool $display = true ): string {
    return 'Test Category';
}

function single_tag_title( string $prefix = '', bool $display = true ): string {
    return 'Test Tag';
}

function get_query_var( string $var, $default = '' ) {
    return $default;
}

function wp_nonce_field( string $action, string $name = '_wpnonce', bool $referer = true, bool $echo = true ): string {
    return '<input type="hidden" name="' . esc_attr( $name ) . '" value="test_nonce" />';
}

function wp_verify_nonce( string $nonce, string $action ): bool {
    return true;
}

function check_ajax_referer( string $action, string $query_arg = '' ): bool {
    return true;
}

function wp_send_json_success( $data = null, int $status = 200 ): void {
    // No-op in tests
}

function wp_send_json_error( $data = null, int $status = 400 ): void {
    // No-op in tests
}


function wp_parse_args( $args, array $defaults = [] ): array {
    if ( is_string( $args ) ) {
        parse_str( $args, $args );
    }
    return array_merge( $defaults, (array) $args );
}

function load_plugin_textdomain( string $domain, $deprecated = false, string $plugin_rel_path = '' ): bool {
    return true;
}

function add_menu_page( string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', string $icon_url = '', $position = null ): string {
    return '';
}

function add_submenu_page( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null ): string {
    return '';
}

function register_setting( string $option_group, string $option_name, $args = [] ): void {}

function wp_enqueue_style( string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all' ): void {}

function wp_enqueue_script( string $handle, string $src = '', array $deps = [], $ver = false, $in_footer = false ): void {}

function wp_localize_script( string $handle, string $object_name, array $l10n ): bool {
    return true;
}

function wp_enqueue_media( array $args = [] ): void {}

function wp_create_nonce( string $action ): string {
    return 'test_nonce_' . $action;
}

function add_meta_box( string $id, string $title, $callback, $screen = null, string $context = 'advanced', string $priority = 'default', $callback_args = null ): void {}

function settings_fields( string $option_group ): void {}

function do_settings_sections( string $page ): void {}

function submit_button( string $text = '', string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null ): void {}

function add_shortcode( string $tag, $callback ): void {}

function do_shortcode( string $content ): string {
    return $content;
}

function get_locale(): string {
    return 'en_US';
}

function get_post_type( $post_id = null ): string {
    global $si_test_state;
    return $si_test_state['posts'][ $post_id ]['post_type'] ?? 'post';
}

function get_posts( array $args = [] ): array {
    global $si_test_state;
    $result = [];
    foreach ( $si_test_state['posts'] as $p ) {
        // Ensure date fields exist
        $p['post_date_gmt']     = $p['post_date_gmt'] ?? gmdate( 'Y-m-d H:i:s' );
        $p['post_modified_gmt'] = $p['post_modified_gmt'] ?? gmdate( 'Y-m-d H:i:s' );
        $result[] = (object) $p;
    }
    return $result;
}

function get_ancestors( int $object_id, string $object_type = '' ): array {
    return [];
}

function get_term( $term, string $taxonomy = '' ) {
    return (object) [ 'term_id' => $term, 'name' => 'Test Term', 'description' => '' ];
}

function get_term_link( $term, string $taxonomy = '' ): string {
    $id = is_object( $term ) ? $term->term_id : (int) $term;
    return 'https://example.com/term/' . $id;
}

function get_post_ancestors( int $post_id ): array {
    return [];
}

function has_post_thumbnail( $post_id = null ): bool {
    return false;
}

function get_the_post_thumbnail_url( $post_id = null, string $size = 'full' ): string {
    return '';
}

function wp_get_attachment_image_src( int $attachment_id, $size = 'full' ) {
    return false;
}

function get_post_thumbnail_id( $post_id = null ): int {
    return 0;
}

function get_attached_media( string $type, $post_id = 0 ): array {
    return [];
}

function add_query_arg( ...$args ): string {
    if ( count( $args ) === 2 ) {
        return $args[1] . '?' . http_build_query( $args[0] );
    }
    return $args[2] ?? '' . '?' . $args[0] . '=' . $args[1];
}

function get_author_posts_url( int $author_id ): string {
    return 'https://example.com/author/' . $author_id;
}

function get_avatar_url( $id_or_email, array $args = [] ): string {
    return 'https://example.com/avatar.jpg';
}

function wp_list_pluck( array $list, string $field ): array {
    return array_column( $list, $field );
}

function is_tax(): bool {
    return false;
}

function is_date(): bool {
    return false;
}

function is_post_type_archive(): bool {
    return false;
}

function single_term_title( string $prefix = '', bool $display = true ): string {
    return 'Test Term';
}

function get_the_author(): string {
    return 'Test Author';
}

function post_type_archive_title( string $prefix = '', bool $display = true ): string {
    return 'Archive';
}

function get_the_category( int $post_id = 0 ): array {
    return [ (object) [ 'name' => 'Uncategorized', 'term_id' => 1 ] ];
}

function get_the_tags( int $post_id = 0 ) {
    return false;
}

function get_search_query( bool $escaped = true ): string {
    return '';
}

function wp_count_posts( string $type = 'post' ) {
    return (object) [ 'publish' => 5 ];
}

function get_pages( array $args = [] ): array {
    return [];
}

function get_categories( array $args = [] ): array {
    return [];
}

function get_category_link( int $cat_id ): string {
    return 'https://example.com/category/' . $cat_id;
}

function get_page_by_path( string $path ) {
    return null;
}

function wp_count_terms( array $args = [] ) {
    return 0;
}

function get_taxonomies( array $args = [], string $output = 'names' ) {
    return [];
}

function taxonomy_exists( string $taxonomy ): bool {
    return in_array( $taxonomy, [ 'category', 'post_tag' ], true );
}

function get_terms( array $args = [] ): array {
    return [];
}

function is_wp_error( $thing ): bool {
    return false;
}

function selected( $selected, $current = true, bool $echo = true ): string {
    $result = $selected == $current ? ' selected="selected"' : '';
    if ( $echo ) echo $result;
    return $result;
}

function checked( $checked, $current = true, bool $echo = true ): string {
    $result = $checked == $current ? ' checked="checked"' : '';
    if ( $echo ) echo $result;
    return $result;
}

function get_current_screen() {
    return null;
}

function is_plugin_active( string $plugin ): bool {
    global $si_test_state;
    return in_array( $plugin, $si_test_state['active_plugins'] ?? [], true );
}

function update_meta_cache( string $meta_type, array $object_ids ): bool {
    // In tests the meta is already in $si_test_state — this is a no-op.
    return true;
}

function attachment_url_to_postid( string $url ): int {
    return 0;
}

function wp_get_attachment_metadata( int $attachment_id ): array {
    return [ 'width' => 1200, 'height' => 630 ];
}

// ── Global $wp stub ──────────────────────────────────────────────────────

global $wp;
$wp = (object) [ 'request' => '' ];

// ── Autoloader ───────────────────────────────────────────────────────────────

spl_autoload_register( function ( string $class ) {
    $prefix = 'Search_Intel\\';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );
    $file     = SEARCH_INTEL_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );
