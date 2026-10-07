<?php
/**
 * Slim branding bar shown on every screen.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="msst-hero">
	<div class="msst-logo" aria-hidden="true">&lt;/&gt;</div>
	<div>
		<p class="msst-title"><?php esc_html_e( 'Scripts Manager By Mudassar', 'scripts-manager-by-mudassar' ); ?></p>
	</div>
	<div class="msst-by">
		<span>
			<?php esc_html_e( 'by', 'scripts-manager-by-mudassar' ); ?> <strong><?php echo esc_html( MSST_Brand::AUTHOR ); ?></strong> ·
			<?php echo MSST_Brand::link( 'website', 'header-website', 'mudassar.work' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper. ?>
		</span>
		<?php echo MSST_Brand::link( 'contact', 'header-contact-us', __( 'Contact Us', 'scripts-manager-by-mudassar' ), 'msst-btn msst-btn-outline msst-btn-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
