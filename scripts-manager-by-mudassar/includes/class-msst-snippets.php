<?php
/**
 * Snippet storage in its own table (sequential IDs) with signed code.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD and validation for snippets.
 */
class MSST_Snippets {

	const DB_VERSION = '1';
	const MAX_CODE   = 500000; // Bytes.
	const MAX_TARGET = 500;

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'msst_snippets';
	}

	/**
	 * Create or update the table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(200) NOT NULL DEFAULT '',
			type varchar(10) NOT NULL DEFAULT 'html',
			code longtext NOT NULL,
			display_on varchar(32) NOT NULL DEFAULT 'site_wide',
			location varchar(20) NOT NULL DEFAULT 'header',
			device varchar(10) NOT NULL DEFAULT 'all',
			targets longtext NULL,
			status tinyint(1) NOT NULL DEFAULT 0,
			error text NULL,
			sig char(64) NOT NULL DEFAULT '',
			created datetime NOT NULL,
			updated datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
			) {$charset};"
		);
		update_option( 'msst_db_version', self::DB_VERSION, false );
	}

	/**
	 * Install the table when missing (fresh copy or upgrade).
	 */
	public static function maybe_install() {
		if ( get_option( 'msst_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/* ---------------------------------------------------------------- choices */

	/**
	 * Snippet types.
	 *
	 * @return array slug => label
	 */
	public static function types() {
		return array(
			'html' => 'HTML',
			'css'  => 'CSS',
			'js'   => 'JavaScript',
			'php'  => 'PHP',
		);
	}

	/**
	 * Where a snippet shows on the site.
	 *
	 * @return array slug => label
	 */
	public static function display_options() {
		return array(
			'site_wide'  => __( 'Site Wide', 'scripts-manager-by-mudassar' ),
			'posts'      => __( 'Specific Posts', 'scripts-manager-by-mudassar' ),
			'pages'      => __( 'Specific Pages', 'scripts-manager-by-mudassar' ),
			'categories' => __( 'Specific Categories (Archive & Posts)', 'scripts-manager-by-mudassar' ),
			'post_types' => __( 'Specific Post Types (Archive & Posts)', 'scripts-manager-by-mudassar' ),
			'tags'       => __( 'Specific Tags (Archive & Posts)', 'scripts-manager-by-mudassar' ),
			'home'       => __( 'Home Page', 'scripts-manager-by-mudassar' ),
			'search'     => __( 'Search Page', 'scripts-manager-by-mudassar' ),
			'archives'   => __( 'All Archive Pages', 'scripts-manager-by-mudassar' ),
			'latest'     => __( 'Latest Posts', 'scripts-manager-by-mudassar' ),
			'shortcode'  => __( 'Shortcode Only', 'scripts-manager-by-mudassar' ),
		);
	}

	/**
	 * Locations per type.
	 *
	 * @param string $type Snippet type.
	 * @return array slug => label
	 */
	public static function locations( $type = 'html' ) {
		if ( 'php' === $type ) {
			return array(
				'everywhere' => __( 'Everywhere (site and dashboard)', 'scripts-manager-by-mudassar' ),
				'frontend'   => __( 'Only on the public site', 'scripts-manager-by-mudassar' ),
				'admin'      => __( 'Only in the dashboard', 'scripts-manager-by-mudassar' ),
			);
		}
		return array(
			'header'         => __( 'Header', 'scripts-manager-by-mudassar' ),
			'body'           => __( 'Body (after opening tag)', 'scripts-manager-by-mudassar' ),
			'footer'         => __( 'Footer', 'scripts-manager-by-mudassar' ),
			'before_content' => __( 'Before Content', 'scripts-manager-by-mudassar' ),
			'after_content'  => __( 'After Content', 'scripts-manager-by-mudassar' ),
		);
	}

	/**
	 * Device choices.
	 *
	 * @return array slug => label
	 */
	public static function devices() {
		return array(
			'all'     => __( 'Show on All Devices', 'scripts-manager-by-mudassar' ),
			'desktop' => __( 'Only Desktop', 'scripts-manager-by-mudassar' ),
			'mobile'  => __( 'Only Mobile', 'scripts-manager-by-mudassar' ),
		);
	}

	/* ------------------------------------------------------------------- PHP */

	/**
	 * Normalise PHP code: drop opening/closing tags.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function normalize_php( $code ) {
		$code = preg_replace( '/^\s*<\?(?:php)?\s*/i', '', (string) $code );
		$code = preg_replace( '/\s*\?>\s*$/', '', $code );
		return trim( $code );
	}

	/**
	 * Check PHP syntax without executing it.
	 *
	 * @param string $code PHP code without tags.
	 * @return array { ok: bool, error: string, warnings: string[] }
	 */
	public static function lint_php( $code ) {
		$result = array(
			'ok'       => true,
			'error'    => '',
			'warnings' => array(),
		);
		try {
			$tokens = token_get_all( '<?php ' . $code, TOKEN_PARSE );
		} catch ( \Throwable $e ) {
			$result['ok']    = false;
			$result['error'] = sprintf(
				/* translators: 1: error message, 2: line number */
				__( '%1$s (line %2$d)', 'scripts-manager-by-mudassar' ),
				$e->getMessage(),
				max( 1, (int) $e->getLine() )
			);
			return $result;
		}
		$risky = array( 'eval', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'assert', 'unserialize', 'create_function' );
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && ( T_STRING === $token[0] || T_EVAL === $token[0] ) && in_array( strtolower( $token[1] ), $risky, true ) ) {
				$result['warnings'][ $token[1] ] = sprintf(
					/* translators: %s: function name */
					__( 'Uses risky function %s()', 'scripts-manager-by-mudassar' ),
					$token[1]
				);
			}
		}
		$result['warnings'] = array_values( $result['warnings'] );
		return $result;
	}

	/* --------------------------------------------------------------- targets */

	/**
	 * Empty target lists.
	 *
	 * @return array
	 */
	public static function empty_targets() {
		return array(
			'pages'      => array(),
			'posts'      => array(),
			'categories' => array(),
			'post_types' => array(),
			'tags'       => array(),
			'ex_pages'   => array(),
			'ex_posts'   => array(),
		);
	}

	/**
	 * Clean target lists: integer IDs, or post type slugs.
	 *
	 * @param mixed $raw Raw lists.
	 * @return array
	 */
	public static function clean_targets( $raw ) {
		$clean = self::empty_targets();
		if ( ! is_array( $raw ) ) {
			return $clean;
		}
		foreach ( $clean as $key => $unused ) {
			if ( empty( $raw[ $key ] ) || ! is_array( $raw[ $key ] ) ) {
				continue;
			}
			$list = array_slice( $raw[ $key ], 0, self::MAX_TARGET );
			if ( 'post_types' === $key ) {
				$list = array_filter( array_map( 'sanitize_key', array_filter( $list, 'is_scalar' ) ) );
			} else {
				$list = array_filter( array_map( 'absint', array_filter( $list, 'is_scalar' ) ) );
			}
			$clean[ $key ] = array_values( array_unique( $list ) );
		}
		return $clean;
	}

	/* ------------------------------------------------------------------ rows */

	/**
	 * Turn a database row into a clean array.
	 *
	 * @param object|array|null $row Row.
	 * @return array|null
	 */
	private static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$row     = (array) $row;
		$targets = json_decode( isset( $row['targets'] ) ? (string) $row['targets'] : '', true );
		$type    = isset( $row['type'], self::types()[ $row['type'] ] ) ? $row['type'] : 'html';
		return array(
			'id'         => (int) $row['id'],
			'name'       => (string) $row['name'],
			'type'       => $type,
			'code'       => (string) $row['code'],
			'display_on' => isset( self::display_options()[ $row['display_on'] ] ) ? $row['display_on'] : 'site_wide',
			'location'   => (string) $row['location'],
			'device'     => isset( self::devices()[ $row['device'] ] ) ? $row['device'] : 'all',
			'targets'    => self::clean_targets( $targets ),
			'status'     => (bool) $row['status'],
			'error'      => isset( $row['error'] ) ? (string) $row['error'] : '',
			'sig'        => (string) $row['sig'],
		);
	}

	/**
	 * One snippet.
	 *
	 * @param int $id Snippet ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		return self::hydrate( $row );
	}

	/**
	 * Payload that is signed for integrity.
	 *
	 * @param int    $id   Snippet ID.
	 * @param string $type Type.
	 * @param string $code Code.
	 * @return string
	 */
	public static function payload( $id, $type, $code ) {
		return (int) $id . '|' . $type . '|' . $code;
	}

	/**
	 * Does the stored signature match the stored code?
	 *
	 * @param array $snippet Snippet.
	 * @return bool
	 */
	public static function is_intact( array $snippet ) {
		return MSST_Security::verify( self::payload( $snippet['id'], $snippet['type'], $snippet['code'] ), $snippet['sig'] );
	}

	/**
	 * List query for the admin table.
	 *
	 * @param array $args search, type, status (all|active|inactive), orderby, order, paged, per_page.
	 * @return array { items: array[], total: int, pages: int }
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array( self::table() );

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}
		if ( ! empty( $args['type'] ) && isset( self::types()[ $args['type'] ] ) ) {
			$where[]  = 'type = %s';
			$params[] = $args['type'];
		}
		if ( isset( $args['status'] ) && 'active' === $args['status'] ) {
			$where[] = 'status = 1';
		} elseif ( isset( $args['status'] ) && 'inactive' === $args['status'] ) {
			$where[] = 'status = 0';
		}

		$columns   = array(
			'id'       => 'id',
			'name'     => 'name',
			'location' => 'location',
			'type'     => 'type',
		);
		$orderby   = isset( $args['orderby'], $columns[ $args['orderby'] ] ) ? $columns[ $args['orderby'] ] : 'id';
		$order     = isset( $args['order'] ) && 'desc' === strtolower( $args['order'] ) ? 'DESC' : 'ASC';
		$per_page  = isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : 20;
		$paged     = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$where_sql = implode( ' AND ', $where );

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where_sql}", $params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own table; $where_sql is built only from fixed fragments with placeholders.

		$params[] = $per_page;
		$params[] = ( $paged - 1 ) * $per_page;
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$where_sql} ORDER BY {$orderby} {$order}, id ASC LIMIT %d OFFSET %d", $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Own table; column and direction come from fixed whitelists.

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::hydrate( $row );
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Counts for the All | Active | Inactive links.
	 *
	 * @return array
	 */
	public static function counts() {
		global $wpdb;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS c FROM %i GROUP BY status', self::table() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$counts = array(
			'all'      => 0,
			'active'   => 0,
			'inactive' => 0,
		);
		foreach ( (array) $rows as $row ) {
			$key             = (int) $row['status'] ? 'active' : 'inactive';
			$counts[ $key ] += (int) $row['c'];
			$counts['all']  += (int) $row['c'];
		}
		return $counts;
	}

	/**
	 * Active snippets for the current request.
	 *
	 * @return array[]
	 */
	public static function active() {
		$cached = wp_cache_get( 'active', 'msst' );
		if ( false !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = 1 ORDER BY id ASC', self::table() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached just below.
		$list = array();
		foreach ( (array) $rows as $row ) {
			$list[] = self::hydrate( $row );
		}
		wp_cache_set( 'active', $list, 'msst' );
		return $list;
	}

	/**
	 * Drop the active cache.
	 */
	public static function flush_cache() {
		wp_cache_delete( 'active', 'msst' );
	}

	/* ---------------------------------------------------------------- writes */

	/**
	 * Create or update a snippet.
	 *
	 * @param array $data name, type, code, display_on, location, device, targets, status.
	 * @param int   $id   Existing ID or 0.
	 * @return int|WP_Error Snippet ID.
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;
		$id   = (int) $id;
		$type = isset( $data['type'], self::types()[ $data['type'] ] ) ? $data['type'] : 'html';
		$code = (string) $data['code'];

		if ( 'php' === $type ) {
			if ( ! MSST_Security::can_edit_php() ) {
				return new WP_Error( 'msst_forbidden', __( 'You are not allowed to create or edit PHP snippets on this site.', 'scripts-manager-by-mudassar' ) );
			}
			$code = self::normalize_php( $code );
			$lint = self::lint_php( $code );
			if ( ! $lint['ok'] ) {
				return new WP_Error( 'msst_syntax', $lint['error'] );
			}
		} elseif ( ! MSST_Security::can_edit_raw() ) {
			return new WP_Error( 'msst_forbidden', __( 'You are not allowed to save raw code.', 'scripts-manager-by-mudassar' ) );
		}
		if ( strlen( $code ) > self::MAX_CODE ) {
			return new WP_Error( 'msst_too_big', __( 'Snippet is too large.', 'scripts-manager-by-mudassar' ) );
		}
		if ( '' === trim( $code ) ) {
			return new WP_Error( 'msst_empty', __( 'Please add some code.', 'scripts-manager-by-mudassar' ) );
		}

		$display = isset( $data['display_on'], self::display_options()[ $data['display_on'] ] ) ? $data['display_on'] : 'site_wide';
		if ( 'php' === $type && 'shortcode' === $display ) {
			return new WP_Error( 'msst_php_shortcode', __( 'PHP snippets cannot be “Shortcode Only”.', 'scripts-manager-by-mudassar' ) );
		}
		$locations = self::locations( $type );
		$location  = isset( $data['location'], $locations[ $data['location'] ] ) ? $data['location'] : key( $locations );
		$device    = isset( $data['device'], self::devices()[ $data['device'] ] ) ? $data['device'] : 'all';
		$name      = sanitize_text_field( (string) $data['name'] );
		$name      = '' === $name ? __( 'Untitled snippet', 'scripts-manager-by-mudassar' ) : mb_substr( $name, 0, 200 );

		if ( $id ) {
			$old = self::get( $id );
			if ( ! $old ) {
				return new WP_Error( 'msst_missing', __( 'Snippet not found.', 'scripts-manager-by-mudassar' ) );
			}
			if ( 'php' === $old['type'] && ! MSST_Security::can_edit_php() ) {
				return new WP_Error( 'msst_forbidden', __( 'You are not allowed to edit PHP snippets.', 'scripts-manager-by-mudassar' ) );
			}
		}

		$row = array(
			'name'       => $name,
			'type'       => $type,
			'code'       => $code,
			'display_on' => $display,
			'location'   => $location,
			'device'     => $device,
			'targets'    => wp_json_encode( self::clean_targets( isset( $data['targets'] ) ? $data['targets'] : array() ) ),
			'status'     => ! empty( $data['status'] ) ? 1 : 0,
			'error'      => '',
			'updated'    => current_time( 'mysql', true ),
		);

		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		} else {
			$row['created'] = $row['updated'];
			$wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$id = (int) $wpdb->insert_id;
			if ( ! $id ) {
				return new WP_Error( 'msst_db', __( 'Could not save the snippet.', 'scripts-manager-by-mudassar' ) );
			}
		}
		$wpdb->update( self::table(), array( 'sig' => MSST_Security::sign( self::payload( $id, $type, $code ) ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		self::flush_cache();
		return $id;
	}

	/**
	 * Turn a snippet ON or OFF.
	 *
	 * @param int  $id Snippet ID.
	 * @param bool $on New state.
	 * @return bool
	 */
	public static function set_status( $id, $on ) {
		global $wpdb;
		$snippet = self::get( $id );
		if ( ! $snippet ) {
			return false;
		}
		$fields = array( 'status' => $on ? 1 : 0 );
		if ( $on ) {
			if ( 'php' === $snippet['type'] ) {
				if ( ! MSST_Security::can_edit_php() || ! self::lint_php( $snippet['code'] )['ok'] ) {
					return false;
				}
			}
			// Turning ON re-approves the current code.
			$fields['sig']   = MSST_Security::sign( self::payload( $snippet['id'], $snippet['type'], $snippet['code'] ) );
			$fields['error'] = '';
		}
		$wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		self::flush_cache();
		return true;
	}

	/**
	 * Switch a snippet OFF and remember why.
	 *
	 * @param int    $id      Snippet ID.
	 * @param string $message Reason.
	 */
	public static function disable_with_error( $id, $message ) {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			self::table(),
			array(
				'status' => 0,
				'error'  => sanitize_text_field( mb_substr( (string) $message, 0, 500 ) ),
			),
			array( 'id' => (int) $id )
		);
		self::flush_cache();
	}

	/**
	 * Delete a snippet.
	 *
	 * @param int $id Snippet ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$snippet = self::get( $id );
		if ( ! $snippet || ( 'php' === $snippet['type'] && ! MSST_Security::can_edit_php() ) ) {
			return false;
		}
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		self::flush_cache();
		return true;
	}

	/**
	 * Duplicate as an inactive copy.
	 *
	 * @param int $id Snippet ID.
	 * @return int|WP_Error
	 */
	public static function duplicate( $id ) {
		$snippet = self::get( $id );
		if ( ! $snippet ) {
			return new WP_Error( 'msst_missing', __( 'Snippet not found.', 'scripts-manager-by-mudassar' ) );
		}
		$snippet['name']  .= ' ' . __( '(copy)', 'scripts-manager-by-mudassar' );
		$snippet['status'] = false;
		return self::save( $snippet, 0 );
	}
}
