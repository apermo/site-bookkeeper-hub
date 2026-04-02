<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Storage;

use Apermo\SiteBookkeeperHub\Model\Network;
use Apermo\SiteBookkeeperHub\Model\NetworkReport;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;
use Apermo\SiteBookkeeperHub\Tests\TestCase;

class NetworkRepositoryTest extends TestCase {

	private NetworkRepository $networkRepo;

	protected function setUp(): void {
		parent::setUp();
		$this->networkRepo = new NetworkRepository( $this->database );
	}

	public function testAddNetworkCreatesRecord(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );

		$network = $this->networkRepo->addNetwork(
			'net-uuid-001',
			'https://network.example.tld',
			$hash,
			'Test Network',
		);

		$this->assertInstanceOf( Network::class, $network );
		$this->assertSame( 'net-uuid-001', $network->id );
		$this->assertSame( 'https://network.example.tld', $network->mainSiteUrl );
		$this->assertSame( 'Test Network', $network->label );
	}

	public function testFindNetworkById(): void {
		$this->createTestNetwork( 'https://network.example.tld', 'Test' );

		$network = $this->networkRepo->findNetworkById( 'net-uuid-001' );
		$this->assertNull( $network );

		$hash = password_hash( 'token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-uuid-001', 'https://find.example.tld', $hash, 'Find Me' );

		$found = $this->networkRepo->findNetworkById( 'net-uuid-001' );
		$this->assertNotNull( $found );
		$this->assertSame( 'https://find.example.tld', $found->mainSiteUrl );
	}

	public function testFindNetworkByMainSiteUrl(): void {
		$hash = password_hash( 'token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-uuid-002', 'https://byurl.example.tld', $hash, null );

		$found = $this->networkRepo->findNetworkByMainSiteUrl( 'https://byurl.example.tld' );
		$this->assertNotNull( $found );
		$this->assertSame( 'net-uuid-002', $found->id );

		$notFound = $this->networkRepo->findNetworkByMainSiteUrl( 'https://nonexistent.example.tld' );
		$this->assertNull( $notFound );
	}

	public function testGetAllNetworks(): void {
		$hash = password_hash( 'token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-001', 'https://alpha.example.tld', $hash, 'Alpha' );
		$this->networkRepo->addNetwork( 'net-002', 'https://beta.example.tld', $hash, 'Beta' );

		$networks = $this->networkRepo->getAllNetworks();
		$this->assertCount( 2, $networks );
		// Ordered by main_site_url.
		$this->assertSame( 'https://alpha.example.tld', $networks[0]->mainSiteUrl );
		$this->assertSame( 'https://beta.example.tld', $networks[1]->mainSiteUrl );
	}

	public function testUpdateNetworkTokenHash(): void {
		$hash = password_hash( 'old-token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-rotate', 'https://rotate.example.tld', $hash, null );

		$newHash = password_hash( 'new-token', PASSWORD_ARGON2ID );
		$this->networkRepo->updateNetworkTokenHash( 'net-rotate', $newHash );

		$network = $this->networkRepo->findNetworkById( 'net-rotate' );
		$this->assertNotNull( $network );
		$this->assertTrue( password_verify( 'new-token', $network->tokenHash ) );
		$this->assertFalse( password_verify( 'old-token', $network->tokenHash ) );
	}

	public function testUpsertNetworkReport(): void {
		$hash = password_hash( 'token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-rpt', 'https://report.example.tld', $hash, null );

		$report = NetworkReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00+00:00',
				'main_site_url' => 'https://report.example.tld',
				'subsites' => [
					[
						'blog_id' => 1,
						'url' => 'https://report.example.tld',
						'label' => 'Main',
					],
				],
				'network_plugins' => [
					[
						'slug' => 'akismet',
						'name' => 'Akismet',
						'version' => '5.6',
						'update_available' => null,
					],
				],
				'super_admins' => [
					[
						'user_login' => 'admin',
						'display_name' => 'Admin',
						'email' => 'admin@example.tld',
					],
				],
				'network_settings' => [
					[
						'key' => 'site_name',
						'label' => 'Network Name',
						'value' => 'My Network',
					],
				],
			],
		);

		$this->networkRepo->upsertNetworkReport( 'net-rpt', $report );

		$stored = $this->networkRepo->getNetworkReport( 'net-rpt' );
		$this->assertNotNull( $stored );
		$this->assertSame( 1, (int) $stored['schema_version'] );
		$this->assertSame( 1, (int) $stored['subsite_count'] );

		$plugins = $this->networkRepo->getNetworkPlugins( 'net-rpt' );
		$this->assertCount( 1, $plugins );
		$this->assertSame( 'akismet', $plugins[0]['slug'] );

		$users = $this->networkRepo->getNetworkUsers( 'net-rpt' );
		$this->assertCount( 1, $users );
		$this->assertSame( 'admin', $users[0]['user_login'] );
	}

	public function testUpsertNetworkReportReplacesData(): void {
		$hash = password_hash( 'token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-replace', 'https://replace.example.tld', $hash, null );

		$report1 = NetworkReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00+00:00',
				'main_site_url' => 'https://replace.example.tld',
				'subsites' => [
					[ 'blog_id' => 1, 'url' => 'https://replace.example.tld', 'label' => 'Main' ],
				],
				'network_plugins' => [
					[ 'slug' => 'akismet', 'name' => 'Akismet', 'version' => '5.5', 'update_available' => '5.6' ],
				],
				'super_admins' => [
					[ 'user_login' => 'admin', 'display_name' => 'Admin', 'email' => 'admin@example.tld' ],
				],
			],
		);
		$this->networkRepo->upsertNetworkReport( 'net-replace', $report1 );

		$report2 = NetworkReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-02T12:00:00+00:00',
				'main_site_url' => 'https://replace.example.tld',
				'subsites' => [
					[ 'blog_id' => 1, 'url' => 'https://replace.example.tld', 'label' => 'Main' ],
					[ 'blog_id' => 2, 'url' => 'https://sub.replace.example.tld', 'label' => 'Sub' ],
				],
				'network_plugins' => [
					[ 'slug' => 'akismet', 'name' => 'Akismet', 'version' => '5.6', 'update_available' => null ],
					[ 'slug' => 'jetpack', 'name' => 'Jetpack', 'version' => '13.0', 'update_available' => null ],
				],
				'super_admins' => [
					[ 'user_login' => 'admin', 'display_name' => 'Admin', 'email' => 'admin@example.tld' ],
					[ 'user_login' => 'super', 'display_name' => 'Super', 'email' => 'super@example.tld' ],
				],
			],
		);
		$this->networkRepo->upsertNetworkReport( 'net-replace', $report2 );

		$stored = $this->networkRepo->getNetworkReport( 'net-replace' );
		$this->assertSame( 2, (int) $stored['subsite_count'] );

		$plugins = $this->networkRepo->getNetworkPlugins( 'net-replace' );
		$this->assertCount( 2, $plugins );

		$users = $this->networkRepo->getNetworkUsers( 'net-replace' );
		$this->assertCount( 2, $users );
	}

	public function testGetNetworkReportReturnsNullForUnknown(): void {
		$result = $this->networkRepo->getNetworkReport( 'nonexistent' );
		$this->assertNull( $result );
	}

	public function testGetNetworkPluginsReturnsEmptyForUnknown(): void {
		$result = $this->networkRepo->getNetworkPlugins( 'nonexistent' );
		$this->assertSame( [], $result );
	}

	public function testGetNetworkUsersReturnsEmptyForUnknown(): void {
		$result = $this->networkRepo->getNetworkUsers( 'nonexistent' );
		$this->assertSame( [], $result );
	}

	/**
	 * Helper to create a test network.
	 *
	 * @param string      $url   Main site URL.
	 * @param string|null $label Optional label.
	 *
	 * @return array{token: string, network: Network}
	 */
	private function createTestNetwork( string $url, ?string $label = null ): array {
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );
		$network = $this->networkRepo->addNetwork( $this->generateUuid(), $url, $hash, $label );

		return [ 'token' => $token, 'network' => $network ];
	}
}
