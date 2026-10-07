<?php
/**
 * Page footer.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="msst-foot">
	<span>
		<?php
		printf(
			/* translators: 1: version, 2: author name */
			esc_html__( 'Mudassar Snippet Studio v%1$s · Made by %2$s', 'mudassar-snippet-studio' ),
			esc_html( MSST_VERSION ),
			'<strong>' . esc_html( MSST_Brand::AUTHOR ) . '</strong>'
		);
		?>
	</span>
	<span class="msst-right">
		<?php esc_html_e( 'Need help?', 'mudassar-snippet-studio' ); ?>
		<?php echo MSST_Brand::link( 'contact', 'footer-contact-us', __( 'Contact Us', 'mudassar-snippet-studio' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> ·
		<?php echo MSST_Brand::link( 'website', 'footer-website', 'mudassar.work' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</span>
</div>
