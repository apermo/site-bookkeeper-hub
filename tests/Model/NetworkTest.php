<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Model;

use Apermo\SiteBookkeeperHub\Model\Network;
use PHPUnit\Framework\TestCase;

class NetworkTest extends TestCase {

	public function testFromRowCreatesNetwork(): void {
		$row = [
			'id' => 'net-uuid-123',
			'main_site_url' => 'https://network.example.tld',
			'token_hash' => '$argon2id$hash',
			'label' => 'My Network',
			'created_at' => '2026-04-01T12:00:00Z',
			'updated_at' => '2026-04-01T12:00:00Z',
		];

		$network = Network::fromRow( $row );

		$this->assertSame( 'net-uuid-123', $network->id );
		$this->assertSame( 'https://network.example.tld', $network->mainSiteUrl );
		$this->assertSame( '$argon2id$hash', $network->tokenHash );
		$this->assertSame( 'My Network', $network->label );
		$this->assertSame( '2026-04-01T12:00:00Z', $network->createdAt );
		$this->assertSame( '2026-04-01T12:00:00Z', $network->updatedAt );
	}

	public function testFromRowHandlesNullLabel(): void {
		$row = [
			'id' => 'net-uuid-456',
			'main_site_url' => 'https://network2.example.tld',
			'token_hash' => '$argon2id$hash',
			'label' => null,
			'created_at' => '2026-04-01T12:00:00Z',
			'updated_at' => '2026-04-01T12:00:00Z',
		];

		$network = Network::fromRow( $row );

		$this->assertNull( $network->label );
	}

	public function testConstructorSetsProperties(): void {
		$network = new Network(
			id: 'net-uuid-789',
			mainSiteUrl: 'https://network3.example.tld',
			tokenHash: '$argon2id$hash',
			label: 'Test',
			createdAt: '2026-04-01T12:00:00Z',
			updatedAt: '2026-04-02T12:00:00Z',
		);

		$this->assertSame( 'net-uuid-789', $network->id );
		$this->assertSame( 'https://network3.example.tld', $network->mainSiteUrl );
		$this->assertSame( 'Test', $network->label );
	}
}
