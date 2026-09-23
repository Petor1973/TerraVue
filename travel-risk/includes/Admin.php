<?php
/**
 * Settings > Travel Risk.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Admin {

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FILE ), array( self::class, 'links' ) );
		add_action( 'admin_post_travel_risk_check_now', array( self::class, 'check_now' ) );
		add_action( 'admin_post_travel_risk_test_change', array( self::class, 'test_change' ) );
	}

	// ------------------------------------------------------------------ test tools

	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( $action );
	}

	private static function back( string $notice ): void {
		set_transient( 'travel_risk_notice_' . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=travel-risk#tr-tests' ) );
		exit;
	}

	/** Runs the hourly check now (without the slow news pre-fetch). Real notifications go out on real changes. */
	public static function check_now(): void {
		self::guard( 'travel_risk_check_now' );
		$s = Notify::check( false );
		self::back( sprintf(
			'Check done: %d users with notifications, %d source/country pairs checked, %d source errors, %d level changes, %d notifications sent.',
			$s['users'], $s['pairs'], $s['errors'], $s['changes'], $s['notified']
		) );
	}

	/** Sends a sample "advice changed" notification to the current user only. */
	public static function test_change(): void {
		self::guard( 'travel_risk_test_change' );
		$iso     = strtoupper( sanitize_key( wp_unslash( $_POST['country'] ?? '' ) ) );
		$country = countries()[ $iso ] ?? null;
		$to      = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
		$user    = $to ? get_user_by( 'email', $to ) : wp_get_current_user();
		if ( ! $country ) {
			self::back( 'Unknown country.' );
		}
		if ( ! $user || ! $user->ID ) {
			self::back( 'No account with that e-mail address.' );
		}
		$r = Notify::simulate( $user->ID, $country );
		self::back( sprintf(
			'Test notification for %s to %s: push sent to %d device(s), %d failed; e-mail %s.',
			$country['en'], $user->user_email, $r['push']['sent'], $r['push']['failed'], $r['email'] ? 'sent' : 'not sent (e-mail notifications are off for that account)'
		) );
	}

	private static function tests_section(): void {
		$user    = get_current_user_id();
		$devices = count( Notify::devices( $user ) );
		$email   = '1' === get_user_meta( $user, Notify::META_EMAIL, true );
		$stats   = get_option( Notify::OPT_LAST_STATS );
		$notice  = get_transient( 'travel_risk_notice_' . $user );
		if ( $notice ) {
			delete_transient( 'travel_risk_notice_' . $user );
			printf( '<div class="notice notice-info"><p>%s</p></div>', esc_html( $notice ) );
		}
		?>
		<h2 id="tr-tests">Test notifications</h2>
		<ol>
			<li>Open <a href="<?php echo esc_url( app_url() ); ?>" target="_blank" rel="noopener">the app</a> while logged in here, go to <strong>Notifications</strong> and turn on push (and/or e-mail) for this device.</li>
			<li>Use the buttons below. A test notification goes to <strong>one account</strong> (yours, or the address you enter) and to all devices with push on for that account. It does not affect other users or the baseline.</li>
		</ol>
		<p class="description">Push is stored per account: a phone signed in with another e-mail address than this admin account does not receive tests sent to you. Enter that address below to reach it.</p>
		<p>Your account: <strong><?php echo (int) $devices; ?></strong> device(s) with push, e-mail notifications <strong><?php echo $email ? 'on' : 'off'; ?></strong>.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px">
			<input type="hidden" name="action" value="travel_risk_test_change">
			<?php wp_nonce_field( 'travel_risk_test_change' ); ?>
			<label>Country
				<select name="country">
					<?php foreach ( countries() as $iso => $c ) : ?>
						<option value="<?php echo esc_attr( $iso ); ?>" <?php selected( $iso, 'ISR' ); ?>><?php echo esc_html( $c['en'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>To <input type="email" name="to" placeholder="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" class="regular-text"></label>
			<?php submit_button( 'Send test "advice changed" notification', 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="travel_risk_check_now">
			<?php wp_nonce_field( 'travel_risk_check_now' ); ?>
			<?php submit_button( 'Run the hourly check now', 'secondary', 'submit', false ); ?>
			<span class="description">Fetches the advice for all followed countries and notifies users of real level changes.</span>
		</form>
		<?php if ( is_array( $stats ) ) : ?>
			<p class="description">Last check: <?php echo (int) $stats['pairs']; ?> pairs, <?php echo (int) $stats['errors']; ?> source errors, <?php echo (int) $stats['changes']; ?> changes, <?php echo (int) $stats['notified']; ?> notifications, news pre-fetched for <?php echo (int) $stats['news']; ?> countries.</p>
		<?php endif; ?>
		<p class="description">Also from the command line: <code>wp travel-risk check</code> and <code>wp travel-risk test-notify --to=&lt;id|email&gt; --country=ISR</code>.</p>
		<?php
	}

	public static function links( array $links ): array {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=travel-risk' ) ), esc_html__( 'Settings', 'travel-risk' ) ) );
		return $links;
	}

	public static function menu(): void {
		add_options_page( 'Travel Risk', 'Travel Risk', 'manage_options', 'travel-risk', array( self::class, 'page' ) );
	}

	public static function register(): void {
		register_setting( 'travel_risk', 'travel_risk_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( self::class, 'sanitize' ),
			'default'           => defaults(),
		) );
	}

	public static function sanitize( $input ): array {
		$input = (array) $input;
		$d     = defaults();
		return array(
			'brand_name'           => sanitize_text_field( $input['brand_name'] ?? '' ) ?: $d['brand_name'],
			'color_primary'        => sanitize_hex_color( $input['color_primary'] ?? '' ) ?: $d['color_primary'],
			'color_accent'         => sanitize_hex_color( $input['color_accent'] ?? '' ) ?: $d['color_accent'],
			'require_registration' => empty( $input['require_registration'] ) ? 0 : 1,
			'news_provider'        => in_array( $input['news_provider'] ?? '', array( 'gdelt', 'google', 'none' ), true ) ? $input['news_provider'] : $d['news_provider'],
			'cache_minutes'        => min( 1440, max( 5, (int) ( $input['cache_minutes'] ?? $d['cache_minutes'] ) ) ),
			'news_hours'           => min( 168, max( 12, (int) ( $input['news_hours'] ?? $d['news_hours'] ) ) ),
			'app_page_id'          => absint( $input['app_page_id'] ?? 0 ),
		);
	}

	public static function page(): void {
		$s    = settings();
		$name = fn( $k ) => 'travel_risk_settings[' . $k . ']';
		?>
		<div class="wrap">
			<h1>Travel Risk</h1>
			<p>Place the shortcode <code>[travel_risk]</code> on a page. That page becomes the installable app.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'travel_risk' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tr-brand">Product name</label></th>
						<td><input id="tr-brand" class="regular-text" name="<?php echo esc_attr( $name( 'brand_name' ) ); ?>" value="<?php echo esc_attr( $s['brand_name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row">Colours</th>
						<td>
							<label>Primary <input type="color" name="<?php echo esc_attr( $name( 'color_primary' ) ); ?>" value="<?php echo esc_attr( $s['color_primary'] ); ?>"></label>
							&nbsp;
							<label>Accent <input type="color" name="<?php echo esc_attr( $name( 'color_accent' ) ); ?>" value="<?php echo esc_attr( $s['color_accent'] ); ?>"></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tr-page">App page</label></th>
						<td>
							<?php
							wp_dropdown_pages( array(
								'id'                => 'tr-page',
								'name'              => esc_attr( $name( 'app_page_id' ) ),
								'selected'          => (int) $s['app_page_id'],
								'show_option_none'  => '— detected automatically —',
								'option_none_value' => 0,
							) );
							?>
							<p class="description">Start page of the installed app and target of sign-in links.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Registration</th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name( 'require_registration' ) ); ?>" value="1" <?php checked( $s['require_registration'] ); ?>> Visitors must register with their e-mail address before using the app</label>
							<p class="description">Sign-in links are sent with <code>wp_mail()</code>. Configure reliable outgoing mail (SMTP) on this site. Make sure a privacy policy page is set under Settings &gt; Privacy; the app links to it.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Notifications</th>
						<td>
							<?php
							$last = (int) get_option( Notify::OPT_LAST );
							$next = wp_next_scheduled( Notify::CRON );
							?>
							<p>Users can turn on push and e-mail notifications in the app. The advice for followed countries is checked every hour.</p>
							<p class="description">
								Last check: <?php echo $last ? esc_html( human_time_diff( $last ) . ' ago' ) : 'not yet'; ?>.
								Next: <?php echo $next ? esc_html( 'in ' . human_time_diff( $next ) ) : 'not scheduled'; ?>.
								WP-Cron only runs when the site has visitors. For reliable notifications, add <code>define( 'DISABLE_WP_CRON', true );</code> to wp-config.php and a server cron job that calls <code>wp-cron.php</code> (or <code>wp cron event run --due-now</code>) every 5–15 minutes.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tr-news">News source</label></th>
						<td>
							<select id="tr-news" name="<?php echo esc_attr( $name( 'news_provider' ) ); ?>">
								<option value="gdelt" <?php selected( $s['news_provider'], 'gdelt' ); ?>>GDELT (open data)</option>
								<option value="google" <?php selected( $s['news_provider'], 'google' ); ?>>Google News RSS (personal, non-commercial use only)</option>
								<option value="none" <?php selected( $s['news_provider'], 'none' ); ?>>No news</option>
							</select>
							<p class="description">For a commercial service, use a news source whose licence allows it.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tr-hours">News period (hours)</label></th>
						<td><input id="tr-hours" type="number" min="12" max="168" name="<?php echo esc_attr( $name( 'news_hours' ) ); ?>" value="<?php echo esc_attr( $s['news_hours'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="tr-cache">Cache (minutes)</label></th>
						<td><input id="tr-cache" type="number" min="5" max="1440" name="<?php echo esc_attr( $name( 'cache_minutes' ) ); ?>" value="<?php echo esc_attr( $s['cache_minutes'] ); ?>"></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php self::tests_section(); ?>
		</div>
		<?php
	}
}
