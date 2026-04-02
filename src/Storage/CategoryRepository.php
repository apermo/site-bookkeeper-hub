<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Storage;

/**
 * Repository for site categories.
 */
class CategoryRepository {

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
	 * Get all categories ordered by sort_order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAll(): array {
		$stmt = $this->database->pdo()->query(
			'SELECT * FROM site_categories ORDER BY sort_order, name',
		);

		return $stmt->fetchAll();
	}

	/**
	 * Find a category by ID.
	 *
	 * @param string $category_id Category UUID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findById( string $category_id ): ?array {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM site_categories WHERE id = :id',
		);
		$stmt->execute( [ ':id' => $category_id ] );
		$row = $stmt->fetch();

		return $row !== false ? $row : null;
	}

	/**
	 * Create a new category.
	 *
	 * @param string $category_id  UUID.
	 * @param string $name         Display name.
	 * @param string $slug         URL-safe slug.
	 * @param int    $overdue_hours Hours before updates are overdue.
	 * @param int    $sort_order   Sort position.
	 *
	 * @return void
	 */
	public function create(
		string $category_id,
		string $name,
		string $slug,
		int $overdue_hours,
		int $sort_order = 0,
	): void {
		$stmt = $this->database->pdo()->prepare(
			'INSERT INTO site_categories (id, name, slug, sort_order, overdue_hours, created_at)
			VALUES (:id, :name, :slug, :sort_order, :overdue_hours, :created_at)',
		);
		$stmt->execute(
			[
				':id'            => $category_id,
				':name'          => $name,
				':slug'          => $slug,
				':sort_order'    => $sort_order,
				':overdue_hours' => $overdue_hours,
				':created_at'    => \gmdate( 'c' ),
			],
		);
	}

	/**
	 * Update an existing category.
	 *
	 * @param string $category_id  UUID.
	 * @param string $name         Display name.
	 * @param string $slug         URL-safe slug.
	 * @param int    $overdue_hours Hours before updates are overdue.
	 * @param int    $sort_order   Sort position.
	 *
	 * @return void
	 */
	public function update(
		string $category_id,
		string $name,
		string $slug,
		int $overdue_hours,
		int $sort_order = 0,
	): void {
		$stmt = $this->database->pdo()->prepare(
			'UPDATE site_categories
			SET name = :name, slug = :slug, sort_order = :sort_order, overdue_hours = :overdue_hours
			WHERE id = :id',
		);
		$stmt->execute(
			[
				':id'            => $category_id,
				':name'          => $name,
				':slug'          => $slug,
				':sort_order'    => $sort_order,
				':overdue_hours' => $overdue_hours,
			],
		);
	}

	/**
	 * Delete a category and nullify sites referencing it.
	 *
	 * @param string $category_id UUID.
	 *
	 * @return void
	 */
	public function delete( string $category_id ): void {
		$stmt = $this->database->pdo()->prepare(
			'UPDATE sites SET category_id = NULL WHERE category_id = :id',
		);
		$stmt->execute( [ ':id' => $category_id ] );

		$stmt = $this->database->pdo()->prepare(
			'DELETE FROM site_categories WHERE id = :id',
		);
		$stmt->execute( [ ':id' => $category_id ] );
	}
}
