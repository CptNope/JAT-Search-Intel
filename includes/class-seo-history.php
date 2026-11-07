<?php
/**
 * SEO change history with one-click restore.
 *
 * Every change to a post's SEO fields (title, description, robots, canonical,
 * social, schema and AI fields) is recorded with who made it and when. All
 * changes made in one save become one entry, and restoring an entry puts every
 * field it changed back to its earlier value.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change tracking for post SEO fields.
 */
class SEO_History {

	/** Post meta holding the history (newest first). */
	public const META_KEY = '_jat_search_intel_seo_history';

	/** Entries kept per post. */
	public const MAX_ENTRIES = 30;

	/** Longest value stored per field (very long AI summaries are cut). */
	private const MAX_VALUE_LENGTH = 2000;

	/** @var array<int,array<string,array{0:string,1:string}>> Pending changes: post => key => [ old, new ]. */
	private static $pending = [];

	/** @var bool Whether the history itself is being written. */
	private static $writing = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'update_post_metadata', [ $this, 'capture_update' ], 10, 4 );
		add_filter( 'add_post_metadata', [ $this, 'capture_add' ], 10, 4 );
		add_filter( 'delete_post_metadata', [ $this, 'capture_delete' ], 10, 3 );
		add_action( 'shutdown', [ self::class, 'flush' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'admin_post_jat_search_intel_history_restore', [ $this, 'handle_restore' ] );
	}

	/**
	 * Tracked meta keys => labels.
	 *
	 * @return array<string,string>
	 */
	public static function tracked(): array {
		return [
			'_jat_search_intel_title'               => __( 'SEO title', 'jat-search-intel' ),
			'_jat_search_intel_description'         => __( 'Meta description', 'jat-search-intel' ),
			'_jat_search_intel_focus_keyword'       => __( 'Focus keyword', 'jat-search-intel' ),
			'_jat_search_intel_secondary_keywords'  => __( 'Secondary keywords', 'jat-search-intel' ),
			'_jat_search_intel_canonical'           => __( 'Canonical URL', 'jat-search-intel' ),
			'_jat_search_intel_noindex'             => __( 'noindex', 'jat-search-intel' ),
			'_jat_search_intel_nofollow'            => __( 'nofollow', 'jat-search-intel' ),
			'_jat_search_intel_social_title'        => __( 'Social title', 'jat-search-intel' ),
			'_jat_search_intel_social_description'  => __( 'Social description', 'jat-search-intel' ),
			'_jat_search_intel_social_image'        => __( 'Social image', 'jat-search-intel' ),
			'_jat_search_intel_twitter_title'       => __( 'X title', 'jat-search-intel' ),
			'_jat_search_intel_twitter_description' => __( 'X description', 'jat-search-intel' ),
			'_jat_search_intel_twitter_image'       => __( 'X image', 'jat-search-intel' ),
			'_jat_search_intel_schema_type'         => __( 'Schema type', 'jat-search-intel' ),
			'_jat_search_intel_direct_answer'       => __( 'Direct answer', 'jat-search-intel' ),
			'_jat_search_intel_ai_summary'          => __( 'AI summary', 'jat-search-intel' ),
			'_jat_search_intel_primary_category'    => __( 'Primary category', 'jat-search-intel' ),
		];
	}

	/**
	 * Current value of a field as stored text.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	private static function current( int $post_id, string $key ): string {
		return self::stringify( get_post_meta( $post_id, $key, true ) );
	}

	/**
	 * A meta value as text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function stringify( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = (string) wp_json_encode( $value );
		}
		$value = is_scalar( $value ) ? (string) $value : '';
		return mb_substr( $value, 0, self::MAX_VALUE_LENGTH );
	}

	/**
	 * Note a pending change (the first old value and the latest new value win).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $old     Value before.
	 * @param string $new     Value after.
	 */
	private static function note( int $post_id, string $key, string $old, string $new ): void {
		if ( self::$writing || ! isset( self::tracked()[ $key ] ) || $post_id <= 0 ) {
			return;
		}
		if ( isset( self::$pending[ $post_id ][ $key ] ) ) {
			self::$pending[ $post_id ][ $key ][1] = $new;
			return;
		}
		if ( $old !== $new ) {
			self::$pending[ $post_id ][ $key ] = [ $old, $new ];
		}
	}

	/**
	 * Before a meta update.
	 *
	 * @param null|bool $check     Short-circuit value (passed through).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 * @param mixed     $value     New value (already unslashed by core).
	 * @return null|bool
	 */
	public function capture_update( $check, $object_id, $meta_key, $value ) {
		if ( null === $check && isset( self::tracked()[ (string) $meta_key ] ) ) {
			self::note( (int) $object_id, (string) $meta_key, self::current( (int) $object_id, (string) $meta_key ), self::stringify( $value ) );
		}
		return $check;
	}

	/**
	 * Before a meta add.
	 *
	 * @param null|bool $check     Short-circuit value (passed through).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 * @param mixed     $value     Value.
	 * @return null|bool
	 */
	public function capture_add( $check, $object_id, $meta_key, $value ) {
		if ( null === $check && isset( self::tracked()[ (string) $meta_key ] ) ) {
			self::note( (int) $object_id, (string) $meta_key, self::current( (int) $object_id, (string) $meta_key ), self::stringify( $value ) );
		}
		return $check;
	}

	/**
	 * Before a meta delete.
	 *
	 * @param null|bool $check     Short-circuit value (passed through).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 * @return null|bool
	 */
	public function capture_delete( $check, $object_id, $meta_key ) {
		if ( null === $check && isset( self::tracked()[ (string) $meta_key ] ) ) {
			self::note( (int) $object_id, (string) $meta_key, self::current( (int) $object_id, (string) $meta_key ), '' );
		}
		return $check;
	}

	/**
	 * Write pending changes as one history entry per post.
	 */
	public static function flush(): void {
		$pending       = self::$pending;
		self::$pending = [];
		foreach ( $pending as $post_id => $changes ) {
			$changes = array_filter(
				$changes,
				static function ( array $pair ): bool {
					return $pair[0] !== $pair[1];
				}
			);
			if ( ! $changes || ! get_post( $post_id ) ) {
				continue;
			}
			$history = self::get( (int) $post_id );
			array_unshift(
				$history,
				[
					'id'      => substr( md5( uniqid( (string) $post_id, true ) ), 0, 12 ),
					'time'    => time(),
					'user'    => get_current_user_id(),
					'changes' => $changes,
				]
			);
			self::$writing = true;
			update_post_meta( (int) $post_id, self::META_KEY, array_slice( $history, 0, self::MAX_ENTRIES ) );
			self::$writing = false;
		}
	}

	/**
	 * Stored history for a post (newest first).
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{id:string,time:int,user:int,changes:array<string,array{0:string,1:string}>}>
	 */
	public static function get( int $post_id ): array {
		$history = get_post_meta( $post_id, self::META_KEY, true );
		return is_array( $history ) ? array_values( $history ) : [];
	}

	/**
	 * Put the fields an entry changed back to their earlier values.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $entry_id Entry ID.
	 * @return array<string,string>|\WP_Error Restored meta key => value.
	 */
	public static function restore( int $post_id, string $entry_id ) {
		foreach ( self::get( $post_id ) as $entry ) {
			if ( $entry['id'] !== $entry_id ) {
				continue;
			}
			$restored = [];
			foreach ( (array) $entry['changes'] as $key => $pair ) {
				if ( ! isset( self::tracked()[ $key ] ) ) {
					continue;
				}
				$old = (string) $pair[0];
				if ( '' === $old ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, wp_slash( $old ) );
				}
				$restored[ $key ] = $old;
				// The block editor edits some fields under another name; send that too.
				$partner = Meta_Sync::partner( $key );
				if ( '' !== $partner ) {
					$restored[ $partner ] = $old;
				}
			}
			self::flush();
			return $restored;
		}
		return new \WP_Error( 'jsi_history_entry', __( 'That history entry no longer exists.', 'jat-search-intel' ), [ 'status' => 404 ] );
	}

	/**
	 * Entries formatted for display.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{id:string,when:string,user:string,fields:string[]}>
	 */
	public static function for_display( int $post_id ): array {
		$labels = self::tracked();
		$out    = [];
		foreach ( self::get( $post_id ) as $entry ) {
			$user  = $entry['user'] ? get_userdata( (int) $entry['user'] ) : false;
			$out[] = [
				'id'     => (string) $entry['id'],
				'when'   => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $entry['time'] ) ?: '',
				'user'   => $user ? $user->display_name : __( 'Automatic update', 'jat-search-intel' ),
				'fields' => array_map(
					static function ( $key ) use ( $labels ): string {
						return $labels[ $key ] ?? $key;
					},
					array_keys( (array) $entry['changes'] )
				),
			];
		}
		return $out;
	}

	/**
	 * REST routes for the editor panel.
	 */
	public function register_routes(): void {
		$permission = static function ( \WP_REST_Request $request ): bool {
			return current_user_can( 'edit_post', (int) $request['id'] );
		};
		register_rest_route(
			'jat-search-intel/v1',
			'/history/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'permission_callback' => $permission,
				'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
				'callback'            => static function ( \WP_REST_Request $request ): \WP_REST_Response {
					return new \WP_REST_Response( [ 'entries' => self::for_display( (int) $request['id'] ) ] );
				},
			]
		);
		register_rest_route(
			'jat-search-intel/v1',
			'/history/(?P<id>\d+)/restore',
			[
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'args'                => [
					'id'    => [ 'type' => 'integer', 'required' => true ],
					'entry' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
				],
				'callback'            => static function ( \WP_REST_Request $request ) {
					$restored = self::restore( (int) $request['id'], (string) $request['entry'] );
					if ( is_wp_error( $restored ) ) {
						return $restored;
					}
					return new \WP_REST_Response( [ 'meta' => $restored ] );
				},
			]
		);
	}

	/**
	 * Restore from the classic editor's meta box.
	 */
	public function handle_restore(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		check_admin_referer( 'jat_search_intel_history_restore_' . $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'jat-search-intel' ), 403 );
		}
		$entry = isset( $_POST['entry'] ) ? sanitize_key( wp_unslash( $_POST['entry'] ) ) : '';
		self::restore( $post_id, $entry );
		wp_safe_redirect( (string) get_edit_post_link( $post_id, 'url' ) );
		exit;
	}

	/**
	 * Recent history with restore buttons, for the classic meta box.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function render_list( int $post_id ): void {
		$entries = array_slice( self::for_display( $post_id ), 0, 5 );
		if ( ! $entries ) {
			return;
		}
		echo '<hr /><h3>' . esc_html__( 'SEO change history', 'jat-search-intel' ) . '</h3><ul>';
		foreach ( $entries as $entry ) {
			echo '<li style="margin-bottom:8px"><strong>' . esc_html( $entry['when'] ) . '</strong> — ';
			/* translators: 1: user name, 2: list of changed fields. */
			echo esc_html( sprintf( __( '%1$s changed %2$s', 'jat-search-intel' ), $entry['user'], implode( ', ', $entry['fields'] ) ) ) . ' ';
			// A form can't nest inside the post form, so the button submits to admin-post via formaction.
			echo '<button type="submit" class="button-link" form="jsi-history-' . esc_attr( $entry['id'] ) . '">' . esc_html__( 'Restore', 'jat-search-intel' ) . '</button></li>';
		}
		echo '</ul>';
		add_action(
			'admin_footer',
			static function () use ( $entries, $post_id ): void {
				foreach ( $entries as $entry ) {
					echo '<form id="jsi-history-' . esc_attr( $entry['id'] ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:none">';
					wp_nonce_field( 'jat_search_intel_history_restore_' . $post_id );
					echo '<input type="hidden" name="action" value="jat_search_intel_history_restore" /><input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '" /><input type="hidden" name="entry" value="' . esc_attr( $entry['id'] ) . '" /></form>';
				}
			}
		);
	}
}
