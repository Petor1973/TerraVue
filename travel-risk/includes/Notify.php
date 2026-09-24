<?php
/**
 * Notifications when the travel advice for a followed country changes, and when GDACS
 * issues an orange or red disaster alert for it.
 *
 * An hourly cron job fetches the advice for every (source, country) pair that
 * users with notifications follow (each user's chosen source, see user_source()), compares level/maxLevel with the previous
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
	const OPT_ALERTS_SEEN = 'travel_risk_alerts_seen';
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
		add_action( self::CRON, fn() => self::check() );
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

	/**
	 * @return array{users:int, pairs:int, errors:int, changes:int, notified:int, alerts:int, world:int, digests:int, news:int}
	 */
	public static function check( bool $prefetch = true ): array {
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
		$pairs  = array();
		$by_iso = array(); // country => users following it (any source), for disaster alerts
		foreach ( $users as $id ) {
			$source = user_source( (int) $id );
			foreach ( user_list( (int) $id, Auth::META_COUNTRIES ) as $iso ) {
				$pairs[ "$source:$iso" ][] = (int) $id;
				$by_iso[ $iso ][]          = (int) $id;
			}
		}

		$old     = (array) get_option( self::OPT_SNAPSHOT, array() );
		$new     = array();
		$sources = sources();
		$stats   = array( 'users' => count( $users ), 'pairs' => count( $pairs ), 'errors' => 0, 'changes' => 0, 'notified' => 0, 'alerts' => 0, 'world' => 0, 'digests' => 0, 'news' => 0 );
		foreach ( $pairs as $key => $followers ) {
			list( $source, $iso ) = explode( ':', $key );
			$country              = countries()[ $iso ] ?? null;
			if ( ! $country ) {
				continue;
			}
			try {
				$advice = $sources->advice( $source, $country );
			} catch ( SourceException $e ) {
				$stats['errors']++;
				if ( isset( $old[ $key ] ) ) {
					$new[ $key ] = $old[ $key ]; // keep the baseline through a temporary outage
				}
				continue;
			}
			cache_put( "advice:$source:$iso", $advice );
			$new[ $key ] = array( 'level' => $advice['level'], 'maxLevel' => $advice['maxLevel'] );

			$before = $old[ $key ] ?? null;
			if ( $before && ( $before['level'] !== $advice['level'] || $before['maxLevel'] !== $advice['maxLevel'] ) ) {
				$stats['changes']++;
				foreach ( $followers as $id ) {
					self::notify_user( $id, $country, $before, $advice );
					$stats['notified']++;
				}
			}
		}
		update_option( self::OPT_SNAPSHOT, $new, false );
		update_option( self::OPT_LAST, time(), false );
		$stats['alerts'] = self::check_alerts( $by_iso );
		$world            = World::collect();
		$stats['world']   = $world['events'];
		$stats['digests'] = World::send_digests();

		$isos           = array_unique( array_map( fn( $k ) => explode( ':', $k )[1], array_keys( $pairs ) ) );
		$stats['news']  = $prefetch ? self::prefetch_news( $isos ) : 0;
		update_option( self::OPT_LAST_STATS, $stats, false );
		return $stats;
	}

	/**
	 * Orange and red GDACS alerts for followed countries. Each user hears about an event once,
	 * and again if it escalates (orange → red). The first run only records what is current,
	 * like the advice baseline. Green alerts (minor) never notify.
	 *
	 * @param array<string,int[]> $by_iso Country => user IDs following it.
	 * @return int Notifications sent.
	 */
	public static function check_alerts( array $by_iso ): int {
		if ( ! setting( 'alerts' ) ) {
			return 0;
		}
		try {
			$events = disaster_alerts();
		} catch ( SourceException $e ) {
			return 0; // keep what we have seen; tried again next hour
		}
		$seen  = get_option( self::OPT_ALERTS_SEEN, null );
		$first = ! is_array( $seen );
		$seen  = (array) $seen;
		$now   = array();
		$todo  = array(); // user => event id => [event, country]
		foreach ( $events as $event ) {
			$rank = Alerts::LEVELS[ $event['level'] ];
			if ( $rank < Alerts::LEVELS['orange'] ) {
				continue;
			}
			$before              = (int) ( $seen[ $event['id'] ] ?? 0 );
			$now[ $event['id'] ] = max( $rank, $before );
			if ( $first || $before >= $rank ) {
				continue;
			}
			foreach ( $event['countries'] as $iso ) {
				foreach ( array_unique( $by_iso[ $iso ] ?? array() ) as $user ) {
					$todo[ $user ][ $event['id'] ] = $todo[ $user ][ $event['id'] ] ?? array( $event, countries()[ $iso ] );
				}
			}
		}
		update_option( self::OPT_ALERTS_SEEN, $now, false );

		$sent = 0;
		foreach ( $todo as $user => $items ) {
			$lang = language( get_user_meta( $user, Auth::META_LANG, true ) );
			foreach ( $items as $id => list( $event, $country ) ) {
				self::send( $user, self::alert_message( $lang, $country, $event ), 'gdacs-' . $id );
				$sent++;
			}
		}
		return $sent;
	}

	/** @return array{title:string, body:string} The GDACS text itself is in English. */
	public static function alert_message( string $lang, array $country, array $event ): array {
		$title = array(
			'en' => 'Disaster alert: %s',
			'de' => 'Katastrophenwarnung: %s',
			'nl' => 'Rampenmelding: %s',
		)[ language( $lang ) ];
		return array( 'title' => sprintf( $title, $country[ language( $lang ) ] ), 'body' => $event['title'] . ' (GDACS)' );
	}

	const OPT_LAST_STATS = 'travel_risk_last_stats';
	const PREFETCH_MAX   = 30; // GDELT allows one call per ~6 s; keeps the hourly run under a few minutes

	/** Warms the news cache for followed countries; returns how many were fetched. */
	public static function prefetch_news( array $isos ): int {
		if ( 'none' === setting( 'news_provider' ) ) {
			return 0;
		}
		$done = 0;
		foreach ( array_slice( array_values( $isos ), 0, self::PREFETCH_MAX ) as $iso ) {
			$country = countries()[ $iso ] ?? null;
			if ( ! $country ) {
				continue;
			}
			try {
				Rest::fetch_news( $country );
				$done++;
			} catch ( SourceException $e ) {
				continue; // tried again next hour; visitors can still trigger a fetch
			}
		}
		return $done;
	}

	/**
	 * Test tool: sends a sample "advice changed" notification (push + e-mail if on) to one user,
	 * marked as a test, without touching the baseline or other users.
	 *
	 * @return array{push:array{sent:int,failed:int}, email:bool}
	 */
	public static function simulate( int $user_id, array $country ): array {
		$lang   = language( get_user_meta( $user_id, Auth::META_LANG, true ) );
		$msg    = self::message( $lang, $country, array( 'level' => 2, 'maxLevel' => 3 ), array( 'level' => 3, 'maxLevel' => 4 ) );
		$prefix = array( 'en' => '[Test] ', 'de' => '[Test] ', 'nl' => '[Test] ' )[ $lang ];
		$msg['title'] = $prefix . $msg['title'];
		return self::notify_user( $user_id, $country, array( 'level' => 2, 'maxLevel' => 3 ), array( 'level' => 3, 'maxLevel' => 4 ), $msg );
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

	/** @return array{push:array{sent:int,failed:int}, email:bool} */
	private static function notify_user( int $user_id, array $country, array $before, array $after, ?array $msg = null ): array {
		$lang = language( get_user_meta( $user_id, Auth::META_LANG, true ) );
		return self::send( $user_id, $msg ?? self::message( $lang, $country, $before, $after ), $country['iso3'] );
	}

	/**
	 * Push to all of the user's devices, plus e-mail when that is on.
	 *
	 * @param array  $msg title, body; optional mail (longer e-mail text), url and tab (app screen to open).
	 * @param string $tag Notifications with the same tag replace each other on the device.
	 * @return array{push:array{sent:int,failed:int}, email:bool}
	 */
	public static function send( int $user_id, array $msg, string $tag ): array {
		$lang   = language( get_user_meta( $user_id, Auth::META_LANG, true ) );
		$push   = array_intersect_key( $msg, array_flip( array( 'title', 'body', 'url', 'tab' ) ) ); // stays well under the 4 KB push limit
		$result = array( 'push' => self::push_user( $user_id, $push + array( 'tag' => $tag ) ), 'email' => false );

		if ( '1' === get_user_meta( $user_id, self::META_EMAIL, true ) ) {
			$user   = get_user_by( 'id', $user_id );
			$footer = array(
				'en' => "Open the app: %s\n\nYou receive this because e-mail notifications are on. Turn them off in the app under Notifications.",
				'de' => "App öffnen: %s\n\nSie erhalten diese Nachricht, weil E-Mail-Benachrichtigungen aktiv sind. Abschalten in der App unter Benachrichtigungen.",
				'nl' => "Open de app: %s\n\nJe ontvangt dit omdat e-mailmeldingen aan staan. Zet ze uit in de app onder Meldingen.",
			)[ $lang ];
			if ( $user ) {
				$result['email'] = (bool) wp_mail( $user->user_email, setting( 'brand_name' ) . ': ' . $msg['title'], ( $msg['mail'] ?? $msg['body'] ) . "\n\n" . sprintf( $footer, $msg['url'] ?? app_url() ) );
			}
		}
		return $result;
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
