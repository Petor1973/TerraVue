<?php
/**
 * Unit tests for the source and news parsers. No WordPress needed.
 * Run: php tests/unit.php
 */

define( 'TRAVEL_RISK_TESTING', true );
require __DIR__ . '/../includes/Sources.php';
require __DIR__ . '/../includes/News.php';

use TravelRisk\Sources;
use TravelRisk\News;
use TravelRisk\SourceException;

$failed = 0;
$count  = 0;
function check( string $name, $actual, $expected ): void {
	global $failed, $count;
	$count++;
	if ( $actual === $expected ) {
		echo "ok   $name\n";
		return;
	}
	$failed++;
	echo "FAIL $name\n     expected " . json_encode( $expected, JSON_UNESCAPED_UNICODE ) . "\n     actual   " . json_encode( $actual, JSON_UNESCAPED_UNICODE ) . "\n";
}

// ---------------------------------------------------------------- BuZa (NL) colour code in running text
$buza = array(
	'regional red first, country-wide yellow after' => array(
		'Op de eilanden in de Golf is de situatie gespannen. Voor de eilanden Abu Musa en Tunb is de kleurcode van het reisadvies rood. Voor de rest van het land is de kleurcode van het reisadvies geel.',
		array( 2, 4 ),
	),
	'yellow for most of the country, red border strip' => array(
		'Sinds 1 juni geldt kleurcode geel voor het grootste deel van het land. Voor de strook van 20 kilometer langs de grens geldt kleurcode rood.',
		array( 2, 4 ),
	),
	'three levels, country-wide last' => array(
		'Voor de grensstreek in het noorden is het reisadvies rood. In de provincies in het oosten geldt kleurcode oranje. Voor de meeste gebieden is de kleurcode geel.',
		array( 2, 4 ),
	),
	'plain green' => array(
		'Het reisadvies voor Noorwegen heeft kleurcode groen. Er zijn geen bijzondere veiligheidsrisico\'s.',
		array( 1, 1 ),
	),
	'whole country red' => array(
		'De kleurcode van het reisadvies is rood voor het hele land. Reis er niet heen.',
		array( 4, 4 ),
	),
	'only regional statements: mildest wins' => array(
		'In het zuiden geldt kleurcode oranje. Voor de kuststrook geldt kleurcode geel.',
		array( 2, 3 ),
	),
	'no colour at all' => array(
		'Er is geen reisadvies beschikbaar.',
		array( null, null ),
	),
);
foreach ( $buza as $name => list( $text, $expected ) ) {
	check( "buza: $name", Sources::buza_levels( $text ), $expected );
}

$xml = '<document><introduction><![CDATA[<h2>In het kort</h2><p>De kleurcode van het reisadvies is groen.</p><div class="notification attention"><p>Meld je aan voor e-mail</p></div>]]></introduction>'
	. '<canonical>https://www.nederlandwereldwijd.nl/reisadvies/noorwegen</canonical><lastmodified>2026-09-01T10:00:00Z</lastmodified></document>';
$parsed = Sources::parse_buza( $xml );
check( 'buza xml: summary without notification box', $parsed['summary'], 'In het kort De kleurcode van het reisadvies is groen.' );
check( 'buza xml: level', array( $parsed['level'], $parsed['maxLevel'] ), array( 1, 1 ) );
check( 'buza xml: url', $parsed['url'], 'https://www.nederlandwereldwijd.nl/reisadvies/noorwegen' );
check( 'buza xml: updated', $parsed['updated'], '2026-09-01T10:00:00+00:00' );
check( 'slug without diacritics', Sources::slug( 'Saoedi-Arabië' ), 'saoedi-arabie' );
check( 'slug with spaces', Sources::slug( 'Bosnië en Herzegovina' ), 'bosnie-en-herzegovina' );

// ---------------------------------------------------------------- FCDO (UK)
$fcdo = function ( array $status ) {
	return json_encode( array(
		'description'       => 'FCDO travel advice for Testland.',
		'public_updated_at' => '2026-09-10T08:00:00Z',
		'details'           => array(
			'alert_status' => $status,
			'parts'        => array(
				array( 'slug' => 'summary', 'body' => '<p>Intro</p>' ),
				array( 'slug' => 'warnings-and-insurance', 'body' => '<p>FCDO advises against all travel to the border area.</p><ul><li>Area A</li></ul>' ),
			),
		),
	) );
};
$cases = array(
	'no alerts'                         => array( array(), array( 1, 1 ) ),
	'parts: all travel'                 => array( array( 'avoid_all_travel_to_parts' ), array( 2, 4 ) ),
	'parts: essential only'             => array( array( 'avoid_all_but_essential_travel_to_parts' ), array( 2, 3 ) ),
	'whole country essential + parts'   => array( array( 'avoid_all_but_essential_travel_to_whole_country', 'avoid_all_travel_to_parts' ), array( 3, 4 ) ),
	'whole country: all travel'         => array( array( 'avoid_all_travel_to_whole_country' ), array( 4, 4 ) ),
);
foreach ( $cases as $name => list( $status, $expected ) ) {
	$r = Sources::parse_fcdo( $fcdo( $status ), 'testland' );
	check( "fcdo: $name", array( $r['level'], $r['maxLevel'] ), $expected );
}
$r = Sources::parse_fcdo( $fcdo( array() ), 'testland' );
check( 'fcdo: summary from warnings part', $r['summary'], 'FCDO advises against all travel to the border area. Area A' );
check( 'fcdo: url', $r['url'], 'https://www.gov.uk/foreign-travel-advice/testland' );

// ---------------------------------------------------------------- Auswärtiges Amt (DE)
$list = json_encode( array( 'response' => array(
	'contentList' => array( '111', '222' ),
	'111'         => array( 'iso3CountryCode' => 'NOR', 'title' => 'Norwegen' ),
	'222'         => array( 'iso3CountryCode' => 'SAU', 'title' => 'Saudi-Arabien' ),
) ) );
check( 'aa: find by iso3', Sources::aa_find( $list, 'SAU' ), '222' );
check( 'aa: unknown country', Sources::aa_find( $list, 'XYZ' ), null );

$aa = function ( array $flags ) {
	return json_encode( array( 'response' => array( '222' => $flags + array(
		'lastModified' => 1757500000000,
		'content'      => '<h3>Aktuelles</h3><p>Vor Reisen in das Grenzgebiet wird gewarnt.</p>',
	) ) ) );
};
$cases = array(
	'no warnings'          => array( array(), array( 1, 1 ) ),
	'partial warning'      => array( array( 'partialWarning' => true ), array( 2, 4 ) ),
	'situation part'       => array( array( 'situationPartWarning' => true ), array( 2, 3 ) ),
	'advise against + part' => array( array( 'situationWarning' => true, 'partialWarning' => true ), array( 3, 4 ) ),
	'travel warning'       => array( array( 'warning' => true ), array( 4, 4 ) ),
);
foreach ( $cases as $name => list( $flags, $expected ) ) {
	$r = Sources::parse_aa( $aa( $flags ), '222' );
	check( "aa: $name", array( $r['level'], $r['maxLevel'] ), $expected );
}
check( 'aa: summary', Sources::parse_aa( $aa( array() ), '222' )['summary'], 'Aktuelles Vor Reisen in das Grenzgebiet wird gewarnt.' );
check( 'aa: millisecond timestamp', Sources::parse_aa( $aa( array() ), '222' )['updated'], gmdate( 'c', 1757500000 ) );

// ---------------------------------------------------------------- adapters with fake HTTP
$calls = array();
$http  = function ( string $url ) use ( &$calls, $xml ) {
	$calls[] = $url;
	if ( str_contains( $url, '/sau/' ) ) {
		return array( 'status' => 404, 'body' => '' );
	}
	return array( 'status' => 200, 'body' => $xml );
};
$sources = new Sources( $http );
$sources->advice( 'buza', array( 'iso3' => 'SAU', 'nl' => 'Saoedi-Arabië' ) );
check( 'buza: falls back to slug after 404', end( $calls ), Sources::BUZA . '/saoedi-arabie/traveladvice' );

$threw = null;
try {
	$sources->advice( 'fcdo', array( 'iso3' => 'GBR', 'uk' => '' ) );
} catch ( SourceException $e ) {
	$threw = $e->getMessage();
}
check( 'fcdo: no advice for the UK itself', $threw, 'no_home_advice' );
check( 'language to source', array( Sources::for_language( 'nl' ), Sources::for_language( 'de' ), Sources::for_language( 'en' ), Sources::for_language( 'xx' ) ), array( 'buza', 'aa', 'fcdo', 'fcdo' ) );

// ---------------------------------------------------------------- news
$gdelt = json_encode( array( 'articles' => array(
	array( 'title' => 'Drone attack near Riyadh', 'url' => 'https://example.com/a', 'domain' => 'example.com', 'seendate' => '20260923T101500Z' ),
	array( 'title' => 'Drone attack near Riyadh', 'url' => 'https://example.com/b', 'domain' => 'other.com', 'seendate' => '20260923T101500Z' ),
	array( 'title' => 'Striker scores late goal in league match', 'url' => 'https://example.com/c', 'domain' => 'sport.com', 'seendate' => '20260923T101500Z' ),
	array( 'title' => 'Bad link', 'url' => 'javascript:alert(1)', 'domain' => 'x', 'seendate' => '' ),
) ) );
$items = News::clean( News::parse_gdelt( $gdelt ) );
check( 'gdelt: dedupe, sports and bad links removed', array_column( $items, 'title' ), array( 'Drone attack near Riyadh' ) );
check( 'gdelt: date', $items[0]['date'], '2026-09-23T10:15:00Z' );

$threw = null;
try {
	News::parse_gdelt( 'Please limit requests to one every 5 seconds', 200 );
} catch ( SourceException $e ) {
	$threw = $e->getMessage();
}
check( 'gdelt: plain-text error', $threw, 'http_200' );

$rss   = '<rss><channel><item><title>Protest in capital &amp; curfew</title><link>https://news.example/1</link><pubDate>Tue, 22 Sep 2026 09:00:00 GMT</pubDate><source url="https://x">Example Times</source></item></channel></rss>';
$items = News::parse_rss( $rss );
check( 'rss: parsed', array( $items[0]['title'], $items[0]['source'], $items[0]['date'] ), array( 'Protest in capital & curfew', 'Example Times', '2026-09-22T09:00:00+00:00' ) );

// ---------------------------------------------------------------- countries.json
$countries = json_decode( file_get_contents( __DIR__ . '/../data/countries.json' ), true );
$iso3      = array_column( $countries, 'iso3' );
check( 'countries: unique ISO3', count( $iso3 ), count( array_unique( $iso3 ) ) );
check( 'countries: all fields present', count( array_filter( $countries, fn( $c ) => $c['iso3'] && $c['iso2'] && $c['en'] && $c['de'] && $c['nl'] ) ), count( $countries ) );

echo "\n" . ( $count - $failed ) . "/$count passed\n";
exit( $failed ? 1 : 0 );
