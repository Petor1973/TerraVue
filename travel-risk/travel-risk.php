<?php
/**
 * Plugin Name:       Terravue – Travel Risk Monitor
 * Description:       Official travel advice (NL, UK, DE, US, CA) and disaster alerts (GDACS) per country, as an installable web app. Place the shortcode [travel_risk] on a page.
 * Version:           0.9.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Peter Langerak
 * License:           Proprietary
 * Text Domain:       travel-risk
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.9.0';
const FILE    = __FILE__;
const DIR     = __DIR__;

/** UI languages; the first is the default. */
const LANGUAGES = array( 'en', 'de', 'nl' );

require_once DIR . '/includes/Sources.php';
require_once DIR . '/includes/News.php';
require_once DIR . '/includes/Alerts.php';
require_once DIR . '/includes/WebPush.php';
require_once DIR . '/includes/Notify.php';
require_once DIR . '/includes/Auth.php';
require_once DIR . '/includes/Rest.php';
require_once DIR . '/includes/Pwa.php';
require_once DIR . '/includes/Frontend.php';
require_once DIR . '/includes/Privacy.php';
require_once DIR . '/includes/Admin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once DIR . '/includes/Cli.php';
	\WP_CLI::add_command( 'travel-risk', Cli::class );
}

function defaults(): array {
	return array(
		'brand_name'           => 'Terravue',
		'color_primary'        => '#0e3a53',
		'color_accent'         => '#12a38a',
		'require_registration' => 1,
		'news_provider'        => 'none', // official updates + GDACS first; GDELT/Google News are opt-in
		'alerts'               => 1,      // GDACS disaster alerts
		'cache_minutes'        => 60,
		'news_hours'           => 48,
		'app_page_id'          => 0,
	);
}

function settings(): array {
	return array_merge( defaults(), (array) get_option( 'travel_risk_settings', array() ) );
}

function setting( string $key ) {
	return settings()[ $key ] ?? null;
}

/** @return array<string,array> ISO3 => country. */
function countries(): array {
	static $map = null;
	if ( null === $map ) {
		$list = json_decode( (string) file_get_contents( DIR . '/data/countries.json' ), true );
		$map  = array_column( $list, null, 'iso3' );
	}
	return $map;
}

/** Array stored in user meta; get_user_meta() returns '' when nothing is stored. */
function user_list( int $user_id, string $key ): array {
	$value = get_user_meta( $user_id, $key, true );
	return is_array( $value ) ? array_values( $value ) : array();
}

/**
 * Advice source a user follows: their own choice, else the default for their language.
 * The source reflects whose government advice applies to them (nationality/employer),
 * deliberately not the device location (no location tracking).
 */
function user_source( int $user_id ): string {
	$source = get_user_meta( $user_id, Auth::META_SOURCE, true );
	return Sources::valid( $source ) ? $source : Sources::for_language( language( get_user_meta( $user_id, Auth::META_LANG, true ) ) );
}

function language( $value ): string {
	return in_array( $value, LANGUAGES, true ) ? $value : LANGUAGES[0];
}

/** HTTP for Sources/News, through the WordPress HTTP API. */
function http_get( string $url, int $timeout = 15 ): array {
	$res = wp_remote_get(
		$url,
		array(
			'timeout'    => $timeout,
			'user-agent' => 'TravelRisk/' . VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		throw SourceException::with( 'unreachable', $res->get_error_message() );
	}
	return array(
		'status' => (int) wp_remote_retrieve_response_code( $res ),
		'body'   => (string) wp_remote_retrieve_body( $res ),
	);
}

/** Sources with the WordPress HTTP API and a shared cache for whole feeds (US, CA, DE list). */
function sources(): Sources {
	return new Sources( __NAMESPACE__ . '\\http_get', __NAMESPACE__ . '\\cached' );
}

/**
 * Current GDACS disaster alerts for the countries we list, shared by the app and the hourly
 * check. One feed for the whole world, re-read every Alerts::CACHE_MINUTES.
 */
function disaster_alerts(): array {
	return cached(
		'alerts:gdacs',
		function () {
			$res = http_get( Alerts::FEED, 20 );
			if ( $res['status'] < 200 || $res['status'] >= 300 ) {
				throw new SourceException( 'http_' . $res['status'] );
			}
			return Alerts::parse( $res['body'], countries(), time() );
		},
		Alerts::CACHE_MINUTES
	);
}

/** Caches successful results only; failures are retried on the next request. */
function cached( string $key, callable $fn, ?int $minutes = null ) {
	$key = 'travel_risk_' . md5( $key );
	$hit = get_transient( $key );
	if ( false !== $hit ) {
		return $hit;
	}
	$value = $fn();
	cache_put( $key, $value, false, $minutes );
	return $value;
}

function cache_put( string $key, $value, bool $hash = true, ?int $minutes = null ): void {
	$key = $hash ? 'travel_risk_' . md5( $key ) : $key;
	set_transient( $key, $value, max( 5, $minutes ?? (int) setting( 'cache_minutes' ) ) * MINUTE_IN_SECONDS );
}

/** URL of the page holding the app (used as PWA start_url and magic link target). */
function app_url(): string {
	$id = (int) setting( 'app_page_id' );
	return $id && get_post_status( $id ) === 'publish' ? get_permalink( $id ) : home_url( '/' );
}

/**
 * After an update the way levels are read may have changed. Clear cached advice and the
 * notification baseline so users are not notified of changes that are really parser fixes;
 * the next hourly check records a fresh baseline.
 */
function maybe_upgrade(): void {
	if ( get_option( 'travel_risk_version' ) === VERSION ) {
		return;
	}
	global $wpdb;
	$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_travel_risk_' ) . '%' ) );
	foreach ( $names as $name ) {
		// Cached advice/news/feeds only (md5 keys); sign-in tokens and rate limits stay.
		if ( preg_match( '/^_transient_(travel_risk_[a-f0-9]{32})$/', $name, $m ) ) {
			delete_transient( $m[1] );
		}
	}
	delete_option( Notify::OPT_SNAPSHOT );
	update_option( 'travel_risk_version', VERSION, false );
}
add_action( 'init', __NAMESPACE__ . '\\maybe_upgrade', 5 );

Auth::init();
Notify::init();
Rest::init();
Pwa::init();
Frontend::init();
Privacy::init();
Admin::init();

register_deactivation_hook( FILE, array( Notify::class, 'unschedule' ) );
register_uninstall_hook( FILE, __NAMESPACE__ . '\\uninstall' );

function uninstall(): void {
	delete_option( 'travel_risk_settings' );
	delete_option( Notify::OPT_VAPID );
	delete_option( Notify::OPT_SNAPSHOT );
	delete_option( Notify::OPT_LAST );
	delete_option( Notify::OPT_LAST_STATS );
	delete_option( Notify::OPT_ALERTS_SEEN );
	delete_option( 'travel_risk_gdelt_last' );
	delete_option( 'travel_risk_version' );
	Notify::unschedule();
	delete_metadata( 'user', 0, Notify::META_PUSH, '', true );
	delete_metadata( 'user', 0, Notify::META_EMAIL, '', true );
	delete_metadata( 'user', 0, Auth::META_COUNTRIES, '', true );
	delete_metadata( 'user', 0, Auth::META_LANG, '', true );
	delete_metadata( 'user', 0, Auth::META_CONSENT, '', true );
	delete_metadata( 'user', 0, Auth::META_SOURCE, '', true );
	// Accounts themselves are ordinary WordPress users and are left in place on purpose.
}
