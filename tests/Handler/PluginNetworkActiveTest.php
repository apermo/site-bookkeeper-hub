<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Handler;

use Apermo\SiteBookkeeperHub\Model\SiteReport;
use Apermo\SiteBookkeeperHub\Tests\TestCase;

class PluginNetworkActiveTest extends TestCase {

	public function testNetworkActiveStoredAndRetrieved(): void {
		$result = $this->createTestSite( 'https://multisite.example.tld' );
		$site = $result['site'];

		$report = SiteReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00Z',
				'site_url' => 'https://multisite.example.tld',
				'environment' => [ 'wp_version' => '6.7' ],
				'plugins' => [
					[
						'slug' => 'akismet',
						'name' => 'Akismet',
						'version' => '5.6',
						'active' => true,
						'network_active' => true,
					],
					[
						'slug' => 'jetpack',
						'name' => 'Jetpack',
						'version' => '13.0',
						'active' => true,
						'network_active' => false,
					],
				],
			],
		);

		$this->repo->upsertReport( $site->id, $report );

		$plugins = $this->repo->getPlugins( $site->id );
		$this->assertCount( 2, $plugins );

		$akismet = $plugins[0];
		$this->assertSame( 'akismet', $akismet['slug'] );
		$this->assertSame( 1, (int) $akismet['network_active'] );

		$jetpack = $plugins[1];
		$this->assertSame( 'jetpack', $jetpack['slug'] );
		$this->assertSame( 0, (int) $jetpack['network_active'] );
	}

	public function testNetworkActiveDefaultsToZero(): void {
		$result = $this->createTestSite( 'https://single.example.tld' );
		$site = $result['site'];

		$report = SiteReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00Z',
				'site_url' => 'https://single.example.tld',
				'environment' => [ 'wp_version' => '6.7' ],
				'plugins' => [
					[
						'slug' => 'akismet',
						'name' => 'Akismet',
						'version' => '5.6',
						'active' => true,
					],
				],
			],
		);

		$this->repo->upsertReport( $site->id, $report );

		$plugins = $this->repo->getPlugins( $site->id );
		$this->assertCount( 1, $plugins );
		$this->assertSame( 0, (int) $plugins[0]['network_active'] );
	}

	public function testAllPluginsIncludesNetworkActive(): void {
		$result = $this->createTestSite( 'https://cross.example.tld' );

		$this->repo->upsertReport(
			$result['site']->id,
			SiteReport::fromArray(
				[
					'schema_version' => 1,
					'timestamp' => '2026-04-01T12:00:00Z',
					'site_url' => 'https://cross.example.tld',
					'environment' => [ 'wp_version' => '6.7' ],
					'plugins' => [
						[
							'slug' => 'akismet',
							'name' => 'Akismet',
							'version' => '5.6',
							'active' => true,
							'network_active' => true,
						],
					],
				],
			),
		);

		$allPlugins = $this->repo->getAllPlugins();
		$this->assertCount( 1, $allPlugins );
		$this->assertArrayHasKey( 'network_active', $allPlugins[0] );
		$this->assertSame( 1, (int) $allPlugins[0]['network_active'] );
	}
}
