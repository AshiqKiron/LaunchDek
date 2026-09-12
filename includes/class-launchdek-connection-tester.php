<?php
/**
 * Connection testing utility.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-click connection tester.
 */
class LAUNCHDEK_Connection_Tester {

	/**
	 * Test connection for a stored site.
	 *
	 * @param int $site_id Site ID.
	 * @return array
	 */
	public static function test_site( $site_id ) {
		$client = LAUNCHDEK_Remote_Client::from_site( $site_id );

		if ( ! $client ) {
			return array(
				'success' => false,
				'message' => __( 'Site not found or missing credentials.', LAUNCHDEK_TEXT_DOMAIN ),
			);
		}

		$result = $client->ping();

		LAUNCHDEK_Audit_Log::log(
			'connection_test',
			$result,
			$site_id
		);

		return $result;
	}

	/**
	 * Test connection with raw credentials (before save).
	 *
	 * @param string $url      Site URL.
	 * @param string $username Admin username.
	 * @param string $password App password.
	 * @return array
	 */
	public static function test_raw( $url, $username, $password ) {
		$client = new LAUNCHDEK_Remote_Client(
			0,
			array(
				'url'             => $url,
				'admin_username'  => $username,
				'app_password'    => $password,
			)
		);

		return $client->ping();
	}
}
