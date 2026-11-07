<?php
/**
 * Schema map: the whole site's structured data in one feed for AI agents.
 *
 * Instead of crawling every page to read its JSON-LD, an agent can read one
 * schema map (an XML list of feeds, advertised in robots.txt) and then each
 * feed: the Organization/Person and WebSite nodes plus the schema of every
 * public, indexable post of a type, a page at a time.
 *
 *   /wp-json/jat-search-intel/v1/schema-map            XML list of feeds
 *   /wp-json/jat-search-intel/v1/schema/{type}[/{page}] JSON-LD @graph
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide schema feeds.
 */
class Schema_Map {

	/** Posts per feed page. */
	public const PER_PAGE = 200;

	/** Bumped on every post change to invalidate cached feeds. */
	private const VERSION_OPTION = 'jat_search_intel_schema_map_version';

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ $this, 'serve_xml' ], 10, 4 );
	}

	/**
	 * Schema map URL.
	 *
	 * @return string
	 */
	public static function map_url(): string {
		return rest_url( 'jat-search-intel/v1/schema-map' );
	}

	/**
	 * Add the schema map to robots.txt so crawlers can find it.
	 *
	 * @param string $output robots.txt.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public static function robots_line( $output, $public ): string {
		$output = (string) $output;
		if ( ! $public ) {
			return $output;
		}
		$line = 'Sitemap: ' . self::map_url();
		return false !== strpos( $output, $line ) ? $output : rtrim( $output ) . "\n" . $line . "\n";
	}

	/**
	 * Invalidate cached feeds when content changes.
	 */
	public static function bump_version(): void {
		update_option( self::VERSION_OPTION, (string) microtime( true ), true );
	}

	/**
	 * Post types included in the feeds.
	 *
	 * @return string[]
	 */
	public function post_types(): array {
		$types = array_values( array_filter( (array) ( $this->settings['sitemap_post_types'] ?? [ 'post', 'page' ] ), 'is_post_type_viewable' ) );
		/**
		 * Filters the post types in the schema map.
		 *
		 * @param string[] $types Post type names.
		 */
		return array_values( (array) apply_filters( 'jat_search_intel_schema_map_post_types', $types ) );
	}

	/**
	 * Query args for the public, indexable posts of a type.
	 *
	 * @param string $post_type Post type.
	 * @return array<string,mixed>
	 */
	private function query_args( string $post_type ): array {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Results are cached per page.
		return [
			'post_type'    => $post_type,
			'post_status'  => 'publish',
			'has_password' => false,
			'orderby'      => 'modified',
			'order'        => 'DESC',
			'meta_query'   => [
				'relation' => 'AND',
				[
					'relation' => 'OR',
					[ 'key' => '_jat_search_intel_noindex', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_jat_search_intel_noindex', 'value' => '1', 'compare' => '!=' ],
				],
				[
					'relation' => 'OR',
					[ 'key' => '_jat_search_intel_ai_noindex', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_jat_search_intel_ai_noindex', 'value' => '1', 'compare' => '!=' ],
				],
			],
		];
		// phpcs:enable
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'jat-search-intel/v1',
			'/schema-map',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => [ $this, 'rest_map' ],
			]
		);
		register_rest_route(
			'jat-search-intel/v1',
			'/schema/(?P<type>[a-z0-9_-]+)(?:/(?P<page>\d+))?',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => [
					'type' => [ 'type' => 'string', 'required' => true ],
					'page' => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
				],
				'callback'            => [ $this, 'rest_feed' ],
			]
		);
	}

	/**
	 * The schema map as an XML urlset (sitemap format).
	 *
	 * @return string
	 */
	public function build_map(): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $this->post_types() as $type ) {
			$query = new \WP_Query( $this->query_args( $type ) + [ 'posts_per_page' => 1, 'fields' => 'ids' ] );
			$pages = (int) ceil( $query->found_posts / self::PER_PAGE );
			$first = $query->posts[0] ?? null;
			$last  = null === $first ? '' : get_post_modified_time( 'c', true, $first );
			for ( $page = 1; $page <= $pages; $page++ ) {
				$xml .= '<url contentType="structuredData/schema.org"><loc>' . esc_url( rest_url( "jat-search-intel/v1/schema/{$type}/{$page}" ) ) . '</loc>';
				if ( $last ) {
					$xml .= '<lastmod>' . esc_html( (string) $last ) . '</lastmod>';
				}
				$xml .= '<changefreq>daily</changefreq></url>' . "\n";
			}
		}
		return $xml . '</urlset>' . "\n";
	}

	/**
	 * One feed page: the site nodes plus each post's schema.
	 *
	 * @param string $post_type Post type.
	 * @param int    $page      Page (1-based).
	 * @return array<string,mixed>|null Null for an unknown type or an empty page.
	 */
	public function build_feed( string $post_type, int $page ): ?array {
		if ( ! in_array( $post_type, $this->post_types(), true ) ) {
			return null;
		}
		$cache_key = 'jat_search_intel_schema_' . md5( $post_type . '|' . $page . '|' . (string) get_option( self::VERSION_OPTION, '' ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$ids = get_posts(
			$this->query_args( $post_type ) + [
				'posts_per_page' => self::PER_PAGE,
				'paged'          => $page,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		if ( ! $ids ) {
			return null;
		}

		$schema = new Schema_Manager( $this->settings );
		$graph  = $schema->get_site_nodes();
		foreach ( $ids as $id ) {
			$graph[] = $schema->get_singular_schema( (int) $id );
		}

		$feed = [
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		];
		set_transient( $cache_key, $feed, HOUR_IN_SECONDS );
		return $feed;
	}

	/**
	 * GET /schema-map (served as XML by serve_xml()).
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_map(): \WP_REST_Response {
		$response = new \WP_REST_Response( $this->build_map() );
		$response->header( 'Content-Type', 'application/xml; charset=UTF-8' );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}

	/**
	 * GET /schema/{type}/{page}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_feed( \WP_REST_Request $request ) {
		$feed = $this->build_feed( sanitize_key( (string) $request['type'] ), max( 1, (int) $request['page'] ) );
		if ( null === $feed ) {
			return new \WP_Error( 'jsi_schema_not_found', __( 'No structured data here.', 'jat-search-intel' ), [ 'status' => 404 ] );
		}
		$response = new \WP_REST_Response( $feed );
		$response->header( 'Content-Type', 'application/ld+json; charset=UTF-8' );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}

	/**
	 * Send the schema map as raw XML instead of a JSON string.
	 *
	 * @param bool              $served  Whether the request was already served.
	 * @param mixed             $result  Response.
	 * @param \WP_REST_Request  $request Request.
	 * @param \WP_REST_Server   $server  Server.
	 * @return bool
	 */
	public function serve_xml( $served, $result, $request, $server ) {
		unset( $server );
		if ( $served || ! $request instanceof \WP_REST_Request || '/jat-search-intel/v1/schema-map' !== $request->get_route() ) {
			return $served;
		}
		if ( ! $result instanceof \WP_REST_Response || 200 !== $result->get_status() ) {
			return $served;
		}
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML built from escaped parts in build_map().
		return true;
	}
}
