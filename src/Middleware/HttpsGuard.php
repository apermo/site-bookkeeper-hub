<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Middleware;

use Apermo\SiteBookkeeperHub\JsonResponse;

/**
 * Rejects plain HTTP requests unless explicitly allowed.
 *
 * Checks $_SERVER superglobals for HTTPS indicators and honours
 * the ALLOW_HTTP environment variable as an escape hatch for
 * local development.
 */
class HttpsGuard {

	/**
	 * Enforce HTTPS on the current request.
	 *
	 * Returns true when the request may proceed, false when it was
	 * rejected (a 403 response has already been sent).
	 *
	 * @param array<string, string> $server Server variables ($_SERVER).
	 *
	 * @return bool
	 */
	public static function check( array $server = [] ): bool {
		if ( self::isHttpAllowed() ) {
			return true;
		}

		if ( self::isSecure( $server ) ) {
			return true;
		}

		JsonResponse::error(
			'https_required',
			'HTTPS is required. Plain HTTP requests are not accepted.',
			403,
		);

		return false;
	}

	/**
	 * Whether the ALLOW_HTTP env var is set to a truthy value.
	 *
	 * @return bool
	 */
	public static function isHttpAllowed(): bool {
		$value = \getenv( 'ALLOW_HTTP' );

		return $value !== false && \strtolower( $value ) === 'true';
	}

	/**
	 * Determine whether the request was made over HTTPS.
	 *
	 * Supports the standard HTTPS flag, the X-Forwarded-Proto
	 * header (reverse proxy), and the REQUEST_SCHEME variable.
	 *
	 * @param array<string, string> $server Server variables.
	 *
	 * @return bool
	 */
	public static function isSecure( array $server ): bool {
		if ( isset( $server['HTTPS'] ) && \strtolower( $server['HTTPS'] ) === 'on' ) {
			return true;
		}

		if ( isset( $server['HTTP_X_FORWARDED_PROTO'] ) && \strtolower( $server['HTTP_X_FORWARDED_PROTO'] ) === 'https' ) {
			return true;
		}

		if ( isset( $server['REQUEST_SCHEME'] ) && \strtolower( $server['REQUEST_SCHEME'] ) === 'https' ) {
			return true;
		}

		return false;
	}
}
