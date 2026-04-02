<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Model;

use Apermo\SiteBookkeeperHub\Model\NetworkReport;
use PHPUnit\Framework\TestCase;

class NetworkReportTest extends TestCase {

	public function testFromArrayCreatesReport(): void {
		$data = [
			'schema_version' => 1,
			'timestamp' => '2026-04-01T12:00:00+00:00',
			'main_site_url' => 'https://network.example.tld',
			'subsites' => [
				[
					'blog_id' => 1,
					'url' => 'https://network.example.tld',
					'label' => 'Main Site',
				],
				[
					'blog_id' => 2,
					'url' => 'https://sub.network.example.tld',
					'label' => 'Subsite',
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
					'key' => 'registration',
					'label' => 'Registration',
					'value' => 'none',
				],
			],
		];

		$report = NetworkReport::fromArray( $data );

		$this->assertSame( 1, $report->schemaVersion );
		$this->assertSame( '2026-04-01T12:00:00+00:00', $report->timestamp );
		$this->assertSame( 'https://network.example.tld', $report->mainSiteUrl );
		$this->assertCount( 2, $report->subsites );
		$this->assertCount( 1, $report->networkPlugins );
		$this->assertCount( 1, $report->superAdmins );
		$this->assertCount( 1, $report->networkSettings );
	}

	public function testFromArrayWithDefaults(): void {
		$data = [
			'schema_version' => 1,
			'timestamp' => '2026-04-01T12:00:00+00:00',
			'main_site_url' => 'https://network.example.tld',
		];

		$report = NetworkReport::fromArray( $data );

		$this->assertSame( [], $report->subsites );
		$this->assertSame( [], $report->networkPlugins );
		$this->assertSame( [], $report->superAdmins );
		$this->assertSame( [], $report->networkSettings );
	}

	public function testFromArrayMissingFieldsUseDefaults(): void {
		$report = NetworkReport::fromArray( [] );

		$this->assertSame( 1, $report->schemaVersion );
		$this->assertSame( '', $report->timestamp );
		$this->assertSame( '', $report->mainSiteUrl );
	}
}
