<?php
/**
 * Plugin Name:       Webcasata Visual Product Builder
 * Plugin URI:        https://webcasata.com/visual-product-builder/
 * Description:       Turn any WooCommerce product into a visual, layer-based customizer — build-your-own cakes, pizzas, gift boxes and more, composited live from admin-uploaded PNG/SVG option layers.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Webcasata
 * Author URI:        https://webcasata.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webcasata-visual-product-builder
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * @package Webcasata_Visual_Product_Builder
 */

// Block direct access to this file — never execute plugin code outside of WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ---------------------------------------------------------------------------
 * Constants
 *
 * Every constant is prefixed WVPB_ so it can never collide with another
 * plugin's globals — required practice for WordPress.org submission.
 * ---------------------------------------------------------------------- */
define( 'WVPB_VERSION', '1.0.0' );
define( 'WVPB_PLUGIN_FILE', __FILE__ );
define( 'WVPB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WVPB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WVPB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WVPB_CPT_CUSTOMIZER', 'wvpb_customizer' );
define( 'WVPB_OPTION_SETTINGS', 'wvpb_settings' );

/*
 * ---------------------------------------------------------------------------
 * Core includes — Stage 1 only.
 *
 * Later stages (builder metabox + save handler, attribute images, product
 * assignment, frontend modal, compositor, cart/order integration) add their
 * own class-wvpb-{name}.php file here, following the same convention.
 * ---------------------------------------------------------------------- */
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-activator.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-deactivator.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-i18n.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-post-types.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-enquiry-post-type.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-enquiry-admin.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-customizer-builder.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-attribute-images.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-product-assign.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-frontend.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-design-enquiry.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-pricing.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-settings.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-admin-menu.php';
require_once WVPB_PLUGIN_DIR . 'includes/class-wvpb-plugin.php';

/*
 * ---------------------------------------------------------------------------
 * Activation / deactivation.
 *
 * Activation only ever ADDS sane defaults, never overwrites existing data —
 * so deactivating and reactivating (e.g. during a plugin update) can never
 * reset a shop owner's saved settings.
 *
 * Deactivation only flushes rewrite rules. It must never delete anything:
 * the only place data is ever removed is uninstall.php, and only if the
 * shop owner explicitly opted in via Settings — see that file for why.
 * ---------------------------------------------------------------------- */
register_activation_hook( __FILE__, array( 'WVPB_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WVPB_Deactivator', 'deactivate' ) );

/*
 * ---------------------------------------------------------------------------
 * WooCommerce High-Performance Order Storage (HPOS) compatibility.
 *
 * Declared unconditionally, as WooCommerce recommends, regardless of
 * whether the store has HPOS enabled.
 * ---------------------------------------------------------------------- */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WVPB_PLUGIN_FILE,
				true
			);
		}
	}
);

/*
 * ---------------------------------------------------------------------------
 * Bootstrap — runs after every plugin (including WooCommerce) has loaded.
 * ---------------------------------------------------------------------- */
add_action( 'plugins_loaded', 'wvpb_bootstrap', 20 );

/**
 * Boots the plugin once we know whether WooCommerce is active.
 *
 * Kept as a single top-level function (rather than running code at the
 * file's top level) so a missing dependency degrades to an admin notice
 * instead of a fatal error on sites without WooCommerce.
 *
 * @return void
 */
function wvpb_bootstrap() {

	if ( ! wvpb_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'wvpb_woocommerce_missing_notice' );
		return;
	}

	WVPB_Plugin::instance()->run();
}

/**
 * Checks whether WooCommerce is active on this site.
 *
 * @return bool True if the WooCommerce plugin is loaded.
 */
function wvpb_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Prints an admin notice when WooCommerce is missing.
 *
 * Output is escaped even though the string is developer-controlled —
 * translators could otherwise introduce unescaped markup via a .po file.
 *
 * @return void
 */
function wvpb_woocommerce_missing_notice() {

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: WooCommerce plugin name. */
					__( '<strong>Webcasata Visual Product Builder</strong> requires %s to be installed and active.', 'webcasata-visual-product-builder' ),
					'WooCommerce'
				),
				array( 'strong' => array() )
			);
			?>
		</p>
	</div>
	<?php
}
