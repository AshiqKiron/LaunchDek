<?php
/**
 * Email notifications for LaunchDek events.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends configurable email alerts via wp_mail().
 */
class LAUNCHDEK_Email_Notifier {

	/**
	 * Dispatch an email notification for an event.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Event payload.
	 * @return void
	 */
	public static function dispatch( $event, $data = array() ) {
		$settings = LAUNCHDEK_Settings::get();
		$enabled  = $settings['email_notification_events'] ?? array();

		if ( empty( $enabled ) || ! in_array( $event, (array) $enabled, true ) ) {
			return;
		}

		$recipients = LAUNCHDEK_Settings::get_email_notification_addresses();

		if ( empty( $recipients ) ) {
			return;
		}

		$data    = self::enrich_data( $event, $data );
		$subject = self::build_subject( $event, $data );
		$body    = self::build_body( $event, $data );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		wp_mail( $recipients, $subject, $body, $headers );
	}

	/**
	 * Add run/site/checklist context when a run ID is present.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Event payload.
	 * @return array
	 */
	protected static function enrich_data( $event, $data ) {
		if ( empty( $data['run_id'] ) ) {
			return $data;
		}

		$run = LAUNCHDEK_Run_Repository::find( absint( $data['run_id'] ) );

		if ( ! $run ) {
			return $data;
		}

		$defaults = array(
			'checklist'         => $run['checklist_title'],
			'site_id'           => $run['site_id'],
			'site_name'         => $run['site_name'],
			'site_url'          => $run['site_url'],
			'status'            => $run['status'],
			'started_by_name'   => $run['started_by_name'],
			'started_at'        => $run['started_at'],
			'completed_at'      => $run['completed_at'],
		);

		return wp_parse_args( $data, $defaults );
	}

	/**
	 * Build email subject line.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Event payload.
	 * @return string
	 */
	protected static function build_subject( $event, $data ) {
		$labels = LAUNCHDEK_Settings::get_email_notification_events();
		$label  = $labels[ $event ]['label'] ?? $event;

		/* translators: 1: event label */
		$subject = sprintf( __( 'LaunchDek: %s', LAUNCHDEK_TEXT_DOMAIN ), $label );

		if ( ! empty( $data['site_name'] ) ) {
			$subject .= ' — ' . $data['site_name'];
		}

		if ( ! empty( $data['checklist'] ) ) {
			$subject .= ' (' . $data['checklist'] . ')';
		}

		return $subject;
	}

	/**
	 * Build plain-text email body.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Event payload.
	 * @return string
	 */
	protected static function build_body( $event, $data ) {
		$labels = LAUNCHDEK_Settings::get_email_notification_events();
		$label  = $labels[ $event ]['label'] ?? $event;
		$lines  = array();

		/* translators: 1: event label */
		$lines[] = sprintf( __( 'LaunchDek alert: %s', LAUNCHDEK_TEXT_DOMAIN ), $label );
		$lines[] = '';

		if ( ! empty( $data['site_name'] ) ) {
			/* translators: %s: site name */
			$lines[] = sprintf( __( 'Site: %s', LAUNCHDEK_TEXT_DOMAIN ), $data['site_name'] );
		}

		if ( ! empty( $data['site_url'] ) ) {
			/* translators: %s: site URL */
			$lines[] = sprintf( __( 'URL: %s', LAUNCHDEK_TEXT_DOMAIN ), $data['site_url'] );
		}

		if ( ! empty( $data['checklist'] ) ) {
			/* translators: %s: checklist title */
			$lines[] = sprintf( __( 'Checklist: %s', LAUNCHDEK_TEXT_DOMAIN ), $data['checklist'] );
		}

		if ( ! empty( $data['step_title'] ) ) {
			/* translators: %s: step title */
			$lines[] = sprintf( __( 'Step: %s', LAUNCHDEK_TEXT_DOMAIN ), $data['step_title'] );
		}

		if ( ! empty( $data['client_user'] ) ) {
			/* translators: %s: client user display name */
			$lines[] = sprintf( __( 'Completed by: %s', LAUNCHDEK_TEXT_DOMAIN ), $data['client_user'] );
		}

		if ( ! empty( $data['note'] ) ) {
			/* translators: %s: note excerpt */
			$lines[] = sprintf( __( 'Note: %s', LAUNCHDEK_TEXT_DOMAIN ), wp_trim_words( $data['note'], 40, '…' ) );
		}

		if ( ! empty( $data['error'] ) ) {
			/* translators: %s: error message */
			$lines[] = sprintf( __( 'Error: %s', LAUNCHDEK_TEXT_DOMAIN ), $data['error'] );
		}

		if ( ! empty( $data['started_by_name'] ) ) {
			/* translators: %s: user display name */
			$lines[] = sprintf( __( 'Started by: %s', LAUNCHDEK_TEXT_DOMAIN ), $data['started_by_name'] );
		}

		if ( ! empty( $data['started_at'] ) ) {
			/* translators: %s: datetime */
			$lines[] = sprintf( __( 'Started: %s', LAUNCHDEK_TEXT_DOMAIN ), $data['started_at'] );
		}

		if ( ! empty( $data['completed_at'] ) ) {
			/* translators: %s: datetime */
			$lines[] = sprintf( __( 'Completed: %s', LAUNCHDEK_TEXT_DOMAIN ), $data['completed_at'] );
		}

		if ( ! empty( $data['run_id'] ) ) {
			$run_url = admin_url(
				'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-automation&run_id=' . absint( $data['run_id'] )
			);
			$lines[] = '';
			/* translators: %s: admin URL */
			$lines[] = sprintf( __( 'View run: %s', LAUNCHDEK_TEXT_DOMAIN ), $run_url );
		}

		$lines[] = '';
		/* translators: %s: site name */
		$lines[] = sprintf( __( 'Sent from %s', LAUNCHDEK_TEXT_DOMAIN ), get_bloginfo( 'name' ) );

		return implode( "\n", $lines );
	}
}
