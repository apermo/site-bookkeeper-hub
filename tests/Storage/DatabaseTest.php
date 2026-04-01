<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Tests\Storage;

use Apermo\SiteMonitorHub\Storage\Database;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase {

	private string $dbPath;

	protected function setUp(): void {
		$this->dbPath = sys_get_temp_dir() . '/smh_test_' . uniqid() . '.sqlite';
	}

	protected function tearDown(): void {
		if ( file_exists( $this->dbPath ) ) {
			unlink( $this->dbPath );
		}
	}

	public function testMigrateCreatesAllTables(): void {
		$db = new Database( $this->dbPath );
		$db->migrate();

		$expected = [
			'sites',
			'reports',
			'site_plugins',
			'site_themes',
			'site_custom_fields',
			'client_tokens',
			'site_users',
			'site_user_meta',
			'site_roles',
		];

		$stmt   = $db->pdo()->query( "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name" );
		$tables = $stmt->fetchAll( \PDO::FETCH_COLUMN );

		foreach ( $expected as $table ) {
			$this->assertContains( $table, $tables, "Table '{$table}' should exist." );
		}
	}

	public function testMigrateIsIdempotent(): void {
		$db = new Database( $this->dbPath );
		$db->migrate();
		$db->migrate();

		$stmt  = $db->pdo()->query( "SELECT count(*) FROM sqlite_master WHERE type='table' AND name='sites'" );
		$count = (int) $stmt->fetchColumn();

		$this->assertSame( 1, $count );
	}

	public function testForeignKeysEnabled(): void {
		$db   = new Database( $this->dbPath );
		$stmt = $db->pdo()->query( 'PRAGMA foreign_keys' );
		$val  = (int) $stmt->fetchColumn();

		$this->assertSame( 1, $val );
	}

	public function testPdoReturnsConnection(): void {
		$db = new Database( $this->dbPath );
		$this->assertInstanceOf( \PDO::class, $db->pdo() );
	}
}
