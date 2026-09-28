<?php
/**
 * Daily world overview: changes in the travel advice of all governments for all countries,
 * plus new orange/red GDACS alerts, as a log (tab "Changes" in the app) and as an optional
 * daily digest per user (push and/or e-mail at a chosen hour).
 *
 * Collection runs inside the hourly check. Whole feeds (US, Canada) cover every country with
 * one request; the per-country sources (NL, UK, DE) are spread over the day, PER_RUN requests
 * per run, each pair re-checked after RECHECK seconds. The first reading of a pair is only a
 * baseline, like the notifications. The log is independent of what users follow.
 *
 * Personal data: the chosen digest hour (UTC) and when the last digest was sent (see Privacy).
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class World {

	const OPT_STATE        = 'travel_risk_world';        // started + last reading per source:country
	const OPT_LOG          = 'travel_risk_world_log';    // change events, newest last
	const OPT_ALERTS       = 'travel_risk_world_alerts'; // GDACS event id => highest level seen
	const META_DIGEST      = 'travel_risk_digest';       // UTC hour 0..23 of the daily overview
	const META_DIGEST_SENT = 'travel_risk_digest_sent';  // unix time of the last overview
	const KEEP_DAYS        = 14;
	const MAX_EVENTS       = 3000;
	const RECHECK          = 72000; // 20 h
	const PER_RUN          = 40;
	const FEED_SOURCES     = array( 'usdos', 'gac' );

	// ------------------------------------------------------------------ collection

	/**
	 * @param string[]|null $isos  Limit to these countries (tests); default all.
	 * @param bool          $force Re-check pairs checked less than RECHECK ago.
	 * @return array{checked:int, errors:int, events:int}
	 */
	public static function collect( ?array $isos = null, bool $force = false ): array {
		$state   = self::state();
		$now     = time();
		$budget  = self::PER_RUN;
		$events  = array();
		$stats   = array( 'checked' => 0, 'errors' => 0, 'events' => 0 );
		$sources = sources();
		foreach ( array_keys( Sources::NAMES ) as $source ) {
			foreach ( $isos ?? array_keys( countries() ) as $iso ) {
				$country = countries()[ $iso ] ?? null;
				$key     = "$source:$iso";
				$prev    = $state['pairs'][ $key ] ?? null;
				if ( ! $country || Sources::HOME[ $source ] === $iso || ( ! $force && $prev && $now - $prev['c'] < self::RECHECK ) ) {
					continue;
				}
				if ( ! in_array( $source, self::FEED_SOURCES, true ) ) {
					if ( $budget <= 0 ) {
						continue;
					}
					$budget--;
				}
				try {
					$a = cached( "advice:$source:$iso", fn() => $sources->advice( $source, $country ) );
				} catch ( SourceException $e ) {
					$stats['errors']++;
					$state['pairs'][ $key ] = array( 'c' => $now ) + (array) $prev; // keep the last reading
					continue;
				}
				$stats['checked']++;
				$cur = array( 'l' => $a['level'], 'm' => $a['maxLevel'], 'u' => $a['updated'] ?? null, 'c' => $now );
				if ( $prev && array_key_exists( 'l', $prev ) ) {
					if ( $prev['l'] !== $cur['l'] || $prev['m'] !== $cur['m'] ) {
						$events[] = array( 'k' => 'level', 's' => $source, 'c' => $iso, 'f' => array( $prev['l'], $prev['m'] ), 'n' => array( $cur['l'], $cur['m'] ), 'x' => $a['latest'] ?? null );
					} elseif ( $cur['u'] && $prev['u'] && $cur['u'] !== $prev['u'] ) {
						$events[] = array( 'k' => 'updated', 's' => $source, 'c' => $iso, 'x' => $a['latest'] ?? null );
					}
				}
				$state['pairs'][ $key ] = $cur;
			}
		}
		update_option( self::OPT_STATE, $state, false );

		$events          = array_merge( $events, self::new_alerts() );
		$stats['events'] = count( $events );
		self::log( $events );
		return $stats;
	}

	/** New orange/red GDACS events worldwide (and escalations); the first run is a baseline. */
	private static function new_alerts(): array {
		if ( ! setting( 'alerts' ) ) {
			return array();
		}
		try {
			$alerts = disaster_alerts();
		} catch ( SourceException $e ) {
			return array();
		}
		$seen   = get_option( self::OPT_ALERTS, null );
		$first  = ! is_array( $seen );
		$seen   = (array) $seen;
		$now    = array();
		$events = array();
		foreach ( $alerts as $a ) {
			$rank = Alerts::LEVELS[ $a['level'] ];
			if ( $rank < Alerts::LEVELS['orange'] ) {
				continue;
			}
			$now[ $a['id'] ] = max( $rank, (int) ( $seen[ $a['id'] ] ?? 0 ) );
			if ( ! $first && (int) ( $seen[ $a['id'] ] ?? 0 ) < $rank ) {
				$events[] = array( 'k' => 'alert', 'cs' => $a['countries'], 'lv' => $a['level'], 'ty' => $a['type'], 'x' => $a['title'], 'u' => $a['url'] );
			}
		}
		update_option( self::OPT_ALERTS, $now, false );
		return $events;
	}

	private static function state(): array {
		$state = get_option( self::OPT_STATE );
		return is_array( $state ) && isset( $state['pairs'] ) ? $state : array( 'started' => time(), 'pairs' => array() );
	}

	private static function log( array $events ): void {
		$now = time();
		$log = array_filter( (array) get_option( self::OPT_LOG, array() ), fn( $e ) => $e['t'] >= $now - self::KEEP_DAYS * DAY_IN_SECONDS );
		foreach ( $events as $e ) {
			$log[] = array( 't' => $now ) + $e;
		}
		update_option( self::OPT_LOG, array_slice( array_values( $log ), -self::MAX_EVENTS ), false );
	}

	// ------------------------------------------------------------------ reading

	/** When collection started (ISO), for "the overview is still building up". */
	public static function started(): ?string {
		$state = get_option( self::OPT_STATE );
		return is_array( $state ) && ! empty( $state['started'] ) ? gmdate( 'c', (int) $state['started'] ) : null;
	}

	/** Number of source/country pairs with a reading. */
	public static function known(): int {
		$state = get_option( self::OPT_STATE );
		return is_array( $state ) ? count( array_filter( (array) ( $state['pairs'] ?? array() ), fn( $p ) => array_key_exists( 'l', $p ) ) ) : 0;
	}

	/**
	 * Events newer than $since, newest first. With $source: that government's advice only
	 * (disaster alerts are always included).
	 */
	public static function events_since( int $since, ?string $source = null ): array {
		$out = array();
		foreach ( array_reverse( (array) get_option( self::OPT_LOG, array() ) ) as $e ) {
			if ( $e['t'] <= $since ) {
				break;
			}
			if ( $source && 'alert' !== $e['k'] && $e['s'] !== $source ) {
				continue;
			}
			$out[] = array_filter( array(
				'time'      => gmdate( 'c', $e['t'] ),
				'kind'      => $e['k'],
				'source'    => $e['s'] ?? null,
				'countries' => $e['cs'] ?? array( $e['c'] ),
				'from'      => $e['f'] ?? null,
				'to'        => $e['n'] ?? null,
				'note'      => $e['x'] ?? null,
				'level'     => $e['lv'] ?? null,
				'type'      => $e['ty'] ?? null,
				'url'       => $e['u'] ?? null,
			), fn( $v ) => null !== $v );
		}
		return $out;
	}

	public static function events( int $days, ?string $source = null ): array {
		return self::events_since( time() - $days * DAY_IN_SECONDS, $source );
	}

	// ------------------------------------------------------------------ daily digest

	/**
	 * Sends the daily overview to users whose chosen hour (UTC) has come and who have not had
	 * one today. Uses the same channels as the other notifications (push devices, e-mail if on).
	 *
	 * @param int|null $user  Only this user, now, regardless of hour (test tool).
	 * @return int Overviews sent.
	 */
	public static function send_digests( ?int $user = null ): int {
		$ids = $user ? array( $user ) : get_users( array(
			'fields'     => 'ID',
			'meta_query' => array( array( 'key' => self::META_DIGEST, 'compare' => 'EXISTS' ) ),
		) );
		$hour  = (int) gmdate( 'G' );
		$today = gmdate( 'Y-m-d' );
		$sent  = 0;
		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$last = (int) get_user_meta( $id, self::META_DIGEST_SENT, true );
			if ( ! $user && ( $hour < (int) get_user_meta( $id, self::META_DIGEST, true ) || ( $last && gmdate( 'Y-m-d', $last ) === $today ) ) ) {
				continue;
			}
			// Since the last overview (at most a week back), or the last 24 hours for the first one.
			$since = $last ? max( $last, time() - 7 * DAY_IN_SECONDS ) : time() - DAY_IN_SECONDS;
			$items = self::events_since( $since, user_source( $id ) );
			update_user_meta( $id, self::META_DIGEST_SENT, time() );
			if ( ! $items && ! $user && ! setting( 'digest_empty' ) ) {
				continue; // nothing changed: no message (unless the admin test setting is on)
			}
			$lang = language( get_user_meta( $id, Auth::META_LANG, true ) );
			Notify::send( $id, self::message( $lang, $items, user_source( $id ) ), 'digest' );
			$sent++;
		}
		return $sent;
	}

	const TEXT = array(
		'en' => array(
			'title'   => 'World overview: %d changes',
			'title1'  => 'World overview: 1 change',
			'none'    => 'World overview: no changes',
			'nobody'  => 'No changes in the travel advice of %s and no new orange or red disaster alerts since the last overview.',
			'intro'   => 'Changes in the travel advice of %s and new disaster alerts since the last overview:',
			'updated' => 'advice updated',
			'more'    => '+%d more',
		),
		'de' => array(
			'title'   => 'Weltüberblick: %d Änderungen',
			'title1'  => 'Weltüberblick: 1 Änderung',
			'none'    => 'Weltüberblick: keine Änderungen',
			'nobody'  => 'Keine Änderungen in den Reisehinweisen von %s und keine neuen orangen oder roten Katastrophenwarnungen seit dem letzten Überblick.',
			'intro'   => 'Änderungen in den Reisehinweisen von %s und neue Katastrophenwarnungen seit dem letzten Überblick:',
			'updated' => 'Hinweis aktualisiert',
			'more'    => '+%d weitere',
		),
		'nl' => array(
			'title'   => 'Wereldoverzicht: %d wijzigingen',
			'title1'  => 'Wereldoverzicht: 1 wijziging',
			'none'    => 'Wereldoverzicht: geen wijzigingen',
			'nobody'  => 'Geen wijzigingen in het reisadvies van %s en geen nieuwe oranje of rode rampenmeldingen sinds het vorige overzicht.',
			'intro'   => 'Wijzigingen in het reisadvies van %s en nieuwe rampenmeldingen sinds het vorige overzicht:',
			'updated' => 'advies bijgewerkt',
			'more'    => '+%d meer',
		),
	);

	/**
	 * Push: a short summary (level changes first, then alerts, then updates); e-mail: every line.
	 *
	 * @return array{title:string, body:string, mail:string, url:string}
	 */
	public static function message( string $lang, array $items, string $source ): array {
		$lang  = language( $lang );
		$text  = self::TEXT[ $lang ];
		$l     = Notify::LEVELS[ $lang ];
		$name  = fn( $iso ) => countries()[ $iso ][ $lang ] ?? $iso;
		$order = array( 'level' => 0, 'alert' => 1, 'updated' => 2 );
		usort( $items, fn( $a, $b ) => $order[ $a['kind'] ] <=> $order[ $b['kind'] ] );

		$lines = array();
		$long  = array();
		foreach ( $items as $e ) {
			$where = implode( ', ', array_map( $name, $e['countries'] ) );
			if ( 'level' === $e['kind'] ) {
				$same    = $e['from'][0] === $e['to'][0];
				$line    = $where . ': ' . ( $same ? $l[ (int) $e['from'][1] ] . ' (max) → ' . $l[ (int) $e['to'][1] ] . ' (max)' : $l[ (int) $e['from'][0] ] . ' → ' . $l[ (int) $e['to'][0] ] );
				$lines[] = $line;
				$long[]  = $line . ( ! empty( $e['note'] ) ? ' — ' . $e['note'] : '' );
			} elseif ( 'alert' === $e['kind'] ) {
				$lines[] = $where . ': ' . $e['note'];
				$long[]  = $where . ': ' . $e['note'] . ' (GDACS) ' . ( $e['url'] ?? '' );
			} else {
				$lines[] = $where . ': ' . $text['updated'];
				$long[]  = $where . ': ' . $text['updated'] . ( ! empty( $e['note'] ) ? ' — ' . $e['note'] : '' );
			}
		}
		$source_name = Sources::NAMES[ $source ] ?? $source;
		$body        = $lines ? implode( '; ', array_slice( $lines, 0, 3 ) ) . ( count( $lines ) > 3 ? ' ' . sprintf( $text['more'], count( $lines ) - 3 ) : '' ) : sprintf( $text['nobody'], $source_name );
		return array(
			'title' => ! $lines ? $text['none'] : ( 1 === count( $lines ) ? $text['title1'] : sprintf( $text['title'], count( $lines ) ) ),
			'body'  => $body,
			'mail'  => $lines ? sprintf( $text['intro'], $source_name ) . "\n\n- " . implode( "\n- ", $long ) : $body,
			'url'   => Pwa::start_url() . '#tr-tab=changes',
			'tab'   => 'changes',
		);
	}
}
