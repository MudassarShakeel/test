<?php
/**
 * Admin screen under Settings and all form handlers.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI controller.
 */
class MSST_Admin {

	const PAGE = 'scripts-manager-by-mudassar';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MSST_FILE ), array( __CLASS__, 'action_links' ) );

		$handlers = array( 'save_headers', 'save_snippet', 'toggle', 'delete', 'duplicate', 'bulk', 'import', 'export', 'restore', 'save_settings', 'regen_secret', 'clear_log', 'download_log', 'test_mode', 'generate', 'use_library' );
		foreach ( $handlers as $handler ) {
			add_action( 'admin_post_msst_' . $handler, array( __CLASS__, 'handle_' . $handler ) );
		}
	}

	/**
	 * Main tabs (always visible).
	 *
	 * @return array slug => label
	 */
	public static function main_tabs() {
		return array(
			'start'    => __( 'Start Here', 'scripts-manager-by-mudassar' ),
			'snippets' => __( 'My Snippets', 'scripts-manager-by-mudassar' ),
			'edit'     => __( 'Add Snippet', 'scripts-manager-by-mudassar' ),
			'headers'  => __( 'Headers & Footers', 'scripts-manager-by-mudassar' ),
			'library'  => __( 'Ready-made', 'scripts-manager-by-mudassar' ),
		);
	}

	/**
	 * Secondary tabs (shown under "More").
	 *
	 * @return array slug => label
	 */
	public static function more_tabs() {
		return array(
			'conditions' => __( 'Rules guide', 'scripts-manager-by-mudassar' ),
			'revisions'  => __( 'History & Schedule', 'scripts-manager-by-mudassar' ),
			'tools'      => __( 'Import / Export', 'scripts-manager-by-mudassar' ),
			'logs'       => __( 'Problems & Activity', 'scripts-manager-by-mudassar' ),
			'settings'   => __( 'Settings & Safety', 'scripts-manager-by-mudassar' ),
			'support'    => __( 'Help & Contact', 'scripts-manager-by-mudassar' ),
		);
	}

	/**
	 * All tabs.
	 *
	 * @return array slug => label
	 */
	public static function tabs() {
		return self::main_tabs() + self::more_tabs();
	}

	/**
	 * Print the grey "what is this page?" box.
	 *
	 * @param string $title Bold line.
	 * @param string $text  Plain explanation.
	 */
	public static function intro( $title, $text ) {
		printf(
			'<div class="msst-intro"><div class="msst-intro-i" aria-hidden="true">i</div><div><strong>%1$s</strong><span>%2$s</span></div></div>',
			esc_html( $title ),
			esc_html( $text )
		);
	}

	/**
	 * Starter code for the "Start from an example" list.
	 *
	 * @return array type => [ label => code ]
	 */
	public static function examples() {
		return array(
			'html'      => array(
				__( 'Blue banner', 'scripts-manager-by-mudassar' ) => '<div style="padding:12px;background:#0071e3;color:#fff;text-align:center">Hello! This is my banner.</div>',
				__( 'Small notice box', 'scripts-manager-by-mudassar' ) => '<div style="border:1px solid #d2d2d7;border-radius:10px;padding:12px">Write your message here.</div>',
			),
			'css'       => array(
				__( 'Smooth scrolling', 'scripts-manager-by-mudassar' ) => 'html { scroll-behavior: smooth; }',
				__( 'Round buttons', 'scripts-manager-by-mudassar' ) => "button,
.button,
input[type='submit'] {
	border-radius: 999px;
}",
			),
			'js'        => array(
				__( 'Say hello in the console', 'scripts-manager-by-mudassar' ) => "console.log( 'Hello from Scripts Manager!' );",
				__( 'Run code when the page is ready', 'scripts-manager-by-mudassar' ) => "document.addEventListener( 'DOMContentLoaded', function () {
	console.log( 'Page is ready' );
} );",
			),
			'php'       => array(
				__( 'Hide admin bar for non-admins', 'scripts-manager-by-mudassar' ) => "add_filter( 'show_admin_bar', function ( \$show ) {
	return current_user_can( 'manage_options' ) ? \$show : false;
} );",
				__( 'Disable emoji scripts', 'scripts-manager-by-mudassar' ) => "remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );",
			),
			'text'      => array(
				__( 'Simple message', 'scripts-manager-by-mudassar' ) => "Thank you for visiting!\nCome back soon.",
			),
			'universal' => array(
				__( 'Message with a shortcode', 'scripts-manager-by-mudassar' ) => '<p>Welcome!</p>[your_shortcode]',
			),
		);
	}

	/**
	 * Add Settings > Scripts Manager.
	 */
	public static function menu() {
		add_options_page(
			__( 'Scripts Manager By Mudassar', 'scripts-manager-by-mudassar' ),
			__( 'Scripts Manager', 'scripts-manager-by-mudassar' ),
			MSST_Security::CAP,
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Plugin list shortcut.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( 'snippets' ) ) . '">' . esc_html__( 'Open', 'scripts-manager-by-mudassar' ) . '</a>' );
		return $links;
	}

	/**
	 * Admin URL for a tab.
	 *
	 * @param string $tab  Tab slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $tab = 'start', array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::PAGE,
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * URL for a nonce-protected admin-post action.
	 *
	 * @param string $action Action without prefix.
	 * @param array  $args   Args.
	 * @return string
	 */
	public static function action_url( $action, array $args = array() ) {
		return wp_nonce_url(
			add_query_arg( array_merge( array( 'action' => 'msst_' . $action ), $args ), admin_url( 'admin-post.php' ) ),
			'msst_' . $action
		);
	}

	/**
	 * Load assets only on our screen.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'msst-admin', MSST_URL . 'assets/admin.css', array(), MSST_VERSION );
		wp_enqueue_script( 'msst-admin', MSST_URL . 'assets/admin.js', array(), MSST_VERSION, true );

		$script_data           = self::script_data();
		$script_data['editor'] = 'edit' === self::current_tab() ? wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) ) : false;
		wp_localize_script( 'msst-admin', 'msstData', $script_data );
		if ( 'edit' === self::current_tab() ) {
			wp_enqueue_style( 'wp-codemirror' );
		}
	}

	/**
	 * Data handed to admin.js (also used by the render test).
	 *
	 * @return array
	 */
	public static function script_data() {
		$catalogue = array();
		foreach ( MSST_Conditions::catalogue() as $key => $def ) {
			$catalogue[ $key ] = array(
				'label'  => $def['label'],
				'group'  => $def['group'],
				'ops'    => $def['ops'],
				'values' => $def['values'],
			);
		}
		$locations = array();
		foreach ( array_keys( MSST_Snippets::types() ) as $type ) {
			$locations[ $type ] = MSST_Snippets::locations( $type );
		}
		$editing = self::current_snippet();
		return array(
			'catalogue' => $catalogue,
			'locations' => $locations,
			'rules'     => $editing ? $editing['conditions'] : array(),
			'modes'     => array(
				'php'       => 'application/x-httpd-php',
				'js'        => 'text/javascript',
				'css'       => 'text/css',
				'html'      => 'text/html',
				'text'      => 'text/html',
				'universal' => 'text/html',
			),
			'editor'    => false,
			'examples'  => self::examples(),
			'phrases'   => MSST_Snippets::phrases(),
			'kinds'     => wp_list_pluck( MSST_Snippets::kinds(), 0 ),
			'i18n'      => array(
				'showWhen'   => __( 'Show when', 'scripts-manager-by-mudassar' ),
				'exampleAsk' => __( 'Replace what you wrote with this example?', 'scripts-manager-by-mudassar' ),
				'on'         => __( 'ON', 'scripts-manager-by-mudassar' ),
				'off'        => __( 'OFF', 'scripts-manager-by-mudassar' ),
				'itWill'     => __( 'This', 'scripts-manager-by-mudassar' ),
				'willRun'    => __( 'code will run', 'scripts-manager-by-mudassar' ),
				'willAppear' => __( 'code will appear', 'scripts-manager-by-mudassar' ),
				'onEvery'    => __( 'on every page', 'scripts-manager-by-mudassar' ),
				'onSome'     => __( 'only on the pages you choose', 'scripts-manager-by-mudassar' ),
				'itIs'       => __( 'It is currently', 'scripts-manager-by-mudassar' ),
				'paragraph'  => __( 'paragraph', 'scripts-manager-by-mudassar' ),
				'addRule'    => __( '+ Add rule', 'scripts-manager-by-mudassar' ),
				'addGroup'   => __( '+ Add OR group', 'scripts-manager-by-mudassar' ),
				'remove'     => __( 'Remove', 'scripts-manager-by-mudassar' ),
				'orLabel'    => __( 'OR', 'scripts-manager-by-mudassar' ),
				'confirm'    => __( 'Are you sure?', 'scripts-manager-by-mudassar' ),
				'copied'     => __( 'Copied', 'scripts-manager-by-mudassar' ),
			),
		);
	}

	/**
	 * Active tab slug from the query string (whitelisted).
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'start'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		return isset( self::tabs()[ $tab ] ) ? $tab : 'start';
	}

	/**
	 * Snippet being edited, if any.
	 *
	 * @return array|null
	 */
	public static function current_snippet() {
		$id = isset( $_GET['snippet'] ) ? absint( $_GET['snippet'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $id ? MSST_Snippets::get( $id ) : null;
	}

	/**
	 * Render the whole settings page.
	 */
	public static function render_page() {
		if ( ! MSST_Security::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'scripts-manager-by-mudassar' ), 403 );
		}
		$tab = self::current_tab();
		echo '<div class="wrap msst"><h1 class="screen-reader-text">' . esc_html__( 'Scripts Manager By Mudassar', 'scripts-manager-by-mudassar' ) . '</h1>';
		include MSST_DIR . 'admin/views/header.php';
		self::render_flash();
		echo '<div class="msst-view msst-view-' . esc_attr( $tab ) . '">';
		include MSST_DIR . 'admin/views/' . $tab . '.php';
		echo '</div>';
		include MSST_DIR . 'admin/views/footer.php';
		echo '</div>';
	}

	/**
	 * Store a one-time message for the next page load.
	 *
	 * @param string $type    success|error|warning.
	 * @param string $message Plain text.
	 */
	public static function flash( $type, $message ) {
		set_transient(
			'msst_flash_' . get_current_user_id(),
			array(
				'type' => in_array( $type, array( 'success', 'error', 'warning' ), true ) ? $type : 'success',
				'msg'  => $message,
			),
			120
		);
	}

	/**
	 * Show and clear the message.
	 */
	private static function render_flash() {
		$key   = 'msst_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! is_array( $flash ) ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="msst-note msst-note-%1$s" role="status">%2$s</div>', esc_attr( $flash['type'] ), esc_html( $flash['msg'] ) );
	}

	/**
	 * Redirect back to a tab.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args Args.
	 */
	private static function back( $tab, array $args = array() ) {
		wp_safe_redirect( self::url( $tab, $args ) );
		exit;
	}

	/**
	 * Fetch a posted string without sanitising (callers decide).
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function post_raw( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in MSST_Security::guard() by every caller.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}

	/**
	 * Fetch a posted text field.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function post_text( $key ) {
		return sanitize_text_field( self::post_raw( $key ) );
	}

	/**
	 * Convert a datetime-local value in site time to a UTC timestamp.
	 *
	 * @param string $value Value like 2026-11-25T00:00.
	 * @return int 0 when empty/invalid.
	 */
	private static function parse_datetime( $value ) {
		$value = str_replace( 'T', ' ', trim( $value ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value ) ) {
			return 0;
		}
		$gmt = get_gmt_from_date( $value . ':00' );
		return $gmt ? (int) strtotime( $gmt . ' UTC' ) : 0;
	}

	/**
	 * Collect posted snippet fields.
	 *
	 * @return array
	 */
	private static function collect_snippet_input() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked by caller; sanitised by MSST_Conditions::clean().
		$rules = isset( $_POST['msst_rules'] ) && is_array( $_POST['msst_rules'] ) ? wp_unslash( $_POST['msst_rules'] ) : array();
		return array(
			'title'      => self::post_text( 'msst_title' ) ? self::post_text( 'msst_title' ) : __( 'Untitled snippet', 'scripts-manager-by-mudassar' ),
			'type'       => sanitize_key( self::post_raw( 'msst_type' ) ),
			'code'       => self::post_raw( 'msst_code' ), // Raw by design; capability checked in MSST_Snippets::save().
			'location'   => sanitize_key( self::post_raw( 'msst_location' ) ),
			'param'      => absint( self::post_raw( 'msst_param' ) ),
			'priority'   => absint( self::post_raw( 'msst_priority' ) ),
			'active'     => '1' === self::post_raw( 'msst_active' ),
			'conditions' => 'every' === self::post_raw( 'msst_pages' ) ? array() : MSST_Conditions::clean( self::normalise_rules( $rules ) ),
			'start'      => self::parse_datetime( self::post_raw( 'msst_start' ) ),
			'end'        => self::parse_datetime( self::post_raw( 'msst_end' ) ),
		);
	}

	/**
	 * Re-index posted rules.
	 *
	 * @param array $rules Posted rules.
	 * @return array
	 */
	private static function normalise_rules( array $rules ) {
		$out = array();
		foreach ( $rules as $group ) {
			if ( is_array( $group ) ) {
				$out[] = array_values( array_filter( $group, 'is_array' ) );
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Save global header/body/footer and integration IDs.
	 */
	public static function handle_save_headers() {
		MSST_Security::guard( 'save_headers' );
		if ( ! MSST_Security::can_edit_raw() ) {
			wp_die( esc_html__( 'You are not allowed to save raw code.', 'scripts-manager-by-mudassar' ), 403 );
		}
		$data = array();
		foreach ( array( 'header', 'body', 'footer' ) as $slot ) {
			$data[ $slot ] = substr( self::post_raw( 'msst_' . $slot ), 0, 200000 );
		}
		foreach ( array( 'ga4', 'gtm', 'meta', 'tiktok' ) as $kind ) {
			$data[ $kind ] = MSST_Settings::clean_integration_id( $kind, self::post_text( 'msst_' . $kind ) );
		}
		MSST_Settings::save_global( $data );
		MSST_Logger::audit( 'Saved global header/footer code' );
		self::flash( 'success', __( 'Header and footer settings saved.', 'scripts-manager-by-mudassar' ) );
		self::back( 'headers' );
	}

	/**
	 * Create or update a snippet.
	 */
	public static function handle_save_snippet() {
		MSST_Security::guard( 'save_snippet' );
		$id     = absint( self::post_raw( 'msst_id' ) );
		$result = MSST_Snippets::save( self::collect_snippet_input(), $id );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
			self::back( 'edit', $id ? array( 'snippet' => $id ) : array() );
		}
		self::flash( 'success', __( 'Snippet saved.', 'scripts-manager-by-mudassar' ) );
		self::back( 'edit', array( 'snippet' => $result ) );
	}

	/**
	 * Activate/deactivate.
	 */
	public static function handle_toggle() {
		MSST_Security::guard( 'toggle' );
		$id = isset( $_GET['snippet'] ) ? absint( $_GET['snippet'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$to = isset( $_GET['to'] ) && '1' === $_GET['to']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( MSST_Snippets::set_active( $id, $to ) ) {
			self::flash( 'success', $to ? __( 'Snippet activated.', 'scripts-manager-by-mudassar' ) : __( 'Snippet deactivated.', 'scripts-manager-by-mudassar' ) );
		} else {
			self::flash( 'error', __( 'Could not change the snippet. Check PHP permissions and syntax.', 'scripts-manager-by-mudassar' ) );
		}
		self::back( 'snippets' );
	}

	/**
	 * Delete a snippet.
	 */
	public static function handle_delete() {
		MSST_Security::guard( 'delete' );
		$id = isset( $_GET['snippet'] ) ? absint( $_GET['snippet'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		if ( MSST_Snippets::delete( $id ) ) {
			self::flash( 'success', __( 'Snippet deleted.', 'scripts-manager-by-mudassar' ) );
		} else {
			self::flash( 'error', __( 'Could not delete the snippet.', 'scripts-manager-by-mudassar' ) );
		}
		self::back( 'snippets' );
	}

	/**
	 * Duplicate a snippet.
	 */
	public static function handle_duplicate() {
		MSST_Security::guard( 'duplicate' );
		$id     = isset( $_GET['snippet'] ) ? absint( $_GET['snippet'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$result = MSST_Snippets::duplicate( $id );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
			self::back( 'snippets' );
		}
		self::flash( 'success', __( 'Snippet duplicated (inactive).', 'scripts-manager-by-mudassar' ) );
		self::back( 'edit', array( 'snippet' => $result ) );
	}

	/**
	 * Bulk actions on the list.
	 */
	public static function handle_bulk() {
		MSST_Security::guard( 'bulk' );
		$action = sanitize_key( self::post_raw( 'bulk_action' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified in guard(); mapped through absint().
		$ids  = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', wp_unslash( $_POST['ids'] ) ) : array();
		$ids  = array_slice( array_filter( $ids ), 0, 200 );
		$done = 0;
		foreach ( $ids as $id ) {
			if ( 'activate' === $action ) {
				$done += MSST_Snippets::set_active( $id, true ) ? 1 : 0;
			} elseif ( 'deactivate' === $action ) {
				$done += MSST_Snippets::set_active( $id, false ) ? 1 : 0;
			} elseif ( 'delete' === $action ) {
				$done += MSST_Snippets::delete( $id ) ? 1 : 0;
			}
		}
		/* translators: %d: number of snippets */
		self::flash( 'success', sprintf( _n( '%d snippet updated.', '%d snippets updated.', $done, 'scripts-manager-by-mudassar' ), $done ) );
		self::back( 'snippets' );
	}

	/**
	 * Import a JSON file.
	 */
	public static function handle_import() {
		MSST_Security::guard( 'import' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$file = isset( $_FILES['msst_file'] ) && is_array( $_FILES['msst_file'] ) ? $_FILES['msst_file'] : array();
		if ( empty( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
			self::flash( 'error', __( 'Please choose a JSON file.', 'scripts-manager-by-mudassar' ) );
			self::back( 'tools' );
		}
		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		if ( 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || (int) $file['size'] > MSST_Importer::MAX_BYTES ) {
			self::flash( 'error', __( 'Only .json files up to 2 MB are accepted.', 'scripts-manager-by-mudassar' ) );
			self::back( 'tools' );
		}
		$json   = (string) file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded temp file.
		$result = MSST_Importer::import( $json );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
		} else {
			self::flash(
				'success',
				sprintf(
					/* translators: 1: imported count, 2: skipped count */
					__( 'Imported %1$d snippets as inactive. Skipped %2$d. Review each one before activating.', 'scripts-manager-by-mudassar' ),
					$result['imported'],
					$result['skipped']
				)
			);
		}
		self::back( 'tools' );
	}

	/**
	 * Download all snippets as JSON.
	 */
	public static function handle_export() {
		MSST_Security::guard( 'export' );
		MSST_Logger::audit( 'Exported snippets' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="scripts-manager-' . gmdate( 'Y-m-d' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo MSST_Importer::export(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	/**
	 * Restore a revision as a new (inactive) version of the code.
	 */
	public static function handle_restore() {
		MSST_Security::guard( 'restore' );
		$id        = isset( $_GET['snippet'] ) ? absint( $_GET['snippet'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$rev       = isset( $_GET['rev'] ) ? absint( $_GET['rev'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$snippet   = MSST_Snippets::get( $id );
		$revisions = MSST_Snippets::revisions( $id );
		if ( ! $snippet || ! isset( $revisions[ $rev ] ) ) {
			self::flash( 'error', __( 'Revision not found.', 'scripts-manager-by-mudassar' ) );
			self::back( 'revisions' );
		}
		$snippet['code']   = $revisions[ $rev ]['code'];
		$snippet['type']   = $revisions[ $rev ]['type'];
		$snippet['active'] = false;
		$result            = MSST_Snippets::save( $snippet, $id );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
		} else {
			self::flash( 'success', __( 'Revision restored. The snippet is inactive until you activate it.', 'scripts-manager-by-mudassar' ) );
		}
		self::back( 'revisions', array( 'snippet' => $id ) );
	}

	/**
	 * Save plugin settings.
	 */
	public static function handle_save_settings() {
		MSST_Security::guard( 'save_settings' );
		$values = array();
		foreach ( array_keys( MSST_Settings::defaults() ) as $key ) {
			$values[ $key ] = '1' === self::post_raw( 'msst_' . $key );
		}
		MSST_Settings::save( $values );
		MSST_Logger::audit( 'Saved settings' );
		self::flash( 'success', __( 'Settings saved.', 'scripts-manager-by-mudassar' ) );
		self::back( 'settings' );
	}

	/**
	 * Regenerate the safe-mode secret.
	 */
	public static function handle_regen_secret() {
		MSST_Security::guard( 'regen_secret' );
		delete_option( 'msst_safe_secret' );
		MSST_Security::ensure_secret();
		MSST_Logger::audit( 'Regenerated safe-mode secret' );
		self::flash( 'success', __( 'New safe-mode URL created.', 'scripts-manager-by-mudassar' ) );
		self::back( 'settings' );
	}

	/**
	 * Toggle personal test mode.
	 */
	public static function handle_test_mode() {
		MSST_Security::guard( 'test_mode' );
		$on = isset( $_GET['to'] ) && '1' === $_GET['to']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		update_user_meta( get_current_user_id(), 'msst_test_mode', $on ? 1 : 0 );
		self::flash( 'success', $on ? __( 'Test mode on: no snippets run for you.', 'scripts-manager-by-mudassar' ) : __( 'Test mode off.', 'scripts-manager-by-mudassar' ) );
		self::back( 'settings' );
	}

	/**
	 * Clear a log (problem list, activity list or the log file).
	 */
	public static function handle_clear_log() {
		MSST_Security::guard( 'clear_log' );
		$which = isset( $_GET['log'] ) ? sanitize_key( wp_unslash( $_GET['log'] ) ) : 'errors'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		if ( 'file' === $which ) {
			MSST_Logger::clear_file();
			MSST_Logger::audit( 'Cleared the error log file' );
		} else {
			MSST_Logger::clear( 'audit' === $which ? 'audit' : 'errors' );
		}
		self::flash( 'success', __( 'Log cleared.', 'scripts-manager-by-mudassar' ) );
		self::back( 'logs' );
	}

	/**
	 * Download the error log file.
	 */
	public static function handle_download_log() {
		MSST_Security::guard( 'download_log' );
		$path = MSST_Logger::log_path();
		if ( ! file_exists( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			self::flash( 'warning', __( 'There is no log file yet. That is good news!', 'scripts-manager-by-mudassar' ) );
			self::back( 'logs' );
		}
		MSST_Logger::audit( 'Downloaded the error log file' );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="scripts-manager-error-log-' . gmdate( 'Y-m-d' ) . '.txt"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Our own protected log file.
		exit;
	}

	/**
	 * Create an inactive snippet from the generator.
	 */
	public static function handle_generate() {
		MSST_Security::guard( 'generate' );
		$generated = MSST_Library::generate( sanitize_key( self::post_raw( 'msst_generator' ) ), self::post_text( 'msst_gen_name' ), self::post_text( 'msst_gen_slug' ) );
		if ( ! $generated ) {
			self::flash( 'error', __( 'Please enter a valid name and slug (max 20 characters).', 'scripts-manager-by-mudassar' ) );
			self::back( 'library' );
		}
		$result = MSST_Snippets::save(
			array(
				'title'      => $generated['title'],
				'type'       => 'php',
				'code'       => $generated['code'],
				'location'   => 'everywhere',
				'param'      => 1,
				'priority'   => 10,
				'active'     => false,
				'conditions' => array(),
				'start'      => 0,
				'end'        => 0,
			),
			0
		);
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
			self::back( 'library' );
		}
		self::flash( 'success', __( 'Snippet generated (inactive). Review it, then activate.', 'scripts-manager-by-mudassar' ) );
		self::back( 'edit', array( 'snippet' => $result ) );
	}

	/**
	 * Copy a library item into a new inactive snippet.
	 */
	public static function handle_use_library() {
		MSST_Security::guard( 'use_library' );
		$key   = isset( $_GET['item'] ) ? sanitize_key( wp_unslash( $_GET['item'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$items = MSST_Library::items();
		if ( ! isset( $items[ $key ] ) ) {
			self::flash( 'error', __( 'Library item not found.', 'scripts-manager-by-mudassar' ) );
			self::back( 'library' );
		}
		$item   = $items[ $key ];
		$result = MSST_Snippets::save(
			array(
				'title'      => $item['title'],
				'type'       => $item['type'],
				'code'       => $item['code'],
				'location'   => $item['location'],
				'param'      => 1,
				'priority'   => 10,
				'active'     => false,
				'conditions' => array(),
				'start'      => 0,
				'end'        => 0,
			),
			0
		);
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
			self::back( 'library' );
		}
		self::flash( 'success', __( 'Added to your snippets (inactive). Review it, then activate.', 'scripts-manager-by-mudassar' ) );
		self::back( 'edit', array( 'snippet' => $result ) );
	}
}
