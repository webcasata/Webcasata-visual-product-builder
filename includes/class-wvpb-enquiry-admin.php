<?php
/**
 * Admin-side display and editing for Design Enquiry records: custom
 * list table columns, a read-only details metabox, and an editable
 * Status/Notes metabox.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Enquiry_Admin.
 */
class WVPB_Enquiry_Admin {

	const META_NAME       = '_wvpb_enquiry_name';
	const META_EMAIL      = '_wvpb_enquiry_email';
	const META_PHONE      = '_wvpb_enquiry_phone';
	const META_COMMENT    = '_wvpb_enquiry_comment';
	const META_PRODUCT_ID = '_wvpb_enquiry_product_id';
	const META_IMAGE_ID   = '_wvpb_enquiry_image_id';
	const META_STATUS     = '_wvpb_enquiry_status';
	const META_NOTES      = '_wvpb_enquiry_notes';

	const NONCE_ACTION = 'wvpb_save_enquiry';
	const NONCE_FIELD  = 'wvpb_enquiry_nonce';

	/**
	 * Hooks registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		$post_type = WVPB_Enquiry_Post_Type::POST_TYPE;

		add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'add_columns' ) );
		add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metaboxes' ) );
		add_action( "save_post_{$post_type}", array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * The three statuses an enquiry can be in.
	 *
	 * @return array<string, string> value => label
	 */
	public static function get_statuses() {
		return array(
			'new'       => __( 'New', 'webcasata-visual-product-builder' ),
			'contacted' => __( 'Contacted', 'webcasata-visual-product-builder' ),
			'closed'    => __( 'Closed', 'webcasata-visual-product-builder' ),
		);
	}

	/**
	 * Human-readable label for a status value, falling back to "New"
	 * for anything unrecognized (including entries with no status set
	 * at all, which defaults to New).
	 *
	 * @param string $status Raw status value.
	 * @return string
	 */
	public static function get_status_label( $status ) {
		$statuses = self::get_statuses();
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['new'];
	}

	/**
	 * Inserts Email/Phone/Product/Status columns right after the
	 * default Title column.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_columns( $columns ) {

		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'title' === $key ) {
				$new_columns['wvpb_email']   = __( 'Email', 'webcasata-visual-product-builder' );
				$new_columns['wvpb_phone']   = __( 'Phone', 'webcasata-visual-product-builder' );
				$new_columns['wvpb_product'] = __( 'Product', 'webcasata-visual-product-builder' );
				$new_columns['wvpb_status']  = __( 'Status', 'webcasata-visual-product-builder' );
			}
		}

		return $new_columns;
	}

	/**
	 * Renders one custom column's content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Row's post ID.
	 * @return void
	 */
	public static function render_column( $column, $post_id ) {

		switch ( $column ) {

			case 'wvpb_email':
				echo esc_html( get_post_meta( $post_id, self::META_EMAIL, true ) );
				break;

			case 'wvpb_phone':
				$phone = get_post_meta( $post_id, self::META_PHONE, true );
				echo $phone ? esc_html( $phone ) : '&#8212;';
				break;

			case 'wvpb_product':
				$product_id = (int) get_post_meta( $post_id, self::META_PRODUCT_ID, true );
				if ( $product_id && get_post( $product_id ) ) {
					printf(
						'<a href="%1$s">%2$s</a>',
						esc_url( (string) get_edit_post_link( $product_id ) ),
						esc_html( get_the_title( $product_id ) )
					);
				} else {
					echo '&#8212;';
				}
				break;

			case 'wvpb_status':
				$status = get_post_meta( $post_id, self::META_STATUS, true );
				if ( ! $status ) {
					$status = 'new';
				}
				printf(
					'<span class="wvpb-status-badge wvpb-status-%1$s">%2$s</span>',
					esc_attr( $status ),
					esc_html( self::get_status_label( $status ) )
				);
				break;
		}
	}

	/**
	 * Registers the two metaboxes on the enquiry edit screen.
	 *
	 * @return void
	 */
	public static function add_metaboxes() {

		add_meta_box(
			'wvpb_enquiry_status',
			__( 'Status & Follow-up', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_status_metabox' ),
			WVPB_Enquiry_Post_Type::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'wvpb_enquiry_details',
			__( 'Enquiry Details', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render_details_metabox' ),
			WVPB_Enquiry_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Renders the editable Status/Notes metabox.
	 *
	 * @param WP_Post $post Enquiry being viewed.
	 * @return void
	 */
	public static function render_status_metabox( $post ) {

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$status = get_post_meta( $post->ID, self::META_STATUS, true );
		if ( ! $status ) {
			$status = 'new';
		}
		$notes = get_post_meta( $post->ID, self::META_NOTES, true );
		?>
		<p>
			<label for="wvpb-enquiry-status"><strong><?php esc_html_e( 'Status', 'webcasata-visual-product-builder' ); ?></strong></label><br />
			<select id="wvpb-enquiry-status" name="wvpb_enquiry_status" style="width:100%;">
				<?php foreach ( self::get_statuses() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="wvpb-enquiry-notes"><strong><?php esc_html_e( 'Notes', 'webcasata-visual-product-builder' ); ?></strong></label><br />
			<textarea id="wvpb-enquiry-notes" name="wvpb_enquiry_notes" rows="6" style="width:100%;"><?php echo esc_textarea( $notes ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Private — for your own tracking. Never shown to the customer.', 'webcasata-visual-product-builder' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Renders the read-only submission details metabox.
	 *
	 * @param WP_Post $post Enquiry being viewed.
	 * @return void
	 */
	public static function render_details_metabox( $post ) {

		$name       = get_post_meta( $post->ID, self::META_NAME, true );
		$email      = get_post_meta( $post->ID, self::META_EMAIL, true );
		$phone      = get_post_meta( $post->ID, self::META_PHONE, true );
		$comment    = get_post_meta( $post->ID, self::META_COMMENT, true );
		$product_id = (int) get_post_meta( $post->ID, self::META_PRODUCT_ID, true );
		$image_id   = (int) get_post_meta( $post->ID, self::META_IMAGE_ID, true );
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Name', 'webcasata-visual-product-builder' ); ?></th>
				<td><?php echo esc_html( $name ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Email', 'webcasata-visual-product-builder' ); ?></th>
				<td><a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a></td>
			</tr>
			<?php if ( $phone ) : ?>
			<tr>
				<th><?php esc_html_e( 'Phone', 'webcasata-visual-product-builder' ); ?></th>
				<td><?php echo esc_html( $phone ); ?></td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><?php esc_html_e( 'Product', 'webcasata-visual-product-builder' ); ?></th>
				<td>
					<?php if ( $product_id && get_post( $product_id ) ) : ?>
						<a href="<?php echo esc_url( (string) get_permalink( $product_id ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( get_the_title( $product_id ) ); ?>
						</a>
					<?php else : ?>
						&#8212;
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $comment ) : ?>
			<tr>
				<th><?php esc_html_e( 'Comment', 'webcasata-visual-product-builder' ); ?></th>
				<td><?php echo nl2br( esc_html( $comment ) ); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ( $image_id && wp_get_attachment_url( $image_id ) ) : ?>
			<tr>
				<th><?php esc_html_e( 'Reference Image', 'webcasata-visual-product-builder' ); ?></th>
				<td>
					<a href="<?php echo esc_url( (string) wp_get_attachment_url( $image_id ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo wp_get_attachment_image( $image_id, 'medium' ); ?>
					</a>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><?php esc_html_e( 'Submitted', 'webcasata-visual-product-builder' ); ?></th>
				<td><?php echo esc_html( get_the_date( '', $post ) . ' ' . get_the_time( '', $post ) ); ?></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves Status/Notes. Every other field on an enquiry is set once,
	 * at submission time, and is never editable through wp-admin — this
	 * handler deliberately only ever touches these two meta keys.
	 *
	 * @param int $post_id Enquiry post ID.
	 * @return void
	 */
	public static function save( $post_id ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if (
			! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION )
		) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['wvpb_enquiry_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_POST['wvpb_enquiry_status'] ) );
			if ( ! array_key_exists( $status, self::get_statuses() ) ) {
				$status = 'new';
			}
			update_post_meta( $post_id, self::META_STATUS, $status );
		}

		if ( isset( $_POST['wvpb_enquiry_notes'] ) ) {
			update_post_meta( $post_id, self::META_NOTES, sanitize_textarea_field( wp_unslash( $_POST['wvpb_enquiry_notes'] ) ) );
		}
	}

	/**
	 * Enqueues the status-badge stylesheet, only on this post type's
	 * own list and edit screens.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {

		if ( ! in_array( $hook, array( 'edit.php', 'post.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || WVPB_Enquiry_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'wvpb-enquiries-admin',
			WVPB_PLUGIN_URL . 'admin/css/enquiries.css',
			array(),
			WVPB_VERSION
		);
	}
}
