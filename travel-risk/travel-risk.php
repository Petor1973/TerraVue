<?php
/**
 * Plugin Name:       Terravue – Travel Risk Monitor
 * Description:       Official travel advice (NL, UK, DE) and recent security news per country, as an installable web app. Place the shortcode [travel_risk] on a page.
 * Version:           0.5.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Peter Langerak
 * License:           Proprietary
 * Text Domain:       travel-risk
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.5.0';
const FILE    = __FILE__;
const DIR     = __DIR__;

/** UI languages; the first is the default. */
const LANGUAGES = array( 'en', 'de', 'nl' );

require_once DIR . '/includes/Sources.php';
require_once DIR . '/includes/News.php';
require_once DIR . '/includes/WebPush.php';
require_once DIR . '/includes/Notify.php';
require_once DIR . '/includes/Auth.php';
require_once DIR . '/includes/Rest.php';
require_once DIR . '/includes/Pwa.php';
require_once DIR . '/includes/Frontend.php';
require_once DIR . '/includes/Privacy.php';
require_once DIR . '/includes/Admin.php';

function defaults(): array {
	return array(
		'brand_name'           => 'Terravue',
		'color_primary'        => '#0e3a53',
		'color_accent'         => '#12a38a',
		'require_registration' => 1,
		'news_provider'        => 'gdelt',
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
function http_get( string $url ): array {
	$res = wp_remote_get(
		$url,
		array(
			'timeout'    => 15,
			'user-agent' => 'TravelRisk/' . VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		throw new SourceException( 'unreachable' );
	}
	return array(
		'status' => (int) wp_remote_retrieve_response_code( $res ),
		'body'   => (string) wp_remote_retrieve_body( $res ),
	);
}

/** Caches successful results only; failures are retried on the next request. */
function cached( string $key, callable $fn ) {
	$key = 'travel_risk_' . md5( $key );
	$hit = get_transient( $key );
	if ( false !== $hit ) {
		return $hit;
	}
	$value = $fn();
	cache_put( $key, $value, false );
	return $value;
}

function cache_put( string $key, $value, bool $hash = true ): void {
	$key = $hash ? 'travel_risk_' . md5( $key ) : $key;
	set_transient( $key, $value, max( 5, (int) setting( 'cache_minutes' ) ) * MINUTE_IN_SECONDS );
}

/** URL of the page holding the app (used as PWA start_url and magic link target). */
function app_url(): string {
	$id = (int) setting( 'app_page_id' );
	return $id && get_post_status( $id ) === 'publish' ? get_permalink( $id ) : home_url( '/' );
}

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
	delete_option( 'travel_risk_gdelt_last' );
	Notify::unschedule();
	delete_metadata( 'user', 0, Notify::META_PUSH, '', true );
	delete_metadata( 'user', 0, Notify::META_EMAIL, '', true );
	delete_metadata( 'user', 0, Auth::META_COUNTRIES, '', true );
	delete_metadata( 'user', 0, Auth::META_LANG, '', true );
	delete_metadata( 'user', 0, Auth::META_CONSENT, '', true );
	delete_metadata( 'user', 0, Auth::META_SOURCE, '', true );
	// Accounts themselves are ordinary WordPress users and are left in place on purpose.
}
