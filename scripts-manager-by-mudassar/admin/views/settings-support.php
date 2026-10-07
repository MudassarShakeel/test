<?php
/**
 * Help & Contact tab.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

global $wp_version;
$msst_counts = MSST_Snippets::counts();
$msst_info   = sprintf(
	'Plugin %s · WordPress %s · PHP %s · Multisite: %s · Snippets: %d (%d on)',
	MSST_VERSION,
	$wp_version,
	PHP_VERSION,
	is_multisite() ? 'yes' : 'no',
	$msst_counts['total'],
	$msst_counts['active']
);

?>
<div class="msst-grid msst-grid-support">
	<div class="msst-card msst-center">
		<div class="msst-logo msst-logo-center" aria-hidden="true">&lt;/&gt;</div>
		<h2 class="msst-h-center"><?php esc_html_e( 'Scripts Manager By Mudassar', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc">
			<?php
			printf(
				/* translators: 1: version, 2: author */
				esc_html__( 'Version %1$s · Built by %2$s', 'scripts-manager-by-mudassar' ),
				esc_html( MSST_VERSION ),
				'<strong>' . esc_html( MSST_Brand::AUTHOR ) . '</strong>'
			);
			?>
		</p>
		<?php echo MSST_Brand::link( 'contact', 'support-contact-support', __( 'Contact Support', 'scripts-manager-by-mudassar' ), 'msst-btn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo MSST_Brand::link( 'website', 'support-visit-website', __( 'Visit mudassar.work', 'scripts-manager-by-mudassar' ), 'msst-btn msst-btn-outline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<p class="msst-label msst-left msst-mt"><?php esc_html_e( 'System info', 'scripts-manager-by-mudassar' ); ?></p>
		<textarea class="msst-code msst-left" rows="3" readonly onclick="this.select()"><?php echo esc_textarea( $msst_info ); ?></textarea>
	</div>
</div>
