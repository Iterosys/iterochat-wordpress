<?php
use PHPUnit\Framework\TestCase;

final class PkceTest extends TestCase {
	public function test_challenge_matches_rfc7636_vector() {
		$verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
		$expected = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';
		$this->assertSame( $expected, Iterochat_OAuth::challenge_from_verifier( $verifier ) );
	}

	public function test_generated_verifier_is_url_safe_and_long_enough() {
		$v = Iterochat_OAuth::generate_verifier();
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9\-_]{43,128}$/', $v );
	}
}
