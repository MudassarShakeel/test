<?php
/**
 * Admin screen under Settings and all form handlers.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI controller.
 */
class MSST_Admin {

	const PAGE = 'mudassar-snippet-studio';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MSST_FILE ), array( __CLASS__, 'action_links' ) );

		$handlers = array( 'save_headers', 'save_snippet', 'toggle', 'delete', 'duplicate', 'bulk', 'import', 'export', 'restore', 'save_settings', 'regen_secret', 'clear_log', 'test_mode', 'generate', 'use_library' );
		foreach ( $handlers as $handler ) {
			add_action( 'admin_post_msst_' . $handler, array( __CLASS__, 'handle_' . $handler ) );
		}
	}

	/**
	 * Tab definitions.
	 *
	 * @return array slug => label
	 */
	public static function tabs() {
		return array(
			'headers'    => __( 'Headers & Footers', 'mudassar-snippet-studio' ),
			'snippets'   => __( 'Snippets', 'mudassar-snippet-studio' ),
			'edit'       => __( 'Add / Edit Snippet', 'mudassar-snippet-studio' ),
			'conditions' => __( 'Conditional Logic', 'mudassar-snippet-studio' ),
			'library'    => __( 'Library & Generator', 'mudassar-snippet-studio' ),
			'revisions'  => __( 'Revisions & Schedule', 'mudassar-snippet-studio' ),
			'tools'      => __( 'Import / Export', 'mudassar-snippet-studio' ),
			'logs'       => __( 'Error Log & Audit', 'mudassar-snippet-studio' ),
			'settings'   => __( 'Settings & Security', 'mudassar-snippet-studio' ),
			'support'    => __( 'Support', 'mudassar-snippet-studio' ),
		);
	}

	/**
	 * Add Settings > Snippet Studio.
	 */
	public static function menu() {
		add_options_page(
			__( 'Mudassar Snippet Studio', 'mudassar-snippet-studio' ),
			__( 'Snippet Studio', 'mudassar-snippet-studio' ),
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
		array_unshift( $links, '<a href="' . esc_url( self::url( 'snippets' ) ) . '">' . esc_html__( 'Open', 'mudassar-snippet-studio' ) . '</a>' );
		return $links;
	}

	/**
	 * Admin URL for a tab.
	 *
	 * @param string $tab  Tab slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $tab = 'headers', array $args = array() ) {
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
		wp_localize_script(
			'msst-admin',
			'msstData',
			array(
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
				'editor'    => 'edit' === self::current_tab() ? wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) ) : false,
				'i18n'      => array(
					'addRule'  => __( '+ Add rule', 'mudassar-snippet-studio' ),
					'addGroup' => __( '+ Add OR group', 'mudassar-snippet-studio' ),
					'remove'   => __( 'Remove', 'mudassar-snippet-studio' ),
					'orLabel'  => __( 'OR', 'mudassar-snippet-studio' ),
					'confirm'  => __( 'Are you sure?', 'mudassar-snippet-studio' ),
					'copied'   => __( 'Copied', 'mudassar-snippet-studio' ),
				),
			)
		);
		if ( 'edit' === self::current_tab() ) {
			wp_enqueue_style( 'wp-codemirror' );
		}
	}

	/**
	 * Active tab slug from the query string (whitelisted).
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'headers'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		return isset( self::tabs()[ $tab ] ) ? $tab : 'headers';
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mudassar-snippet-studio' ), 403 );
		}
		$tab = self::current_tab();
		echo '<div class="wrap msst"><h1 class="screen-reader-text">' . esc_html__( 'Mudassar Snippet Studio', 'mudassar-snippet-studio' ) . '</h1>';
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
			'title'      => self::post_text( 'msst_title' ) ? self::post_text( 'msst_title' ) : __( 'Untitled snippet', 'mudassar-snippet-studio' ),
			'type'       => sanitize_key( self::post_raw( 'msst_type' ) ),
			'code'       => self::post_raw( 'msst_code' ), // Raw by design; capability checked in MSST_Snippets::save().
			'location'   => sanitize_key( self::post_raw( 'msst_location' ) ),
			'param'      => absint( self::post_raw( 'msst_param' ) ),
			'priority'   => absint( self::post_raw( 'msst_priority' ) ),
			'active'     => '1' === self::post_raw( 'msst_active' ),
			'conditions' => MSST_Conditions::clean( self::normalise_rules( $rules ) ),
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
			wp_die( esc_html__( 'You are not allowed to save raw code.', 'mudassar-snippet-studio' ), 403 );
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
		self::flash( 'success', __( 'Header and footer settings saved.', 'mudassar-snippet-studio' ) );
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
		self::flash( 'success', __( 'Snippet saved.', 'mudassar-snippet-studio' ) );
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
			self::flash( 'success', $to ? __( 'Snippet activated.', 'mudassar-snippet-studio' ) : __( 'Snippet deactivated.', 'mudassar-snippet-studio' ) );
		} else {
			self::flash( 'error', __( 'Could not change the snippet. Check PHP permissions and syntax.', 'mudassar-snippet-studio' ) );
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
			self::flash( 'success', __( 'Snippet deleted.', 'mudassar-snippet-studio' ) );
		} else {
			self::flash( 'error', __( 'Could not delete the snippet.', 'mudassar-snippet-studio' ) );
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
		self::flash( 'success', __( 'Snippet duplicated (inactive).', 'mudassar-snippet-studio' ) );
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
		self::flash( 'success', sprintf( _n( '%d snippet updated.', '%d snippets updated.', $done, 'mudassar-snippet-studio' ), $done ) );
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
			self::flash( 'error', __( 'Please choose a JSON file.', 'mudassar-snippet-studio' ) );
			self::back( 'tools' );
		}
		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		if ( 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || (int) $file['size'] > MSST_Importer::MAX_BYTES ) {
			self::flash( 'error', __( 'Only .json files up to 2 MB are accepted.', 'mudassar-snippet-studio' ) );
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
					__( 'Imported %1$d snippets as inactive. Skipped %2$d. Review each one before activating.', 'mudassar-snippet-studio' ),
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
		header( 'Content-Disposition: attachment; filename="snippet-studio-' . gmdate( 'Y-m-d' ) . '.json"' );
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
			self::flash( 'error', __( 'Revision not found.', 'mudassar-snippet-studio' ) );
			self::back( 'revisions' );
		}
		$snippet['code']   = $revisions[ $rev ]['code'];
		$snippet['type']   = $revisions[ $rev ]['type'];
		$snippet['active'] = false;
		$result            = MSST_Snippets::save( $snippet, $id );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
		} else {
			self::flash( 'success', __( 'Revision restored. The snippet is inactive until you activate it.', 'mudassar-snippet-studio' ) );
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
		self::flash( 'success', __( 'Settings saved.', 'mudassar-snippet-studio' ) );
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
		self::flash( 'success', __( 'New safe-mode URL created.', 'mudassar-snippet-studio' ) );
		self::back( 'settings' );
	}

	/**
	 * Toggle personal test mode.
	 */
	public static function handle_test_mode() {
		MSST_Security::guard( 'test_mode' );
		$on = isset( $_GET['to'] ) && '1' === $_GET['to']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		update_user_meta( get_current_user_id(), 'msst_test_mode', $on ? 1 : 0 );
		self::flash( 'success', $on ? __( 'Test mode on: no snippets run for you.', 'mudassar-snippet-studio' ) : __( 'Test mode off.', 'mudassar-snippet-studio' ) );
		self::back( 'settings' );
	}

	/**
	 * Clear a log.
	 */
	public static function handle_clear_log() {
		MSST_Security::guard( 'clear_log' );
		$name = isset( $_GET['log'] ) && 'audit' === $_GET['log'] ? 'audit' : 'errors'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		MSST_Logger::clear( $name );
		self::flash( 'success', __( 'Log cleared.', 'mudassar-snippet-studio' ) );
		self::back( 'logs' );
	}

	/**
	 * Create an inactive snippet from the generator.
	 */
	public static function handle_generate() {
		MSST_Security::guard( 'generate' );
		$generated = MSST_Library::generate( sanitize_key( self::post_raw( 'msst_generator' ) ), self::post_text( 'msst_gen_name' ), self::post_text( 'msst_gen_slug' ) );
		if ( ! $generated ) {
			self::flash( 'error', __( 'Please enter a valid name and slug (max 20 characters).', 'mudassar-snippet-studio' ) );
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
		self::flash( 'success', __( 'Snippet generated (inactive). Review it, then activate.', 'mudassar-snippet-studio' ) );
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
			self::flash( 'error', __( 'Library item not found.', 'mudassar-snippet-studio' ) );
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
		self::flash( 'success', __( 'Added to your snippets (inactive). Review it, then activate.', 'mudassar-snippet-studio' ) );
		self::back( 'edit', array( 'snippet' => $result ) );
	}
}
