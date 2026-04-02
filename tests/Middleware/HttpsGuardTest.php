<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Middleware;

use Apermo\SiteBookkeeperHub\Middleware\HttpsGuard;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Apermo\SiteBookkeeperHub\Middleware\HttpsGuard
 */
class HttpsGuardTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// Ensure the escape hatch is off by default.
		putenv( 'ALLOW_HTTP' );
	}

	protected function tearDown(): void {
		putenv( 'ALLOW_HTTP' );
		parent::tearDown();
	}

	public function testRejectsPlainHttp(): void {
		$server = [
			'REQUEST_SCHEME' => 'http',
		];

		$this->assertFalse( HttpsGuard::isSecure( $server ) );
	}

	public function testAcceptsHttpsViaServerVar(): void {
		$server = [
			'HTTPS' => 'on',
		];

		$this->assertTrue( HttpsGuard::isSecure( $server ) );
	}

	public function testAcceptsHttpsViaForwardedProto(): void {
		$server = [
			'HTTP_X_FORWARDED_PROTO' => 'https',
		];

		$this->assertTrue( HttpsGuard::isSecure( $server ) );
	}

	public function testAcceptsHttpsViaRequestScheme(): void {
		$server = [
			'REQUEST_SCHEME' => 'https',
		];

		$this->assertTrue( HttpsGuard::isSecure( $server ) );
	}

	public function testAllowHttpEnvBypassesCheck(): void {
		putenv( 'ALLOW_HTTP=true' );

		$this->assertTrue( HttpsGuard::isHttpAllowed() );
	}

	public function testAllowHttpEnvNotSetMeansNotAllowed(): void {
		putenv( 'ALLOW_HTTP' );

		$this->assertFalse( HttpsGuard::isHttpAllowed() );
	}

	public function testCheckReturnsTrueForHttps(): void {
		$server = [
			'HTTPS' => 'on',
		];

		$this->assertTrue( HttpsGuard::check( $server ) );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCheckReturnsFalseForPlainHttp(): void {
		$server = [
			'REQUEST_SCHEME' => 'http',
		];

		$this->assertFalse( HttpsGuard::check( $server ) );
	}

	public function testCheckReturnsTrueWhenAllowHttpSet(): void {
		putenv( 'ALLOW_HTTP=true' );

		$server = [
			'REQUEST_SCHEME' => 'http',
		];

		$this->assertTrue( HttpsGuard::check( $server ) );
	}

	public function testEmptyServerIsNotSecure(): void {
		$this->assertFalse( HttpsGuard::isSecure( [] ) );
	}

	public function testHttpsOffIsNotSecure(): void {
		$server = [
			'HTTPS' => 'off',
		];

		$this->assertFalse( HttpsGuard::isSecure( $server ) );
	}

	public function testAllowHttpFalseStringIsNotAllowed(): void {
		putenv( 'ALLOW_HTTP=false' );

		$this->assertFalse( HttpsGuard::isHttpAllowed() );
	}
}
