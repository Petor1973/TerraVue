<?php
/**
 * GDPR hooks into the WordPress privacy tools (Tools > Export / Erase Personal
 * Data) and a suggested text for the privacy policy (Settings > Privacy).
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Privacy {

	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
		add_action( 'admin_init', array( self::class, 'policy_text' ) );
	}

	public static function register_exporter( array $exporters ): array {
		$exporters['travel-risk'] = array(
			'exporter_friendly_name' => setting( 'brand_name' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( array $erasers ): array {
		$erasers['travel-risk'] = array(
			'eraser_friendly_name' => setting( 'brand_name' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	public static function export( string $email ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		$fields = array(
			array( 'name' => 'Saved countries', 'value' => implode( ', ', user_list( $user->ID, Auth::META_COUNTRIES ) ) ),
			array( 'name' => 'Language', 'value' => (string) get_user_meta( $user->ID, Auth::META_LANG, true ) ),
			array( 'name' => 'Travel advice source', 'value' => (string) get_user_meta( $user->ID, Auth::META_SOURCE, true ) ),
			array( 'name' => 'Consent given at', 'value' => (string) get_user_meta( $user->ID, Auth::META_CONSENT, true ) ),
			array( 'name' => 'E-mail notifications', 'value' => '1' === get_user_meta( $user->ID, Notify::META_EMAIL, true ) ? 'on' : 'off' ),
			array(
				'name'  => 'Devices with push notifications',
				'value' => implode( "\n", array_map( fn( $d ) => wp_parse_url( $d['endpoint'], PHP_URL_HOST ) . ' (since ' . $d['created'] . ')', Notify::devices( $user->ID ) ) ),
			),
		);
		return array(
			'data' => array(
				array(
					'group_id'    => 'travel-risk',
					'group_label' => setting( 'brand_name' ),
					'item_id'     => 'travel-risk-' . $user->ID,
					'data'        => $fields,
				),
			),
			'done' => true,
		);
	}

	public static function erase( string $email ): array {
		$user    = get_user_by( 'email', $email );
		$removed = false;
		if ( $user ) {
			foreach ( array( Auth::META_COUNTRIES, Auth::META_LANG, Auth::META_CONSENT, Auth::META_SOURCE, Notify::META_PUSH, Notify::META_EMAIL ) as $key ) {
				$removed = delete_user_meta( $user->ID, $key ) || $removed;
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	public static function policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$brand = esc_html( setting( 'brand_name' ) );
		wp_add_privacy_policy_content(
			$brand,
			"<p>To use $brand you register once with your e-mail address. You receive a sign-in link; your account is only created after you open it (double opt-in). We store your e-mail address, the date of your consent, the countries you add and your language preference, only to provide the service. Legal basis: your consent (Art. 6(1)(a) GDPR). You can delete your account and all related data at any time from within the app.</p>"
			. "<p>To show travel advice and news, the server requests public data from the travel advice services of the governments of the Netherlands, the United Kingdom, Germany, the United States, Canada and Australia, the public disaster alert feed of GDACS (European Commission and United Nations) and, if enabled, a news service. Only country names or codes are sent to these services, never your personal data.</p>"
			. "<p>Notifications are optional and off by default. If you turn on push notifications, we store the push address and encryption keys your browser gives us for that device, and send messages through your browser's push service (Google, Mozilla, Apple or Microsoft); the message content is end-to-end encrypted. If you turn on e-mail notifications, we store that choice. Turning notifications off, signing out on a device or deleting your account removes this data. We check the official advice and disaster alerts hourly for the countries you follow.</p>"
			. '<p>The app stores your chosen countries and language on your device (local storage) so it works offline. Signing out clears cached data.</p>'
		);
	}
}
