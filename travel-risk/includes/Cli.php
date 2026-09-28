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
	 * and on new orange/red GDACS disaster alerts, and pre-fetches news.
	 *
	 * [--skip-news]
	 * : Skip the news pre-fetch.
	 */
	public function check( $args, $assoc ): void {
		$s = Notify::check( empty( $assoc['skip-news'] ) );
		\WP_CLI::success( sprintf(
			'%d users, %d pairs, %d source errors, %d changes, %d notifications, %d disaster alert notifications, %d world changes logged, %d daily overviews, news for %d countries.',
			$s['users'], $s['pairs'], $s['errors'], $s['changes'], $s['notified'], $s['alerts'], $s['world'], $s['digests'], $s['news']
		) );
	}

	/**
	 * Sends the daily world overview to one user now (changes since their last overview).
	 *
	 * --to=<user>
	 * : User ID or e-mail address.
	 */
	public function digest( $args, $assoc ): void {
		$user = is_numeric( $assoc['to'] ) ? get_user_by( 'id', (int) $assoc['to'] ) : get_user_by( 'email', $assoc['to'] );
		if ( ! $user ) {
			\WP_CLI::error( 'User not found.' );
		}
		World::send_digests( $user->ID );
		\WP_CLI::success( 'Daily overview sent to ' . $user->user_email . ' (push devices and e-mail if on).' );
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
		foreach ( $r['push']['devices'] as $d ) {
			\WP_CLI::log( sprintf( '  %s: %s', $d['service'], $d['code'] >= 200 && $d['code'] < 300 ? 'OK' : trim( ( $d['code'] ? $d['code'] : 'no answer' ) . ' ' . $d['reason'] ) ) );
		}
		\WP_CLI::success( sprintf( 'Push sent to %d device(s), %d failed; e-mail %s.', $r['push']['sent'], $r['push']['failed'], $r['email'] ? 'sent' : 'not sent (off)' ) );
	}
}
