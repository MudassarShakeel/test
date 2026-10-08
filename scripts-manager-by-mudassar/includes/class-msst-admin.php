<?php
/**
 * Admin menu, screens and form handlers.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI controller.
 */
class MSST_Admin {

	const PAGE_LIST     = 'scripts-manager';
	const PAGE_ADD      = 'scripts-manager-add';
	const PAGE_TOOLS    = 'scripts-manager-tools';
	const PAGE_SETTINGS = 'scripts-manager-settings';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MSST_FILE ), array( __CLASS__, 'action_links' ) );

		foreach ( array( 'save_snippet', 'toggle', 'delete', 'duplicate', 'bulk', 'export', 'import', 'save_settings', 'regen_secret', 'test_mode' ) as $handler ) {
			add_action( 'admin_post_msst_' . $handler, array( __CLASS__, 'handle_' . $handler ) );
		}
	}

	/**
	 * Left menu: Scripts Manager > All Snippets, Add New, Tools, Settings.
	 */
	public static function menu() {
		$cap = MSST_Security::CAP;
		add_menu_page( __( 'Scripts Manager By Mudassar', 'scripts-manager-by-mudassar' ), __( 'Scripts Manager', 'scripts-manager-by-mudassar' ), $cap, self::PAGE_LIST, array( __CLASS__, 'page_list' ), 'dashicons-editor-code', 80 );
		add_submenu_page( self::PAGE_LIST, __( 'All Snippets', 'scripts-manager-by-mudassar' ), __( 'All Snippets', 'scripts-manager-by-mudassar' ), $cap, self::PAGE_LIST, array( __CLASS__, 'page_list' ) );
		add_submenu_page( self::PAGE_LIST, __( 'Add New', 'scripts-manager-by-mudassar' ), __( 'Add New', 'scripts-manager-by-mudassar' ), $cap, self::PAGE_ADD, array( __CLASS__, 'page_form' ) );
		add_submenu_page( self::PAGE_LIST, __( 'Tools', 'scripts-manager-by-mudassar' ), __( 'Tools', 'scripts-manager-by-mudassar' ), $cap, self::PAGE_TOOLS, array( __CLASS__, 'page_tools' ) );
		add_submenu_page( self::PAGE_LIST, __( 'Settings', 'scripts-manager-by-mudassar' ), __( 'Settings', 'scripts-manager-by-mudassar' ), $cap, self::PAGE_SETTINGS, array( __CLASS__, 'page_settings' ) );
	}

	/**
	 * Plugin list shortcut.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( self::PAGE_LIST ) ) . '">' . esc_html__( 'All Snippets', 'scripts-manager-by-mudassar' ) . '</a>' );
		return $links;
	}

	/**
	 * Admin URL for one of our pages.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $page = self::PAGE_LIST, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
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
	 * Data handed to admin.js (also used by the render test).
	 *
	 * @return array
	 */
	public static function script_data() {
		return array(
			'locations' => array(
				'php'  => MSST_Snippets::locations( 'php' ),
				'html' => MSST_Snippets::locations( 'html' ),
			),
			'modes'     => array(
				'php'  => 'application/x-httpd-php',
				'js'   => 'text/javascript',
				'css'  => 'text/css',
				'html' => 'text/html',
			),
			'rows'      => array(
				'site_wide'  => array( 'ex_pages', 'ex_posts' ),
				'pages'      => array( 'pages' ),
				'posts'      => array( 'posts' ),
				'categories' => array( 'categories' ),
				'post_types' => array( 'post_types' ),
				'tags'       => array( 'tags' ),
			),
			'editor'    => false,
			'i18n'      => array(
				'confirm'  => __( 'Are you sure?', 'scripts-manager-by-mudassar' ),
				'selected' => __( 'selected', 'scripts-manager-by-mudassar' ),
				'copied'   => __( 'Copied', 'scripts-manager-by-mudassar' ),
			),
		);
	}

	/**
	 * Load assets only on our screens.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'scripts-manager' ) ) {
			return;
		}
		wp_enqueue_style( 'msst-admin', MSST_URL . 'assets/admin.css', array(), MSST_VERSION );
		wp_enqueue_script( 'msst-admin', MSST_URL . 'assets/admin.js', array(), MSST_VERSION, true );
		$data = self::script_data();
		if ( false !== strpos( (string) $hook, self::PAGE_ADD ) ) {
			$data['editor'] = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
			wp_enqueue_style( 'wp-codemirror' );
		}
		wp_localize_script( 'msst-admin', 'msstData', $data );
	}

	/* ---------------------------------------------------------------- helpers */

	/**
	 * Print the common wrapper start (branding) and flash message.
	 *
	 * @param string $title Page heading.
	 * @param string $extra Extra HTML after the heading (already escaped).
	 */
	private static function open( $title, $extra = '' ) {
		if ( ! MSST_Security::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'scripts-manager-by-mudassar' ), 403 );
		}
		echo '<div class="wrap msst"><h1 class="screen-reader-text">' . esc_html( $title ) . '</h1>';
		include MSST_DIR . 'admin/views/header.php';
		echo '<div class="msst-heading"><h2 class="msst-h1">' . esc_html( $title ) . '</h2>' . $extra . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra is escaped by the caller.
		self::render_flash();
	}

	/**
	 * Print the common wrapper end.
	 */
	private static function close() {
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
	 * Redirect to one of our pages.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Args.
	 */
	private static function back( $page, array $args = array() ) {
		wp_safe_redirect( self::url( $page, $args ) );
		exit;
	}

	/**
	 * Posted raw string.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function post_raw( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in MSST_Security::guard() by every caller.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}

	/**
	 * Posted list (cleaned later by MSST_Snippets::clean_targets()).
	 *
	 * @param string $key Key.
	 * @return array
	 */
	private static function post_list( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by caller; cleaned by clean_targets().
		return isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array();
	}

	/**
	 * Posted integer IDs (checkboxes).
	 *
	 * @return int[]
	 */
	private static function post_ids() {
		return array_slice( array_values( array_filter( array_map( 'absint', array_filter( self::post_list( 'ids' ), 'is_scalar' ) ) ) ), 0, 200 );
	}

	/**
	 * Collect the snippet form.
	 *
	 * @return array
	 */
	private static function collect_input() {
		return array(
			'name'       => sanitize_text_field( self::post_raw( 'msst_name' ) ),
			'type'       => sanitize_key( self::post_raw( 'msst_type' ) ),
			'code'       => self::post_raw( 'msst_code' ), // Raw by design; capability checked in MSST_Snippets::save().
			'display_on' => sanitize_key( self::post_raw( 'msst_display_on' ) ),
			'location'   => sanitize_key( self::post_raw( 'msst_location' ) ),
			'device'     => sanitize_key( self::post_raw( 'msst_device' ) ),
			'status'     => '1' === self::post_raw( 'msst_status' ),
			'targets'    => array(
				'pages'      => self::post_list( 'msst_pages' ),
				'posts'      => self::post_list( 'msst_posts' ),
				'categories' => self::post_list( 'msst_categories' ),
				'post_types' => self::post_list( 'msst_post_types' ),
				'tags'       => self::post_list( 'msst_tags' ),
				'ex_pages'   => self::post_list( 'msst_ex_pages' ),
				'ex_posts'   => self::post_list( 'msst_ex_posts' ),
			),
		);
	}

	/**
	 * Choices for the page/post/category/tag/post-type pickers.
	 *
	 * @return array kind => [ id => label ]
	 */
	public static function picker_choices() {
		$choices = array(
			'pages'      => array(),
			'posts'      => array(),
			'categories' => array(),
			'tags'       => array(),
			'post_types' => array(),
		);
		$status  = array( 'publish', 'private', 'draft', 'future', 'pending' );
		$base    = array(
			'post_status'      => $status,
			'numberposts'      => 1000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- Admin-only picker, ids only.
			'orderby'          => 'title',
			'order'            => 'ASC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		);
		foreach ( get_posts( array_merge( $base, array( 'post_type' => 'page' ) ) ) as $id ) {
			$choices['pages'][ $id ] = get_the_title( $id ) ? get_the_title( $id ) : '#' . $id;
		}
		$types = array_diff( array_keys( get_post_types( array( 'public' => true ) ) ), array( 'page', 'attachment' ) );
		foreach ( get_posts( array_merge( $base, array( 'post_type' => array_values( $types ) ) ) ) as $id ) {
			$choices['posts'][ $id ] = get_the_title( $id ) ? get_the_title( $id ) : '#' . $id;
		}
		foreach ( (array) get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'number'     => 1000,
			)
		) as $term ) {
			if ( is_object( $term ) ) {
				$choices['categories'][ $term->term_id ] = $term->name;
			}
		}
		foreach ( (array) get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
				'number'     => 1000,
			)
		) as $term ) {
			if ( is_object( $term ) ) {
				$choices['tags'][ $term->term_id ] = $term->name;
			}
		}
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $slug => $object ) {
			if ( 'attachment' !== $slug ) {
				$choices['post_types'][ $slug ] = $object->labels->singular_name;
			}
		}
		return $choices;
	}

	/* ------------------------------------------------------------------ pages */

	/**
	 * All Snippets.
	 */
	public static function page_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$msst_search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$msst_orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
		$msst_order   = isset( $_GET['order'] ) && 'desc' === sanitize_key( wp_unslash( $_GET['order'] ) ) ? 'desc' : 'asc';
		$msst_paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$msst_list = MSST_Snippets::query(
			array(
				'search'  => $msst_search,
				'orderby' => $msst_orderby,
				'order'   => $msst_order,
				'paged'   => $msst_paged,
			)
		);
		self::open( __( 'Snippets', 'scripts-manager-by-mudassar' ), '<a class="msst-btn msst-btn-outline msst-btn-sm" href="' . esc_url( self::url( self::PAGE_ADD ) ) . '">' . esc_html__( 'Add New Snippet', 'scripts-manager-by-mudassar' ) . '</a>' );
		include MSST_DIR . 'admin/views/list.php';
		self::close();
	}

	/**
	 * Add New / Edit.
	 */
	public static function page_form() {
		$msst_id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.
		$msst_snippet = $msst_id ? MSST_Snippets::get( $msst_id ) : null;
		if ( $msst_id && ! $msst_snippet ) {
			self::flash( 'error', __( 'Snippet not found.', 'scripts-manager-by-mudassar' ) );
			self::back( self::PAGE_LIST );
		}
		if ( ! $msst_snippet ) {
			$msst_snippet = array(
				'id'         => 0,
				'name'       => '',
				'type'       => 'html',
				'code'       => '',
				'display_on' => 'site_wide',
				'location'   => 'header',
				'device'     => 'all',
				'targets'    => MSST_Snippets::empty_targets(),
				'status'     => true,
				'error'      => '',
				'sig'        => '',
			);
		}
		$msst_choices = self::picker_choices();
		self::open( $msst_id ? __( 'Edit Snippet', 'scripts-manager-by-mudassar' ) : __( 'Add New Snippet', 'scripts-manager-by-mudassar' ) );
		include MSST_DIR . 'admin/views/form.php';
		self::close();
	}

	/**
	 * Tools: export and import.
	 */
	public static function page_tools() {
		$msst_all = MSST_Snippets::query( array( 'per_page' => 200 ) );
		self::open( __( 'Tools', 'scripts-manager-by-mudassar' ) );
		include MSST_DIR . 'admin/views/tools.php';
		self::close();
	}

	/**
	 * Settings, safety and help.
	 */
	public static function page_settings() {
		self::open( __( 'Settings', 'scripts-manager-by-mudassar' ) );
		include MSST_DIR . 'admin/views/settings.php';
		self::close();
	}

	/* --------------------------------------------------------------- handlers */

	/**
	 * Create or update a snippet.
	 */
	public static function handle_save_snippet() {
		MSST_Security::guard( 'save_snippet' );
		$id     = absint( self::post_raw( 'msst_id' ) );
		$result = MSST_Snippets::save( self::collect_input(), $id );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
			self::back( self::PAGE_ADD, $id ? array( 'id' => $id ) : array() );
		}
		self::flash( 'success', __( 'Snippet saved.', 'scripts-manager-by-mudassar' ) );
		self::back( self::PAGE_ADD, array( 'id' => $result ) );
	}

	/**
	 * Turn ON/OFF.
	 */
	public static function handle_toggle() {
		MSST_Security::guard( 'toggle' );
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$on = isset( $_GET['to'] ) && '1' === $_GET['to']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( MSST_Snippets::set_status( $id, $on ) ) {
			self::flash( 'success', $on ? __( 'Snippet turned ON.', 'scripts-manager-by-mudassar' ) : __( 'Snippet turned OFF.', 'scripts-manager-by-mudassar' ) );
		} else {
			self::flash( 'error', __( 'Could not change the snippet. Check PHP permissions and syntax.', 'scripts-manager-by-mudassar' ) );
		}
		self::back( self::PAGE_LIST );
	}

	/**
	 * Delete.
	 */
	public static function handle_delete() {
		MSST_Security::guard( 'delete' );
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$ok = MSST_Snippets::delete( $id );
		self::flash( $ok ? 'success' : 'error', $ok ? __( 'Snippet deleted.', 'scripts-manager-by-mudassar' ) : __( 'Could not delete the snippet.', 'scripts-manager-by-mudassar' ) );
		self::back( self::PAGE_LIST );
	}

	/**
	 * Duplicate.
	 */
	public static function handle_duplicate() {
		MSST_Security::guard( 'duplicate' );
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$result = MSST_Snippets::duplicate( $id );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
			self::back( self::PAGE_LIST );
		}
		self::flash( 'success', __( 'Snippet duplicated (OFF).', 'scripts-manager-by-mudassar' ) );
		self::back( self::PAGE_ADD, array( 'id' => $result ) );
	}

	/**
	 * Bulk actions.
	 */
	public static function handle_bulk() {
		MSST_Security::guard( 'bulk' );
		$action = sanitize_key( self::post_raw( 'bulk_action' ) );
		$done   = 0;
		foreach ( self::post_ids() as $id ) {
			if ( 'activate' === $action ) {
				$done += MSST_Snippets::set_status( $id, true ) ? 1 : 0;
			} elseif ( 'deactivate' === $action ) {
				$done += MSST_Snippets::set_status( $id, false ) ? 1 : 0;
			} elseif ( 'delete' === $action ) {
				$done += MSST_Snippets::delete( $id ) ? 1 : 0;
			}
		}
		/* translators: %d: number of snippets */
		self::flash( 'success', sprintf( _n( '%d snippet updated.', '%d snippets updated.', $done, 'scripts-manager-by-mudassar' ), $done ) );
		self::back( self::PAGE_LIST );
	}

	/**
	 * Export selected snippets.
	 */
	public static function handle_export() {
		MSST_Security::guard( 'export' );
		$ids = self::post_ids();
		if ( ! $ids ) {
			self::flash( 'warning', __( 'Select at least one snippet to export.', 'scripts-manager-by-mudassar' ) );
			self::back( self::PAGE_TOOLS );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="scripts-manager-' . gmdate( 'Y-m-d' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo MSST_Importer::export( $ids ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	/**
	 * Import a JSON file.
	 */
	public static function handle_import() {
		MSST_Security::guard( 'import' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified in guard(); file is validated below.
		$file = isset( $_FILES['msst_file'] ) && is_array( $_FILES['msst_file'] ) ? $_FILES['msst_file'] : array();
		if ( empty( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
			self::flash( 'error', __( 'Please choose a JSON file.', 'scripts-manager-by-mudassar' ) );
			self::back( self::PAGE_TOOLS );
		}
		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		if ( 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || (int) $file['size'] > MSST_Importer::MAX_BYTES ) {
			self::flash( 'error', __( 'Only .json files up to 2 MB are accepted.', 'scripts-manager-by-mudassar' ) );
			self::back( self::PAGE_TOOLS );
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
					__( 'Imported %1$d snippets as OFF. Skipped %2$d. Check each one, then turn it ON.', 'scripts-manager-by-mudassar' ),
					$result['imported'],
					$result['skipped']
				)
			);
		}
		self::back( self::PAGE_TOOLS );
	}

	/**
	 * Save settings.
	 */
	public static function handle_save_settings() {
		MSST_Security::guard( 'save_settings' );
		$values = array();
		foreach ( array_keys( MSST_Settings::defaults() ) as $key ) {
			$values[ $key ] = '1' === self::post_raw( 'msst_' . $key );
		}
		MSST_Settings::save( $values );
		self::flash( 'success', __( 'Settings saved.', 'scripts-manager-by-mudassar' ) );
		self::back( self::PAGE_SETTINGS );
	}

	/**
	 * New safe-mode link.
	 */
	public static function handle_regen_secret() {
		MSST_Security::guard( 'regen_secret' );
		delete_option( 'msst_safe_secret' );
		MSST_Security::ensure_secret();
		self::flash( 'success', __( 'New Safe Mode link created.', 'scripts-manager-by-mudassar' ) );
		self::back( self::PAGE_SETTINGS );
	}

	/**
	 * Personal test mode.
	 */
	public static function handle_test_mode() {
		MSST_Security::guard( 'test_mode' );
		$on = isset( $_GET['to'] ) && '1' === $_GET['to']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		update_user_meta( get_current_user_id(), 'msst_test_mode', $on ? 1 : 0 );
		self::flash( 'success', $on ? __( 'Test view on: no snippets run for you.', 'scripts-manager-by-mudassar' ) : __( 'Test view off.', 'scripts-manager-by-mudassar' ) );
		self::back( self::PAGE_SETTINGS );
	}
}
