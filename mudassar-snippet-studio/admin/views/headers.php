<?php
/**
 * Headers & Footers tab.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

$msst_global   = MSST_Settings::get_global();
$msst_can_edit = MSST_Security::can_edit_raw();
$msst_ro       = $msst_can_edit ? '' : ' readonly';
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msst-grid msst-grid-main">
	<input type="hidden" name="action" value="msst_save_headers">
	<?php wp_nonce_field( 'msst_save_headers' ); ?>
	<div>
		<?php
		$msst_boxes = array(
			'header' => array( __( 'Header', 'mudassar-snippet-studio' ), __( 'Printed in the page <head>. Perfect for analytics, meta tags and pixels.', 'mudassar-snippet-studio' ) ),
			'body'   => array( __( 'Body', 'mudassar-snippet-studio' ), __( 'Printed right after the opening <body> tag.', 'mudassar-snippet-studio' ) ),
			'footer' => array( __( 'Footer', 'mudassar-snippet-studio' ), __( 'Printed before the closing </body> tag.', 'mudassar-snippet-studio' ) ),
		);
		foreach ( $msst_boxes as $msst_slot => $msst_info ) :
			?>
			<div class="msst-card">
				<h2><?php echo esc_html( $msst_info[0] ); ?></h2>
				<p class="msst-desc"><?php echo esc_html( $msst_info[1] ); ?></p>
				<textarea class="msst-code" name="msst_<?php echo esc_attr( $msst_slot ); ?>" rows="6" spellcheck="false"<?php echo esc_attr( $msst_ro ); ?>><?php echo esc_textarea( $msst_global[ $msst_slot ] ); ?></textarea>
			</div>
		<?php endforeach; ?>
		<?php if ( $msst_can_edit ) : ?>
			<button type="submit" class="msst-btn"><?php esc_html_e( 'Save Changes', 'mudassar-snippet-studio' ); ?></button>
		<?php else : ?>
			<div class="msst-note msst-note-warning"><?php esc_html_e( 'Your account cannot save raw code (unfiltered_html is required).', 'mudassar-snippet-studio' ); ?></div>
		<?php endif; ?>
	</div>
	<div>
		<div class="msst-card">
			<h2><?php esc_html_e( 'Quick integrations', 'mudassar-snippet-studio' ); ?></h2>
			<p class="msst-desc"><?php esc_html_e( 'Paste an ID. Safe code is generated for you.', 'mudassar-snippet-studio' ); ?></p>
			<?php
			$msst_fields = array(
				'ga4'    => array( __( 'Google Analytics (GA4)', 'mudassar-snippet-studio' ), 'G-XXXXXXXXXX' ),
				'gtm'    => array( __( 'Google Tag Manager', 'mudassar-snippet-studio' ), 'GTM-XXXXXXX' ),
				'meta'   => array( __( 'Meta (Facebook) Pixel', 'mudassar-snippet-studio' ), '1234567890' ),
				'tiktok' => array( __( 'TikTok Pixel', 'mudassar-snippet-studio' ), 'ABCDEF1234' ),
			);
			foreach ( $msst_fields as $msst_kind => $msst_info ) :
				?>
				<p>
					<label class="msst-label" for="msst_<?php echo esc_attr( $msst_kind ); ?>"><?php echo esc_html( $msst_info[0] ); ?></label>
					<input class="msst-input" type="text" id="msst_<?php echo esc_attr( $msst_kind ); ?>" name="msst_<?php echo esc_attr( $msst_kind ); ?>" value="<?php echo esc_attr( $msst_global[ $msst_kind ] ); ?>" placeholder="<?php echo esc_attr( $msst_info[1] ); ?>" maxlength="40" autocomplete="off"<?php echo esc_attr( $msst_ro ); ?>>
				</p>
			<?php endforeach; ?>
		</div>
		<div class="msst-card">
			<h2><?php esc_html_e( 'Who can edit?', 'mudassar-snippet-studio' ); ?></h2>
			<div class="msst-note"><?php esc_html_e( 'Only users with the Snippet Studio capability and unfiltered_html. By default: Administrators.', 'mudassar-snippet-studio' ); ?></div>
		</div>
	</div>
</form>
