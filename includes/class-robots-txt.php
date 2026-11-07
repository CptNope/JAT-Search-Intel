<?php
namespace JAT_Search_Intel;

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
	private const OPTION_KEY = 'jat_search_intel_robots_txt';

	/** Earlier versions, newest first. */
	public const HISTORY_OPTION = 'jat_search_intel_robots_txt_history';

	/** Versions kept. */
	public const HISTORY_SIZE = 10;

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
			add_action( 'wp_ajax_jat_search_intel_save_robots_txt', [ $this, 'ajax_save' ] );
			add_action( 'wp_ajax_jat_search_intel_validate_robots_txt', [ $this, 'ajax_validate' ] );
			add_action( 'admin_post_jat_search_intel_robots_restore', [ $this, 'handle_restore' ] );
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
		$previous = $this->get_content();
		if ( $previous === $content ) {
			return false;
		}
		// Keep the version being replaced so it can be restored.
		$history = self::get_history();
		array_unshift(
			$history,
			[
				'content' => $previous,
				'time'    => time(),
				'user'    => get_current_user_id(),
			]
		);
		update_option( self::HISTORY_OPTION, array_slice( $history, 0, self::HISTORY_SIZE ), false );
		return update_option( self::OPTION_KEY, $content );
	}

	/**
	 * Earlier versions, newest first.
	 *
	 * @return array<int,array{content:string,time:int,user:int}>
	 */
	public static function get_history(): array {
		$history = get_option( self::HISTORY_OPTION, [] );
		return is_array( $history ) ? array_values( $history ) : [];
	}

	/**
	 * Restore an earlier version.
	 */
	public function handle_restore(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'jat-search-intel' ), 403 );
		}
		check_admin_referer( 'jat_search_intel_robots_restore' );
		$index   = isset( $_POST['version'] ) ? absint( $_POST['version'] ) : -1;
		$history = self::get_history();
		if ( isset( $history[ $index ] ) ) {
			$this->save_content( (string) $history[ $index ]['content'] );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=jat-search-intel-robots&restored=1' ) );
		exit;
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
			'jat-search-intel',
			__( 'Robots.txt', 'jat-search-intel' ),
			__( 'Robots.txt', 'jat-search-intel' ),
			'manage_options',
			'jat-search-intel-robots',
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
			<h1><?php esc_html_e( 'JAT Search Intel — Robots.txt Editor', 'jat-search-intel' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Edit your virtual robots.txt file. WordPress generates robots.txt dynamically — this editor overrides the default output.', 'jat-search-intel' ); ?></p>

			<div style="margin-top:20px;">
				<textarea id="si-robots-txt" rows="20" class="large-text code" style="font-family:monospace;"><?php echo esc_textarea( $content ); ?></textarea>
			</div>

			<div id="si-robots-validation" style="margin-top:10px;"></div>

			<p style="margin-top:15px;">
				<button class="button button-primary" id="si-save-robots"><?php esc_html_e( 'Save', 'jat-search-intel' ); ?></button>
				<button class="button" id="si-validate-robots"><?php esc_html_e( 'Validate', 'jat-search-intel' ); ?></button>
				<button class="button" id="si-reset-robots"><?php esc_html_e( 'Reset to Default', 'jat-search-intel' ); ?></button>
			</p>

			<?php wp_nonce_field( 'si_robots_nonce', 'si_robots_nonce_field' ); ?>

			<?php $this->render_history(); ?>
		</div>
		<?php
	}

	/**
	 * Earlier versions with Restore buttons.
	 */
	private function render_history(): void {
		$history = self::get_history();
		if ( ! $history ) {
			return;
		}
		echo '<h2 style="margin-top:2em">' . esc_html__( 'Earlier versions', 'jat-search-intel' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( $history as $index => $version ) {
			$user = $version['user'] ? get_userdata( (int) $version['user'] ) : false;
			echo '<tr><td style="width:40%"><strong>' . esc_html( (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $version['time'] ) ) . '</strong>';
			echo '<br /><small>' . esc_html( $user ? $user->display_name : '' ) . '</small></td>';
			echo '<td><details><summary>' . esc_html__( 'Show this version', 'jat-search-intel' ) . '</summary><pre style="white-space:pre-wrap;max-height:240px;overflow:auto">' . esc_html( '' !== $version['content'] ? $version['content'] : __( '(empty: WordPress default)', 'jat-search-intel' ) ) . '</pre></details></td>';
			echo '<td style="width:120px"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'jat_search_intel_robots_restore' );
			echo '<input type="hidden" name="action" value="jat_search_intel_robots_restore" /><input type="hidden" name="version" value="' . esc_attr( (string) $index ) . '" />';
			echo '<button type="submit" class="button">' . esc_html__( 'Restore', 'jat-search-intel' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table>';
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
