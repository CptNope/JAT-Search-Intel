<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Role-Based Access Control for Search Intel.
 *
 * Provides a custom "SEO Editor" role, per-module permissions,
 * view-only mode for authors, and settings lock.
 *
 * @since 3.0.0
 */
class RBAC {

	/** @var array */
	private $settings;

	/** Custom role slug. */
	public const ROLE_SEO_EDITOR = 'search_intel_seo_editor';

	/** Module capability prefixes. */
	public const MODULES = [
		'seo'     => 'search_intel_manage_seo',
		'aeo'     => 'search_intel_manage_aeo',
		'geo'     => 'search_intel_manage_geo',
		'sitemap' => 'search_intel_manage_sitemap',
		'settings' => 'search_intel_manage_settings',
	];

	/** View-only capability. */
	public const CAP_VIEW = 'search_intel_view_scores';

	/** Settings lock capability. */
	public const CAP_SETTINGS = 'search_intel_manage_settings';

	/**
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( empty( $settings['enable_rbac'] ) ) {
			return;
		}

		add_action( 'admin_init', [ $this, 'ensure_role_exists' ] );
		add_filter( 'search_intel_user_can_module', [ $this, 'check_module_access' ], 10, 3 );
		add_filter( 'search_intel_user_can_edit', [ $this, 'check_edit_access' ], 10, 2 );
	}

	// ── Role Management ──────────────────────────────────────────────────────

	/**
	 * Ensure the SEO Editor role exists with correct capabilities.
	 */
	public function ensure_role_exists(): void {
		$role = get_role( self::ROLE_SEO_EDITOR );

		$caps = $this->get_seo_editor_caps();

		if ( $role ) {
			// Sync capabilities.
			foreach ( $caps as $cap => $grant ) {
				if ( $grant ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
			return;
		}

		add_role( self::ROLE_SEO_EDITOR, 'SEO Editor', $caps );
	}

	/**
	 * Get the default capabilities for the SEO Editor role.
	 *
	 * @return array<string, bool>
	 */
	public function get_seo_editor_caps(): array {
		$caps = [
			// WordPress defaults for editors-lite.
			'read'         => true,
			'edit_posts'   => true,
			'edit_pages'   => true,
			'upload_files' => true,

			// Search Intel view.
			self::CAP_VIEW => true,
		];

		// Module capabilities — SEO Editor gets all modules except settings by default.
		foreach ( self::MODULES as $module => $cap ) {
			$caps[ $cap ] = ( $module !== 'settings' );
		}

		return $caps;
	}

	/**
	 * Remove the SEO Editor role (for deactivation cleanup).
	 */
	public function remove_role(): void {
		remove_role( self::ROLE_SEO_EDITOR );
	}

	// ── Permission Checks ────────────────────────────────────────────────────

	/**
	 * Check if a user can access a specific module.
	 *
	 * @param bool   $can     Current permission state.
	 * @param string $module  Module key (seo, aeo, geo, sitemap, settings).
	 * @param int    $user_id User ID (0 = current user).
	 * @return bool
	 */
	public function check_module_access( bool $can, string $module, int $user_id = 0 ): bool {
		$user = $user_id > 0 ? get_userdata( $user_id ) : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		// Administrators always have full access.
		if ( user_can( $user, 'manage_options' ) ) {
			return true;
		}

		$cap = self::MODULES[ $module ] ?? '';
		if ( empty( $cap ) ) {
			return false;
		}

		return user_can( $user, $cap );
	}

	/**
	 * Check if a user can edit SEO data (not just view).
	 *
	 * Authors get view-only access if they have CAP_VIEW but not module caps.
	 *
	 * @param bool $can     Current permission state.
	 * @param int  $user_id User ID (0 = current user).
	 * @return bool
	 */
	public function check_edit_access( bool $can, int $user_id = 0 ): bool {
		$user = $user_id > 0 ? get_userdata( $user_id ) : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		// Administrators always have full access.
		if ( user_can( $user, 'manage_options' ) ) {
			return true;
		}

		// Check for any module edit capability.
		foreach ( self::MODULES as $module => $cap ) {
			if ( $module === 'settings' ) {
				continue;
			}
			if ( user_can( $user, $cap ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a user can only view scores (view-only mode).
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return bool
	 */
	public function is_view_only( int $user_id = 0 ): bool {
		$user = $user_id > 0 ? get_userdata( $user_id ) : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		// Admin is never view-only.
		if ( user_can( $user, 'manage_options' ) ) {
			return false;
		}

		// View-only = has CAP_VIEW but no module edit caps.
		if ( ! user_can( $user, self::CAP_VIEW ) ) {
			return false;
		}

		foreach ( self::MODULES as $module => $cap ) {
			if ( $module === 'settings' ) {
				continue;
			}
			if ( user_can( $user, $cap ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if settings are locked for the current user.
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return bool True if settings are locked (user cannot change global config).
	 */
	public function is_settings_locked( int $user_id = 0 ): bool {
		if ( empty( $this->settings['enable_settings_lock'] ) ) {
			return false;
		}

		$user = $user_id > 0 ? get_userdata( $user_id ) : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return true;
		}

		// Only manage_options (admin) can change settings when lock is on.
		return ! user_can( $user, 'manage_options' );
	}

	// ── Grant / Revoke Capabilities ──────────────────────────────────────────

	/**
	 * Grant a module capability to a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $module  Module key.
	 * @return bool
	 */
	public function grant_module( int $user_id, string $module ): bool {
		$cap = self::MODULES[ $module ] ?? '';
		if ( empty( $cap ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$user->add_cap( $cap );
		return true;
	}

	/**
	 * Revoke a module capability from a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $module  Module key.
	 * @return bool
	 */
	public function revoke_module( int $user_id, string $module ): bool {
		$cap = self::MODULES[ $module ] ?? '';
		if ( empty( $cap ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$user->remove_cap( $cap );
		return true;
	}

	/**
	 * Grant view-only access to a user (typically authors).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function grant_view_only( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Add view cap, remove all module caps.
		$user->add_cap( self::CAP_VIEW );
		foreach ( self::MODULES as $cap ) {
			$user->remove_cap( $cap );
		}

		return true;
	}

	/**
	 * Get a summary of all users and their Search Intel permissions.
	 *
	 * @return array
	 */
	public function get_permissions_summary(): array {
		$users   = get_users( [ 'number' => 100 ] );
		$summary = [];

		foreach ( $users as $user ) {
			$modules = [];
			foreach ( self::MODULES as $module => $cap ) {
				if ( user_can( $user, $cap ) || user_can( $user, 'manage_options' ) ) {
					$modules[] = $module;
				}
			}

			$summary[] = [
				'user_id'   => $user->ID,
				'login'     => $user->user_login,
				'role'      => implode( ', ', $user->roles ),
				'view_only' => $this->is_view_only( $user->ID ),
				'modules'   => $modules,
			];
		}

		return $summary;
	}
}
