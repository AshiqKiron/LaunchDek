<?php
/**
 * Integration site inventory sync orchestrator.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync connector site lists into LaunchDek sites.
 */
class LAUNCHDEK_Integration_Sync {

	/**
	 * Preview sync without writing site rows.
	 *
	 * @param string $slug Integration slug.
	 * @return array|WP_Error
	 */
	public static function preview( $slug ) {
		return self::run( $slug, true );
	}

	/**
	 * Sync connector sites into LaunchDek.
	 *
	 * @param string $slug Integration slug.
	 * @return array|WP_Error
	 */
	public static function sync( $slug ) {
		return self::run( $slug, false );
	}

	/**
	 * Run sync or preview for an integration.
	 *
	 * @param string $slug    Integration slug.
	 * @param bool   $dry_run Preview only.
	 * @return array|WP_Error
	 */
	protected static function run( $slug, $dry_run ) {
		$integration = LAUNCHDEK_Integrations::get( $slug );

		if ( ! $integration ) {
			return new WP_Error(
				'launchdek_integration_not_found',
				__( 'Integration not found.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 404 )
			);
		}

		if ( ! $integration->supports_site_sync() ) {
			return new WP_Error(
				'launchdek_integration_sync_unsupported',
				__( 'This connector does not support site sync yet.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		$platform_sites = $integration->fetch_platform_sites();

		if ( is_wp_error( $platform_sites ) ) {
			return $platform_sites;
		}

		$summary = array(
			'created'           => 0,
			'updated'           => 0,
			'skipped'           => 0,
			'needs_credentials' => 0,
			'total'             => count( $platform_sites ),
		);

		$items = array();

		foreach ( $platform_sites as $platform_row ) {
			$external_id = (string) ( $platform_row['external_id'] ?? '' );
			if ( '' === $external_id ) {
				++$summary['skipped'];
				continue;
			}

			$result = LAUNCHDEK_Site_Repository::upsert_from_integration( $slug, $external_id, $platform_row, $dry_run );
			$action = $result['action'] ?? 'skipped';

			if ( 'created' === $action ) {
				++$summary['created'];
			} elseif ( 'updated' === $action ) {
				++$summary['updated'];
			} else {
				++$summary['skipped'];
			}

			if ( ! $dry_run && ! empty( $result['site_id'] ) && ! LAUNCHDEK_Site_Repository::has_credentials( (int) $result['site_id'] ) ) {
				++$summary['needs_credentials'];
			}

			$items[] = $result;
		}

		if ( ! $dry_run ) {
			LAUNCHDEK_Audit_Log::log(
				'integration_sync',
				array(
					'integration' => $slug,
					'summary'     => $summary,
				)
			);
		}

		return array(
			'integration' => $slug,
			'dry_run'     => $dry_run,
			'summary'     => $summary,
			'items'       => $items,
		);
	}
}
