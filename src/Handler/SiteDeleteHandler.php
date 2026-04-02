<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;

/**
 * Handles DELETE /sites/{id} — removes a site and all its data.
 */
class SiteDeleteHandler {

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
	 * Handle the DELETE /sites/{id} request.
	 *
	 * @param array<string, string> $params Route parameters with 'id'.
	 *
	 * @return void
	 */
	public function handle( array $params ): void {
		$token = ClientAuth::extractBearerToken();
		if ( $token === null || ! $this->auth->authenticate( $token ) ) {
			JsonResponse::error( 'unauthorized', 'Invalid or missing client token.', 401 );
			return;
		}

		$site_id = $params['id'] ?? '';
		$site = $this->repo->findSiteById( $site_id );

		if ( $site === null ) {
			JsonResponse::error( 'not_found', 'Site not found.', 404 );
			return;
		}

		$this->repo->deleteSite( $site->id );

		\http_response_code( 204 );
	}
}
