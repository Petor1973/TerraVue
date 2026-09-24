<?php
/**
 * Unit tests for the source and news parsers and Web Push crypto. No WordPress needed.
 * Run: php tests/unit.php
 */

define( 'TRAVEL_RISK_TESTING', true );
require __DIR__ . '/../includes/Sources.php';
require __DIR__ . '/../includes/News.php';
require __DIR__ . '/../includes/Alerts.php';
require __DIR__ . '/../includes/WebPush.php';

use TravelRisk\Sources;
use TravelRisk\News;
use TravelRisk\Alerts;
use TravelRisk\SourceException;
use TravelRisk\WebPush;

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
	'Israel: red for border areas (compound words) first, yellow for most of the country' => array(
		'De kleurcode van het reisadvies is rood voor de grensgebieden tussen Israël en Gaza, Libanon en Egypte. Wat uw situatie ook is: reis niet hierheen. Per 8 september geldt kleurcode geel voor het grootste deel van Israël.',
		array( 2, 4 ),
	),
	'red for "de Gazastrook" (compound with strook)' => array(
		'Voor de Gazastrook geldt kleurcode rood. De kleurcode van het reisadvies voor Israël is oranje.',
		array( 3, 4 ),
	),
	'"tussen X en Y" is a border area' => array(
		'Het reisadvies is rood tussen Armenië en Azerbeidzjan. Voor Armenië is het reisadvies geel.',
		array( 2, 4 ),
	),
	'country names are not regions (Oostenrijk)' => array(
		'De kleurcode van het reisadvies voor Oostenrijk is groen.',
		array( 1, 1 ),
	),
	'no colour at all' => array(
		'Er is geen reisadvies beschikbaar.',
		array( null, null ),
	),
);
foreach ( $buza as $name => list( $text, $expected ) ) {
	check( "buza: $name", array_slice( Sources::buza_levels( $text ), 0, 2 ), $expected );
}

$xml = '<document><introduction><![CDATA[<h2>In het kort</h2><p>De kleurcode van het reisadvies is groen.</p><div class="notification attention"><p>Meld je aan voor e-mail</p></div>]]></introduction>'
	. '<canonical>https://www.nederlandwereldwijd.nl/reisadvies/noorwegen</canonical><lastmodified>2026-09-01T10:00:00Z</lastmodified></document>';
$parsed = Sources::parse_buza( $xml );
check( 'buza xml: summary without notification box', $parsed['summary'], 'In het kort De kleurcode van het reisadvies is groen.' );
check( 'buza xml: level', array( $parsed['level'], $parsed['maxLevel'] ), array( 1, 1 ) );
check( 'buza xml: basis is the deciding sentence', $parsed['basis'], 'In het kort De kleurcode van het reisadvies is groen.' );
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
		'content'      => '<h3>Aktuelles</h3><p>Hinweise zur Sicherheit im Land.</p>',
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
check( 'aa: summary', Sources::parse_aa( $aa( array() ), '222' )['summary'], 'Aktuelles Hinweise zur Sicherheit im Land.' );
$aa_detail = json_encode( array( 'response' => array( '333' => array(
	'lastModified' => 1757500000000,
	'content'      => '<h3>Teilreisewarnung</h3><p>Vor Reisen in den Gazastreifen und in das Grenzgebiet zu Libanon wird gewarnt.</p><p>Von Reisen in die übrigen Landesteile von Israel wird abgeraten.</p>',
) ) ) );
$r = Sources::parse_aa( $aa_detail, '333', array( 'iso3CountryCode' => 'ISR', 'partialWarning' => true ) );
check( 'aa: Israel - partial warning from the list, "abgeraten" for the rest from the text', array( $r['level'], $r['maxLevel'] ), array( 3, 4 ) );
check( 'aa: basis names flag and sentence', $r['basis'], 'partialWarning · "Von Reisen in die übrigen Landesteile von Israel wird abgeraten."' );
$r = Sources::parse_aa( json_encode( array( 'response' => array( '444' => array( 'content' => '<p>Von Reisen in die Grenzregion zu Kolumbien wird abgeraten.</p>' ) ) ) ), '444', array( 'warning' => 'false' ) );
check( 'aa: regional "abgeraten" only raises the maximum; string "false" is not a flag', array( $r['level'], $r['maxLevel'] ), array( 1, 3 ) );
check( 'aa: millisecond timestamp', Sources::parse_aa( $aa( array() ), '222' )['updated'], gmdate( 'c', 1757500000 ) );

// ---------------------------------------------------------------- FCDO: two regional tiers (Ukraine)
$fcdo_text = function ( array $status, string $warnings ) {
	return json_encode( array( 'details' => array( 'alert_status' => $status, 'parts' => array( array( 'slug' => 'warnings-and-insurance', 'body' => "<p>$warnings</p>" ) ) ) ) );
};
$r = Sources::parse_fcdo( $fcdo_text( array( 'avoid_all_travel_to_parts', 'avoid_all_but_essential_travel_to_parts' ), 'FCDO advises against all travel to Gaza and within 500m of the border. FCDO advises against all but essential travel to parts of the West Bank.' ), 'israel' );
check( 'fcdo: Israel - two regional tiers, rest of the country not covered', array( $r['level'], $r['maxLevel'] ), array( 2, 4 ) );
check( 'fcdo: basis lists alert_status', $r['basis'], 'alert_status: avoid_all_travel_to_parts, avoid_all_but_essential_travel_to_parts' );
$r = Sources::parse_fcdo( $fcdo_text( array( 'avoid_all_travel_to_parts', 'avoid_all_but_essential_travel_to_parts' ), 'FCDO advises against all travel to Crimea. FCDO advises against all but essential travel to the rest of Ukraine.' ), 'ukraine' );
check( 'fcdo: Ukraine - text puts the rest of the country at essential only', array( $r['level'], $r['maxLevel'] ), array( 3, 4 ) );

// ---------------------------------------------------------------- United States (RSS)
$sau = array( 'iso3' => 'SAU', 'iso2' => 'SA', 'en' => 'Saudi Arabia' );
$chn = array( 'iso3' => 'CHN', 'iso2' => 'CN', 'en' => 'China' );
$mmr = array( 'iso3' => 'MMR', 'iso2' => 'MM', 'en' => 'Myanmar', 'alt' => array( 'Burma' ) );
$bhs = array( 'iso3' => 'BHS', 'iso2' => 'BS', 'en' => 'Bahamas' );
$nor = array( 'iso3' => 'NOR', 'iso2' => 'NO', 'en' => 'Norway' );
$us_rss = '<?xml version="1.0" encoding="utf-8"?><rss><channel>'
	. '<item><title>Saudi Arabia - Level 3: Reconsider Travel</title><link>https://travel.state.gov/sa.html</link><pubDate>Mon, 21 Sep 2026 10:00:00 GMT</pubDate>'
	. '<description>&lt;p&gt;Reconsider travel due to missile and drone attacks. &lt;b&gt;Level 4: Do Not Travel&lt;/b&gt; to within 10 miles of the Yemen border.&lt;/p&gt;</description></item>'
	. '<item><title>Mainland China, Hong Kong &amp; Macau - See Summaries - Level 2: Exercise Increased Caution</title><link>https://travel.state.gov/cn.html</link><description>Exercise increased caution.</description></item>'
	. '<item><title>Burma (Myanmar) Travel Advisory - Level 4: Do Not Travel</title><link>https://travel.state.gov/mm.html</link><description>Do not travel.</description></item>'
	. '<item><title>The Bahamas - Level 2: Exercise Increased Caution</title><link>https://travel.state.gov/bs.html</link><description>Caution.</description></item>'
	. '<item><title>Worldwide Caution</title><link>https://travel.state.gov/ww.html</link><description>No level.</description></item>'
	. '</channel></rss>';
$r = Sources::parse_usdos( $us_rss, $sau );
check( 'us: level from title, regional Level 4 from text', array( $r['level'], $r['maxLevel'] ), array( 3, 4 ) );
check( 'us: summary and url', array( str_starts_with( $r['summary'], 'Reconsider travel due to' ), $r['url'] ), array( true, 'https://travel.state.gov/sa.html' ) );
check( 'us: combined title "Mainland China, Hong Kong & Macau - See Summaries"', Sources::parse_usdos( $us_rss, $chn )['level'], 2 );
check( 'us: "Burma (Myanmar) Travel Advisory" via alias', Sources::parse_usdos( $us_rss, $mmr )['level'], 4 );
check( 'us: "The Bahamas"', Sources::parse_usdos( $us_rss, $bhs )['level'], 2 );
$threw = null;
try {
	Sources::parse_usdos( $us_rss, $nor );
} catch ( SourceException $e ) {
	$threw = $e->getMessage();
}
check( 'us: country not in feed -> not_found', $threw, 'not_found' );

// ---------------------------------------------------------------- Canada (JSON index)
$ca_json = json_encode( array(
	'metadata' => array( 'generated' => array( 'date' => '2026-09-23' ) ),
	'data'     => array(
		'SA' => array( 'country-iso' => 'SA', 'advisory-state' => 1, 'has-regional-advisory' => 1, 'date-published' => array( 'date' => '2026-09-20 10:00:00' ), 'eng' => array( 'name' => 'Saudi Arabia', 'url-slug' => 'saudi-arabia', 'advisory-text' => 'Exercise a high degree of caution', 'recent-updates' => '<p>Updated security section.</p>' ) ),
		'NO' => array( 'country-iso' => 'NO', 'advisory-state' => 0, 'has-regional-advisory' => 0, 'eng' => array( 'url-slug' => 'norway', 'advisory-text' => 'Take normal security precautions' ) ),
		'MM' => array( 'country-iso' => 'MM', 'advisory-state' => 3, 'has-regional-advisory' => 0, 'eng' => array( 'url-slug' => 'myanmar', 'advisory-text' => 'Avoid all travel' ) ),
	),
) );
$r = Sources::parse_gac( $ca_json, $sau );
check( 'ca: advisory-state 1 -> level 2, regional flag', array( $r['level'], $r['maxLevel'], $r['regional'] ), array( 2, 2, true ) );
check( 'ca: url and summary', array( $r['url'], $r['summary'] ), array( 'https://travel.gc.ca/destinations/saudi-arabia', 'Exercise a high degree of caution. Updated security section.' ) );
check( 'ca: levels 1 and 4', array( Sources::parse_gac( $ca_json, $nor )['level'], Sources::parse_gac( $ca_json, $mmr )['level'] ), array( 1, 4 ) );

// ---------------------------------------------------------------- Australia (Smartraveller RSS)
$au_rss = '<rss xmlns:ta="https://www.smartraveller.gov.au"><channel>'
	. '<item><title>Saudi Arabia</title><link>https://www.smartraveller.gov.au/destinations/middle-east/saudi-arabia</link><pubDate>Tue, 22 Sep 2026 09:00:00 GMT</pubDate>'
	. '<description>&lt;p&gt;Exercise a high degree of caution in Saudi Arabia. Do not travel to within 10km of the border with Yemen.&lt;/p&gt;</description>'
	. '<ta:warnings><ta:level>3/5</ta:level><ta:description>Exercise a high degree of caution</ta:description></ta:warnings></item>'
	. '<item><title>South Korea (Republic of Korea)</title><link>https://www.smartraveller.gov.au/destinations/asia/south-korea-republic-korea</link><description>Normal.</description>'
	. '<ta:warnings><ta:level>2/5</ta:level><ta:description>Exercise normal safety precautions</ta:description></ta:warnings></item>'
	. '<item><title>Türkiye</title><link>https://www.smartraveller.gov.au/destinations/europe/turkiye</link><description>Reconsider your need to travel to the border with Syria.</description>'
	. '<ta:warnings><ta:level>3/5</ta:level><ta:description>Exercise a high degree of caution</ta:description></ta:warnings></item>'
	. '</channel></rss>';
$r = Sources::parse_dfat( $au_rss, $sau );
check( 'au: level from ta:description, regional "Do not travel to" -> max 4', array( $r['level'], $r['maxLevel'] ), array( 2, 4 ) );
check( 'au: "South Korea (Republic of Korea)"', Sources::parse_dfat( $au_rss, array( 'iso3' => 'KOR', 'en' => 'South Korea' ) )['level'], 1 );
check( 'au: "Türkiye" via alias, "Reconsider ... to" -> max 3', array_values( array_intersect_key( Sources::parse_dfat( $au_rss, array( 'iso3' => 'TUR', 'en' => 'Turkey', 'alt' => array( 'Turkiye' ) ) ), array( 'level' => 1, 'maxLevel' => 1 ) ) ), array( 2, 3 ) );

check( 'phrases to levels', array_map( array( Sources::class, 'level_from_phrase' ), array( 'Exercise normal safety precautions', 'Exercise a high degree of caution', 'Reconsider your need to travel', 'Avoid non-essential travel', 'Do not travel', 'Avoid all travel', 'something else' ) ), array( 1, 2, 3, 3, 4, 4, null ) );

$threw = null;
try {
	( new Sources( fn() => array( 'status' => 200, 'body' => '' ) ) )->advice( 'usdos', array( 'iso3' => 'USA', 'en' => 'United States' ) );
} catch ( SourceException $e ) {
	$threw = $e->getMessage();
}
check( 'us: no advice for the US itself', $threw, 'no_home_advice' );

$fetches = 0;
$shared  = new Sources( function () use ( &$fetches, $us_rss ) { $fetches++; return array( 'status' => 200, 'body' => $us_rss ); } );
$shared->advice( 'usdos', $sau );
$shared->advice( 'usdos', $mmr );
check( 'feed fetched once for several countries', $fetches, 1 );

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


// ---------------------------------------------------------------- Web Push (RFC 8291 / 8292)
// Decryption here is an independent, test-only implementation of the receiving side.
function test_decrypt( string $body, $ua_key, string $auth ): string {
	$salt      = substr( $body, 0, 16 );
	$idlen     = ord( $body[20] );
	$as_public = substr( $body, 21, $idlen );
	$data      = substr( $body, 21 + $idlen );
	$ua_public = WebPush::raw_public( $ua_key );
	$shared    = openssl_pkey_derive( openssl_pkey_get_public( WebPush::pem_public( $as_public ) ), $ua_key );
	$prk_key   = hash_hmac( 'sha256', $shared, $auth, true );
	$ikm       = hash_hmac( 'sha256', "WebPush: info\0" . $ua_public . $as_public . "\x01", $prk_key, true );
	$prk       = hash_hmac( 'sha256', $ikm, $salt, true );
	$cek       = substr( hash_hmac( 'sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true ), 0, 16 );
	$nonce     = substr( hash_hmac( 'sha256', "Content-Encoding: nonce\0\x01", $prk, true ), 0, 12 );
	$plain     = openssl_decrypt( substr( $data, 0, -16 ), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr( $data, -16 ) );
	return false === $plain ? 'DECRYPT FAILED' : rtrim( $plain, "\x00" );
}
function raw_to_der( string $raw ): string {
	$int = function ( string $x ) {
		$x = ltrim( $x, "\x00" );
		if ( ord( $x[0] ) & 0x80 ) {
			$x = "\x00" . $x;
		}
		return "\x02" . chr( strlen( $x ) ) . $x;
	};
	$seq = $int( substr( $raw, 0, 32 ) ) . $int( substr( $raw, 32 ) );
	return "\x30" . chr( strlen( $seq ) ) . $seq;
}

$ua      = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
$ua_auth = random_bytes( 16 );
$message = '{"title":"Saudi Arabia","body":"Exercise caution → Do not travel ✓"}';
$body    = WebPush::encrypt( $message, WebPush::raw_public( $ua ), $ua_auth );
check( 'push: header = salt, record size 4096, 65-byte key', array( unpack( 'N', substr( $body, 16, 4 ) )[1], ord( $body[20] ) ), array( 4096, 65 ) );
check( 'push: decrypts with padding delimiter 0x02', test_decrypt( $body, $ua, $ua_auth ), $message . "\x02" );
check( 'push: fresh salt and key every message', substr( $body, 0, 86 ) !== substr( WebPush::encrypt( $message, WebPush::raw_public( $ua ), $ua_auth ), 0, 86 ), true );
check( 'push: wrong auth secret fails', test_decrypt( $body, $ua, random_bytes( 16 ) ), 'DECRYPT FAILED' );

$vapid = WebPush::generate_vapid_keys();
$req   = WebPush::request(
	array( 'endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz', 'p256dh' => WebPush::b64url( WebPush::raw_public( $ua ) ), 'auth' => WebPush::b64url( $ua_auth ) ),
	$message,
	$vapid + array( 'subject' => 'mailto:admin@example.com' )
);
preg_match( '/^vapid t=([^,]+), k=(.+)$/', $req['headers']['Authorization'], $m );
list( $jh, $jc, $js ) = explode( '.', $m[1] );
$claims = json_decode( WebPush::b64url_decode( $jc ), true );
check( 'vapid: audience is push service origin', $claims['aud'], 'https://fcm.googleapis.com' );
check( 'vapid: subject and expiry', array( $claims['sub'], $claims['exp'] > time() && $claims['exp'] <= time() + 86400 ), array( 'mailto:admin@example.com', true ) );
check( 'vapid: k is the public key', $m[2], $vapid['public'] );
check(
	'vapid: ES256 signature verifies',
	openssl_verify( "$jh.$jc", raw_to_der( WebPush::b64url_decode( $js ) ), WebPush::pem_public( WebPush::b64url_decode( $vapid['public'] ) ), OPENSSL_ALGO_SHA256 ),
	1
);
check( 'push: headers', array( $req['headers']['Content-Encoding'], $req['headers']['TTL'] ), array( 'aes128gcm', '86400' ) );
check( 'b64url round trip', WebPush::b64url_decode( WebPush::b64url( "\xff\xfe\x00abc" ) ), "\xff\xfe\x00abc" );


// ---------------------------------------------------------------- latest update notes
$fcdo_latest = json_encode( array( 'details' => array( 'alert_status' => array(), 'change_description' => 'Latest update: <p>Updated information on protests in the capital.</p>' ) ) );
check( 'latest: fcdo change_description, own label dropped', Sources::parse_fcdo( $fcdo_latest, 'norway' )['latest'], 'Updated information on protests in the capital.' );
check( 'latest: fcdo without change_description', Sources::parse_fcdo( json_encode( array( 'details' => array() ) ), 'norway' )['latest'], null );
$no = array( 'iso3' => 'NOR', 'iso2' => 'NO', 'en' => 'Norway' );
$us_feed = fn( $desc ) => '<rss><channel><item><title>Norway - Level 2: Exercise Increased Caution</title><link>https://travel.state.gov/no.html</link><description>' . htmlspecialchars( $desc ) . '</description></item></channel></rss>';
check( 'latest: usdos reissue note', Sources::parse_usdos( $us_feed( '<p>Reissued after periodic review with minor edits.</p><p>Exercise increased caution due to terrorism.</p>' ), $no )['latest'], 'Reissued after periodic review with minor edits.' );
check( 'latest: usdos without note', Sources::parse_usdos( $us_feed( '<p>Exercise increased caution due to terrorism.</p>' ), $no )['latest'], null );
$au_feed = '<rss xmlns:ta="x"><channel><item><title>Norway</title><link>https://www.smartraveller.gov.au/destinations/europe/norway</link><description>Latest update: We\'ve reviewed our advice for Norway. The level of our advice has not changed. Exercise normal safety precautions.</description><ta:warnings><ta:description>Exercise normal safety precautions</ta:description></ta:warnings></item></channel></rss>';
check( 'latest: dfat two sentences after "Latest update"', Sources::parse_dfat( $au_feed, $no )['latest'], 'We\'ve reviewed our advice for Norway. The level of our advice has not changed.' );
$aa_latest = json_encode( array( 'response' => array( '9' => array( 'content' => '<p>Letzte Änderungen: Aktualisierung im Abschnitt Sicherheit.</p><p>Landesspezifische Hinweise folgen. Weiterer Text.</p>' ) ) ) );
check( 'latest: aa "Letzte Änderungen"', Sources::parse_aa( $aa_latest, '9' )['latest'], 'Aktualisierung im Abschnitt Sicherheit. Landesspezifische Hinweise folgen.' );
$gac_latest = json_encode( array( 'data' => array( 'NO' => array( 'advisory-state' => 0, 'eng' => array( 'advisory-text' => 'Take normal security precautions', 'recent-updates' => '<p>Editorial change.</p>' ) ) ) ) );
check( 'latest: gac recent-updates', Sources::parse_gac( $gac_latest, $no )['latest'], 'Editorial change.' );
$buza_latest = '<d><introduction><![CDATA[<p>Het reisadvies voor Noorwegen heeft kleurcode groen.</p>]]></introduction><content><![CDATA[<h2>Wat is er veranderd?</h2><p>De informatie over natuurbranden is aangepast.</p>]]></content></d>';
check( 'latest: buza "Wat is er veranderd?"', Sources::parse_buza( $buza_latest )['latest'], 'De informatie over natuurbranden is aangepast.' );
check( 'latest: none in plain text', Sources::after_label( 'Nothing to see here.', 'Latest update' ), null );

// ---------------------------------------------------------------- GDACS disaster alerts
$now   = 1790000000;
$when  = fn( $s ) => gmdate( 'D, d M Y H:i:s \G\M\T', $now - $s );
$event = fn( $title, $type, $id, $level, $to, $current, $iso, $names, $severity = '' ) => '<item><title>' . $title . '</title><link>https://www.gdacs.org/report.aspx?eventtype=' . $type . '&amp;eventid=' . $id . '</link>'
	. '<pubDate>' . $to . '</pubDate><gdacs:iscurrent>' . $current . '</gdacs:iscurrent><gdacs:fromdate>' . $to . '</gdacs:fromdate><gdacs:todate>' . $to . '</gdacs:todate>'
	. '<gdacs:eventtype>' . $type . '</gdacs:eventtype><gdacs:alertlevel>' . $level . '</gdacs:alertlevel><gdacs:eventid>' . $id . '</gdacs:eventid>'
	. '<gdacs:severity unit="M" value="6.1">' . $severity . '</gdacs:severity><gdacs:iso3>' . $iso . '</gdacs:iso3><gdacs:country>' . $names . '</gdacs:country>'
	. '<gdacs:resources><gdacs:resource id="x" url="http://example.org/?iso3=XXX"><gdacs:title>UNOSAT maps</gdacs:title></gdacs:resource></gdacs:resources></item>';
$gdacs = '<?xml version="1.0" encoding="utf-8"?><rss version="2.0" xmlns:gdacs="http://www.gdacs.org"><channel><title>GDACS RSS information</title>'
	. $event( 'Orange earthquake alert (Magnitude 6.1M, Depth:10km) in Indonesia', 'EQ', '1001', 'Orange', $when( 7200 ), 'true', 'IDN', 'Indonesia', 'Magnitude 6.1M, Depth:10km' )
	. $event( 'Green earthquake alert (Magnitude 5.5M) in South Africa', 'EQ', '1002', 'Green', $when( 5 * 3600 ), 'true', 'ZAF', 'South Africa' )
	. $event( 'Green earthquake alert (Magnitude 5.0M) in Chile', 'EQ', '1003', 'Green', $when( 5 * 86400 ), 'true', 'CHL', 'Chile' )
	. $event( 'Drought is on going in Bulgaria, Iraq, Iran, Turkey', 'DR', '1004', 'Green', $when( 3600 ), 'true', 'BGR', 'Bulgaria, Iraq, Iran, Turkey' )
	. $event( 'Red drought alert in Bulgaria, Iraq, Iran, Turkey', 'DR', '1005', 'Red', $when( 3600 ), 'true', 'BGR', 'Bulgaria, Iraq, Iran, Turkey' )
	. $event( 'Red alert for tropical cyclone OFFSHORE-26', 'TC', '1006', 'Red', $when( 3600 ), 'true', '', '' )
	. $event( 'Orange flood alert in Norway', 'FL', '1007', 'Orange', $when( 10 * 86400 ), 'false', 'NOR', 'Norway' )
	. '</channel></rss>';
$map    = array_column( $countries = json_decode( file_get_contents( __DIR__ . '/../data/countries.json' ), true ), null, 'iso3' );
$events = Alerts::parse( $gdacs, $map, $now );
check( 'gdacs: kept events, most severe first', array_column( $events, 'id' ), array( 'DR1005', 'EQ1001', 'EQ1002' ) );
check( 'gdacs: all countries of a multi-country event (iso3 has only the first)', $events[0]['countries'], array( 'BGR', 'IRN', 'IRQ', 'TUR' ) );
check( 'gdacs: fields', array( $events[1]['type'], $events[1]['level'], $events[1]['severity'], $events[1]['url'], $events[1]['to'] ), array( 'EQ', 'orange', 'Magnitude 6.1M, Depth:10km', 'https://www.gdacs.org/report.aspx?eventtype=EQ&eventid=1001', gmdate( 'c', $now - 7200 ) ) );
check( 'gdacs: for_country', array_column( Alerts::for_country( $events, 'TUR' ), 'id' ), array( 'DR1005' ) );
check( 'gdacs: nothing for a quiet country', Alerts::for_country( $events, 'NOR' ), array() );
check( 'gdacs: empty channel is a quiet day', Alerts::parse( '<rss><channel><title>GDACS</title></channel></rss>', $map, $now ), array() );
check( 'gdacs: not a feed is an error', ( function () { try { Alerts::parse( '<rss></rss>', array(), 0 ); return 'no error'; } catch ( SourceException $e ) { return $e->getMessage(); } } )(), 'unexpected_response' );

// ---------------------------------------------------------------- countries.json
$countries = json_decode( file_get_contents( __DIR__ . '/../data/countries.json' ), true );
$iso3      = array_column( $countries, 'iso3' );
check( 'countries: unique ISO3', count( $iso3 ), count( array_unique( $iso3 ) ) );
check( 'countries: all fields present', count( array_filter( $countries, fn( $c ) => $c['iso3'] && $c['iso2'] && $c['en'] && $c['de'] && $c['nl'] ) ), count( $countries ) );

echo "\n" . ( $count - $failed ) . "/$count passed\n";
exit( $failed ? 1 : 0 );
