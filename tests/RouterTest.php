<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Tests;

use Apermo\SiteMonitorHub\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase {

	public function testMatchesStaticGetRoute(): void {
		$router = new Router();
		$router->get( '/sites', fn () => 'list' );

		$result = $router->match( 'GET', '/sites' );

		$this->assertNotNull( $result );
		$this->assertSame( 'list', ( $result[0] )() );
		$this->assertSame( [], $result[1] );
	}

	public function testMatchesPostRoute(): void {
		$router = new Router();
		$router->post( '/report', fn () => 'report' );

		$result = $router->match( 'POST', '/report' );

		$this->assertNotNull( $result );
		$this->assertSame( 'report', ( $result[0] )() );
	}

	public function testMatchesParameterizedRoute(): void {
		$router = new Router();
		$router->get( '/sites/{id}', fn () => 'detail' );

		$result = $router->match( 'GET', '/sites/abc-123' );

		$this->assertNotNull( $result );
		$this->assertSame( [ 'id' => 'abc-123' ], $result[1] );
	}

	public function testReturnsNullForNoMatch(): void {
		$router = new Router();
		$router->get( '/sites', fn () => 'list' );

		$this->assertNull( $router->match( 'GET', '/unknown' ) );
	}

	public function testReturnsNullForWrongMethod(): void {
		$router = new Router();
		$router->get( '/sites', fn () => 'list' );

		$this->assertNull( $router->match( 'POST', '/sites' ) );
	}

	public function testPathExistsReturnsTrueForRegisteredPath(): void {
		$router = new Router();
		$router->get( '/sites', fn () => 'list' );

		$this->assertTrue( $router->pathExists( '/sites' ) );
	}

	public function testPathExistsReturnsFalseForUnknownPath(): void {
		$router = new Router();
		$router->get( '/sites', fn () => 'list' );

		$this->assertFalse( $router->pathExists( '/nope' ) );
	}

	public function testMethodIsCaseInsensitive(): void {
		$router = new Router();
		$router->get( '/sites', fn () => 'list' );

		$result = $router->match( 'get', '/sites' );

		$this->assertNotNull( $result );
	}
}
