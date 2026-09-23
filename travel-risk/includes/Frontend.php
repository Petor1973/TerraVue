<?php
/**
 * Shortcode [travel_risk]: renders the app container and loads the app.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Frontend {

	public static function init(): void {
		add_shortcode( 'travel_risk', array( self::class, 'shortcode' ) );
		add_action( 'wp_head', array( self::class, 'head' ), 2 );
		add_filter( 'template_include', array( self::class, 'template' ) );
	}

	/** The installed app (?tr_app=1) gets a bare page: no theme header, menu or footer. */
	public static function template( string $template ): string {
		return isset( $_GET['tr_app'] ) && self::is_app_page() ? DIR . '/templates/app.php' : $template; // phpcs:ignore WordPress.Security.NonceVerification
	}

	private static function is_app_page(): bool {
		$post = get_post();
		return is_singular() && $post && has_shortcode( $post->post_content, 'travel_risk' );
	}

	/** PWA tags, only on the page that holds the app. */
	public static function head(): void {
		if ( ! self::is_app_page() ) {
			return;
		}
		printf( '<link rel="manifest" href="%s">' . "\n", esc_url( Pwa::manifest_url() ) );
		printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( setting( 'color_primary' ) ) );
		printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( plugins_url( 'assets/icons/icon-192.png', FILE ) ) );
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	}

	public static function shortcode(): string {
		$page_id = get_the_ID();
		if ( $page_id && ! setting( 'app_page_id' ) ) {
			// First page that shows the app becomes the PWA start page.
			update_option( 'travel_risk_settings', array_merge( settings(), array( 'app_page_id' => $page_id ) ) );
		}

		wp_enqueue_style( 'travel-risk', plugins_url( 'assets/app.css', FILE ), array(), VERSION );
		wp_add_inline_style(
			'travel-risk',
			sprintf(
				'.trisk{--tr-primary:%s;--tr-accent:%s}',
				sanitize_hex_color( setting( 'color_primary' ) ) ?: '#0e3a53',
				sanitize_hex_color( setting( 'color_accent' ) ) ?: '#12a38a'
			)
		);
		wp_enqueue_script( 'travel-risk', plugins_url( 'assets/app.js', FILE ), array(), VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script(
			'travel-risk',
			'window.TRAVEL_RISK = ' . wp_json_encode(
				array(
					'rest'       => esc_url_raw( rest_url( Rest::NS . '/' ) ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'brand'      => setting( 'brand_name' ),
					'languages'  => LANGUAGES,
					'countries'  => plugins_url( 'data/countries.json', FILE ) . '?ver=' . VERSION,
					'worker'     => Pwa::worker_url(),
					'appUrl'     => Pwa::start_url(),
					'icon'       => plugins_url( 'assets/icons/icon-192.png', FILE ),
					'privacyUrl' => get_privacy_policy_url(),
					'news'       => 'none' === setting( 'news_provider' ) ? false : setting( 'news_provider' ),
				)
			) . ';',
			'before'
		);

		return '<div id="travel-risk-app" class="trisk alignwide" data-loading="1"><noscript>JavaScript is required.</noscript></div>';
	}
}
