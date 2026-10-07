<?php
/**
 * Page banner and tab navigation.
 *
 * @package ScriptsManagerByMudassar
 * @var string $tab Active tab.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="msst-hero">
	<div class="msst-logo" aria-hidden="true">&lt;/&gt;</div>
	<div>
		<p class="msst-title"><?php esc_html_e( 'Scripts Manager By Mudassar', 'scripts-manager-by-mudassar' ); ?></p>
		<p class="msst-sub"><?php esc_html_e( 'Add code to your website safely – no experience needed.', 'scripts-manager-by-mudassar' ); ?></p>
	</div>
	<div class="msst-by">
		<span>
			<?php esc_html_e( 'by', 'scripts-manager-by-mudassar' ); ?> <strong><?php echo esc_html( MSST_Brand::AUTHOR ); ?></strong> ·
			<?php echo MSST_Brand::link( 'website', 'header-website', 'mudassar.work' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper. ?>
		</span>
		<?php echo MSST_Brand::link( 'contact', 'header-contact-us', __( 'Contact Us', 'scripts-manager-by-mudassar' ), 'msst-btn msst-btn-outline msst-btn-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
<nav class="msst-tabs" aria-label="<?php esc_attr_e( 'Main sections', 'scripts-manager-by-mudassar' ); ?>">
	<?php foreach ( MSST_Admin::main_tabs() as $msst_slug => $msst_label ) : ?>
		<a href="<?php echo esc_url( MSST_Admin::url( $msst_slug ) ); ?>" class="<?php echo $msst_slug === $tab ? 'is-active' : ''; ?>"<?php echo $msst_slug === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $msst_label ); ?></a>
	<?php endforeach; ?>
</nav>
<nav class="msst-more" aria-label="<?php esc_attr_e( 'More sections', 'scripts-manager-by-mudassar' ); ?>">
	<strong><?php esc_html_e( 'More:', 'scripts-manager-by-mudassar' ); ?></strong>
	<?php foreach ( MSST_Admin::more_tabs() as $msst_slug => $msst_label ) : ?>
		<a href="<?php echo esc_url( MSST_Admin::url( $msst_slug ) ); ?>" class="<?php echo $msst_slug === $tab ? 'is-active' : ''; ?>"<?php echo $msst_slug === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $msst_label ); ?></a>
	<?php endforeach; ?>
</nav>
