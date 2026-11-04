<?php
/**
 * Google API access via a service account (no third-party OAuth proxy).
 *
 * The site owner creates a service account in Google Cloud, adds its email
 * as a user on their Search Console property, and pastes the JSON key here.
 * Access tokens are minted locally with a signed JWT and cached for ~55 min.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service-account token helper.
 */
class Google_Auth {

	public const KEY_OPTION = 'jat_search_intel_google_service_account';

	public const SCOPE_SEARCH_CONSOLE = 'https://www.googleapis.com/auth/webmasters.readonly';

	private const TOKEN_TRANSIENT = 'jat_search_intel_google_token_';

	/**
	 * Store a service account key (encrypted at rest).
	 *
	 * @param string $json Service account JSON key.
	 * @return true|\WP_Error
	 */
	public static function save_key( string $json ) {
		$key = json_decode( $json, true );
		if ( ! is_array( $key ) || empty( $key['client_email'] ) || empty( $key['private_key'] ) || ( $key['type'] ?? '' ) !== 'service_account' ) {
			return new \WP_Error( 'jsi_bad_key', __( 'That is not a Google service account JSON key.', 'jat-search-intel' ) );
		}

		$minimal = wp_json_encode(
			[
				'client_email' => sanitize_email( $key['client_email'] ),
				'private_key'  => (string) $key['private_key'],
				'token_uri'    => esc_url_raw( $key['token_uri'] ?? 'https://oauth2.googleapis.com/token' ),
			]
		);

		update_option( self::KEY_OPTION, ( new AI_Engine( [] ) )->encrypt( (string) $minimal ), false );
		self::forget_tokens();
		return true;
	}

	/**
	 * Remove the stored key.
	 */
	public static function delete_key(): void {
		delete_option( self::KEY_OPTION );
		self::forget_tokens();
	}

	/**
	 * Decrypted key, or null when none is stored.
	 *
	 * @return array{client_email:string, private_key:string, token_uri:string}|null
	 */
	public static function get_key(): ?array {
		$stored = (string) get_option( self::KEY_OPTION, '' );
		if ( '' === $stored ) {
			return null;
		}
		$key = json_decode( ( new AI_Engine( [] ) )->decrypt( $stored ), true );
		return is_array( $key ) && ! empty( $key['client_email'] ) ? $key : null;
	}

	/**
	 * Service account email (to show the user which address to add in Search Console).
	 *
	 * @return string
	 */
	public static function client_email(): string {
		$key = self::get_key();
		return $key ? $key['client_email'] : '';
	}

	/**
	 * Get an access token for a scope.
	 *
	 * @param string $scope OAuth scope.
	 * @return string|\WP_Error
	 */
	public static function get_token( string $scope ) {
		$cache_key = self::TOKEN_TRANSIENT . md5( $scope );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$key = self::get_key();
		if ( ! $key ) {
			return new \WP_Error( 'jsi_no_key', __( 'No Google service account is connected.', 'jat-search-intel' ) );
		}

		$assertion = self::build_jwt( $key, $scope, time() );
		if ( is_wp_error( $assertion ) ) {
			return $assertion;
		}

		$response = wp_remote_post(
			$key['token_uri'] ?: 'https://oauth2.googleapis.com/token',
			[
				'timeout' => 15,
				'body'    => [
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $assertion,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$message = is_array( $body ) ? (string) ( $body['error_description'] ?? $body['error'] ?? '' ) : '';
			return new \WP_Error( 'jsi_token', $message ?: __( 'Google did not return an access token.', 'jat-search-intel' ) );
		}

		$ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 300 );
		set_transient( $cache_key, (string) $body['access_token'], $ttl );

		return (string) $body['access_token'];
	}

	/**
	 * Build and sign the JWT assertion (RS256).
	 *
	 * @param array  $key   Service account key.
	 * @param string $scope Scope.
	 * @param int    $now   Unix time.
	 * @return string|\WP_Error
	 */
	public static function build_jwt( array $key, string $scope, int $now ) {
		if ( ! function_exists( 'openssl_sign' ) ) {
			return new \WP_Error( 'jsi_openssl', __( 'The PHP OpenSSL extension is required to connect Google services.', 'jat-search-intel' ) );
		}

		$header = [
			'alg' => 'RS256',
			'typ' => 'JWT',
		];
		$claims = [
			'iss'   => $key['client_email'],
			'scope' => $scope,
			'aud'   => $key['token_uri'] ?: 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		];

		$unsigned  = self::base64url( (string) wp_json_encode( $header ) ) . '.' . self::base64url( (string) wp_json_encode( $claims ) );
		$signature = '';
		$private   = openssl_pkey_get_private( $key['private_key'] );
		if ( ! $private || ! openssl_sign( $unsigned, $signature, $private, OPENSSL_ALGO_SHA256 ) ) {
			return new \WP_Error( 'jsi_sign', __( 'Could not sign the request with the service account key.', 'jat-search-intel' ) );
		}

		return $unsigned . '.' . self::base64url( $signature );
	}

	/**
	 * Base64url encoding (RFC 7515).
	 *
	 * @param string $data Raw bytes.
	 * @return string
	 */
	public static function base64url( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- JWT encoding required by Google OAuth.
	}

	/**
	 * Drop cached access tokens.
	 */
	private static function forget_tokens(): void {
		delete_transient( self::TOKEN_TRANSIENT . md5( self::SCOPE_SEARCH_CONSOLE ) );
	}
}
