<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Iterochat_OAuth {

	/** base64url without padding. */
	public static function base64url( $bin ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url encoding for PKCE (RFC 7636), not obfuscation.
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	/** A high-entropy PKCE code verifier (43 chars from 32 random bytes). */
	public static function generate_verifier() {
		return self::base64url( random_bytes( 32 ) );
	}

	/** S256 code challenge for a verifier. */
	public static function challenge_from_verifier( $verifier ) {
		return self::base64url( hash( 'sha256', $verifier, true ) );
	}

	/**
	 * Start a device authorization. Returns the response array
	 * {device_code,user_code,verification_uri,verification_uri_complete,interval,expires_in}
	 * or a WP_Error.
	 */
	public static function request_device_code( $api_url, $challenge, $site_label ) {
		$res = wp_remote_post( $api_url . '/api/oauth/device/code', array(
			'timeout' => 15,
			'body'    => array(
				'client_id'             => ITEROCHAT_CLIENT_ID,
				'scope'                 => ITEROCHAT_SCOPE,
				'code_challenge'        => $challenge,
				'code_challenge_method' => 'S256',
				'site_label'            => $site_label,
			),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$status = wp_remote_retrieve_response_code( $res );
		$json   = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $status || empty( $json['device_code'] ) ) {
			return new WP_Error( 'iterochat_device', 'Could not start the connection (HTTP ' . intval( $status ) . ').' );
		}
		return $json;
	}

	/**
	 * Poll the token endpoint once. Returns:
	 *  - array (token payload) on success,
	 *  - string 'authorization_pending' | 'expired_token' | 'access_denied' on those OAuth errors,
	 *  - WP_Error on transport or other failure.
	 */
	public static function poll_token( $api_url, $device_code, $verifier ) {
		$res = wp_remote_post( $api_url . '/api/oauth/token', array(
			'timeout' => 15,
			'body'    => array(
				'grant_type'    => 'urn:ietf:params:oauth:grant-type:device_code',
				'device_code'   => $device_code,
				'code_verifier' => $verifier,
				'client_id'     => ITEROCHAT_CLIENT_ID,
			),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$status = wp_remote_retrieve_response_code( $res );
		$json   = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 === $status && ! empty( $json['access_token'] ) ) {
			return $json;
		}
		$err = is_array( $json ) && ! empty( $json['error'] ) ? $json['error'] : 'invalid_grant';
		if ( in_array( $err, array( 'authorization_pending', 'expired_token', 'access_denied' ), true ) ) {
			return $err;
		}
		return new WP_Error( 'iterochat_token', $err );
	}

	/** Read the live connection (widget key + org). Array on success; WP_Error code 'revoked' on HTTP 401. */
	public static function read_connection( $api_url, $access_token ) {
		$res = wp_remote_get( $api_url . '/api/connect/widget', array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$status = wp_remote_retrieve_response_code( $res );
		if ( 401 === $status ) {
			return new WP_Error( 'revoked', 'This connection was revoked.' );
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $status || empty( $json['widget_key'] ) ) {
			return new WP_Error( 'iterochat_read', 'Could not read the connection (HTTP ' . intval( $status ) . ').' );
		}
		return $json;
	}
}
