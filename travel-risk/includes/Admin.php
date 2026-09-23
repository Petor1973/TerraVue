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
		</div>
		<?php
	}
}
