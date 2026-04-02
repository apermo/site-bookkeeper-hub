<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;

/**
 * Handles PATCH /sites/{id} — update category and/or notes.
 */
class SitePatchHandler {

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
	 * Handle the PATCH /sites/{id} request.
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

		$data = \json_decode( (string) \file_get_contents( 'php://input' ), true );
		if ( ! \is_array( $data ) ) {
			JsonResponse::error( 'bad_request', 'Invalid JSON payload.', 400 );
			return;
		}

		// Conflict detection for notes.
		if ( \array_key_exists( 'notes', $data ) && isset( $data['notes_hash'] ) ) {
			$current_hash = $site->notesHash();
			$sent_hash = (string) $data['notes_hash'];

			if ( $current_hash !== $sent_hash ) {
				JsonResponse::send(
					[
						'error'      => 'conflict',
						'message'    => 'Notes were modified by another client.',
						'notes'      => $site->notes,
						'notes_hash' => $current_hash,
					],
					409,
				);
				return;
			}
		}

		$category_id = \array_key_exists( 'category_id', $data )
			? ( $data['category_id'] !== '' ? (string) $data['category_id'] : null )
			: $site->categoryId;

		$notes = \array_key_exists( 'notes', $data )
			? (string) $data['notes']
			: $site->notes;

		$this->repo->updateSiteMeta( $site->id, $category_id, $notes );

		// Re-fetch to return updated state.
		$updated = $this->repo->findSiteById( $site->id );

		JsonResponse::send(
			[
				'id'          => $updated->id,
				'category_id' => $updated->categoryId,
				'notes'       => $updated->notes,
				'notes_hash'  => $updated->notesHash(),
			],
		);
	}
}
