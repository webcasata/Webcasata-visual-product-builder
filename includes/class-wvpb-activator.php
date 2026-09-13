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

		// Register the post types before flushing, so their rewrite
		// structures (if any are added in a later version) are
		// accounted for. Harmless no-op today, since neither is public.
		if ( class_exists( 'WVPB_Post_Types' ) ) {
			WVPB_Post_Types::register();
			WVPB_Post_Types::grant_capabilities();
		}
		if ( class_exists( 'WVPB_Enquiry_Post_Type' ) ) {
			WVPB_Enquiry_Post_Type::register();
			WVPB_Enquiry_Post_Type::grant_capabilities();
		}

		self::maybe_create_thankyou_page();

		flush_rewrite_rules();
	}

	/**
	 * Creates the "Design Enquiry Confirmation" page the My Design
	 * enquiry form redirects to, if one doesn't already exist.
	 *
	 * Checks the page itself still exists (not just the option) before
	 * skipping creation, so a shop owner who deleted the page by
	 * mistake gets a working one back on the next activation rather
	 * than a permanently broken redirect.
	 *
	 * @return void
	 */
	private static function maybe_create_thankyou_page() {

		$page_id = get_option( 'wvpb_thankyou_page_id' );

		if ( $page_id && get_post( $page_id ) && 'page' === get_post_type( $page_id ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Design Enquiry Confirmation', 'webcasata-visual-product-builder' ),
				'post_content' => '[wvpb_enquiry_thankyou]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'wvpb_thankyou_page_id', $page_id );
		}
	}
}
