<?php
/**
 * Registers the Customizer custom post type.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Post_Types.
 */
class WVPB_Post_Types {

	/**
	 * Singular base used to build this post type's own capability names.
	 *
	 * @var string
	 */
	const CAP_SINGULAR = 'wvpb_customizer';

	/**
	 * Plural base used to build this post type's own capability names.
	 *
	 * @var string
	 */
	const CAP_PLURAL = 'wvpb_customizers';

	/**
	 * Hooks registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers the `wvpb_customizer` post type.
	 *
	 * Uses a dedicated capability_type (a unique singular/plural pair)
	 * rather than reusing 'post' with remapped capability slots. This
	 * is the same pattern WooCommerce itself uses for `product` and
	 * `shop_order`: WordPress computes a full, uniquely-named set of
	 * capabilities (edit_wvpb_customizers, delete_wvpb_customizers, …)
	 * from these two strings, and none of them collide with the
	 * reserved edit_post/read_post/delete_post names — which is what
	 * avoids the "map_meta_cap called incorrectly" notice that a flat
	 * remap of those three specific slots triggers. See
	 * grant_capabilities() below for who actually gets these caps.
	 *
	 * @return void
	 */
	public static function register() {

		$labels = array(
			'name'               => _x( 'Customizers', 'post type general name', 'webcasata-visual-product-builder' ),
			'singular_name'      => _x( 'Customizer', 'post type singular name', 'webcasata-visual-product-builder' ),
			'menu_name'          => _x( 'Customizers', 'admin menu', 'webcasata-visual-product-builder' ),
			'add_new'            => _x( 'Add New', 'customizer', 'webcasata-visual-product-builder' ),
			'add_new_item'       => __( 'Add New Customizer', 'webcasata-visual-product-builder' ),
			'edit_item'          => __( 'Edit Customizer', 'webcasata-visual-product-builder' ),
			'new_item'           => __( 'New Customizer', 'webcasata-visual-product-builder' ),
			'view_item'          => __( 'View Customizer', 'webcasata-visual-product-builder' ),
			'search_items'       => __( 'Search Customizers', 'webcasata-visual-product-builder' ),
			'not_found'          => __( 'No customizers found.', 'webcasata-visual-product-builder' ),
			'not_found_in_trash' => __( 'No customizers found in Trash.', 'webcasata-visual-product-builder' ),
			'all_items'          => __( 'All Customizers', 'webcasata-visual-product-builder' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => array( self::CAP_SINGULAR, self::CAP_PLURAL ),
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			'menu_icon'           => 'dashicons-layout',
			'menu_position'       => 56, // Just under WooCommerce's own menu.
		);

		register_post_type( WVPB_CPT_CUSTOMIZER, $args );
	}

	/**
	 * The primitive capabilities this post type needs, in the form
	 * WordPress computes from CAP_PLURAL above.
	 *
	 * Deliberately excludes the singular meta capabilities
	 * (edit_wvpb_customizer / read_wvpb_customizer / delete_wvpb_customizer)
	 * — those are meta capabilities WordPress resolves dynamically per
	 * post via map_meta_cap(), and per WordPress's own guidance, meta
	 * capabilities should never be granted to a role directly.
	 *
	 * @return string[] Capability names.
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
	 * Manager — the same two roles WooCommerce itself grants product
	 * and order management capabilities to.
	 *
	 * Called on activation. Safe to call repeatedly: WP_Role::add_cap()
	 * is a no-op if the role already has the capability, so reactivating
	 * the plugin never resets anything a site admin may have customized.
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
	 * Shop Manager.
	 *
	 * Only ever called from uninstall.php, and only if the shop owner
	 * explicitly opted in to full data deletion — never on deactivation.
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
