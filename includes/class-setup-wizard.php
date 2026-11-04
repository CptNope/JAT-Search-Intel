<?php
/**
 * Three-step setup wizard.
 *
 * 1. Who you are — organisation or person, name, logo, official profiles.
 * 2. Search engines — indexing, sitemap, IndexNow.
 * 3. AI — crawler access, Content Signals, llms.txt and Markdown copies.
 *
 * Finishes on the Readiness page. Offered through a dismissible notice after
 * activation rather than a redirect, and always available under Tools.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup wizard.
 */
class Setup_Wizard {

	public const PAGE_SLUG = 'jat-search-intel-setup';

	/** Option holding the wizard state: '' (never run), 'pending', 'dismissed' or 'done'. */
	public const STATE_OPTION = 'jat_search_intel_setup_state';

	private const NONCE = 'jat_search_intel_setup';

	private const PROFILE_KEYS = [ 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'pinterest', 'tiktok', 'github' ];

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		add_action( 'admin_menu', [ $this, 'register_page' ], 20 );
		add_action( 'admin_post_jat_search_intel_setup', [ $this, 'handle_step' ] );
		add_action( 'admin_post_jat_search_intel_setup_dismiss', [ $this, 'handle_dismiss' ] );
		add_action( 'admin_notices', [ $this, 'render_notice' ] );
	}

	/**
	 * Mark the wizard as pending on first activation.
	 */
	public static function on_activation(): void {
		if ( '' === (string) get_option( self::STATE_OPTION, '' ) ) {
			add_option( self::STATE_OPTION, 'pending', '', false );
		}
	}

	/**
	 * Register the page (shown as a tab under Tools).
	 */
	public function register_page(): void {
		add_submenu_page(
			Admin_Menu::PARENT,
			__( 'Setup Wizard', 'jat-search-intel' ),
			__( 'Setup Wizard', 'jat-search-intel' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * "Finish setting up" notice on the dashboard, Plugins screen and our own pages.
	 */
	public function render_notice(): void {
		if ( 'pending' !== get_option( self::STATE_OPTION ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		if ( ! in_array( $id, [ 'dashboard', 'plugins' ], true ) && false === strpos( $id, 'jat-search-intel' ) ) {
			return;
		}
		if ( false !== strpos( $id, self::PAGE_SLUG ) ) {
			return;
		}

		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=jat_search_intel_setup_dismiss' ), self::NONCE . '_dismiss' );
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'JAT Search Intel is active.', 'jat-search-intel' ) . '</strong> ';
		echo esc_html__( 'Three quick steps tell search engines and AI assistants who you are and how they may use your content.', 'jat-search-intel' ) . '</p><p>';
		echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Run setup', 'jat-search-intel' ) . '</a> ';
		echo '<a class="button-link" style="margin-left:8px" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Skip, I\'ll configure it myself', 'jat-search-intel' ) . '</a></p></div>';
	}

	/**
	 * Dismiss the notice.
	 */
	public function handle_dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'jat-search-intel' ), 403 );
		}
		check_admin_referer( self::NONCE . '_dismiss' );
		update_option( self::STATE_OPTION, 'dismissed', false );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	// ── Saving ──────────────────────────────────────────────────────────

	/**
	 * Save a step and go to the next one.
	 */
	public function handle_step(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'jat-search-intel' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$step  = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;
		$input = isset( $_POST['jsi'] ) && is_array( $_POST['jsi'] ) ? wp_unslash( $_POST['jsi'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitised in apply_step().

		$settings = get_option( 'jat_search_intel_settings', [] );
		$settings = array_merge( Core::get_default_settings(), is_array( $settings ) ? $settings : [] );
		$settings = $this->apply_step( $step, (array) $input, $settings );

		// Write directly: the values are sanitised here, and the settings-page sanitizer is page-scoped.
		remove_all_filters( 'sanitize_option_jat_search_intel_settings' );
		update_option( 'jat_search_intel_settings', $settings );

		if ( $step >= 3 ) {
			update_option( self::STATE_OPTION, 'done', false );
			Upgrade::request_rewrite_flush();
			wp_safe_redirect( admin_url( 'admin.php?page=' . Readiness::PAGE_SLUG . '&jsi_setup=done' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&step=' . ( $step + 1 ) ) );
		exit;
	}

	/**
	 * Apply one step's input to the settings (and related options). Public for tests.
	 *
	 * @param int                 $step     Step number.
	 * @param array               $input    Raw input.
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	public function apply_step( int $step, array $input, array $settings ): array {
		switch ( $step ) {
			case 1:
				$type                       = (string) ( $input['knowledge_type'] ?? 'Organization' );
				$settings['knowledge_type'] = in_array( $type, [ 'Organization', 'Person' ], true ) ? $type : 'Organization';
				$settings['knowledge_name'] = sanitize_text_field( (string) ( $input['knowledge_name'] ?? '' ) );
				$settings['knowledge_logo'] = esc_url_raw( (string) ( $input['knowledge_logo'] ?? '' ) );

				$profiles = get_option( 'jat_search_intel_social_profiles', [] );
				$profiles = is_array( $profiles ) ? $profiles : [];
				foreach ( self::PROFILE_KEYS as $key ) {
					if ( isset( $input['profiles'][ $key ] ) ) {
						$url = esc_url_raw( trim( (string) $input['profiles'][ $key ] ) );
						if ( '' === $url ) {
							unset( $profiles[ $key ] );
						} else {
							$profiles[ $key ] = $url;
						}
					}
				}
				update_option( 'jat_search_intel_social_profiles', $profiles );
				break;

			case 2:
				if ( ! empty( $input['make_public'] ) ) {
					update_option( 'blog_public', '1' );
				}
				$settings['enable_sitemap']  = ! empty( $input['enable_sitemap'] );
				$settings['enable_indexnow'] = ! empty( $input['enable_indexnow'] );
				if ( $settings['enable_indexnow'] && empty( $settings['indexnow_api_key'] ) ) {
					$settings['indexnow_api_key'] = IndexNow::generate_key();
				}
				break;

			case 3:
				$preset   = (string) ( $input['ai_preset'] ?? 'training' );
				$settings = AI_Search::apply_preset( $settings, $preset );

				$settings['enable_geo']                = $settings['enable_geo'] || ! empty( $input['enable_llms_txt'] );
				$settings['enable_llms_txt']           = ! empty( $input['enable_llms_txt'] );
				$settings['enable_markdown_endpoints'] = ! empty( $input['enable_markdown_endpoints'] );
				break;
		}

		return $settings;
	}

	// ── Page ────────────────────────────────────────────────────────────

	/**
	 * Render the current step.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Step number for display only.
		$step  = isset( $_GET['step'] ) ? max( 1, min( 3, absint( $_GET['step'] ) ) ) : 1;
		$s     = array_merge( Core::get_default_settings(), $this->settings, (array) get_option( 'jat_search_intel_settings', [] ) );
		$steps = [
			1 => __( 'Who you are', 'jat-search-intel' ),
			2 => __( 'Search engines', 'jat-search-intel' ),
			3 => __( 'AI assistants', 'jat-search-intel' ),
		];

		echo '<div class="wrap jsi-setup" style="max-width:760px">';
		echo '<h1>' . esc_html__( 'Set up JAT Search Intel', 'jat-search-intel' ) . '</h1>';

		echo '<ol class="jsi-steps" style="display:flex;gap:8px;list-style:none;margin:16px 0 20px;padding:0">';
		foreach ( $steps as $n => $label ) {
			$state = $n < $step ? 'done' : ( $n === $step ? 'current' : 'todo' );
			$bg    = 'current' === $state ? '#2271b1' : ( 'done' === $state ? '#00a32a' : '#dcdcde' );
			$fg    = 'todo' === $state ? '#50575e' : '#fff';
			printf(
				'<li style="flex:1;padding:8px 12px;border-radius:4px;background:%1$s;color:%2$s"%3$s>%4$s. %5$s</li>',
				esc_attr( $bg ),
				esc_attr( $fg ),
				'current' === $state ? ' aria-current="step"' : '',
				(int) $n,
				esc_html( $label )
			);
		}
		echo '</ol>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="card" style="max-width:none;padding:8px 24px 20px">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="action" value="jat_search_intel_setup" /><input type="hidden" name="step" value="' . esc_attr( (string) $step ) . '" />';

		if ( 1 === $step ) {
			$this->render_step_identity( $s );
		} elseif ( 2 === $step ) {
			$this->render_step_search( $s );
		} else {
			$this->render_step_ai( $s );
		}

		echo '<p style="display:flex;justify-content:space-between;align-items:center;margin-top:20px">';
		if ( $step > 1 ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&step=' . ( $step - 1 ) ) ) . '">&larr; ' . esc_html__( 'Back', 'jat-search-intel' ) . '</a>';
		} else {
			echo '<span></span>';
		}
		echo '<button type="submit" class="button button-primary button-large">' . esc_html( 3 === $step ? __( 'Finish and check readiness', 'jat-search-intel' ) : __( 'Save and continue', 'jat-search-intel' ) ) . '</button></p>';
		echo '</form></div>';
	}

	/**
	 * Step 1.
	 *
	 * @param array $s Settings.
	 */
	private function render_step_identity( array $s ): void {
		$profiles = (array) get_option( 'jat_search_intel_social_profiles', [] );
		$labels   = [
			'facebook'  => 'Facebook',
			'twitter'   => 'X (Twitter)',
			'instagram' => 'Instagram',
			'linkedin'  => 'LinkedIn',
			'youtube'   => 'YouTube',
			'pinterest' => 'Pinterest',
			'tiktok'    => 'TikTok',
			'github'    => 'GitHub',
		];

		echo '<h2>' . esc_html__( 'Who is behind this site?', 'jat-search-intel' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Search engines and AI assistants build a profile of you as an entity. Your name, logo and official profiles connect your site to every other mention of you on the web.', 'jat-search-intel' ) . '</p>';
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row">' . esc_html__( 'This site represents', 'jat-search-intel' ) . '</th><td><fieldset>';
		foreach ( [ 'Organization' => __( 'An organisation or business', 'jat-search-intel' ), 'Person' => __( 'A person', 'jat-search-intel' ) ] as $value => $label ) {
			printf( '<label style="margin-right:16px"><input type="radio" name="jsi[knowledge_type]" value="%1$s" %2$s /> %3$s</label>', esc_attr( $value ), checked( $s['knowledge_type'], $value, false ), esc_html( $label ) );
		}
		echo '</fieldset></td></tr>';

		echo '<tr><th scope="row"><label for="jsi-name">' . esc_html__( 'Name', 'jat-search-intel' ) . '</label></th><td><input type="text" id="jsi-name" class="regular-text" name="jsi[knowledge_name]" value="' . esc_attr( (string) $s['knowledge_name'] ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="jsi-logo">' . esc_html__( 'Logo or photo URL', 'jat-search-intel' ) . '</label></th><td><input type="url" id="jsi-logo" class="regular-text" name="jsi[knowledge_logo]" value="' . esc_attr( (string) $s['knowledge_logo'] ) . '" placeholder="https://" />';
		echo '<p class="description">' . esc_html__( 'Square, at least 112 × 112 pixels. Copy the file URL from the Media Library.', 'jat-search-intel' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Official profiles', 'jat-search-intel' ) . '</th><td>';
		foreach ( $labels as $key => $label ) {
			printf(
				'<p style="margin:0 0 6px"><label style="display:inline-block;width:110px" for="jsi-p-%1$s">%2$s</label><input type="url" id="jsi-p-%1$s" class="regular-text" name="jsi[profiles][%1$s]" value="%3$s" placeholder="https://" /></p>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( (string) ( $profiles[ $key ] ?? '' ) )
			);
		}
		echo '<p class="description">' . esc_html__( 'Add the ones you actively use. Leave the rest empty.', 'jat-search-intel' ) . '</p></td></tr>';
		echo '</table>';
	}

	/**
	 * Step 2.
	 *
	 * @param array $s Settings.
	 */
	private function render_step_search( array $s ): void {
		echo '<h2>' . esc_html__( 'Help search engines find your pages', 'jat-search-intel' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';

		if ( '0' === (string) get_option( 'blog_public', '1' ) ) {
			echo '<tr><th scope="row">' . esc_html__( 'Indexing', 'jat-search-intel' ) . '</th><td><div class="notice notice-warning inline"><p>' . esc_html__( 'WordPress is currently asking search engines not to index this site (Settings > Reading). Nothing can rank or be cited while this is on.', 'jat-search-intel' ) . '</p></div>';
			echo '<label><input type="checkbox" name="jsi[make_public]" value="1" /> ' . esc_html__( 'Allow search engines to index this site', 'jat-search-intel' ) . '</label>';
			echo '<p class="description">' . esc_html__( 'Leave unticked while the site is still in development.', 'jat-search-intel' ) . '</p></td></tr>';
		}

		echo '<tr><th scope="row">' . esc_html__( 'XML sitemap', 'jat-search-intel' ) . '</th><td><label><input type="checkbox" name="jsi[enable_sitemap]" value="1" ' . checked( ! empty( $s['enable_sitemap'] ), true, false ) . ' /> ' . esc_html__( 'Publish a sitemap and list it in robots.txt', 'jat-search-intel' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Recommended. Replaces the basic WordPress sitemap with one that includes images and respects noindex.', 'jat-search-intel' ) . '</p></td></tr>';

		echo '<tr><th scope="row">IndexNow</th><td><label><input type="checkbox" name="jsi[enable_indexnow]" value="1" ' . checked( ! empty( $s['enable_indexnow'] ), true, false ) . ' /> ' . esc_html__( 'Tell Bing and other engines about new and updated pages instantly', 'jat-search-intel' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Recommended. ChatGPT search and Microsoft Copilot answer from Bing\'s index. This sends the URL of each page you publish or update to the IndexNow service; no key or account is needed.', 'jat-search-intel' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Google Search Console', 'jat-search-intel' ) . '</th><td><p>' . esc_html__( 'Optional. Connect it later under Reports > Rank Tracker to see positions and clicks for each page.', 'jat-search-intel' ) . '</p></td></tr>';
		echo '</table>';
	}

	/**
	 * Step 3.
	 *
	 * @param array $s Settings.
	 */
	private function render_step_ai( array $s ): void {
		$current = AI_Search::detect_preset( $s );
		$presets = [
			'allow'    => [ __( 'Allow everything', 'jat-search-intel' ), __( 'AI search, AI answers and AI model training may all use your content.', 'jat-search-intel' ) ],
			'training' => [ __( 'Allow AI search, block AI training (recommended)', 'jat-search-intel' ), __( 'You can still appear and be cited in ChatGPT, Perplexity, Claude and Google AI answers, but your content is not offered for training new models.', 'jat-search-intel' ) ],
			'block'    => [ __( 'Block all AI crawlers', 'jat-search-intel' ), __( 'Keeps AI crawlers out entirely. You will not be cited by AI assistants that respect robots.txt. Regular Google and Bing search are not affected.', 'jat-search-intel' ) ],
		];

		echo '<h2>' . esc_html__( 'How may AI assistants use your content?', 'jat-search-intel' ) . '</h2>';
		echo '<fieldset style="margin:12px 0 20px">';
		foreach ( $presets as $value => list( $label, $help ) ) {
			printf(
				'<label style="display:block;padding:10px 12px;margin-bottom:8px;border:1px solid #dcdcde;border-radius:4px"><input type="radio" name="jsi[ai_preset]" value="%1$s" %2$s /> <strong>%3$s</strong><br><span class="description" style="margin-left:24px;display:inline-block">%4$s</span></label>',
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $label ),
				esc_html( $help )
			);
		}
		echo '</fieldset><p class="description">' . esc_html__( 'This writes per-crawler rules and Content Signals to robots.txt. Fine-tune individual crawlers later under Settings > GEO.', 'jat-search-intel' ) . '</p>';

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">llms.txt</th><td><label><input type="checkbox" name="jsi[enable_llms_txt]" value="1" ' . checked( ! empty( $s['enable_llms_txt'] ), true, false ) . ' /> ' . esc_html__( 'Publish a guide to your key pages for AI agents at /llms.txt', 'jat-search-intel' ) . '</label></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Markdown copies', 'jat-search-intel' ) . '</th><td><label><input type="checkbox" name="jsi[enable_markdown_endpoints]" value="1" ' . checked( ! empty( $s['enable_markdown_endpoints'] ), true, false ) . ' /> ' . esc_html__( 'Offer a clean Markdown version of each post (add .md to the URL)', 'jat-search-intel' ) . '</label></td></tr>';
		echo '</table>';
	}
}
