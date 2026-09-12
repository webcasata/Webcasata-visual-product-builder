<?php
/**
 * Admin menu registration.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Admin_Menu.
 */
class WVPB_Admin_Menu {

	/**
	 * Hooks registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Adds "Settings" under the Customizers menu that WordPress builds
	 * automatically for the `wvpb_customizer` post type, so the whole
	 * plugin lives under one menu instead of adding a second top-level
	 * entry.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . WVPB_CPT_CUSTOMIZER,
			__( 'Webcasata Visual Product Builder Settings', 'webcasata-visual-product-builder' ),
			__( 'Settings', 'webcasata-visual-product-builder' ),
			'manage_woocommerce',
			WVPB_Settings::SETTINGS_PAGE,
			array( 'WVPB_Settings', 'render_page' )
		);
	}
}
