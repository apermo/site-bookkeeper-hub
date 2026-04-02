<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;

/**
 * Handles GET /themes — cross-site theme report.
 */
class ThemesHandler {

	/**
	 * Site repository.
	 *
	 * @var SiteRepository
	 */
	private SiteRepository $repo;

	/**
	 * Client authenticator.
	 *
	 * @var ClientAuth
	 */
	private ClientAuth $auth;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository $repo Repository.
	 * @param ClientAuth     $auth Client authenticator.
	 */
	public function __construct( SiteRepository $repo, ClientAuth $auth ) {
		$this->repo = $repo;
		$this->auth = $auth;
	}

	/**
	 * Handle the GET /themes request.
	 *
	 * @param array<string, string> $params Route parameters (unused).
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function handle( array $params ): void {
		$token = ClientAuth::extractBearerToken();
		if ( $token === null || ! $this->auth->authenticate( $token ) ) {
			JsonResponse::error( 'unauthorized', 'Invalid or missing client token.', 401 );
			return;
		}

		$slug = $_GET['slug'] ?? null;
		$outdated = isset( $_GET['outdated'] ) && $_GET['outdated'] === 'true';

		$rows = $this->repo->getAllThemes( $slug, $outdated );

		JsonResponse::send( [ 'themes' => self::groupBySlug( $rows ) ] );
	}

	/**
	 * Group flat theme rows by slug with nested sites array.
	 *
	 * @param array<int, array<string, mixed>> $rows Flat rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function groupBySlug( array $rows ): array {
		$grouped = [];

		foreach ( $rows as $row ) {
			$slug = $row['slug'];
			if ( ! isset( $grouped[ $slug ] ) ) {
				$grouped[ $slug ] = [
					'slug' => $slug,
					'name' => $row['name'],
					'sites' => [],
				];
			}

			$grouped[ $slug ]['sites'][] = [
				'site_id' => $row['site_id'],
				'site_url' => $row['site_url'],
				'label' => $row['site_label'] ?? null,
				'version' => $row['version'],
				'update_available' => $row['update_available'],
				'active' => $row['active'],
				'last_updated' => $row['last_updated'],
			];
		}

		return \array_values( $grouped );
	}
}
