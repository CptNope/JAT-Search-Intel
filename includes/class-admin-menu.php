<?php
/**
 * Compact admin menu.
 *
 * Modules register their own pages as before. After they have done so, this
 * class folds them into six sidebar entries — Readiness, Settings, Redirects,
 * AI Search, Reports and Tools — and shows the other pages of each group as
 * tabs. Every page keeps its URL, so links, forms and bookmarks keep working.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu grouping.
 */
class Admin_Menu {

	public const PARENT = 'jat-search-intel';

	/** @var array<string,string> Visible page slug => tab label, for the current request. */
	private $tabs = [];

	/** @var string Group key of the page being viewed. */
	private $current_group = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'regroup' ], 999 );
		add_filter( 'parent_file', [ $this, 'parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'submenu_file' ], 10, 2 );
		add_action( 'in_admin_header', [ $this, 'render_tabs' ] );
		add_action( 'admin_head', [ $this, 'menu_css' ] );
	}

	/**
	 * Hide grouped pages from the sidebar (they are reachable through the tabs).
	 */
	public function menu_css(): void {
		echo '<style id="jsi-menu">#adminmenu .jsi-menu-hidden{display:none}</style>' . "\n";
	}

	/**
	 * Groups in sidebar order: key => [ label, page slugs in tab order ].
	 *
	 * @return array<string,array{0:string,1:string[]}>
	 */
	public static function groups(): array {
		$groups = [
			'readiness' => [ __( 'Readiness', 'jat-search-intel' ), [ 'jat-search-intel-readiness' ] ],
			'settings'  => [
				__( 'Settings', 'jat-search-intel' ),
				[ 'jat-search-intel', 'jat-search-intel-seo', 'jat-search-intel-aeo', 'jat-search-intel-geo', 'jat-search-intel-sitemap', 'jat-search-intel-social', 'jat-search-intel-robots', 'jat-search-intel-ai' ],
			],
			'redirects' => [ __( 'Redirects', 'jat-search-intel' ), [ 'jat-search-intel-redirects', 'jat-search-intel-broken-links' ] ],
			'ai'        => [
				__( 'AI Search', 'jat-search-intel' ),
				[ 'jat-search-intel-ai-search', 'jat-search-intel-visibility', 'jat-search-intel-citation-graph', 'jat-search-intel-authority' ],
			],
			'reports'   => [
				__( 'Reports', 'jat-search-intel' ),
				[ 'jat-search-intel-performance', 'jat-search-intel-rank-tracker', 'jat-search-intel-analytics', 'jat-search-intel-ga4', 'jat-search-intel-cwv', 'jat-search-intel-content-decay', 'jat-search-intel-cannibalization', 'jat-search-intel-reports' ],
			],
			'tools'     => [
				__( 'Tools', 'jat-search-intel' ),
				[ 'jat-search-intel-bulk-tools', 'jat-search-intel-bulk-editor', 'jat-search-intel-advanced-linking', 'jat-search-intel-migration', 'jat-search-intel-setup' ],
			],
		];

		/**
		 * Filters the admin menu groups.
		 *
		 * @param array $groups key => [ label, page slugs ].
		 */
		return (array) apply_filters( 'jat_search_intel_admin_menu_groups', $groups );
	}

	/**
	 * Group key for a page slug, or '' if the page is not one of ours.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	public static function group_of( string $slug ): string {
		foreach ( self::groups() as $key => $group ) {
			if ( in_array( $slug, $group[1], true ) ) {
				return $key;
			}
		}
		return '';
	}

	/**
	 * Reorder the plugin's submenu: one visible entry per group, pointing at its first available page.
	 */
	public function regroup(): void {
		global $submenu;

		if ( empty( $submenu[ self::PARENT ] ) || ! is_array( $submenu[ self::PARENT ] ) ) {
			return;
		}

		$registered = [];
		foreach ( $submenu[ self::PARENT ] as $item ) {
			$registered[ $item[2] ] = $item; // [ menu title, capability, slug, page title ].
		}

		// Every page stays in the submenu (WordPress grants page access from it); pages after
		// the first in each group get a class that hides them, and appear as tabs instead.
		$rebuilt  = [];
		$assigned = [];
		foreach ( self::groups() as $key => list( $label, $slugs ) ) {
			$first = true;
			foreach ( $slugs as $slug ) {
				if ( ! isset( $registered[ $slug ] ) ) {
					continue;
				}
				$item              = $registered[ $slug ];
				$assigned[ $slug ] = true;
				if ( $first ) {
					$item[3] = $item[3] ?? $item[0];
					$item[0] = $label;
					$first   = false;
				} else {
					$item[4] = trim( ( $item[4] ?? '' ) . ' jsi-menu-hidden' );
				}
				$rebuilt[] = $item;
			}
		}

		// Pages a group does not know about (third-party add-ons) stay visible at the end.
		foreach ( $registered as $slug => $item ) {
			if ( empty( $assigned[ $slug ] ) ) {
				$rebuilt[] = $item;
			}
		}

		$submenu[ self::PARENT ] = $rebuilt; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering our own submenu is the purpose of this class.

		$this->prepare_current_page( $registered );
	}

	/**
	 * Work out tabs and the page title for the page being viewed.
	 *
	 * @param array<string,array> $registered Registered pages by slug.
	 */
	private function prepare_current_page( array $registered ): void {
		$page = self::current_page();
		$key  = '' === $page ? '' : self::group_of( $page );
		if ( '' === $key ) {
			return;
		}
		$this->current_group = $key;

		foreach ( self::groups()[ $key ][1] as $slug ) {
			if ( isset( $registered[ $slug ] ) && current_user_can( (string) $registered[ $slug ][1] ) ) {
				$this->tabs[ $slug ] = (string) $registered[ $slug ][0];
			}
		}
	}

	/**
	 * Current ?page= value.
	 *
	 * @return string
	 */
	private static function current_page(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the admin page slug only.
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	}

	/**
	 * Keep our top-level menu open on hidden pages.
	 *
	 * @param string $parent_file Parent file.
	 * @return string
	 */
	public function parent_file( $parent_file ) {
		return '' !== $this->current_group ? self::PARENT : $parent_file;
	}

	/**
	 * Highlight the group's entry on hidden pages.
	 *
	 * @param string|null $submenu_file Submenu file.
	 * @param string      $parent_file  Parent file.
	 * @return string|null
	 */
	public function submenu_file( $submenu_file, $parent_file = '' ) {
		unset( $parent_file );
		if ( '' === $this->current_group || ! $this->tabs ) {
			return $submenu_file;
		}
		return (string) array_key_first( $this->tabs );
	}

	/**
	 * Tab bar across the top of each page in a group with more than one page.
	 */
	public function render_tabs(): void {
		if ( count( $this->tabs ) < 2 ) {
			return;
		}
		$page = self::current_page();

		echo '<nav class="nav-tab-wrapper jsi-group-tabs" style="margin:12px 20px 0 2px" aria-label="' . esc_attr__( 'JAT Search Intel sections', 'jat-search-intel' ) . '">';
		foreach ( $this->tabs as $slug => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( admin_url( 'admin.php?page=' . $slug ) ),
				$slug === $page ? ' nav-tab-active' : '',
				$slug === $page ? ' aria-current="page"' : '',
				esc_html( wp_strip_all_tags( $label ) )
			);
		}
		echo '</nav>';
	}
}
