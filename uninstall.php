<?php
/**
 * Fires only when the plugin is DELETED from the Plugins screen —
 * never on a plain deactivation.
 *
 * By default this file does nothing but check the opt-in flag and
 * return: every customizer, uploaded layer image reference, product
 * assignment and setting is left exactly as it was, so reinstalling
 * the plugin later restores a shop owner's work untouched.
 *
 * Data is only removed if they explicitly ticked "Delete all data on
 * uninstall" on the Settings screen (see class-wvpb-settings.php).
 *
 * @package Webcasata_Visual_Product_Builder
 */

// WordPress defines this constant only when it is genuinely running
// an uninstall — guards against this file being loaded directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wvpb_settings = get_option( 'wvpb_settings', array() );

if ( empty( $wvpb_settings['delete_data_on_uninstall'] ) ) {
	// Opted out — the default. Leave every trace of the plugin's data in place.
	return;
}

/*
 * ---------------------------------------------------------------------------
 * 1. Remove the capabilities this plugin granted to Administrator and
 *    Shop Manager on activation (see WVPB_Post_Types::grant_capabilities()).
 *    Kept self-contained here rather than loading the plugin's classes,
 *    since uninstall.php is expected to run standalone.
 * ---------------------------------------------------------------------- */
foreach ( array( 'administrator', 'shop_manager' ) as $wvpb_role_name ) {
	$wvpb_role = get_role( $wvpb_role_name );
	if ( ! $wvpb_role ) {
		continue;
	}
	foreach (
		array(
			'edit_wvpb_customizers',
			'edit_others_wvpb_customizers',
			'edit_private_wvpb_customizers',
			'edit_published_wvpb_customizers',
			'publish_wvpb_customizers',
			'read_private_wvpb_customizers',
			'delete_wvpb_customizers',
			'delete_private_wvpb_customizers',
			'delete_published_wvpb_customizers',
			'delete_others_wvpb_customizers',
		) as $wvpb_cap
	) {
		$wvpb_role->remove_cap( $wvpb_cap );
	}
}

/*
 * ---------------------------------------------------------------------------
 * 2. Delete every Customizer post and its post meta (the JSON config).
 *
 * wp_delete_post( ..., true ) bypasses Trash and removes post meta with it.
 * This does NOT delete the PNG/SVG attachments referenced inside the config
 * — see the note at the bottom of this file for why that's deliberate.
 * ---------------------------------------------------------------------- */
$wvpb_customizer_ids = get_posts(
	array(
		'post_type'      => 'wvpb_customizer',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $wvpb_customizer_ids as $wvpb_customizer_id ) {
	wp_delete_post( $wvpb_customizer_id, true );
}

/*
 * ---------------------------------------------------------------------------
 * 3. Delete the per-attribute-term layer images this plugin adds to
 *    WooCommerce attribute terms (e.g. Attributes → Flavour → Mango).
 *
 * There's no WordPress API for "delete this term meta key everywhere," so a
 * direct, prepared query against our own plugin-prefixed keys is the
 * accepted approach here — this file is exactly the place WordPress.org
 * review expects that kind of cleanup query.
 * ---------------------------------------------------------------------- */
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s OR meta_key = %s",
		'_wvpb_term_image_png',
		'_wvpb_term_image_svg'
	)
);

/*
 * ---------------------------------------------------------------------------
 * 4. Delete product-level "which customizer applies to this product" meta.
 * ---------------------------------------------------------------------- */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
		'_wvpb_assigned_customizer'
	)
);

/*
 * ---------------------------------------------------------------------------
 * 5. Delete plugin options.
 * ---------------------------------------------------------------------- */
delete_option( 'wvpb_settings' );
delete_option( 'wvpb_db_version' );

/*
 * ---------------------------------------------------------------------------
 * 6. Delete any transients the plugin may have set.
 * ---------------------------------------------------------------------- */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_wvpb_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wvpb_' ) . '%'
	)
);

/*
 * ---------------------------------------------------------------------------
 * Note on media library attachments:
 *
 * Uploaded PNG/SVG layer images are standalone Media Library attachments,
 * not children of the Customizer post, so they are deliberately NOT deleted
 * here even in the opt-in case above. A shop's media library is often
 * reused elsewhere (product galleries, other pages), so silently mass-
 * deleting attachments on uninstall would be a surprising, hard-to-reverse
 * side effect that goes beyond what "delete this plugin's data" implies.
 * ---------------------------------------------------------------------- */
