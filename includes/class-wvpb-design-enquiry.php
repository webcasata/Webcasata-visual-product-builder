<?php
/**
 * Handles "My Design" enquiry submissions from the frontend modal.
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
		// all real processing, so the bot gets no signal to adapt to.
		if ( ! empty( $_POST['wvpb_hp'] ) ) {
			wp_send_json_success( array( 'message' => self::success_message() ) );
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

		$attachment_id  = 0;
		$upload_error   = '';
		if ( ! empty( $_FILES['design_image'] ) && ! empty( $_FILES['design_image']['name'] ) ) {
			$upload_result = self::handle_uploaded_image();
			if ( is_wp_error( $upload_result ) ) {
				$upload_error = $upload_result->get_error_message();
			} else {
				$attachment_id = $upload_result;
			}
		}

		self::send_notification_email( $name, $email, $phone, $comment, $product, $selections, $attachment_id );

		$response = array( 'message' => self::success_message() );
		if ( $upload_error ) {
			// The enquiry itself still went through — the image just
			// didn't attach — so this is a success response with a
			// note, not a hard failure.
			$response['upload_warning'] = $upload_error;
		}

		wp_send_json_success( $response );
	}

	/**
	 * Validates and sanitizes the JSON-encoded selections map the
	 * frontend sends ({stepIndex: value, ...}) into a plain array of
	 * "Step N: value" strings safe to drop into an email body.
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

	/**
	 * The message shown to the customer on success.
	 *
	 * @return string
	 */
	private static function success_message() {
		return __( "Thanks! We've received your design and will be in touch shortly.", 'webcasata-visual-product-builder' );
	}
}
