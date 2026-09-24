<?php
/**
 * Disaster alerts from GDACS (Global Disaster Alert and Coordination System, a cooperation
 * of the European Commission and the United Nations): earthquakes, tropical cyclones,
 * floods, volcanoes, droughts, wildfires, tsunamis.
 *
 * One public RSS feed with all current events; the server fetches it, users send nothing.
 * Alert levels: Green (minor), Orange (moderate), Red (severe humanitarian impact).
 *
 * No WordPress dependency (parsed with fixtures in tests/unit.php).
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || defined( 'TRAVEL_RISK_TESTING' ) || exit;

class Alerts {

	const FEED = 'https://www.gdacs.org/xml/rss.xml';

	const ATTRIBUTION = 'Disaster alerts: GDACS, Global Disaster Alert and Coordination System (European Commission and United Nations), gdacs.org.';

	/** Events that ended longer ago are dropped. */
	const RECENT_DAYS = 7;

	/** Green alerts are minor and frequent: only recent ones, and no (long-running) droughts. */
	const GREEN_HOURS = 72;

	const CACHE_MINUTES = 30;

	const LEVELS = array( 'green' => 1, 'orange' => 2, 'red' => 3 );

	/**
	 * @param array $countries ISO3 => entry from data/countries.json.
	 * @return array<int,array{id:string,type:string,level:string,title:string,url:string,from:?string,to:?string,severity:string,countries:string[]}>
	 *         Most severe first, then most recent.
	 */
	public static function parse( string $xml, array $countries, int $now ): array {
		if ( false === strpos( $xml, '<item' ) && false !== strpos( $xml, '<channel' ) ) {
			return array(); // a quiet day
		}
		$events = array();
		foreach ( Sources::rss_items( $xml ) as $item ) {
			$raw   = $item['raw'];
			$level = strtolower( (string) self::field( $raw, 'alertlevel' ) );
			if ( ! isset( self::LEVELS[ $level ] ) ) {
				continue;
			}
			$type    = strtoupper( (string) self::field( $raw, 'eventtype' ) );
			$to      = strtotime( (string) ( self::field( $raw, 'todate' ) ?? $item['pubDate'] ) ) ?: null;
			$from    = strtotime( (string) self::field( $raw, 'fromdate' ) ) ?: $to;
			$current = 'true' === strtolower( (string) self::field( $raw, 'iscurrent' ) );
			if ( ! $current && ( ! $to || $to < $now - self::RECENT_DAYS * 86400 ) ) {
				continue;
			}
			if ( 'green' === $level && ( 'DR' === $type || ! $to || $to < $now - self::GREEN_HOURS * 3600 ) ) {
				continue;
			}
			$isos = self::countries( $raw, $countries );
			if ( ! $isos ) {
				continue; // at sea, or a country we do not list
			}
			$events[] = array(
				'id'        => $type . ( self::field( $raw, 'eventid' ) ?? md5( $item['link'] ) ),
				'type'      => $type,
				'level'     => $level,
				'title'     => $item['title'],
				'url'       => $item['link'],
				'from'      => $from ? gmdate( 'c', $from ) : null,
				'to'        => $to ? gmdate( 'c', $to ) : null,
				'severity'  => Sources::text( (string) self::field( $raw, 'severity' ) ),
				'countries' => $isos,
			);
		}
		usort( $events, fn( $a, $b ) => self::LEVELS[ $b['level'] ] <=> self::LEVELS[ $a['level'] ] ?: strcmp( (string) $b['to'], (string) $a['to'] ) );
		return $events;
	}

	/** Events for one country. */
	public static function for_country( array $events, string $iso3 ): array {
		return array_values( array_filter( $events, fn( $e ) => in_array( $iso3, $e['countries'], true ) ) );
	}

	/**
	 * gdacs:iso3 holds one code, also for events in several countries ("Drought is on going in
	 * Bulgaria, Iraq, Iran, Turkey" has BGR); gdacs:country lists all names.
	 */
	private static function countries( string $raw, array $countries ): array {
		$found = array();
		foreach ( preg_split( '/[^A-Z]+/', strtoupper( (string) self::field( $raw, 'iso3' ) ) ) as $iso ) {
			if ( isset( $countries[ $iso ] ) ) {
				$found[ $iso ] = true;
			}
		}
		$names = Sources::text( (string) self::field( $raw, 'country' ) );
		if ( '' !== $names ) {
			foreach ( $countries as $iso => $country ) {
				if ( ! isset( $found[ $iso ] ) && Sources::name_matches( $names, $country ) ) {
					$found[ $iso ] = true;
				}
			}
		}
		return array_keys( $found );
	}

	private static function field( string $raw, string $tag ): ?string {
		return preg_match( '/<gdacs:' . $tag . '\b[^>]*>([\s\S]*?)<\/gdacs:' . $tag . '>/', $raw, $m ) ? trim( $m[1] ) : null;
	}
}
