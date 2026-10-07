<?php
/**
 * Add / Edit snippet tab (4 easy steps).
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

$msst_snippet = MSST_Admin::current_snippet();
$msst_new     = ! $msst_snippet;
if ( $msst_new ) {
	$msst_snippet = array(
		'id'         => 0,
		'title'      => '',
		'type'       => 'html',
		'code'       => '',
		'location'   => '',
		'param'      => 1,
		'priority'   => 10,
		'active'     => false,
		'conditions' => array(),
		'start'      => 0,
		'end'        => 0,
		'sig'        => '',
		'error'      => '',
	);
}
$msst_kinds     = MSST_Snippets::kinds();
$msst_locations = MSST_Snippets::locations( $msst_snippet['type'] );
$msst_php_ok    = MSST_Security::can_edit_php();
$msst_lint      = 'php' === $msst_snippet['type'] && '' !== $msst_snippet['code'] ? MSST_Snippets::lint_php( $msst_snippet['code'] ) : null;
$msst_locked    = ! $msst_new && 'php' === $msst_snippet['type'] && ! $msst_php_ok;
$msst_some      = ! empty( $msst_snippet['conditions'] );
$msst_fmt       = static function ( $ts ) {
	return $ts ? wp_date( 'Y-m-d\TH:i', $ts ) : '';
};

MSST_Admin::intro( $msst_new ? __( 'Add a snippet in 4 easy steps', 'scripts-manager-by-mudassar' ) : __( 'Edit your snippet', 'scripts-manager-by-mudassar' ), __( 'Go from top to bottom. The sentence on the right always tells you, in plain words, what will happen.', 'scripts-manager-by-mudassar' ) );
?>
<?php if ( ! $msst_new && ! MSST_Snippets::is_intact( $msst_snippet ) ) : ?>
	<div class="msst-note msst-note-error"><?php esc_html_e( 'Safety warning: this snippet was changed outside the plugin, so it is blocked. Read the code below. If it looks right, click Save to approve it again.', 'scripts-manager-by-mudassar' ); ?></div>
<?php endif; ?>
<?php if ( '' !== $msst_snippet['error'] ) : ?>
	<div class="msst-note msst-note-error"><strong><?php esc_html_e( 'This snippet was turned off because of a problem:', 'scripts-manager-by-mudassar' ); ?></strong> <?php echo esc_html( $msst_snippet['error'] ); ?></div>
<?php endif; ?>
<?php if ( ! $msst_php_ok ) : ?>
	<div class="msst-note msst-note-warning"><?php esc_html_e( 'PHP is locked on this site for your account. You can still use HTML, CSS, JavaScript and Text.', 'scripts-manager-by-mudassar' ); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msst-grid msst-grid-main" id="msst-edit-form" data-active="<?php echo $msst_snippet['active'] ? '1' : '0'; ?>">
	<input type="hidden" name="action" value="msst_save_snippet">
	<input type="hidden" name="msst_id" value="<?php echo esc_attr( (string) $msst_snippet['id'] ); ?>">
	<?php wp_nonce_field( 'msst_save_snippet' ); ?>
	<div>
		<div class="msst-card">
			<h2><span class="msst-step">1</span> <?php esc_html_e( 'Give it a name', 'scripts-manager-by-mudassar' ); ?></h2>
			<p class="msst-desc"><?php esc_html_e( 'Only you will see this. Example: “Facebook Pixel”.', 'scripts-manager-by-mudassar' ); ?></p>
			<input class="msst-input" type="text" id="msst_title" name="msst_title" value="<?php echo esc_attr( $msst_snippet['title'] ); ?>" maxlength="200" required placeholder="<?php esc_attr_e( 'My new snippet', 'scripts-manager-by-mudassar' ); ?>">
		</div>

		<div class="msst-card">
			<h2><span class="msst-step">2</span> <?php esc_html_e( 'What kind of code is it?', 'scripts-manager-by-mudassar' ); ?></h2>
			<p class="msst-desc"><?php echo wp_kses( __( 'Not sure? Choose <b>HTML</b>. It is the safest.', 'scripts-manager-by-mudassar' ), array( 'b' => array() ) ); ?></p>
			<div class="msst-kinds" role="radiogroup" aria-label="<?php esc_attr_e( 'Kind of code', 'scripts-manager-by-mudassar' ); ?>">
				<?php foreach ( $msst_kinds as $msst_slug => $msst_kind ) : ?>
					<label class="msst-kind">
						<input type="radio" name="msst_type" value="<?php echo esc_attr( $msst_slug ); ?>" <?php checked( $msst_snippet['type'], $msst_slug ); ?> <?php disabled( 'php' === $msst_slug && ! $msst_php_ok ); ?>>
						<span class="msst-kind-box">
							<b><?php echo esc_html( $msst_kind[0] ); ?></b>
							<span><?php echo esc_html( $msst_kind[1] ); ?></span>
							<?php if ( 'easy' === $msst_kind[2] ) : ?>
								<em class="msst-badge-ok"><?php esc_html_e( 'Easiest', 'scripts-manager-by-mudassar' ); ?></em>
							<?php elseif ( 'advanced' === $msst_kind[2] ) : ?>
								<em class="msst-badge-warn"><?php esc_html_e( 'Advanced', 'scripts-manager-by-mudassar' ); ?></em>
							<?php endif; ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<?php if ( ! $msst_locked ) : ?>
				<p class="msst-inline">
					<label class="msst-label" for="msst-example"><?php esc_html_e( 'Start from an example:', 'scripts-manager-by-mudassar' ); ?></label>
					<select class="msst-input msst-w-280" id="msst-example"><option value=""><?php esc_html_e( 'Choose an example…', 'scripts-manager-by-mudassar' ); ?></option></select>
				</p>
			<?php endif; ?>
			<textarea class="msst-code" id="msst_code" name="msst_code" rows="12" spellcheck="false" aria-label="<?php esc_attr_e( 'Your code', 'scripts-manager-by-mudassar' ); ?>" <?php echo $msst_locked ? 'readonly' : ''; ?>><?php echo esc_textarea( $msst_snippet['code'] ); ?></textarea>
			<?php if ( $msst_lint && $msst_lint['ok'] ) : ?>
				<div class="msst-note msst-note-success"><?php esc_html_e( '✔ Code looks good – no mistakes found.', 'scripts-manager-by-mudassar' ); ?></div>
				<?php foreach ( $msst_lint['warnings'] as $msst_warning ) : ?>
					<div class="msst-note msst-note-warning"><?php echo esc_html( $msst_warning ); ?></div>
				<?php endforeach; ?>
			<?php elseif ( $msst_lint ) : ?>
				<div class="msst-note msst-note-error"><strong><?php esc_html_e( 'There is a mistake in your PHP:', 'scripts-manager-by-mudassar' ); ?></strong> <?php echo esc_html( $msst_lint['error'] ); ?></div>
			<?php else : ?>
				<p class="msst-hint"><?php esc_html_e( 'PHP code is checked for mistakes when you save. Do not type <?php at the start.', 'scripts-manager-by-mudassar' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="msst-card">
			<h2><span class="msst-step">3</span> <?php esc_html_e( 'Where and when should it show?', 'scripts-manager-by-mudassar' ); ?></h2>
			<p>
				<label class="msst-label" for="msst_location"><?php esc_html_e( 'Where on the page?', 'scripts-manager-by-mudassar' ); ?></label>
				<select class="msst-input" id="msst_location" name="msst_location" data-current="<?php echo esc_attr( $msst_snippet['location'] ); ?>">
					<?php foreach ( $msst_locations as $msst_slug => $msst_label ) : ?>
						<option value="<?php echo esc_attr( $msst_slug ); ?>" <?php selected( $msst_snippet['location'], $msst_slug ); ?>><?php echo esc_html( $msst_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="msst-hint" id="msst-loc-hint"></span>
			</p>
			<p id="msst-param-wrap" hidden>
				<label class="msst-label" for="msst_param"><?php esc_html_e( 'Which paragraph number?', 'scripts-manager-by-mudassar' ); ?></label>
				<input class="msst-input msst-w-140" type="number" min="1" max="50" id="msst_param" name="msst_param" value="<?php echo esc_attr( (string) $msst_snippet['param'] ); ?>">
			</p>
			<p class="msst-label"><?php esc_html_e( 'On which pages?', 'scripts-manager-by-mudassar' ); ?></p>
			<div class="msst-seg" role="radiogroup">
				<label><input type="radio" name="msst_pages" value="every" <?php checked( ! $msst_some ); ?>><span><?php esc_html_e( 'Every page', 'scripts-manager-by-mudassar' ); ?></span></label>
				<label><input type="radio" name="msst_pages" value="some" <?php checked( $msst_some ); ?>><span><?php esc_html_e( 'Only some pages', 'scripts-manager-by-mudassar' ); ?></span></label>
			</div>
			<div id="msst-rules-wrap" <?php echo $msst_some ? '' : 'hidden'; ?>>
				<p class="msst-hint"><?php esc_html_e( 'Show it when ANY group matches. A group matches when ALL its rules are true. Example: “Page type is Home page”.', 'scripts-manager-by-mudassar' ); ?></p>
				<div id="msst-rules"></div>
			</div>
		</div>

		<div class="msst-card">
			<h2><span class="msst-step">4</span> <?php esc_html_e( 'Turn it on', 'scripts-manager-by-mudassar' ); ?></h2>
			<p class="msst-desc"><?php esc_html_e( 'You can save it OFF first and turn it on later.', 'scripts-manager-by-mudassar' ); ?></p>
			<?php if ( ! $msst_locked ) : ?>
				<div class="msst-actions">
					<?php if ( $msst_snippet['active'] ) : ?>
						<button type="submit" name="msst_active" value="1" class="msst-btn msst-btn-big msst-order-1"><?php esc_html_e( 'Save changes', 'scripts-manager-by-mudassar' ); ?></button>
						<button type="submit" name="msst_active" value="0" class="msst-btn msst-btn-outline msst-btn-big msst-order-2"><?php esc_html_e( 'Save and turn OFF', 'scripts-manager-by-mudassar' ); ?></button>
					<?php else : ?>
						<button type="submit" name="msst_active" value="0" class="msst-btn msst-btn-outline msst-btn-big msst-order-2"><?php esc_html_e( 'Save as OFF', 'scripts-manager-by-mudassar' ); ?></button>
						<button type="submit" name="msst_active" value="1" class="msst-btn msst-btn-big msst-order-1"><?php esc_html_e( 'Save and turn ON', 'scripts-manager-by-mudassar' ); ?></button>
					<?php endif; ?>
					<a class="msst-btn msst-btn-outline msst-order-3" href="<?php echo esc_url( MSST_Admin::url( 'snippets' ) ); ?>"><?php esc_html_e( 'Cancel', 'scripts-manager-by-mudassar' ); ?></a>
				</div>
			<?php else : ?>
				<a class="msst-btn msst-btn-outline" href="<?php echo esc_url( MSST_Admin::url( 'snippets' ) ); ?>"><?php esc_html_e( 'Back', 'scripts-manager-by-mudassar' ); ?></a>
			<?php endif; ?>
			<details class="msst-adv">
				<summary><?php esc_html_e( 'More options (order, start and end dates)', 'scripts-manager-by-mudassar' ); ?></summary>
				<div class="msst-grid msst-grid-3">
					<p>
						<label class="msst-label" for="msst_priority"><?php esc_html_e( 'Order (lower runs first)', 'scripts-manager-by-mudassar' ); ?></label>
						<input class="msst-input" type="number" min="0" max="999" id="msst_priority" name="msst_priority" value="<?php echo esc_attr( (string) $msst_snippet['priority'] ); ?>">
					</p>
					<p>
						<label class="msst-label" for="msst_start"><?php esc_html_e( 'Start showing on', 'scripts-manager-by-mudassar' ); ?></label>
						<input class="msst-input" type="datetime-local" id="msst_start" name="msst_start" value="<?php echo esc_attr( $msst_fmt( $msst_snippet['start'] ) ); ?>">
					</p>
					<p>
						<label class="msst-label" for="msst_end"><?php esc_html_e( 'Stop showing on', 'scripts-manager-by-mudassar' ); ?></label>
						<input class="msst-input" type="datetime-local" id="msst_end" name="msst_end" value="<?php echo esc_attr( $msst_fmt( $msst_snippet['end'] ) ); ?>">
					</p>
				</div>
				<p class="msst-hint"><?php esc_html_e( 'Dates use your site time. Leave empty to show it all the time.', 'scripts-manager-by-mudassar' ); ?></p>
				<?php if ( ! $msst_new ) : ?>
					<p><?php esc_html_e( 'Shortcode:', 'scripts-manager-by-mudassar' ); ?> <code class="msst-copy">[msst_snippet id="<?php echo esc_html( (string) $msst_snippet['id'] ); ?>"]</code> <span class="msst-hint"><?php esc_html_e( '(works when “Where on the page?” is “Only where I put the shortcode”)', 'scripts-manager-by-mudassar' ); ?></span></p>
				<?php endif; ?>
			</details>
		</div>
	</div>
	<div class="msst-side">
		<div class="msst-card msst-card-blue">
			<h2><?php esc_html_e( 'What will happen', 'scripts-manager-by-mudassar' ); ?></h2>
			<div class="msst-sentence" id="msst-summary" aria-live="polite"></div>
		</div>
		<div class="msst-card">
			<h2><?php esc_html_e( 'Checklist', 'scripts-manager-by-mudassar' ); ?></h2>
			<div id="msst-checklist">
				<div class="msst-chk" data-check="name"><i>✓</i> <?php esc_html_e( 'Name added', 'scripts-manager-by-mudassar' ); ?></div>
				<div class="msst-chk" data-check="code"><i>✓</i> <?php esc_html_e( 'Code added', 'scripts-manager-by-mudassar' ); ?></div>
				<div class="msst-chk" data-check="where"><i>✓</i> <?php esc_html_e( 'Location chosen', 'scripts-manager-by-mudassar' ); ?></div>
				<div class="msst-chk" data-check="on"><i>✓</i> <?php esc_html_e( 'Turned ON', 'scripts-manager-by-mudassar' ); ?></div>
			</div>
		</div>
		<div class="msst-card">
			<h2><?php esc_html_e( 'Need help?', 'scripts-manager-by-mudassar' ); ?></h2>
			<p class="msst-desc"><?php esc_html_e( 'We answer quickly.', 'scripts-manager-by-mudassar' ); ?></p>
			<?php echo MSST_Brand::link( 'contact', 'add-snippet-need-help', __( 'Contact Us', 'scripts-manager-by-mudassar' ), 'msst-btn msst-btn-outline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</form>
