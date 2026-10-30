#!/usr/bin/env php
<?php
/**
 * Verify the WordPress.org Community zip excludes Pro-only assets.
 *
 * Usage: php bin/verify-community-zip.php [--zip=dist/checklistdek-free.zip]
 *
 * @package ChecklistDek
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Run from the command line only.\n" );
	exit( 1 );
}

/**
 * Allowed built-in template categories in the Community zip.
 *
 * @return string[]
 */
function checklistdek_community_template_categories() {
	return array( 'security', 'maintenance', 'performance', 'ecommerce' );
}

/**
 * Paths that must not appear inside the plugin root (checklistdek/…).
 *
 * @return string[]
 */
function checklistdek_community_zip_forbidden_paths() {
	return array(
		'vendor',
		'bin',
		'composer.json',
		'composer.lock',
		'includes/checklistdek-freemius.php',
		'includes/class-checklistdek-licensing.php',
		'includes/community/class-checklistdek-licensing.php',
		'includes/class-checklistdek-auto-capture.php',
		'includes/class-checklistdek-email-notifier.php',
		'includes/class-checklistdek-webhook-dispatcher.php',
		'includes/class-checklistdek-drift-cron.php',
		'includes/class-checklistdek-mu-plugin-installer.php',
		'includes/integrations/class-checklistdek-mainwp-agent.php',
		'includes/client-panel-layouts.php',
		'includes/checklistdek-premium-modules.php',
		'includes/class-checklistdek-installer-vault-cleanup.php',
		'mu-plugin/checklistdek-client/class-rest-api-install.php',
		'mu-plugin/checklistdek-client/class-auto-capture.php',
		'admin/partials/checklistdek-billing-page.php',
		'includes/class-checklistdek-agency-vault.php',
		'admin/partials/checklistdek-checklists-vault-section.php',
		'admin/js/checklistdek-admin-vault.js',
	);
}

/**
 * Community stub paths that must be present.
 *
 * @return string[]
 */
function checklistdek_community_zip_required_paths() {
	return array(
		'checklistdek.php',
		'includes/checklistdek-build.php',
		'includes/community/class-checklistdek-mu-plugin-installer.php',
		'includes/community/client-panel-layouts.php',
		'includes/community/class-checklistdek-webhook-dispatcher.php',
		'includes/community/class-checklistdek-drift-cron.php',
	);
}

/**
 * Normalize a zip entry to a path relative to the plugin root, or empty if not under checklistdek/.
 *
 * @param string $entry Zip entry name.
 * @return string
 */
function checklistdek_community_zip_relative_entry( $entry ) {
	$entry = str_replace( '\\', '/', (string) $entry );
	$entry = ltrim( $entry, '/' );

	if ( 0 === strpos( $entry, 'checklistdek/' ) ) {
		return substr( $entry, strlen( 'checklistdek/' ) );
	}

	return '';
}

/**
 * @param string $relative Path relative to plugin root.
 * @param string $forbidden Forbidden path (file or directory prefix).
 * @return bool
 */
function checklistdek_community_path_matches_forbidden( $relative, $forbidden ) {
	$forbidden = str_replace( '\\', '/', $forbidden );
	if ( $relative === $forbidden ) {
		return true;
	}

	return 0 === strpos( $relative, $forbidden . '/' );
}

/**
 * Verify a Community zip package.
 *
 * @param string $zip_path Absolute path to checklistdek-free.zip.
 * @return string[] List of error messages (empty when valid).
 */
function checklistdek_verify_community_zip( $zip_path ) {
	$errors = array();

	if ( ! is_readable( $zip_path ) ) {
		return array( "Zip not found or not readable: {$zip_path}" );
	}

	if ( ! class_exists( 'ZipArchive', false ) ) {
		return array( 'PHP ZipArchive extension is required.' );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return array( "Could not open zip: {$zip_path}" );
	}

	$entries       = array();
	$forbidden     = checklistdek_community_zip_forbidden_paths();
	$required      = checklistdek_community_zip_required_paths();
	$present       = array_fill_keys( $required, false );
	$categories    = checklistdek_community_template_categories();
	$template_json = array();

	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name     = $zip->getNameIndex( $i );
		$relative = checklistdek_community_zip_relative_entry( $name );
		if ( '' === $relative ) {
			continue;
		}

		$entries[] = $relative;

		foreach ( $forbidden as $path ) {
			if ( checklistdek_community_path_matches_forbidden( $relative, $path ) ) {
				$errors[] = "Forbidden path in zip: checklistdek/{$relative}";
			}
		}

		if ( isset( $present[ $relative ] ) ) {
			$present[ $relative ] = true;
		}

		if ( 0 === strpos( $relative, 'templates/' ) && substr( $relative, -5 ) === '.json' ) {
			$template_json[] = $relative;
		}
	}

	foreach ( $present as $path => $found ) {
		if ( ! $found ) {
			$errors[] = "Missing required Community file: checklistdek/{$path}";
		}
	}

	if ( empty( $template_json ) ) {
		$errors[] = 'No template JSON files found under checklistdek/templates/.';
	}

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

	foreach ( $template_json as $relative ) {
		$raw = $zip->getFromName( 'checklistdek/' . $relative );
		if ( false === $raw ) {
			$errors[] = "Could not read template from zip: checklistdek/{$relative}";
			continue;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$errors[] = "Invalid template JSON in zip: checklistdek/{$relative}";
			continue;
		}
		$category = isset( $data['category'] ) ? sanitize_key( (string) $data['category'] ) : '';
		if ( ! in_array( $category, $categories, true ) ) {
			$errors[] = "Template category \"{$category}\" not allowed in Community zip: checklistdek/{$relative}";
		}
	}

	$bootstrap = $zip->getFromName( 'checklistdek/checklistdek.php' );
	if ( false === $bootstrap ) {
		$errors[] = 'Missing checklistdek/checklistdek.php in zip.';
	} else {
		if ( preg_match( "/define\\s*\\(\\s*['\"]CHECKLISTDEK_BUILD['\"]\\s*,\\s*['\"]community['\"]\\s*\\)/", $bootstrap ) ) {
			$errors[] = 'Bootstrap must not patch CHECKLISTDEK_BUILD to community (WordPress.org package uses default build helpers only).';
		}
		if ( preg_match( '/require_once\\s+[^;]*checklistdek-freemius\\.php/', $bootstrap ) ) {
			$errors[] = 'Bootstrap must not require checklistdek-freemius.php directly.';
		}
	}

	$build_helpers = $zip->getFromName( 'checklistdek/includes/checklistdek-build.php' );
	if ( false === $build_helpers ) {
		$errors[] = 'Missing checklistdek/includes/checklistdek-build.php in zip.';
	} elseif ( preg_match( "/define\\s*\\(\\s*['\"]CHECKLISTDEK_BUILD['\"]\\s*,\\s*['\"]community['\"]\\s*\\)/", $build_helpers ) ) {
		$errors[] = 'checklistdek-build.php must not hard-code CHECKLISTDEK_BUILD to community in the org zip.';
	} elseif ( false === strpos( $build_helpers, 'checklistdek_is_pro_distribution' ) ) {
		$errors[] = 'checklistdek-build.php must scope Pro features to Pro distribution (checklistdek_is_pro_distribution).';
	} elseif ( false !== strpos( $build_helpers, 'can_use_agency_vault' ) || false !== strpos( $build_helpers, 'is_vault' ) ) {
		$errors[] = 'checklistdek-build.php must not reference Private Agency Vault (Pro-only premium module).';
	}

	$installer = $zip->getFromName( 'checklistdek/includes/class-checklistdek-installer.php' );
	if ( false === $installer ) {
		$errors[] = 'Missing checklistdek/includes/class-checklistdek-installer.php in zip.';
	} elseif ( false !== strpos( $installer, 'DROP COLUMN `is_vault`' ) ) {
		$errors[] = 'Community installer must not run is_vault DROP COLUMN (Pro-only vault cleanup file).';
	} elseif ( false !== strpos( $installer, 'is_vault' ) ) {
		$errors[] = 'Community installer must not define is_vault schema (Pro-only premium module).';
	} elseif ( false === strpos( $installer, 'checklistdek_premium_installer_migrations' ) ) {
		$errors[] = 'Community installer must delegate Pro checklist schema to checklistdek_premium_installer_migrations when bundled.';
	}

	foreach ( $entries as $relative ) {
		if ( false !== stripos( $relative, 'freemius' ) ) {
			$errors[] = "Freemius-related path in zip: checklistdek/{$relative}";
		}
	}

	$forbidden_class_markers = array(
		'class CHECKLISTDEK_Agency_Vault',
		'class CHECKLISTDEK_Auto_Capture',
		'class CHECKLISTDEK_Client_Auto_Capture',
		'class CHECKLISTDEK_Client_REST_API_Install',
	);

	$forbidden_content_markers = array(
		'/templates/vault',
		'can_use_agency_vault',
		'checklistdek-admin-vault',
		'checklistdek-agency-vault',
	);

	foreach ( $entries as $relative ) {
		if ( substr( $relative, -4 ) !== '.php' && substr( $relative, -3 ) !== '.js' ) {
			continue;
		}

		$raw = $zip->getFromName( 'checklistdek/' . $relative );
		if ( false === $raw ) {
			continue;
		}

		foreach ( $forbidden_class_markers as $marker ) {
			if ( false !== strpos( $raw, $marker ) ) {
				$errors[] = "Premium executable marker \"{$marker}\" in zip: checklistdek/{$relative}";
			}
		}

		foreach ( $forbidden_content_markers as $marker ) {
			if ( false !== strpos( $raw, $marker ) ) {
				$errors[] = "Private Agency Vault marker \"{$marker}\" in zip: checklistdek/{$relative}";
			}
		}
	}

	$zip->close();

	return array_values( array_unique( $errors ) );
}

$verify_cli_script = isset( $argv[0] ) ? realpath( $argv[0] ) : false;
$verify_this_file  = realpath( __FILE__ );

if ( 'cli' === PHP_SAPI && $verify_cli_script && $verify_this_file && $verify_cli_script === $verify_this_file ) {
	$root     = dirname( __DIR__ );
	$zip_path = $root . '/dist/checklistdek-free.zip';

	foreach ( array_slice( $argv, 1 ) as $arg ) {
		if ( 0 === strpos( $arg, '--zip=' ) ) {
			$zip_path = substr( $arg, 6 );
			if ( $zip_path !== $root . '/dist/checklistdek-free.zip' && ! preg_match( '#^/#', $zip_path ) ) {
				$zip_path = $root . '/' . ltrim( $zip_path, '/' );
			}
		} elseif ( '--help' === $arg || '-h' === $arg ) {
			fwrite( STDOUT, "Usage: php bin/verify-community-zip.php [--zip=dist/checklistdek-free.zip]\n" );
			exit( 0 );
		}
	}

	$errors = checklistdek_verify_community_zip( $zip_path );

	if ( ! empty( $errors ) ) {
		fwrite( STDERR, "Community zip verification failed: {$zip_path}\n" );
		foreach ( $errors as $error ) {
			fwrite( STDERR, "  - {$error}\n" );
		}
		exit( 1 );
	}

	fwrite( STDOUT, "Community zip OK: {$zip_path}\n" );
	exit( 0 );
}
