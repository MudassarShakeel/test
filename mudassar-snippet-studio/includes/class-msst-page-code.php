<?php
/**
 * Per-post header/footer code (meta box).
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page-specific snippets.
 */
class MSST_Page_Code {

	const NONCE_ACTION = 'msst_page_code';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_header' ), 100 );
		add_action( 'wp_footer', array( __CLASS__, 'print_footer' ), 100 );
	}

	/**
	 * Register the box for users allowed to write raw code.
	 */
	public static function add_box() {
		if ( ! MSST_Security::can_edit_raw() ) {
			return;
		}
		foreach ( get_post_types( array( 'public' => true ) ) as $type ) {
			add_meta_box( 'msst_page_code', __( 'Snippet Studio – page code', 'mudassar-snippet-studio' ), array( __CLASS__, 'render_box' ), $type, 'normal', 'low' );
		}
	}

	/**
	 * Render the box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, 'msst_page_nonce' );
		echo '<p><label for="msst_page_header"><strong>' . esc_html__( 'Header code (this page only)', 'mudassar-snippet-studio' ) . '</strong></label></p>';
		echo '<textarea id="msst_page_header" name="msst_page_header" rows="4" class="large-text code">' . esc_textarea( (string) get_post_meta( $post->ID, '_msst_page_header', true ) ) . '</textarea>';
		echo '<p><label for="msst_page_footer"><strong>' . esc_html__( 'Footer code (this page only)', 'mudassar-snippet-studio' ) . '</strong></label></p>';
		echo '<textarea id="msst_page_footer" name="msst_page_footer" rows="4" class="large-text code">' . esc_textarea( (string) get_post_meta( $post->ID, '_msst_page_footer', true ) ) . '</textarea>';
	}

	/**
	 * Save with nonce, capability and signature.
	 *
	 * @param int     $post_id Post ID.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST['msst_page_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['msst_page_nonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! MSST_Security::can_edit_raw() ) {
			return;
		}
		$header = isset( $_POST['msst_page_header'] ) ? wp_unslash( $_POST['msst_page_header'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw code, restricted to users with unfiltered_html.
		$footer = isset( $_POST['msst_page_footer'] ) ? wp_unslash( $_POST['msst_page_footer'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$header = is_string( $header ) ? substr( $header, 0, 100000 ) : '';
		$footer = is_string( $footer ) ? substr( $footer, 0, 100000 ) : '';
		update_post_meta( $post_id, '_msst_page_header', wp_slash( $header ) );
		update_post_meta( $post_id, '_msst_page_footer', wp_slash( $footer ) );
		update_post_meta( $post_id, '_msst_page_sig', MSST_Security::sign( 'page|' . $post_id . '|' . $header . '|' . $footer ) );
		MSST_Logger::audit( sprintf( 'Saved page code for post #%d', $post_id ) );
	}

	/**
	 * Header output.
	 */
	public static function print_header() {
		self::output( '_msst_page_header' );
	}

	/**
	 * Footer output.
	 */
	public static function print_footer() {
		self::output( '_msst_page_footer' );
	}

	/**
	 * Print verified code for the current singular post.
	 *
	 * @param string $key Meta key.
	 */
	private static function output( $key ) {
		if ( ! is_singular() || MSST_Security::safe_mode() || MSST_Security::test_mode() ) {
			return;
		}
		$id     = get_queried_object_id();
		$header = (string) get_post_meta( $id, '_msst_page_header', true );
		$footer = (string) get_post_meta( $id, '_msst_page_footer', true );
		$sig    = (string) get_post_meta( $id, '_msst_page_sig', true );
		if ( ! MSST_Security::verify( 'page|' . $id . '|' . $header . '|' . $footer, $sig ) ) {
			return;
		}
		$code = '_msst_page_header' === $key ? $header : $footer;
		if ( '' !== $code ) {
			echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional raw output of signed, authorised code.
		}
	}
}
