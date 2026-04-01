<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Storage;

use PDO;
use RuntimeException;
use Throwable;

/**
 * SQLite connection manager with schema migration.
 */
class Database {

	/**
	 * PDO connection instance.
	 *
	 * @var PDO
	 */
	private PDO $connection;

	/**
	 * Open or create the SQLite database at the given path.
	 *
	 * @param string $path Filesystem path to the SQLite file.
	 *
	 * @throws RuntimeException When the parent directory cannot be created.
	 */
	public function __construct( string $path ) {
		$directory = \dirname( $path );
		if ( ! \is_dir( $directory ) && ! \mkdir( $directory, 0755, true ) ) {
			throw new RuntimeException( "Cannot create directory: {$directory}" );
		}

		$this->connection = new PDO( "sqlite:{$path}" );
		$this->connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->connection->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
		$this->connection->exec( 'PRAGMA journal_mode=WAL' );
		$this->connection->exec( 'PRAGMA foreign_keys=ON' );
	}

	/**
	 * Return the underlying PDO connection.
	 *
	 * @return PDO
	 */
	public function pdo(): PDO {
		return $this->connection;
	}

	/**
	 * Run all schema migrations.
	 *
	 * @throws Throwable When a migration step fails.
	 */
	public function migrate(): void {
		$this->connection->exec( 'PRAGMA foreign_keys=OFF' );
		$this->connection->beginTransaction();

		try {
			$this->createSitesTable();
			$this->createReportsTable();
			$this->createSitePluginsTable();
			$this->createSiteThemesTable();
			$this->createSiteCustomFieldsTable();
			$this->createClientTokensTable();
			$this->createSiteUsersTable();
			$this->createSiteUserMetaTable();
			$this->createSiteRolesTable();

			$this->connection->commit();
		} catch ( Throwable $exception ) {
			$this->connection->rollBack();
			throw $exception;
		}

		$this->connection->exec( 'PRAGMA foreign_keys=ON' );
	}

	/**
	 * Create the sites table.
	 *
	 * @return void
	 */
	private function createSitesTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS sites (
				id TEXT PRIMARY KEY,
				site_url TEXT NOT NULL UNIQUE,
				token_hash TEXT NOT NULL,
				label TEXT,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)',
		);
	}

	/**
	 * Create the reports table.
	 *
	 * @return void
	 */
	private function createReportsTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS reports (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				site_id TEXT NOT NULL REFERENCES sites(id),
				received_at TEXT NOT NULL,
				schema_version INTEGER NOT NULL,
				payload JSON NOT NULL,
				wp_version TEXT,
				php_version TEXT,
				wp_update_available TEXT,
				wp_version_last_updated TEXT,
				last_updated TEXT,
				UNIQUE(site_id)
			)',
		);
	}

	/**
	 * Create the site_plugins table.
	 *
	 * @return void
	 */
	private function createSitePluginsTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_plugins (
				site_id TEXT NOT NULL REFERENCES sites(id),
				slug TEXT NOT NULL,
				name TEXT NOT NULL,
				version TEXT NOT NULL,
				update_available TEXT,
				active INTEGER NOT NULL DEFAULT 1,
				last_updated TEXT NOT NULL,
				PRIMARY KEY (site_id, slug)
			)',
		);
	}

	/**
	 * Create the site_themes table.
	 *
	 * @return void
	 */
	private function createSiteThemesTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_themes (
				site_id TEXT NOT NULL REFERENCES sites(id),
				slug TEXT NOT NULL,
				name TEXT NOT NULL,
				version TEXT NOT NULL,
				update_available TEXT,
				active INTEGER NOT NULL DEFAULT 0,
				last_updated TEXT NOT NULL,
				PRIMARY KEY (site_id, slug)
			)',
		);
	}

	/**
	 * Create the site_custom_fields table.
	 *
	 * @return void
	 */
	private function createSiteCustomFieldsTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_custom_fields (
				site_id TEXT NOT NULL REFERENCES sites(id),
				key TEXT NOT NULL,
				label TEXT NOT NULL,
				value TEXT NOT NULL,
				status TEXT,
				PRIMARY KEY (site_id, key)
			)',
		);
	}

	/**
	 * Create the client_tokens table.
	 *
	 * @return void
	 */
	private function createClientTokensTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS client_tokens (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				token_hash TEXT NOT NULL,
				label TEXT,
				created_at TEXT NOT NULL
			)',
		);
	}

	/**
	 * Create the site_users table.
	 *
	 * @return void
	 */
	private function createSiteUsersTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_users (
				site_id TEXT NOT NULL REFERENCES sites(id),
				user_login TEXT NOT NULL,
				display_name TEXT NOT NULL,
				email TEXT NOT NULL,
				role TEXT NOT NULL,
				PRIMARY KEY (site_id, user_login)
			)',
		);
	}

	/**
	 * Create the site_user_meta table.
	 *
	 * @return void
	 */
	private function createSiteUserMetaTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_user_meta (
				site_id TEXT NOT NULL REFERENCES sites(id),
				user_login TEXT NOT NULL,
				meta_key TEXT NOT NULL,
				meta_value TEXT NOT NULL,
				PRIMARY KEY (site_id, user_login, meta_key)
			)',
		);
	}

	/**
	 * Create the site_roles table.
	 *
	 * @return void
	 */
	private function createSiteRolesTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_roles (
				site_id TEXT NOT NULL REFERENCES sites(id),
				slug TEXT NOT NULL,
				name TEXT NOT NULL,
				is_custom INTEGER NOT NULL DEFAULT 0,
				is_modified INTEGER NOT NULL DEFAULT 0,
				capabilities TEXT NOT NULL,
				PRIMARY KEY (site_id, slug)
			)',
		);
	}
}
