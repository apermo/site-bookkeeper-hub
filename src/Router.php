<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub;

/**
 * Minimal request router.
 *
 * Maps HTTP method + path to handler callbacks.
 */
class Router {

	/**
	 * Registered route definitions.
	 *
	 * @var array<string, array<string, callable>>
	 */
	private array $routes = [];

	/**
	 * Register a GET route.
	 *
	 * @param string   $pattern  Route pattern (e.g. '/sites' or '/sites/{id}').
	 * @param callable $handler  Handler callback.
	 *
	 * @return void
	 */
	public function get( string $pattern, callable $handler ): void {
		$this->routes['GET'][ $pattern ] = $handler;
	}

	/**
	 * Register a POST route.
	 *
	 * @param string   $pattern  Route pattern.
	 * @param callable $handler  Handler callback.
	 *
	 * @return void
	 */
	public function post( string $pattern, callable $handler ): void {
		$this->routes['POST'][ $pattern ] = $handler;
	}

	/**
	 * Register a PATCH route.
	 *
	 * @param string   $pattern  Route pattern.
	 * @param callable $handler  Handler callback.
	 *
	 * @return void
	 */
	public function patch( string $pattern, callable $handler ): void {
		$this->routes['PATCH'][ $pattern ] = $handler;
	}

	/**
	 * Register a PUT route.
	 *
	 * @param string   $pattern  Route pattern.
	 * @param callable $handler  Handler callback.
	 *
	 * @return void
	 */
	public function put( string $pattern, callable $handler ): void {
		$this->routes['PUT'][ $pattern ] = $handler;
	}

	/**
	 * Register a DELETE route.
	 *
	 * @param string   $pattern  Route pattern.
	 * @param callable $handler  Handler callback.
	 *
	 * @return void
	 */
	public function delete( string $pattern, callable $handler ): void {
		$this->routes['DELETE'][ $pattern ] = $handler;
	}

	/**
	 * Dispatch the current request to a matching handler.
	 *
	 * @param string $method HTTP method (GET, POST, etc.).
	 * @param string $path   Request path (e.g. '/sites/abc-123').
	 *
	 * @return array{callable, array<string, string>}|null Handler and matched params, or null.
	 */
	public function match( string $method, string $path ): ?array {
		$method = \strtoupper( $method );

		if ( ! isset( $this->routes[ $method ] ) ) {
			return null;
		}

		foreach ( $this->routes[ $method ] as $pattern => $handler ) {
			$params = $this->matchPattern( $pattern, $path );
			if ( $params !== null ) {
				return [ $handler, $params ];
			}
		}

		return null;
	}

	/**
	 * Check whether a request path matches any registered route (any method).
	 *
	 * @param string $path Request path.
	 *
	 * @return bool
	 */
	public function pathExists( string $path ): bool {
		foreach ( $this->routes as $methods ) {
			foreach ( $methods as $pattern => $handler ) {
				if ( $this->matchPattern( $pattern, $path ) !== null ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Match a route pattern against a path, extracting named parameters.
	 *
	 * @param string $pattern Route pattern with optional {name} placeholders.
	 * @param string $path    Actual request path.
	 *
	 * @return array<string, string>|null Extracted params or null on mismatch.
	 */
	private function matchPattern( string $pattern, string $path ): ?array {
		// Convert {name} placeholders to named regex groups.
		$regex = \preg_replace( '#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern );
		$regex = '#^' . $regex . '$#';

		if ( \preg_match( $regex, $path, $matches ) ) {
			return \array_filter( $matches, '\is_string', \ARRAY_FILTER_USE_KEY );
		}

		return null;
	}
}
