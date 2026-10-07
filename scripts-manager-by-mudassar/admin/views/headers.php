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

MSST_Admin::intro( __( 'Code for your whole website', 'scripts-manager-by-mudassar' ), __( 'Use this for tracking tools (Google Analytics, Facebook Pixel). Paste an ID, or paste code into the boxes below.', 'scripts-manager-by-mudassar' ) );
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msst-grid msst-grid-main">
	<input type="hidden" name="action" value="msst_save_headers">
	<?php wp_nonce_field( 'msst_save_headers' ); ?>
	<div>
		<div class="msst-card">
			<h2><span class="msst-step">1</span> <?php esc_html_e( 'Easiest way: paste an ID', 'scripts-manager-by-mudassar' ); ?></h2>
			<p class="msst-desc"><?php esc_html_e( 'We make safe code for you. Leave blank what you do not use.', 'scripts-manager-by-mudassar' ); ?></p>
			<div class="msst-grid msst-grid-2">
				<?php
				$msst_fields = array(
					'ga4'    => array( __( 'Google Analytics ID', 'scripts-manager-by-mudassar' ), 'G-XXXXXXXXXX', __( 'Looks like G-ABC123XYZ', 'scripts-manager-by-mudassar' ) ),
					'gtm'    => array( __( 'Google Tag Manager ID', 'scripts-manager-by-mudassar' ), 'GTM-XXXXXXX', __( 'Looks like GTM-ABC123', 'scripts-manager-by-mudassar' ) ),
					'meta'   => array( __( 'Facebook (Meta) Pixel ID', 'scripts-manager-by-mudassar' ), '1234567890', __( 'Numbers only', 'scripts-manager-by-mudassar' ) ),
					'tiktok' => array( __( 'TikTok Pixel ID', 'scripts-manager-by-mudassar' ), 'ABCDEF1234', __( 'Letters and numbers', 'scripts-manager-by-mudassar' ) ),
				);
				foreach ( $msst_fields as $msst_kind => $msst_info ) :
					?>
					<div>
						<label class="msst-label" for="msst_<?php echo esc_attr( $msst_kind ); ?>"><?php echo esc_html( $msst_info[0] ); ?></label>
						<input class="msst-input" type="text" id="msst_<?php echo esc_attr( $msst_kind ); ?>" name="msst_<?php echo esc_attr( $msst_kind ); ?>" value="<?php echo esc_attr( $msst_global[ $msst_kind ] ); ?>" placeholder="<?php echo esc_attr( $msst_info[1] ); ?>" maxlength="40" autocomplete="off"<?php echo esc_attr( $msst_ro ); ?>>
						<div class="msst-hint"><?php echo esc_html( $msst_info[2] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="msst-card">
			<h2><span class="msst-step">2</span> <?php esc_html_e( 'Or paste your own code', 'scripts-manager-by-mudassar' ); ?></h2>
			<p class="msst-desc"><?php esc_html_e( 'Paste exactly what the service gave you.', 'scripts-manager-by-mudassar' ); ?></p>
			<?php
			$msst_boxes = array(
				'header' => __( 'Header box', 'scripts-manager-by-mudassar' ),
				'body'   => __( 'Body box (optional)', 'scripts-manager-by-mudassar' ),
				'footer' => __( 'Footer box', 'scripts-manager-by-mudassar' ),
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
	</div>
	<div class="msst-side">
		<div class="msst-card">
			<h2><?php esc_html_e( 'Where does it go?', 'scripts-manager-by-mudassar' ); ?></h2>
			<p class="msst-desc"><?php esc_html_e( 'The three boxes match parts of your page:', 'scripts-manager-by-mudassar' ); ?></p>
			<div class="msst-wire">
				<div class="msst-hl"><?php esc_html_e( 'Header – very top (most tracking codes)', 'scripts-manager-by-mudassar' ); ?></div>
				<div class="msst-hl msst-soft"><?php esc_html_e( 'Body – right after the page opens', 'scripts-manager-by-mudassar' ); ?></div>
				<div><?php esc_html_e( 'Your page content…', 'scripts-manager-by-mudassar' ); ?></div>
				<div class="msst-hl"><?php esc_html_e( 'Footer – very bottom (chat widgets)', 'scripts-manager-by-mudassar' ); ?></div>
			</div>
		</div>
		<div class="msst-card">
			<h2><?php esc_html_e( 'Tip', 'scripts-manager-by-mudassar' ); ?></h2>
			<p class="msst-desc msst-m0"><?php esc_html_e( 'After saving, open your site in a private window and refresh. Then check your tool (like Google Analytics “Realtime”).', 'scripts-manager-by-mudassar' ); ?></p>
		</div>
	</div>
</form>
