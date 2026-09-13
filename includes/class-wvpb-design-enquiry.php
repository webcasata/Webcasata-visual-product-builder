<?php
/**
 * Handles "My Design" enquiry submissions from the frontend modal:
 * validates input, stores a permanent record, emails the shop owner,
 * and hands back a confirmation-page URL for the browser to redirect to.
 *
 * This is a public-facing AJAX endpoint (customers are never logged
 * in), so every value here is treated as untrusted input regardless
 * of what the frontend JS is supposed to send.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Design_Enquiry.
 */
class WVPB_Design_Enquiry {

	const NONCE_ACTION = 'wvpb_submit_enquiry';
	const AJAX_ACTION   = 'wvpb_submit_enquiry';
	const SHORTCODE      = 'wvpb_enquiry_thankyou';

	/**
	 * How long the confirmation page can show submission details for,
	 * in seconds (1 hour). After this, the link still lands on a
	 * friendly page — it just no longer shows the personal details,
	 * which matters more for privacy than convenience this long after
	 * submission.
	 *
	 * @var int
	 */
	const CONFIRMATION_TTL = HOUR_IN_SECONDS;

	/**
	 * Image mime types accepted for the reference-design upload.
	 *
	 * Deliberately excludes SVG: this endpoint accepts files from
	 * anonymous, logged-out visitors, and an SVG can carry an embedded
	 * <script> — allowing it here would be a stored-XSS vector in a way
	 * that the admin's own PNG/SVG layer uploads (which require an
	 * authenticated, capability-checked user) are not.
	 *
	 * @var string[]
	 */
	const ALLOWED_MIME_TYPES = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );

	/**
	 * Maximum accepted upload size, in bytes (5MB).
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 5242880;

	/**
	 * Hooks registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'handle_submission' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'handle_submission' ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_thankyou_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_thankyou_style' ) );
	}

	/**
	 * Processes a submitted enquiry.
	 *
	 * @return void Always ends the request via wp_send_json_success()/error().
	 */
	public static function handle_submission() {

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		// Honeypot: a genuine visitor never sees or fills this field
		// (it's hidden off-screen in the form). If it's filled, this is
		// almost certainly a bot — respond as if it worked, but skip
		// all real processing (no record, no email), so the bot gets
		// no signal to adapt to.
		if ( ! empty( $_POST['wvpb_hp'] ) ) {
			wp_send_json_success( array( 'redirect_url' => self::get_thankyou_url() ) );
		}

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';

		if ( '' === $name || '' === $email || ! is_email( $email ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please enter your name and a valid email address.', 'webcasata-visual-product-builder' ) )
			);
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		$selections = self::sanitize_selections(
			isset( $_POST['selections'] ) ? wp_unslash( $_POST['selections'] ) : ''
		);

		$attachment_id = 0;
		$upload_error  = '';
		if ( ! empty( $_FILES['design_image'] ) && ! empty( $_FILES['design_image']['name'] ) ) {
			$upload_result = self::handle_uploaded_image();
			if ( is_wp_error( $upload_result ) ) {
				$upload_error = $upload_result->get_error_message();
			} else {
				$attachment_id = $upload_result;
			}
		}

		$enquiry_id = self::create_enquiry_record( $name, $email, $phone, $comment, $product_id, $attachment_id );

		self::send_notification_email( $name, $email, $phone, $comment, $product, $selections, $attachment_id );

		$token = self::store_confirmation_snapshot( $name, $email, $phone, $comment, $product, $attachment_id );

		$response = array(
			'redirect_url' => self::get_thankyou_url( $token ),
		);
		if ( $upload_error ) {
			$response['upload_warning'] = $upload_error;
		}
		if ( is_wp_error( $enquiry_id ) ) {
			// The email still went out — the record just didn't save —
			// so this genuinely is still a success from the customer's
			// point of view. Logged for the admin to notice separately
			// rather than surfaced to the customer.
			// translators comment intentionally omitted: internal debug use only.
			error_log( 'WVPB: failed to save enquiry record — ' . $enquiry_id->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		wp_send_json_success( $response );
	}

	/**
	 * Creates the permanent, admin-visible record of this enquiry.
	 *
	 * @param string $name          Customer name.
	 * @param string $email         Customer email.
	 * @param string $phone         Customer phone (may be empty).
	 * @param string $comment       Customer comment (may be empty).
	 * @param int    $product_id    Product ID, or 0.
	 * @param int    $attachment_id Uploaded reference image attachment ID, or 0.
	 * @return int|WP_Error Post ID on success.
	 */
	private static function create_enquiry_record( $name, $email, $phone, $comment, $product_id, $attachment_id ) {

		$post_id = wp_insert_post(
			array(
				'post_type'   => WVPB_Enquiry_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%1$s — %2$s', $name, current_time( 'Y-m-d H:i' ) ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, WVPB_Enquiry_Admin::META_NAME, $name );
		update_post_meta( $post_id, WVPB_Enquiry_Admin::META_EMAIL, $email );
		update_post_meta( $post_id, WVPB_Enquiry_Admin::META_PHONE, $phone );
		update_post_meta( $post_id, WVPB_Enquiry_Admin::META_COMMENT, $comment );
		update_post_meta( $post_id, WVPB_Enquiry_Admin::META_STATUS, 'new' );
		if ( $product_id ) {
			update_post_meta( $post_id, WVPB_Enquiry_Admin::META_PRODUCT_ID, $product_id );
		}
		if ( $attachment_id ) {
			update_post_meta( $post_id, WVPB_Enquiry_Admin::META_IMAGE_ID, $attachment_id );
		}

		return $post_id;
	}

	/**
	 * Stores a short-lived snapshot of the submission for the
	 * confirmation page to display, keyed by a random token — kept
	 * separate from the permanent post record so the confirmation link
	 * can't be used to browse or guess at other customers' enquiries,
	 * and so it naturally stops working after CONFIRMATION_TTL.
	 *
	 * @param string          $name          Customer name.
	 * @param string          $email         Customer email.
	 * @param string          $phone         Customer phone.
	 * @param string          $comment       Customer comment.
	 * @param WC_Product|null $product       Product, if known.
	 * @param int             $attachment_id Uploaded image attachment ID, or 0.
	 * @return string The token to append to the confirmation URL.
	 */
	private static function store_confirmation_snapshot( $name, $email, $phone, $comment, $product, $attachment_id ) {

		$token = wp_generate_password( 32, false, false );

		set_transient(
			'wvpb_enquiry_' . $token,
			array(
				'name'         => $name,
				'email'        => $email,
				'phone'        => $phone,
				'comment'      => $comment,
				'product_name' => $product ? $product->get_name() : '',
				'image_url'    => $attachment_id ? wp_get_attachment_url( $attachment_id ) : '',
			),
			self::CONFIRMATION_TTL
		);

		return $token;
	}

	/**
	 * Builds the confirmation page URL, with the token appended if given.
	 *
	 * @param string $token Optional confirmation token.
	 * @return string
	 */
	private static function get_thankyou_url( $token = '' ) {

		$page_id = get_option( 'wvpb_thankyou_page_id' );
		$base    = ( $page_id && get_post( $page_id ) ) ? get_permalink( $page_id ) : home_url( '/' );

		return $token ? add_query_arg( 'wvpb_enquiry', $token, $base ) : $base;
	}

	/**
	 * Renders the [wvpb_enquiry_thankyou] shortcode.
	 *
	 * @return string HTML.
	 */
	public static function render_thankyou_shortcode() {

		$token = isset( $_GET['wvpb_enquiry'] ) ? sanitize_text_field( wp_unslash( $_GET['wvpb_enquiry'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data  = $token ? get_transient( 'wvpb_enquiry_' . $token ) : false;

		ob_start();
		?>
		<div class="wvpb-enquiry-thankyou">
			<?php if ( $data ) : ?>
				<h2><?php esc_html_e( 'Thank you for your enquiry!', 'webcasata-visual-product-builder' ); ?></h2>
				<p class="wvpb-enquiry-thankyou-lead">
					<?php esc_html_e( 'Our team will contact you shortly to confirm your order.', 'webcasata-visual-product-builder' ); ?>
				</p>
				<div class="wvpb-enquiry-summary">
					<p><strong><?php esc_html_e( 'Name', 'webcasata-visual-product-builder' ); ?>:</strong> <?php echo esc_html( $data['name'] ); ?></p>
					<p><strong><?php esc_html_e( 'Email', 'webcasata-visual-product-builder' ); ?>:</strong> <?php echo esc_html( $data['email'] ); ?></p>
					<?php if ( ! empty( $data['phone'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Phone', 'webcasata-visual-product-builder' ); ?>:</strong> <?php echo esc_html( $data['phone'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $data['product_name'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Product', 'webcasata-visual-product-builder' ); ?>:</strong> <?php echo esc_html( $data['product_name'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $data['comment'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Comment', 'webcasata-visual-product-builder' ); ?>:</strong><br /><?php echo nl2br( esc_html( $data['comment'] ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $data['image_url'] ) ) : ?>
						<p><img src="<?php echo esc_url( $data['image_url'] ); ?>" alt="" class="wvpb-enquiry-thankyou-image" /></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<h2><?php esc_html_e( 'Thank you!', 'webcasata-visual-product-builder' ); ?></h2>
				<p><?php esc_html_e( "If you've recently sent us a design enquiry, we've received it and our team will be in touch soon.", 'webcasata-visual-product-builder' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueues the confirmation page's small stylesheet, only on the
	 * page that actually contains the shortcode.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_thankyou_style() {

		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post || ! has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			return;
		}

		wp_enqueue_style(
			'wvpb-thankyou',
			WVPB_PLUGIN_URL . 'public/css/thankyou.css',
			array(),
			WVPB_VERSION
		);
	}

	/**
	 * Validates and sanitizes the JSON-encoded selections map the
	 * frontend sends ({stepIndex: value, ...}) into a plain array of
	 * "Step N: value" strings safe to drop into an email body.
	 *
	 * With every step now disabled the moment "My Design" is toggled
	 * on, this will normally arrive empty — kept tolerant of that
	 * rather than assuming it always has content.
	 *
	 * @param string $raw_json Raw JSON string from $_POST.
	 * @return string[] Sanitized, human-readable lines.
	 */
	private static function sanitize_selections( $raw_json ) {

		$decoded = json_decode( (string) $raw_json, true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$lines = array();

		foreach ( $decoded as $step_index => $value ) {
			if ( ! is_scalar( $step_index ) || ! is_scalar( $value ) ) {
				continue;
			}
			$lines[] = sprintf(
				/* translators: 1: step number, 2: selected value. */
				__( 'Step %1$s: %2$s', 'webcasata-visual-product-builder' ),
				absint( $step_index ) + 1,
				sanitize_text_field( (string) $value )
			);
		}

		return $lines;
	}

	/**
	 * Validates and stores the uploaded reference image as a Media
	 * Library attachment (not attached to any post).
	 *
	 * @return int|WP_Error Attachment ID on success, WP_Error otherwise.
	 */
	private static function handle_uploaded_image() {

		$file = $_FILES['design_image'];

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'wvpb_upload_error', __( 'The uploaded file could not be received.', 'webcasata-visual-product-builder' ) );
		}

		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'wvpb_upload_too_large', __( 'That image is larger than the 5MB limit.', 'webcasata-visual-product-builder' ) );
		}

		// Checked against our own explicit whitelist before WordPress's
		// own upload handling ever sees the file — defense in depth,
		// on top of (not instead of) WordPress's own mime checks.
		$filetype = wp_check_filetype( $file['name'] );
		if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], self::ALLOWED_MIME_TYPES, true ) ) {
			return new WP_Error( 'wvpb_upload_bad_type', __( 'Please upload a JPG, PNG, GIF, or WEBP image.', 'webcasata-visual-product-builder' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// media_handle_upload() re-validates the mime type itself
		// against the site's own allowed upload types, moves the file,
		// creates the attachment, and generates its metadata/thumbnails
		// in one call. Parent post ID 0: this isn't attached to any
		// product or page, it's a standalone reference image.
		$attachment_id = media_handle_upload( 'design_image', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'wvpb_upload_failed', __( 'The image could not be uploaded.', 'webcasata-visual-product-builder' ) );
		}

		return $attachment_id;
	}

	/**
	 * Emails the shop owner with the enquiry details.
	 *
	 * @param string          $name       Customer name.
	 * @param string          $email      Customer email.
	 * @param string          $phone      Customer phone (may be empty).
	 * @param string          $comment    Customer comment (may be empty).
	 * @param WC_Product|null $product    The product the enquiry was made from, if known.
	 * @param string[]        $selections Human-readable selection lines.
	 * @param int             $attachment_id Uploaded reference image attachment ID, or 0.
	 * @return void
	 */
	private static function send_notification_email( $name, $email, $phone, $comment, $product, $selections, $attachment_id ) {

		$settings = get_option( WVPB_OPTION_SETTINGS, array() );
		$to       = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: %s: site name. */
			__( 'New custom design enquiry — %s', 'webcasata-visual-product-builder' ),
			get_bloginfo( 'name' )
		);

		$lines   = array();
		$lines[] = sprintf( __( 'Name: %s', 'webcasata-visual-product-builder' ), $name );
		$lines[] = sprintf( __( 'Email: %s', 'webcasata-visual-product-builder' ), $email );
		if ( $phone ) {
			$lines[] = sprintf( __( 'Phone: %s', 'webcasata-visual-product-builder' ), $phone );
		}
		if ( $product ) {
			$lines[] = sprintf( __( 'Product: %1$s (%2$s)', 'webcasata-visual-product-builder' ), $product->get_name(), get_permalink( $product->get_id() ) );
		}
		if ( $selections ) {
			$lines[] = __( 'Selections made before switching to My Design:', 'webcasata-visual-product-builder' );
			foreach ( $selections as $line ) {
				$lines[] = '  - ' . $line;
			}
		}
		if ( $attachment_id ) {
			$lines[] = sprintf( __( 'Reference image: %s', 'webcasata-visual-product-builder' ), wp_get_attachment_url( $attachment_id ) );
		}
		if ( $comment ) {
			$lines[] = '';
			$lines[] = __( 'Comment:', 'webcasata-visual-product-builder' );
			$lines[] = $comment;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
		}

		wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
	}
}
