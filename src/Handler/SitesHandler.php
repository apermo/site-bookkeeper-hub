<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Model\Site;
use Apermo\SiteBookkeeperHub\Storage\CategoryRepository;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;

/**
 * Handles GET /sites — returns a summary list of all monitored sites.
 */
class SitesHandler {

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
	 * Hours before a site is considered stale.
	 *
	 * @var int
	 */
	private int $staleHours;

	/**
	 * Category repository.
	 *
	 * @var CategoryRepository
	 */
	private ?CategoryRepository $categoryRepo;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository     $repo         Repository.
	 * @param ClientAuth         $auth         Client authenticator.
	 * @param int                $staleHours   Stale threshold in hours.
	 * @param CategoryRepository $category_repo Category repository.
	 */
	public function __construct(
		SiteRepository $repo,
		ClientAuth $auth,
		int $staleHours = 48,
		?CategoryRepository $category_repo = null,
	) {
		$this->repo = $repo;
		$this->auth = $auth;
		$this->staleHours = $staleHours;
		$this->categoryRepo = $category_repo;
	}

	/**
	 * Handle the GET /sites request.
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

		$sites = $this->repo->getAllSites();
		$staleThreshold = \time() - ( $this->staleHours * 3600 );
		$categories = $this->loadCategories();
		$result = [];

		foreach ( $sites as $site ) {
			$result[] = $this->buildSiteSummary( $site, $staleThreshold, $categories );
		}

		JsonResponse::send( [ 'sites' => $result ] );
	}

	/**
	 * Load all categories indexed by ID.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function loadCategories(): array {
		if ( $this->categoryRepo === null ) {
			return [];
		}

		$result = [];
		foreach ( $this->categoryRepo->getAll() as $category ) {
			$result[ $category['id'] ] = $category;
		}

		return $result;
	}

	/**
	 * Build a summary array for a single site.
	 *
	 * @param Site                                $site           Site entity.
	 * @param int                                 $staleThreshold Unix timestamp threshold.
	 * @param array<string, array<string, mixed>> $categories     Categories indexed by ID.
	 *
	 * @return array<string, mixed>
	 */
	private function buildSiteSummary( Site $site, int $staleThreshold, array $categories = [] ): array {
		$report = $this->repo->getReport( $site->id );
		$plugins = $this->repo->getPlugins( $site->id );
		$themes = $this->repo->getThemes( $site->id );

		$pendingPlugins = 0;
		foreach ( $plugins as $plugin ) {
			if ( $plugin['update_available'] !== null ) {
				$pendingPlugins++;
			}
		}

		$pendingThemes = 0;
		foreach ( $themes as $theme ) {
			if ( $theme['update_available'] !== null ) {
				$pendingThemes++;
			}
		}

		$lastSeen = $report['last_updated'] ?? $site->updatedAt;
		$isStale = \strtotime( $lastSeen ) < $staleThreshold;

		$category = null;
		$overdue = false;
		if ( $site->categoryId !== null && isset( $categories[ $site->categoryId ] ) ) {
			$category = [
				'id'           => $categories[ $site->categoryId ]['id'],
				'name'         => $categories[ $site->categoryId ]['name'],
				'slug'         => $categories[ $site->categoryId ]['slug'],
				'overdue_hours' => (int) $categories[ $site->categoryId ]['overdue_hours'],
			];
			$overdue = $this->hasOverdueUpdates( $plugins, $category['overdue_hours'] )
				|| $this->hasOverdueUpdates( $themes, $category['overdue_hours'] );
		}

		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeysError -- API contract.
		return [
			'id' => $site->id,
			'site_url' => $site->siteUrl,
			'label' => $site->label,
			'network_id' => $site->networkId,
			'category' => $category,
			'environment_type' => $report['environment_type'] ?? null,
			'wp_version' => $report['wp_version'] ?? null,
			'wp_update_available' => $report['wp_update_available'] ?? null,
			'php_version' => $report['php_version'] ?? null,
			'pending_plugin_updates' => $pendingPlugins,
			'pending_theme_updates' => $pendingThemes,
			'last_updated' => $report['last_updated'] ?? null,
			'last_seen' => $lastSeen,
			'stale' => $isStale,
			'overdue' => $overdue,
			'notes_hash' => $site->notesHash(),
		];
	}

	/**
	 * Check if any item has an overdue update based on update_available_since.
	 *
	 * @param array<int, array<string, mixed>> $items         Plugin or theme rows.
	 * @param int                              $overdue_hours Category threshold.
	 *
	 * @return bool
	 */
	private function hasOverdueUpdates( array $items, int $overdue_hours ): bool {
		$threshold = \time() - ( $overdue_hours * 3600 );

		foreach ( $items as $item ) {
			$update = $item['update_available'] ?? '';
			$since = $item['update_available_since'] ?? null;

			if ( $update === '' || $update === null || $since === null ) {
				continue;
			}

			$since_time = \strtotime( $since );
			if ( $since_time !== false && $since_time < $threshold ) {
				return true;
			}
		}

		return false;
	}
}
