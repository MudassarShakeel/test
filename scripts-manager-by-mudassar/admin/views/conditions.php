<?php
/**
 * Rules guide tab.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

MSST_Admin::intro( __( 'Rules guide', 'scripts-manager-by-mudassar' ), __( 'Rules decide on which pages a snippet shows. You build them inside “Add Snippet” → step 3 → “Only some pages”.', 'scripts-manager-by-mudassar' ) );

$msst_groups    = array(
	'page' => array( __( 'Page & content', 'scripts-manager-by-mudassar' ), __( 'Page type, post type and address (URL).', 'scripts-manager-by-mudassar' ) ),
	'user' => array( __( 'Visitor', 'scripts-manager-by-mudassar' ), __( 'Logged in, role, phone or computer, where they came from.', 'scripts-manager-by-mudassar' ) ),
	'time' => array( __( 'Date', 'scripts-manager-by-mudassar' ), __( 'Show only before or after a date.', 'scripts-manager-by-mudassar' ) ),
	'shop' => array( __( 'Shop', 'scripts-manager-by-mudassar' ), __( 'WooCommerce and Easy Digital Downloads cart rules.', 'scripts-manager-by-mudassar' ) ),
);
$msst_catalogue = MSST_Conditions::catalogue();
?>
<div class="msst-grid msst-grid-2">
	<div class="msst-card">
		<h2><?php esc_html_e( 'Examples in plain words', 'scripts-manager-by-mudassar' ); ?></h2>
		<div class="msst-note"><?php echo wp_kses( __( '“Show only on the <b>home page</b>”', 'scripts-manager-by-mudassar' ), array( 'b' => array() ) ); ?></div>
		<div class="msst-note"><?php echo wp_kses( __( '“Hide when the person is <b>logged in</b>”', 'scripts-manager-by-mudassar' ), array( 'b' => array() ) ); ?></div>
		<div class="msst-note"><?php echo wp_kses( __( '“Show only on <b>phones</b>”', 'scripts-manager-by-mudassar' ), array( 'b' => array() ) ); ?></div>
		<div class="msst-note"><?php echo wp_kses( __( '“Show only if the <b>cart is over $50</b>”', 'scripts-manager-by-mudassar' ), array( 'b' => array() ) ); ?></div>
		<a class="msst-btn" href="<?php echo esc_url( MSST_Admin::url( 'edit' ) ); ?>"><?php esc_html_e( 'Try it in Add Snippet', 'scripts-manager-by-mudassar' ); ?></a>
	</div>
	<div class="msst-card">
		<h2><?php esc_html_e( 'How it works', 'scripts-manager-by-mudassar' ); ?></h2>
		<p class="msst-desc"><?php esc_html_e( 'Each rule reads like a sentence: “Show when Page type is Home page”.', 'scripts-manager-by-mudassar' ); ?></p>
		<ul class="msst-list">
			<li><?php echo wp_kses( __( '<b>AND</b> – add another rule in the same group. Both must be true.', 'scripts-manager-by-mudassar' ), array( 'b' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( '<b>OR</b> – add a new group. Either group is enough.', 'scripts-manager-by-mudassar' ), array( 'b' => array() ) ); ?></li>
			<li><?php esc_html_e( 'No rules? The snippet shows on every page.', 'scripts-manager-by-mudassar' ); ?></li>
		</ul>
	</div>
</div>
<div class="msst-grid msst-grid-4">
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
