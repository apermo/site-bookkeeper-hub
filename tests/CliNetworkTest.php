<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests;

use Apermo\SiteBookkeeperHub\Auth\NetworkAuth;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;

class CliNetworkTest extends TestCase {

	private NetworkRepository $networkRepo;

	protected function setUp(): void {
		parent::setUp();
		$this->networkRepo = new NetworkRepository( $this->database );
	}

	public function testNetworkAddAndList(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );

		$network = $this->networkRepo->addNetwork(
			$this->generateUuid(),
			'https://cli-net.example.tld',
			$hash,
			'CLI Network',
		);

		$this->assertSame( 'https://cli-net.example.tld', $network->mainSiteUrl );
		$this->assertSame( 'CLI Network', $network->label );

		$networks = $this->networkRepo->getAllNetworks();
		$this->assertCount( 1, $networks );
		$this->assertSame( 'https://cli-net.example.tld', $networks[0]->mainSiteUrl );
	}

	public function testNetworkTokenRotation(): void {
		$oldToken = bin2hex( random_bytes( 32 ) );
		$oldHash = password_hash( $oldToken, PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork(
			'net-rotate',
			'https://rotate-net.example.tld',
			$oldHash,
			null,
		);

		$newToken = bin2hex( random_bytes( 32 ) );
		$newHash = password_hash( $newToken, PASSWORD_ARGON2ID );
		$this->networkRepo->updateNetworkTokenHash( 'net-rotate', $newHash );

		$auth = new NetworkAuth( $this->database );

		// Old token should fail.
		$this->assertNull( $auth->authenticate( $oldToken ) );

		// New token should work.
		$network = $auth->authenticate( $newToken );
		$this->assertNotNull( $network );
		$this->assertSame( 'net-rotate', $network->id );
	}
}
