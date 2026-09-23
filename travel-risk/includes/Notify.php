<?php
/**
 * Notifications when the travel advice for a followed country changes.
 *
 * An hourly cron job fetches the advice for every (source, country) pair that
 * users with notifications follow, compares level/maxLevel with the previous
 * run and notifies those users by Web Push and/or e-mail. The first run for a
 * pair only records a baseline.
 *
 * Personal data (see Privacy): push subscriptions per user (endpoint + keys)
 * and the e-mail notification preference. Both are removed when switched off,
 * when a push service reports the subscription gone, and with the account.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Notify {

	const META_PUSH    = 'travel_risk_push';
	const META_EMAIL   = 'travel_risk_notify_email';
	const OPT_VAPID    = 'travel_risk_vapid';
	const OPT_SNAPSHOT = 'travel_risk_snapshots';
	const OPT_LAST     = 'travel_risk_last_check';
	const CRON         = 'travel_risk_check';
	const MAX_DEVICES  = 10;

	/** Push services browsers use. Endpoints elsewhere are refused (no requests to arbitrary hosts). */
	const PUSH_HOSTS = array( 'fcm.googleapis.com', 'android.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com' );

	const LEVELS = array(
		'en' => array( 'Unknown', 'Normal precautions', 'Exercise caution', 'Essential travel only', 'Do not travel' ),
		'de' => array( 'Unbekannt', 'Normale Vorsicht', 'Erhöhte Vorsicht', 'Nur notwendige Reisen', 'Reisewarnung' ),
		'nl' => array( 'Onbekend', 'Normale voorzorg', 'Let op', 'Alleen noodzakelijke reizen', 'Niet reizen' ),
	);

	public static function init(): void {
		add_action( self::CRON, array( self::class, 'check' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON );
	}

	// ------------------------------------------------------------------ keys

	/** VAPID key pair of this site, created on first use. */
	public static function vapid(): array {
		$keys = get_option( self::OPT_VAPID );
		if ( ! is_array( $keys ) || empty( $keys['private'] ) ) {
			$keys = WebPush::generate_vapid_keys();
			update_option( self::OPT_VAPID, $keys, false );
		}
		return $keys + array( 'subject' => 'mailto:' . get_option( 'admin_email' ) );
	}

	// ------------------------------------------------------------------ subscriptions

	/** @return array|\WP_Error Normalised subscription. */
	public static function validate( $sub ) {
		$endpoint = is_array( $sub ) ? (string) ( $sub['endpoint'] ?? '' ) : '';
		$p256dh   = is_array( $sub ) ? (string) ( $sub['keys']['p256dh'] ?? '' ) : '';
		$auth     = is_array( $sub ) ? (string) ( $sub['keys']['auth'] ?? '' ) : '';
		$host     = strtolower( (string) wp_parse_url( $endpoint, PHP_URL_HOST ) );
		$allowed  = array_filter( self::PUSH_HOSTS, fn( $h ) => $host === $h || str_ends_with( $host, '.' . $h ) );
		if ( 'https' !== wp_parse_url( $endpoint, PHP_URL_SCHEME ) || ! $allowed || strlen( $endpoint ) > 1024
			|| 65 !== strlen( WebPush::b64url_decode( $p256dh ) ) || 16 !== strlen( WebPush::b64url_decode( $auth ) ) ) {
			return new \WP_Error( 'invalid_subscription', 'invalid_subscription', array( 'status' => 400 ) );
		}
		return array( 'endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth, 'created' => gmdate( 'c' ) );
	}

	public static function devices( int $user_id ): array {
		return user_list( $user_id, self::META_PUSH );
	}

	public static function add_device( int $user_id, array $sub ): void {
		$list   = array_filter( self::devices( $user_id ), fn( $d ) => $d['endpoint'] !== $sub['endpoint'] );
		$list[] = $sub;
		update_user_meta( $user_id, self::META_PUSH, array_slice( array_values( $list ), -self::MAX_DEVICES ) );
	}

	public static function remove_device( int $user_id, string $endpoint ): void {
		$list = array_values( array_filter( self::devices( $user_id ), fn( $d ) => $d['endpoint'] !== $endpoint ) );
		$list ? update_user_meta( $user_id, self::META_PUSH, $list ) : delete_user_meta( $user_id, self::META_PUSH );
	}

	// ------------------------------------------------------------------ change detection

	public static function check(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$users = get_users( array(
			'fields'     => 'ID',
			'meta_query' => array(
				'relation' => 'OR',
				array( 'key' => self::META_PUSH, 'compare' => 'EXISTS' ),
				array( 'key' => self::META_EMAIL, 'value' => '1' ),
			),
		) );

		// (source, country) => users following it with that source.
		$pairs = array();
		foreach ( $users as $id ) {
			$source = Sources::for_language( language( get_user_meta( $id, Auth::META_LANG, true ) ) );
			foreach ( user_list( (int) $id, Auth::META_COUNTRIES ) as $iso ) {
				$pairs[ "$source:$iso" ][] = (int) $id;
			}
		}

		$old     = (array) get_option( self::OPT_SNAPSHOT, array() );
		$new     = array();
		$sources = new Sources( __NAMESPACE__ . '\\http_get' );
		foreach ( $pairs as $key => $followers ) {
			list( $source, $iso ) = explode( ':', $key );
			$country              = countries()[ $iso ] ?? null;
			if ( ! $country ) {
				continue;
			}
			try {
				$advice = $sources->advice( $source, $country );
			} catch ( SourceException $e ) {
				if ( isset( $old[ $key ] ) ) {
					$new[ $key ] = $old[ $key ]; // keep the baseline through a temporary outage
				}
				continue;
			}
			cache_put( "advice:$source:$iso", $advice );
			$new[ $key ] = array( 'level' => $advice['level'], 'maxLevel' => $advice['maxLevel'] );

			$before = $old[ $key ] ?? null;
			if ( $before && ( $before['level'] !== $advice['level'] || $before['maxLevel'] !== $advice['maxLevel'] ) ) {
				foreach ( $followers as $id ) {
					self::notify_user( $id, $country, $before, $advice );
				}
			}
		}
		update_option( self::OPT_SNAPSHOT, $new, false );
		update_option( self::OPT_LAST, time(), false );
	}

	/** @return array{title:string, body:string} */
	public static function message( string $lang, array $country, array $before, array $after ): array {
		$l    = self::LEVELS[ language( $lang ) ];
		$name = $country[ language( $lang ) ];
		$text = array(
			'en' => array( 'Travel advice changed: %1$s', '%2$s → %3$s.', ' Parts of the country: %4$s.' ),
			'de' => array( 'Reisehinweis geändert: %1$s', '%2$s → %3$s.', ' Teile des Landes: %4$s.' ),
			'nl' => array( 'Reisadvies gewijzigd: %1$s', '%2$s → %3$s.', ' Delen van het land: %4$s.' ),
		)[ language( $lang ) ];

		$from = $l[ (int) $before['level'] ];
		$to   = $l[ (int) $after['level'] ];
		if ( $from === $to ) {
			// Only the regional level moved.
			$from = $l[ (int) $before['maxLevel'] ] . ' (max)';
			$to   = $l[ (int) $after['maxLevel'] ] . ' (max)';
		}
		$body = sprintf( $text[1], $name, $from, $to );
		if ( $after['maxLevel'] > $after['level'] ) {
			$body .= sprintf( $text[2], $name, $from, $to, $l[ (int) $after['maxLevel'] ] );
		}
		return array( 'title' => sprintf( $text[0], $name ), 'body' => $body );
	}

	private static function notify_user( int $user_id, array $country, array $before, array $after ): void {
		$lang = language( get_user_meta( $user_id, Auth::META_LANG, true ) );
		$msg  = self::message( $lang, $country, $before, $after );
		self::push_user( $user_id, $msg + array( 'tag' => $country['iso3'] ) );

		if ( '1' === get_user_meta( $user_id, self::META_EMAIL, true ) ) {
			$user   = get_user_by( 'id', $user_id );
			$footer = array(
				'en' => "Open the app: %s\n\nYou receive this because e-mail notifications are on. Turn them off in the app under Notifications.",
				'de' => "App öffnen: %s\n\nSie erhalten diese Nachricht, weil E-Mail-Benachrichtigungen aktiv sind. Abschalten in der App unter Benachrichtigungen.",
				'nl' => "Open de app: %s\n\nJe ontvangt dit omdat e-mailmeldingen aan staan. Zet ze uit in de app onder Meldingen.",
			)[ $lang ];
			if ( $user ) {
				wp_mail( $user->user_email, setting( 'brand_name' ) . ': ' . $msg['title'], $msg['body'] . "\n\n" . sprintf( $footer, app_url() ) );
			}
		}
	}

	/** @return array{sent:int, failed:int} */
	public static function push_user( int $user_id, array $message ): array {
		$payload = wp_json_encode( $message + array( 'url' => Pwa::start_url() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$vapid   = self::vapid();
		$result  = array( 'sent' => 0, 'failed' => 0 );
		foreach ( self::devices( $user_id ) as $device ) {
			$req = WebPush::request( $device, $payload, $vapid );
			$res = wp_safe_remote_post( $req['url'], array( 'headers' => $req['headers'], 'body' => $req['body'], 'timeout' => 10 ) );
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			if ( $code >= 200 && $code < 300 ) {
				$result['sent']++;
				continue;
			}
			$result['failed']++;
			if ( 404 === $code || 410 === $code ) {
				// Subscription no longer exists (app removed, permission revoked).
				self::remove_device( $user_id, $device['endpoint'] );
			}
		}
		return $result;
	}
}
