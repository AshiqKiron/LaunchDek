<?php
/**
 * Background drift detection for remote site settings.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drift and state verifier.
 */
class LAUNCHDEK_Drift_Verifier {

	const STATUS_OPTION = 'launchdek_drift_status';

	/**
	 * Critical settings to monitor.
	 *
	 * @return array
	 */
	public static function get_checks() {
		return array(
			'permalink_structure' => array(
				'route'   => '/wp/v2/settings',
				'field'   => 'permalink_structure',
				'label'   => __( 'Permalink Structure', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'blog_public' => array(
				'route'   => '/wp/v2/settings',
				'field'   => 'blog_public',
				'label'   => __( 'Search Engine Visibility', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'environment_type' => array(
				'route'   => '/wp/v2/settings',
				'field'   => 'wp_environment_type',
				'label'   => __( 'Environment Type', LAUNCHDEK_TEXT_DOMAIN ),
			),
		);
	}

	/**
	 * Verify a single site for configuration drift.
	 *
	 * @param int $site_id Site ID.
	 * @return array
	 */
	public static function verify_site( $site_id ) {
		$client = LAUNCHDEK_Remote_Client::from_site( $site_id );
		$site   = LAUNCHDEK_Site_Repository::find( $site_id );

		if ( ! $client || ! $site ) {
			return array(
				'success' => false,
				'message' => __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ),
			);
		}

		$drifts  = array();
		$checks  = self::get_checks();
		$baseline = get_option( 'launchdek_drift_baseline_' . $site_id, array() );

		foreach ( $checks as $key => $check ) {
			$response = $client->rest( 'GET', $check['route'] );

			if ( is_wp_error( $response ) ) {
				$drifts[] = array(
					'check'   => $key,
					'label'   => $check['label'],
					'status'  => 'error',
					'message' => $response->get_error_message(),
				);
				continue;
			}

			$current = $response['body'][ $check['field'] ] ?? null;
			$stored  = $baseline[ $key ] ?? null;

			if ( null === $stored ) {
				$baseline[ $key ] = $current;
				continue;
			}

			if ( $stored !== $current ) {
				$drifts[] = array(
					'check'    => $key,
					'label'    => $check['label'],
					'status'   => 'drift',
					'expected' => $stored,
					'actual'   => $current,
				);
			}
		}

		update_option( 'launchdek_drift_baseline_' . $site_id, $baseline, false );

		LAUNCHDEK_Audit_Log::log(
			'drift_verified',
			array(
				'drifts' => $drifts,
				'count'  => count( $drifts ),
			),
			$site_id
		);

		$result = array(
			'success'   => true,
			'site_id'   => $site_id,
			'site_name' => $site['name'] ?: $site['url'],
			'drifts'    => $drifts,
			'has_drift' => ! empty( $drifts ),
		);

		self::record_site_status( $site_id, $result );

		return $result;
	}

	/**
	 * Verify all registered sites.
	 *
	 * @return array
	 */
	public static function verify_all() {
		$sites   = LAUNCHDEK_Site_Repository::all();
		$results = array();

		foreach ( $sites as $site ) {
			$results[ $site['id'] ] = self::verify_site( $site['id'] );
		}

		self::record_batch_status( $results );

		return $results;
	}

	/**
	 * Persist per-site drift status for the admin UI.
	 *
	 * @param int   $site_id Site ID.
	 * @param array $result  Verification result.
	 * @return void
	 */
	private static function record_site_status( $site_id, $result ) {
		$status = self::get_status_store();
		$status['sites'][ (string) $site_id ] = array(
			'site_id'   => (int) $site_id,
			'site_name' => $result['site_name'] ?? '',
			'has_drift' => ! empty( $result['has_drift'] ),
			'drifts'    => $result['drifts'] ?? array(),
			'checked_at'=> current_time( 'mysql', true ),
		);
		$status['last_run'] = current_time( 'mysql', true );
		update_option( self::STATUS_OPTION, $status, false );
	}

	/**
	 * Update stored status after a batch verification.
	 *
	 * @param array $results Site results keyed by site ID.
	 * @return void
	 */
	private static function record_batch_status( $results ) {
		$status = self::get_status_store();

		foreach ( $results as $site_id => $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}
			$status['sites'][ (string) $site_id ] = array(
				'site_id'   => (int) $site_id,
				'site_name' => $result['site_name'] ?? '',
				'has_drift' => ! empty( $result['has_drift'] ),
				'drifts'    => $result['drifts'] ?? array(),
				'checked_at'=> current_time( 'mysql', true ),
			);
		}

		$status['last_run'] = current_time( 'mysql', true );
		update_option( self::STATUS_OPTION, $status, false );
	}

	/**
	 * Read stored drift status.
	 *
	 * @return array
	 */
	private static function get_status_store() {
		$stored = get_option( self::STATUS_OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Build a summary payload for the admin UI.
	 *
	 * @return array
	 */
	public static function get_status_summary() {
		$settings = LAUNCHDEK_Settings::get();
		$stored   = self::get_status_store();
		$sites    = LAUNCHDEK_Site_Repository::all();
		$entries  = array();

		foreach ( $sites as $site ) {
			$key     = (string) $site['id'];
			$entry   = $stored['sites'][ $key ] ?? array();
			$entries[] = array(
				'site_id'    => (int) $site['id'],
				'site_name'  => $site['name'] ?: $site['url'],
				'has_drift'  => ! empty( $entry['has_drift'] ),
				'drift_count'=> is_array( $entry['drifts'] ?? null ) ? count( $entry['drifts'] ) : 0,
				'checked_at' => $entry['checked_at'] ?? '',
			);
		}

		return array(
			'enabled'         => ! empty( $settings['enabled'] ) && ! empty( $settings['drift_verification_enabled'] ),
			'last_run'        => $stored['last_run'] ?? '',
			'next_scheduled'  => wp_next_scheduled( LAUNCHDEK_Drift_Cron::HOOK ) ? gmdate( 'Y-m-d H:i:s', wp_next_scheduled( LAUNCHDEK_Drift_Cron::HOOK ) ) : '',
			'checks'          => array_values(
				array_map(
					function ( $check ) {
						return $check['label'];
					},
					self::get_checks()
				)
			),
			'sites'           => $entries,
		);
	}
}
