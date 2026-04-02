<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Model;

/**
 * DTO representing an incoming network health report.
 */
class NetworkReport {

	/**
	 * Build a NetworkReport from the decoded JSON payload.
	 *
	 * @param int                              $schemaVersion   Schema version.
	 * @param string                           $timestamp       ISO 8601 timestamp.
	 * @param string                           $mainSiteUrl     Main site URL of the network.
	 * @param array<int, array<string, mixed>> $subsites        List of subsites.
	 * @param array<int, array<string, mixed>> $networkPlugins  Network-activated plugins.
	 * @param array<int, array<string, mixed>> $superAdmins     Super admin users.
	 * @param array<int, array<string, mixed>> $networkSettings Network settings.
	 */
	public function __construct(
		public readonly int $schemaVersion,
		public readonly string $timestamp,
		public readonly string $mainSiteUrl,
		public readonly array $subsites = [],
		public readonly array $networkPlugins = [],
		public readonly array $superAdmins = [],
		public readonly array $networkSettings = [],
	) {
	}

	/**
	 * Create a NetworkReport from a decoded JSON array.
	 *
	 * @param array<string, mixed> $data Decoded JSON payload.
	 *
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		return new self(
			schemaVersion: (int) ( $data['schema_version'] ?? 1 ),
			timestamp: (string) ( $data['timestamp'] ?? '' ),
			mainSiteUrl: (string) ( $data['main_site_url'] ?? '' ),
			subsites: (array) ( $data['subsites'] ?? [] ),
			networkPlugins: (array) ( $data['network_plugins'] ?? [] ),
			superAdmins: (array) ( $data['super_admins'] ?? [] ),
			networkSettings: (array) ( $data['network_settings'] ?? [] ),
		);
	}
}
