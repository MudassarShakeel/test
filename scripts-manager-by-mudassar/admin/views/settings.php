<?php
/**
 * Settings tab: sections for general settings, history, import/export, problems and help.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

$msst_section = MSST_Admin::current_section();
?>
<nav class="msst-subnav" aria-label="<?php esc_attr_e( 'Settings sections', 'scripts-manager-by-mudassar' ); ?>">
	<?php foreach ( MSST_Admin::sections() as $msst_slug => $msst_label ) : ?>
		<a href="<?php echo esc_url( MSST_Admin::url( 'settings', array( 'section' => $msst_slug ) ) ); ?>" class="<?php echo $msst_slug === $msst_section ? 'is-active' : ''; ?>"<?php echo $msst_slug === $msst_section ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $msst_label ); ?></a>
	<?php endforeach; ?>
</nav>
<?php
require MSST_DIR . 'admin/views/settings-' . $msst_section . '.php';
