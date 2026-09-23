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

// signed in
wp_set_current_user( $user->ID );
$r = call( 'PUT', 'me', array( 'countries' => array( 'sau', 'NOR', 'XXX', 'SAU' ), 'lang' => 'nl' ) );
ok( 'countries saved, validated, deduplicated', array( 'SAU', 'NOR' ) === $r->get_data()['countries'], $r->get_data() );
ok( 'language saved', 'nl' === $r->get_data()['lang'] );

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
ok( 'manifest start_url is app page', $page ? TravelRisk\Pwa::manifest()['start_url'] === get_permalink( $page ) : TravelRisk\Pwa::manifest()['start_url'] === home_url( '/' ) );

// privacy tools
$export = TravelRisk\Privacy::export( $email );
ok( 'privacy export has countries', str_contains( wp_json_encode( $export ), 'SAU, NOR' ) );

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
