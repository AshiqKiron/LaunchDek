#!/usr/bin/env php
<?php
/**
 * Build the Community (WordPress.org) zip — Pro PHP and templates omitted.
 *
 * Usage: php bin/build-community-zip.php [--output=dist/checklistdek-community.zip]
 *
 * @package ChecklistDek
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Run from the command line only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$out  = $root . '/dist/checklistdek-community.zip';

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
	'includes/class-checklistdek-auto-capture.php',
	'mu-plugin/checklistdek-client/class-auto-capture.php',
	'includes/class-checklistdek-mu-plugin-installer.php',
	'includes/integrations/class-checklistdek-mainwp-agent.php',
	'mu-plugin/checklistdek-client/class-rest-api-install.php',
	'includes/class-checklistdek-email-notifier.php',
	'includes/class-checklistdek-webhook-dispatcher.php',
	'includes/class-checklistdek-drift-cron.php',
	'includes/class-checklistdek-licensing.php',
	'includes/checklistdek-freemius.php',
	'includes/community/class-checklistdek-licensing.php',
	'includes/client-panel-layouts.php',
	'admin/partials/checklistdek-billing-page.php',
	'composer.json',
	'composer.lock',
	'phpcs.xml.dist',
	'phpunit.xml.dist',
	'ChecklistDek.code-workspace',
);

$staging = sys_get_temp_dir() . '/checklistdek-community-' . uniqid( '', true );
$plugin_dir = $staging . '/checklistdek';

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
 * WordPress.org plugin check rejects hidden files (.DS_Store, .gitignore, etc.).
 *
 * @param string $path Relative path.
 * @return bool
 */
function is_hidden_path( $path ) {
	$path = str_replace( '\\', '/', $path );
	foreach ( explode( '/', $path ) as $segment ) {
		if ( '' !== $segment && '.' === $segment[0] ) {
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

/**
 * Strip translation entries from the shipped .pot that reference Pro-only or removed sources.
 *
 * Keeps the WordPress.org zip free of stale billing / license-limit strings for Plugin Check.
 *
 * @param string $pot_path Absolute path to checklistdek.pot in the staging tree.
 * @return void
 */
function filter_community_pot( $pot_path ) {
	if ( ! is_readable( $pot_path ) ) {
		return;
	}

	$content = file_get_contents( $pot_path );
	if ( false === $content ) {
		return;
	}

	$exclude_source_fragments = array(
		'admin/partials/checklistdek-billing-page.php',
		'includes/class-checklistdek-auto-capture.php',
		'includes/class-checklistdek-email-notifier.php',
		'includes/class-checklistdek-webhook-dispatcher.php',
		'includes/class-checklistdek-drift-cron.php',
		'includes/class-checklistdek-mu-plugin-installer.php',
		'includes/integrations/class-checklistdek-mainwp-agent.php',
		'mu-plugin/checklistdek-client/class-rest-api-install.php',
		'mu-plugin/checklistdek-client/class-auto-capture.php',
		'includes/checklistdek-freemius.php',
		'includes/class-checklistdek-licensing.php',
		'includes/community/class-checklistdek-licensing.php',
	);

	$exclude_msgid_fragments = array(
		'Upgrade on Billing',
		'Get Pro',
		'site limit',
		'Compare Community and paid plans',
		'Pro and Agency plans',
		'Billing admin page',
	);

	$pos     = strpos( $content, "\n#:" );
	$header  = false === $pos ? $content : substr( $content, 0, $pos );
	$entries = false === $pos ? '' : substr( $content, $pos + 1 );

	if ( '' === trim( $entries ) ) {
		return;
	}

	$blocks = preg_split( '/\n(?=#:)/', trim( $entries ), -1, PREG_SPLIT_NO_EMPTY );
	$kept   = array();

	foreach ( $blocks as $block ) {
		$block = trim( $block );
		if ( '' === $block ) {
			continue;
		}

		$drop = false;
		foreach ( $exclude_source_fragments as $fragment ) {
			if ( false !== strpos( $block, $fragment ) ) {
				$drop = true;
				break;
			}
		}

		if ( ! $drop ) {
			foreach ( $exclude_msgid_fragments as $fragment ) {
				if ( false !== stripos( $block, $fragment ) ) {
					$drop = true;
					break;
				}
			}
		}

		// Stale menu label — Billing submenu was removed; do not ship in the Community catalog.
		if ( ! $drop && preg_match( '/^msgid "Billing"$/m', $block ) ) {
			$drop = true;
		}

		if ( ! $drop ) {
			$kept[] = $block;
		}
	}

	$filtered = rtrim( $header ) . "\n\n" . implode( "\n\n", $kept ) . "\n";
	file_put_contents( $pot_path, $filtered );
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

	if ( is_hidden_path( $relative ) || should_exclude( $relative, $exclude_paths ) ) {
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

$community_pot = $plugin_dir . '/languages/checklistdek.pot';
filter_community_pot( $community_pot );
copy( $community_pot, $root . '/languages/checklistdek.pot' );

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

$zip_root = 'checklistdek';
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
