<?php
/**
 * Outgoing webhook notifications.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook dispatcher for Slack, Discord, and Teams.
 */
class LAUNCHDEK_Webhook_Dispatcher {

	/**
	 * Dispatch notification for an event.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Event payload.
	 * @return void
	 */
	public static function dispatch( $event, $data = array() ) {
		$settings = LAUNCHDEK_Settings::get();
		$enabled  = $settings['notification_events'] ?? array();

		if ( ! empty( $enabled ) && ! in_array( $event, (array) $enabled, true ) ) {
			return;
		}

		$message = self::format_message( $event, $data );

		if ( ! empty( $settings['slack_webhook'] ) ) {
			self::send_slack( $settings['slack_webhook'], $message, $event, $data );
		}

		if ( ! empty( $settings['discord_webhook'] ) ) {
			self::send_discord( $settings['discord_webhook'], $message );
		}

		if ( ! empty( $settings['teams_webhook'] ) ) {
			self::send_teams( $settings['teams_webhook'], $message, $event );
		}
	}

	/**
	 * Format human-readable message.
	 *
	 * @param string $event Event.
	 * @param array  $data  Data.
	 * @return string
	 */
	protected static function format_message( $event, $data ) {
		/* translators: 1: event name */
		$base = sprintf( __( 'LaunchDek: %s', LAUNCHDEK_TEXT_DOMAIN ), $event );

		if ( ! empty( $data['checklist'] ) ) {
			$base .= ' — ' . $data['checklist'];
		} elseif ( ! empty( $data['workflow'] ) ) {
			$base .= ' — ' . $data['workflow'];
		}

		if ( ! empty( $data['error'] ) ) {
			$base .= ' — ' . $data['error'];
		}

		return $base;
	}

	/**
	 * Send Slack webhook.
	 *
	 * @param string $url     Webhook URL.
	 * @param string $message Message text.
	 * @param string $event   Event name.
	 * @param array  $data    Extra data.
	 * @return void
	 */
	protected static function send_slack( $url, $message, $event, $data ) {
		wp_remote_post(
			esc_url_raw( $url ),
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'text' => $message,
						'blocks' => array(
							array(
								'type' => 'section',
								'text' => array(
									'type' => 'mrkdwn',
									'text' => '*' . $message . '*',
								),
							),
						),
					)
				),
			)
		);
	}

	/**
	 * Send Discord webhook.
	 *
	 * @param string $url     Webhook URL.
	 * @param string $message Message.
	 * @return void
	 */
	protected static function send_discord( $url, $message ) {
		wp_remote_post(
			esc_url_raw( $url ),
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'content' => $message ) ),
			)
		);
	}

	/**
	 * Send Microsoft Teams webhook.
	 *
	 * @param string $url     Webhook URL.
	 * @param string $message Message.
	 * @param string $event   Event.
	 * @return void
	 */
	protected static function send_teams( $url, $message, $event ) {
		wp_remote_post(
			esc_url_raw( $url ),
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'@type'      => 'MessageCard',
						'@context'   => 'http://schema.org/extensions',
						'summary'    => $message,
						'themeColor' => '0076D7',
						'title'      => 'LaunchDek — ' . $event,
						'text'       => $message,
					)
				),
			)
		);
	}
}
