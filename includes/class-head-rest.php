<?php
/**
 * SEO head for headless sites.
 *
 * Adds two fields to published posts, pages and other public post types in
 * the WordPress REST API:
 *
 * - `jat_search_intel_head`: the HTML JAT Search Intel prints in <head>
 *   (title, meta description, robots, canonical, Open Graph, X card, schema).
 * - `jat_search_intel_head_json`: the same as structured data.
 *
 * Both are worked out only for single-item requests, or when asked for with
 * `_fields`, so list requests stay fast.
 *
 * @package JAT_Search_Intel
 * @since 3.8.0
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST head fields.
 */
class Head_Rest {

	public const FIELD_HTML = 'jat_search_intel_head';
	public const FIELD_JSON = 'jat_search_intel_head_json';

	/**
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		if ( empty( $settings['enable_seo'] ) ) {
			return;
		}
		add_action( 'rest_api_init', [ $this, 'register_fields' ] );
	}

	/**
	 * Register the fields on public post types.
	 */
	public function register_fields(): void {
		$types = array_diff( array_values( (array) get_post_types( [ 'public' => true, 'show_in_rest' => true ] ) ), [ 'attachment' ] );
		foreach ( $types as $type ) {
			register_rest_field(
				$type,
				self::FIELD_HTML,
				[
					'get_callback' => [ $this, 'get_html_field' ],
					'schema'       => [
						'description' => __( 'SEO tags for <head>, from JAT Search Intel.', 'jat-search-intel' ),
						'type'        => [ 'string', 'null' ],
						'context'     => [ 'view', 'edit' ],
						'readonly'    => true,
					],
				]
			);
			register_rest_field(
				$type,
				self::FIELD_JSON,
				[
					'get_callback' => [ $this, 'get_json_field' ],
					'schema'       => [
						'description' => __( 'SEO head as structured data, from JAT Search Intel.', 'jat-search-intel' ),
						'type'        => [ 'object', 'null' ],
						'context'     => [ 'view', 'edit' ],
						'readonly'    => true,
					],
				]
			);
		}
	}

	/**
	 * HTML field callback.
	 *
	 * @param array            $data    Prepared post data.
	 * @param string           $field   Field name.
	 * @param \WP_REST_Request $request Request.
	 * @return string|null
	 */
	public function get_html_field( $data, $field, $request ) {
		$post_id = (int) ( $data['id'] ?? 0 );
		if ( ! self::wanted( $request, self::FIELD_HTML ) || ! self::is_public( $post_id ) ) {
			return null;
		}
		return self::render_head( $post_id );
	}

	/**
	 * JSON field callback.
	 *
	 * @param array            $data    Prepared post data.
	 * @param string           $field   Field name.
	 * @param \WP_REST_Request $request Request.
	 * @return array|null
	 */
	public function get_json_field( $data, $field, $request ) {
		$post_id = (int) ( $data['id'] ?? 0 );
		if ( ! self::wanted( $request, self::FIELD_JSON ) || ! self::is_public( $post_id ) ) {
			return null;
		}
		return self::parse_head( self::render_head( $post_id ) );
	}

	/**
	 * Whether to work out a field for this request.
	 *
	 * @param mixed  $request Request.
	 * @param string $field   Field name.
	 * @return bool
	 */
	private static function wanted( $request, string $field ): bool {
		if ( ! $request instanceof \WP_REST_Request ) {
			return false;
		}
		$fields = $request->get_param( '_fields' );
		if ( $fields ) {
			$fields = is_array( $fields ) ? $fields : explode( ',', (string) $fields );
			return in_array( $field, array_map( 'trim', $fields ), true );
		}
		// Single item: /wp/v2/posts/123.
		return (bool) preg_match( '#/\d+/?$#', (string) $request->get_route() );
	}

	/**
	 * Only published posts without a password.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function is_public( int $post_id ): bool {
		$post = $post_id ? get_post( $post_id ) : null;
		return $post && 'publish' === $post->post_status && '' === (string) $post->post_password && is_post_type_viewable( $post->post_type );
	}

	/**
	 * Render JAT Search Intel's <head> output for a post.
	 *
	 * Sets the post up as the main query, runs only this plugin's wp_head
	 * callbacks, then restores everything.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function render_head( int $post_id ): string {
		global $wp_query, $wp_the_query, $post;

		$saved_query     = $wp_query;
		$saved_the_query = $wp_the_query;
		$saved_post      = $post;

		$type  = (string) get_post_type( $post_id );
		$args  = 'page' === $type ? [ 'page_id' => $post_id ] : [ 'p' => $post_id, 'post_type' => $type ];
		$query = new \WP_Query( $args + [ 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true ] );

		$wp_query     = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
		$wp_the_query = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
		if ( $query->have_posts() ) {
			$query->the_post();
		}

		ob_start();
		echo '<title>' . esc_html( wp_get_document_title() ) . "</title>\n";
		if ( function_exists( 'wp_robots' ) ) {
			wp_robots();
		}
		foreach ( self::own_head_callbacks() as $callback ) {
			call_user_func( $callback );
		}
		$html = (string) ob_get_clean();

		$wp_query     = $saved_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring.
		$wp_the_query = $saved_the_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring.
		$post         = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring.
		if ( $saved_post instanceof \WP_Post ) {
			setup_postdata( $saved_post );
		}

		return trim( $html );
	}

	/**
	 * This plugin's wp_head callbacks, in priority order.
	 *
	 * @return callable[]
	 */
	private static function own_head_callbacks(): array {
		global $wp_filter;
		$hook = $wp_filter['wp_head'] ?? null;
		if ( ! $hook || empty( $hook->callbacks ) ) {
			return [];
		}
		$priorities = $hook->callbacks;
		ksort( $priorities );
		$out = [];
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $item ) {
				$fn = $item['function'] ?? null;
				if ( is_array( $fn ) && isset( $fn[0] ) ) {
					$class = is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0];
					if ( 0 === strpos( $class, __NAMESPACE__ . '\\' ) ) {
						$out[] = $fn;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Head HTML as structured data.
	 *
	 * @param string $html Head HTML.
	 * @return array{title:string,description:string,canonical:string,robots:string,og:array<string,string>,twitter:array<string,string>,meta:array<string,string>,schema:array<int,mixed>}
	 */
	public static function parse_head( string $html ): array {
		$out = [
			'title'       => '',
			'description' => '',
			'canonical'   => '',
			'robots'      => '',
			'og'          => [],
			'twitter'     => [],
			'meta'        => [],
			'schema'      => [],
		];

		if ( preg_match( '#<title>(.*?)</title>#is', $html, $m ) ) {
			$out['title'] = html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		if ( preg_match( '#<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']#i', $html, $m ) ) {
			$out['canonical'] = html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		preg_match_all( '#<meta\s+([^>]+?)\s*/?>#i', $html, $tags );
		foreach ( $tags[1] as $attrs ) {
			preg_match_all( '#([a-z:-]+)=["\']([^"\']*)["\']#i', $attrs, $pairs, PREG_SET_ORDER );
			$a = [];
			foreach ( $pairs as $pair ) {
				$a[ strtolower( $pair[1] ) ] = html_entity_decode( $pair[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
			$key     = $a['property'] ?? ( $a['name'] ?? '' );
			$content = $a['content'] ?? '';
			if ( '' === $key ) {
				continue;
			}
			if ( 'description' === $key ) {
				$out['description'] = $content;
			} elseif ( 'robots' === $key ) {
				$out['robots'] = $content;
			} elseif ( 0 === strpos( $key, 'og:' ) || 0 === strpos( $key, 'article:' ) ) {
				$out['og'][ $key ] = $content;
			} elseif ( 0 === strpos( $key, 'twitter:' ) ) {
				$out['twitter'][ $key ] = $content;
			} else {
				$out['meta'][ $key ] = $content;
			}
		}

		preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $scripts );
		foreach ( $scripts[1] as $json ) {
			$decoded = json_decode( trim( $json ), true );
			if ( is_array( $decoded ) ) {
				$out['schema'][] = $decoded;
			}
		}

		return $out;
	}
}
