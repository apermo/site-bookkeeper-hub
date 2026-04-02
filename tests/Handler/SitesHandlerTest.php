<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Handler;

use Apermo\SiteBookkeeperHub\Model\SiteReport;
use Apermo\SiteBookkeeperHub\Tests\TestCase;

class SitesHandlerTest extends TestCase {

	public function testGetSitesSummary(): void {
		$result1 = $this->createTestSite( 'https://alpha.tld', 'Alpha' );
		$result2 = $this->createTestSite( 'https://beta.tld', 'Beta' );

		$report1 = SiteReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00Z',
				'site_url' => 'https://alpha.tld',
				'environment' => [
					'wp_version' => '6.7',
					'php_version' => '8.2.10',
				],
				'plugins' => [
					[
						'slug' => 'akismet',
						'name' => 'Akismet',
						'version' => '5.3',
						'active' => true,
						'update_available' => '5.4',
					],
				],
				'themes' => [
					[
						'slug' => 'twentytwentyfive',
						'name' => 'Twenty Twenty-Five',
						'version' => '1.0',
						'active' => true,
						'update_available' => '1.1',
					],
				],
			],
		);
		$this->repo->upsertReport( $result1['site']->id, $report1 );

		$sites = $this->repo->getAllSites();
		$this->assertCount( 2, $sites );

		// Verify the report data is accessible.
		$report = $this->repo->getReport( $result1['site']->id );
		$this->assertNotNull( $report );
		$this->assertSame( '6.7', $report['wp_version'] );

		// Count pending updates.
		$plugins = $this->repo->getPlugins( $result1['site']->id );
		$pendingPluginUpdates = 0;
		foreach ( $plugins as $plugin ) {
			if ( $plugin['update_available'] !== null ) {
				$pendingPluginUpdates++;
			}
		}
		$this->assertSame( 1, $pendingPluginUpdates );

		$themes = $this->repo->getThemes( $result1['site']->id );
		$pendingThemeUpdates = 0;
		foreach ( $themes as $theme ) {
			if ( $theme['update_available'] !== null ) {
				$pendingThemeUpdates++;
			}
		}
		$this->assertSame( 1, $pendingThemeUpdates );
	}

	public function testStaleDetection(): void {
		$result = $this->createTestSite( 'https://stale.tld' );

		// Manually set updated_at to 72 hours ago.
		$past = gmdate( 'Y-m-d\TH:i:s\Z', time() - ( 72 * 3600 ) );
		$this->database->pdo()->prepare(
			'UPDATE sites SET updated_at = :updated_at WHERE id = :id',
		)->execute(
			[
				':updated_at' => $past,
				':id' => $result['site']->id,
			],
		);

		$site = $this->repo->findSiteById( $result['site']->id );
		$this->assertNotNull( $site );

		$updatedAt = strtotime( $site->updatedAt );
		$staleThreshold = time() - ( 48 * 3600 );
		$isStale = $updatedAt < $staleThreshold;

		$this->assertTrue( $isStale );
	}

	public function testNotStaleWithinThreshold(): void {
		$result = $this->createTestSite( 'https://fresh.tld' );

		// Site was just created, so it should not be stale.
		$site = $this->repo->findSiteById( $result['site']->id );
		$this->assertNotNull( $site );

		$updatedAt = strtotime( $site->updatedAt );
		$staleThreshold = time() - ( 48 * 3600 );
		$isStale = $updatedAt < $staleThreshold;

		$this->assertFalse( $isStale );
	}
}
