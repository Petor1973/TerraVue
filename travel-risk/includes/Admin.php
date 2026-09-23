<?php
/**
 * Admin menu "<brand>" with three pages:
 *   Settings       travel-risk                 product name, colours, app page, registration, news
 *   Notifications  travel-risk-notifications   hourly check status and test tools
 *   Sources        travel-risk-sources         governments, licences, live source test
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Admin {

	const SLUG          = 'travel-risk';
	const NOTIFICATIONS = 'travel-risk-notifications';
	const SOURCES       = 'travel-risk-sources';

	/** Licence status per source, see CLAUDE.md "Bronnen en licenties". */
	const LICENCES = array(
		'buza'  => array( 'Open data', false ),
		'fcdo'  => array( 'Open Government Licence v3.0', true ),
		'aa'    => array( 'Open data (terms on auswaertiges-amt.de)', false ),
		'usdos' => array( 'Public domain', true ),
		'gac'   => array( 'Open Government Licence – Canada', true ),
		'dfat'  => array( 'Smartraveller copyright terms', false ),
	);

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'admin_init', array( self::class, 'redirect_old_url' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FILE ), array( self::class, 'links' ) );
		add_action( 'admin_post_travel_risk_check_now', array( self::class, 'check_now' ) );
		add_action( 'admin_post_travel_risk_test_change', array( self::class, 'test_change' ) );
		add_action( 'admin_post_travel_risk_test_sources', array( self::class, 'test_sources' ) );
	}

	public static function menu(): void {
		$brand = setting( 'brand_name' );
		add_menu_page( $brand, $brand, 'manage_options', self::SLUG, array( self::class, 'settings_page' ), 'dashicons-admin-site-alt3', 58 );
		add_submenu_page( self::SLUG, "$brand – Settings", 'Settings', 'manage_options', self::SLUG, array( self::class, 'settings_page' ) );
		add_submenu_page( self::SLUG, "$brand – Notifications", 'Notifications', 'manage_options', self::NOTIFICATIONS, array( self::class, 'notifications_page' ) );
		add_submenu_page( self::SLUG, "$brand – Sources", 'Sources', 'manage_options', self::SOURCES, array( self::class, 'sources_page' ) );
	}

	/** The page used to live under Settings; keep old bookmarks working. */
	public static function redirect_old_url(): void {
		global $pagenow;
		if ( 'options-general.php' === $pagenow && self::SLUG === ( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( self::url() );
			exit;
		}
	}

	public static function url( string $page = self::SLUG ): string {
		return admin_url( 'admin.php?page=' . $page );
	}

	public static function links( array $links ): array {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'travel-risk' ) ) );
		return $links;
	}

	// ------------------------------------------------------------------ settings

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

	public static function settings_page(): void {
		$s    = settings();
		$name = fn( $k ) => 'travel_risk_settings[' . $k . ']';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $s['brand_name'] ); ?> – Settings</h1>
			<p>Place the shortcode <code>[travel_risk]</code> on a page. That page becomes the installable app.
				<a href="<?php echo esc_url( app_url() ); ?>" target="_blank" rel="noopener">Open the app</a></p>
			<?php settings_errors(); ?>
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
		</div>
		<?php
	}

	// ------------------------------------------------------------------ shared helpers

	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( $action );
	}

	private static function back( string $page, $notice ): void {
		set_transient( 'travel_risk_notice_' . get_current_user_id(), $notice, 30 * MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url( $page ) );
		exit;
	}

	/** One-time result of the last action (string notice or source test rows). */
	private static function take_notice() {
		$key    = 'travel_risk_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );
		return $notice;
	}

	private static function country_select( string $selected = 'ISR' ): void {
		echo '<select name="country">';
		foreach ( countries() as $iso => $c ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $iso ), selected( $iso, $selected, false ), esc_html( $c['en'] ) );
		}
		echo '</select>';
	}

	private static function posted_country(): ?array {
		$iso = strtoupper( sanitize_key( wp_unslash( $_POST['country'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in guard()
		return countries()[ $iso ] ?? null;
	}

	// ------------------------------------------------------------------ notifications

	/** Runs the hourly check now (without the slow news pre-fetch). Real notifications go out on real changes. */
	public static function check_now(): void {
		self::guard( 'travel_risk_check_now' );
		$s = Notify::check( false );
		self::back( self::NOTIFICATIONS, sprintf(
			'Check done: %d users with notifications, %d source/country pairs checked, %d source errors, %d level changes, %d notifications sent.',
			$s['users'], $s['pairs'], $s['errors'], $s['changes'], $s['notified']
		) );
	}

	/** Sends a sample "advice changed" notification to one account (own or given address). */
	public static function test_change(): void {
		self::guard( 'travel_risk_test_change' );
		$country = self::posted_country();
		$to      = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$user    = $to ? get_user_by( 'email', $to ) : wp_get_current_user();
		if ( ! $country ) {
			self::back( self::NOTIFICATIONS, 'Unknown country.' );
		}
		if ( ! $user || ! $user->ID ) {
			self::back( self::NOTIFICATIONS, 'No account with that e-mail address.' );
		}
		$r = Notify::simulate( $user->ID, $country );
		self::back( self::NOTIFICATIONS, sprintf(
			'Test notification for %s to %s: push sent to %d device(s), %d failed; e-mail %s.',
			$country['en'], $user->user_email, $r['push']['sent'], $r['push']['failed'], $r['email'] ? 'sent' : 'not sent (e-mail notifications are off for that account)'
		) );
	}

	public static function notifications_page(): void {
		$user    = get_current_user_id();
		$devices = count( Notify::devices( $user ) );
		$email   = '1' === get_user_meta( $user, Notify::META_EMAIL, true );
		$stats   = get_option( Notify::OPT_LAST_STATS );
		$last    = (int) get_option( Notify::OPT_LAST );
		$next    = wp_next_scheduled( Notify::CRON );
		$notice  = self::take_notice();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( setting( 'brand_name' ) ); ?> – Notifications</h1>
			<?php if ( is_string( $notice ) ) : ?>
				<div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<h2>Hourly check</h2>
			<p>Users turn on push and e-mail notifications in the app. The advice for followed countries is checked every hour; users are notified when the level changes.</p>
			<p>
				Last check: <strong><?php echo $last ? esc_html( human_time_diff( $last ) . ' ago' ) : 'not yet'; ?></strong>.
				Next: <strong><?php echo $next ? esc_html( 'in ' . human_time_diff( $next ) ) : 'not scheduled'; ?></strong>.
				<?php if ( is_array( $stats ) ) : ?>
					Last run: <?php echo (int) $stats['pairs']; ?> source/country pairs, <?php echo (int) $stats['errors']; ?> source errors, <?php echo (int) $stats['changes']; ?> changes, <?php echo (int) $stats['notified']; ?> notifications, news pre-fetched for <?php echo (int) $stats['news']; ?> countries.
				<?php endif; ?>
			</p>
			<p class="description">WP-Cron only runs when the site has visitors. For reliable notifications, add <code>define( 'DISABLE_WP_CRON', true );</code> to wp-config.php and a server cron job that runs <code>wp travel-risk check</code> every hour (or calls <code>wp-cron.php</code> every 5–15 minutes).</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="travel_risk_check_now">
				<?php wp_nonce_field( 'travel_risk_check_now' ); ?>
				<?php submit_button( 'Run the hourly check now', 'secondary', 'submit', false ); ?>
				<span class="description">Fetches the advice for all followed countries and notifies users of real level changes.</span>
			</form>

			<h2>Test notifications</h2>
			<ol>
				<li>Open <a href="<?php echo esc_url( app_url() ); ?>" target="_blank" rel="noopener">the app</a>, go to <strong>Notifications</strong> and turn on push (and/or e-mail) for your device.</li>
				<li>Send a test below. It goes to <strong>one account</strong> (yours, or the address you enter) and to all devices with push on for that account. Other users and the baseline are not affected.</li>
			</ol>
			<p class="description">Push is stored per account: a phone signed in with another e-mail address than this admin account does not receive tests sent to you. Enter that address to reach it.</p>
			<p>Your account: <strong><?php echo (int) $devices; ?></strong> device(s) with push, e-mail notifications <strong><?php echo $email ? 'on' : 'off'; ?></strong>.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="travel_risk_test_change">
				<?php wp_nonce_field( 'travel_risk_test_change' ); ?>
				<label>Country <?php self::country_select(); ?></label>
				<label>To <input type="email" name="to" placeholder="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" class="regular-text"></label>
				<?php submit_button( 'Send test "advice changed" notification', 'secondary', 'submit', false ); ?>
			</form>
			<p class="description">From the command line: <code>wp travel-risk check</code> and <code>wp travel-risk test-notify --to=&lt;id|email&gt; --country=ISR</code>.</p>
		</div>
		<?php
	}

	// ------------------------------------------------------------------ sources

	/** Fetches the live advice for one country from every source, bypassing the cache. */
	public static function test_sources(): void {
		self::guard( 'travel_risk_test_sources' );
		$country = self::posted_country();
		if ( ! $country ) {
			self::back( self::SOURCES, 'Unknown country.' );
		}
		$fresh = new Sources( __NAMESPACE__ . '\\http_get' );
		$rows  = array();
		foreach ( array_keys( Sources::NAMES ) as $id ) {
			$start = microtime( true );
			try {
				$a      = $fresh->advice( $id, $country );
				$rows[] = array( 'id' => $id, 'level' => $a['level'], 'max' => $a['maxLevel'], 'regional' => ! empty( $a['regional'] ), 'basis' => $a['basis'] ?? '', 'url' => $a['url'] ?? '', 'error' => '' );
			} catch ( SourceException $e ) {
				$rows[] = array( 'id' => $id, 'error' => $e->getMessage() );
			} catch ( \Throwable $e ) {
				$rows[] = array( 'id' => $id, 'error' => 'PHP error: ' . $e->getMessage() );
			}
			$rows[ count( $rows ) - 1 ]['ms'] = (int) ( ( microtime( true ) - $start ) * 1000 );
		}
		self::back( self::SOURCES, array( 'country' => $country, 'rows' => $rows ) );
	}

	public static function sources_page(): void {
		$result = self::take_notice();
		$levels = Notify::LEVELS['en'];
		?>
		<div class="wrap">
			<h1><?php echo esc_html( setting( 'brand_name' ) ); ?> – Sources</h1>
			<p>Users choose whose government advice they follow. Attribution for each source is shown in the app footer.</p>
			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><th>Source</th><th>Licence</th><th>Status</th></tr></thead>
				<tbody>
				<?php foreach ( Sources::NAMES as $id => $name ) : ?>
					<?php list( $licence, $verified ) = self::LICENCES[ $id ]; ?>
					<tr>
						<td><strong><?php echo esc_html( $name ); ?></strong><br><code><?php echo esc_html( $id ); ?></code></td>
						<td><?php echo esc_html( $licence ); ?><br><span class="description"><?php echo esc_html( Sources::ATTRIBUTION[ $id ] ); ?></span></td>
						<td><?php echo $verified ? '✅ verified' : '⚠️ check terms before commercial use'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Test sources now</h2>
			<p>Fetches the current advice for one country from all six governments, without cache, and shows how the app reads it. Compare with the official websites to validate the interpretation.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="travel_risk_test_sources">
				<?php wp_nonce_field( 'travel_risk_test_sources' ); ?>
				<label>Country <?php self::country_select( is_array( $result ) ? $result['country']['iso3'] : 'ISR' ); ?></label>
				<?php submit_button( 'Test sources now', 'primary', 'submit', false ); ?>
			</form>

			<?php if ( is_string( $result ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $result ); ?></p></div>
			<?php elseif ( is_array( $result ) ) : ?>
				<h3><?php echo esc_html( $result['country']['en'] ); ?></h3>
				<table class="widefat striped" style="max-width:1100px">
					<thead><tr><th>Source</th><th>Level</th><th>Strictest in parts</th><th>Why this level</th><th>Time</th></tr></thead>
					<tbody>
					<?php foreach ( $result['rows'] as $r ) : ?>
						<tr>
							<td><?php echo esc_html( Sources::NAMES[ $r['id'] ] ); ?></td>
							<?php if ( $r['error'] ) : ?>
								<td colspan="3"><strong style="color:#b32d2e"><?php echo esc_html( $r['error'] ); ?></strong></td>
							<?php else : ?>
								<td><?php echo esc_html( $levels[ (int) $r['level'] ] ); ?></td>
								<td><?php echo esc_html( $r['max'] > $r['level'] ? $levels[ (int) $r['max'] ] : ( $r['regional'] ? 'regional warnings (no level)' : '—' ) ); ?></td>
								<td><?php echo esc_html( $r['basis'] ); ?><?php if ( $r['url'] ) : ?> <a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener">official page</a><?php endif; ?></td>
							<?php endif; ?>
							<td><?php echo (int) $r['ms']; ?> ms</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">Errors: <code>no_home_advice</code> = a government gives no advice for its own country; <code>not_found</code> = the country is not in that source; <code>http_403</code> = the source refused the request (e.g. bot protection); <code>unreachable</code> = no connection or time-out.</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
