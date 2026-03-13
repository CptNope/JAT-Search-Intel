<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit Log — tracks settings changes for accountability.
 *
 * @since 2.0.0
 */
class Audit_Log {

	/**
	 * Option key for the audit log.
	 */
	private const OPTION_KEY = 'search_intel_audit_log';

	/**
	 * Max log entries to keep.
	 */
	private const MAX_ENTRIES = 200;

	/** @var array */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_audit_log'] ) ) {
			add_action( 'update_option_search_intel_settings', [ $this, 'log_settings_change' ], 10, 2 );
		}
	}

	/**
	 * Log a settings change.
	 *
	 * @param mixed $old_value Old settings value.
	 * @param mixed $new_value New settings value.
	 */
	public function log_settings_change( $old_value, $new_value ): void {
		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		$changes = $this->diff_settings( $old_value, $new_value );
		if ( empty( $changes ) ) {
			return;
		}

		$this->add_entry( 'settings_updated', $changes );
	}

	/**
	 * Add an entry to the audit log.
	 *
	 * @param string $action  Action identifier.
	 * @param array  $details Additional details.
	 */
	public function add_entry( string $action, array $details = [] ): void {
		$log = $this->get_log();

		$user_id = get_current_user_id();

		array_unshift( $log, [
			'action'    => $action,
			'user_id'   => $user_id,
			'user_name' => $this->get_user_display_name( $user_id ),
			'details'   => $details,
			'timestamp' => current_time( 'mysql' ),
			'ip_hash'   => $this->hash_ip(),
		] );

		// Trim to max entries.
		$log = array_slice( $log, 0, self::MAX_ENTRIES );

		update_option( self::OPTION_KEY, $log, false );
	}

	/**
	 * Get the full audit log.
	 *
	 * @return array
	 */
	public function get_log(): array {
		return get_option( self::OPTION_KEY, [] );
	}

	/**
	 * Clear the audit log.
	 */
	public function clear_log(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Diff two settings arrays to find changes.
	 *
	 * @param array $old Old settings.
	 * @param array $new New settings.
	 * @return array Changed keys with old and new values.
	 */
	public function diff_settings( array $old, array $new ): array {
		$changes = [];

		$all_keys = array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) );

		foreach ( $all_keys as $key ) {
			$old_val = $old[ $key ] ?? null;
			$new_val = $new[ $key ] ?? null;

			if ( $old_val !== $new_val ) {
				$changes[ $key ] = [
					'old' => $old_val,
					'new' => $new_val,
				];
			}
		}

		return $changes;
	}

	/**
	 * Get display name for a user ID.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_user_display_name( int $user_id ): string {
		if ( $user_id < 1 ) {
			return 'System';
		}
		return get_the_author_meta( 'display_name', $user_id ) ?: 'User #' . $user_id;
	}

	/**
	 * Hash the client IP for privacy.
	 *
	 * @return string
	 */
	private function hash_ip(): string {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return hash( 'sha256', $ip . wp_salt() );
	}
}
