<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Deactivator.
 */
class WVPB_Deactivator {

	/**
	 * Runs once, on plugin deactivation.
	 *
	 * Deliberately does nothing except flush rewrite rules. Customizers,
	 * uploaded layer images and settings must survive a deactivate →
	 * reactivate cycle (or a plugin update, which deactivates then
	 * reactivates) completely untouched. Only uninstall.php may ever
	 * delete data, and only with the shop owner's explicit opt-in.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
