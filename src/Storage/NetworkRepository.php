<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Storage;

use Apermo\SiteBookkeeperHub\Model\Network;
use Apermo\SiteBookkeeperHub\Model\NetworkReport;

/**
 * Repository for persisting and querying network data.
 */
class NetworkRepository {

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Constructor.
	 *
	 * @param Database $database Database connection.
	 */
	public function __construct( Database $database ) {
		$this->database = $database;
	}

	/**
	 * Insert a new network record.
	 *
	 * @param string      $id          UUID.
	 * @param string      $mainSiteUrl Main site URL.
	 * @param string      $tokenHash   Argon2id token hash.
	 * @param string|null $label       Optional label.
	 *
	 * @return Network
	 */
	public function addNetwork( string $id, string $mainSiteUrl, string $tokenHash, ?string $label = null ): Network {
		$timestamp = \gmdate( 'Y-m-d\TH:i:s\Z' );
		$stmt = $this->database->pdo()->prepare(
			'INSERT INTO networks (id, main_site_url, token_hash, label, created_at, updated_at)
			 VALUES (:id, :main_site_url, :token_hash, :label, :created_at, :updated_at)',
		);
		$stmt->execute(
			[
				':id' => $id,
				':main_site_url' => $mainSiteUrl,
				':token_hash' => $tokenHash,
				':label' => $label,
				':created_at' => $timestamp,
				':updated_at' => $timestamp,
			],
		);

		return new Network( $id, $mainSiteUrl, $tokenHash, $label, $timestamp, $timestamp );
	}

	/**
	 * Find a network by its ID.
	 *
	 * @param string $id Network UUID.
	 *
	 * @return Network|null
	 */
	public function findNetworkById( string $id ): ?Network {
		$stmt = $this->database->pdo()->prepare( 'SELECT * FROM networks WHERE id = :id' );
		$stmt->execute( [ ':id' => $id ] );
		$row = $stmt->fetch();

		return $row ? Network::fromRow( $row ) : null;
	}

	/**
	 * Find a network by its main site URL.
	 *
	 * @param string $url Main site URL.
	 *
	 * @return Network|null
	 */
	public function findNetworkByMainSiteUrl( string $url ): ?Network {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM networks WHERE main_site_url = :url',
		);
		$stmt->execute( [ ':url' => $url ] );
		$row = $stmt->fetch();

		return $row ? Network::fromRow( $row ) : null;
	}

	/**
	 * Get all networks.
	 *
	 * @return array<int, Network>
	 */
	public function getAllNetworks(): array {
		$stmt = $this->database->pdo()->query(
			'SELECT * FROM networks ORDER BY main_site_url',
		);
		$rows = $stmt->fetchAll();

		return \array_map( [ Network::class, 'fromRow' ], $rows );
	}

	/**
	 * Update the token hash for a network.
	 *
	 * @param string $networkId Network UUID.
	 * @param string $tokenHash New argon2id hash.
	 *
	 * @return void
	 */
	public function updateNetworkTokenHash( string $networkId, string $tokenHash ): void {
		$stmt = $this->database->pdo()->prepare(
			'UPDATE networks SET token_hash = :hash, updated_at = :now WHERE id = :id',
		);
		$stmt->execute(
			[
				':hash' => $tokenHash,
				':now' => \gmdate( 'Y-m-d\TH:i:s\Z' ),
				':id' => $networkId,
			],
		);
	}

	/**
	 * Upsert a network report and its related data.
	 *
	 * @param string        $networkId Network UUID.
	 * @param NetworkReport $report    Incoming network report DTO.
	 *
	 * @return void
	 */
	public function upsertNetworkReport( string $networkId, NetworkReport $report ): void {
		$timestamp = \gmdate( 'Y-m-d\TH:i:s\Z' );

		$this->upsertNetworkReportRow( $networkId, $report, $timestamp );
		$this->touchNetworkTimestamp( $networkId, $timestamp );
		$this->upsertNetworkPluginRows( $networkId, $report->networkPlugins, $timestamp );
		$this->upsertNetworkUserRows( $networkId, $report->superAdmins );
	}

	/**
	 * Get the network report for a network.
	 *
	 * @param string $networkId Network UUID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function getNetworkReport( string $networkId ): ?array {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM network_reports WHERE network_id = :network_id',
		);
		$stmt->execute( [ ':network_id' => $networkId ] );

		$row = $stmt->fetch();
		return $row ?: null;
	}

	/**
	 * Get network plugins for a network.
	 *
	 * @param string $networkId Network UUID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getNetworkPlugins( string $networkId ): array {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM network_plugins WHERE network_id = :network_id ORDER BY slug',
		);
		$stmt->execute( [ ':network_id' => $networkId ] );

		return $stmt->fetchAll();
	}

	/**
	 * Get super admin users for a network.
	 *
	 * @param string $networkId Network UUID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getNetworkUsers( string $networkId ): array {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM network_users WHERE network_id = :network_id ORDER BY user_login',
		);
		$stmt->execute( [ ':network_id' => $networkId ] );

		return $stmt->fetchAll();
	}

	/**
	 * Upsert the main network report row.
	 *
	 * @param string        $networkId Network UUID.
	 * @param NetworkReport $report    Report DTO.
	 * @param string        $timestamp Current ISO 8601 timestamp.
	 *
	 * @return void
	 */
	private function upsertNetworkReportRow( string $networkId, NetworkReport $report, string $timestamp ): void {
		$stmt = $this->database->pdo()->prepare(
			'INSERT INTO network_reports (network_id, received_at, schema_version, payload, subsite_count, last_updated)
			 VALUES (:network_id, :received_at, :schema_version, :payload, :subsite_count, :last_updated)
			 ON CONFLICT(network_id) DO UPDATE SET
				received_at = :received_at,
				schema_version = :schema_version,
				payload = :payload,
				subsite_count = :subsite_count,
				last_updated = :last_updated',
		);

		$payload = \json_encode(
			[
				'schema_version' => $report->schemaVersion,
				'timestamp' => $report->timestamp,
				'main_site_url' => $report->mainSiteUrl,
				'subsites' => $report->subsites,
				'network_plugins' => $report->networkPlugins,
				'super_admins' => $report->superAdmins,
				'network_settings' => $report->networkSettings,
			],
		);

		$stmt->execute(
			[
				':network_id' => $networkId,
				':received_at' => $timestamp,
				':schema_version' => $report->schemaVersion,
				':payload' => $payload,
				':subsite_count' => \count( $report->subsites ),
				':last_updated' => $timestamp,
			],
		);
	}

	/**
	 * Update the network's updated_at timestamp.
	 *
	 * @param string $networkId Network UUID.
	 * @param string $timestamp ISO 8601 timestamp.
	 *
	 * @return void
	 */
	private function touchNetworkTimestamp( string $networkId, string $timestamp ): void {
		$stmt = $this->database->pdo()->prepare(
			'UPDATE networks SET updated_at = :timestamp WHERE id = :id',
		);
		$stmt->execute(
			[
				':timestamp' => $timestamp,
				':id' => $networkId,
			],
		);
	}

	/**
	 * Replace network plugin records.
	 *
	 * @param string                           $networkId Network UUID.
	 * @param array<int, array<string, mixed>> $plugins   Plugin data from report.
	 * @param string                           $timestamp Current timestamp.
	 *
	 * @return void
	 */
	private function upsertNetworkPluginRows( string $networkId, array $plugins, string $timestamp ): void {
		$conn = $this->database->pdo();

		$stmt = $conn->prepare( 'DELETE FROM network_plugins WHERE network_id = :network_id' );
		$stmt->execute( [ ':network_id' => $networkId ] );

		$stmt = $conn->prepare(
			'INSERT INTO network_plugins (network_id, slug, name, version, update_available, last_updated)
			 VALUES (:network_id, :slug, :name, :version, :update_available, :last_updated)',
		);

		foreach ( $plugins as $plugin ) {
			$stmt->execute(
				[
					':network_id' => $networkId,
					':slug' => $plugin['slug'] ?? '',
					':name' => $plugin['name'] ?? '',
					':version' => $plugin['version'] ?? '',
					':update_available' => $plugin['update_available'] ?? null,
					':last_updated' => $timestamp,
				],
			);
		}
	}

	/**
	 * Replace network user (super admin) records.
	 *
	 * @param string                           $networkId   Network UUID.
	 * @param array<int, array<string, mixed>> $superAdmins Super admin data.
	 *
	 * @return void
	 */
	private function upsertNetworkUserRows( string $networkId, array $superAdmins ): void {
		$conn = $this->database->pdo();

		$stmt = $conn->prepare( 'DELETE FROM network_users WHERE network_id = :network_id' );
		$stmt->execute( [ ':network_id' => $networkId ] );

		$stmt = $conn->prepare(
			'INSERT INTO network_users (network_id, user_login, display_name, email)
			 VALUES (:network_id, :user_login, :display_name, :email)',
		);

		foreach ( $superAdmins as $admin ) {
			$stmt->execute(
				[
					':network_id' => $networkId,
					':user_login' => $admin['user_login'] ?? '',
					':display_name' => $admin['display_name'] ?? '',
					':email' => $admin['email'] ?? '',
				],
			);
		}
	}
}
