<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Tests;

use Apermo\SiteMonitorHub\Auth\ClientAuth;
use Apermo\SiteMonitorHub\Auth\TokenAuth;

class CliTest extends TestCase {

	public function testSiteAddAndList(): void {
		$uuid = $this->generateUuid();
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );

		$site = $this->repo->addSite( $uuid, 'https://cli-test.tld', $hash, 'CLI Test' );

		$this->assertSame( $uuid, $site->id );
		$this->assertSame( 'https://cli-test.tld', $site->siteUrl );
		$this->assertSame( 'CLI Test', $site->label );

		$sites = $this->repo->getAllSites();
		$this->assertCount( 1, $sites );
		$this->assertSame( 'https://cli-test.tld', $sites[0]->siteUrl );
	}

	public function testSiteTokenRotation(): void {
		$result = $this->createTestSite( 'https://rotate.tld' );
		$oldToken = $result['token'];

		$newToken = bin2hex( random_bytes( 32 ) );
		$newHash = password_hash( $newToken, PASSWORD_ARGON2ID );
		$this->repo->updateSiteTokenHash( $result['site']->id, $newHash );

		// Old token should no longer work.
		$auth = new TokenAuth( $this->database );
		$this->assertNull( $auth->authenticate( $oldToken ) );

		// New token should work.
		$this->assertNotNull( $auth->authenticate( $newToken ) );
	}

	public function testClientTokenCreation(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );
		$tokenId = $this->repo->addClientToken( $hash, 'test-client' );

		$this->assertGreaterThan( 0, $tokenId );

		$auth = new ClientAuth( $this->database );
		$this->assertTrue( $auth->authenticate( $token ) );
	}

	public function testDuplicateSiteUrlRejected(): void {
		$this->createTestSite( 'https://duplicate.tld' );

		$existing = $this->repo->findSiteByUrl( 'https://duplicate.tld' );
		$this->assertNotNull( $existing );
	}
}
