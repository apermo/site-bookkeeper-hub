<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Storage;

use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;
use Apermo\SiteBookkeeperHub\Tests\TestCase;

class SiteRepositoryNetworkTest extends TestCase {

	private NetworkRepository $networkRepo;

	protected function setUp(): void {
		parent::setUp();
		$this->networkRepo = new NetworkRepository( $this->database );
	}

	public function testFindOrCreateSiteForNetworkCreatesNew(): void {
		$hash = password_hash( 'token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-001', 'https://network.example.tld', $hash, null );

		$site = $this->repo->findOrCreateSiteForNetwork(
			'net-001',
			'https://sub.network.example.tld',
			$hash,
		);

		$this->assertSame( 'https://sub.network.example.tld', $site->siteUrl );

		// Verify network_id is set.
		$stmt = $this->database->pdo()->prepare(
			'SELECT network_id FROM sites WHERE id = :id',
		);
		$stmt->execute( [ ':id' => $site->id ] );
		$row = $stmt->fetch();
		$this->assertSame( 'net-001', $row['network_id'] );
	}

	public function testFindOrCreateSiteForNetworkFindsExisting(): void {
		$hash = password_hash( 'token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-002', 'https://network2.example.tld', $hash, null );

		$site1 = $this->repo->findOrCreateSiteForNetwork(
			'net-002',
			'https://sub.network2.example.tld',
			$hash,
		);
		$site2 = $this->repo->findOrCreateSiteForNetwork(
			'net-002',
			'https://sub.network2.example.tld',
			$hash,
		);

		$this->assertSame( $site1->id, $site2->id );
	}

	public function testGetSitesByNetworkId(): void {
		$hash = password_hash( 'token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-003', 'https://network3.example.tld', $hash, null );

		$this->repo->findOrCreateSiteForNetwork( 'net-003', 'https://sub1.network3.example.tld', $hash );
		$this->repo->findOrCreateSiteForNetwork( 'net-003', 'https://sub2.network3.example.tld', $hash );

		// A standalone site should not appear.
		$this->createTestSite( 'https://standalone.example.tld' );

		$sites = $this->repo->getSitesByNetworkId( 'net-003' );
		$this->assertCount( 2, $sites );

		$urls = array_map( fn ( $s ) => $s->siteUrl, $sites );
		$this->assertContains( 'https://sub1.network3.example.tld', $urls );
		$this->assertContains( 'https://sub2.network3.example.tld', $urls );
	}

	public function testGetSitesByNetworkIdReturnsEmptyForUnknown(): void {
		$sites = $this->repo->getSitesByNetworkId( 'nonexistent' );
		$this->assertSame( [], $sites );
	}
}
