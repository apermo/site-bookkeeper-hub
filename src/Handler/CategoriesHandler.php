<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Storage\CategoryRepository;

/**
 * Handles GET/POST /categories and PUT/DELETE /categories/{id}.
 */
class CategoriesHandler {

	/**
	 * Category repository.
	 *
	 * @var CategoryRepository
	 */
	private CategoryRepository $repo;

	/**
	 * Client authenticator.
	 *
	 * @var ClientAuth
	 */
	private ClientAuth $auth;

	/**
	 * Constructor.
	 *
	 * @param CategoryRepository $repo Repository.
	 * @param ClientAuth         $auth Client authenticator.
	 */
	public function __construct( CategoryRepository $repo, ClientAuth $auth ) {
		$this->repo = $repo;
		$this->auth = $auth;
	}

	/**
	 * Generate a v4 UUID.
	 *
	 * @return string
	 */
	private static function generateUuid(): string {
		$data = \random_bytes( 16 );
		$data[6] = \chr( \ord( $data[6] ) & 0x0f | 0x40 );
		$data[8] = \chr( \ord( $data[8] ) & 0x3f | 0x80 );

		return \vsprintf( '%s%s-%s-%s-%s-%s%s%s', \str_split( \bin2hex( $data ), 4 ) );
	}

	/**
	 * Generate a URL-safe slug from a name.
	 *
	 * @param string $name Category name.
	 *
	 * @return string
	 */
	private static function slugify( string $name ): string {
		$slug = \mb_strtolower( $name );
		$slug = (string) \preg_replace( '/[^a-z0-9]+/', '-', $slug );

		return \trim( $slug, '-' );
	}

	/**
	 * Handle GET /categories — list all categories.
	 *
	 * @param array<string, string> $params Route parameters (unused).
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function handleList( array $params ): void {
		$token = ClientAuth::extractBearerToken();
		if ( $token === null || ! $this->auth->authenticate( $token ) ) {
			JsonResponse::error( 'unauthorized', 'Invalid or missing client token.', 401 );
			return;
		}

		JsonResponse::send( [ 'categories' => $this->repo->getAll() ] );
	}

	/**
	 * Handle POST /categories — create a new category.
	 *
	 * @param array<string, string> $params Route parameters (unused).
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function handleCreate( array $params ): void {
		$token = ClientAuth::extractBearerToken();
		if ( $token === null || ! $this->auth->authenticate( $token ) ) {
			JsonResponse::error( 'unauthorized', 'Invalid or missing client token.', 401 );
			return;
		}

		$data = \json_decode( (string) \file_get_contents( 'php://input' ), true );
		if ( ! \is_array( $data ) || ! isset( $data['name'] ) ) {
			JsonResponse::error( 'bad_request', 'Missing name.', 400 );
			return;
		}

		$name = (string) $data['name'];
		$slug = (string) ( $data['slug'] ?? self::slugify( $name ) );
		$overdue_hours = (int) ( $data['overdue_hours'] ?? 48 );
		$sort_order = (int) ( $data['sort_order'] ?? 0 );
		$uuid = self::generateUuid();

		$this->repo->create( $uuid, $name, $slug, $overdue_hours, $sort_order );

		JsonResponse::send( $this->repo->findById( $uuid ), 201 );
	}

	/**
	 * Handle PUT /categories/{id} — update a category.
	 *
	 * @param array<string, string> $params Route parameters with 'id'.
	 *
	 * @return void
	 */
	public function handleUpdate( array $params ): void {
		$token = ClientAuth::extractBearerToken();
		if ( $token === null || ! $this->auth->authenticate( $token ) ) {
			JsonResponse::error( 'unauthorized', 'Invalid or missing client token.', 401 );
			return;
		}

		$category_id = $params['id'] ?? '';
		$existing = $this->repo->findById( $category_id );
		if ( $existing === null ) {
			JsonResponse::error( 'not_found', 'Category not found.', 404 );
			return;
		}

		$data = \json_decode( (string) \file_get_contents( 'php://input' ), true );
		if ( ! \is_array( $data ) ) {
			JsonResponse::error( 'bad_request', 'Invalid JSON payload.', 400 );
			return;
		}

		$name = (string) ( $data['name'] ?? $existing['name'] );
		$slug = (string) ( $data['slug'] ?? $existing['slug'] );
		$overdue_hours = (int) ( $data['overdue_hours'] ?? $existing['overdue_hours'] );
		$sort_order = (int) ( $data['sort_order'] ?? $existing['sort_order'] );

		$this->repo->update( $category_id, $name, $slug, $overdue_hours, $sort_order );

		JsonResponse::send( $this->repo->findById( $category_id ) );
	}

	/**
	 * Handle DELETE /categories/{id} — delete a category.
	 *
	 * @param array<string, string> $params Route parameters with 'id'.
	 *
	 * @return void
	 */
	public function handleDelete( array $params ): void {
		$token = ClientAuth::extractBearerToken();
		if ( $token === null || ! $this->auth->authenticate( $token ) ) {
			JsonResponse::error( 'unauthorized', 'Invalid or missing client token.', 401 );
			return;
		}

		$category_id = $params['id'] ?? '';
		if ( $this->repo->findById( $category_id ) === null ) {
			JsonResponse::error( 'not_found', 'Category not found.', 404 );
			return;
		}

		$this->repo->delete( $category_id );

		\http_response_code( 204 );
	}
}
