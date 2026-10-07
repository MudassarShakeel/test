<?php
/**
 * Support tab.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

global $wp_version;
$msst_counts = MSST_Snippets::counts();
$msst_info   = sprintf(
	"Plugin: Mudassar Snippet Studio %s\nWordPress: %s\nPHP: %s\nMultisite: %s\nSnippets: %d (%d active)",
	MSST_VERSION,
	$wp_version,
	PHP_VERSION,
	is_multisite() ? 'yes' : 'no',
	$msst_counts['total'],
	$msst_counts['active']
);
?>
<div class="msst-grid msst-grid-2">
	<div class="msst-card msst-center">
		<div class="msst-logo msst-logo-center" aria-hidden="true">&lt;/&gt;</div>
		<h2><?php esc_html_e( 'Mudassar Snippet Studio', 'mudassar-snippet-studio' ); ?></h2>
		<p class="msst-desc">
			<?php
			printf(
				/* translators: 1: version, 2: author */
				esc_html__( 'Version %1$s · Built by %2$s', 'mudassar-snippet-studio' ),
				esc_html( MSST_VERSION ),
				'<strong>' . esc_html( MSST_Brand::AUTHOR ) . '</strong>'
			);
			?>
		</p>
		<?php echo MSST_Brand::link( 'contact', 'support-contact-support', __( 'Contact Support', 'mudassar-snippet-studio' ), 'msst-btn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo MSST_Brand::link( 'website', 'support-visit-website', __( 'Visit mudassar.work', 'mudassar-snippet-studio' ), 'msst-btn msst-btn-outline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'System info for support', 'mudassar-snippet-studio' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Copy this and paste it into your message. Nothing is sent automatically.', 'mudassar-snippet-studio' ); ?></p>
		<textarea class="msst-code" rows="7" readonly onclick="this.select()"><?php echo esc_textarea( $msst_info ); ?></textarea>
	</div>
</div>
