<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO plugin migration engine.
 *
 * Moves titles, descriptions, focus keyphrases, robots settings, canonicals,
 * social fields, custom schema, primary categories, term SEO, site-wide
 * settings and redirects from Yoast SEO, Rank Math, All in One SEO, SEOPress
 * and The SEO Framework into JAT Search Intel.
 *
 * All in One SEO 4 and later keep their data in their own tables
 * (`aioseo_posts`, `aioseo_terms`, `aioseo_redirects`) and the `aioseo_options`
 * option, so those are read directly; the older `_aioseo_*` post meta is only
 * used when the tables are missing.
 *
 * Title and description variables are converted to JAT Search Intel's own
 * (`%title%`, `%sep%`, `%sitename%` ...) so imported titles never print raw
 * tags such as `#site_title` or `%%sitename%%`.
 *
 * @since 3.0.0
 */
class Migration {

	/** @var array */
	private $settings;

	/** Rows read per query from the source tables. */
	private const BATCH = 500;

	/**
	 * Source plugin definitions.
	 *
	 * `meta` maps a JAT Search Intel field to the source plugin's post meta key.
	 * Boolean fields (noindex, nofollow) are read with is_true_flag().
	 *
	 * @var array<string,array>
	 */
	private const SOURCES = [
		'yoast'    => [
			'label'  => 'Yoast SEO',
			'detect' => 'wordpress-seo/wp-seo.php',
			'meta'   => [
				'title'               => '_yoast_wpseo_title',
				'description'         => '_yoast_wpseo_metadesc',
				'keyword'             => '_yoast_wpseo_focuskw',
				'noindex'             => '_yoast_wpseo_meta-robots-noindex',
				'nofollow'            => '_yoast_wpseo_meta-robots-nofollow',
				'canonical'           => '_yoast_wpseo_canonical',
				'social_title'        => '_yoast_wpseo_opengraph-title',
				'social_description'  => '_yoast_wpseo_opengraph-description',
				'social_image'        => '_yoast_wpseo_opengraph-image',
				'twitter_title'       => '_yoast_wpseo_twitter-title',
				'twitter_description' => '_yoast_wpseo_twitter-description',
				'twitter_image'       => '_yoast_wpseo_twitter-image',
				'primary_category'    => '_yoast_wpseo_primary_category',
			],
			'options' => [
				'redirects' => 'wpseo-premium-redirects-base',
			],
		],
		'rankmath' => [
			'label'  => 'Rank Math',
			'detect' => 'seo-by-rank-math/rank-math.php',
			'meta'   => [
				'title'               => 'rank_math_title',
				'description'         => 'rank_math_description',
				'keyword'             => 'rank_math_focus_keyword',
				'noindex'             => 'rank_math_robots',
				'nofollow'            => 'rank_math_robots',
				'canonical'           => 'rank_math_canonical_url',
				'social_title'        => 'rank_math_facebook_title',
				'social_description'  => 'rank_math_facebook_description',
				'social_image'        => 'rank_math_facebook_image',
				'twitter_title'       => 'rank_math_twitter_title',
				'twitter_description' => 'rank_math_twitter_description',
				'primary_category'    => 'rank_math_primary_category',
			],
			'options' => [
				'redirects_table' => 'rank_math_redirections',
			],
		],
		'aioseo'   => [
			'label'  => 'All in One SEO',
			'detect' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
			// Legacy (AIOSEO 3 and earlier) post meta, used only without the aioseo_posts table.
			'meta'   => [
				'title'              => '_aioseo_title',
				'description'        => '_aioseo_description',
				'noindex'            => '_aioseo_noindex',
				'nofollow'           => '_aioseo_nofollow',
				'canonical'          => '_aioseo_canonical_url',
				'social_title'       => '_aioseo_og_title',
				'social_description' => '_aioseo_og_description',
				'social_image'       => '_aioseo_og_image',
				'twitter_title'      => '_aioseo_twitter_title',
			],
			'options' => [],
		],
		'seopress' => [
			'label'  => 'SEOPress',
			'detect' => 'wp-seopress/seopress.php',
			'meta'   => [
				'title'               => '_seopress_titles_title',
				'description'         => '_seopress_titles_desc',
				'keyword'             => '_seopress_analysis_target_kw',
				'noindex'             => '_seopress_robots_index',
				'nofollow'            => '_seopress_robots_follow',
				'canonical'           => '_seopress_robots_canonical',
				'social_title'        => '_seopress_social_fb_title',
				'social_description'  => '_seopress_social_fb_desc',
				'social_image'        => '_seopress_social_fb_img',
				'twitter_title'       => '_seopress_social_twitter_title',
				'twitter_description' => '_seopress_social_twitter_desc',
				'twitter_image'       => '_seopress_social_twitter_img',
				'primary_category'    => '_seopress_robots_primary_cat',
			],
			'options' => [],
		],
		'tsf'      => [
			'label'  => 'The SEO Framework',
			'detect' => 'autodescription/autodescription.php',
			'meta'   => [
				'title'              => '_genesis_title',
				'description'        => '_genesis_description',
				'noindex'            => '_genesis_noindex',
				'nofollow'           => '_genesis_nofollow',
				'canonical'          => '_genesis_canonical_uri',
				'social_title'       => '_open_graph_title',
				'social_description' => '_open_graph_description',
				'social_image'       => '_social_image_url',
				'twitter_title'      => '_twitter_title',
				'twitter_description'=> '_twitter_description',
			],
			'options' => [],
		],
	];

	/** JAT Search Intel field => post meta key. */
	private const TARGETS = [
		'title'               => '_jat_search_intel_title',
		'description'         => '_jat_search_intel_description',
		'keyword'             => '_jat_search_intel_focus_keyword',
		'secondary_keywords'  => '_jat_search_intel_secondary_keywords',
		'noindex'             => '_jat_search_intel_noindex',
		'nofollow'            => '_jat_search_intel_nofollow',
		'canonical'           => '_jat_search_intel_canonical',
		'social_title'        => '_jat_search_intel_social_title',
		'social_description'  => '_jat_search_intel_social_description',
		'social_image'        => '_jat_search_intel_social_image',
		'twitter_title'       => '_jat_search_intel_twitter_title',
		'twitter_description' => '_jat_search_intel_twitter_description',
		'twitter_image'       => '_jat_search_intel_twitter_image',
		'custom_schema'       => '_jat_search_intel_custom_schema',
		'primary_category'    => '_jat_search_intel_primary_category',
	];

	/** Fields that may contain title/description variables. */
	private const TEXT_WITH_VARIABLES = [ 'title', 'description', 'social_title', 'social_description', 'twitter_title', 'twitter_description' ];

	/** Fields stored as URLs. */
	private const URL_FIELDS = [ 'canonical', 'social_image', 'twitter_image' ];

	/** Variables JAT Search Intel understands (see Title_Engine::resolve_variables()). */
	public const KNOWN_TOKENS = [ '%title%', '%sitename%', '%tagline%', '%sep%', '%category%', '%tag%', '%date%', '%author%', '%page%', '%excerpt%', '%search_query%' ];

	/**
	 * Source variable => JAT Search Intel variable.
	 *
	 * Anything not listed is dropped, so an import never shows raw tags.
	 *
	 * @var array<string,array<string,string>>
	 */
	private const VARIABLE_MAP = [
		'aioseo'   => [
			'#post_title'       => '%title%',
			'#page_title'       => '%title%',
			'#archive_title'    => '%title%',
			'#taxonomy_title'   => '%category%',
			'#category_title'   => '%category%',
			'#categories'       => '%category%',
			'#tag_title'        => '%tag%',
			'#site_title'       => '%sitename%',
			'#tagline'          => '%tagline%',
			'#separator_sa'     => '%sep%',
			'#author_name'      => '%author%',
			'#author_first_name'=> '%author%',
			'#post_date'        => '%date%',
			'#current_date'     => '%date%',
			'#page_number'      => '%page%',
			'#post_excerpt'     => '%excerpt%',
			'#post_excerpt_only'=> '%excerpt%',
			'#search_term'      => '%search_query%',
		],
		'yoast'    => [
			'%%title%%'            => '%title%',
			'%%sitename%%'         => '%sitename%',
			'%%sitedesc%%'         => '%tagline%',
			'%%sep%%'              => '%sep%',
			'%%category%%'         => '%category%',
			'%%primary_category%%' => '%category%',
			'%%term_title%%'       => '%category%',
			'%%tag%%'              => '%tag%',
			'%%date%%'             => '%date%',
			'%%name%%'             => '%author%',
			'%%page%%'             => '%page%',
			'%%pagenumber%%'       => '%page%',
			'%%excerpt%%'          => '%excerpt%',
			'%%excerpt_only%%'     => '%excerpt%',
			'%%searchphrase%%'     => '%search_query%',
		],
		'rankmath' => [
			'%title%'           => '%title%',
			'%sitename%'        => '%sitename%',
			'%sitedesc%'        => '%tagline%',
			'%sep%'             => '%sep%',
			'%category%'        => '%category%',
			'%primary_category%'=> '%category%',
			'%term%'            => '%category%',
			'%tag%'             => '%tag%',
			'%date%'            => '%date%',
			'%currentdate%'     => '%date%',
			'%name%'            => '%author%',
			'%page%'            => '%page%',
			'%excerpt%'         => '%excerpt%',
			'%excerpt_only%'    => '%excerpt%',
			'%search_query%'    => '%search_query%',
		],
		'seopress' => [
			'%%post_title%%'      => '%title%',
			'%%sitetitle%%'       => '%sitename%',
			'%%tagline%%'         => '%tagline%',
			'%%sep%%'             => '%sep%',
			'%%_category_title%%' => '%category%',
			'%%post_category%%'   => '%category%',
			'%%tag_title%%'       => '%tag%',
			'%%post_tag%%'        => '%tag%',
			'%%post_date%%'       => '%date%',
			'%%currentdate%%'     => '%date%',
			'%%post_author%%'     => '%author%',
			'%%current_pagination%%' => '%page%',
			'%%post_excerpt%%'    => '%excerpt%',
			'%%search_keywords%%' => '%search_query%',
		],
		'tsf'      => [],
	];

	/**
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_jat_search_intel_migration_run', [ $this, 'ajax_run_migration' ] );
		add_action( 'wp_ajax_jat_search_intel_migration_dryrun', [ $this, 'ajax_dry_run' ] );
	}

	// ── Variables ────────────────────────────────────────────────────────────

	/**
	 * Convert another plugin's title/description variables to ours.
	 *
	 * Unknown variables are removed and whitespace is tidied, so the result
	 * never contains raw tags.
	 *
	 * @param string $text   Source text.
	 * @param string $source Source key.
	 * @return string
	 */
	public static function convert_variables( string $text, string $source ): string {
		if ( '' === $text ) {
			return '';
		}

		$map = self::VARIABLE_MAP[ $source ] ?? [];

		if ( 'aioseo' === $source ) {
			// Longest tags first so #post_excerpt_only is not read as #post_excerpt.
			uksort( $map, static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			} );
			// AIOSEO tags can carry arguments: #custom_field-name, #post_date-F j, Y.
			$text = (string) preg_replace_callback(
				'/#[a-z_]+(?:-[^\s#]*)?/i',
				static function ( $m ) use ( $map ) {
					$tag = strtolower( $m[0] );
					foreach ( $map as $from => $to ) {
						if ( 0 === strpos( $tag, $from ) && ( strlen( $tag ) === strlen( $from ) || '-' === $tag[ strlen( $from ) ] ) ) {
							return $to;
						}
					}
					return '';
				},
				$text
			);
		} elseif ( in_array( $source, [ 'yoast', 'seopress' ], true ) ) {
			$text = (string) preg_replace_callback(
				'/%%[a-z0-9_]+(?:\([^)]*\))?%%/i',
				static function ( $m ) use ( $map ) {
					return $map[ strtolower( $m[0] ) ] ?? '';
				},
				$text
			);
		} elseif ( 'rankmath' === $source ) {
			$text = (string) preg_replace_callback(
				'/%[a-z0-9_]+(?:\([^)]*\))?%/i',
				static function ( $m ) use ( $map ) {
					return $map[ strtolower( $m[0] ) ] ?? '';
				},
				$text
			);
		}

		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );

		// Separators left dangling by a removed variable ("Title | " or "%sep% %sep%").
		$text = (string) preg_replace( '/(%sep%\s*){2,}/', '%sep% ', $text );
		$text = (string) preg_replace( '/^\s*%sep%\s*|\s*%sep%\s*$/', '', $text );

		return trim( $text );
	}

	// ── Detection ────────────────────────────────────────────────────────────

	/**
	 * Detect which SEO plugins have data available for migration.
	 *
	 * @return array Detected source keys with post counts.
	 */
	public function detect_sources(): array {
		$detected = [];

		foreach ( self::SOURCES as $key => $source ) {
			if ( 'aioseo' === $key && $this->aioseo_has_tables() ) {
				$count = $this->count_aioseo_posts();
			} else {
				$count = $this->count_posts_with_meta( array_values( array_unique( $source['meta'] ) ) );
			}

			if ( $count > 0 ) {
				$detected[ $key ] = [
					'label' => $source['label'],
					'posts' => $count,
					'meta'  => array_keys( $source['meta'] ),
				];
			}
		}

		return $detected;
	}

	/**
	 * Post types to migrate: every public type except attachments.
	 *
	 * @return string[]
	 */
	private function post_types(): array {
		$types = array_values( (array) get_post_types( [ 'public' => true ], 'names' ) );
		$types = array_values( array_diff( $types, [ 'attachment' ] ) );
		return $types ?: [ 'post', 'page' ];
	}

	/**
	 * All post IDs that may hold SEO data.
	 *
	 * @return int[]
	 */
	private function all_post_ids(): array {
		return array_map( 'intval', get_posts( [
			'post_type'      => $this->post_types(),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] ) );
	}

	/**
	 * Count posts that have at least one of the given meta keys.
	 *
	 * @param array $meta_keys Meta keys to check.
	 * @return int
	 */
	private function count_posts_with_meta( array $meta_keys ): int {
		$count = 0;
		foreach ( $this->all_post_ids() as $post_id ) {
			foreach ( $meta_keys as $meta_key ) {
				$val = get_post_meta( $post_id, $meta_key, true );
				if ( ! empty( $val ) ) {
					$count++;
					break;
				}
			}
		}

		return $count;
	}

	// ── Reading ──────────────────────────────────────────────────────────────

	/**
	 * Read one post's SEO values from a source, as JAT Search Intel fields.
	 *
	 * @param string $source  Source key.
	 * @param int    $post_id Post ID.
	 * @return array<string,string>
	 */
	public function read_post_fields( string $source, int $post_id ): array {
		$config = self::SOURCES[ $source ] ?? null;
		if ( null === $config ) {
			return [];
		}

		$values = [];
		foreach ( $config['meta'] as $field => $meta_key ) {
			$raw = get_post_meta( $post_id, $meta_key, true );
			if ( in_array( $field, [ 'noindex', 'nofollow' ], true ) ) {
				if ( $this->is_true_flag( $source, $field, $raw ) ) {
					$values[ $field ] = '1';
				}
				continue;
			}
			if ( 'primary_category' === $field ) {
				if ( (int) $raw > 0 ) {
					$values[ $field ] = (string) (int) $raw;
				}
				continue;
			}
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$values[ $field ] = $raw;
			}
		}

		// Rank Math keeps extra keyphrases after the first comma.
		if ( 'rankmath' === $source && ! empty( $values['keyword'] ) && false !== strpos( $values['keyword'], ',' ) ) {
			$parts                        = array_map( 'trim', explode( ',', $values['keyword'] ) );
			$values['keyword']            = (string) array_shift( $parts );
			$values['secondary_keywords'] = implode( ', ', array_filter( $parts ) );
		}

		return $values;
	}

	/**
	 * Whether a source's robots value means "on" (noindex / nofollow).
	 *
	 * @param string $source Source key.
	 * @param string $field  noindex or nofollow.
	 * @param mixed  $raw    Stored value.
	 * @return bool
	 */
	private function is_true_flag( string $source, string $field, $raw ): bool {
		if ( 'rankmath' === $source ) {
			$list = is_array( $raw ) ? $raw : maybe_unserialize( $raw );
			return is_array( $list ) && in_array( $field, $list, true );
		}
		if ( 'seopress' === $source ) {
			return 'yes' === $raw;
		}
		// Yoast stores 1 = noindex, 2 = index; the others store 1 / on / true.
		return in_array( strtolower( (string) $raw ), [ '1', 'on', 'true', 'yes' ], true );
	}

	/**
	 * Whether All in One SEO 4+ tables exist.
	 *
	 * @return bool
	 */
	protected function aioseo_has_tables(): bool {
		return $this->table_exists( 'aioseo_posts' );
	}

	/**
	 * Whether a table (without the WordPress prefix) exists.
	 *
	 * @param string $name Table name without prefix.
	 * @return bool
	 */
	protected function table_exists( string $name ): bool {
		global $wpdb;
		$table = $wpdb->prefix . $name;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration check.
		return $found === $table;
	}

	/**
	 * Read rows from a source plugin table in batches.
	 *
	 * @param string $name   Table name without prefix.
	 * @param int    $offset Row offset.
	 * @return array<int,array<string,mixed>>
	 */
	protected function read_table( string $name, int $offset ): array {
		global $wpdb;
		$table = $wpdb->prefix . $name;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration read of another plugin's table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC LIMIT %d OFFSET %d', $table, self::BATCH, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Every row of a source table.
	 *
	 * @param string $name Table name without prefix.
	 * @return array<int,array<string,mixed>>
	 */
	private function all_rows( string $name ): array {
		if ( ! $this->table_exists( $name ) ) {
			return [];
		}
		$all    = [];
		$offset = 0;
		do {
			$rows   = $this->read_table( $name, $offset );
			$all    = array_merge( $all, $rows );
			$offset += self::BATCH;
		} while ( count( $rows ) === self::BATCH );
		return $all;
	}

	/**
	 * Count AIOSEO post rows that hold anything worth importing.
	 *
	 * @return int
	 */
	private function count_aioseo_posts(): int {
		$count = 0;
		foreach ( $this->all_rows( 'aioseo_posts' ) as $row ) {
			if ( ! empty( $this->map_aioseo_post_row( $row ) ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Convert an `aioseo_posts` row to JAT Search Intel fields.
	 *
	 * @param array<string,mixed> $row Table row.
	 * @return array<string,string>
	 */
	public function map_aioseo_post_row( array $row ): array {
		$values = [];

		foreach ( [ 'title' => 'title', 'description' => 'description', 'canonical_url' => 'canonical', 'og_title' => 'social_title', 'og_description' => 'social_description', 'twitter_title' => 'twitter_title', 'twitter_description' => 'twitter_description' ] as $column => $field ) {
			if ( isset( $row[ $column ] ) && is_string( $row[ $column ] ) && '' !== trim( $row[ $column ] ) ) {
				$values[ $field ] = $row[ $column ];
			}
		}

		// Focus keyphrase: the focus_keyword column (AIOSEO 5) or the keyphrases JSON (AIOSEO 4).
		$keyphrases = self::decode_json( $row['keyphrases'] ?? '' );
		$focus      = trim( (string) ( $row['focus_keyword'] ?? '' ) );
		if ( '' === $focus && ! empty( $keyphrases['focus']['keyphrase'] ) ) {
			$focus = trim( (string) $keyphrases['focus']['keyphrase'] );
		}
		if ( '' !== $focus ) {
			$values['keyword'] = $focus;
		}

		$secondary  = [];
		$additional = self::decode_json( $row['additional_keywords'] ?? '' );
		if ( empty( $additional ) && ! empty( $keyphrases['additional'] ) && is_array( $keyphrases['additional'] ) ) {
			$additional = $keyphrases['additional'];
		}
		foreach ( (array) $additional as $item ) {
			$phrase = is_array( $item ) ? ( $item['keyphrase'] ?? $item['keyword'] ?? '' ) : $item;
			$phrase = trim( (string) $phrase );
			if ( '' !== $phrase ) {
				$secondary[] = $phrase;
			}
		}
		if ( $secondary ) {
			$values['secondary_keywords'] = implode( ', ', array_unique( $secondary ) );
		}

		// Robots: only when the post overrides the defaults.
		if ( isset( $row['robots_default'] ) && ! (int) $row['robots_default'] ) {
			if ( ! empty( $row['robots_noindex'] ) ) {
				$values['noindex'] = '1';
			}
			if ( ! empty( $row['robots_nofollow'] ) ) {
				$values['nofollow'] = '1';
			}
		}

		// Social images: only custom ones; the featured image is our default anyway.
		if ( ! empty( $row['og_image_custom_url'] ) ) {
			$values['social_image'] = (string) $row['og_image_custom_url'];
		}
		if ( isset( $row['twitter_use_og'] ) && ! (int) $row['twitter_use_og'] && ! empty( $row['twitter_image_custom_url'] ) ) {
			$values['twitter_image'] = (string) $row['twitter_image_custom_url'];
		}

		// Primary category.
		$primary = self::decode_json( $row['primary_term'] ?? '' );
		if ( ! empty( $primary['category'] ) && (int) $primary['category'] > 0 ) {
			$values['primary_category'] = (string) (int) $primary['category'];
		}

		// Custom schema the user pasted into AIOSEO's "Custom Schema" graphs.
		$schema = self::decode_json( $row['schema'] ?? '' );
		$nodes  = [];
		foreach ( (array) ( $schema['customGraphs'] ?? [] ) as $graph ) {
			if ( ! is_array( $graph ) ) {
				continue;
			}
			$json = $graph['schema'] ?? ( $graph['properties']['schema'] ?? ( $graph['properties']['json'] ?? '' ) );
			$json = is_array( $json ) ? (string) wp_json_encode( $json ) : (string) $json;
			if ( '' === trim( $json ) ) {
				continue;
			}
			$parsed = Custom_Schema::parse( $json );
			if ( null !== $parsed ) {
				$nodes = array_merge( $nodes, $parsed );
			}
		}
		if ( $nodes ) {
			$values['custom_schema'] = (string) wp_json_encode( count( $nodes ) === 1 ? $nodes[0] : [ '@graph' => $nodes ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		return $values;
	}

	/**
	 * Decode a JSON column, returning [] for anything that is not an object or list.
	 *
	 * @param mixed $value Raw column value.
	 * @return array
	 */
	private static function decode_json( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return [];
		}
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Collect every post's fields for a source: post ID => fields.
	 *
	 * @param string $source Source key.
	 * @return array<int,array<string,string>>
	 */
	private function collect_posts( string $source ): array {
		$out = [];

		if ( 'aioseo' === $source && $this->aioseo_has_tables() ) {
			foreach ( $this->all_rows( 'aioseo_posts' ) as $row ) {
				$post_id = (int) ( $row['post_id'] ?? 0 );
				$fields  = $this->map_aioseo_post_row( $row );
				if ( $post_id > 0 && $fields ) {
					$out[ $post_id ] = $fields;
				}
			}
			return $out;
		}

		foreach ( $this->all_post_ids() as $post_id ) {
			$fields = $this->read_post_fields( $source, $post_id );
			if ( $fields ) {
				$out[ $post_id ] = $fields;
			}
		}

		return $out;
	}

	// ── Dry Run ──────────────────────────────────────────────────────────────

	/**
	 * Preview what would be migrated without making changes.
	 *
	 * @param string $source Source plugin key (yoast, rankmath, etc.).
	 * @return array Preview data.
	 */
	public function dry_run( string $source ): array {
		if ( ! isset( self::SOURCES[ $source ] ) ) {
			return [ 'error' => 'Unknown source: ' . $source ];
		}

		$preview = [
			'source'     => self::SOURCES[ $source ]['label'],
			'fields'     => [],
			'post_count' => 0,
			'samples'    => [],
			'terms'      => 0,
			'redirects'  => 0,
			'settings'   => [],
		];

		$field_counts = [];
		$samples      = [];

		foreach ( $this->collect_posts( $source ) as $post_id => $fields ) {
			$preview['post_count']++;
			foreach ( $fields as $field => $val ) {
				$field_counts[ $field ] = ( $field_counts[ $field ] ?? 0 ) + 1;
				if ( count( $samples[ $field ] ?? [] ) < 3 ) {
					$shown                = $this->prepare_value( $field, $val, $source );
					$samples[ $field ][] = [
						'post_id' => $post_id,
						'title'   => get_the_title( $post_id ),
						'value'   => mb_strimwidth( $shown, 0, 100, '...' ),
					];
				}
			}
		}

		foreach ( $field_counts as $field => $count ) {
			$preview['fields'][ $field ] = [
				'count'   => $count,
				'target'  => $this->get_target_meta_key( $field ),
				'samples' => $samples[ $field ] ?? [],
			];
		}

		if ( 'aioseo' === $source ) {
			$preview['terms']     = count( $this->collect_aioseo_terms() );
			$preview['redirects'] = count( $this->collect_redirects( $source ) );
			$preview['settings']  = array_keys( $this->map_aioseo_settings() );
		} else {
			$preview['redirects'] = count( $this->collect_redirects( $source ) );
		}

		return $preview;
	}

	// ── Migration ────────────────────────────────────────────────────────────

	/**
	 * Run a full migration from a source plugin.
	 *
	 * @param string $source    Source plugin key.
	 * @param bool   $overwrite Whether to overwrite existing JAT Search Intel values.
	 * @return array Migration result.
	 */
	public function migrate( string $source, bool $overwrite = false ): array {
		if ( ! isset( self::SOURCES[ $source ] ) ) {
			return [ 'error' => 'Unknown source: ' . $source ];
		}

		$result = [
			'source'    => self::SOURCES[ $source ]['label'],
			'migrated'  => 0,
			'skipped'   => 0,
			'fields'    => [],
			'terms'     => 0,
			'redirects' => 0,
			'settings'  => [],
		];

		foreach ( $this->collect_posts( $source ) as $post_id => $fields ) {
			$post_migrated = false;

			foreach ( $fields as $field => $source_val ) {
				$target_key = $this->get_target_meta_key( $field );
				if ( '' === $target_key ) {
					continue;
				}

				$existing = get_post_meta( $post_id, $target_key, true );
				if ( '' !== (string) $existing && ! $overwrite ) {
					$result['skipped']++;
					continue;
				}

				$value = $this->prepare_value( $field, $source_val, $source );
				if ( '' === $value ) {
					continue;
				}

				update_post_meta( $post_id, $target_key, 'custom_schema' === $field ? wp_slash( $value ) : $value );

				$result['fields'][ $field ] = ( $result['fields'][ $field ] ?? 0 ) + 1;
				$post_migrated              = true;
			}

			if ( $post_migrated ) {
				$result['migrated']++;
			}
		}

		if ( 'aioseo' === $source ) {
			$result['terms']    = $this->migrate_aioseo_terms( $overwrite );
			$result['settings'] = $this->migrate_aioseo_settings( $overwrite );
		}

		$result['redirects'] = $this->migrate_redirects( $source );

		return $result;
	}

	/**
	 * Map field names to JAT Search Intel meta keys.
	 *
	 * @param string $field Field name.
	 * @return string Target meta key.
	 */
	private function get_target_meta_key( string $field ): string {
		if ( 'primary_category' === $field ) {
			return Primary_Term::meta_key( 'category' );
		}
		return self::TARGETS[ $field ] ?? '';
	}

	/**
	 * Convert and sanitize a value for storage.
	 *
	 * @param string $field  Field name.
	 * @param mixed  $value  Raw value.
	 * @param string $source Source key.
	 * @return string Sanitized value ('' to skip).
	 */
	private function prepare_value( string $field, $value, string $source ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		if ( in_array( $field, [ 'noindex', 'nofollow' ], true ) ) {
			return '1' === $value ? '1' : '';
		}

		if ( 'primary_category' === $field ) {
			return (int) $value > 0 ? (string) (int) $value : '';
		}

		if ( 'custom_schema' === $field ) {
			return null !== Custom_Schema::parse( $value ) ? $value : '';
		}

		if ( in_array( $field, self::URL_FIELDS, true ) ) {
			return esc_url_raw( $value );
		}

		if ( in_array( $field, self::TEXT_WITH_VARIABLES, true ) ) {
			// Not sanitize_text_field(): it would strip %category% and %date%.
			return Title_Engine::sanitize_template( self::convert_variables( $value, $source ) );
		}

		return sanitize_text_field( $value );
	}

	// ── Terms ────────────────────────────────────────────────────────────────

	/**
	 * AIOSEO term rows mapped to Term_SEO fields: term ID => fields.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function collect_aioseo_terms(): array {
		$out = [];
		foreach ( $this->all_rows( 'aioseo_terms' ) as $row ) {
			$term_id = (int) ( $row['term_id'] ?? 0 );
			if ( $term_id <= 0 ) {
				continue;
			}
			$fields = [];
			foreach ( [ 'title' => 'title', 'description' => 'description', 'canonical_url' => 'canonical', 'og_title' => 'social_title', 'og_description' => 'social_description', 'og_image_custom_url' => 'social_image' ] as $column => $field ) {
				if ( ! empty( $row[ $column ] ) && is_string( $row[ $column ] ) ) {
					$fields[ $field ] = $row[ $column ];
				}
			}
			$keyphrases = self::decode_json( $row['keyphrases'] ?? '' );
			if ( ! empty( $keyphrases['focus']['keyphrase'] ) ) {
				$fields['focus_keyword'] = (string) $keyphrases['focus']['keyphrase'];
			}
			if ( isset( $row['robots_default'] ) && ! (int) $row['robots_default'] ) {
				if ( ! empty( $row['robots_noindex'] ) ) {
					$fields['noindex'] = '1';
				}
				if ( ! empty( $row['robots_nofollow'] ) ) {
					$fields['nofollow'] = '1';
				}
			}
			if ( $fields ) {
				$out[ $term_id ] = $fields;
			}
		}
		return $out;
	}

	/**
	 * Import AIOSEO category/tag SEO.
	 *
	 * @param bool $overwrite Overwrite existing values.
	 * @return int Terms updated.
	 */
	private function migrate_aioseo_terms( bool $overwrite ): int {
		$count = 0;
		foreach ( $this->collect_aioseo_terms() as $term_id => $fields ) {
			$changed = false;
			foreach ( $fields as $field => $raw ) {
				if ( ! isset( Term_SEO::FIELDS[ $field ] ) ) {
					continue;
				}
				$key = Term_SEO::META_PREFIX . $field;
				if ( '' !== (string) get_term_meta( $term_id, $key, true ) && ! $overwrite ) {
					continue;
				}
				$type = Term_SEO::FIELDS[ $field ];
				if ( 'url' === $type ) {
					$value = esc_url_raw( $raw );
				} elseif ( 'bool' === $type ) {
					$value = '1' === $raw ? '1' : '';
				} else {
					$value = Title_Engine::sanitize_template( self::convert_variables( $raw, 'aioseo' ) );
				}
				if ( '' === $value ) {
					continue;
				}
				update_term_meta( $term_id, $key, $value );
				$changed = true;
			}
			if ( $changed ) {
				$count++;
			}
		}
		return $count;
	}

	// ── Site-wide settings ───────────────────────────────────────────────────

	/**
	 * Read AIOSEO's site-wide settings as JAT Search Intel settings.
	 *
	 * Returns setting key => value, plus 'social_profiles' => [platform => url].
	 *
	 * @return array<string,mixed>
	 */
	public function map_aioseo_settings(): array {
		$raw  = get_option( 'aioseo_options', '' );
		$opts = self::decode_json( $raw );
		if ( empty( $opts ) ) {
			return [];
		}

		$out    = [];
		$search = $opts['searchAppearance'] ?? [];
		$global = $search['global'] ?? [];

		if ( ! empty( $global['separator'] ) ) {
			$out['separator'] = html_entity_decode( (string) $global['separator'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		$templates = [];
		if ( ! empty( $global['siteTitle'] ) ) {
			$templates['homepage'] = self::convert_variables( (string) $global['siteTitle'], 'aioseo' );
		}
		$map = [
			'single'   => $search['postTypes']['post']['title'] ?? '',
			'page'     => $search['postTypes']['page']['title'] ?? '',
			'category' => $search['taxonomies']['category']['title'] ?? '',
			'tag'      => $search['taxonomies']['post_tag']['title'] ?? '',
			'author'   => $search['archives']['author']['title'] ?? '',
			'archive'  => $search['archives']['date']['title'] ?? '',
			'search'   => $search['archives']['search']['title'] ?? '',
		];
		foreach ( $map as $context => $template ) {
			if ( is_string( $template ) && '' !== trim( $template ) ) {
				$converted = self::convert_variables( $template, 'aioseo' );
				if ( '' !== $converted ) {
					$templates[ $context ] = $converted;
				}
			}
		}
		if ( $templates ) {
			$out['title_templates'] = $templates;
		}

		$schema = $global['schema'] ?? [];
		if ( ! empty( $schema['siteRepresents'] ) ) {
			$is_person             = 'person' === $schema['siteRepresents'];
			$out['knowledge_type'] = $is_person ? 'Person' : 'Organization';
			$name                  = $is_person ? ( $schema['personName'] ?? '' ) : ( $schema['organizationName'] ?? '' );
			$logo                  = $is_person ? ( $schema['personLogo'] ?? '' ) : ( $schema['organizationLogo'] ?? '' );
			if ( '' !== trim( (string) $name ) ) {
				$out['knowledge_name'] = sanitize_text_field( (string) $name );
			}
			if ( '' !== trim( (string) $logo ) ) {
				$out['knowledge_logo'] = esc_url_raw( (string) $logo );
			}
		}

		$tools = $opts['webmasterTools'] ?? [];
		foreach ( [ 'google', 'bing', 'yandex', 'baidu', 'pinterest' ] as $engine ) {
			if ( ! empty( $tools[ $engine ] ) ) {
				$out[ 'verify_' . $engine ] = sanitize_text_field( (string) $tools[ $engine ] );
			}
		}

		$urls     = $opts['social']['profiles']['urls'] ?? [];
		$profiles = [];
		foreach ( [ 'facebook' => 'facebookPageUrl', 'twitter' => 'twitterUrl', 'instagram' => 'instagramUrl', 'linkedin' => 'linkedinUrl', 'youtube' => 'youtubeUrl', 'pinterest' => 'pinterestUrl', 'tiktok' => 'tiktokUrl', 'github' => 'githubUrl' ] as $platform => $key ) {
			if ( ! empty( $urls[ $key ] ) ) {
				$profiles[ $platform ] = esc_url_raw( (string) $urls[ $key ] );
			}
		}
		if ( $profiles ) {
			$out['social_profiles'] = $profiles;
		}
		if ( ! empty( $profiles['twitter'] ) && preg_match( '#(?:twitter|x)\.com/@?([A-Za-z0-9_]{1,15})#i', $profiles['twitter'], $m ) ) {
			$out['twitter_site'] = '@' . $m[1];
		}

		return $out;
	}

	/**
	 * Import AIOSEO's site-wide settings.
	 *
	 * Without $overwrite, only settings still at their default or empty are set.
	 *
	 * @param bool $overwrite Overwrite existing values.
	 * @return string[] Setting keys that were updated.
	 */
	private function migrate_aioseo_settings( bool $overwrite ): array {
		$mapped = $this->map_aioseo_settings();
		if ( empty( $mapped ) ) {
			return [];
		}

		$changed  = [];
		$saved    = get_option( 'jat_search_intel_settings', [] );
		$saved    = is_array( $saved ) ? $saved : [];
		$defaults = Core::get_default_settings();

		if ( isset( $mapped['social_profiles'] ) ) {
			$profiles = get_option( 'jat_search_intel_social_profiles', [] );
			$profiles = is_array( $profiles ) ? $profiles : [];
			foreach ( $mapped['social_profiles'] as $platform => $url ) {
				if ( empty( $profiles[ $platform ] ) || $overwrite ) {
					$profiles[ $platform ] = $url;
					$changed[]             = 'social_' . $platform;
				}
			}
			update_option( 'jat_search_intel_social_profiles', $profiles );
			unset( $mapped['social_profiles'] );
		}

		foreach ( $mapped as $key => $value ) {
			if ( 'title_templates' === $key ) {
				$current = $saved['title_templates'] ?? $defaults['title_templates'];
				foreach ( $value as $context => $template ) {
					$is_default = ( $current[ $context ] ?? '' ) === ( $defaults['title_templates'][ $context ] ?? null );
					if ( $overwrite || $is_default || empty( $current[ $context ] ) ) {
						$current[ $context ] = $template;
						$changed[]           = 'title_templates.' . $context;
					}
				}
				$saved['title_templates'] = $current;
				continue;
			}

			$current    = $saved[ $key ] ?? ( $defaults[ $key ] ?? '' );
			$is_default = array_key_exists( $key, $defaults ) && $current === $defaults[ $key ];
			if ( $overwrite || '' === $current || $is_default ) {
				$saved[ $key ] = $value;
				$changed[]     = $key;
			}
		}

		update_option( 'jat_search_intel_settings', $saved );

		return $changed;
	}

	// ── Redirects ────────────────────────────────────────────────────────────

	/**
	 * Read a source plugin's redirects in Redirect_Manager's format.
	 *
	 * @param string $source Source key.
	 * @return array<int,array<string,mixed>>
	 */
	private function collect_redirects( string $source ): array {
		$out = [];

		if ( 'aioseo' === $source ) {
			foreach ( $this->all_rows( 'aioseo_redirects' ) as $row ) {
				$out[] = [
					'source_url'  => (string) ( $row['source_url'] ?? '' ),
					'target_url'  => (string) ( $row['target_url'] ?? '' ),
					'status_code' => (int) ( $row['type'] ?? 301 ),
					'match_type'  => ! empty( $row['regex'] ) ? 'regex' : 'exact',
					'enabled'     => isset( $row['enabled'] ) ? (int) $row['enabled'] : 1,
				];
			}
		} elseif ( 'rankmath' === $source ) {
			foreach ( $this->all_rows( self::SOURCES['rankmath']['options']['redirects_table'] ) as $row ) {
				$sources = maybe_unserialize( $row['sources'] ?? '' );
				$first   = is_array( $sources ) && isset( $sources[0] ) && is_array( $sources[0] ) ? $sources[0] : [];
				$compare = $first['comparison'] ?? 'exact';
				$out[]   = [
					'source_url'  => (string) ( $first['pattern'] ?? '' ),
					'target_url'  => (string) ( $row['url_to'] ?? '' ),
					'status_code' => (int) ( $row['header_code'] ?? 301 ),
					'match_type'  => 'regex' === $compare ? 'regex' : ( 'start' === $compare ? 'prefix' : 'exact' ),
					'enabled'     => 'active' === ( $row['status'] ?? 'active' ) ? 1 : 0,
				];
			}
		} elseif ( ! empty( self::SOURCES[ $source ]['options']['redirects'] ) ) {
			$list = get_option( self::SOURCES[ $source ]['options']['redirects'], [] );
			foreach ( is_array( $list ) ? $list : [] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$from  = (string) ( $item['origin'] ?? $item['from'] ?? '' );
				$to    = (string) ( $item['url'] ?? $item['to'] ?? '' );
				$out[] = [
					'source_url'  => $from,
					'target_url'  => $to,
					'status_code' => (int) ( $item['type'] ?? $item['status'] ?? $item['code'] ?? 301 ),
					'match_type'  => 'regex' === ( $item['format'] ?? '' ) ? 'regex' : 'exact',
					'enabled'     => 1,
				];
			}
		}

		$clean = [];
		foreach ( $out as $redirect ) {
			$from = trim( $redirect['source_url'] );
			if ( '' === $from ) {
				continue;
			}
			if ( 'regex' !== $redirect['match_type'] ) {
				$from = '/' . ltrim( (string) wp_parse_url( $from, PHP_URL_PATH ) ?: $from, '/' );
			}
			if ( '' === trim( $redirect['target_url'] ) && 410 !== $redirect['status_code'] ) {
				continue;
			}
			$redirect['source_url'] = $from;
			$redirect['note']       = sprintf( 'Imported from %s', self::SOURCES[ $source ]['label'] );
			$clean[]                = $redirect;
		}

		return $clean;
	}

	/**
	 * The redirect store (Redirect_Manager instance), or null when unavailable.
	 *
	 * @return Redirect_Manager|null
	 */
	protected function redirect_store() {
		$core = Core::get_instance();
		return ( isset( $core->redirects ) && $core->redirects instanceof Redirect_Manager ) ? $core->redirects : null;
	}

	/**
	 * Import a source plugin's redirects into the redirect manager.
	 *
	 * Redirects whose source already exists are skipped.
	 *
	 * @param string $source Source key.
	 * @return int Number of redirects imported.
	 */
	private function migrate_redirects( string $source ): int {
		$incoming = $this->collect_redirects( $source );
		$store    = $this->redirect_store();
		if ( empty( $incoming ) || null === $store ) {
			return 0;
		}

		$existing = [];
		foreach ( $store->get_redirects( 1, 100000 )['items'] ?? [] as $row ) {
			$existing[ (string) ( is_object( $row ) ? $row->source_url : ( $row['source_url'] ?? '' ) ) ] = true;
		}

		$count = 0;
		foreach ( $incoming as $redirect ) {
			if ( isset( $existing[ $redirect['source_url'] ] ) ) {
				continue;
			}
			if ( false !== $store->save_redirect( $redirect ) ) {
				$existing[ $redirect['source_url'] ] = true;
				$count++;
			}
		}

		return $count;
	}

	// ── AJAX Handlers ────────────────────────────────────────────────────────

	/**
	 * AJAX handler for running migration.
	 */
	public function ajax_run_migration(): void {
		check_ajax_referer( 'jat_search_intel_migration', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to run a migration.', 'jat-search-intel' ), 403 );
		}

		$source    = ( isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '' );
		$overwrite = ! empty( $_POST['overwrite'] ) && '0' !== $_POST['overwrite'];

		wp_send_json_success( $this->migrate( $source, $overwrite ) );
	}

	/**
	 * AJAX handler for dry run preview.
	 */
	public function ajax_dry_run(): void {
		check_ajax_referer( 'jat_search_intel_migration', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to run a migration.', 'jat-search-intel' ), 403 );
		}

		$source = ( isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '' );
		wp_send_json_success( $this->dry_run( $source ) );
	}

	// ── Admin Page ───────────────────────────────────────────────────────────

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'jat-search-intel',
			__( 'Plugin Migration', 'jat-search-intel' ),
			__( 'Migration', 'jat-search-intel' ),
			'manage_options',
			'jat-search-intel-migration',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Load the migration page script.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public function enqueue_assets( $hook ): void {
		if ( false === strpos( (string) $hook, 'jat-search-intel-migration' ) ) {
			return;
		}
		wp_enqueue_script(
			'jat-search-intel-migration',
			JAT_SEARCH_INTEL_URL . 'admin/js/migration.js',
			[],
			JAT_SEARCH_INTEL_VERSION,
			true
		);
		wp_localize_script(
			'jat-search-intel-migration',
			'jatSearchIntelMigration',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'jat_search_intel_migration' ),
				'i18n'    => [
					'working'   => __( 'Working…', 'jat-search-intel' ),
					'failed'    => __( 'The request failed. Please reload the page and try again.', 'jat-search-intel' ),
					'confirm'   => __( 'Copy this SEO data into JAT Search Intel now? Your other plugin keeps its data, so you can run this again.', 'jat-search-intel' ),
					'posts'     => __( 'Posts with data', 'jat-search-intel' ),
					'migrated'  => __( 'Posts updated', 'jat-search-intel' ),
					'skipped'   => __( 'Fields skipped (already set)', 'jat-search-intel' ),
					'terms'     => __( 'Categories and tags', 'jat-search-intel' ),
					'redirects' => __( 'Redirects', 'jat-search-intel' ),
					'settings'  => __( 'Site settings', 'jat-search-intel' ),
					'field'     => __( 'Field', 'jat-search-intel' ),
					'count'     => __( 'Posts', 'jat-search-intel' ),
					'example'   => __( 'Example', 'jat-search-intel' ),
					'done'      => __( 'Done. Deactivate the other SEO plugin to avoid duplicate tags.', 'jat-search-intel' ),
				],
			]
		);
	}

	/**
	 * Render migration admin page.
	 */
	public function render_admin_page(): void {
		$detected = $this->detect_sources();

		echo '<div class="wrap"><h1>' . esc_html__( 'SEO Plugin Migration', 'jat-search-intel' ) . '</h1>';

		if ( empty( $detected ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'No SEO plugin data detected. Migration is available for Yoast SEO, Rank Math, All in One SEO, SEOPress and The SEO Framework.', 'jat-search-intel' ) . '</p></div>';
			echo '</div>';
			return;
		}

		echo '<p>' . esc_html__( 'Preview first to see what will be copied. Nothing is deleted from the other plugin.', 'jat-search-intel' ) . '</p>';

		foreach ( $detected as $key => $info ) {
			printf(
				'<div class="card" style="max-width:720px;margin-bottom:16px"><h2>%1$s</h2><p><strong>%2$d</strong> %3$s</p><p><button type="button" class="button" data-jsi-migrate="dryrun" data-source="%4$s">%5$s</button> <button type="button" class="button button-primary" data-jsi-migrate="run" data-source="%4$s">%6$s</button> <label style="margin-left:12px"><input type="checkbox" data-jsi-overwrite="%4$s"> %7$s</label></p><div class="jsi-migration-result" data-jsi-result="%4$s" aria-live="polite"></div></div>',
				esc_html( $info['label'] ),
				absint( $info['posts'] ),
				esc_html__( 'posts with SEO data', 'jat-search-intel' ),
				esc_attr( $key ),
				esc_html__( 'Preview migration', 'jat-search-intel' ),
				esc_html__( 'Migrate now', 'jat-search-intel' ),
				esc_html__( 'Overwrite values already set in JAT Search Intel', 'jat-search-intel' )
			);
		}

		echo '</div>';
	}
}
