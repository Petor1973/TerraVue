<?php
/**
 * REST API, namespace travel-risk/v1.
 *
 *   GET    /advice/{ISO3}?lang=en   Travel advice from the source for that language
 *   GET    /news/{ISO3}             Recent security news
 *   GET    /me                      Signed-in state, saved countries and language
 *   PUT    /me                      Save countries and/or language
 *   DELETE /me                      Delete own account and data
 *   POST   /login                   Request a sign-in link by e-mail
 *   POST   /login/verify            Exchange the link token for a session
 *   POST   /logout
 *
 * Error responses carry a short code (e.g. "rate_limited") that the app translates.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Rest {

	const NS            = 'travel-risk/v1';
	const MAX_COUNTRIES = 60;
	const GDELT_SPACING = 6; // seconds between GDELT calls; it answers 429 below ~5 s

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		$iso = '(?P<iso>[A-Za-z]{3})';

		register_rest_route( self::NS, "/advice/$iso", array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'advice' ),
			'permission_callback' => array( self::class, 'can_read' ),
			'args'                => array( 'lang' => array( 'type' => 'string', 'default' => LANGUAGES[0] ) ),
		) );
		register_rest_route( self::NS, "/news/$iso", array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'news' ),
			'permission_callback' => array( self::class, 'can_read' ),
		) );
		register_rest_route( self::NS, '/me', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'me' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( self::class, 'save_me' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'countries' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'lang'      => array( 'type' => 'string', 'enum' => LANGUAGES ),
				),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( self::class, 'delete_me' ),
				'permission_callback' => 'is_user_logged_in',
			),
		) );
		register_rest_route( self::NS, '/login', array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'login' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'email'   => array( 'type' => 'string', 'required' => true ),
				'consent' => array( 'type' => 'boolean', 'required' => true ),
				'lang'    => array( 'type' => 'string', 'default' => LANGUAGES[0] ),
				'website' => array( 'type' => 'string', 'default' => '' ), // honeypot
			),
		) );
		register_rest_route( self::NS, '/login/verify', array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'verify' ),
			'permission_callback' => '__return_true',
			'args'                => array( 'token' => array( 'type' => 'string', 'required' => true ) ),
		) );
		register_rest_route( self::NS, '/logout', array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'logout' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function can_read(): bool {
		return ! setting( 'require_registration' ) || is_user_logged_in();
	}

	private static function country( \WP_REST_Request $req ) {
		$iso = strtoupper( $req['iso'] );
		return countries()[ $iso ] ?? new \WP_Error( 'unknown_country', 'unknown_country', array( 'status' => 404 ) );
	}

	private static function fail( SourceException $e ): \WP_Error {
		$status = 'not_found' === $e->getMessage() || 'no_home_advice' === $e->getMessage() ? 404 : 502;
		return new \WP_Error( $e->getMessage(), $e->getMessage(), array( 'status' => $status ) );
	}

	public static function advice( \WP_REST_Request $req ) {
		$country = self::country( $req );
		if ( is_wp_error( $country ) ) {
			return $country;
		}
		$source = Sources::for_language( language( $req['lang'] ) );
		try {
			$data = cached(
				"advice:$source:{$country['iso3']}",
				fn() => ( new Sources( __NAMESPACE__ . '\\http_get' ) )->advice( $source, $country )
			);
		} catch ( SourceException $e ) {
			return self::fail( $e );
		}
		return rest_ensure_response( $data + array( 'sourceName' => Sources::NAMES[ $source ] ) );
	}

	public static function news( \WP_REST_Request $req ) {
		$country  = self::country( $req );
		$provider = setting( 'news_provider' );
		if ( is_wp_error( $country ) ) {
			return $country;
		}
		if ( 'none' === $provider ) {
			return new \WP_Error( 'news_disabled', 'news_disabled', array( 'status' => 404 ) );
		}
		$hours = (int) setting( 'news_hours' );
		try {
			$data = cached(
				"news:$provider:$hours:{$country['iso3']}",
				function () use ( $provider, $country, $hours ) {
					if ( 'gdelt' === $provider ) {
						self::gdelt_throttle();
					}
					return ( new News( __NAMESPACE__ . '\\http_get' ) )->fetch( $provider, $country, $hours );
				}
			);
		} catch ( SourceException $e ) {
			return self::fail( $e );
		}
		return rest_ensure_response( $data + array( 'hours' => $hours ) );
	}

	/** Spaces GDELT calls across all visitors; waits at most GDELT_SPACING seconds. */
	private static function gdelt_throttle(): void {
		$last = (float) get_option( 'travel_risk_gdelt_last', 0 );
		$wait = $last + self::GDELT_SPACING - microtime( true );
		if ( $wait > 0 ) {
			usleep( (int) ( min( $wait, self::GDELT_SPACING ) * 1e6 ) );
		}
		update_option( 'travel_risk_gdelt_last', microtime( true ), false );
	}

	public static function me() {
		if ( ! is_user_logged_in() ) {
			return array(
				'loggedIn'             => false,
				'registrationRequired' => (bool) setting( 'require_registration' ),
			);
		}
		$user = wp_get_current_user();
		return array(
			'loggedIn'             => true,
			'registrationRequired' => (bool) setting( 'require_registration' ),
			'email'                => $user->user_email,
			'countries'            => array_values( (array) get_user_meta( $user->ID, Auth::META_COUNTRIES, true ) ),
			'lang'                 => get_user_meta( $user->ID, Auth::META_LANG, true ) ?: null,
			'canDelete'            => ! current_user_can( 'edit_posts' ),
		);
	}

	public static function save_me( \WP_REST_Request $req ) {
		$id = get_current_user_id();
		if ( null !== $req['countries'] ) {
			$valid = array_values( array_unique( array_filter(
				array_map( 'strtoupper', (array) $req['countries'] ),
				fn( $iso ) => isset( countries()[ $iso ] )
			) ) );
			update_user_meta( $id, Auth::META_COUNTRIES, array_slice( $valid, 0, self::MAX_COUNTRIES ) );
		}
		if ( null !== $req['lang'] ) {
			update_user_meta( $id, Auth::META_LANG, language( $req['lang'] ) );
		}
		return self::me();
	}

	public static function delete_me() {
		if ( current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'forbidden', 'forbidden', array( 'status' => 403 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$id = get_current_user_id();
		wp_logout();
		wp_delete_user( $id );
		return array( 'deleted' => true );
	}

	public static function login( \WP_REST_Request $req ) {
		if ( '' !== $req['website'] ) {
			return array( 'sent' => true ); // bot; pretend success
		}
		if ( ! $req['consent'] ) {
			return new \WP_Error( 'consent_required', 'consent_required', array( 'status' => 400 ) );
		}
		if ( ! is_email( $req['email'] ) ) {
			return new \WP_Error( 'invalid_email', 'invalid_email', array( 'status' => 400 ) );
		}
		Auth::request_link( $req['email'], language( $req['lang'] ) );
		return array( 'sent' => true );
	}

	public static function verify( \WP_REST_Request $req ) {
		// JSON only: a cross-site form post cannot send it without a CORS preflight,
		// so another site cannot sign a visitor into someone else's account.
		if ( ! $req->is_json_content_type() ) {
			return new \WP_Error( 'invalid_token', 'invalid_token', array( 'status' => 400 ) );
		}
		$result = Auth::verify( (string) $req['token'] );
		return is_wp_error( $result ) ? $result : array( 'loggedIn' => true );
	}

	public static function logout() {
		wp_logout();
		return array( 'loggedIn' => false );
	}
}
