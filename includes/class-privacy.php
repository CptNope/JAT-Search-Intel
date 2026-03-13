<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy & GDPR compliance — data exporters, erasers, and privacy policy text.
 *
 * @since 1.5.0
 */
class Privacy {

	/**
	 * User meta keys stored by the plugin.
	 *
	 * @var array<string,string>
	 */
	private const USER_META_KEYS = [
		'search_intel_credentials'      => 'Credentials / Qualifications',
		'search_intel_expertise_areas'   => 'Areas of Expertise',
		'search_intel_years_experience'  => 'Years of Experience',
		'search_intel_social_profiles'   => 'Social / Professional Profiles',
		'search_intel_eeat_consent'      => 'E-E-A-T Data Consent',
	];

	/**
	 * Constructor — register privacy hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'add_privacy_policy_content' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['search-intel'] = [
			'exporter_friendly_name' => __( 'Search Intel Author Data', 'search-intel' ),
			'callback'               => [ $this, 'export_personal_data' ],
		];
		return $exporters;
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['search-intel'] = [
			'eraser_friendly_name' => __( 'Search Intel Author Data', 'search-intel' ),
			'callback'             => [ $this, 'erase_personal_data' ],
		];
		return $erasers;
	}

	/**
	 * Export personal data for a user by email.
	 *
	 * @param string $email_address User email.
	 * @param int    $page          Page number (unused, single page).
	 * @return array
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		$data = [];

		foreach ( self::USER_META_KEYS as $meta_key => $label ) {
			$value = get_user_meta( $user->ID, $meta_key, true );

			if ( empty( $value ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			$data[] = [
				'name'  => __( $label, 'search-intel' ),
				'value' => (string) $value,
			];
		}

		$export_items = [];

		if ( ! empty( $data ) ) {
			$export_items[] = [
				'group_id'          => 'search-intel-author',
				'group_label'       => __( 'Search Intel Author Data', 'search-intel' ),
				'group_description' => __( 'Author E-E-A-T data stored by the Search Intel plugin.', 'search-intel' ),
				'item_id'           => 'search-intel-author-' . $user->ID,
				'data'              => $data,
			];
		}

		return [
			'data' => $export_items,
			'done' => true,
		];
	}

	/**
	 * Erase personal data for a user by email.
	 *
	 * @param string $email_address User email.
	 * @param int    $page          Page number (unused, single page).
	 * @return array
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return [
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => [],
				'done'           => true,
			];
		}

		$items_removed = 0;

		foreach ( array_keys( self::USER_META_KEYS ) as $meta_key ) {
			$value = get_user_meta( $user->ID, $meta_key, true );
			if ( ! empty( $value ) ) {
				delete_user_meta( $user->ID, $meta_key );
				$items_removed++;
			}
		}

		return [
			'items_removed'  => $items_removed > 0,
			'items_retained' => false,
			'messages'       => $items_removed > 0
				? [ sprintf(
					/* translators: %d: number of meta fields removed */
					__( 'Removed %d Search Intel author data field(s).', 'search-intel' ),
					$items_removed
				) ]
				: [],
			'done'           => true,
		];
	}

	/**
	 * Add suggested privacy policy content.
	 */
	public function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<h2>' . __( 'Search Intel Plugin', 'search-intel' ) . '</h2>';

		$content .= '<h3>' . __( 'What personal data we collect and why', 'search-intel' ) . '</h3>';
		$content .= '<p>' . __( 'The Search Intel plugin stores author E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) data on user profiles when explicitly provided by the author. This includes:', 'search-intel' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . __( 'Professional credentials and qualifications', 'search-intel' ) . '</li>';
		$content .= '<li>' . __( 'Areas of expertise', 'search-intel' ) . '</li>';
		$content .= '<li>' . __( 'Years of professional experience', 'search-intel' ) . '</li>';
		$content .= '<li>' . __( 'Social and professional profile URLs', 'search-intel' ) . '</li>';
		$content .= '</ul>';
		$content .= '<p>' . __( 'This data is only collected when an author explicitly provides it via their WordPress user profile, with their consent.', 'search-intel' ) . '</p>';

		$content .= '<h3>' . __( 'How we use your data', 'search-intel' ) . '</h3>';
		$content .= '<p>' . __( 'Author E-E-A-T data is used to generate structured data (JSON-LD) in the HTML source of published content. This helps search engines and AI systems understand the qualifications of content authors. The data is output as machine-readable markup and may be visible in search results.', 'search-intel' ) . '</p>';

		$content .= '<h3>' . __( 'Data storage and retention', 'search-intel' ) . '</h3>';
		$content .= '<p>' . __( 'All data is stored locally in your WordPress database. The plugin does not transmit personal data to any external service by default. SEO analysis scores and post-level metadata are stored as post meta and do not contain personal information.', 'search-intel' ) . '</p>';

		$content .= '<h3>' . __( 'Search engine notifications', 'search-intel' ) . '</h3>';
		$content .= '<p>' . __( 'When enabled (opt-in), the plugin may send sitemap ping requests to Google and Bing when content is published. These requests contain only your sitemap URL and no personal data.', 'search-intel' ) . '</p>';

		$content .= '<h3>' . __( 'Data export and deletion', 'search-intel' ) . '</h3>';
		$content .= '<p>' . __( 'Search Intel fully supports the WordPress personal data export and erasure tools. When a data export or deletion request is processed, all author E-E-A-T data stored by this plugin will be included.', 'search-intel' ) . '</p>';

		wp_add_privacy_policy_content(
			'Search Intel',
			wp_kses_post( $content )
		);
	}
}
