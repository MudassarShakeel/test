<?php
/**
 * Page banner and tab navigation.
 *
 * @package MudassarSnippetStudio
 * @var string $tab Active tab.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="msst-hero">
	<div class="msst-logo" aria-hidden="true">&lt;/&gt;</div>
	<div>
		<p class="msst-title"><?php esc_html_e( 'Mudassar Snippet Studio', 'mudassar-snippet-studio' ); ?></p>
		<p class="msst-sub"><?php esc_html_e( 'Headers, footers and code snippets – safely managed.', 'mudassar-snippet-studio' ); ?></p>
	</div>
	<div class="msst-by">
		<span>
			<?php esc_html_e( 'by', 'mudassar-snippet-studio' ); ?> <strong><?php echo esc_html( MSST_Brand::AUTHOR ); ?></strong> ·
			<?php echo MSST_Brand::link( 'website', 'header-website', 'mudassar.work' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper. ?>
		</span>
		<?php echo MSST_Brand::link( 'contact', 'header-contact-us', __( 'Contact Us', 'mudassar-snippet-studio' ), 'msst-btn msst-btn-outline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
<nav class="msst-tabs" aria-label="<?php esc_attr_e( 'Snippet Studio sections', 'mudassar-snippet-studio' ); ?>">
	<?php foreach ( MSST_Admin::tabs() as $msst_slug => $msst_label ) : ?>
		<a href="<?php echo esc_url( MSST_Admin::url( $msst_slug ) ); ?>" class="<?php echo $msst_slug === $tab ? 'is-active' : ''; ?>"<?php echo $msst_slug === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $msst_label ); ?></a>
	<?php endforeach; ?>
</nav>
