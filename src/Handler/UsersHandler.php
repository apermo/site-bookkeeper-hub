<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;

/**
 * Handles GET /users — cross-site user search.
 */
class UsersHandler {

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
	 * Handle the GET /users request.
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

		$search = $_GET['search'] ?? '';
		if ( $search === '' ) {
			JsonResponse::error( 'bad_request', 'Missing search parameter.', 400 );
			return;
		}

		$rows = $this->repo->searchUsers( $search );

		JsonResponse::send( [ 'users' => $this->groupByUser( $rows ) ] );
	}

	/**
	 * Group flat rows by user identity (login + email).
	 *
	 * @param array<int, array<string, mixed>> $rows Flat rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function groupByUser( array $rows ): array {
		$grouped = [];

		foreach ( $rows as $row ) {
			$identity = $row['user_login'] . '|' . $row['email'];
			if ( ! isset( $grouped[ $identity ] ) ) {
				$grouped[ $identity ] = [
					'user_login' => $row['user_login'],
					'display_name' => $row['display_name'],
					'email' => $row['email'],
					'sites' => [],
				];
			}

			$grouped[ $identity ]['sites'][] = [
				'site_id' => $row['site_id'],
				'site_url' => $row['site_url'],
				'label' => $row['site_label'] ?? null,
				'role' => $row['role'],
			];
		}

		return \array_values( $grouped );
	}
}
