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

MSST_Admin::intro( __( 'Help & contact', 'scripts-manager-by-mudassar' ), __( 'Stuck? Read the quick answers, or tell us what happened.', 'scripts-manager-by-mudassar' ) );
?>
<div class="msst-grid msst-grid-2">
	<div class="msst-card">
		<h2><?php esc_html_e( 'Quick answers', 'scripts-manager-by-mudassar' ); ?></h2>
		<details open class="msst-faq"><summary><?php esc_html_e( 'My site looks broken. What now?', 'scripts-manager-by-mudassar' ); ?></summary><p><?php esc_html_e( 'Open your Safe Mode link (Settings & Safety). Your site loads with every snippet OFF. Then turn OFF the last snippet you added.', 'scripts-manager-by-mudassar' ); ?></p></details>
		<details class="msst-faq"><summary><?php esc_html_e( 'I do not see my snippet on the site', 'scripts-manager-by-mudassar' ); ?></summary><p><?php esc_html_e( 'Check that it is ON, that “On which pages” fits the page you are looking at, and refresh in a private window. If you use a caching plugin, clear its cache.', 'scripts-manager-by-mudassar' ); ?></p></details>
		<details class="msst-faq"><summary><?php esc_html_e( 'Which kind of code should I pick?', 'scripts-manager-by-mudassar' ); ?></summary><p><?php esc_html_e( 'HTML for boxes and banners, CSS for looks, JavaScript for trackers and widgets, PHP only if a guide tells you to.', 'scripts-manager-by-mudassar' ); ?></p></details>
		<details class="msst-faq"><summary><?php esc_html_e( 'Where do I find my Analytics ID?', 'scripts-manager-by-mudassar' ); ?></summary><p><?php esc_html_e( 'In Google Analytics: Admin → Data streams → your website. The ID starts with G-.', 'scripts-manager-by-mudassar' ); ?></p></details>
		<details class="msst-faq"><summary><?php esc_html_e( 'Where is the error log file?', 'scripts-manager-by-mudassar' ); ?></summary><p><?php esc_html_e( 'Open Problems & Activity. You can read the last lines there and download the whole file.', 'scripts-manager-by-mudassar' ); ?></p></details>
	</div>
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
		<p class="msst-desc msst-left msst-mt"><?php esc_html_e( 'Copy this into your message so we can help faster:', 'scripts-manager-by-mudassar' ); ?></p>
		<textarea class="msst-code msst-left" rows="3" readonly onclick="this.select()"><?php echo esc_textarea( $msst_info ); ?></textarea>
	</div>
</div>
