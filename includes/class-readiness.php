<?php
/**
 * Site-wide AI Search Readiness audit.
 *
 * One page that answers "can search engines and AI assistants find, read,
 * trust and cite this site?" — with a score, plain-language findings and
 * one-click fixes for anything the plugin itself controls. Also builds the
 * Refresh Queue: important pages that have gone longest without an update.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Readiness audit.
 */
class Readiness {

	public const PAGE_SLUG = 'jat-search-intel-readiness';

	/** Bots whose access decides whether you can appear in search and AI answers at all. */
	private const SEARCH_BOTS = [ 'Googlebot', 'Bingbot', 'OAI-SearchBot', 'PerplexityBot', 'Claude-SearchBot' ];

	private const WEIGHTS = [
		'critical'  => 10,
		'important' => 6,
		'helpful'   => 3,
		'info'      => 0,
	];

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_admin_page' ], 11 );
			add_action( 'admin_post_jat_search_intel_readiness_fix', [ $this, 'handle_fix' ] );
		}
	}

	// ── Checks ──────────────────────────────────────────────────────────

	/**
	 * Run every check.
	 *
	 * @return array<int,array{id:string,group:string,level:string,status:string,title:string,detail:string,fix:string,link:string}>
	 */
	public function run(): array {
		$checks = [];
		$add    = static function ( string $id, string $group, string $level, string $status, string $title, string $detail, string $fix = '', string $link = '' ) use ( &$checks ): void {
			$checks[] = compact( 'id', 'group', 'level', 'status', 'title', 'detail', 'fix', 'link' );
		};
		$s = $this->settings;

		// ── Can search engines and AI crawlers reach the site? ──
		$public = '1' === (string) get_option( 'blog_public', '1' );
		$add(
			'visibility',
			'access',
			'critical',
			$public ? 'pass' : 'fail',
			$public ? __( 'Search engines are allowed to index the site', 'jat-search-intel' ) : __( 'The site asks search engines not to index it', 'jat-search-intel' ),
			$public ? '' : __( '"Discourage search engines" is ticked under Settings > Reading. Nothing on this site can rank or be cited until it is unticked.', 'jat-search-intel' ),
			'',
			admin_url( 'options-reading.php' )
		);

		$blocked = $this->blocked_search_bots();
		$add(
			'robots-search-bots',
			'access',
			'critical',
			$blocked ? 'fail' : 'pass',
			$blocked
				/* translators: %s: comma-separated crawler names. */
				? sprintf( __( 'robots.txt blocks %s', 'jat-search-intel' ), implode( ', ', $blocked ) )
				: __( 'Google, Bing and AI search crawlers can read the site', 'jat-search-intel' ),
			$blocked ? __( 'Blocking these crawlers removes you from their search results and from the AI answers built on them (ChatGPT search and Copilot use Bing).', 'jat-search-intel' ) : '',
			$this->rules_block_search_bots() ? 'allow-ai-search' : '',
			$blocked && ! $this->rules_block_search_bots() ? admin_url( 'admin.php?page=jat-search-intel-robots' ) : ''
		);

		$static_robots = defined( 'ABSPATH' ) && is_file( ABSPATH . 'robots.txt' );
		if ( $static_robots ) {
			$add( 'static-robots', 'access', 'helpful', 'warn', __( 'A physical robots.txt file overrides WordPress', 'jat-search-intel' ), __( 'Your sitemap lines, AI crawler rules and Content Signals are not served while a robots.txt file sits in the site root.', 'jat-search-intel' ) );
		}

		$front_noindex = $this->front_page_noindexed();
		if ( $front_noindex ) {
			$add( 'front-noindex', 'access', 'critical', 'fail', __( 'Your home page is set to noindex', 'jat-search-intel' ), __( 'The page set as your home page has "noindex" switched on in its SEO settings.', 'jat-search-intel' ), '', (string) get_edit_post_link( (int) get_option( 'page_on_front' ), 'raw' ) );
		}

		$conflicts = $this->conflicting_plugins();
		$add(
			'conflicts',
			'access',
			'critical',
			$conflicts ? 'fail' : 'pass',
			$conflicts
				/* translators: %s: plugin names. */
				? sprintf( __( 'Another SEO plugin is active: %s', 'jat-search-intel' ), implode( ', ', $conflicts ) )
				: __( 'No other SEO plugin is printing duplicate tags', 'jat-search-intel' ),
			$conflicts ? __( 'Two SEO plugins print duplicate titles, canonicals and schema, which confuses search engines. Import its data under Migration, then deactivate it.', 'jat-search-intel' ) : '',
			'',
			$conflicts ? admin_url( 'admin.php?page=jat-search-intel-migration' ) : ''
		);

		$https = 0 === strpos( home_url( '/' ), 'https://' );
		$add( 'https', 'access', 'important', $https ? 'pass' : 'warn', $https ? __( 'Site is served over HTTPS', 'jat-search-intel' ) : __( 'Site address is not HTTPS', 'jat-search-intel' ), $https ? '' : __( 'Search engines and browsers favour secure pages. Ask your host for a certificate, then update the addresses under Settings > General.', 'jat-search-intel' ), '', $https ? '' : admin_url( 'options-general.php' ) );

		$pretty = (bool) get_option( 'permalink_structure' );
		$add( 'permalinks', 'access', 'important', $pretty ? 'pass' : 'warn', $pretty ? __( 'Readable permalinks are on', 'jat-search-intel' ) : __( 'Permalinks are set to "Plain"', 'jat-search-intel' ), $pretty ? '' : __( 'URLs like ?p=123 carry no meaning. Choose "Post name" under Settings > Permalinks.', 'jat-search-intel' ), '', $pretty ? '' : admin_url( 'options-permalink.php' ) );

		// ── Discovery ──
		$add( 'sitemap', 'discovery', 'important', ! empty( $s['enable_sitemap'] ) ? 'pass' : 'fail', ! empty( $s['enable_sitemap'] ) ? __( 'XML sitemap is published and listed in robots.txt', 'jat-search-intel' ) : __( 'XML sitemap is off', 'jat-search-intel' ), '', empty( $s['enable_sitemap'] ) ? 'enable-sitemap' : '' );

		$indexnow = ! empty( $s['enable_indexnow'] ) && ! empty( $s['indexnow_api_key'] );
		$add( 'indexnow', 'discovery', 'important', $indexnow ? 'pass' : 'warn', $indexnow ? __( 'IndexNow pings Bing on every publish and update', 'jat-search-intel' ) : __( 'IndexNow is off', 'jat-search-intel' ), $indexnow ? '' : __( 'ChatGPT search and Microsoft Copilot answer from Bing\'s index. IndexNow tells Bing about new and changed pages within minutes instead of waiting for a crawl.', 'jat-search-intel' ), $indexnow ? '' : 'enable-indexnow' );

		$llms = ! empty( $s['enable_geo'] ) && ! empty( $s['enable_llms_txt'] );
		$add( 'llms', 'discovery', 'helpful', $llms ? 'pass' : 'warn', $llms ? __( 'llms.txt guides AI agents to your key pages', 'jat-search-intel' ) : __( 'llms.txt is off', 'jat-search-intel' ), '', $llms ? '' : 'enable-llms' );
		if ( $llms && defined( 'ABSPATH' ) && is_file( ABSPATH . 'llms.txt' ) ) {
			$add( 'llms-static', 'discovery', 'helpful', 'warn', __( 'A static llms.txt file hides the generated one', 'jat-search-intel' ), __( 'Another tool wrote llms.txt into the site root. Remove it from the notice on the Plugins screen.', 'jat-search-intel' ), '', admin_url( 'plugins.php' ) );
		}

		$md = ! empty( $s['enable_markdown_endpoints'] );
		$add( 'markdown', 'discovery', 'helpful', $md ? 'pass' : 'warn', $md ? __( 'Clean Markdown copies are available for AI agents', 'jat-search-intel' ) : __( 'Markdown copies for AI agents are off', 'jat-search-intel' ), $md ? '' : __( 'AI agents read Markdown far more reliably than themed HTML.', 'jat-search-intel' ), $md ? '' : 'enable-markdown' );

		// ── Understanding & trust ──
		$schema = ! empty( $s['enable_schema'] );
		$add( 'schema', 'trust', 'important', $schema ? 'pass' : 'fail', $schema ? __( 'Structured data (connected JSON-LD graph) is on', 'jat-search-intel' ) : __( 'Structured data is off', 'jat-search-intel' ), '', $schema ? '' : 'enable-schema' );

		$logo = ! empty( $s['knowledge_logo'] );
		$add( 'logo', 'trust', 'helpful', $logo ? 'pass' : 'warn', $logo ? __( 'Organization logo is set', 'jat-search-intel' ) : __( 'No organization logo in your schema', 'jat-search-intel' ), $logo ? '' : __( 'A logo helps search engines and AI assistants recognise your brand as an entity.', 'jat-search-intel' ), '', $logo ? '' : admin_url( 'admin.php?page=jat-search-intel' ) );

		$profiles = array_filter( (array) get_option( 'jat_search_intel_social_profiles', [] ) );
		$add(
			'same-as',
			'trust',
			'helpful',
			count( $profiles ) >= 2 ? 'pass' : 'warn',
			count( $profiles ) >= 2
				/* translators: %d: number of profiles. */
				? sprintf( __( '%d official profiles linked to your brand', 'jat-search-intel' ), count( $profiles ) )
				: __( 'Few official profiles linked to your brand', 'jat-search-intel' ),
			count( $profiles ) >= 2 ? '' : __( 'Brand mentions across the web are the strongest signal for being cited by AI. Linking your official profiles (sameAs) ties those mentions to you.', 'jat-search-intel' ),
			'',
			count( $profiles ) >= 2 ? '' : admin_url( 'admin.php?page=jat-search-intel-social' )
		);

		$authors_without_bio = $this->authors_without_bio();
		$add(
			'author-bios',
			'trust',
			'helpful',
			$authors_without_bio ? 'warn' : 'pass',
			$authors_without_bio
				/* translators: %d: number of authors. */
				? sprintf( _n( '%d author has no bio', '%d authors have no bio', $authors_without_bio, 'jat-search-intel' ), $authors_without_bio )
				: __( 'Every author has a bio', 'jat-search-intel' ),
			$authors_without_bio ? __( 'Author bios and credentials feed the Person schema behind E-E-A-T signals.', 'jat-search-intel' ) : '',
			'',
			$authors_without_bio ? admin_url( 'users.php' ) : ''
		);

		// ── Content ──
		$stats = $this->content_stats();
		if ( $stats['total'] > 0 ) {
			$stale_pct = (int) round( 100 * $stats['stale'] / $stats['total'] );
			$add(
				'freshness',
				'content',
				'helpful',
				$stale_pct > 30 ? 'warn' : 'pass',
				$stale_pct > 30
					/* translators: %d: percentage. */
					? sprintf( __( '%d%% of your content has not been updated in a year', 'jat-search-intel' ), $stale_pct )
					/* translators: %d: percentage. */
					: sprintf( __( '%d%% of your content was updated in the last year', 'jat-search-intel' ), 100 - $stale_pct ),
				$stale_pct > 30 ? __( 'AI assistants favour current pages, and pages refreshed every few months earn more AI referrals. Work through the Refresh Queue below.', 'jat-search-intel' ) : '',
				''
			);

			$missing_pct = (int) round( 100 * $stats['no_description'] / $stats['total'] );
			$add(
				'descriptions',
				'content',
				'helpful',
				$missing_pct > 25 ? 'warn' : 'pass',
				$missing_pct > 25
					/* translators: %d: percentage. */
					? sprintf( __( '%d%% of pages rely on an auto-generated description', 'jat-search-intel' ), $missing_pct )
					: __( 'Most pages have a written meta description', 'jat-search-intel' ),
				$missing_pct > 25 ? __( 'A written meta description doubles as the summary AI answers quote. Bulk Tools can draft them.', 'jat-search-intel' ) : '',
				'',
				$missing_pct > 25 ? admin_url( 'admin.php?page=jat-search-intel-bulk-tools' ) : ''
			);

			if ( $stats['analyzed'] < $stats['total'] ) {
				$add(
					'unscored',
					'content',
					'info',
					'info',
					/* translators: 1: analysed count, 2: total. */
					sprintf( __( '%1$d of %2$d pages have SEO/AEO/GEO scores', 'jat-search-intel' ), $stats['analyzed'], $stats['total'] ),
					__( 'Run a bulk rescore to see which pages need answer-first intros, FAQs, sources or entities.', 'jat-search-intel' ),
					'',
					admin_url( 'admin.php?page=jat-search-intel-bulk-tools' )
				);
			} elseif ( $stats['low_geo'] > 0 ) {
				$add(
					'low-geo',
					'content',
					'helpful',
					'warn',
					/* translators: %d: number of pages. */
					sprintf( _n( '%d page scores below 50 for AI search (GEO)', '%d pages score below 50 for AI search (GEO)', $stats['low_geo'], 'jat-search-intel' ), $stats['low_geo'] ),
					__( 'Open them and follow the GEO checks: answer-first intro, sources, definitions and an AI summary.', 'jat-search-intel' ),
					'',
					admin_url( 'edit.php' )
				);
			}
		}

		// ── Informational ──
		$signals = AI_Search::signals_from_settings( $s );
		$add( 'content-signals', 'control', 'info', $signals ? 'pass' : 'info', $signals ? __( 'Content Signals state how your content may be used', 'jat-search-intel' ) : __( 'No Content Signals set', 'jat-search-intel' ), $signals ? '' : __( 'Optional: say yes/no to search, AI answers and AI training in robots.txt.', 'jat-search-intel' ), '', admin_url( 'admin.php?page=jat-search-intel-geo' ) );

		$gsc = '' !== Google_Auth::client_email();
		$add( 'search-console', 'control', 'info', $gsc ? 'pass' : 'info', $gsc ? __( 'Search Console is connected for rank tracking', 'jat-search-intel' ) : __( 'Search Console is not connected', 'jat-search-intel' ), $gsc ? '' : __( 'Connect it to track positions and to rank the Refresh Queue by real clicks.', 'jat-search-intel' ), '', admin_url( 'admin.php?page=jat-search-intel-rank-tracker' ) );

		return $checks;
	}

	/**
	 * Score 0–100: passes count fully, warnings half.
	 *
	 * @param array $checks Checks from run().
	 * @return int
	 */
	public static function score( array $checks ): int {
		$total = 0;
		$got   = 0.0;
		foreach ( $checks as $check ) {
			$weight = self::WEIGHTS[ $check['level'] ] ?? 0;
			if ( ! $weight ) {
				continue;
			}
			$total += $weight;
			if ( 'pass' === $check['status'] ) {
				$got += $weight;
			} elseif ( 'warn' === $check['status'] ) {
				$got += $weight / 2;
			}
		}
		return $total ? (int) round( 100 * $got / $total ) : 100;
	}

	/**
	 * Parse robots.txt and return the important crawlers it blocks from the whole site.
	 *
	 * @param string|null $robots robots.txt content (default: what this site serves).
	 * @return string[]
	 */
	public function blocked_search_bots( ?string $robots = null ): array {
		if ( null === $robots ) {
			$robots = self::current_robots_txt();
		}
		$groups = self::parse_robots( $robots );
		$out    = [];
		foreach ( self::SEARCH_BOTS as $bot ) {
			$rules = $groups[ strtolower( $bot ) ] ?? ( $groups['*'] ?? [] );
			if ( in_array( '/', $rules, true ) ) {
				$out[] = $bot;
			}
		}
		return $out;
	}

	/**
	 * robots.txt as served: the physical file if present, else WordPress's virtual one.
	 *
	 * @return string
	 */
	public static function current_robots_txt(): string {
		if ( defined( 'ABSPATH' ) && is_file( ABSPATH . 'robots.txt' ) ) {
			return (string) file_get_contents( ABSPATH . 'robots.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		}
		$public = (string) get_option( 'blog_public', '1' );
		$output = "User-agent: *\n";
		if ( '0' === $public ) {
			$output .= "Disallow: /\n";
		} else {
			$output .= "Disallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
		}
		return (string) apply_filters( 'robots_txt', $output, $public ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
	}

	/**
	 * Map lowercase user agent => list of Disallow paths.
	 *
	 * @param string $robots robots.txt.
	 * @return array<string,string[]>
	 */
	public static function parse_robots( string $robots ): array {
		$groups  = [];
		$agents  = [];
		$in_rule = false;

		foreach ( preg_split( '/\r\n|\r|\n/', $robots ) as $line ) {
			$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $field, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
			$field                 = strtolower( $field );

			if ( 'user-agent' === $field ) {
				if ( $in_rule ) {
					$agents  = [];
					$in_rule = false;
				}
				$agents[]                          = strtolower( $value );
				$groups[ strtolower( $value ) ]    = $groups[ strtolower( $value ) ] ?? [];
			} elseif ( in_array( $field, [ 'disallow', 'allow' ], true ) ) {
				$in_rule = true;
				if ( 'disallow' === $field && '' !== $value ) {
					foreach ( $agents as $agent ) {
						$groups[ $agent ][] = $value;
					}
				}
			}
		}
		return $groups;
	}

	/**
	 * Whether our own AI crawler rules block a search or live-fetch bot.
	 *
	 * @return bool
	 */
	private function rules_block_search_bots(): bool {
		foreach ( (array) ( $this->settings['ai_crawler_rules'] ?? [] ) as $bot => $rule ) {
			$purpose = AI_Search::BOTS[ $bot ][1] ?? '';
			if ( 'block' === $rule && in_array( $purpose, [ 'search', 'user' ], true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the static front page is noindexed.
	 *
	 * @return bool
	 */
	private function front_page_noindexed(): bool {
		$front = (int) get_option( 'page_on_front' );
		return 'page' === get_option( 'show_on_front' ) && $front && get_post_meta( $front, '_jat_search_intel_noindex', true );
	}

	/**
	 * Active SEO plugins that print overlapping tags.
	 *
	 * @return string[]
	 */
	private function conflicting_plugins(): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$known = [
			'wordpress-seo/wp-seo.php'                    => 'Yoast SEO',
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
			'seo-by-rank-math/rank-math.php'              => 'Rank Math',
			'autodescription/autodescription.php'         => 'The SEO Framework',
			'wp-seopress/seopress.php'                    => 'SEOPress',
			'slim-seo/slim-seo.php'                       => 'Slim SEO',
		];
		$active = [];
		foreach ( $known as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $name;
			}
		}
		return $active;
	}

	/**
	 * Authors of published posts who have no biographical info.
	 *
	 * @return int
	 */
	private function authors_without_bio(): int {
		$count   = 0;
		$authors = get_users(
			[
				'has_published_posts' => (array) ( $this->settings['enabled_post_types'] ?? [ 'post' ] ),
				'fields'              => 'ID',
				'number'              => 50,
			]
		);
		foreach ( $authors as $author_id ) {
			if ( '' === trim( (string) get_the_author_meta( 'description', (int) $author_id ) ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Aggregate content statistics in a handful of queries.
	 *
	 * @return array{total:int, stale:int, no_description:int, analyzed:int, low_geo:int}
	 */
	public function content_stats(): array {
		global $wpdb;

		$types = array_values( array_filter( (array) ( $this->settings['enabled_post_types'] ?? [ 'post', 'page' ] ), 'post_type_exists' ) );
		if ( ! $types ) {
			return [ 'total' => 0, 'stale' => 0, 'no_description' => 0, 'analyzed' => 0, 'low_geo' => 0 ];
		}

		$in     = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Aggregate admin report; $in is a list of %s placeholders.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total,
					SUM( p.post_modified_gmt < %s ) AS stale,
					SUM( ( d.meta_value IS NULL OR d.meta_value = '' ) AND p.post_excerpt = '' ) AS no_description,
					SUM( g.meta_value IS NOT NULL ) AS analyzed,
					SUM( g.meta_value IS NOT NULL AND CAST( g.meta_value AS DECIMAL(5,1) ) < 50 ) AS low_geo
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '_jat_search_intel_description'
				LEFT JOIN {$wpdb->postmeta} g ON g.post_id = p.ID AND g.meta_key = '_jat_search_intel_geo_score'
				WHERE p.post_status = 'publish' AND p.post_password = '' AND p.post_type IN ({$in})",
				array_merge( [ $cutoff ], $types )
			),
			ARRAY_A
		);
		// phpcs:enable

		return [
			'total'          => (int) ( $row['total'] ?? 0 ),
			'stale'          => (int) ( $row['stale'] ?? 0 ),
			'no_description' => (int) ( $row['no_description'] ?? 0 ),
			'analyzed'       => (int) ( $row['analyzed'] ?? 0 ),
			'low_geo'        => (int) ( $row['low_geo'] ?? 0 ),
		];
	}

	// ── Refresh queue ───────────────────────────────────────────────────

	/**
	 * Pages most in need of a refresh: the longest-unchanged pages, weighted
	 * by real Search Console clicks when available.
	 *
	 * @param int $limit Rows.
	 * @return array<int,array{post_id:int,title:string,url:string,days:int,clicks:int,impressions:int,priority:float}>
	 */
	public function refresh_queue( int $limit = 20 ): array {
		global $wpdb;

		$types = array_values( array_filter( (array) ( $this->settings['enabled_post_types'] ?? [ 'post', 'page' ] ), 'post_type_exists' ) );
		if ( ! $types ) {
			return [];
		}
		$in = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Admin report; $in is a list of %s placeholders.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_modified_gmt FROM {$wpdb->posts}
				WHERE post_status = 'publish' AND post_password = '' AND post_type IN ({$in})
				ORDER BY post_modified_gmt ASC LIMIT 300",
				$types
			)
		);

		// Search Console clicks per URL over the last 28 days, if the rank tracker has data.
		$traffic = [];
		$table   = $wpdb->prefix . Rank_Tracker::TABLE;
		$exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $exists ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT url, SUM(clicks) AS clicks, SUM(impressions) AS impressions FROM %i WHERE day >= %s GROUP BY url', $table, gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS ) )
			);
			foreach ( (array) $rows as $row ) {
				$traffic[ untrailingslashit( (string) $row->url ) ] = [ (int) $row->clicks, (int) $row->impressions ];
			}
		}
		// phpcs:enable

		$now   = time();
		$queue = [];
		foreach ( (array) $posts as $post ) {
			$url         = (string) get_permalink( (int) $post->ID );
			$days        = (int) floor( ( $now - (int) strtotime( $post->post_modified_gmt . ' UTC' ) ) / DAY_IN_SECONDS );
			list( $clicks, $impressions ) = $traffic[ untrailingslashit( $url ) ] ?? [ 0, 0 ];
			if ( $days < 90 ) {
				continue; // Refreshed this quarter.
			}
			$queue[] = [
				'post_id'     => (int) $post->ID,
				'title'       => (string) $post->post_title,
				'url'         => $url,
				'days'        => $days,
				'clicks'      => $clicks,
				'impressions' => $impressions,
				'priority'    => round( $days * ( 1 + log( 1 + $clicks * 10 + $impressions ) ), 1 ),
			];
		}

		usort(
			$queue,
			static function ( $a, $b ) {
				return $b['priority'] <=> $a['priority'];
			}
		);

		return array_slice( $queue, 0, $limit );
	}

	// ── Fixes ───────────────────────────────────────────────────────────

	/**
	 * Apply a one-click fix that only changes this plugin's own settings.
	 *
	 * @param string $fix Fix id.
	 * @return bool Whether something changed.
	 */
	public static function apply_fix( string $fix ): bool {
		$settings = get_option( 'jat_search_intel_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];

		switch ( $fix ) {
			case 'enable-sitemap':
				$settings['enable_sitemap'] = true;
				break;
			case 'enable-indexnow':
				$settings['enable_indexnow'] = true;
				if ( empty( $settings['indexnow_api_key'] ) ) {
					$settings['indexnow_api_key'] = IndexNow::generate_key();
				}
				break;
			case 'enable-llms':
				$settings['enable_geo']      = true;
				$settings['enable_llms_txt'] = true;
				break;
			case 'enable-markdown':
				$settings['enable_markdown_endpoints'] = true;
				break;
			case 'enable-schema':
				$settings['enable_schema'] = true;
				break;
			case 'allow-ai-search':
				foreach ( (array) ( $settings['ai_crawler_rules'] ?? [] ) as $bot => $rule ) {
					if ( in_array( AI_Search::BOTS[ $bot ][1] ?? '', [ 'search', 'user' ], true ) ) {
						$settings['ai_crawler_rules'][ $bot ] = 'allow';
					}
				}
				break;
			default:
				return false;
		}

		// Write directly: these are known-safe values, and the settings sanitizer is page-scoped.
		remove_all_filters( 'sanitize_option_jat_search_intel_settings' );
		update_option( 'jat_search_intel_settings', $settings );
		Upgrade::request_rewrite_flush();
		return true;
	}

	/**
	 * admin-post handler for fixes.
	 */
	public function handle_fix(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'jat-search-intel' ), 403 );
		}
		check_admin_referer( 'jat_search_intel_readiness_fix' );

		$fix = isset( $_POST['fix'] ) ? sanitize_key( wp_unslash( $_POST['fix'] ) ) : '';
		$ok  = self::apply_fix( $fix );

		wp_safe_redirect( add_query_arg( 'jsi_fixed', $ok ? $fix : 'none', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	// ── Admin page ──────────────────────────────────────────────────────

	/**
	 * Register the page near the top of the plugin menu.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'jat-search-intel',
			__( 'AI Search Readiness', 'jat-search-intel' ),
			__( 'Readiness', 'jat-search-intel' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_admin_page' ],
			1
		);
	}

	/**
	 * Render the audit.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$checks = $this->run();
		$score  = self::score( $checks );
		$counts = array_count_values( array_column( $checks, 'status' ) );
		$groups = [
			'access'    => __( 'Can search engines and AI crawlers reach you?', 'jat-search-intel' ),
			'discovery' => __( 'Do they find new and updated pages quickly?', 'jat-search-intel' ),
			'trust'     => __( 'Do they understand and trust who you are?', 'jat-search-intel' ),
			'content'   => __( 'Is your content easy to quote and current?', 'jat-search-intel' ),
			'control'   => __( 'Controls and connections', 'jat-search-intel' ),
		];
		$icons  = [
			'pass' => [ 'yes-alt', '#00a32a', __( 'Good', 'jat-search-intel' ) ],
			'warn' => [ 'warning', '#dba617', __( 'Improve', 'jat-search-intel' ) ],
			'fail' => [ 'dismiss', '#d63638', __( 'Fix', 'jat-search-intel' ) ],
			'info' => [ 'info-outline', '#2271b1', __( 'Info', 'jat-search-intel' ) ],
		];
		$color  = $score >= 80 ? '#00a32a' : ( $score >= 50 ? '#dba617' : '#d63638' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag after redirect.
		$fixed = isset( $_GET['jsi_fixed'] ) ? sanitize_key( wp_unslash( $_GET['jsi_fixed'] ) ) : '';

		echo '<div class="wrap"><h1>' . esc_html__( 'AI Search Readiness', 'jat-search-intel' ) . '</h1>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag after redirect.
		if ( isset( $_GET['jsi_setup'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Setup complete. Here is where your site stands; work through anything marked Fix or Improve.', 'jat-search-intel' ) . '</p></div>';
		}
		if ( $fixed && 'none' !== $fixed ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Fixed. The check below has been re-run.', 'jat-search-intel' ) . '</p></div>';
		}

		echo '<div style="display:flex;gap:24px;align-items:center;margin:16px 0 8px">';
		echo '<div style="width:96px;height:96px;border-radius:50%;border:8px solid ' . esc_attr( $color ) . ';display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:600">' . esc_html( (string) $score ) . '</div>';
		echo '<div><p style="font-size:14px;margin:0 0 6px">' . esc_html__( 'How ready this site is to be found by search engines and cited by AI assistants such as ChatGPT, Google AI Mode, Perplexity and Copilot.', 'jat-search-intel' ) . '</p>';
		/* translators: 1: issues to fix, 2: improvements, 3: passed checks. */
		echo '<p style="margin:0;color:#50575e">' . esc_html( sprintf( __( '%1$d to fix · %2$d to improve · %3$d passed', 'jat-search-intel' ), (int) ( $counts['fail'] ?? 0 ), (int) ( $counts['warn'] ?? 0 ), (int) ( $counts['pass'] ?? 0 ) ) ) . '</p></div></div>';

		foreach ( $groups as $group => $heading ) {
			$items = array_filter( $checks, static function ( $c ) use ( $group ) {
				return $c['group'] === $group;
			} );
			if ( ! $items ) {
				continue;
			}
			usort( $items, static function ( $a, $b ) {
				$order = [ 'fail' => 0, 'warn' => 1, 'info' => 2, 'pass' => 3 ];
				return $order[ $a['status'] ] <=> $order[ $b['status'] ];
			} );

			echo '<h2 style="margin-top:28px">' . esc_html( $heading ) . '</h2><table class="widefat striped" style="max-width:1000px"><tbody>';
			foreach ( $items as $c ) {
				$icon = $icons[ $c['status'] ];
				echo '<tr><td style="width:28px"><span class="dashicons dashicons-' . esc_attr( $icon[0] ) . '" style="color:' . esc_attr( $icon[1] ) . '" title="' . esc_attr( $icon[2] ) . '"></span></td>';
				echo '<td><strong>' . esc_html( $c['title'] ) . '</strong>';
				if ( '' !== $c['detail'] ) {
					echo '<br><span style="color:#50575e">' . esc_html( $c['detail'] ) . '</span>';
				}
				echo '</td><td style="width:170px;text-align:right">';
				if ( '' !== $c['fix'] ) {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
					wp_nonce_field( 'jat_search_intel_readiness_fix' );
					echo '<input type="hidden" name="action" value="jat_search_intel_readiness_fix" /><input type="hidden" name="fix" value="' . esc_attr( $c['fix'] ) . '" />';
					echo '<button class="button button-primary">' . esc_html__( 'Fix it', 'jat-search-intel' ) . '</button></form>';
				} elseif ( '' !== $c['link'] && 'pass' !== $c['status'] ) {
					echo '<a class="button" href="' . esc_url( $c['link'] ) . '">' . esc_html__( 'Open', 'jat-search-intel' ) . '</a>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		// Refresh queue.
		$queue = $this->refresh_queue();
		echo '<h2 style="margin-top:32px">' . esc_html__( 'Refresh Queue', 'jat-search-intel' ) . '</h2>';
		echo '<p style="max-width:800px">' . esc_html__( 'Pages that have gone longest without an update, weighted by Search Console clicks when connected. Review the facts, add anything new, refresh the date-sensitive parts, then save.', 'jat-search-intel' ) . '</p>';
		if ( $queue ) {
			echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>' . esc_html__( 'Page', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Last updated', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Clicks (28d)', 'jat-search-intel' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $queue as $row ) {
				/* translators: %s: number of days. */
				$age = sprintf( _n( '%s day ago', '%s days ago', $row['days'], 'jat-search-intel' ), number_format_i18n( $row['days'] ) );
				echo '<tr><td><a href="' . esc_url( $row['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $row['title'] ?: __( '(no title)', 'jat-search-intel' ) ) . '</a></td><td>' . esc_html( $age ) . '</td><td>' . esc_html( $row['clicks'] ? number_format_i18n( $row['clicks'] ) : '—' ) . '</td>';
				echo '<td style="text-align:right"><a class="button button-small" href="' . esc_url( (string) get_edit_post_link( $row['post_id'], 'raw' ) ) . '">' . esc_html__( 'Edit', 'jat-search-intel' ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p><em>' . esc_html__( 'Nothing to refresh: every page has been updated in the last 90 days.', 'jat-search-intel' ) . '</em></p>';
		}

		echo '</div>';
	}
}
