<?php
/**
 * Bare page for the installed app (PWA): only the app, no theme header or footer.
 * The shortcode runs before wp_head() so its styles and scripts are enqueued in time.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

$app = do_shortcode( '[travel_risk]' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body class="trisk-standalone">
<?php echo $app; // phpcs:ignore WordPress.Security.EscapeOutput -- static container markup ?>
<?php wp_footer(); ?>
</body>
</html>
