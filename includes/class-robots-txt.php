<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Robots.txt Visual Editor — edit and validate robots.txt directives.
 *
 * @since 2.0.0
 */
class Robots_Txt {

	/**
	 * Option key for custom robots.txt content.
	 */
	private const OPTION_KEY = 'search_intel_robots_txt';

	/** @var array */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_robots_editor'] ) ) {
			add_filter( 'robots_txt', [ $this, 'filter_robots_txt' ], 999, 2 );
		}

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
			add_action( 'wp_ajax_si_save_robots_txt', [ $this, 'ajax_save' ] );
			add_action( 'wp_ajax_si_validate_robots_txt', [ $this, 'ajax_validate' ] );
		}
	}

	/**
	 * Filter WordPress robots.txt output with custom content.
	 *
	 * @param string $output  Default robots.txt content.
	 * @param bool   $public  Whether the site is public.
	 * @return string
	 */
	public function filter_robots_txt( string $output, bool $public ): string {
		$custom = $this->get_content();
		if ( ! empty( $custom ) ) {
			return $custom;
		}
		return $output;
	}

	/**
	 * Get stored robots.txt content.
	 *
	 * @return string
	 */
	public function get_content(): string {
		return (string) get_option( self::OPTION_KEY, '' );
	}

	/**
	 * Save robots.txt content.
	 *
	 * @param string $content Robots.txt content.
	 * @return bool
	 */
	public function save_content( string $content ): bool {
		return update_option( self::OPTION_KEY, $content );
	}

	/**
	 * Validate robots.txt syntax.
	 *
	 * @param string $content Robots.txt content.
	 * @return array{valid: bool, errors: string[], warnings: string[]}
	 */
	public function validate( string $content ): array {
		$errors   = [];
		$warnings = [];
		$lines    = explode( "\n", $content );

		$valid_directives = [
			'user-agent', 'disallow', 'allow', 'sitemap',
			'crawl-delay', 'host', 'clean-param',
		];

		$has_user_agent = false;

		foreach ( $lines as $num => $line ) {
			$line_num = $num + 1;
			$trimmed  = trim( $line );

			// Skip empty lines and comments.
			if ( '' === $trimmed || 0 === strpos( $trimmed, '#' ) ) {
				continue;
			}

			// Check for directive: value format.
			if ( strpos( $trimmed, ':' ) === false ) {
				$errors[] = sprintf( 'Line %d: Missing colon — expected "Directive: value" format.', $line_num );
				continue;
			}

			$parts     = explode( ':', $trimmed, 2 );
			$directive = strtolower( trim( $parts[0] ) );
			$value     = trim( $parts[1] ?? '' );

			if ( ! in_array( $directive, $valid_directives, true ) ) {
				$warnings[] = sprintf( 'Line %d: Unknown directive "%s".', $line_num, $directive );
			}

			if ( 'user-agent' === $directive ) {
				$has_user_agent = true;
				if ( '' === $value ) {
					$errors[] = sprintf( 'Line %d: User-agent requires a value.', $line_num );
				}
			}

			if ( 'disallow' === $directive && ! $has_user_agent ) {
				$errors[] = sprintf( 'Line %d: Disallow before any User-agent directive.', $line_num );
			}

			if ( 'sitemap' === $directive ) {
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$errors[] = sprintf( 'Line %d: Sitemap must be a valid URL.', $line_num );
				}
			}

			// Check for wildcard in Disallow for non-Google/Bing bots.
			if ( in_array( $directive, [ 'disallow', 'allow' ], true ) && strpos( $value, '*' ) !== false ) {
				$warnings[] = sprintf( 'Line %d: Wildcards in %s are only supported by Google and Bing.', $line_num, ucfirst( $directive ) );
			}
		}

		$has_directives = ! empty( array_filter( $lines, function ( $l ) {
			$t = trim( $l );
			return '' !== $t && 0 !== strpos( $t, '#' );
		} ) );

		if ( ! $has_user_agent && $has_directives ) {
			$errors[] = 'No User-agent directive found. At least one is required.';
		}

		return [
			'valid'    => empty( $errors ),
			'errors'   => $errors,
			'warnings' => $warnings,
		];
	}

	/**
	 * Register admin submenu page.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'search-intel',
			__( 'Robots.txt', 'search-intel' ),
			__( 'Robots.txt', 'search-intel' ),
			'manage_options',
			'search-intel-robots',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Render the robots.txt editor page.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$content = $this->get_content();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Search Intel — Robots.txt Editor', 'search-intel' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Edit your virtual robots.txt file. WordPress generates robots.txt dynamically — this editor overrides the default output.', 'search-intel' ); ?></p>

			<div style="margin-top:20px;">
				<textarea id="si-robots-txt" rows="20" class="large-text code" style="font-family:monospace;"><?php echo esc_textarea( $content ); ?></textarea>
			</div>

			<div id="si-robots-validation" style="margin-top:10px;"></div>

			<p style="margin-top:15px;">
				<button class="button button-primary" id="si-save-robots"><?php esc_html_e( 'Save', 'search-intel' ); ?></button>
				<button class="button" id="si-validate-robots"><?php esc_html_e( 'Validate', 'search-intel' ); ?></button>
				<button class="button" id="si-reset-robots"><?php esc_html_e( 'Reset to Default', 'search-intel' ); ?></button>
			</p>

			<?php wp_nonce_field( 'si_robots_nonce', 'si_robots_nonce_field' ); ?>
		</div>
		<?php
	}

	/**
	 * AJAX: Save robots.txt content.
	 */
	public function ajax_save(): void {
		check_ajax_referer( 'si_robots_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
		$this->save_content( $content );
		wp_send_json_success();
	}

	/**
	 * AJAX: Validate robots.txt content.
	 */
	public function ajax_validate(): void {
		check_ajax_referer( 'si_robots_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
		$result  = $this->validate( $content );
		wp_send_json_success( $result );
	}
}
