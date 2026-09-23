<?php
/**
 * One-time registration / sign-in by e-mail ("magic link"), which doubles as
 * double opt-in: an account is only created once the address is confirmed.
 *
 * The token travels in the URL fragment (#tr-login=...), so it never reaches
 * the server in a GET request and mail scanners that prefetch links (Outlook
 * Safe Links etc.) cannot use it up. The app posts it to /login/verify.
 *
 * Personal data stored: e-mail address (WordPress user), consent timestamp,
 * chosen countries and UI language (user meta). Nothing else.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Auth {

	const META_COUNTRIES = 'travel_risk_countries';
	const META_LANG      = 'travel_risk_lang';
	const META_CONSENT   = 'travel_risk_consent';

	const TOKEN_TTL     = 30 * MINUTE_IN_SECONDS;
	const MAX_PER_EMAIL = 3;   // link requests per address per hour
	const MAX_PER_IP    = 10;  // link requests per IP per hour

	public static function init(): void {
		add_filter( 'show_admin_bar', array( self::class, 'admin_bar' ) );
	}

	/** App users have no business in wp-admin; keep the toolbar out of the app. */
	public static function admin_bar( $show ) {
		return current_user_can( 'edit_posts' ) ? $show : false;
	}

	/**
	 * Sends a sign-in link. Always "succeeds" towards the caller so the endpoint
	 * cannot be used to find out which addresses have an account.
	 */
	public static function request_link( string $email, string $lang ): void {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return;
		}
		if ( ! self::within_limit( 'mail_' . strtolower( $email ), self::MAX_PER_EMAIL )
			|| ! self::within_limit( 'ip_' . self::client_ip(), self::MAX_PER_IP ) ) {
			return;
		}
		$user = get_user_by( 'email', $email );
		if ( $user && user_can( $user, 'edit_posts' ) ) {
			// Staff accounts sign in with a password, never with a link.
			return;
		}

		$token = bin2hex( random_bytes( 32 ) );
		set_transient( self::token_key( $token ), array( 'email' => $email, 'lang' => $lang ), self::TOKEN_TTL );

		$link  = app_url() . '#tr-login=' . $token;
		$texts = self::mail_texts( $lang, setting( 'brand_name' ), $link );
		wp_mail( $email, $texts[0], $texts[1] );
	}

	/** @return int|\WP_Error User ID. */
	public static function verify( string $token ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return new \WP_Error( 'invalid_token', 'invalid_token', array( 'status' => 400 ) );
		}
		$key  = self::token_key( $token );
		$data = get_transient( $key );
		if ( ! $data ) {
			return new \WP_Error( 'expired_token', 'expired_token', array( 'status' => 410 ) );
		}
		delete_transient( $key );

		$user = get_user_by( 'email', $data['email'] );
		if ( $user && user_can( $user, 'edit_posts' ) ) {
			return new \WP_Error( 'invalid_token', 'invalid_token', array( 'status' => 400 ) );
		}
		if ( ! $user ) {
			$id = wp_insert_user(
				array(
					'user_login'   => 'tr_' . strtolower( wp_generate_password( 12, false ) ),
					'user_email'   => $data['email'],
					'user_pass'    => wp_generate_password( 32, true, true ),
					'display_name' => strstr( $data['email'], '@', true ),
					'role'         => 'subscriber',
				)
			);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$user = get_user_by( 'id', $id );
		}
		if ( ! get_user_meta( $user->ID, self::META_CONSENT, true ) ) {
			update_user_meta( $user->ID, self::META_CONSENT, gmdate( 'c' ) );
		}
		if ( ! get_user_meta( $user->ID, self::META_LANG, true ) ) {
			update_user_meta( $user->ID, self::META_LANG, $data['lang'] );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		return $user->ID;
	}

	private static function token_key( string $token ): string {
		return 'travel_risk_tok_' . hash( 'sha256', $token );
	}

	/** Counters are keyed by hash only; no addresses or IPs are stored in clear. */
	private static function within_limit( string $what, int $max ): bool {
		$key   = 'travel_risk_rl_' . hash( 'sha256', wp_salt() . $what );
		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	private static function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/** @return array{0:string,1:string} [subject, body] */
	public static function mail_texts( string $lang, string $brand, string $link ): array {
		$texts = array(
			'en' => array(
				'Your sign-in link for %1$s',
				"Hello,\n\nUse the link below to sign in to %1\$s. It is valid for 30 minutes and can be used once.\n\n%2\$s\n\nDid not request this? Then you can ignore this e-mail; no account will be created.\n\n%1\$s",
			),
			'de' => array(
				'Ihr Anmeldelink für %1$s',
				"Hallo,\n\nmit dem folgenden Link melden Sie sich bei %1\$s an. Er ist 30 Minuten gültig und nur einmal verwendbar.\n\n%2\$s\n\nSie haben das nicht angefordert? Dann ignorieren Sie diese E-Mail; es wird kein Konto angelegt.\n\n%1\$s",
			),
			'nl' => array(
				'Je inloglink voor %1$s',
				"Hallo,\n\nMet de link hieronder log je in bij %1\$s. De link is 30 minuten geldig en één keer te gebruiken.\n\n%2\$s\n\nNiet aangevraagd? Dan kun je deze e-mail negeren; er wordt geen account aangemaakt.\n\n%1\$s",
			),
		);
		$t = $texts[ language( $lang ) ];
		return array( sprintf( $t[0], $brand ), sprintf( $t[1], $brand, $link ) );
	}
}
