<?php
/**
 * WP-CLI commands for testing and for a server cron without WP-Cron.
 *
 *   wp travel-risk check [--skip-news]                    Run the hourly check now
 *   wp travel-risk test-notify --to=<id|email> [--country=ISR]
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Cli {

	/**
	 * Runs the hourly check: fetches advice for all followed countries, notifies on level changes
	 * and pre-fetches news.
	 *
	 * [--skip-news]
	 * : Skip the news pre-fetch.
	 */
	public function check( $args, $assoc ): void {
		$s = Notify::check( empty( $assoc['skip-news'] ) );
		\WP_CLI::success( sprintf(
			'%d users, %d pairs, %d source errors, %d changes, %d notifications, news for %d countries.',
			$s['users'], $s['pairs'], $s['errors'], $s['changes'], $s['notified'], $s['news']
		) );
	}

	/**
	 * Sends a sample "advice changed" notification to one user (push + e-mail if on).
	 *
	 * --to=<user>
	 * : User ID or e-mail address.
	 *
	 * [--country=<iso3>]
	 * : Country, default ISR.
	 *
	 * @subcommand test-notify
	 */
	public function test_notify( $args, $assoc ): void {
		$user = is_numeric( $assoc['to'] ) ? get_user_by( 'id', (int) $assoc['to'] ) : get_user_by( 'email', $assoc['to'] );
		if ( ! $user ) {
			\WP_CLI::error( 'User not found.' );
		}
		$country = countries()[ strtoupper( $assoc['country'] ?? 'ISR' ) ] ?? null;
		if ( ! $country ) {
			\WP_CLI::error( 'Unknown country.' );
		}
		$r = Notify::simulate( $user->ID, $country );
		\WP_CLI::success( sprintf( 'Push sent to %d device(s), %d failed; e-mail %s.', $r['push']['sent'], $r['push']['failed'], $r['email'] ? 'sent' : 'not sent (off)' ) );
	}
}
