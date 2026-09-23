<?php
/**
 * Official travel advice sources, normalised to one four-level scale.
 *
 *   1 = normal precautions (green)   2 = caution (yellow)
 *   3 = essential travel only (orange)   4 = do not travel (red)
 *
 * Every adapter returns:
 *   [ source, level, maxLevel, summary, url, updated ]
 * where `level` applies to most of the country and `maxLevel` is the strictest
 * level anywhere in the country (regional warnings).
 *
 * No WordPress dependency: HTTP is injected as a callable
 *   fn(string $url): array{status:int, body:string}
 * so parsers can be unit-tested with fixtures.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || defined( 'TRAVEL_RISK_TESTING' ) || exit;

class SourceException extends \RuntimeException {}

class Sources {

	const BUZA = 'https://opendata.nederlandwereldwijd.nl/v2/sources/nederlandwereldwijd/infotypes/countries';
	const FCDO = 'https://www.gov.uk/api/content/foreign-travel-advice';
	const AA   = 'https://www.auswaertiges-amt.de/opendata/travelwarning';
	// The State Department's JSON API (cadataapi) has returned an empty list since
	// Sept 2026; the RSS feed carries one item per destination with the level.
	const USDOS = 'https://travel.state.gov/_res/rss/TAsTWs.xml';
	const GAC   = 'https://data.international.gc.ca/travel-voyage/index-alpha-eng.json';
	const DFAT  = 'https://www.smartraveller.gov.au/countries/documents/index.rss';

	/** Default source for a UI language, when the user has not chosen one. */
	const BY_LANGUAGE = array(
		'nl' => 'buza',
		'en' => 'fcdo',
		'de' => 'aa',
	);

	const NAMES = array(
		'buza'  => 'Ministerie van Buitenlandse Zaken (NL)',
		'fcdo'  => 'Foreign, Commonwealth & Development Office (UK)',
		'aa'    => 'Auswärtiges Amt (DE)',
		'usdos' => 'U.S. Department of State (US)',
		'gac'   => 'Global Affairs Canada (CA)',
		'dfat'  => 'Smartraveller, DFAT (AU)',
	);

	/** Each government publishes no advice for its own country. */
	const HOME = array( 'buza' => 'NLD', 'fcdo' => 'GBR', 'aa' => 'DEU', 'usdos' => 'USA', 'gac' => 'CAN', 'dfat' => 'AUS' );

	/** Licence / attribution per source, shown in the app footer and the readme. */
	const ATTRIBUTION = array(
		'buza'  => 'Reisadviezen: Ministerie van Buitenlandse Zaken, open data (opendata.nederlandwereldwijd.nl).',
		'fcdo'  => 'Contains public sector information licensed under the Open Government Licence v3.0 (FCDO, GOV.UK).',
		'aa'    => 'Reise- und Sicherheitshinweise: Auswärtiges Amt, Open-Data-Schnittstelle.',
		'usdos' => 'Travel advisories: U.S. Department of State, Bureau of Consular Affairs (public domain).',
		'gac'   => 'Contains information licensed under the Open Government Licence – Canada (Global Affairs Canada).',
		'dfat'  => 'Travel advice: Smartraveller, Australian Government Department of Foreign Affairs and Trade.',
	);

	/** @var callable */
	private $http;

	/** @var callable|null fn(string $key, callable $produce) — caches whole feeds between requests. */
	private $cache;

	/** Feeds already fetched by this instance (one cron run checks many countries). */
	private $feeds = array();

	public function __construct( callable $http, ?callable $cache = null ) {
		$this->http  = $http;
		$this->cache = $cache;
	}

	public static function for_language( string $lang ): string {
		return self::BY_LANGUAGE[ $lang ] ?? 'fcdo';
	}

	public static function valid( $source ): bool {
		return is_string( $source ) && isset( self::NAMES[ $source ] );
	}

	/**
	 * @param array $country Entry from data/countries.json.
	 */
	public function advice( string $source, array $country ): array {
		if ( ( self::HOME[ $source ] ?? '' ) === $country['iso3'] ) {
			throw new SourceException( 'no_home_advice' );
		}
		switch ( $source ) {
			case 'usdos':
				return self::parse_usdos( $this->feed( self::USDOS ), $country );
			case 'gac':
				return self::parse_gac( $this->feed( self::GAC ), $country );
			case 'dfat':
				return self::parse_dfat( $this->feed( self::DFAT ), $country );
			case 'buza':
				return $this->buza( $country );
			case 'aa':
				return $this->aa( $country );
			default:
				return $this->fcdo( $country );
		}
	}

	// ------------------------------------------------------------------
	// Netherlands: Ministerie van Buitenlandse Zaken (open data, XML).
	// The colour code is only present in running text, so it is parsed.
	// ------------------------------------------------------------------

	private function buza( array $country ): array {
		$res = $this->get( self::BUZA . '/' . strtolower( $country['iso3'] ) . '/traveladvice' );
		if ( 404 === $res['status'] ) {
			$res = $this->get( self::BUZA . '/' . self::slug( $country['nl'] ) . '/traveladvice' );
		}
		self::expect_ok( $res );
		return self::parse_buza( $res['body'] );
	}

	public static function parse_buza( string $xml ): array {
		$intro = self::text( self::xml_field( $xml, 'introduction' ) ?? '' );
		if ( '' === $intro ) {
			throw new SourceException( 'unexpected_response' );
		}
		list( $level, $max, $basis ) = self::buza_levels( $intro );
		return array(
			'source'   => 'buza',
			'level'    => $level,
			'maxLevel' => $max,
			'basis'    => $basis,
			'summary'  => self::shorten( $intro ),
			'url'      => self::xml_field( $xml, 'canonical' ),
			'updated'  => self::iso_date( self::xml_field( $xml, 'lastmodified' ) ),
		);
	}

	/**
	 * Works sentence by sentence.
	 *  - A sentence with explicit country-wide wording ("grootste deel", "rest van het land")
	 *    decides the level.
	 *  - Otherwise the first colour sentence that names no region decides it. Regions are
	 *    recognised by a preposition + area noun ("in het noorden", "op de eilanden") and by
	 *    border/strip words, also inside compounds ("grensgebieden", "Gazastrook") and
	 *    "tussen X en Y".
	 *  - Only regional statements: the mildest one is the best guess for the rest.
	 *
	 * @return array{0:?int,1:?int,2:string} [level, maxLevel, deciding sentence]
	 */
	public static function buza_levels( string $text ): array {
		$colours  = array( 'groen' => 1, 'geel' => 2, 'oranje' => 3, 'rood' => 4 );
		$colour   = '/\b(groen|geel|oranje|rood)\b/iu';
		$national = '/grootste deel|meeste gebieden|hele land|gehele land|rest van (het land|\p{Lu})|overige delen|overal in/iu';
		$regional = array(
			'/\b(voor|in|op|langs|rond|binnen|nabij)\s+(de|het|een)?\s*(\w+\s+)?(strook|gebied|gebieden|grens|regio|regio\'s|provincie|provincies|eiland|eilanden|noorden|zuiden|oosten|westen|noordoosten|noordwesten|zuidoosten|zuidwesten|deel|delen|stad|steden|kust|departement|departementen|staat|staten|district|districten)\b/iu',
			'/\b\w*(strook|grens|grenzen|grensgebied|grensgebieden|grensstreek|grensregio|kilometer)\b/iu',
			'/\btussen\s+\p{Lu}\w*\s+en\s+/u',
		);
		$is_regional = function ( string $sentence ) use ( $regional ) {
			foreach ( $regional as $re ) {
				if ( preg_match( $re, $sentence ) ) {
					return true;
				}
			}
			return false;
		};

		$explicit = null;
		$plain    = null;
		$local    = array();
		$all      = array();

		foreach ( preg_split( '/(?<=[.!?])\s+/u', $text ) as $sentence ) {
			if ( ! preg_match_all( $colour, $sentence, $m ) ) {
				continue;
			}
			$found = array_map( fn( $c ) => $colours[ mb_strtolower( $c ) ], $m[1] );
			$all   = array_merge( $all, $found );
			if ( ! preg_match( '/kleurcode|reisadvies/iu', $sentence ) ) {
				continue;
			}
			if ( preg_match( $national, $sentence ) ) {
				$explicit = $explicit ?? array( $found[0], $sentence );
			} elseif ( ! $is_regional( $sentence ) ) {
				$plain = $plain ?? array( $found[0], $sentence );
			} else {
				$local = array_merge( $local, $found );
			}
		}

		$pick  = $explicit ?? $plain;
		$level = $pick ? $pick[0] : ( $local ? min( $local ) : null );
		$max   = $all ? max( array_merge( $all, $level ? array( $level ) : array() ) ) : $level;
		$basis = $pick ? trim( $pick[1] ) : ( $local ? 'regional statements only; mildest used' : '' );
		return array( $level, $max, $basis );
	}

	// ------------------------------------------------------------------
	// United Kingdom: FCDO via the GOV.UK Content API (JSON, OGL v3).
	// Structured field details.alert_status.
	// ------------------------------------------------------------------

	private function fcdo( array $country ): array {
		if ( empty( $country['uk'] ) ) {
			throw new SourceException( 'not_found' );
		}
		$res = $this->get( self::FCDO . '/' . rawurlencode( $country['uk'] ) );
		self::expect_ok( $res );
		return self::parse_fcdo( $res['body'], $country['uk'] );
	}

	public static function parse_fcdo( string $json, string $slug ): array {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || ! isset( $data['details'] ) ) {
			throw new SourceException( 'unexpected_response' );
		}
		$status   = (array) ( $data['details']['alert_status'] ?? array() );
		$has      = fn( $s ) => in_array( $s, $status, true );
		$warnings = '';
		foreach ( (array) ( $data['details']['parts'] ?? array() ) as $part ) {
			if ( 'warnings-and-insurance' === ( $part['slug'] ?? '' ) ) {
				$warnings = self::text( $part['body'] ?? '' );
			}
		}

		if ( $has( 'avoid_all_travel_to_whole_country' ) ) {
			$level = 4;
		} elseif ( $has( 'avoid_all_but_essential_travel_to_whole_country' ) ) {
			$level = 3;
		} elseif ( preg_match( '/advises? against all but essential travel to the (rest|remainder) of/i', $warnings ) ) {
			// Parts-only alerts, but the text puts the rest of the country at "essential only"
			// (e.g. Ukraine). Two "to parts" statuses alone do not mean that (e.g. Israel).
			$level = 3;
		} elseif ( $status ) {
			$level = 2;
		} else {
			$level = 1;
		}
		$max = $level;
		if ( $has( 'avoid_all_travel_to_parts' ) ) {
			$max = 4;
		} elseif ( $has( 'avoid_all_but_essential_travel_to_parts' ) ) {
			$max = max( $max, 3 );
		}

		$summary = '' !== $warnings ? $warnings : trim( (string) ( $data['description'] ?? '' ) );

		return array(
			'source'   => 'fcdo',
			'level'    => $level,
			'maxLevel' => $max,
			'basis'    => $status ? 'alert_status: ' . implode( ', ', $status ) : 'alert_status: none',
			'summary'  => self::shorten( $summary ),
			'url'      => 'https://www.gov.uk/foreign-travel-advice/' . $slug,
			'updated'  => self::iso_date( $data['public_updated_at'] ?? null ),
		);
	}

	// ------------------------------------------------------------------
	// Germany: Auswärtiges Amt open data (JSON).
	// Boolean flags warning / partialWarning / situationWarning / situationPartWarning.
	// ------------------------------------------------------------------

	private function aa( array $country ): array {
		$list = $this->feed( self::AA );
		$id   = self::aa_find( $list, $country['iso3'] );
		if ( null === $id ) {
			throw new SourceException( 'not_found' );
		}
		$detail = $this->get( self::AA . '/' . rawurlencode( $id ) );
		self::expect_ok( $detail );
		$summary = json_decode( $list, true )['response'][ $id ] ?? array();
		return self::parse_aa( $detail['body'], $id, is_array( $summary ) ? $summary : array() );
	}

	public static function aa_find( string $json, string $iso3 ): ?string {
		$data = json_decode( $json, true );
		foreach ( (array) ( $data['response'] ?? array() ) as $id => $entry ) {
			if ( is_array( $entry ) && strtoupper( $entry['iso3CountryCode'] ?? '' ) === $iso3 ) {
				return (string) $id;
			}
		}
		return null;
	}

	/**
	 * Flags from the list entry and the detail record are combined (either may carry them).
	 * situationWarning / situationPartWarning were introduced for COVID-19; the everyday
	 * "Von Reisen ... wird abgeraten" (advise against travel) only appears in the page text,
	 * so that wording is read as well.
	 *
	 * @param array $summary The country's entry from the /travelwarning list.
	 */
	public static function parse_aa( string $json, string $id, array $summary = array() ): array {
		$data  = json_decode( $json, true );
		$entry = $data['response'][ $id ] ?? null;
		if ( ! is_array( $entry ) ) {
			throw new SourceException( 'unexpected_response' );
		}
		$flag = fn( $k ) => self::truthy( $entry[ $k ] ?? null ) || self::truthy( $summary[ $k ] ?? null );

		if ( $flag( 'warning' ) ) {
			$level = 4;
		} elseif ( $flag( 'situationWarning' ) ) {
			$level = 3;
		} elseif ( $flag( 'partialWarning' ) || $flag( 'situationPartWarning' ) ) {
			$level = 2;
		} else {
			$level = 1;
		}
		$max = $level;
		if ( $flag( 'partialWarning' ) ) {
			$max = 4;
		} elseif ( $flag( 'situationPartWarning' ) ) {
			$max = max( $max, 3 );
		}

		$text   = self::text( $entry['content'] ?? '' );
		$phrase = self::aa_phrases( $text );
		$level  = max( $level, $phrase['level'] );
		$max    = max( $max, $level, $phrase['max'] );

		$basis = array_keys( array_filter( array_map( $flag, array_combine( self::AA_FLAGS, self::AA_FLAGS ) ) ) );
		$basis = ( $basis ? implode( ', ', $basis ) : 'no warning flags' ) . ( $phrase['sentence'] ? ' · "' . $phrase['sentence'] . '"' : '' );

		$updated = $entry['lastModified'] ?? ( $summary['lastModified'] ?? null );
		return array(
			'source'   => 'aa',
			'level'    => $level,
			'maxLevel' => $max,
			'basis'    => $basis,
			'summary'  => self::shorten( $text ),
			'url'      => 'https://www.auswaertiges-amt.de/de/ReiseUndSicherheit/reise-und-sicherheitshinweise',
			'updated'  => is_numeric( $updated ) ? gmdate( 'c', (int) ( $updated / 1000 ) ) : self::iso_date( $updated ),
		);
	}

	const AA_FLAGS = array( 'warning', 'partialWarning', 'situationWarning', 'situationPartWarning' );

	/**
	 * "Vor Reisen nach X wird gewarnt" = travel warning (4); "Von (nicht notwendigen) Reisen
	 * nach X wird (dringend) abgeraten" = advise against travel (3). A sentence naming an area
	 * (Gebiet, Grenze, Norden, Streifen, ...) only raises the regional maximum.
	 *
	 * @return array{level:int, max:int, sentence:string}
	 */
	public static function aa_phrases( string $text ): array {
		$out      = array( 'level' => 0, 'max' => 0, 'sentence' => '' );
		$regional = '/gebiet|region|grenz|provinz|streifen|westjordanland|golan|norden|süden|osten|westen|nördlich|südlich|östlich|westlich|umgebung|bezirk|distrikt|umkreis|kilometer|\bkm\b|stadt|städte|insel/iu';
		foreach ( preg_split( '/(?<=[.!?])\s+/u', $text ) as $sentence ) {
			if ( preg_match( '/\bReisen\b.*\bwird\s+gewarnt\b/iu', $sentence ) ) {
				$step = 4;
			} elseif ( preg_match( '/\bReisen\b.*\bwird\s+(dringend\s+)?abgeraten\b/iu', $sentence ) ) {
				$step = 3;
			} else {
				continue;
			}
			$out['max'] = max( $out['max'], $step );
			if ( ! preg_match( $regional, $sentence ) && $step > $out['level'] ) {
				$out['level']    = $step;
				$out['sentence'] = mb_substr( trim( $sentence ), 0, 160 );
			}
		}
		return $out;
	}

	private static function truthy( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || ( is_string( $value ) && 'true' === strtolower( $value ) );
	}

	// ------------------------------------------------------------------
	// United States: State Department travel advisories (RSS, public domain).
	// Title "Saudi Arabia - Level 3: Reconsider Travel"; regional levels only in the text.
	// ------------------------------------------------------------------

	public static function parse_usdos( string $xml, array $country ): array {
		foreach ( self::rss_items( $xml ) as $item ) {
			if ( ! preg_match( '/^(?<name>.+?)\s*[-–]\s*Level\s*(?<level>[1-4])\s*:/u', $item['title'], $m ) ) {
				continue;
			}
			$name = preg_replace( '/\s*(?:[-–]\s*See\s+Summaries|Travel\s+Advisory)\s*$/i', '', $m['name'] );
			if ( ! self::name_matches( $name, $country ) ) {
				continue;
			}
			$level = (int) $m['level'];
			$text  = self::text( $item['description'] );
			$max   = $level;
			if ( preg_match_all( '/Level\s*([1-4])\b/i', $text, $levels ) ) {
				$max = max( $level, ...array_map( 'intval', $levels[1] ) );
			}
			if ( preg_match( '/do not travel to\b/i', $text ) ) {
				$max = 4;
			}
			return array(
				'source'   => 'usdos',
				'level'    => $level,
				'maxLevel' => $max,
				'basis'    => $item['title'],
				'summary'  => self::shorten( $text ),
				'url'      => $item['link'],
				'updated'  => self::iso_date( $item['pubDate'] ),
			);
		}
		throw new SourceException( 'not_found' );
	}

	// ------------------------------------------------------------------
	// Canada: Travel Advice and Advisories, one JSON index (Open Government Licence – Canada).
	// advisory-state 0..3 = our levels 1..4; regional advisories are flagged, not listed.
	// ------------------------------------------------------------------

	public static function parse_gac( string $json, array $country ): array {
		$data  = json_decode( $json, true );
		$entry = $data['data'][ $country['iso2'] ] ?? null;
		if ( ! is_array( $data ) || ! isset( $data['data'] ) ) {
			throw new SourceException( 'unexpected_response' );
		}
		if ( ! is_array( $entry ) ) {
			throw new SourceException( 'not_found' );
		}
		$eng   = (array) ( $entry['eng'] ?? array() );
		$state = $entry['advisory-state'] ?? null;
		$level = is_int( $state ) && $state >= 0 && $state <= 3 ? $state + 1 : self::level_from_phrase( (string) ( $eng['advisory-text'] ?? '' ) );
		$slug  = (string) ( $eng['url-slug'] ?? '' );
		$text  = trim( ( $eng['advisory-text'] ?? '' ) . '. ' . self::text( (string) ( $eng['recent-updates'] ?? '' ) ), ' .' );
		return array(
			'source'   => 'gac',
			'level'    => $level,
			'maxLevel' => $level,
			'basis'    => 'advisory-state ' . ( is_int( $state ) ? $state : '?' ) . ': ' . ( $eng['advisory-text'] ?? '' ) . ( ! empty( $entry['has-regional-advisory'] ) ? ' (+ regional advisories)' : '' ),
			'regional' => ! empty( $entry['has-regional-advisory'] ) && $level < 4,
			'summary'  => self::shorten( $text . '.' ),
			'url'      => 'https://travel.gc.ca/destinations/' . ( $slug ?: '' ),
			'updated'  => self::iso_date( $entry['date-published']['date'] ?? null ),
		);
	}

	// ------------------------------------------------------------------
	// Australia: Smartraveller RSS. The overall advice is in <ta:warnings><ta:description>;
	// stricter regional advice only appears in the text ("Do not travel to ...").
	// ------------------------------------------------------------------

	public static function parse_dfat( string $xml, array $country ): array {
		foreach ( self::rss_items( $xml ) as $item ) {
			$slug = basename( (string) parse_url( $item['link'], PHP_URL_PATH ) );
			if ( ! self::name_matches( $item['title'], $country ) && ! self::name_matches( str_replace( '-', ' ', $slug ), $country ) ) {
				continue;
			}
			$overall = preg_match( '/<ta:description>(?:<!\[CDATA\[)?([\s\S]*?)(?:\]\]>)?<\/ta:description>/', $item['raw'], $d ) ? self::text( $d[1] ) : '';
			$level   = self::level_from_phrase( $overall );
			$text    = self::text( $item['description'] );
			$max     = $level;
			if ( preg_match( '/do not travel to\b/i', $text ) ) {
				$max = 4;
			} elseif ( preg_match( '/reconsider your need to travel to\b/i', $text ) ) {
				$max = max( (int) $max, 3 );
			}
			return array(
				'source'   => 'dfat',
				'level'    => $level,
				'basis'    => $overall,
				'maxLevel' => $level ? max( $level, $max ) : null,
				'summary'  => self::shorten( trim( $overall . '. ' . $text, ' .' ) . '.' ),
				'url'      => $item['link'],
				'updated'  => self::iso_date( $item['pubDate'] ),
			);
		}
		throw new SourceException( 'not_found' );
	}

	/** The four-step wording Canada, Australia and the US use. */
	public static function level_from_phrase( string $text ): ?int {
		$t = strtolower( $text );
		if ( preg_match( '/do not travel|avoid all travel/', $t ) && ! preg_match( '/non-essential|but essential/', $t ) ) {
			return 4;
		}
		if ( preg_match( '/reconsider (your need to )?travel|avoid non-essential travel/', $t ) ) {
			return 3;
		}
		if ( preg_match( '/high degree of caution|increased caution/', $t ) ) {
			return 2;
		}
		if ( preg_match( '/normal (safety|security)? ?precautions/', $t ) ) {
			return 1;
		}
		return null;
	}

	/** @return array<int,array{title:string,link:string,description:string,pubDate:?string,raw:string}> */
	public static function rss_items( string $xml ): array {
		if ( ! preg_match_all( '/<item\b[^>]*>([\s\S]*?)<\/item>/', $xml, $m ) ) {
			throw new SourceException( 'unexpected_response' );
		}
		return array_map(
			fn( $raw ) => array(
				'title'       => self::text( self::xml_field( $raw, 'title' ) ?? '' ),
				'link'        => trim( html_entity_decode( self::xml_field( $raw, 'link' ) ?? '' ) ),
				'description' => html_entity_decode( self::xml_field( $raw, 'description' ) ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'pubDate'     => self::xml_field( $raw, 'pubDate' ),
				'raw'         => $raw,
			),
			$m[1]
		);
	}

	/**
	 * Feed names differ from ours ("Burma (Myanmar)", "Mainland China, Hong Kong & Macau",
	 * "The Bahamas"). Compares normalised names, including the parts of combined titles
	 * and any aliases in countries.json ("alt").
	 */
	public static function name_matches( string $feed_name, array $country ): bool {
		$feed_keys = array( self::name_key( $feed_name ) );
		if ( preg_match( '/^(.*?)\s*\((.*)\)\s*$/', $feed_name, $p ) ) {
			$feed_keys[] = self::name_key( $p[1] );
			$feed_keys[] = self::name_key( $p[2] );
		}
		foreach ( preg_split( '/\s*(?:,|&|\band\b)\s*/i', $feed_name ) as $part ) {
			$feed_keys[] = self::name_key( preg_replace( '/^mainland\s+/i', '', $part ) );
		}
		$ours = array_map( array( self::class, 'name_key' ), array_merge( array( $country['en'] ), (array) ( $country['alt'] ?? array() ) ) );
		return (bool) array_intersect( array_filter( $feed_keys ), $ours );
	}

	public static function name_key( string $name ): string {
		$ascii = strtolower( (string) iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $name ) );
		$ascii = preg_replace( '/[^a-z0-9]+/', ' ', $ascii );
		return trim( preg_replace( '/^the\s+/', '', trim( $ascii ) ) );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/** Whole feeds (US, Canada, Australia, AA list) are fetched once and shared by all countries. */
	private function feed( string $url ): string {
		if ( isset( $this->feeds[ $url ] ) ) {
			return $this->feeds[ $url ];
		}
		$fetch = function () use ( $url ) {
			$res = $this->get( $url );
			self::expect_ok( $res );
			return $res['body'];
		};
		$this->feeds[ $url ] = $this->cache ? ( $this->cache )( 'feed:' . $url, $fetch ) : $fetch();
		return $this->feeds[ $url ];
	}

	private function get( string $url ): array {
		return ( $this->http )( $url );
	}

	private static function expect_ok( array $res ): void {
		if ( 404 === $res['status'] ) {
			throw new SourceException( 'not_found' );
		}
		if ( $res['status'] < 200 || $res['status'] >= 300 ) {
			throw new SourceException( 'http_' . $res['status'] );
		}
	}

	public static function xml_field( string $xml, string $tag ): ?string {
		$pattern = '/<' . $tag . '>(?:<!\[CDATA\[)?([\s\S]*?)(?:\]\]>)?<\/' . $tag . '>/';
		return preg_match( $pattern, $xml, $m ) ? trim( $m[1] ) : null;
	}

	/** HTML fragment to plain text; drops notification boxes (newsletter sign-ups etc.). */
	public static function text( string $html ): string {
		$html = preg_replace( '/<div class="notification[\s\S]*?<\/div>/', ' ', $html );
		$html = preg_replace( '/<\/(p|li|h\d)>/i', '$0 ', $html );
		$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	public static function shorten( string $text, int $max = 900 ): string {
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, $max );
		$end = mb_strrpos( $cut, '. ' );
		return ( $end > $max / 2 ? mb_substr( $cut, 0, $end + 1 ) : $cut ) . ' …';
	}

	public static function slug( string $name ): string {
		$ascii = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $name );
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $ascii ) ), '-' );
	}

	private static function iso_date( ?string $value ): ?string {
		if ( ! $value ) {
			return null;
		}
		$t = strtotime( $value );
		return $t ? gmdate( 'c', $t ) : null;
	}
}

