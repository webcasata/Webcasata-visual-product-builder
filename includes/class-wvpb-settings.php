<?php
/**
 * Plugin settings screen, built on the WordPress Settings API.
 *
 * Using the Settings API (rather than hand-rolling a form and reading
 * $_POST directly) gets nonce verification, capability checks on the
 * options.php submission handler, and a sanitize callback for free —
 * all the pieces WordPress.org review looks for on a settings screen.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Settings.
 */
class WVPB_Settings {

	const SETTINGS_GROUP = 'wvpb_settings_group';
	const SETTINGS_PAGE  = 'wvpb-settings';

	/**
	 * Hooks registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Registers the setting, its section, and its one field.
	 *
	 * @return void
	 */
	public static function register_settings() {

		register_setting(
			self::SETTINGS_GROUP,
			WVPB_OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(
					'delete_data_on_uninstall' => false,
					'button_position'          => 'before_cart',
					'show_on_archive'          => false,
					'show_sticky_bar'          => false,
					'swatch_shape'             => 'circle',
					'swatch_radius'            => 10,
					'active_color'             => '#c9862e',
					'notification_email'       => get_option( 'admin_email' ),
				),
			)
		);

		add_settings_section(
			'wvpb_data_section',
			__( 'Data', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_data_section_intro' ),
			self::SETTINGS_PAGE
		);

		add_settings_field(
			'wvpb_delete_data_on_uninstall',
			__( 'On uninstall', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_delete_data_field' ),
			self::SETTINGS_PAGE,
			'wvpb_data_section'
		);

		add_settings_section(
			'wvpb_display_section',
			__( 'Frontend Display', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_display_section_intro' ),
			self::SETTINGS_PAGE
		);

		add_settings_field(
			'wvpb_button_position',
			__( 'Customize button position', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_button_position_field' ),
			self::SETTINGS_PAGE,
			'wvpb_display_section'
		);

		add_settings_field(
			'wvpb_show_on_archive',
			__( 'Shop / category pages', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_show_on_archive_field' ),
			self::SETTINGS_PAGE,
			'wvpb_display_section'
		);

		add_settings_field(
			'wvpb_show_sticky_bar',
			__( 'Sticky bar', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_sticky_bar_field' ),
			self::SETTINGS_PAGE,
			'wvpb_display_section'
		);

		add_settings_section(
			'wvpb_style_section',
			__( 'Selection Style', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_style_section_intro' ),
			self::SETTINGS_PAGE
		);

		add_settings_field(
			'wvpb_swatch_shape',
			__( 'Swatch shape', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_swatch_shape_field' ),
			self::SETTINGS_PAGE,
			'wvpb_style_section'
		);

		add_settings_field(
			'wvpb_swatch_radius',
			__( 'Custom corner radius', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_swatch_radius_field' ),
			self::SETTINGS_PAGE,
			'wvpb_style_section'
		);

		add_settings_field(
			'wvpb_active_color',
			__( 'Active border color', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_active_color_field' ),
			self::SETTINGS_PAGE,
			'wvpb_style_section'
		);

		add_settings_section(
			'wvpb_enquiry_section',
			__( 'Design Enquiries', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_enquiry_section_intro' ),
			self::SETTINGS_PAGE
		);

		add_settings_field(
			'wvpb_notification_email',
			__( 'Notification email', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_notification_email_field' ),
			self::SETTINGS_PAGE,
			'wvpb_enquiry_section'
		);
	}

	/**
	 * The swatch-shape values the settings screen (and the frontend
	 * CSS variables in WVPB_Frontend) both recognize.
	 *
	 * @return string[]
	 */
	public static function get_allowed_swatch_shapes() {
		return array( 'circle', 'square', 'rounded' );
	}

	/**
	 * The button-position values the settings screen (and the frontend
	 * hook wiring in WVPB_Frontend) both recognize.
	 *
	 * @return string[]
	 */
	public static function get_allowed_button_positions() {
		return array( 'before_cart', 'after_cart', 'before_summary', 'after_summary' );
	}

	/**
	 * Sanitizes the settings array before WordPress saves it.
	 *
	 * Every key is explicitly whitelisted and cast to its expected
	 * type — nothing arriving from the form is trusted or stored as-is,
	 * and unrecognized keys are simply dropped.
	 *
	 * @param mixed $input Raw value submitted from the settings form.
	 * @return array Sanitized settings, merged over any existing values.
	 */
	public static function sanitize( $input ) {

		$existing = get_option( WVPB_OPTION_SETTINGS, array() );
		$input    = is_array( $input ) ? $input : array();

		$position = isset( $input['button_position'] ) ? sanitize_key( $input['button_position'] ) : 'before_cart';
		if ( ! in_array( $position, self::get_allowed_button_positions(), true ) ) {
			$position = 'before_cart';
		}

		$shape = isset( $input['swatch_shape'] ) ? sanitize_key( $input['swatch_shape'] ) : 'circle';
		if ( ! in_array( $shape, self::get_allowed_swatch_shapes(), true ) ) {
			$shape = 'circle';
		}

		// Clamped to a sane range rather than trusting an arbitrary
		// number — an unbounded radius could otherwise be used to
		// inject an oversized value into the inline stylesheet.
		$radius = isset( $input['swatch_radius'] ) ? absint( $input['swatch_radius'] ) : 10;
		$radius = max( 0, min( 100, $radius ) );

		$active_color = isset( $input['active_color'] ) ? sanitize_hex_color( $input['active_color'] ) : '';
		if ( ! $active_color ) {
			$active_color = '#c9862e';
		}

		$notification_email = isset( $input['notification_email'] ) ? sanitize_email( $input['notification_email'] ) : '';
		if ( ! $notification_email || ! is_email( $notification_email ) ) {
			$notification_email = get_option( 'admin_email' );
		}

		$sanitized = array(
			'delete_data_on_uninstall' => ! empty( $input['delete_data_on_uninstall'] ),
			'button_position'          => $position,
			'show_on_archive'          => ! empty( $input['show_on_archive'] ),
			'show_sticky_bar'          => ! empty( $input['show_sticky_bar'] ),
			'swatch_shape'             => $shape,
			'swatch_radius'            => $radius,
			'active_color'             => $active_color,
			'notification_email'       => $notification_email,
		);

		return wp_parse_args( $sanitized, is_array( $existing ) ? $existing : array() );
	}

	/**
	 * Prints the intro text for the Data section.
	 *
	 * @return void
	 */
	public static function render_data_section_intro() {
		echo '<p>' . esc_html__( 'Your customizers, uploaded layer images, and settings are always kept if you simply deactivate this plugin.', 'webcasata-visual-product-builder' ) . '</p>';
	}

	/**
	 * Renders the "delete data on uninstall" checkbox field.
	 *
	 * @return void
	 */
	public static function render_delete_data_field() {

		$settings = get_option( WVPB_OPTION_SETTINGS, array() );
		$checked  = ! empty( $settings['delete_data_on_uninstall'] );
		?>
		<label for="wvpb_delete_data_on_uninstall">
			<input
				type="checkbox"
				id="wvpb_delete_data_on_uninstall"
				name="<?php echo esc_attr( WVPB_OPTION_SETTINGS ); ?>[delete_data_on_uninstall]"
				value="1"
				<?php checked( $checked ); ?>
			/>
			<?php esc_html_e( 'Permanently delete all customizers, layer images, and settings when this plugin is deleted from the Plugins screen.', 'webcasata-visual-product-builder' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Leave this unchecked (the default) if you might reinstall the plugin later and want your work to still be there.', 'webcasata-visual-product-builder' ); ?>
		</p>
		<?php
	}

	/**
	 * Prints the intro text for the Frontend Display section.
	 *
	 * @return void
	 */
	public static function render_display_section_intro() {
		echo '<p>' . esc_html__( 'Control where the Customize button appears on the storefront.', 'webcasata-visual-product-builder' ) . '</p>';
	}

	/**
	 * Renders the button-position dropdown.
	 *
	 * @return void
	 */
	public static function render_button_position_field() {

		$settings = get_option( WVPB_OPTION_SETTINGS, array() );
		$current  = isset( $settings['button_position'] ) ? $settings['button_position'] : 'before_cart';

		$labels = array(
			'before_cart'    => __( 'Before the Add to Cart button (default)', 'webcasata-visual-product-builder' ),
			'after_cart'     => __( 'After the Add to Cart button', 'webcasata-visual-product-builder' ),
			'before_summary' => __( 'Above the product title', 'webcasata-visual-product-builder' ),
			'after_summary'  => __( 'Below the short description', 'webcasata-visual-product-builder' ),
		);
		?>
		<select name="<?php echo esc_attr( WVPB_OPTION_SETTINGS ); ?>[button_position]">
			<?php foreach ( self::get_allowed_button_positions() as $value ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $labels[ $value ] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Renders the "show on archive pages" checkbox.
	 *
	 * @return void
	 */
	public static function render_show_on_archive_field() {

		$settings = get_option( WVPB_OPTION_SETTINGS, array() );
		$checked  = ! empty( $settings['show_on_archive'] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( WVPB_OPTION_SETTINGS ); ?>[show_on_archive]" value="1" <?php checked( $checked ); ?> />
			<?php esc_html_e( 'Also show a Customize button on shop and category product listings.', 'webcasata-visual-product-builder' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Clicking it takes the customer to the product page with the customizer already open.', 'webcasata-visual-product-builder' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the "sticky bar" checkbox.
	 *
	 * @return void
	 */
	public static function render_sticky_bar_field() {

		$settings = get_option( WVPB_OPTION_SETTINGS, array() );
		$checked  = ! empty( $settings['show_sticky_bar'] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( WVPB_OPTION_SETTINGS ); ?>[show_sticky_bar]" value="1" <?php checked( $checked ); ?> />
			<?php esc_html_e( 'Show a sticky bar with the product name, price, and a Customize button once the customer scrolls past it.', 'webcasata-visual-product-builder' ); ?>
		</label>
		<?php
	}

	/**
	 * Prints the intro text for the Selection Style section.
	 *
	 * @return void
	 */
	public static function render_style_section_intro() {
		echo '<p>' . esc_html__( 'Control how swatch and button-group options look when selected in the customizer popup.', 'webcasata-visual-product-builder' ) . '</p>';
	}

	/**
	 * Renders the swatch-shape radio buttons.
	 *
	 * @return void
	 */
	public static function render_swatch_shape_field() {

		$settings = get_option( WVPB_OPTION_SETTINGS, array() );
		$current  = isset( $settings['swatch_shape'] ) ? $settings['swatch_shape'] : 'circle';

		$labels = array(
			'circle'  => __( 'Circular', 'webcasata-visual-product-builder' ),
			'square'  => __( 'Square', 'webcasata-visual-product-builder' ),
			'rounded' => __( 'Custom corner radius', 'webcasata-visual-product-builder' ),
		);

		foreach ( self::get_allowed_swatch_shapes() as $value ) {
			?>
			<label style="margin-right: 16px;">
				<input
					type="radio"
					name="<?php echo esc_attr( WVPB_OPTION_SETTINGS ); ?>[swatch_shape]"
					value="<?php echo esc_attr( $value ); ?>"
					<?php checked( $current, $value ); ?>
				/>
				<?php echo esc_html( $labels[ $value ] ); ?>
			</label>
			<?php
		}
	}

	/**
	 * Renders the custom-radius number field.
	 *
	 * @return void
	 */
	public static function render_swatch_radius_field() {

		$settings = get_option( WVPB_OPTION_SETTINGS, array() );
		$current  = isset( $settings['swatch_radius'] ) ? (int) $settings['swatch_radius'] : 10;
		?>
		<input
			type="number"
			min="0"
			max="100"
			name="<?php echo esc_attr( WVPB_OPTION_SETTINGS ); ?>[swatch_radius]"
			value="<?php echo esc_attr( $current ); ?>"
			style="width: 80px;"
		/> px
		<p class="description">
			<?php esc_html_e( 'Only used when the shape above is set to "Custom corner radius."', 'webcasata-visual-product-builder' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the active-color picker.
	 *
	 * @return void
	 */
	public static function render_active_color_field() {

		$settings = get_option( WVPB_OPTION_SETTINGS, array() );
		$current  = ! empty( $settings['active_color'] ) ? $settings['active_color'] : '#c9862e';
		?>
		<input
			type="color"
			name="<?php echo esc_attr( WVPB_OPTION_SETTINGS ); ?>[active_color]"
			value="<?php echo esc_attr( $current ); ?>"
		/>
		<p class="description">
			<?php esc_html_e( 'Border color shown around whichever option the customer currently has selected.', 'webcasata-visual-product-builder' ); ?>
		</p>
		<?php
	}

	/**
	 * Prints the intro text for the Design Enquiries section.
	 *
	 * @return void
	 */
	public static function render_enquiry_section_intro() {
		echo '<p>' . esc_html__( 'When a customer submits their own design instead of using the steps above, we email these details straight to you, save a record under Design Enquiries, and redirect them to a confirmation page.', 'webcasata-visual-product-builder' ) . '</p>';

		$page_id = get_option( 'wvpb_thankyou_page_id' );
		if ( $page_id && get_post( $page_id ) ) {
			printf(
				'<p>%s</p>',
				wp_kses(
					sprintf(
						/* translators: 1: view URL, 2: edit URL. */
						__( 'Confirmation page: <a href="%1$s" target="_blank" rel="noopener noreferrer">View</a> &middot; <a href="%2$s">Edit</a>', 'webcasata-visual-product-builder' ),
						esc_url( (string) get_permalink( $page_id ) ),
						esc_url( (string) get_edit_post_link( $page_id ) )
					),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				)
			);
		}
	}

	/**
	 * Renders the notification email field.
	 *
	 * @return void
	 */
	public static function render_notification_email_field() {

		$settings = get_option( WVPB_OPTION_SETTINGS, array() );
		$current  = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );
		?>
		<input
			type="email"
			name="<?php echo esc_attr( WVPB_OPTION_SETTINGS ); ?>[notification_email]"
			value="<?php echo esc_attr( $current ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Where "My Design" enquiries are sent. Defaults to your site\'s admin email.', 'webcasata-visual-product-builder' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the settings page shell.
	 *
	 * @return void
	 */
	public static function render_page() {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Webcasata Visual Product Builder — Settings', 'webcasata-visual-product-builder' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SETTINGS_GROUP ); // Outputs the nonce + option group fields.
				do_settings_sections( self::SETTINGS_PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
