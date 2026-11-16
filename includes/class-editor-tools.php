<?php
/**
 * Editing shortcuts: headline scoring endpoint, Quick Edit and Bulk Edit
 * in the posts list, and an SEO menu in the admin bar.
 *
 * @package JAT_Search_Intel
 * @since 3.8.0
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor tools.
 */
class Editor_Tools {

	private const NONCE_ACTION = 'jat_search_intel_inline_edit';
	private const NONCE_FIELD  = 'jat_search_intel_inline_nonce';
	private const COLUMN       = 'si_scores';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 90 );

		if ( is_admin() ) {
			add_action( 'manage_posts_custom_column', [ $this, 'inline_data' ], 20, 2 );
			add_action( 'manage_pages_custom_column', [ $this, 'inline_data' ], 20, 2 );
			add_action( 'quick_edit_custom_box', [ $this, 'quick_edit_box' ], 10, 2 );
			add_action( 'bulk_edit_custom_box', [ $this, 'bulk_edit_box' ], 10, 2 );
			add_action( 'save_post', [ $this, 'save_inline' ], 10, 2 );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_list_script' ] );
		}
	}

	// ── Headline endpoint ────────────────────────────────────────────────────

	/**
	 * Register POST /headline.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest_Api::NAMESPACE,
			'/headline',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_headline' ],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'headline' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Score a headline.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_headline( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( Headline_Analyzer::analyze( (string) $request['headline'] ), 200 );
	}

	// ── Quick Edit / Bulk Edit ───────────────────────────────────────────────

	/**
	 * Hidden per-row values that the Quick Edit script copies into the form.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function inline_data( $column, $post_id ): void {
		if ( self::COLUMN !== $column || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		printf(
			'<div class="hidden jsi-inline-data" data-title="%1$s" data-description="%2$s" data-keyword="%3$s" data-noindex="%4$s"></div>',
			esc_attr( (string) get_post_meta( $post_id, '_jat_search_intel_title', true ) ),
			esc_attr( (string) get_post_meta( $post_id, '_jat_search_intel_description', true ) ),
			esc_attr( (string) get_post_meta( $post_id, '_jat_search_intel_focus_keyword', true ) ),
			esc_attr( '1' === (string) get_post_meta( $post_id, '_jat_search_intel_noindex', true ) ? '1' : '' )
		);
	}

	/**
	 * Fields in the Quick Edit form.
	 *
	 * @param string $column    Column key.
	 * @param string $post_type Post type.
	 */
	public function quick_edit_box( $column, $post_type ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<fieldset class="inline-edit-col-left jsi-quick-edit" style="clear:both">
			<div class="inline-edit-col">
				<span class="title inline-edit-categories-label"><?php esc_html_e( 'JAT Search Intel', 'jat-search-intel' ); ?></span>
				<label>
					<span class="title"><?php esc_html_e( 'SEO title', 'jat-search-intel' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="jsi_title" value="" maxlength="200"></span>
				</label>
				<label>
					<span class="title"><?php esc_html_e( 'Description', 'jat-search-intel' ); ?></span>
					<span class="input-text-wrap"><textarea name="jsi_description" rows="2" maxlength="500"></textarea></span>
				</label>
				<label>
					<span class="title"><?php esc_html_e( 'Keyphrase', 'jat-search-intel' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="jsi_keyword" value="" maxlength="200"></span>
				</label>
				<label class="alignleft">
					<input type="checkbox" name="jsi_noindex" value="1">
					<span class="checkbox-title"><?php esc_html_e( 'Hide from search engines (noindex)', 'jat-search-intel' ); ?></span>
				</label>
				<input type="hidden" name="jsi_quick_edit" value="1">
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Fields in the Bulk Edit form.
	 *
	 * @param string $column    Column key.
	 * @param string $post_type Post type.
	 */
	public function bulk_edit_box( $column, $post_type ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<fieldset class="inline-edit-col-right jsi-bulk-edit">
			<div class="inline-edit-col">
				<span class="title inline-edit-categories-label"><?php esc_html_e( 'JAT Search Intel', 'jat-search-intel' ); ?></span>
				<label class="inline-edit-status alignleft">
					<span class="title"><?php esc_html_e( 'Search engines', 'jat-search-intel' ); ?></span>
					<select name="jsi_bulk_noindex">
						<option value=""><?php esc_html_e( '— No change —', 'jat-search-intel' ); ?></option>
						<option value="index"><?php esc_html_e( 'Show (index)', 'jat-search-intel' ); ?></option>
						<option value="noindex"><?php esc_html_e( 'Hide (noindex)', 'jat-search-intel' ); ?></option>
					</select>
				</label>
				<label class="inline-edit-status alignleft">
					<span class="title"><?php esc_html_e( 'Links', 'jat-search-intel' ); ?></span>
					<select name="jsi_bulk_nofollow">
						<option value=""><?php esc_html_e( '— No change —', 'jat-search-intel' ); ?></option>
						<option value="follow"><?php esc_html_e( 'Follow', 'jat-search-intel' ); ?></option>
						<option value="nofollow"><?php esc_html_e( 'Nofollow', 'jat-search-intel' ); ?></option>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save Quick Edit and Bulk Edit values.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_inline( $post_id, $post = null ): void {
		if ( ! isset( $_REQUEST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Quick Edit: one post, every field.
		if ( ! empty( $_POST['jsi_quick_edit'] ) ) {
			// Titles and descriptions may use %variables%, which sanitize_text_field() would damage.
			$title = isset( $_POST['jsi_title'] ) ? Title_Engine::sanitize_template( (string) wp_unslash( $_POST['jsi_title'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Title_Engine::sanitize_template().
			self::put( (int) $post_id, '_jat_search_intel_title', $title );
			$keyword = isset( $_POST['jsi_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['jsi_keyword'] ) ) : '';
			self::put( (int) $post_id, '_jat_search_intel_focus_keyword', $keyword );
			$description = isset( $_POST['jsi_description'] ) ? Title_Engine::sanitize_template( (string) wp_unslash( $_POST['jsi_description'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Title_Engine::sanitize_template().
			self::put( (int) $post_id, '_jat_search_intel_description', $description );
			self::put( (int) $post_id, '_jat_search_intel_noindex', ! empty( $_POST['jsi_noindex'] ) ? '1' : '' );
			return;
		}

		// Bulk Edit: only the robots choices, and only when changed.
		$noindex  = isset( $_REQUEST['jsi_bulk_noindex'] ) ? sanitize_key( wp_unslash( $_REQUEST['jsi_bulk_noindex'] ) ) : '';
		$nofollow = isset( $_REQUEST['jsi_bulk_nofollow'] ) ? sanitize_key( wp_unslash( $_REQUEST['jsi_bulk_nofollow'] ) ) : '';
		if ( in_array( $noindex, [ 'index', 'noindex' ], true ) ) {
			self::put( (int) $post_id, '_jat_search_intel_noindex', 'noindex' === $noindex ? '1' : '' );
		}
		if ( in_array( $nofollow, [ 'follow', 'nofollow' ], true ) ) {
			self::put( (int) $post_id, '_jat_search_intel_nofollow', 'nofollow' === $nofollow ? '1' : '' );
		}
	}

	/**
	 * Update or delete a meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $value   Value ('' deletes).
	 */
	private static function put( int $post_id, string $key, string $value ): void {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Load the Quick Edit script on post lists.
	 *
	 * @param string $hook Admin page.
	 */
	public function enqueue_list_script( $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'jat-search-intel-quick-edit',
			JAT_SEARCH_INTEL_URL . 'admin/js/quick-edit.js',
			[ 'inline-edit-post' ],
			JAT_SEARCH_INTEL_VERSION,
			true
		);
	}

	// ── Admin bar ────────────────────────────────────────────────────────────

	/**
	 * SEO menu in the admin bar, with the page's scores and test links.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public function admin_bar( $bar ): void {
		if ( ! is_object( $bar ) || ! is_admin_bar_showing() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$post_id = 0;
		if ( is_admin() ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && 'post' === $screen->base && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: which post's links to show.
				$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: which post's links to show.
			}
		} elseif ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
		}

		$title = __( 'SEO', 'jat-search-intel' );
		if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
			$score = get_post_meta( $post_id, '_jat_search_intel_seo_score', true );
			if ( '' !== (string) $score ) {
				/* translators: %d: SEO score out of 100 */
				$title = sprintf( __( 'SEO %d', 'jat-search-intel' ), (int) $score );
			}
		}

		$root = [
			'id'    => 'jat-search-intel',
			'title' => esc_html( $title ),
			'meta'  => [ 'title' => __( 'JAT Search Intel', 'jat-search-intel' ) ],
		];
		if ( current_user_can( 'manage_options' ) ) {
			$root['href'] = admin_url( 'admin.php?page=' . Readiness::PAGE_SLUG );
		}
		$bar->add_node( $root );

		if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
			$url = get_permalink( $post_id );
			foreach ( [ 'seo' => __( 'SEO', 'jat-search-intel' ), 'aeo' => __( 'AEO', 'jat-search-intel' ), 'geo' => __( 'GEO', 'jat-search-intel' ) ] as $key => $label ) {
				$value = get_post_meta( $post_id, '_jat_search_intel_' . $key . '_score', true );
				if ( '' === (string) $value ) {
					continue;
				}
				$bar->add_node(
					[
						'parent' => 'jat-search-intel',
						'id'     => 'jat-search-intel-score-' . $key,
						/* translators: 1: score type, 2: score */
						'title'  => esc_html( sprintf( __( '%1$s score: %2$d/100', 'jat-search-intel' ), $label, (int) $value ) ),
					]
				);
			}
			if ( ! is_admin() ) {
				$bar->add_node(
					[
						'parent' => 'jat-search-intel',
						'id'     => 'jat-search-intel-edit',
						'title'  => esc_html__( 'Edit SEO for this page', 'jat-search-intel' ),
						'href'   => (string) get_edit_post_link( $post_id ),
					]
				);
			}
			if ( $url && 'publish' === get_post_status( $post_id ) ) {
				$tests = [
					'rich-results' => [ __( 'Test rich results (Google)', 'jat-search-intel' ), 'https://search.google.com/test/rich-results?url=' . rawurlencode( $url ) ],
					'schema'       => [ __( 'Validate schema (Schema.org)', 'jat-search-intel' ), 'https://validator.schema.org/#url=' . rawurlencode( $url ) ],
					'pagespeed'    => [ __( 'PageSpeed Insights', 'jat-search-intel' ), 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $url ) ],
				];
				foreach ( $tests as $id => $test ) {
					$bar->add_node(
						[
							'parent' => 'jat-search-intel',
							'id'     => 'jat-search-intel-' . $id,
							'title'  => esc_html( $test[0] ),
							'href'   => $test[1],
							'meta'   => [ 'target' => '_blank', 'rel' => 'noopener noreferrer' ],
						]
					);
				}
			}
		}

		if ( current_user_can( 'manage_options' ) ) {
			$bar->add_node(
				[
					'parent' => 'jat-search-intel',
					'id'     => 'jat-search-intel-readiness',
					'title'  => esc_html__( 'Readiness report', 'jat-search-intel' ),
					'href'   => admin_url( 'admin.php?page=' . Readiness::PAGE_SLUG ),
				]
			);
		}
	}
}
