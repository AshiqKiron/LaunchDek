#!/usr/bin/env php
<?php
/**
 * Build the full Pro / premium zip (all templates, Pro PHP, Freemius SDK).
 *
 * Usage: php bin/build-pro-zip.php [--output=dist/launchdek-pro.zip]
 *
 * @package LaunchDek
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Run from the command line only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$out  = $root . '/dist/launchdek-pro.zip';

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--output=' ) ) {
		$out = substr( $arg, 9 );
	}
}

$exclude_paths = array(
	'.git',
	'.cursor',
	'.github',
	'node_modules',
	'dist',
	'bin',
	'includes/community',
	'composer.json',
	'composer.lock',
	'phpcs.xml.dist',
	'phpunit.xml.dist',
);

$staging    = sys_get_temp_dir() . '/launchdek-pro-' . uniqid( '', true );
$plugin_dir = $staging . '/launchdek';

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
function should_exclude_pro( $path, $exclude_paths ) {
	$path = str_replace( '\\', '/', $path );

	if ( 0 === strpos( $path, 'vendor/' ) ) {
		return 0 !== strpos( $path, 'vendor/freemius' );
	}

	foreach ( $exclude_paths as $exclude ) {
		$exclude = str_replace( '\\', '/', $exclude );
		if ( $path === $exclude || 0 === strpos( $path, $exclude . '/' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * WordPress.org plugin check rejects hidden files (.DS_Store, .gitignore, etc.).
 *
 * @param string $path Relative path.
 * @return bool
 */
function is_hidden_path_pro( $path ) {
	$path = str_replace( '\\', '/', $path );
	foreach ( explode( '/', $path ) as $segment ) {
		if ( '' !== $segment && '.' === $segment[0] ) {
			return true;
		}
	}
	return false;
}

$freemius_dir = $root . '/vendor/freemius';
if ( ! is_dir( $freemius_dir ) ) {
	fwrite( STDERR, "Freemius SDK missing. Install vendor/freemius before building the Pro zip.\n" );
	exit( 1 );
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

	if ( is_hidden_path_pro( $relative ) || should_exclude_pro( $relative, $exclude_paths ) ) {
		continue;
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

$zip_root = 'launchdek';
$files    = new RecursiveIteratorIterator(
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

fwrite( STDOUT, "Pro build written to {$out}\n" );
