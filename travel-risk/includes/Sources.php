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

	/** Default source for a UI language, when the user has not chosen one. */
	const BY_LANGUAGE = array(
		'nl' => 'buza',
		'en' => 'fcdo',
		'de' => 'aa',
	);

	const NAMES = array(
		'buza' => 'Ministerie van Buitenlandse Zaken (NL)',
		'fcdo' => 'Foreign, Commonwealth & Development Office (UK)',
		'aa'   => 'Auswärtiges Amt (DE)',
	);

	/** @var callable */
	private $http;

	public function __construct( callable $http ) {
		$this->http = $http;
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
		switch ( $source ) {
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
		if ( 'NLD' === $country['iso3'] ) {
			throw new SourceException( 'no_home_advice' );
		}
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
		list( $level, $max ) = self::buza_levels( $intro );
		return array(
			'source'   => 'buza',
			'level'    => $level,
			'maxLevel' => $max,
			'summary'  => self::shorten( $intro ),
			'url'      => self::xml_field( $xml, 'canonical' ),
			'updated'  => self::iso_date( self::xml_field( $xml, 'lastmodified' ) ),
		);
	}

	/**
	 * Works sentence by sentence. A sentence mentioning a colour code counts as
	 * country-wide unless it names a region ("voor de strook langs de grens",
	 * "in het noorden", "op de eilanden"); explicit country-wide wording
	 * ("grootste deel", "hele land") always wins.
	 *
	 * @return array{0:?int,1:?int} [level, maxLevel]
	 */
	public static function buza_levels( string $text ): array {
		$colours  = array( 'groen' => 1, 'geel' => 2, 'oranje' => 3, 'rood' => 4 );
		$colour   = '/\b(groen|geel|oranje|rood)\b/iu';
		$national = '/grootste deel|meeste gebieden|hele land|gehele land|rest van het land|overige delen/iu';
		$regional = '/\b(voor|in|op|langs|rond|binnen|nabij)\s+(de|het|een)?\s*(\w+\s+)?(strook|gebied|gebieden|grens|grensgebied|regio|regio\'s|provincie|provincies|eiland|eilanden|noorden|zuiden|oosten|westen|noordoosten|noordwesten|zuidoosten|zuidwesten|deel|delen|stad|steden|kust|kuststrook|departement|departementen|staat|staten|district|districten)\b/iu';

		$country_wide = array();
		$local        = array();
		$all          = array();

		foreach ( preg_split( '/(?<=[.!?])\s+/u', $text ) as $sentence ) {
			if ( ! preg_match_all( $colour, $sentence, $m ) ) {
				continue;
			}
			$found = array_map( fn( $c ) => $colours[ mb_strtolower( $c ) ], $m[1] );
			$all   = array_merge( $all, $found );
			if ( ! preg_match( '/kleurcode|reisadvies/iu', $sentence ) ) {
				continue;
			}
			if ( preg_match( $national, $sentence ) || ! preg_match( $regional, $sentence ) ) {
				$country_wide[] = $found[0];
			} else {
				$local = array_merge( $local, $found );
			}
		}

		if ( $country_wide ) {
			$level = $country_wide[0];
		} elseif ( $local ) {
			// Only regional statements: the mildest one is the best guess for the rest.
			$level = min( $local );
		} else {
			$level = null;
		}
		$max = $all ? max( array_merge( $all, $level ? array( $level ) : array() ) ) : $level;
		return array( $level, $max );
	}

	// ------------------------------------------------------------------
	// United Kingdom: FCDO via the GOV.UK Content API (JSON, OGL v3).
	// Structured field details.alert_status.
	// ------------------------------------------------------------------

	private function fcdo( array $country ): array {
		if ( 'GBR' === $country['iso3'] || empty( $country['uk'] ) ) {
			throw new SourceException( 'no_home_advice' );
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
		$status = (array) ( $data['details']['alert_status'] ?? array() );
		$has    = fn( $s ) => in_array( $s, $status, true );

		if ( $has( 'avoid_all_travel_to_whole_country' ) ) {
			$level = 4;
		} elseif ( $has( 'avoid_all_but_essential_travel_to_whole_country' ) ) {
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

		$summary = trim( (string) ( $data['description'] ?? '' ) );
		foreach ( (array) ( $data['details']['parts'] ?? array() ) as $part ) {
			if ( 'warnings-and-insurance' === ( $part['slug'] ?? '' ) ) {
				$summary = self::text( $part['body'] ?? '' );
				break;
			}
		}

		return array(
			'source'   => 'fcdo',
			'level'    => $level,
			'maxLevel' => $max,
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
		if ( 'DEU' === $country['iso3'] ) {
			throw new SourceException( 'no_home_advice' );
		}
		$list = $this->get( self::AA );
		self::expect_ok( $list );
		$id = self::aa_find( $list['body'], $country['iso3'] );
		if ( null === $id ) {
			throw new SourceException( 'not_found' );
		}
		$detail = $this->get( self::AA . '/' . rawurlencode( $id ) );
		self::expect_ok( $detail );
		return self::parse_aa( $detail['body'], $id );
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

	public static function parse_aa( string $json, string $id ): array {
		$data  = json_decode( $json, true );
		$entry = $data['response'][ $id ] ?? null;
		if ( ! is_array( $entry ) ) {
			throw new SourceException( 'unexpected_response' );
		}
		$flag = fn( $k ) => ! empty( $entry[ $k ] );

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

		$updated = $entry['lastModified'] ?? null;
		return array(
			'source'   => 'aa',
			'level'    => $level,
			'maxLevel' => $max,
			'summary'  => self::shorten( self::text( $entry['content'] ?? '' ) ),
			'url'      => 'https://www.auswaertiges-amt.de/de/ReiseUndSicherheit/reise-und-sicherheitshinweise',
			'updated'  => is_numeric( $updated ) ? gmdate( 'c', (int) ( $updated / 1000 ) ) : self::iso_date( $updated ),
		);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

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
