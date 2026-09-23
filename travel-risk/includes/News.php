<?php
/**
 * Recent security-related news per country.
 *
 * Providers:
 *   gdelt  GDELT DOC 2.0 API (open data, attribution requested). Default.
 *   google Google News RSS. Only for personal / non-commercial use; see readme.
 *   none   News disabled.
 *
 * Like Sources, HTTP is injected so parsing can be tested without WordPress.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || defined( 'TRAVEL_RISK_TESTING' ) || exit;

class News {

	const GDELT = 'https://api.gdeltproject.org/api/v2/doc/doc';

	const KEYWORDS = array( 'attack', 'drone', 'missile', 'explosion', 'protest', 'strike', 'riot',
		'shooting', 'terror', 'earthquake', 'flood', 'storm', 'wildfire', 'evacuation', 'curfew',
		'kidnapping', 'coup', 'unrest' );

	/** Titles matching this are almost never about safety on the ground. */
	const NOISE = '/\b(football|soccer|cricket|rugby|tennis|golf|nba|nfl|f1|grand prix|league|cup|olympic|match|goal|striker|squad|coach|film|movie|album|box office|celebrity|stock|shares|earnings)\b/i';

	/** @var callable */
	private $http;

	public function __construct( callable $http ) {
		$this->http = $http;
	}

	public function fetch( string $provider, array $country, int $hours ): array {
		$items = 'google' === $provider
			? $this->google( $country, $hours )
			: $this->gdelt( $country, $hours );
		return array(
			'provider' => $provider,
			'items'    => self::clean( $items ),
		);
	}

	public function gdelt_url( array $country, int $hours ): string {
		$query = sprintf( '"%s" (%s) sourcelang:english', $country['en'], implode( ' OR ', self::KEYWORDS ) );
		return self::GDELT . '?' . http_build_query(
			array(
				'query'      => $query,
				'mode'       => 'artlist',
				'format'     => 'json',
				'maxrecords' => 25,
				'sort'       => 'datedesc',
				'timespan'   => $hours . 'h',
			)
		);
	}

	private function gdelt( array $country, int $hours ): array {
		$res = ( $this->http )( $this->gdelt_url( $country, $hours ) );
		if ( 429 === $res['status'] ) {
			throw new SourceException( 'rate_limited' );
		}
		return self::parse_gdelt( $res['body'], $res['status'] );
	}

	public static function parse_gdelt( string $body, int $status = 200 ): array {
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			// GDELT answers errors as plain text, often with HTTP 200.
			throw new SourceException( 'http_' . $status );
		}
		return array_map(
			fn( $a ) => array(
				'title'  => (string) ( $a['title'] ?? '' ),
				'url'    => (string) ( $a['url'] ?? '' ),
				'source' => (string) ( $a['domain'] ?? '' ),
				'date'   => preg_match( '/^(\d{4})(\d\d)(\d\d)T(\d\d)(\d\d)(\d\d)Z$/', $a['seendate'] ?? '', $m )
					? "$m[1]-$m[2]-$m[3]T$m[4]:$m[5]:$m[6]Z" : null,
			),
			(array) ( $data['articles'] ?? array() )
		);
	}

	private function google( array $country, int $hours ): array {
		$q   = sprintf( '"%s" (%s) when:%dd', $country['en'], implode( ' OR ', self::KEYWORDS ), (int) ceil( $hours / 24 ) );
		$url = 'https://news.google.com/rss/search?' . http_build_query(
			array( 'q' => $q, 'hl' => 'en-US', 'gl' => 'US', 'ceid' => 'US:en' )
		);
		$res = ( $this->http )( $url );
		if ( $res['status'] < 200 || $res['status'] >= 300 ) {
			throw new SourceException( 'http_' . $res['status'] );
		}
		return self::parse_rss( $res['body'] );
	}

	public static function parse_rss( string $xml ): array {
		preg_match_all( '/<item>([\s\S]*?)<\/item>/', $xml, $m );
		return array_map(
			function ( $item ) {
				$pub = Sources::xml_field( $item, 'pubDate' );
				return array(
					'title'  => Sources::text( Sources::xml_field( $item, 'title' ) ?? '' ),
					'url'    => Sources::xml_field( $item, 'link' ) ?? '',
					'source' => preg_match( '/<source[^>]*>([\s\S]*?)<\/source>/', $item, $s ) ? Sources::text( $s[1] ) : 'Google News',
					'date'   => $pub && strtotime( $pub ) ? gmdate( 'c', strtotime( $pub ) ) : null,
				);
			},
			$m[1]
		);
	}

	/** Drops empty, duplicate, non-http and obviously irrelevant items; max 8. */
	public static function clean( array $items ): array {
		$seen = array();
		$out  = array();
		foreach ( $items as $item ) {
			$key = mb_strtolower( trim( $item['title'] ) );
			if ( '' === $key || isset( $seen[ $key ] ) || ! preg_match( '#^https?://#i', $item['url'] ) ) {
				continue;
			}
			if ( preg_match( self::NOISE, $item['title'] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $item;
			if ( count( $out ) >= 8 ) {
				break;
			}
		}
		return $out;
	}
}
