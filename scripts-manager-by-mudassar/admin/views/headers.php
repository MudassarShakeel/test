<?php
/**
 * Headers & Footers tab.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

$msst_global   = MSST_Settings::get_global();
$msst_can_edit = MSST_Security::can_edit_raw();
$msst_ro       = $msst_can_edit ? '' : ' readonly';
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="msst_save_headers">
	<?php wp_nonce_field( 'msst_save_headers' ); ?>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Tracking IDs', 'scripts-manager-by-mudassar' ); ?></h2>
		<div class="msst-grid msst-grid-2">
			<?php
			$msst_fields = array(
				'ga4'    => array( __( 'Google Analytics ID', 'scripts-manager-by-mudassar' ), 'G-XXXXXXXXXX' ),
				'gtm'    => array( __( 'Google Tag Manager ID', 'scripts-manager-by-mudassar' ), 'GTM-XXXXXXX' ),
				'meta'   => array( __( 'Facebook (Meta) Pixel ID', 'scripts-manager-by-mudassar' ), '1234567890' ),
				'tiktok' => array( __( 'TikTok Pixel ID', 'scripts-manager-by-mudassar' ), 'ABCDEF1234' ),
			);
			foreach ( $msst_fields as $msst_kind => $msst_info ) :
				?>
				<div>
					<label class="msst-label" for="msst_<?php echo esc_attr( $msst_kind ); ?>"><?php echo esc_html( $msst_info[0] ); ?></label>
					<input class="msst-input" type="text" id="msst_<?php echo esc_attr( $msst_kind ); ?>" name="msst_<?php echo esc_attr( $msst_kind ); ?>" value="<?php echo esc_attr( $msst_global[ $msst_kind ] ); ?>" placeholder="<?php echo esc_attr( $msst_info[1] ); ?>" maxlength="40" autocomplete="off"<?php echo esc_attr( $msst_ro ); ?>>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'Custom code', 'scripts-manager-by-mudassar' ); ?></h2>
		<?php
		$msst_boxes = array(
			'header' => __( 'Header', 'scripts-manager-by-mudassar' ),
			'body'   => __( 'Body', 'scripts-manager-by-mudassar' ),
			'footer' => __( 'Footer', 'scripts-manager-by-mudassar' ),
		);
		foreach ( $msst_boxes as $msst_slot => $msst_label ) :
			?>
			<p>
				<label class="msst-label" for="msst_<?php echo esc_attr( $msst_slot ); ?>"><?php echo esc_html( $msst_label ); ?></label>
				<textarea class="msst-code" id="msst_<?php echo esc_attr( $msst_slot ); ?>" name="msst_<?php echo esc_attr( $msst_slot ); ?>" rows="5" spellcheck="false"<?php echo esc_attr( $msst_ro ); ?>><?php echo esc_textarea( $msst_global[ $msst_slot ] ); ?></textarea>
			</p>
		<?php endforeach; ?>
	</div>
	<?php if ( $msst_can_edit ) : ?>
		<button type="submit" class="msst-btn msst-btn-big"><?php esc_html_e( 'Save changes', 'scripts-manager-by-mudassar' ); ?></button>
	<?php else : ?>
		<div class="msst-note msst-note-warning"><?php esc_html_e( 'Your account is not allowed to save raw code.', 'scripts-manager-by-mudassar' ); ?></div>
	<?php endif; ?>
</form>
