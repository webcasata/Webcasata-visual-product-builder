<?php
/**
 * Fired during plugin activation.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Activator.
 */
class WVPB_Activator {

	/**
	 * Runs once, on plugin activation.
	 *
	 * Uses add_option() rather than update_option() throughout: if the
	 * option already exists (e.g. the plugin was deactivated and is now
	 * being reactivated, or was updated) this is a no-op and a shop
	 * owner's existing choices are left exactly as they were.
	 *
	 * @return void
	 */
	public static function activate() {

		add_option(
			WVPB_OPTION_SETTINGS,
			array(
				'delete_data_on_uninstall' => false,
				'button_position'          => 'before_cart',
				'show_on_archive'          => false,
				'show_sticky_bar'          => false,
				'swatch_shape'             => 'circle',
				'swatch_radius'            => 10,
				'active_color'             => '#c9862e',
				'notification_email'       => get_option( 'admin_email' ),
			)
		);

		add_option( 'wvpb_db_version', WVPB_VERSION );

		// Register the post type before flushing, so its rewrite
		// structure (if any is added in a later version) is accounted
		// for. Harmless no-op today, since the CPT is not public.
		if ( class_exists( 'WVPB_Post_Types' ) ) {
			WVPB_Post_Types::register();
			WVPB_Post_Types::grant_capabilities();
		}

		flush_rewrite_rules();
	}
}
