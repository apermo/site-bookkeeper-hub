<?php
/**
 * CLI management tool for Site Monitor Hub.
 *
 * Commands:
 *   site:add <url> [--label=<label>]    Register a new site
 *   site:list                           List all registered sites
 *   site:rotate-token <url>             Rotate a site's bearer token
 *   client:add [--label=<label>]        Create a new client read token
 *
 * @phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Apermo\SiteMonitorHub\Storage\Database;
use Apermo\SiteMonitorHub\Storage\SiteRepository;

// Load .env if it exists.
$env_file = __DIR__ . '/../.env';
if ( file_exists( $env_file ) ) {
	$lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line === '' || str_starts_with( $line, '#' ) ) {
			continue;
		}
		putenv( $line );
	}
}

$database_path = getenv( 'DATABASE_PATH' ) ?: __DIR__ . '/../data/monitor.sqlite';
$database = new Database( $database_path );
$database->migrate();
$repo = new SiteRepository( $database );

$command = $argv[1] ?? '';

switch ( $command ) {
	case 'site:add':
		handle_site_add( $repo, array_slice( $argv, 2 ) );
		break;
	case 'site:list':
		handle_site_list( $repo );
		break;
	case 'site:rotate-token':
		handle_site_rotate_token( $repo, array_slice( $argv, 2 ) );
		break;
	case 'client:add':
		handle_client_add( $repo, array_slice( $argv, 2 ) );
		break;
	default:
		fwrite( STDERR, "Usage: php bin/manage.php <command>\n\n" );
		fwrite( STDERR, "Commands:\n" );
		fwrite( STDERR, "  site:add <url> [--label=<label>]\n" );
		fwrite( STDERR, "  site:list\n" );
		fwrite( STDERR, "  site:rotate-token <url>\n" );
		fwrite( STDERR, "  client:add [--label=<label>]\n" );
		exit( 1 );
}

/**
 * Register a new site.
 *
 * @param SiteRepository     $repo Repository.
 * @param array<int, string> $arguments CLI arguments.
 *
 * @return void
 */
function handle_site_add( SiteRepository $repo, array $arguments ): void {
	$url = '';
	$label = null;

	foreach ( $arguments as $argument ) {
		if ( str_starts_with( $argument, '--label=' ) ) {
			$label = substr( $argument, 8 );
		} elseif ( $url === '' ) {
			$url = $argument;
		}
	}

	if ( $url === '' ) {
		fwrite( STDERR, "Error: URL is required.\n" );
		fwrite( STDERR, "Usage: php bin/manage.php site:add <url> [--label=<label>]\n" );
		exit( 1 );
	}

	$existing = $repo->findSiteByUrl( $url );
	if ( $existing !== null ) {
		fwrite( STDERR, "Error: Site '{$url}' already exists.\n" );
		exit( 1 );
	}

	$token = bin2hex( random_bytes( 32 ) );
	$hash = password_hash( $token, PASSWORD_ARGON2ID );
	$uuid = generate_uuid();

	$repo->addSite( $uuid, $url, $hash, $label );

	echo "Site registered successfully.\n";
	echo "ID:    {$uuid}\n";
	echo "URL:   {$url}\n";
	if ( $label !== null ) {
		echo "Label: {$label}\n";
	}
	echo "\nBearer token (save this — it cannot be retrieved later):\n";
	echo "{$token}\n";
}

/**
 * List all registered sites.
 *
 * @param SiteRepository $repo Repository.
 *
 * @return void
 */
function handle_site_list( SiteRepository $repo ): void {
	$sites = $repo->getAllSites();

	if ( $sites === [] ) {
		echo "No sites registered.\n";
		return;
	}

	foreach ( $sites as $site ) {
		$label = $site->label !== null ? " ({$site->label})" : '';
		echo "{$site->id}  {$site->siteUrl}{$label}\n";
	}

	echo "\nTotal: " . count( $sites ) . " site(s)\n";
}

/**
 * Rotate a site's bearer token.
 *
 * @param SiteRepository     $repo Repository.
 * @param array<int, string> $arguments CLI arguments.
 *
 * @return void
 */
function handle_site_rotate_token( SiteRepository $repo, array $arguments ): void {
	$url = $arguments[0] ?? '';

	if ( $url === '' ) {
		fwrite( STDERR, "Error: URL is required.\n" );
		fwrite( STDERR, "Usage: php bin/manage.php site:rotate-token <url>\n" );
		exit( 1 );
	}

	$site = $repo->findSiteByUrl( $url );
	if ( $site === null ) {
		fwrite( STDERR, "Error: Site '{$url}' not found.\n" );
		exit( 1 );
	}

	$token = bin2hex( random_bytes( 32 ) );
	$hash = password_hash( $token, PASSWORD_ARGON2ID );

	$repo->updateSiteTokenHash( $site->id, $hash );

	echo "Token rotated for {$url}.\n";
	echo "\nNew bearer token (save this — it cannot be retrieved later):\n";
	echo "{$token}\n";
}

/**
 * Create a new client read token.
 *
 * @param SiteRepository     $repo Repository.
 * @param array<int, string> $arguments CLI arguments.
 *
 * @return void
 */
function handle_client_add( SiteRepository $repo, array $arguments ): void {
	$label = null;

	foreach ( $arguments as $argument ) {
		if ( str_starts_with( $argument, '--label=' ) ) {
			$label = substr( $argument, 8 );
		}
	}

	$token = bin2hex( random_bytes( 32 ) );
	$hash = password_hash( $token, PASSWORD_ARGON2ID );

	$id = $repo->addClientToken( $hash, $label );

	echo "Client token created.\n";
	echo "ID: {$id}\n";
	if ( $label !== null ) {
		echo "Label: {$label}\n";
	}
	echo "\nBearer token (save this — it cannot be retrieved later):\n";
	echo "{$token}\n";
}

/**
 * Generate a v4 UUID.
 *
 * @return string
 */
function generate_uuid(): string {
	$data = random_bytes( 16 );
	$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
	$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

	return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
}
