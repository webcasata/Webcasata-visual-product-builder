<?php
/**
 * Loads the plugin's translations.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_I18n.
 */
class WVPB_I18n {

	/**
	 * Loads the .mo files from /languages.
	 *
	 * WordPress.org auto-loads translations for hosted plugins whose
	 * text domain matches the plugin slug, but this call is kept for
	 * correctness on sites that install translations manually.
	 *
	 * @return void
	 */
	public static function load() {
		load_plugin_textdomain(
			'webcasata-visual-product-builder',
			false,
			dirname( WVPB_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
