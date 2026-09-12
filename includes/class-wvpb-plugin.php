<?php
/**
 * Main plugin orchestrator.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Plugin.
 *
 * A thin singleton whose only job is to call init() on every module once
 * we know it's safe to (WooCommerce confirmed active). Keeping this class
 * a short manifest — rather than growing real logic inside it — is what
 * lets Stage 2+ modules be added here as one line each.
 */
final class WVPB_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WVPB_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return WVPB_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — instantiate via instance() only.
	 */
	private function __construct() {}

	/**
	 * Registers every module's hooks.
	 *
	 * Called once, from the `plugins_loaded` bootstrap in the main
	 * plugin file, after WooCommerce has been confirmed active.
	 *
	 * @return void
	 */
	public function run() {

		WVPB_I18n::load();
		WVPB_Post_Types::init();
		WVPB_Customizer_Builder::init();
		WVPB_Attribute_Images::init();
		WVPB_Product_Assign::init();
		WVPB_Frontend::init();
		WVPB_Settings::init();
		WVPB_Admin_Menu::init();

		/*
		 * Stage 6+ modules register here, one line each, following the
		 * same "class exposes a static init(), init() adds its own
		 * hooks" pattern used above:
		 *
		 * WVPB_Cart::init();                // cart item data + order line item meta
		 * WVPB_Pricing::init();             // server-side price validation
		 */
	}
}
