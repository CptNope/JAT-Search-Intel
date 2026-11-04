<?php
/**
 * Custom JSON-LD per post: paste any Schema.org markup (from a schema
 * generator or Google's examples) and it joins the page's connected graph.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom schema meta box and output.
 */
class Custom_Schema {

	public const META_KEY = '_jat_search_intel_custom_schema';

	private const NOTICE_KEY = 'jat_search_intel_schema_error_';

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( empty( $settings['enable_schema'] ) ) {
			return;
		}

		add_filter( 'jat_search_intel_schema_graph', [ $this, 'add_graph_nodes' ], 20, 2 );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
			add_action( 'save_post', [ $this, 'save' ], 10, 2 );
			add_action( 'admin_notices', [ $this, 'error_notice' ] );
		}
	}

	/**
	 * Parse stored JSON-LD into graph nodes.
	 *
	 * @param string $json Raw JSON.
	 * @return array<int,array<string,mixed>>|null Nodes, or null when invalid.
	 */
	public static function parse( string $json ): ?array {
		$json = trim( $json );
		if ( '' === $json ) {
			return [];
		}

		// Allow a pasted <script type="application/ld+json"> wrapper.
		$json = (string) preg_replace( '~^<script[^>]*>\s*|\s*</script>$~i', '', $json );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			$nodes = $data['@graph'];
		} elseif ( array_is_list( $data ) ) {
			$nodes = $data;
		} else {
			$nodes = [ $data ];
		}

		$clean = [];
		foreach ( $nodes as $node ) {
			if ( is_array( $node ) && ! empty( $node['@type'] ) ) {
				unset( $node['@context'] );
				$clean[] = $node;
			}
		}

		return $clean ? $clean : null;
	}

	/**
	 * Add the post's custom nodes to the graph.
	 *
	 * @param array $nodes   Graph nodes.
	 * @param array $context Graph context.
	 * @return array
	 */
	public function add_graph_nodes( array $nodes, array $context = [] ): array {
		if ( ! is_singular() ) {
			return $nodes;
		}
		$custom = self::parse( (string) get_post_meta( get_queried_object_id(), self::META_KEY, true ) );
		return $custom ? array_merge( $nodes, $custom ) : $nodes;
	}

	/**
	 * Register the meta box on enabled post types.
	 */
	public function register_metabox(): void {
		foreach ( (array) ( $this->settings['enabled_post_types'] ?? [ 'post', 'page' ] ) as $post_type ) {
			add_meta_box(
				'jat-search-intel-custom-schema',
				__( 'Custom Schema (JSON-LD)', 'jat-search-intel' ),
				[ $this, 'render_metabox' ],
				$post_type,
				'normal',
				'low'
			);
		}
	}

	/**
	 * Meta box markup.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_metabox( $post ): void {
		wp_nonce_field( 'jat_search_intel_custom_schema', 'jat_search_intel_custom_schema_nonce' );
		$value = (string) get_post_meta( $post->ID, self::META_KEY, true );
		echo '<p>' . esc_html__( 'Paste Schema.org JSON-LD for this page (a single object, a list, or an @graph). It is validated on save and merged into the page graph. Leave empty for none.', 'jat-search-intel' ) . '</p>';
		echo '<textarea name="jat_search_intel_custom_schema" rows="10" class="large-text code" spellcheck="false" placeholder="{&quot;@type&quot;: &quot;Event&quot;, ...}">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Test the result with Google\'s Rich Results Test or the Schema.org validator.', 'jat-search-intel' ) . '</p>';
	}

	/**
	 * Validate and store the JSON.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save( $post_id, $post ): void {
		if ( ! isset( $_POST['jat_search_intel_custom_schema_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jat_search_intel_custom_schema_nonce'] ) ), 'jat_search_intel_custom_schema' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['jat_search_intel_custom_schema'] ) ? trim( (string) wp_unslash( $_POST['jat_search_intel_custom_schema'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated as JSON and re-encoded below.

		if ( '' === $raw ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		$nodes = self::parse( $raw );
		if ( null === $nodes ) {
			set_transient( self::NOTICE_KEY . get_current_user_id(), (int) $post_id, 60 );
			return; // Keep the previous valid value.
		}

		$encoded = wp_json_encode( count( $nodes ) === 1 ? $nodes[0] : [ '@graph' => $nodes ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( $post_id, self::META_KEY, wp_slash( (string) $encoded ) );
	}

	/**
	 * Tell the editor when the pasted JSON could not be parsed.
	 */
	public function error_notice(): void {
		$key = self::NOTICE_KEY . get_current_user_id();
		if ( ! get_transient( $key ) ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Custom Schema was not saved: the JSON-LD is not valid JSON or has no @type. The previous version was kept.', 'jat-search-intel' ) . '</p></div>';
	}
}
