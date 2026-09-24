<?php
/**
 * REST API, namespace travel-risk/v1.
 *
 *   GET    /advice/{ISO3}?source=   Travel advice from buza | fcdo | aa | usdos | gac | dfat (or ?lang= for its default source)
 *   GET    /news/{ISO3}             Recent security news (only when a news provider is on)
 *   GET    /alerts                  Current GDACS disaster alerts, all listed countries
 *   GET    /me                      Signed-in state, saved countries and language
 *   PUT    /me                      Save countries, language, advice source, e-mail notifications
 *   DELETE /me                      Delete own account and data
 *   POST   /login                   Request a sign-in link by e-mail
 *   POST   /login/verify            Exchange the link token or the 6-digit code for a session
 *   POST   /logout
 *   GET    /push/key                VAPID public key for PushManager.subscribe()
 *   POST   /push                    Register this device for push notifications
 *   DELETE /push                    Remove this device
 *   POST   /push/test               Send a test notification to the user's devices
 *
 * Error responses carry a short code (e.g. "rate_limited") that the app translates.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Rest {

	const NS            = 'travel-risk/v1';
	const MAX_COUNTRIES = 60;
	const GDELT_SPACING = 6; // seconds between GDELT calls; it answers 429 below ~5 s
	const NEWS_CACHE_MINUTES = 150; // longer than the hourly pre-fetch, so the cache never runs dry

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		$iso = '(?P<iso>[A-Za-z]{3})';

		register_rest_route( self::NS, "/advice/$iso", array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'advice' ),
			'permission_callback' => array( self::class, 'can_read' ),
			'args'                => array(
				'source' => array( 'type' => 'string', 'enum' => array_keys( Sources::NAMES ) ),
				'lang'   => array( 'type' => 'string', 'default' => LANGUAGES[0] ),
			),
		) );
		register_rest_route( self::NS, "/news/$iso", array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'news' ),
			'permission_callback' => array( self::class, 'can_read' ),
		) );
		register_rest_route( self::NS, '/alerts', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'alerts' ),
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
					'lang'        => array( 'type' => 'string', 'enum' => LANGUAGES ),
					'notifyEmail' => array( 'type' => 'boolean' ),
					'source'      => array( 'type' => 'string', 'enum' => array_keys( Sources::NAMES ) ),
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
			'args'                => array(
				'token' => array( 'type' => 'string' ),
				'email' => array( 'type' => 'string' ),
				'code'  => array( 'type' => 'string' ),
			),
		) );
		register_rest_route( self::NS, '/push/key', array(
			'methods'             => 'GET',
			'callback'            => fn() => array( 'publicKey' => Notify::vapid()['public'] ),
			'permission_callback' => 'is_user_logged_in',
		) );
		register_rest_route( self::NS, '/push', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'push_add' ),
				'permission_callback' => 'is_user_logged_in',
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( self::class, 'push_remove' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array( 'endpoint' => array( 'type' => 'string', 'required' => true ) ),
			),
		) );
		register_rest_route( self::NS, '/push/test', array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'push_test' ),
			'permission_callback' => 'is_user_logged_in',
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
		$source = $req['source'] ?: Sources::for_language( language( $req['lang'] ) );
		try {
			$data = cached(
				"advice:$source:{$country['iso3']}",
				fn() => sources()->advice( $source, $country )
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
		try {
			$data = self::fetch_news( $country );
		} catch ( SourceException $e ) {
			return self::fail( $e );
		}
		return rest_ensure_response( $data + array( 'hours' => (int) setting( 'news_hours' ) ) );
	}

	public static function alerts() {
		if ( ! setting( 'alerts' ) ) {
			return new \WP_Error( 'alerts_disabled', 'alerts_disabled', array( 'status' => 404 ) );
		}
		try {
			return rest_ensure_response( array( 'items' => disaster_alerts() ) );
		} catch ( SourceException $e ) {
			return self::fail( $e );
		}
	}

	/**
	 * News for one country, cached for NEWS_CACHE_MINUTES. The hourly check pre-fetches news for
	 * followed countries (Notify::prefetch_news), so visitors rarely wait for GDELT, which is slow.
	 */
	public static function fetch_news( array $country ): array {
		$provider = setting( 'news_provider' );
		$hours    = (int) setting( 'news_hours' );
		return cached(
			"news:$provider:$hours:{$country['iso3']}",
			function () use ( $provider, $country, $hours ) {
				if ( 'gdelt' === $provider ) {
					self::gdelt_throttle();
				}
				$http = fn( string $url ) => http_get( $url, 'gdelt' === $provider ? 30 : 15 );
				return ( new News( $http ) )->fetch( $provider, $country, $hours );
			},
			self::NEWS_CACHE_MINUTES
		);
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
			'countries'            => user_list( $user->ID, Auth::META_COUNTRIES ),
			'lang'                 => get_user_meta( $user->ID, Auth::META_LANG, true ) ?: null,
			'source'               => Sources::valid( get_user_meta( $user->ID, Auth::META_SOURCE, true ) ) ? get_user_meta( $user->ID, Auth::META_SOURCE, true ) : null,
			'canDelete'            => ! current_user_can( 'edit_posts' ),
			'notifyEmail'          => '1' === get_user_meta( $user->ID, Notify::META_EMAIL, true ),
			'pushDevices'          => count( Notify::devices( $user->ID ) ),
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
		if ( null !== $req['source'] ) {
			update_user_meta( $id, Auth::META_SOURCE, $req['source'] );
		}
		if ( null !== $req['notifyEmail'] ) {
			$req['notifyEmail'] ? update_user_meta( $id, Notify::META_EMAIL, '1' ) : delete_user_meta( $id, Notify::META_EMAIL );
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
		$result = $req['token']
			? Auth::verify( (string) $req['token'] )
			: Auth::verify_code( (string) $req['email'], (string) $req['code'] );
		return is_wp_error( $result ) ? $result : array( 'loggedIn' => true );
	}

	public static function push_add( \WP_REST_Request $req ) {
		$sub = Notify::validate( $req->get_json_params() );
		if ( is_wp_error( $sub ) ) {
			return $sub;
		}
		Notify::add_device( get_current_user_id(), $sub );
		return self::me();
	}

	public static function push_remove( \WP_REST_Request $req ) {
		Notify::remove_device( get_current_user_id(), (string) $req['endpoint'] );
		return self::me();
	}

	public static function push_test() {
		$lang = language( get_user_meta( get_current_user_id(), Auth::META_LANG, true ) );
		$text = array(
			'en' => array( 'Notifications are working', 'You will get a message here when the travel advice for one of your countries changes.' ),
			'de' => array( 'Benachrichtigungen funktionieren', 'Sie erhalten hier eine Nachricht, wenn sich der Reisehinweis für eines Ihrer Länder ändert.' ),
			'nl' => array( 'Meldingen werken', 'Je krijgt hier een bericht als het reisadvies voor een van je landen wijzigt.' ),
		)[ $lang ];
		return Notify::push_user( get_current_user_id(), array( 'title' => $text[0], 'body' => $text[1], 'tag' => 'test' ) );
	}

	public static function logout() {
		wp_logout();
		return array( 'loggedIn' => false );
	}
}
