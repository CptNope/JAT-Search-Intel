<?php
/**
 * Page Performance report: is the work paying off, page by page?
 *
 * One table that puts Search Console clicks and impressions, AI assistant
 * referrals, AI crawler visits, GEO score and freshness side by side, with a
 * plain-language next step for each page.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-page performance.
 */
class Performance_Report {

	public const PAGE_SLUG = 'jat-search-intel-performance';

	private const MAX_ROWS = 100;

	private const SORTABLE = [ 'clicks', 'impressions', 'ai_referrals', 'ai_crawls', 'geo', 'days' ];

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings (unused; kept for the module contract).
	 */
	public function __construct( array $settings = [] ) {
		unset( $settings );
		add_action( 'admin_menu', [ $this, 'register_page' ], 20 );
	}

	/**
	 * Register under Reports.
	 */
	public function register_page(): void {
		add_submenu_page(
			Admin_Menu::PARENT,
			__( 'Page Performance', 'jat-search-intel' ),
			__( 'Page Performance', 'jat-search-intel' ),
			'edit_others_posts',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Combine the data sources into rows keyed by path. Pure function for tests.
	 *
	 * @param array<string,int>                 $referrals   path => AI referral visits.
	 * @param array<string,int>                 $crawls      path => AI crawler visits.
	 * @param array<string,array{0:int,1:int}>  $search      path => [ clicks, impressions ].
	 * @return array<string,array{path:string,clicks:int,impressions:int,ai_referrals:int,ai_crawls:int}>
	 */
	public static function merge( array $referrals, array $crawls, array $search ): array {
		$rows  = [];
		$blank = static function ( string $path ): array {
			return [
				'path'         => $path,
				'clicks'       => 0,
				'impressions'  => 0,
				'ai_referrals' => 0,
				'ai_crawls'    => 0,
			];
		};
		foreach ( $referrals as $path => $n ) {
			$path                        = self::normalize_path( (string) $path );
			$rows[ $path ]               = $rows[ $path ] ?? $blank( $path );
			$rows[ $path ]['ai_referrals'] += (int) $n;
		}
		foreach ( $crawls as $path => $n ) {
			$path                     = self::normalize_path( (string) $path );
			$rows[ $path ]            = $rows[ $path ] ?? $blank( $path );
			$rows[ $path ]['ai_crawls'] += (int) $n;
		}
		foreach ( $search as $path => $pair ) {
			$path                        = self::normalize_path( (string) $path );
			$rows[ $path ]               = $rows[ $path ] ?? $blank( $path );
			$rows[ $path ]['clicks']      += (int) $pair[0];
			$rows[ $path ]['impressions'] += (int) $pair[1];
		}
		return $rows;
	}

	/**
	 * Path with a trailing slash and no query string, so URLs from different sources match.
	 *
	 * @param string $url URL or path.
	 * @return string
	 */
	public static function normalize_path( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = '' === $path ? '/' : $path;
		if ( ! preg_match( '/\.[a-z0-9]{2,4}$/i', $path ) ) {
			$path = trailingslashit( $path );
		}
		return $path;
	}

	/**
	 * The one thing to do next for a page.
	 *
	 * @param array $row Row with clicks, impressions, ai_referrals, ai_crawls, geo, days.
	 * @return string
	 */
	public static function next_step( array $row ): string {
		$impressions = (int) ( $row['impressions'] ?? 0 );
		$clicks      = (int) ( $row['clicks'] ?? 0 );
		$days        = (int) ( $row['days'] ?? 0 );
		$geo         = $row['geo'] ?? null;

		if ( $days > 365 && ( $clicks > 0 || (int) ( $row['ai_referrals'] ?? 0 ) > 0 ) ) {
			return __( 'Earning traffic but over a year old: refresh it before it slips.', 'jat-search-intel' );
		}
		if ( $impressions >= 100 && $clicks / max( 1, $impressions ) < 0.01 ) {
			return __( 'Seen often in Google but rarely clicked: rewrite the title and meta description.', 'jat-search-intel' );
		}
		if ( (int) ( $row['ai_crawls'] ?? 0 ) >= 5 && 0 === (int) ( $row['ai_referrals'] ?? 0 ) ) {
			return __( 'Read by AI crawlers but never sends visitors: add an answer-first intro and sourced facts (see Passage Coach).', 'jat-search-intel' );
		}
		if ( null !== $geo && (float) $geo < 50 ) {
			return __( 'Low GEO score: open it and work through the Passage Coach.', 'jat-search-intel' );
		}
		if ( $days > 365 ) {
			return __( 'Not updated in over a year.', 'jat-search-intel' );
		}
		return '';
	}

	/**
	 * Build the report rows from live data.
	 *
	 * @param string $orderby Column.
	 * @return array{rows:array<int,array>,has_search:bool,has_ai:bool}
	 */
	public function rows( string $orderby = 'clicks' ): array {
		global $wpdb;

		$traffic   = get_option( AI_Search::TRAFFIC_OPTION, [] );
		$traffic   = is_array( $traffic ) ? $traffic : [];
		$flatten   = static function ( $by_label ): array {
			$out = [];
			foreach ( (array) $by_label as $pages ) {
				foreach ( (array) $pages as $path => $n ) {
					$out[ $path ] = ( $out[ $path ] ?? 0 ) + (int) $n;
				}
			}
			return $out;
		};
		$referrals = $flatten( $traffic['referrals']['pages'] ?? [] );
		$crawls    = $flatten( $traffic['crawlers']['pages'] ?? [] );

		$search = [];
		$table  = $wpdb->prefix . Rank_Tracker::TABLE;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Admin report over our own table.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			$results = $wpdb->get_results(
				$wpdb->prepare( 'SELECT url, SUM(clicks) AS clicks, SUM(impressions) AS impressions FROM %i WHERE day >= %s AND url <> %s GROUP BY url', $table, gmdate( 'Y-m-d', time() - 28 * DAY_IN_SECONDS ), '' )
			);
			foreach ( (array) $results as $r ) {
				$search[ (string) $r->url ] = [ (int) $r->clicks, (int) $r->impressions ];
			}
		}
		// phpcs:enable

		$merged = self::merge( $referrals, $crawls, $search );

		// Nothing tracked yet: list recent content so the table is still useful.
		if ( ! $merged ) {
			foreach ( get_posts( [ 'post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => 25, 'fields' => 'ids', 'orderby' => 'modified' ] ) as $id ) {
				$path            = self::normalize_path( (string) get_permalink( (int) $id ) );
				$merged[ $path ] = self::merge( [], [], [ $path => [ 0, 0 ] ] )[ $path ];
			}
		}

		$rows = [];
		$now  = time();
		foreach ( $merged as $path => $row ) {
			$post_id = (int) url_to_postid( home_url( $path ) );
			if ( ! $post_id && '/' === $path ) {
				$post_id = (int) get_option( 'page_on_front' );
			}
			$post         = $post_id ? get_post( $post_id ) : null;
			$geo_meta     = $post ? get_post_meta( $post_id, '_jat_search_intel_geo_score', true ) : '';
			$row['post']  = $post_id;
			$row['title'] = $post ? get_the_title( $post ) : $path;
			$row['geo']   = '' === $geo_meta ? null : (float) $geo_meta;
			$row['days']  = $post ? (int) floor( ( $now - (int) strtotime( $post->post_modified_gmt . ' UTC' ) ) / DAY_IN_SECONDS ) : 0;
			$row['next']  = self::next_step( $row );
			$rows[]       = $row;
		}

		$orderby = in_array( $orderby, self::SORTABLE, true ) ? $orderby : 'clicks';
		usort(
			$rows,
			static function ( $a, $b ) use ( $orderby ) {
				$primary = ( $b[ $orderby ] ?? 0 ) <=> ( $a[ $orderby ] ?? 0 );
				return 0 !== $primary ? $primary : ( $b['ai_referrals'] + $b['clicks'] ) <=> ( $a['ai_referrals'] + $a['clicks'] );
			}
		);

		return [
			'rows'       => array_slice( $rows, 0, self::MAX_ROWS ),
			'has_search' => (bool) $search,
			'has_ai'     => $referrals || $crawls,
		];
	}

	/**
	 * Render.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sort column only.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'clicks';
		$data    = $this->rows( $orderby );

		$columns = [
			'clicks'       => __( 'Search clicks', 'jat-search-intel' ),
			'impressions'  => __( 'Impressions', 'jat-search-intel' ),
			'ai_referrals' => __( 'AI referrals', 'jat-search-intel' ),
			'ai_crawls'    => __( 'AI crawls', 'jat-search-intel' ),
			'geo'          => __( 'GEO score', 'jat-search-intel' ),
			'days'         => __( 'Last updated', 'jat-search-intel' ),
		];

		echo '<div class="wrap"><h1>' . esc_html__( 'Page Performance', 'jat-search-intel' ) . '</h1>';
		echo '<p style="max-width:860px">' . esc_html__( 'Google results, AI assistant visits and AI crawler activity for each page, with the next thing to do. Search columns cover the last 28 days; AI columns count everything since tracking began.', 'jat-search-intel' ) . '</p>';

		$notes = [];
		if ( ! $data['has_search'] ) {
			$notes[] = '<a href="' . esc_url( admin_url( 'admin.php?page=jat-search-intel-rank-tracker' ) ) . '">' . esc_html__( 'Connect Search Console', 'jat-search-intel' ) . '</a> ' . esc_html__( 'to fill in clicks and impressions.', 'jat-search-intel' );
		}
		if ( ! $data['has_ai'] ) {
			$notes[] = '<a href="' . esc_url( admin_url( 'admin.php?page=jat-search-intel-geo' ) ) . '">' . esc_html__( 'Turn on AI traffic tracking', 'jat-search-intel' ) . '</a> ' . esc_html__( 'to see AI referrals and crawler visits.', 'jat-search-intel' );
		}
		foreach ( $notes as $note ) {
			echo '<div class="notice notice-info inline"><p>' . wp_kses( $note, [ 'a' => [ 'href' => [] ] ] ) . '</p></div>';
		}

		echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>' . esc_html__( 'Page', 'jat-search-intel' ) . '</th>';
		foreach ( $columns as $key => $label ) {
			$url = add_query_arg( [ 'page' => self::PAGE_SLUG, 'orderby' => $key ], admin_url( 'admin.php' ) );
			printf(
				'<th style="white-space:nowrap"><a href="%1$s"%2$s>%3$s%4$s</a></th>',
				esc_url( $url ),
				$key === $orderby ? ' aria-sort="descending"' : '',
				esc_html( $label ),
				$key === $orderby ? ' ▾' : ''
			);
		}
		echo '<th>' . esc_html__( 'Next step', 'jat-search-intel' ) . '</th></tr></thead><tbody>';

		foreach ( $data['rows'] as $row ) {
			echo '<tr><td><a href="' . esc_url( home_url( $row['path'] ) ) . '" target="_blank" rel="noopener">' . esc_html( wp_strip_all_tags( (string) $row['title'] ) ) . '</a>';
			if ( $row['post'] ) {
				echo '<br><a href="' . esc_url( (string) get_edit_post_link( $row['post'], 'raw' ) ) . '" style="font-size:12px">' . esc_html__( 'Edit', 'jat-search-intel' ) . '</a>';
			}
			echo '</td>';
			foreach ( [ 'clicks', 'impressions', 'ai_referrals', 'ai_crawls' ] as $key ) {
				echo '<td>' . esc_html( $row[ $key ] ? number_format_i18n( $row[ $key ] ) : '—' ) . '</td>';
			}
			echo '<td>' . esc_html( null === $row['geo'] ? '—' : (string) round( $row['geo'] ) ) . '</td>';
			/* translators: %s: number of days. */
			echo '<td style="white-space:nowrap">' . esc_html( $row['post'] ? sprintf( _n( '%s day ago', '%s days ago', $row['days'], 'jat-search-intel' ), number_format_i18n( $row['days'] ) ) : '—' ) . '</td>';
			echo '<td>' . esc_html( $row['next'] ) . '</td></tr>';
		}
		if ( ! $data['rows'] ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No published content yet.', 'jat-search-intel' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
