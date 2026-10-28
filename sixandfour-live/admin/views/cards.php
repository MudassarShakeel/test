<?php
/**
 * Score cards screen: sample pictures drawn by the same code that draws real cards.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$gd_ok = function_exists( 'imagettftext' );
?>
<p class="sf-lede">These are the pictures the plugin draws. Teams, players and scores here are made up.</p>
<?php if ( ! $gd_ok ) : ?>
	<div class="sf-notice sf-error">Your server's PHP does not have GD with FreeType, so cards cannot be drawn. Ask Hostinger to enable the GD extension, or switch PHP version in hPanel.</div>
<?php else : ?>
	<div class="sf-cards">
		<?php foreach ( SF_Samples::types() as $type => $label ) : ?>
			<?php $src = wp_nonce_url( admin_url( 'admin-post.php?action=sf_sample&type=' . $type ), 'sf_sample' ); ?>
			<figure class="sf-card-fig">
				<img loading="lazy" src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $label . ' sample card' ); ?>">
				<figcaption><?php echo esc_html( $label ); ?></figcaption>
			</figure>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<p class="sf-sub">Picture sizes: 1200 × 630 for scores, 1080 × 1080 for milestones and polls, 1080 × 1350 for summaries, tables and the scoreboard. Flags are drawn for national teams. Upload your own team pictures under Teams.</p>
