<?php

class Iterochat_OAuth {

	/** base64url without padding. */
	public static function base64url( $bin ) {
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
}
