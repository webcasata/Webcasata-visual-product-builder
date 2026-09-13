<?php
/**
 * Registers the post type used to keep a permanent, browsable record
 * of every "My Design" enquiry submission.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Enquiry_Post_Type.
 */
class WVPB_Enquiry_Post_Type {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'wvpb_enquiry';

	/**
	 * Singular/plural bases used to build this post type's own
	 * capability names — same reasoning as WVPB_Post_Types: a dedicated
	 * capability_type avoids colliding with WordPress's reserved
	 * edit_post/read_post/delete_post meta capability names.
	 *
	 * @var string
	 */
	const CAP_SINGULAR = 'wvpb_enquiry';
	const CAP_PLURAL   = 'wvpb_enquiries';

	/**
	 * Hooks registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers the `wvpb_enquiry` post type.
	 *
	 * Entries are only ever created programmatically, from the AJAX
	 * submission handler — never manually — so "Add New" is disabled
	 * by mapping create_posts to WordPress's built-in "nobody has this"
	 * sentinel capability.
	 *
	 * @return void
	 */
	public static function register() {

		$labels = array(
			'name'               => _x( 'Design Enquiries', 'post type general name', 'webcasata-visual-product-builder' ),
			'singular_name'      => _x( 'Design Enquiry', 'post type singular name', 'webcasata-visual-product-builder' ),
			'menu_name'          => _x( 'Design Enquiries', 'admin menu', 'webcasata-visual-product-builder' ),
			'all_items'          => __( 'All Enquiries', 'webcasata-visual-product-builder' ),
			'edit_item'          => __( 'Enquiry Details', 'webcasata-visual-product-builder' ),
			'view_item'          => __( 'View Enquiry', 'webcasata-visual-product-builder' ),
			'search_items'       => __( 'Search Enquiries', 'webcasata-visual-product-builder' ),
			'not_found'          => __( 'No enquiries found.', 'webcasata-visual-product-builder' ),
			'not_found_in_trash' => __( 'No enquiries found in Trash.', 'webcasata-visual-product-builder' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . WVPB_CPT_CUSTOMIZER,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => array( self::CAP_SINGULAR, self::CAP_PLURAL ),
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'create_posts' => 'do_not_allow',
			),
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			'menu_icon'           => 'dashicons-email-alt',
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * The primitive capabilities this post type needs. See
	 * WVPB_Post_Types::get_primitive_capabilities() for why meta
	 * capabilities (edit_wvpb_enquiry etc.) are deliberately excluded.
	 *
	 * @return string[]
	 */
	public static function get_primitive_capabilities() {
		$base = self::CAP_PLURAL;
		return array(
			"edit_{$base}",
			"edit_others_{$base}",
			"edit_private_{$base}",
			"edit_published_{$base}",
			"publish_{$base}",
			"read_private_{$base}",
			"delete_{$base}",
			"delete_private_{$base}",
			"delete_published_{$base}",
			"delete_others_{$base}",
		);
	}

	/**
	 * Grants this post type's capabilities to Administrator and Shop
	 * Manager. Called on activation; safe to call repeatedly.
	 *
	 * @return void
	 */
	public static function grant_capabilities() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::get_primitive_capabilities() as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Removes this post type's capabilities from Administrator and
	 * Shop Manager. Only ever called from uninstall.php, and only with
	 * explicit opt-in.
	 *
	 * @return void
	 */
	public static function remove_capabilities() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::get_primitive_capabilities() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
