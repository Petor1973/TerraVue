<?php
/**
 * Progressive Web App: web app manifest and service worker.
 *
 * Both are served from the site root (/?travel_risk_pwa=...) so the service
 * worker scope covers the app page without rewrite rules. The worker itself
 * only touches the app page, the plugin's assets and this plugin's REST
 * routes; the rest of the WordPress site is left alone.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Pwa {

	/** Matches both /wp-json/travel-risk/v1/... and ?rest_route=/travel-risk/v1/... */
	const NS_MARKER = 'travel-risk/v1/';

	public static function init(): void {
		add_action( 'init', array( self::class, 'serve' ), 1 );
	}

	public static function manifest_url(): string {
		return add_query_arg( 'travel_risk_pwa', 'manifest', home_url( '/' ) );
	}

	/** Start URL of the installed app: the app page in the bare app template (see Frontend). */
	public static function start_url(): string {
		return add_query_arg( 'tr_app', '1', app_url() );
	}

	public static function worker_url(): string {
		return add_query_arg( 'travel_risk_pwa', 'sw', home_url( '/' ) );
	}

	public static function serve(): void {
		$what = isset( $_GET['travel_risk_pwa'] ) ? sanitize_key( $_GET['travel_risk_pwa'] ) : '';
		if ( 'manifest' === $what ) {
			self::send( 'application/manifest+json', wp_json_encode( self::manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
		if ( 'sw' === $what ) {
			header( 'Service-Worker-Allowed: /' );
			self::send( 'text/javascript', self::worker() );
		}
	}

	private static function send( string $type, string $body ): void {
		nocache_headers();
		header( "Content-Type: $type; charset=utf-8" );
		header( 'X-Content-Type-Options: nosniff' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON / JS built from trusted values
		exit;
	}

	public static function manifest(): array {
		$icons = plugins_url( 'assets/icons/', FILE );
		return array(
			'name'             => setting( 'brand_name' ),
			'short_name'       => setting( 'brand_name' ),
			'description'      => 'Travel advice and security news per country.',
			'id'               => wp_make_link_relative( app_url() ),
			'start_url'        => self::start_url(),
			'scope'            => home_url( '/' ),
			'display'          => 'standalone',
			'background_color' => '#f4f6f8',
			'theme_color'      => setting( 'color_primary' ),
			'icons'            => array(
				array( 'src' => $icons . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png' ),
				array( 'src' => $icons . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png' ),
				array( 'src' => $icons . 'maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
			),
		);
	}

	private static function worker(): string {
		$config = array(
			'cache'  => 'travel-risk-' . VERSION . '-' . substr( md5( app_url() ), 0, 6 ),
			'app'    => strtok( app_url(), '#' ),
			'assets' => plugins_url( 'assets/', FILE ),
			'api'    => self::NS_MARKER,
			'shell'  => array(
				strtok( app_url(), '#' ),
				self::start_url(),
				plugins_url( 'assets/app.js', FILE ) . '?ver=' . VERSION,
				plugins_url( 'assets/app.css', FILE ) . '?ver=' . VERSION,
				plugins_url( 'data/countries.json', FILE ) . '?ver=' . VERSION,
				plugins_url( 'assets/icons/icon-192.png', FILE ),
			),
		);
		return 'const CONFIG = ' . wp_json_encode( $config, JSON_UNESCAPED_SLASHES ) . ";\n"
			. file_get_contents( DIR . '/assets/sw.js' );
	}
}
