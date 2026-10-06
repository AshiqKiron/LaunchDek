#!/usr/bin/env php
<?php
/**
 * Build the Community (WordPress.org) zip — Pro PHP and templates omitted.
 *
 * Usage: php bin/build-community-zip.php [--output=dist/launchdek-community.zip]
 *
 * @package LaunchDek
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Run from the command line only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$out  = $root . '/dist/launchdek-community.zip';

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--output=' ) ) {
		$out = substr( $arg, 9 );
	}
}

$community_categories = array( 'security', 'maintenance', 'performance', 'ecommerce' );

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Minimal sanitize_key for CLI.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

$exclude_paths = array(
	'.git',
	'.cursor',
	'.github',
	'vendor',
	'node_modules',
	'dist',
	'bin',
	'includes/class-launchdek-auto-capture.php',
	'includes/class-launchdek-email-notifier.php',
	'includes/class-launchdek-webhook-dispatcher.php',
	'includes/class-launchdek-drift-cron.php',
	'admin/partials/launchdek-billing-page.php',
	'composer.json',
	'composer.lock',
	'phpcs.xml.dist',
	'phpunit.xml.dist',
);

$staging = sys_get_temp_dir() . '/launchdek-community-' . uniqid( '', true );
$plugin_dir = $staging . '/LaunchDek';

/**
 * @param string $dir Directory.
 * @return void
 */
function rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = scandir( $dir );
	if ( false === $items ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			rrmdir( $path );
		} else {
			unlink( $path );
		}
	}
	rmdir( $dir );
}

/**
 * @param string $source Source file.
 * @param string $dest   Destination file.
 * @return void
 */
function copy_file( $source, $dest ) {
	$parent = dirname( $dest );
	if ( ! is_dir( $parent ) ) {
		mkdir( $parent, 0755, true );
	}
	copy( $source, $dest );
}

/**
 * @param string $path Relative path.
 * @param array  $exclude_paths Exclusions.
 * @return bool
 */
function should_exclude( $path, $exclude_paths ) {
	$path = str_replace( '\\', '/', $path );
	foreach ( $exclude_paths as $exclude ) {
		$exclude = str_replace( '\\', '/', $exclude );
		if ( $path === $exclude || 0 === strpos( $path, $exclude . '/' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * @param string $json_path Template JSON path.
 * @param array  $community_categories Allowed categories.
 * @return bool
 */
function template_allowed( $json_path, $community_categories ) {
	$raw = file_get_contents( $json_path );
	if ( false === $raw ) {
		return false;
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return false;
	}
	$category = isset( $data['category'] ) ? sanitize_key( (string) $data['category'] ) : '';

	return in_array( $category, $community_categories, true );
}

if ( is_dir( $staging ) ) {
	rrmdir( $staging );
}
mkdir( $plugin_dir, 0755, true );

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ( $iterator as $file ) {
	/** @var SplFileInfo $file */
	$relative = substr( $file->getPathname(), strlen( $root ) + 1 );
	$relative = str_replace( '\\', '/', $relative );

	if ( should_exclude( $relative, $exclude_paths ) ) {
		continue;
	}

	if ( 0 === strpos( $relative, 'templates/' ) && substr( $relative, -5 ) === '.json' ) {
		if ( ! template_allowed( $file->getPathname(), $community_categories ) ) {
			continue;
		}
	}

	$target = $plugin_dir . '/' . $relative;
	if ( $file->isDir() ) {
		if ( ! is_dir( $target ) ) {
			mkdir( $target, 0755, true );
		}
		continue;
	}

	copy_file( $file->getPathname(), $target );
}

$bootstrap = $plugin_dir . '/launchdek.php';
$contents  = file_get_contents( $bootstrap );
if ( false === $contents ) {
	fwrite( STDERR, "Could not read launchdek.php in staging.\n" );
	exit( 1 );
}

$needle = "require_once LAUNCHDEK_PLUGIN_DIR . 'includes/launchdek-build.php';";
$insert = "define( 'LAUNCHDEK_BUILD', 'community' );\n\n" . $needle;
if ( false === strpos( $contents, $needle ) ) {
	fwrite( STDERR, "launchdek.php bootstrap marker not found.\n" );
	exit( 1 );
}
if ( false === strpos( $contents, "define( 'LAUNCHDEK_BUILD', 'community' );" ) ) {
	$contents = str_replace( $needle, $insert, $contents );
	file_put_contents( $bootstrap, $contents );
}

$out_dir = dirname( $out );
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0755, true );
}
if ( file_exists( $out ) ) {
	unlink( $out );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $out, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not create zip: {$out}\n" );
	exit( 1 );
}

$zip_root = 'LaunchDek';
$files = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ( $files as $file ) {
	/** @var SplFileInfo $file */
	if ( ! $file->isFile() ) {
		continue;
	}
	$local = $zip_root . '/' . substr( $file->getPathname(), strlen( $plugin_dir ) + 1 );
	$local = str_replace( '\\', '/', $local );
	$zip->addFile( $file->getPathname(), $local );
}

$zip->close();
rrmdir( $staging );

fwrite( STDOUT, "Community build written to {$out}\n" );
