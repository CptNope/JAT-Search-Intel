<?php
/**
 * PHPUnit bootstrap for JAT Search Intel unit tests.
 *
 * Provides WordPress function stubs so unit tests can run
 * without a full WordPress installation.
 */

// Plugin constants
define( 'JAT_SEARCH_INTEL_VERSION', '1.1.0' );
define( 'JAT_SEARCH_INTEL_DIR', __DIR__ . '/../' );

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}
define( 'JAT_SEARCH_INTEL_URL', 'https://example.com/wp-content/plugins/jat-search-intel/' );
define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );

define( 'JAT_SEARCH_INTEL_BASENAME', 'jat-search-intel/jat-search-intel.php' );

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

function update_option( string $option, $value, $autoload = null ): bool {
    global $si_test_state;
    $si_test_state['options'][ $option ] = $value;
    return true;
}


function add_option( string $option, $value = "", $deprecated = "", $autoload = null ): bool {
    global $si_test_state;
    if ( isset( $si_test_state["options"][ $option ] ) ) {
        return false;
    }
    $si_test_state["options"][ $option ] = $value;
    $si_test_state["autoload"][ $option ] = $autoload;
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
    if ( is_object( $post_id ) ) {
        $post_id = $post_id->ID ?? 0;
    }
    $post_id = (int) $post_id;
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

function esc_attr__( string $text, string $domain = 'default' ): string {
    return esc_attr( $text );
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
    if ( is_object( $post_id ) ) {
        $post_id = $post_id->ID ?? 0;
    }
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
    global $si_test_state;
    return ! empty( $si_test_state['is_admin'] );
}

function is_front_page(): bool {
    return false;
}

function wp_get_document_title(): string {
    global $si_test_state;
    return $si_test_state['document_title'] ?? 'Test Page';
}

if ( ! function_exists( 'strip_shortcodes' ) ) {
    function strip_shortcodes( string $content ): string {
        return preg_replace( '/\[[^\]]+\]/', '', $content );
    }
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
    global $si_test_state;
    return ! in_array( $capability, (array) ( $si_test_state['denied_caps'] ?? [] ), true );
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

function wp_set_script_translations( string $handle, string $domain = 'default', string $path = '' ): bool {
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

function add_shortcode( string $tag, $callback ): void {
    global $si_test_state;
    $si_test_state['shortcodes'][ $tag ] = $callback;
}

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
    $result    = [];
    $post_type = $args['post_type'] ?? 'post';
    $limit     = $args['posts_per_page'] ?? -1;
    $offset    = $args['offset'] ?? 0;
    $fields    = $args['fields'] ?? '';

    $filtered = [];
    foreach ( $si_test_state['posts'] as $p ) {
        $status_filter = $args['post_status'] ?? '';
        if ( ! empty( $status_filter ) && $status_filter !== 'any' && ( $p['post_status'] ?? 'publish' ) !== $status_filter ) {
            continue;
        }
        $p_type = $p['post_type'] ?? 'post';
        if ( is_array( $post_type ) ? ! in_array( $p_type, $post_type, true ) : $p_type !== $post_type ) {
            continue;
        }
        $p['post_date_gmt']     = $p['post_date_gmt'] ?? gmdate( 'Y-m-d H:i:s' );
        $p['post_modified_gmt'] = $p['post_modified_gmt'] ?? gmdate( 'Y-m-d H:i:s' );
        $filtered[] = $p;
    }

    if ( $offset > 0 ) {
        $filtered = array_slice( $filtered, $offset );
    }
    if ( $limit > 0 ) {
        $filtered = array_slice( $filtered, 0, $limit );
    }

    foreach ( $filtered as $p ) {
        if ( 'ids' === $fields ) {
            $result[] = (int) $p['ID'];
        } else {
            $result[] = (object) $p;
        }
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

function get_post_types( $args = [], $output = 'names' ) {
    if ( $output === 'names' || ( isset( $args['public'] ) && $args['public'] ) ) {
        return [ 'post' => 'post', 'page' => 'page' ];
    }
    return [ 'post' => (object) [ 'name' => 'post', 'labels' => (object) [ 'name' => 'Posts' ] ] ];
}

function register_rest_route( string $namespace, string $route, array $args = [], bool $override = false ): void {}

function flush_rewrite_rules( bool $hard = true ): void {}

function plugin_dir_path( string $file ): string {
    return dirname( $file ) . '/';
}

function plugin_dir_url( string $file ): string {
    return 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function plugin_basename( string $file ): string {
    return 'jat-search-intel/' . basename( $file );
}

// ── WP_REST stubs ────────────────────────────────────────────────────────

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request implements \ArrayAccess {
        private $params = [];
        private $json_params = [];
        private $method = 'GET';

        public function __construct( string $method = 'GET', string $route = '' ) {
            $this->method = $method;
        }

        public function set_param( string $key, $value ): void {
            $this->params[ $key ] = $value;
        }

        public function get_param( string $key ) {
            return $this->params[ $key ] ?? null;
        }

        public function set_json_params( array $params ): void {
            $this->json_params = $params;
        }

        public function get_json_params(): array {
            return $this->json_params;
        }

        public function offsetExists( $offset ): bool {
            return isset( $this->params[ $offset ] );
        }

        #[\ReturnTypeWillChange]
        public function offsetGet( $offset ) {
            return $this->params[ $offset ] ?? null;
        }

        public function offsetSet( $offset, $value ): void {
            $this->params[ $offset ] = $value;
        }

        public function offsetUnset( $offset ): void {
            unset( $this->params[ $offset ] );
        }
    }
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public $data;
        public $status;

        public function __construct( $data = null, int $status = 200 ) {
            $this->data   = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status(): int {
            return $this->status;
        }
    }
}

// ── Global $wp stub ──────────────────────────────────────────────────────

global $wp;
$wp = (object) [ 'request' => '' ];

// ── Global $wpdb stub ───────────────────────────────────────────────────

global $wpdb;
$wpdb = new class {
    public $options   = 'wp_options';
    public $posts     = 'wp_posts';
    public $prefix    = 'wp_';
    public $insert_id = 0;

    /** @var array<string,array<int,array>> In-memory tables for testing */
    public $tables = [];
    private $auto_increment = [];

    public function esc_like( string $text ): string {
        return addcslashes( $text, '_%\\' );
    }

    public function prepare( string $query, ...$args ): string {
        $i = 0;
        return preg_replace_callback( '/%[sdi]/', function ( $m ) use ( $args, &$i ) {
            $value = $args[ $i++ ] ?? '';
            if ( '%i' === $m[0] ) {
                return '`' . $value . '`';
            }
            if ( '%d' === $m[0] ) {
                return (string) (int) $value;
            }
            return "'" . $value . "'";
        }, $query );
    }

    public function query( string $query ) {
        return 0;
    }

    public function get_results( string $query, $output = 'OBJECT' ): array {
        return [];
    }

    public function get_row( string $query, $output = 'OBJECT', int $y = 0 ) {
        return null;
    }

    public function get_var( string $query, int $x = 0, int $y = 0 ) {
        return null;
    }

    public function get_charset_collate(): string {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function insert( string $table, array $data, $format = null ) {
        if ( ! isset( $this->auto_increment[ $table ] ) ) {
            $this->auto_increment[ $table ] = 1;
        }
        $id = $this->auto_increment[ $table ]++;
        $data['id'] = $id;
        $this->tables[ $table ][ $id ] = $data;
        $this->insert_id = $id;
        return 1;
    }

    public function update( string $table, array $data, array $where, $format = null, $where_format = null ) {
        foreach ( $this->tables[ $table ] ?? [] as $id => $row ) {
            $match = true;
            foreach ( $where as $k => $v ) {
                if ( ( $row[ $k ] ?? null ) != $v ) {
                    $match = false;
                    break;
                }
            }
            if ( $match ) {
                $this->tables[ $table ][ $id ] = array_merge( $row, $data );
                return 1;
            }
        }
        return 0;
    }

    public function delete( string $table, array $where, $where_format = null ) {
        foreach ( $this->tables[ $table ] ?? [] as $id => $row ) {
            $match = true;
            foreach ( $where as $k => $v ) {
                if ( ( $row[ $k ] ?? null ) != $v ) {
                    $match = false;
                    break;
                }
            }
            if ( $match ) {
                unset( $this->tables[ $table ][ $id ] );
                return 1;
            }
        }
        return 0;
    }
};

// ── Block / Script / Style registration stubs ────────────────────────────────

global $si_registered_meta, $si_registered_blocks, $si_registered_scripts, $si_registered_styles;
$si_registered_meta    = [];
$si_registered_blocks  = [];
$si_registered_scripts = [];
$si_registered_styles  = [];

function register_post_meta( string $post_type, string $meta_key, array $args ): bool {
	global $si_registered_meta;
	$si_registered_meta[ $post_type ][ $meta_key ] = $args;
	return true;
}

function register_block_type( string $block_type, array $args = [] ) {
	global $si_registered_blocks;
	$si_registered_blocks[ $block_type ] = $args;
	return true;
}

function wp_register_script( string $handle, string $src = '', array $deps = [], $ver = false, $in_footer = false ): bool {
	global $si_registered_scripts;
	$si_registered_scripts[ $handle ] = [
		'src'  => $src,
		'deps' => $deps,
		'ver'  => $ver,
	];
	return true;
}

function wp_register_style( string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all' ): bool {
	global $si_registered_styles;
	$si_registered_styles[ $handle ] = [
		'src'  => $src,
		'deps' => $deps,
		'ver'  => $ver,
	];
	return true;
}

function sanitize_title( string $title ): string {
	return strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '-', $title ) );
}

function get_the_archive_title(): string {
	return 'Archive';
}

function get_user_by( string $field, $value ) {
	global $si_test_state;
	if ( 'email' === $field ) {
		foreach ( $si_test_state['users'] ?? [] as $user ) {
			if ( ( $user['user_email'] ?? '' ) === $value ) {
				return (object) $user;
			}
		}
	}
	return false;
}

function delete_user_meta( int $user_id, string $key ): bool {
	global $si_test_state;
	unset( $si_test_state['user_meta'][ $user_id ][ $key ] );
	return true;
}

function wp_add_privacy_policy_content( string $plugin_name, string $policy_text ): void {
	global $si_test_state;
	$si_test_state['privacy_policy'][ $plugin_name ] = $policy_text;
}

// ── Redirect / IndexNow / WooCommerce / Local Business stubs ─────────────────

function wp_redirect( string $location, int $status = 302 ): bool {
	global $si_test_state;
	$si_test_state['last_redirect'] = [ 'location' => $location, 'status' => $status ];
	return true;
}

function status_header( int $code ): void {
	global $si_test_state;
	$si_test_state['last_status_header'] = $code;
}

function nocache_headers(): void {}

function wp_parse_url( string $url, int $component = -1 ) {
	if ( $component === -1 ) {
		return parse_url( $url );
	}
	$map = [
		PHP_URL_SCHEME => 'scheme',
		PHP_URL_HOST   => 'host',
		PHP_URL_PATH   => 'path',
		PHP_URL_QUERY  => 'query',
	];
	$key = $map[ $component ] ?? null;
	if ( ! $key ) {
		return null;
	}
	$parts = parse_url( $url );
	return $parts[ $key ] ?? null;
}

function wp_salt( string $scheme = 'auth' ): string {
	return 'test-salt-' . $scheme;
}

function human_time_diff( int $from, int $to = 0 ): string {
	return '1 hour';
}

function number_format_i18n( $number, int $decimals = 0 ): string {
	return number_format( (float) $number, $decimals );
}

function paginate_links( array $args = [] ): string {
	return '<span class="page-numbers">1</span>';
}

function sanitize_key( string $key ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}

function wp_remote_get( string $url, array $args = [] ): array {
	global $si_test_state;
	return $si_test_state['remote_response'] ?? [
		'response' => [ 'code' => 200, 'message' => 'OK' ],
		'body'     => '',
	];
}

function wp_remote_retrieve_response_code( $response ): int {
	if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
		return (int) $response['response']['code'];
	}
	return 0;
}

function wp_remote_retrieve_response_message( $response ): string {
	if ( is_array( $response ) && isset( $response['response']['message'] ) ) {
		return $response['response']['message'];
	}
	return '';
}

function current_time( string $type, bool $gmt = false ): string {
	if ( 'mysql' === $type ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
	return (string) time();
}

function get_the_terms( int $post_id, string $taxonomy ) {
	global $si_test_state;
	return $si_test_state['post_terms'][ $post_id ][ $taxonomy ] ?? false;
}

function wp_get_attachment_url( int $attachment_id ) {
	return $attachment_id > 0 ? 'https://example.com/wp-content/uploads/image-' . $attachment_id . '.jpg' : false;
}

function wp_die( $message = '', $title = '', $args = [] ): void {
	// No-op in tests
}

function get_woocommerce_currency(): string {
	return 'USD';
}

function sanitize_textarea_field( string $str ): string {
	return trim( strip_tags( $str ) );
}

// ── WP-Cron stubs ────────────────────────────────────────────────────────────

function wp_next_scheduled( string $hook, array $args = [] ) {
	global $si_test_state;
	return $si_test_state['scheduled_events'][ $hook ] ?? false;
}

function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = [] ): bool {
	global $si_test_state;
	$si_test_state['scheduled_events'][ $hook ] = $timestamp;
	return true;
}

function wp_unschedule_event( int $timestamp, string $hook, array $args = [] ): bool {
	global $si_test_state;
	unset( $si_test_state['scheduled_events'][ $hook ] );
	return true;
}

// ── HTTP stubs ───────────────────────────────────────────────────────────────

function wp_remote_head( string $url, array $args = [] ): array {
	global $si_test_state;
	return $si_test_state['remote_head_response'] ?? $si_test_state['remote_response'] ?? [
		'response' => [ 'code' => 200, 'message' => 'OK' ],
		'body'     => '',
	];
}

// ── Taxonomy stubs ───────────────────────────────────────────────────────────

function get_object_taxonomies( $object_type, string $output = 'names' ): array {
	return [ 'category', 'post_tag' ];
}

function wp_remote_post( string $url, array $args = [] ): array {
	global $si_test_state;
	return $si_test_state['remote_post_response'] ?? [
		'response' => [ 'code' => 200, 'message' => 'OK' ],
		'body'     => '',
	];
}

function wp_remote_retrieve_body( $response ): string {
	if ( is_array( $response ) && isset( $response['body'] ) ) {
		return (string) $response['body'];
	}
	return '';
}

function url_to_postid( string $url ): int {
	return 0;
}

function wp_get_post_categories( int $post_id, array $args = [] ): array {
	$fields = $args['fields'] ?? 'ids';
	if ( $fields === 'names' ) {
		return [ 'Uncategorized' ];
	}
	return [ 1 ];
}

function get_edit_post_link( int $post_id = 0, string $context = 'display' ): ?string {
	return 'https://example.com/wp-admin/post.php?post=' . $post_id . '&action=edit';
}

// ── WP_Query stub ────────────────────────────────────────────────────────────

class WP_Query {
	public array $posts      = [];
	public int   $found_posts = 0;

	public function __construct( array $args = [] ) {
		$this->posts       = get_posts( $args );
		$this->found_posts = count( $this->posts );
	}

	public function have_posts(): bool {
		return ! empty( $this->posts );
	}
}

// ── Multisite stubs ──────────────────────────────────────────────────────────

function is_multisite(): bool {
	global $si_test_state;
	return $si_test_state['is_multisite'] ?? false;
}

function get_site_option( string $option, $default = false ) {
	global $si_test_state;
	return $si_test_state['site_options'][ $option ] ?? $default;
}

function update_site_option( string $option, $value ): bool {
	global $si_test_state;
	$si_test_state['site_options'][ $option ] = $value;
	return true;
}

function switch_to_blog( int $blog_id ): bool {
	return true;
}

function restore_current_blog(): bool {
	return true;
}

function get_sites( array $args = [] ): array {
	return [];
}

function get_home_url( int $blog_id = 0, string $path = '', string $scheme = '' ): string {
	return 'https://example.com' . $path;
}

function network_admin_url( string $path = '' ): string {
	return 'https://example.com/wp-admin/network/' . $path;
}

function wp_safe_redirect( string $location, int $status = 302, string $x_redirect_by = 'WordPress' ): void {}

// ── User / Role stubs ────────────────────────────────────────────────────────

function get_users( array $args = [] ): array {
	return [];
}

/**
 * Minimal WP_User stand-in for tests.
 */
class SI_Test_User {
	public int $ID;
	public string $user_login;
	public array $roles;
	public array $caps;

	public function __construct( array $data ) {
		$this->ID         = $data['ID'] ?? 0;
		$this->user_login = $data['user_login'] ?? '';
		$this->roles      = $data['roles'] ?? [];
		$this->caps       = $data['caps'] ?? [];
	}

	public function exists(): bool {
		return $this->ID > 0;
	}

	public function add_cap( string $cap ): void {
		$this->caps[ $cap ] = true;
		// Persist back to state.
		global $si_test_state;
		if ( isset( $si_test_state['users'][ $this->ID ] ) ) {
			$si_test_state['users'][ $this->ID ]['caps'][ $cap ] = true;
		}
	}

	public function remove_cap( string $cap ): void {
		unset( $this->caps[ $cap ] );
		global $si_test_state;
		if ( isset( $si_test_state['users'][ $this->ID ] ) ) {
			unset( $si_test_state['users'][ $this->ID ]['caps'][ $cap ] );
		}
	}
}

function get_userdata( int $user_id ) {
	global $si_test_state;
	$users = $si_test_state['users'] ?? [];
	if ( isset( $users[ $user_id ] ) ) {
		return new \SI_Test_User( $users[ $user_id ] );
	}
	return false;
}

function wp_get_current_user() {
	global $si_test_state;
	$uid = $si_test_state['current_user_id'] ?? 0;
	if ( $uid > 0 ) {
		$user = get_userdata( $uid );
		return $user ?: new \SI_Test_User( [ 'ID' => 0 ] );
	}
	return new \SI_Test_User( [ 'ID' => 0 ] );
}

function user_can( $user, string $cap ): bool {
	if ( $user instanceof \SI_Test_User ) {
		return ! empty( $user->caps[ $cap ] );
	}
	if ( is_object( $user ) && isset( $user->caps ) && is_array( $user->caps ) ) {
		return ! empty( $user->caps[ $cap ] );
	}
	return false;
}

function get_role( string $role ) {
	global $si_test_state;
	return $si_test_state['roles'][ $role ] ?? null;
}

function add_role( string $role, string $display_name, array $caps = [] ) {
	global $si_test_state;
	$si_test_state['roles'][ $role ] = (object) [
		'name'         => $role,
		'display_name' => $display_name,
		'capabilities' => $caps,
	];
	// add_cap / remove_cap methods via stdClass won't work, but good enough for construction tests.
	return $si_test_state['roles'][ $role ];
}

function remove_role( string $role ): void {
	global $si_test_state;
	unset( $si_test_state['roles'][ $role ] );
}

function wp_roles() {
	global $si_test_state;
	return (object) [ 'role_objects' => $si_test_state['role_objects'] ?? [] ];
}

function wp_clear_scheduled_hook( string $hook, array $args = [] ) {
	global $si_test_state;
	$si_test_state['cleared_hooks'][] = $hook;
	return 0;
}

function is_main_site( $site_id = null ): bool {
	return true;
}

if ( ! function_exists( 'delete_site_option' ) ) {
	function delete_site_option( string $option ): bool {
		global $si_test_state;
		unset( $si_test_state['site_options'][ $option ] );
		return true;
	}
}

function post_type_exists( string $post_type ): bool {
	return in_array( $post_type, [ 'post', 'page', 'product' ], true );
}

if ( ! function_exists( 'esc_xml' ) ) {
	function esc_xml( string $text ): string {
		return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}
}

function wp_attachment_is_image( $post = null ): bool {
	return true;
}

function get_attached_file( int $attachment_id ): string {
	return '/uploads/image-' . $attachment_id . '.jpg';
}

function wp_basename( string $path, string $suffix = '' ): string {
	return basename( $path, $suffix );
}

function is_feed( $feeds = '' ): bool {
	global $si_test_state;
	return ! empty( $si_test_state['is_feed'] );
}

function wpautop( string $text, bool $br = true ): string {
	return '<p>' . trim( $text ) . '</p>';
}

function shortcode_atts( array $pairs, $atts, string $shortcode = '' ): array {
	$atts = (array) $atts;
	$out  = [];
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}

function map_deep( $value, callable $callback ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = map_deep( $v, $callback );
		}
		return $value;
	}
	return $callback( $value );
}

function get_post_type_object( string $post_type ) {
	return (object) [ 'name' => $post_type, 'labels' => (object) [ 'name' => ucfirst( $post_type ) . 's' ] ];
}

function get_post_status( $post = null ) {
	return 'publish';
}

function wp_get_post_parent_id( $post = null ): int {
	return 0;
}

function is_attachment( $attachment = '' ): bool {
	return false;
}

function is_object_in_taxonomy( $object_type, string $taxonomy ): bool {
	return 'post' === $object_type && in_array( $taxonomy, [ 'category', 'post_tag' ], true );
}

// ── Object cache stubs ──────────────────────────────────────────────────────

function wp_using_ext_object_cache(): bool {
	global $si_test_state;
	return $si_test_state['ext_object_cache'] ?? false;
}

function wp_cache_get( string $key, string $group = '' ) {
	global $si_test_state;
	return $si_test_state['object_cache'][ $group . ':' . $key ] ?? false;
}

function wp_cache_set( string $key, $value, string $group = '', int $expiry = 0 ): bool {
	global $si_test_state;
	$si_test_state['object_cache'][ $group . ':' . $key ] = $value;
	return true;
}

function wp_cache_delete( string $key, string $group = '' ): bool {
	global $si_test_state;
	unset( $si_test_state['object_cache'][ $group . ':' . $key ] );
	return true;
}

function wp_generate_uuid4(): string {
	return sprintf(
		'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
		mt_rand( 0, 0xffff ),
		mt_rand( 0, 0x0fff ) | 0x4000,
		mt_rand( 0, 0x3fff ) | 0x8000,
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
	);
}

function sanitize_hex_color( string $color ): string {
	if ( preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $color ) ) {
		return $color;
	}
	return '';
}

// ── Post time stubs ──────────────────────────────────────────────────────────

function get_post_modified_time( string $format = 'U', bool $gmt = false, $post = null ): string {
	$p = null;
	if ( is_object( $post ) ) {
		$p = $post;
	} elseif ( is_int( $post ) ) {
		$p = get_post( $post );
	}
	if ( $p && ! empty( $p->post_modified ) ) {
		return $p->post_modified;
	}
	return gmdate( $format );
}

// ── Block detection stubs ────────────────────────────────────────────────────

function has_block( string $block_name, $post = null ): bool {
	if ( is_object( $post ) && ! empty( $post->post_content ) ) {
		return str_contains( $post->post_content, '<!-- wp:' . $block_name );
	}
	if ( is_int( $post ) || is_string( $post ) ) {
		$p = get_post( (int) $post );
		if ( $p && ! empty( $p->post_content ) ) {
			return str_contains( $p->post_content, '<!-- wp:' . $block_name );
		}
	}
	return false;
}

function has_blocks( $post = null ): bool {
	if ( is_int( $post ) || is_string( $post ) ) {
		$p = get_post( (int) $post );
		$content = $p->post_content ?? '';
	} elseif ( is_object( $post ) ) {
		$content = $post->post_content ?? '';
	} else {
		$content = '';
	}
	return str_contains( $content, '<!-- wp:' );
}

function _deprecated_function( string $function, string $version, string $replacement = '' ): void {
	// No-op in tests — the shortcode still renders, this just triggers a notice.
}

// ── Notifications stubs ──────────────────────────────────────────────────────

function wp_mail( string $to, string $subject, string $message, $headers = '', $attachments = [] ): bool {
	global $si_test_state;
	$si_test_state['last_email'] = [
		'to'      => $to,
		'subject' => $subject,
		'message' => $message,
	];
	return true;
}

function is_email( string $email ) {
	return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

function wp_add_dashboard_widget( string $widget_id, string $widget_name, callable $callback, $control_callback = null, $args = null, $context = 'normal', $priority = 'core' ): void {}

if ( ! function_exists( 'wp_get_post_tags' ) ) {
	function wp_get_post_tags( int $post_id = 0, array $args = [] ) {
		return [];
	}
}

if ( ! defined( 'LOGGED_IN_SALT' ) ) {
	define( 'LOGGED_IN_SALT', 'test-salt-for-unit-tests-only' );
}

// ── Autoloader ───────────────────────────────────────────────────────────────

spl_autoload_register( function ( string $class ) {
    $prefix = 'JAT_Search_Intel\\';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );
    $file     = JAT_SEARCH_INTEL_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_message() {
			return $this->message;
		}
		public function get_error_code() {
			return $this->code;
		}
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( string $tag, $priority = false ): bool {
		global $si_test_state;
		unset( $si_test_state['filters'][ $tag ] );
		return true;
	}
}

if ( ! function_exists( 'is_post_type_viewable' ) ) {
	function is_post_type_viewable( $post_type ): bool {
		return in_array( $post_type, [ 'post', 'page', 'product' ], true );
	}
}

if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( string $name, array $args ) {
		$GLOBALS['si_test_state']['abilities'][ $name ] = $args;
		return null;
	}
	function wp_register_ability_category( string $slug, array $args ) {
		$GLOBALS['si_test_state']['ability_categories'][ $slug ] = $args;
		return null;
	}
}

if ( ! function_exists( '__return_true' ) ) {
	function __return_true(): bool {
		return true;
	}
}

if ( ! function_exists( 'parse_blocks' ) ) {
	/**
	 * Minimal parse_blocks() for tests: top-level, non-nested blocks plus freeform runs.
	 */
	function parse_blocks( string $content ): array {
		$out    = [];
		$offset = 0;
		preg_match_all( '#<!-- wp:([a-z0-9/-]+)(\s+(\{.*?\}))?\s*(/)?-->(.*?)(?:<!-- /wp:\1 -->|(?=<!-- wp:)|$)#s', $content, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		foreach ( $m as $match ) {
			$start = $match[0][1];
			if ( $start > $offset ) {
				$out[] = [ 'blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => substr( $content, $offset, $start - $offset ) ];
			}
			$name  = $match[1][0];
			$name  = false === strpos( $name, '/' ) ? 'core/' . $name : $name;
			$out[] = [
				'blockName'   => $name,
				'attrs'       => isset( $match[3][0] ) && '' !== $match[3][0] ? (array) json_decode( $match[3][0], true ) : [],
				'innerBlocks' => [],
				'innerHTML'   => $match[5][0] ?? '',
			];
			$offset = $start + strlen( $match[0][0] );
		}
		if ( $offset < strlen( $content ) ) {
			$out[] = [ 'blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => substr( $content, $offset ) ];
		}
		return $out;
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $data ) {
		return is_string( $data ) && preg_match( '/^[aOs]:\d+:/', $data ) ? unserialize( $data ) : $data; // phpcs:ignore
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ): string {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
	function untrailingslashit( $value ): string {
		return rtrim( (string) $value, '/\\' );
	}
}

if ( ! function_exists( 'wp_slash' ) ) {
	function wp_slash( $value ) {
		return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( int $timestamp, string $hook, array $args = [] ): bool {
		$GLOBALS['si_test_state']['single_events'][ $hook ] = $timestamp;
		return true;
	}
}
