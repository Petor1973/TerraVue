<?php
/**
 * Integration smoke test inside a real WordPress install (plugin active).
 * Run: wp eval-file wp-content/plugins/travel-risk/tests/wp-smoke.php
 * External sources are faked (tests/fake-sources.php), mail is captured.
 */

require_once __DIR__ . '/fake-sources.php';

// wp eval-file runs this inside a function, so the counter lives in $GLOBALS explicitly.
$GLOBALS['travel_risk_failed'] = 0;
function ok( string $name, bool $cond, $info = '' ): void {
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $name . ( $cond ? '' : '  ' . json_encode( $info ) ) . "\n";
	$GLOBALS['travel_risk_failed'] += $cond ? 0 : 1;
}
function call( string $method, string $route, array $params = array() ): WP_REST_Response {
	$req = new WP_REST_Request( $method, '/travel-risk/v1/' . $route );
	$req->set_header( 'Content-Type', 'application/json' );
	$req->set_body( wp_json_encode( $params ) );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	return rest_do_request( $req );
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%travel_risk%' OR option_name LIKE '_transient_timeout_%travel_risk%'" );
wp_set_current_user( 0 );
$email = 'tester+' . wp_rand() . '@example.com';

// registration required: anonymous gets 401
$r = call( 'GET', 'advice/SAU' );
ok( 'advice requires sign-in', 401 === $r->get_status(), $r->get_status() );
ok( 'me: logged out', false === call( 'GET', 'me' )->get_data()['loggedIn'] );

// login request validation
ok( 'login without consent refused', 400 === call( 'POST', 'login', array( 'email' => $email, 'consent' => false ) )->get_status() );
ok( 'login with bad address refused', 400 === call( 'POST', 'login', array( 'email' => 'nope', 'consent' => true ) )->get_status() );
ok( 'honeypot: pretend success, no mail', true === call( 'POST', 'login', array( 'email' => $email, 'consent' => true, 'website' => 'x' ) )->get_data()['sent'] && ! get_user_by( 'email', $email ) );

@unlink( WP_CONTENT_DIR . '/last-mail.txt' );
$r = call( 'POST', 'login', array( 'email' => $email, 'consent' => true, 'lang' => 'de' ) );
ok( 'login link requested', true === $r->get_data()['sent'] );
$mail = (string) @file_get_contents( WP_CONTENT_DIR . '/last-mail.txt' );
ok( 'mail sent to address', str_starts_with( $mail, $email ) );
ok( 'mail in German', str_contains( $mail, 'Anmeldelink' ) );
ok( 'no account before confirmation', ! get_user_by( 'email', $email ) );
preg_match( '/#tr-login=([a-f0-9]{64})/', $mail, $m );
ok( 'mail contains token in fragment', ! empty( $m[1] ) );

// verify
ok( 'bad token refused', 400 === call( 'POST', 'login/verify', array( 'token' => 'abc' ) )->get_status() );
ok( 'unknown token expired', 410 === call( 'POST', 'login/verify', array( 'token' => str_repeat( 'a', 64 ) ) )->get_status() );
$form = new WP_REST_Request( 'POST', '/travel-risk/v1/login/verify' );
$form->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
$form->set_param( 'token', $m[1] );
ok( 'form post refused (login CSRF)', 400 === rest_do_request( $form )->get_status() );
$r = call( 'POST', 'login/verify', array( 'token' => $m[1] ) );
ok( 'token accepted', 200 === $r->get_status(), $r->get_data() );
$user = get_user_by( 'email', $email );
ok( 'account created as subscriber', $user && in_array( 'subscriber', $user->roles, true ) );
ok( 'consent timestamp stored', (bool) get_user_meta( $user->ID, 'travel_risk_consent', true ) );
ok( 'language from request stored', 'de' === get_user_meta( $user->ID, 'travel_risk_lang', true ) );
ok( 'token single use', 410 === call( 'POST', 'login/verify', array( 'token' => $m[1] ) )->get_status() );

// sign-in code (second address; the link above is already used)
wp_set_current_user( 0 );
$email2 = 'code+' . wp_rand() . '@example.com';
call( 'POST', 'login', array( 'email' => $email2, 'consent' => true, 'lang' => 'nl' ) );
$mail = (string) @file_get_contents( WP_CONTENT_DIR . '/last-mail.txt' );
preg_match( '/^\s+(\d{6})$/m', $mail, $c );
ok( 'mail contains 6-digit code (NL)', ! empty( $c[1] ) && str_contains( $mail, 'code in de app' ) );
$wrong = str_pad( (string) ( ( (int) $c[1] + 1 ) % 1000000 ), 6, '0', STR_PAD_LEFT );
ok( 'wrong code refused', 400 === call( 'POST', 'login/verify', array( 'email' => $email2, 'code' => $wrong ) )->get_status() );
$r = call( 'POST', 'login/verify', array( 'email' => $email2, 'code' => $c[1] ) );
ok( 'right code signs in and creates account', 200 === $r->get_status() && get_user_by( 'email', $email2 ), $r->get_data() );
ok( 'code single use', 410 === call( 'POST', 'login/verify', array( 'email' => $email2, 'code' => $c[1] ) )->get_status() );
preg_match( '/#tr-login=([a-f0-9]{64})/', $mail, $m2 );
ok( 'link of same mail is spent after code use', 410 === call( 'POST', 'login/verify', array( 'token' => $m2[1] ) )->get_status() );

wp_set_current_user( 0 );
$email3 = 'guess+' . wp_rand() . '@example.com';
call( 'POST', 'login', array( 'email' => $email3, 'consent' => true ) );
preg_match( '/^\s+(\d{6})$/m', (string) @file_get_contents( WP_CONTENT_DIR . '/last-mail.txt' ), $c3 );
$statuses = array();
for ( $i = 0; $i < 5; $i++ ) {
	$statuses[] = call( 'POST', 'login/verify', array( 'email' => $email3, 'code' => $c3[1] === '000000' ? '111111' : '000000' ) )->get_status();
}
ok( 'five wrong guesses expire the code', array( 400, 400, 400, 400, 410 ) === $statuses, $statuses );
ok( 'even the right code fails after that', 410 === call( 'POST', 'login/verify', array( 'email' => $email3, 'code' => $c3[1] ) )->get_status() );
wp_delete_user( get_user_by( 'email', $email2 )->ID );

// signed in
wp_set_current_user( $user->ID );
$r = call( 'PUT', 'me', array( 'countries' => array( 'sau', 'NOR', 'XXX', 'SAU' ), 'lang' => 'nl' ) );
ok( 'countries saved, validated, deduplicated', array( 'SAU', 'NOR' ) === $r->get_data()['countries'], $r->get_data() );
ok( 'language saved', 'nl' === $r->get_data()['lang'] );
ok( 'no source chosen yet', null === $r->get_data()['source'] );
ok( 'default source follows language (nl -> buza)', 'buza' === TravelRisk\user_source( $user->ID ) );
$r = call( 'PUT', 'me', array( 'source' => 'aa' ) );
ok( 'source saved independently of language', 'aa' === $r->get_data()['source'] && 'nl' === $r->get_data()['lang'] && 'aa' === TravelRisk\user_source( $user->ID ) );
ok( 'unknown source refused', 400 === call( 'PUT', 'me', array( 'source' => 'xx' ) )->get_status() );
$d = call( 'GET', 'advice/SAU', array( 'source' => 'aa', 'lang' => 'en' ) )->get_data();
ok( 'advice source parameter wins over language', 'aa' === $d['source'], $d );
ok( 'advice with unknown source refused', 400 === call( 'GET', 'advice/SAU', array( 'source' => 'xx' ) )->get_status() );

$r = call( 'GET', 'advice/SAU', array( 'lang' => 'en' ) );
$d = $r->get_data();
ok( 'advice en = FCDO', 200 === $r->get_status() && 'fcdo' === $d['source'] && 2 === $d['level'] && 3 === $d['maxLevel'], $d );
$d = call( 'GET', 'advice/SAU', array( 'lang' => 'de' ) )->get_data();
ok( 'advice de = Auswärtiges Amt', 'aa' === $d['source'] && 4 === $d['maxLevel'], $d );
$d = call( 'GET', 'advice/SAU', array( 'lang' => 'nl' ) )->get_data();
ok( 'advice nl = BuZa', 'buza' === $d['source'] && 2 === $d['level'] && 4 === $d['maxLevel'], $d );
ok( 'unknown country 404', 404 === call( 'GET', 'advice/XYZ' )->get_status() );
ok( 'home country for source 404', 404 === call( 'GET', 'advice/NLD', array( 'lang' => 'nl' ) )->get_status() );

$r = call( 'GET', 'news/SAU' );
ok( 'news', 200 === $r->get_status() && 2 === count( $r->get_data()['items'] ), $r->get_data() );

// PWA
$page = (int) TravelRisk\setting( 'app_page_id' );
ok( 'manifest start_url is app page in app mode', TravelRisk\Pwa::manifest()['start_url'] === add_query_arg( 'tr_app', '1', $page ? get_permalink( $page ) : home_url( '/' ) ) );

// push devices
$ua   = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
$keys = array( 'p256dh' => TravelRisk\WebPush::b64url( TravelRisk\WebPush::raw_public( $ua ) ), 'auth' => TravelRisk\WebPush::b64url( random_bytes( 16 ) ) );
$key  = call( 'GET', 'push/key' )->get_data()['publicKey'];
ok( 'vapid public key is a P-256 point', 65 === strlen( TravelRisk\WebPush::b64url_decode( $key ) ) );
ok( 'vapid key stable', $key === call( 'GET', 'push/key' )->get_data()['publicKey'] );
ok( 'push endpoint on unknown host refused (no SSRF)', 400 === call( 'POST', 'push', array( 'endpoint' => 'https://169.254.169.254/latest', 'keys' => $keys ) )->get_status() );
ok( 'push endpoint over http refused', 400 === call( 'POST', 'push', array( 'endpoint' => 'http://fcm.googleapis.com/fcm/send/x', 'keys' => $keys ) )->get_status() );
ok( 'push with bad keys refused', 400 === call( 'POST', 'push', array( 'endpoint' => 'https://fcm.googleapis.com/fcm/send/x', 'keys' => array( 'p256dh' => 'abc', 'auth' => 'def' ) ) )->get_status() );
$r = call( 'POST', 'push', array( 'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-1', 'keys' => $keys ) );
ok( 'device registered', 200 === $r->get_status() && 1 === $r->get_data()['pushDevices'], $r->get_data() );
call( 'POST', 'push', array( 'endpoint' => 'https://web.push.apple.com/device-2', 'keys' => $keys ) );
call( 'POST', 'push', array( 'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-1', 'keys' => $keys ) );
ok( 'same device not stored twice', 2 === call( 'GET', 'me' )->get_data()['pushDevices'] );

// Capture pushes; device-2 answers 410 (gone) and must be removed.
$GLOBALS['travel_risk_pushes'] = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( ! preg_match( '#^https://(fcm\.googleapis\.com|web\.push\.apple\.com)/#', $url ) ) {
		return $pre;
	}
	$GLOBALS['travel_risk_pushes'][] = array( 'url' => $url, 'args' => $args );
	$code = str_contains( $url, 'device-2' ) ? 410 : 201;
	return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
}, 5, 3 );

$r = call( 'POST', 'push/test' );
ok( 'test push: 1 sent, 1 gone', array( 'sent' => 1, 'failed' => 1 ) === $r->get_data(), $r->get_data() );
ok( 'gone device removed', 1 === call( 'GET', 'me' )->get_data()['pushDevices'] );
$p = $GLOBALS['travel_risk_pushes'][0];
ok( 'push request is encrypted and signed', 'aes128gcm' === $p['args']['headers']['Content-Encoding'] && str_starts_with( $p['args']['headers']['Authorization'], 'vapid t=' ) && ! str_contains( $p['args']['body'], 'Notifications' ) );

// e-mail preference
ok( 'e-mail notifications on', true === call( 'PUT', 'me', array( 'notifyEmail' => true ) )->get_data()['notifyEmail'] );

// hourly check: first run is the baseline, a change triggers push + e-mail
update_user_meta( $user->ID, TravelRisk\Auth::META_LANG, 'nl' );
update_user_meta( $user->ID, TravelRisk\Auth::META_SOURCE, 'fcdo' ); // Dutch interface, UK advice
delete_option( TravelRisk\Notify::OPT_SNAPSHOT );
$GLOBALS['travel_risk_pushes'] = array();
@unlink( WP_CONTENT_DIR . '/last-mail.txt' );
TravelRisk\Notify::check();
$snap = get_option( TravelRisk\Notify::OPT_SNAPSHOT );
ok( 'baseline stored per chosen source and country', isset( $snap['fcdo:SAU'], $snap['fcdo:NOR'] ) && ! isset( $snap['buza:SAU'] ) && 2 === $snap['fcdo:SAU']['level'], $snap );
ok( 'no notification on baseline', ! $GLOBALS['travel_risk_pushes'] && ! file_exists( WP_CONTENT_DIR . '/last-mail.txt' ) );

TravelRisk\Notify::check();
ok( 'no notification without change', ! $GLOBALS['travel_risk_pushes'] );

$raise = function ( $pre, $args, $url ) {
	if ( ! str_contains( $url, 'foreign-travel-advice/saudi-arabia' ) ) {
		return $pre;
	}
	$body = wp_json_encode( array( 'description' => 'Saudi', 'public_updated_at' => '2026-09-24T08:00:00Z', 'details' => array( 'alert_status' => array( 'avoid_all_travel_to_whole_country' ), 'parts' => array() ) ) );
	return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $raise, 6, 3 );
@unlink( WP_CONTENT_DIR . '/mail-log.txt' );
TravelRisk\Notify::check();
remove_filter( 'pre_http_request', $raise, 6 );
ok( 'change: push sent to this user\'s device', 1 === count( array_filter( $GLOBALS['travel_risk_pushes'], fn( $p ) => str_contains( $p['url'], 'device-1' ) ) ), count( $GLOBALS['travel_risk_pushes'] ) );
// Other test users may follow the same country; find this user's mail in the log.
$mail = '';
foreach ( explode( "\n-----\n", (string) @file_get_contents( WP_CONTENT_DIR . '/mail-log.txt' ) ) as $m ) {
	$mail = str_starts_with( $m, $email ) ? $m : $mail;
}
ok( 'change: e-mail sent in the user\'s language', str_starts_with( $mail, $email ) && str_contains( $mail, 'Reisadvies gewijzigd: Saoedi-Arabië' ) && str_contains( $mail, 'Let op → Niet reizen' ), $mail );
ok( 'snapshot updated', 4 === get_option( TravelRisk\Notify::OPT_SNAPSHOT )['fcdo:SAU']['level'] );
ok( 'app cache refreshed by check', 4 === call( 'GET', 'advice/SAU', array( 'lang' => 'en' ) )->get_data()['level'] );

$msg = TravelRisk\Notify::message( 'de', TravelRisk\countries()['SAU'], array( 'level' => 2, 'maxLevel' => 2 ), array( 'level' => 2, 'maxLevel' => 4 ) );
ok( 'message for regional change (DE)', 'Reisehinweis geändert: Saudi-Arabien' === $msg['title'] && str_contains( $msg['body'], 'Erhöhte Vorsicht (max) → Reisewarnung (max)' ), $msg );

// remove device via DELETE with JSON body
$r = call( 'DELETE', 'push', array( 'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-1' ) );
ok( 'device removed', 0 === $r->get_data()['pushDevices'], $r->get_data() );
call( 'POST', 'push', array( 'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-1', 'keys' => $keys ) );

// privacy tools
$export = TravelRisk\Privacy::export( $email );
ok( 'privacy export has countries', str_contains( wp_json_encode( $export ), 'SAU, NOR' ) );
ok( 'privacy export has advice source', str_contains( wp_json_encode( $export ), '"fcdo"' ) );
ok( 'privacy export has notification data', str_contains( wp_json_encode( $export ), 'fcm.googleapis.com' ) && str_contains( wp_json_encode( $export ), '"on"' ) );

// delete own account
$r = call( 'DELETE', 'me' );
ok( 'account deleted', 200 === $r->get_status() && ! get_user_by( 'email', $email ), $r->get_data() );

// admins can never sign in by link
wp_set_current_user( 0 );
@unlink( WP_CONTENT_DIR . '/last-mail.txt' );
call( 'POST', 'login', array( 'email' => get_option( 'admin_email' ), 'consent' => true ) );
ok( 'no link for admin accounts', ! file_exists( WP_CONTENT_DIR . '/last-mail.txt' ) );

$failed = $GLOBALS['travel_risk_failed'];
echo $failed ? "\n$failed FAILED\n" : "\nall passed\n";
exit( $failed ? 1 : 0 );
