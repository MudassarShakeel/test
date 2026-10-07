<?php
/**
 * Conditional logic reference tab.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

$msst_groups    = array(
	'page' => array( __( 'Page & content', 'mudassar-snippet-studio' ), __( 'Page type, post type and URL path.', 'mudassar-snippet-studio' ) ),
	'user' => array( __( 'User & visitor', 'mudassar-snippet-studio' ), __( 'Logged in, role, device, referrer and cookie.', 'mudassar-snippet-studio' ) ),
	'time' => array( __( 'Date & time', 'mudassar-snippet-studio' ), __( 'Show only before or after a date.', 'mudassar-snippet-studio' ) ),
	'shop' => array( __( 'E-commerce', 'mudassar-snippet-studio' ), __( 'WooCommerce and Easy Digital Downloads cart rules.', 'mudassar-snippet-studio' ) ),
);
$msst_catalogue = MSST_Conditions::catalogue();
?>
<div class="msst-card">
	<h2><?php esc_html_e( 'How conditional logic works', 'mudassar-snippet-studio' ); ?></h2>
	<p class="msst-desc"><?php esc_html_e( 'Build rules inside each snippet. Rules in one group are combined with AND. Several groups are combined with OR. A snippet with no rules always runs.', 'mudassar-snippet-studio' ); ?></p>
	<a class="msst-btn" href="<?php echo esc_url( MSST_Admin::url( 'edit' ) ); ?>"><?php esc_html_e( 'Add a snippet with rules', 'mudassar-snippet-studio' ); ?></a>
</div>
<div class="msst-grid msst-grid-2">
	<?php foreach ( $msst_groups as $msst_key => $msst_info ) : ?>
		<div class="msst-card">
			<h2><?php echo esc_html( $msst_info[0] ); ?></h2>
			<p class="msst-desc"><?php echo esc_html( $msst_info[1] ); ?></p>
			<ul class="msst-list">
				<?php foreach ( $msst_catalogue as $msst_def ) : ?>
					<?php if ( $msst_def['group'] === $msst_key ) : ?>
						<li><?php echo esc_html( $msst_def['label'] ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endforeach; ?>
</div>
<div class="msst-note"><?php esc_html_e( 'PHP snippets that use page-type or post-type rules start after WordPress has parsed the request, so they run slightly later than other PHP snippets.', 'mudassar-snippet-studio' ); ?></div>
