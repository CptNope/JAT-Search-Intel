<?php
/**
 * SEO fields for taxonomy terms: categories, tags, product categories and any
 * other public taxonomy.
 *
 * Each term can have its own SEO title, meta description, canonical URL, robots
 * directives and social title, description and image. Whole taxonomies can be
 * kept out of search, and empty terms are noindexed by default.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Term SEO fields and lookups.
 */
class Term_SEO {

	/** Term meta keys are this prefix plus the field name. */
	public const META_PREFIX = '_jat_search_intel_';

	/** Field => type (text, textarea, url, bool). */
	public const FIELDS = [
		'title'              => 'text',
		'description'        => 'textarea',
		'focus_keyword'      => 'text',
		'canonical'          => 'url',
		'noindex'            => 'bool',
		'nofollow'           => 'bool',
		'social_title'       => 'text',
		'social_description' => 'textarea',
		'social_image'       => 'url',
	];

	private const NONCE_FIELD  = 'jat_search_intel_term_nonce';
	private const NONCE_ACTION = 'jat_search_intel_term_seo_';

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
		if ( empty( $settings['enable_seo'] ) ) {
			return;
		}
		add_action( 'admin_init', [ $this, 'register_form_hooks' ] );
	}

	/**
	 * Taxonomies whose terms get SEO fields: every public taxonomy with archive pages.
	 *
	 * @return string[]
	 */
	public static function taxonomies(): array {
		$taxonomies = get_taxonomies( [ 'public' => true, 'publicly_queryable' => true ], 'names' );
		unset( $taxonomies['post_format'] );
		/**
		 * Filters the taxonomies that get per-term SEO fields.
		 *
		 * @param string[] $taxonomies Taxonomy names.
		 */
		return array_values( (array) apply_filters( 'jat_search_intel_term_seo_taxonomies', array_values( $taxonomies ) ) );
	}

	/**
	 * Hook the edit form and save handler for each taxonomy.
	 */
	public function register_form_hooks(): void {
		foreach ( self::taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", [ $this, 'render_fields' ], 20, 2 );
			add_action( "edited_{$taxonomy}", [ $this, 'save' ], 10, 1 );
		}
	}

	/**
	 * Value of one term field.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $field   Field name (a key of FIELDS).
	 * @return string
	 */
	public static function get( int $term_id, string $field ): string {
		if ( ! isset( self::FIELDS[ $field ] ) || $term_id <= 0 ) {
			return '';
		}
		return (string) get_term_meta( $term_id, self::META_PREFIX . $field, true );
	}

	/**
	 * All fields of a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array<string,string|bool>
	 */
	public static function get_all( int $term_id ): array {
		$out = [];
		foreach ( self::FIELDS as $field => $type ) {
			$value         = self::get( $term_id, $field );
			$out[ $field ] = 'bool' === $type ? '1' === $value : $value;
		}
		return $out;
	}

	/**
	 * Whether a term archive should be kept out of search results.
	 *
	 * @param \WP_Term            $term     Term.
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return bool
	 */
	public static function is_noindex( \WP_Term $term, array $settings ): bool {
		if ( '1' === self::get( (int) $term->term_id, 'noindex' ) ) {
			return true;
		}
		if ( in_array( $term->taxonomy, (array) ( $settings['noindex_taxonomies'] ?? [] ), true ) ) {
			return true;
		}
		return ! empty( $settings['noindex_empty_terms'] ) && 0 === (int) $term->count;
	}

	/**
	 * Clean and store submitted fields for a term.
	 *
	 * @param int                 $term_id Term ID.
	 * @param array<string,mixed> $values  Field => raw value.
	 * @param bool                $partial Only touch the fields given. Otherwise (a submitted
	 *                                     form) missing checkboxes are saved as off.
	 * @return string[] Fields that changed.
	 */
	public static function update( int $term_id, array $values, bool $partial = false ): array {
		$changed = [];
		foreach ( self::FIELDS as $field => $type ) {
			if ( ! array_key_exists( $field, $values ) && ( $partial || 'bool' !== $type ) ) {
				continue;
			}
			$raw = $values[ $field ] ?? '';
			switch ( $type ) {
				case 'bool':
					$value = ! empty( $raw ) ? '1' : '';
					break;
				case 'url':
					$value = esc_url_raw( trim( (string) $raw ) );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( (string) $raw );
					break;
				default:
					$value = sanitize_text_field( (string) $raw );
			}

			$key = self::META_PREFIX . $field;
			if ( (string) get_term_meta( $term_id, $key, true ) === $value ) {
				continue;
			}
			if ( '' === $value ) {
				delete_term_meta( $term_id, $key );
			} else {
				update_term_meta( $term_id, $key, $value );
			}
			$changed[] = $field;
		}
		return $changed;
	}

	/**
	 * Save handler for the term edit screen.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save( $term_id ): void {
		$term_id = (int) $term_id;
		// Quick Edit and other term updates don't carry these fields; leave them alone.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION . $term_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$raw = isset( $_POST['jat_search_intel_term'] ) && is_array( $_POST['jat_search_intel_term'] )
			? wp_unslash( $_POST['jat_search_intel_term'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitized by type in update().
			: [];

		$values = [];
		foreach ( array_keys( self::FIELDS ) as $field ) {
			if ( isset( $raw[ $field ] ) ) {
				$values[ $field ] = $raw[ $field ];
			}
		}
		self::update( $term_id, $values );
	}

	/**
	 * Fields on the term edit screen.
	 *
	 * @param \WP_Term $term     Term being edited.
	 * @param string   $taxonomy Taxonomy.
	 */
	public function render_fields( $term, $taxonomy ): void {
		if ( ! $term instanceof \WP_Term ) {
			return;
		}
		unset( $taxonomy );
		$values   = self::get_all( (int) $term->term_id );
		$name     = static function ( string $field ): string {
			return 'jat_search_intel_term[' . $field . ']';
		};
		$taxonomy_noindex = in_array( $term->taxonomy, (array) ( $this->settings['noindex_taxonomies'] ?? [] ), true );

		echo '<tr class="form-field"><th colspan="2" style="padding-bottom:0"><h2 style="margin:1em 0 0">' . esc_html__( 'JAT Search Intel', 'jat-search-intel' ) . '</h2>';
		wp_nonce_field( self::NONCE_ACTION . (int) $term->term_id, self::NONCE_FIELD );
		echo '</th></tr>';

		$text_rows = [
			'title'         => [ __( 'SEO title', 'jat-search-intel' ), __( 'Leave empty to use the title template for this archive.', 'jat-search-intel' ) ],
			'description'   => [ __( 'Meta description', 'jat-search-intel' ), __( 'Leave empty to use the term description.', 'jat-search-intel' ) ],
			'focus_keyword' => [ __( 'Focus keyword', 'jat-search-intel' ), '' ],
			'canonical'     => [ __( 'Canonical URL', 'jat-search-intel' ), __( 'Only set this if another URL is the main version of this archive.', 'jat-search-intel' ) ],
		];
		foreach ( $text_rows as $field => $labels ) {
			$this->render_row( $field, $labels[0], $labels[1], $name( $field ), (string) $values[ $field ] );
		}

		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Search engines', 'jat-search-intel' ) . '</th><td>';
		echo '<label><input type="checkbox" name="' . esc_attr( $name( 'noindex' ) ) . '" value="1" ' . checked( (bool) $values['noindex'], true, false ) . ' /> ' . esc_html__( 'Hide this archive from search results (noindex)', 'jat-search-intel' ) . '</label><br />';
		echo '<label><input type="checkbox" name="' . esc_attr( $name( 'nofollow' ) ) . '" value="1" ' . checked( (bool) $values['nofollow'], true, false ) . ' /> ' . esc_html__( 'Ask search engines not to follow links on it (nofollow)', 'jat-search-intel' ) . '</label>';
		if ( $taxonomy_noindex ) {
			echo '<p class="description">' . esc_html__( 'This whole taxonomy is set to noindex under JAT Search Intel → Settings → SEO.', 'jat-search-intel' ) . '</p>';
		}
		echo '</td></tr>';

		$social_rows = [
			'social_title'       => [ __( 'Social title', 'jat-search-intel' ), __( 'Shown when the archive is shared on Facebook, LinkedIn or X.', 'jat-search-intel' ) ],
			'social_description' => [ __( 'Social description', 'jat-search-intel' ), '' ],
			'social_image'       => [ __( 'Social image URL', 'jat-search-intel' ), __( 'At least 1200 × 630 pixels.', 'jat-search-intel' ) ],
		];
		foreach ( $social_rows as $field => $labels ) {
			$this->render_row( $field, $labels[0], $labels[1], $name( $field ), (string) $values[ $field ] );
		}
	}

	/**
	 * One text, URL or textarea row.
	 *
	 * @param string $field       Field name.
	 * @param string $label       Label.
	 * @param string $description Help text.
	 * @param string $input_name  Input name.
	 * @param string $value       Current value.
	 */
	private function render_row( string $field, string $label, string $description, string $input_name, string $value ): void {
		$id   = 'jsi-term-' . str_replace( '_', '-', $field );
		$type = self::FIELDS[ $field ];
		echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'textarea' === $type ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $input_name ) . '" rows="3">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input id="' . esc_attr( $id ) . '" type="' . ( 'url' === $type ? 'url' : 'text' ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '" />';
		}
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}
}
