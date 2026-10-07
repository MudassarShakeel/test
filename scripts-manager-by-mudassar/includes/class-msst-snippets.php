<?php
/**
 * Snippet storage (custom post type + signed meta).
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD and validation for snippets.
 */
class MSST_Snippets {

	const POST_TYPE    = 'msst_snippet';
	const MAX_REVISION = 25;
	const MAX_CODE     = 500000; // Bytes.

	/**
	 * Allowed snippet types.
	 *
	 * @return array slug => label
	 */
	public static function types() {
		return array(
			'php'       => 'PHP',
			'js'        => 'JavaScript',
			'css'       => 'CSS',
			'html'      => 'HTML',
			'text'      => 'Text',
			'universal' => 'Universal',
		);
	}

	/**
	 * Plain-language cards for the type picker.
	 *
	 * @return array slug => [ label, description, badge ]
	 */
	public static function kinds() {
		return array(
			'html'      => array( __( 'HTML', 'scripts-manager-by-mudassar' ), __( 'Boxes, banners, text, embeds', 'scripts-manager-by-mudassar' ), 'easy' ),
			'css'       => array( __( 'CSS', 'scripts-manager-by-mudassar' ), __( 'Change colours, sizes, looks', 'scripts-manager-by-mudassar' ), '' ),
			'js'        => array( __( 'JavaScript', 'scripts-manager-by-mudassar' ), __( 'Pixels, chat widgets, popups', 'scripts-manager-by-mudassar' ), '' ),
			'php'       => array( __( 'PHP', 'scripts-manager-by-mudassar' ), __( 'Add features to WordPress', 'scripts-manager-by-mudassar' ), 'advanced' ),
			'text'      => array( __( 'Text', 'scripts-manager-by-mudassar' ), __( 'Plain message with line breaks', 'scripts-manager-by-mudassar' ), '' ),
			'universal' => array( __( 'Universal', 'scripts-manager-by-mudassar' ), __( 'HTML that also allows shortcodes', 'scripts-manager-by-mudassar' ), '' ),
		);
	}

	/**
	 * Allowed locations per type (plain wording).
	 *
	 * @param string $type Snippet type.
	 * @return array slug => label
	 */
	public static function locations( $type ) {
		if ( 'php' === $type ) {
			return array(
				'everywhere' => __( 'Whole site (runs in the background)', 'scripts-manager-by-mudassar' ),
				'admin'      => __( 'Only in the dashboard (wp-admin)', 'scripts-manager-by-mudassar' ),
				'frontend'   => __( 'Only on the public site', 'scripts-manager-by-mudassar' ),
			);
		}
		return array(
			'header'           => __( 'Top of every page (header)', 'scripts-manager-by-mudassar' ),
			'body'             => __( 'Right after the page opens (body)', 'scripts-manager-by-mudassar' ),
			'footer'           => __( 'Bottom of every page (footer)', 'scripts-manager-by-mudassar' ),
			'before_content'   => __( 'Above the post text', 'scripts-manager-by-mudassar' ),
			'after_content'    => __( 'Below the post text', 'scripts-manager-by-mudassar' ),
			'before_paragraph' => __( 'Before a paragraph number…', 'scripts-manager-by-mudassar' ),
			'after_paragraph'  => __( 'After a paragraph number…', 'scripts-manager-by-mudassar' ),
			'admin_footer'     => __( 'Bottom of the dashboard (wp-admin)', 'scripts-manager-by-mudassar' ),
			'shortcode'        => __( 'Only where I put the shortcode', 'scripts-manager-by-mudassar' ),
		);
	}

	/**
	 * Short phrases used in the live "what will happen" sentence.
	 *
	 * @return array location slug => phrase
	 */
	public static function phrases() {
		return array(
			'everywhere'       => __( 'in the background of your whole site', 'scripts-manager-by-mudassar' ),
			'admin'            => __( 'in the background of the dashboard only', 'scripts-manager-by-mudassar' ),
			'frontend'         => __( 'in the background of the public site only', 'scripts-manager-by-mudassar' ),
			'header'           => __( 'at the top of the page (header)', 'scripts-manager-by-mudassar' ),
			'body'             => __( 'right after the page opens', 'scripts-manager-by-mudassar' ),
			'footer'           => __( 'at the bottom of the page (footer)', 'scripts-manager-by-mudassar' ),
			'before_content'   => __( 'above the post text', 'scripts-manager-by-mudassar' ),
			'after_content'    => __( 'below the post text', 'scripts-manager-by-mudassar' ),
			'before_paragraph' => __( 'before the chosen paragraph', 'scripts-manager-by-mudassar' ),
			'after_paragraph'  => __( 'after the chosen paragraph', 'scripts-manager-by-mudassar' ),
			'admin_footer'     => __( 'at the bottom of the dashboard', 'scripts-manager-by-mudassar' ),
			'shortcode'        => __( 'wherever you place its shortcode', 'scripts-manager-by-mudassar' ),
		);
	}

	/**
	 * Register the private post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array( 'name' => __( 'Snippets', 'scripts-manager-by-mudassar' ) ),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Fetch one snippet as an array.
	 *
	 * @param int $id Post ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$type = (string) get_post_meta( $post->ID, '_msst_type', true );
		return array(
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'type'       => isset( self::types()[ $type ] ) ? $type : 'html',
			'code'       => (string) get_post_meta( $post->ID, '_msst_code', true ),
			'location'   => (string) get_post_meta( $post->ID, '_msst_location', true ),
			'param'      => max( 1, (int) get_post_meta( $post->ID, '_msst_param', true ) ),
			'priority'   => (int) get_post_meta( $post->ID, '_msst_priority', true ),
			'active'     => (bool) get_post_meta( $post->ID, '_msst_active', true ),
			'conditions' => MSST_Conditions::clean( get_post_meta( $post->ID, '_msst_conditions', true ) ),
			'start'      => (int) get_post_meta( $post->ID, '_msst_start', true ),
			'end'        => (int) get_post_meta( $post->ID, '_msst_end', true ),
			'sig'        => (string) get_post_meta( $post->ID, '_msst_sig', true ),
			'error'      => (string) get_post_meta( $post->ID, '_msst_error', true ),
		);
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
	 * @param array $snippet Snippet array.
	 * @return bool
	 */
	public static function is_intact( array $snippet ) {
		return MSST_Security::verify( self::payload( $snippet['id'], $snippet['type'], $snippet['code'] ), $snippet['sig'] );
	}

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

	/**
	 * Create or update a snippet.
	 *
	 * @param array $data Validated input (see MSST_Admin::collect_snippet_input).
	 * @param int   $id   Existing ID or 0.
	 * @return int|WP_Error Snippet ID.
	 */
	public static function save( array $data, $id = 0 ) {
		$type = isset( $data['type'], self::types()[ $data['type'] ] ) ? $data['type'] : 'html';
		$code = (string) $data['code'];

		if ( 'php' === $type ) {
			if ( ! MSST_Security::can_edit_php() ) {
				return new WP_Error( 'msst_forbidden', __( 'You are not allowed to create or edit PHP snippets on this site.', 'scripts-manager-by-mudassar' ) );
			}
			$code = self::normalize_php( $code );
		} elseif ( ! MSST_Security::can_edit_raw() ) {
			return new WP_Error( 'msst_forbidden', __( 'You are not allowed to save raw code.', 'scripts-manager-by-mudassar' ) );
		}
		if ( strlen( $code ) > self::MAX_CODE ) {
			return new WP_Error( 'msst_too_big', __( 'Snippet is too large.', 'scripts-manager-by-mudassar' ) );
		}

		$active = ! empty( $data['active'] );
		if ( 'php' === $type ) {
			$lint = self::lint_php( $code );
			if ( ! $lint['ok'] ) {
				return new WP_Error( 'msst_syntax', $lint['error'] );
			}
		}

		$locations = self::locations( $type );
		$location  = isset( $locations[ $data['location'] ] ) ? $data['location'] : key( $locations );

		$id = (int) $id;
		if ( $id ) {
			$old = self::get( $id );
			if ( ! $old ) {
				return new WP_Error( 'msst_missing', __( 'Snippet not found.', 'scripts-manager-by-mudassar' ) );
			}
			if ( 'php' === $old['type'] && ! MSST_Security::can_edit_php() ) {
				return new WP_Error( 'msst_forbidden', __( 'You are not allowed to edit PHP snippets.', 'scripts-manager-by-mudassar' ) );
			}
			if ( $old['code'] !== $code || $old['type'] !== $type ) {
				self::add_revision( $id, $old );
			}
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $data['title'],
				)
			);
		} else {
			$id = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $data['title'],
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
		}

		update_post_meta( $id, '_msst_type', $type );
		update_post_meta( $id, '_msst_code', wp_slash( $code ) );
		update_post_meta( $id, '_msst_location', $location );
		update_post_meta( $id, '_msst_param', max( 1, min( 50, (int) $data['param'] ) ) );
		update_post_meta( $id, '_msst_priority', max( 0, min( 999, (int) $data['priority'] ) ) );
		update_post_meta( $id, '_msst_active', $active ? 1 : 0 );
		update_post_meta( $id, '_msst_conditions', MSST_Conditions::clean( $data['conditions'] ) );
		update_post_meta( $id, '_msst_start', (int) $data['start'] );
		update_post_meta( $id, '_msst_end', (int) $data['end'] );
		update_post_meta( $id, '_msst_sig', MSST_Security::sign( self::payload( $id, $type, $code ) ) );
		delete_post_meta( $id, '_msst_error' );

		self::flush_cache();
		MSST_Logger::audit( sprintf( 'Saved snippet #%d "%s" (%s)', $id, $data['title'], $type ) );
		return (int) $id;
	}

	/**
	 * Toggle active state.
	 *
	 * @param int  $id     Snippet ID.
	 * @param bool $active New state.
	 * @return bool
	 */
	public static function set_active( $id, $active ) {
		$snippet = self::get( $id );
		if ( ! $snippet ) {
			return false;
		}
		if ( $active && 'php' === $snippet['type'] ) {
			if ( ! MSST_Security::can_edit_php() ) {
				return false;
			}
			$lint = self::lint_php( $snippet['code'] );
			if ( ! $lint['ok'] ) {
				return false;
			}
		}
		if ( $active ) {
			// Re-activating re-approves the current content.
			update_post_meta( $id, '_msst_sig', MSST_Security::sign( self::payload( $id, $snippet['type'], $snippet['code'] ) ) );
			delete_post_meta( $id, '_msst_error' );
		}
		update_post_meta( $id, '_msst_active', $active ? 1 : 0 );
		self::flush_cache();
		MSST_Logger::audit( sprintf( '%s snippet #%d "%s"', $active ? 'Activated' : 'Deactivated', $id, $snippet['title'] ) );
		return true;
	}

	/**
	 * Mark a snippet as failed and disable it.
	 *
	 * @param int    $id      Snippet ID.
	 * @param string $message Reason.
	 */
	public static function disable_with_error( $id, $message ) {
		$snippet = self::get( $id );
		if ( ! $snippet ) {
			return;
		}
		update_post_meta( $id, '_msst_active', 0 );
		update_post_meta( $id, '_msst_error', sanitize_text_field( mb_substr( $message, 0, 500 ) ) );
		self::flush_cache();
		MSST_Logger::error( $id, $snippet['title'], $message );
	}

	/**
	 * Delete a snippet permanently.
	 *
	 * @param int $id Snippet ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		$snippet = self::get( $id );
		if ( ! $snippet ) {
			return false;
		}
		if ( 'php' === $snippet['type'] && ! MSST_Security::can_edit_php() ) {
			return false;
		}
		wp_delete_post( (int) $id, true );
		self::flush_cache();
		MSST_Logger::audit( sprintf( 'Deleted snippet #%d "%s"', $id, $snippet['title'] ) );
		return true;
	}

	/**
	 * Duplicate (inactive copy).
	 *
	 * @param int $id Snippet ID.
	 * @return int|WP_Error
	 */
	public static function duplicate( $id ) {
		$snippet = self::get( $id );
		if ( ! $snippet ) {
			return new WP_Error( 'msst_missing', __( 'Snippet not found.', 'scripts-manager-by-mudassar' ) );
		}
		$snippet['title'] .= ' ' . __( '(copy)', 'scripts-manager-by-mudassar' );
		$snippet['active'] = false;
		return self::save( $snippet, 0 );
	}

	/**
	 * Push the previous version to the revision list.
	 *
	 * @param int   $id  Snippet ID.
	 * @param array $old Previous snippet.
	 */
	private static function add_revision( $id, array $old ) {
		$list = self::revisions( $id );
		array_unshift(
			$list,
			array(
				'time' => time(),
				'user' => wp_get_current_user()->user_login,
				'type' => $old['type'],
				'code' => $old['code'],
			)
		);
		update_post_meta( $id, '_msst_revisions', wp_slash( array_slice( $list, 0, self::MAX_REVISION ) ) );
	}

	/**
	 * Revision list, newest first.
	 *
	 * @param int $id Snippet ID.
	 * @return array
	 */
	public static function revisions( $id ) {
		$list = get_post_meta( (int) $id, '_msst_revisions', true );
		return is_array( $list ) ? array_values( $list ) : array();
	}

	/**
	 * Query snippets for the admin list.
	 *
	 * @param array $args search, type, paged, per_page.
	 * @return array { items: array[], total: int, pages: int }
	 */
	public static function query( array $args = array() ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$query    = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		);
		if ( ! empty( $args['search'] ) ) {
			$query['s'] = $args['search'];
		}
		$meta = array();
		if ( ! empty( $args['type'] ) && isset( self::types()[ $args['type'] ] ) ) {
			$meta[] = array(
				'key'   => '_msst_type',
				'value' => $args['type'],
			);
		}
		if ( ! empty( $args['status'] ) ) {
			if ( 'on' === $args['status'] ) {
				$meta[] = array(
					'key'   => '_msst_active',
					'value' => '1',
				);
			} elseif ( 'off' === $args['status'] ) {
				$meta[] = array(
					'key'   => '_msst_active',
					'value' => '0',
				);
			} elseif ( 'problem' === $args['status'] ) {
				$meta[] = array(
					'key'     => '_msst_error',
					'compare' => 'EXISTS',
				);
			}
		}
		if ( $meta ) {
			$query['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small private post type.
		}
		$q     = new WP_Query( $query );
		$items = array();
		foreach ( $q->posts as $post_id ) {
			$items[] = self::get( $post_id );
		}
		return array(
			'items' => array_filter( $items ),
			'total' => (int) $q->found_posts,
			'pages' => (int) $q->max_num_pages,
		);
	}

	/**
	 * Counts for the stats row.
	 *
	 * @return array
	 */
	public static function counts() {
		$all    = self::query( array( 'per_page' => 500 ) );
		$counts = array(
			'total'     => $all['total'],
			'active'    => 0,
			'scheduled' => 0,
			'errors'    => 0,
		);
		foreach ( $all['items'] as $s ) {
			if ( $s['active'] ) {
				++$counts['active'];
			}
			if ( $s['start'] || $s['end'] ) {
				++$counts['scheduled'];
			}
			if ( '' !== $s['error'] ) {
				++$counts['errors'];
			}
		}
		return $counts;
	}

	/**
	 * Active snippets (cached for the request), ordered by priority.
	 *
	 * @return array[]
	 */
	public static function active() {
		$cached = wp_cache_get( 'active', 'msst' );
		if ( false !== $cached ) {
			return $cached;
		}
		$q    = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Small private post type.
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small private post type.
					array(
						'key'   => '_msst_active',
						'value' => '1',
					),
				),
			)
		);
		$list = array();
		foreach ( $q->posts as $post_id ) {
			$snippet = self::get( $post_id );
			if ( $snippet ) {
				$list[] = $snippet;
			}
		}
		usort(
			$list,
			static function ( $a, $b ) {
				if ( $a['priority'] !== $b['priority'] ) {
					return $a['priority'] <=> $b['priority'];
				}
				return $a['id'] <=> $b['id'];
			}
		);
		wp_cache_set( 'active', $list, 'msst' );
		return $list;
	}

	/**
	 * Is the snippet inside its schedule window?
	 *
	 * @param array $snippet Snippet.
	 * @return bool
	 */
	public static function in_schedule( array $snippet ) {
		$now = time();
		if ( $snippet['start'] && $now < $snippet['start'] ) {
			return false;
		}
		if ( $snippet['end'] && $now > $snippet['end'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Drop the active cache.
	 */
	public static function flush_cache() {
		wp_cache_delete( 'active', 'msst' );
	}
}
