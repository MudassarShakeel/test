<?php
/**
 * Front-end and admin execution of snippets.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides which snippets run where and runs them safely.
 */
class MSST_Runner {

	/**
	 * ID of the PHP snippet currently executing (for fatal error detection).
	 *
	 * @var int
	 */
	private static $current = 0;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'boot' ), 5 );
		add_shortcode( 'msst_snippet', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Attach runtime hooks unless disabled.
	 */
	public static function boot() {
		if ( MSST_Security::safe_mode() || MSST_Security::test_mode() ) {
			return;
		}
		register_shutdown_function( array( __CLASS__, 'on_shutdown' ) );

		add_action( 'wp_head', array( __CLASS__, 'print_header' ), 99 );
		add_action( 'wp_body_open', array( __CLASS__, 'print_body' ), 99 );
		add_action( 'wp_footer', array( __CLASS__, 'print_footer' ), 99 );
		add_action( 'admin_footer', array( __CLASS__, 'print_admin_footer' ), 99 );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );

		self::run_php_snippets( false );
		add_action( 'wp', array( __CLASS__, 'run_query_php' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'run_query_php' ), 1 );
	}

	/**
	 * PHP snippets whose conditions need the main query.
	 */
	public static function run_query_php() {
		self::run_php_snippets( true );
	}

	/**
	 * Run eligible PHP snippets.
	 *
	 * @param bool $deferred Run the group that needs the main query (true) or the rest (false).
	 */
	private static function run_php_snippets( $deferred ) {
		$settings = MSST_Settings::get();
		if ( empty( $settings['php_enabled'] ) ) {
			return;
		}
		foreach ( MSST_Snippets::active() as $snippet ) {
			if ( 'php' !== $snippet['type'] || MSST_Conditions::needs_query( $snippet['conditions'] ) !== $deferred ) {
				continue;
			}
			$admin = is_admin();
			if ( ( 'admin' === $snippet['location'] && ! $admin ) || ( 'frontend' === $snippet['location'] && $admin ) ) {
				continue;
			}
			if ( ! self::eligible( $snippet ) ) {
				continue;
			}
			self::run_php( $snippet );
		}
	}

	/**
	 * Schedule, integrity and condition checks.
	 *
	 * @param array $snippet Snippet.
	 * @return bool
	 */
	private static function eligible( array $snippet ) {
		if ( ! MSST_Snippets::in_schedule( $snippet ) ) {
			return false;
		}
		if ( ! MSST_Snippets::is_intact( $snippet ) ) {
			self::flag_tampered( $snippet );
			return false;
		}
		return MSST_Conditions::passes( $snippet['conditions'] );
	}

	/**
	 * Tampered snippets are disabled until an administrator re-approves them.
	 *
	 * @param array $snippet Snippet.
	 */
	private static function flag_tampered( array $snippet ) {
		MSST_Snippets::disable_with_error( $snippet['id'], __( 'Blocked: the stored code changed outside the plugin. Review it and activate it again.', 'scripts-manager-by-mudassar' ) );
	}

	/**
	 * Execute one PHP snippet in isolation.
	 *
	 * @param array $snippet Snippet.
	 */
	private static function run_php( array $snippet ) {
		self::$current = $snippet['id'];
		try {
			eval( $snippet['code'] ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Core feature; code is administrator-authored, signed and capability-gated.
		} catch ( \Throwable $e ) {
			$settings = MSST_Settings::get();
			if ( ! empty( $settings['auto_disable'] ) ) {
				MSST_Snippets::disable_with_error( $snippet['id'], $e->getMessage() );
			} else {
				MSST_Logger::error( $snippet['id'], $snippet['title'], $e->getMessage() );
			}
		}
		self::$current = 0;
	}

	/**
	 * Detect a fatal error raised inside a snippet and disable it.
	 */
	public static function on_shutdown() {
		if ( ! self::$current ) {
			return;
		}
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
			$settings = MSST_Settings::get();
			if ( ! empty( $settings['auto_disable'] ) ) {
				MSST_Snippets::disable_with_error( self::$current, $error['message'] );
			}
		}
	}

	/**
	 * Snippets for an output location that should render now.
	 *
	 * @param string $location Location slug.
	 * @return array[]
	 */
	private static function for_location( $location ) {
		$out = array();
		foreach ( MSST_Snippets::active() as $snippet ) {
			if ( 'php' === $snippet['type'] || $snippet['location'] !== $location ) {
				continue;
			}
			if ( self::eligible( $snippet ) ) {
				$out[] = $snippet;
			}
		}
		return $out;
	}

	/**
	 * Render a snippet's markup by type.
	 *
	 * @param array $snippet Snippet.
	 * @return string
	 */
	public static function render( array $snippet ) {
		$code = $snippet['code'];
		switch ( $snippet['type'] ) {
			case 'js':
				return false !== stripos( $code, '<script' ) ? $code : "<script>\n" . $code . "\n</script>";
			case 'css':
				return false !== stripos( $code, '<style' ) ? $code : "<style>\n" . $code . "\n</style>";
			case 'text':
				return wpautop( wp_kses_post( $code ) );
			case 'universal':
				return do_shortcode( $code );
			case 'html':
			default:
				return $code;
		}
	}

	/**
	 * Echo snippets for a location.
	 *
	 * @param string $location Location.
	 */
	private static function print_location( $location ) {
		foreach ( self::for_location( $location ) as $snippet ) {
			echo "\n" . self::render( $snippet ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional raw output of signed, administrator-authored code.
		}
	}

	/**
	 * Output the global box and integrations for one slot.
	 *
	 * @param string $slot header|body|footer.
	 */
	private static function print_global( $slot ) {
		if ( is_admin() ) {
			return;
		}
		$global = MSST_Settings::get_global();
		$signed = MSST_Security::verify( 'global|' . $global['header'] . '|' . $global['body'] . '|' . $global['footer'], $global['sig'] );
		echo MSST_Integrations::render( $slot, $global ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from strictly validated IDs.
		if ( $signed && '' !== $global[ $slot ] ) {
			echo "\n" . $global[ $slot ] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional raw output of signed, administrator-authored code.
		}
	}

	/**
	 * Header output.
	 */
	public static function print_header() {
		self::print_global( 'header' );
		self::print_location( 'header' );
	}

	/**
	 * Body output.
	 */
	public static function print_body() {
		self::print_global( 'body' );
		self::print_location( 'body' );
	}

	/**
	 * Footer output.
	 */
	public static function print_footer() {
		self::print_global( 'footer' );
		self::print_location( 'footer' );
	}

	/**
	 * Admin footer output.
	 */
	public static function print_admin_footer() {
		self::print_location( 'admin_footer' );
	}

	/**
	 * Insert snippets around or inside post content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( is_admin() || ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}
		$before = '';
		$after  = '';
		foreach ( self::for_location( 'before_content' ) as $snippet ) {
			$before .= self::render( $snippet );
		}
		foreach ( self::for_location( 'after_content' ) as $snippet ) {
			$after .= self::render( $snippet );
		}
		foreach ( array( 'before_paragraph', 'after_paragraph' ) as $location ) {
			foreach ( self::for_location( $location ) as $snippet ) {
				$content = self::insert_paragraph( $content, self::render( $snippet ), $snippet['param'], 'after_paragraph' === $location );
			}
		}
		return $before . $content . $after;
	}

	/**
	 * Insert markup before/after the Nth paragraph.
	 *
	 * @param string $content Content.
	 * @param string $markup  Markup to insert.
	 * @param int    $n       Paragraph number (1-based).
	 * @param bool   $after   After the paragraph (true) or before it (false).
	 * @return string
	 */
	private static function insert_paragraph( $content, $markup, $n, $after ) {
		$parts = explode( '</p>', $content );
		$last  = count( $parts ) - 1;
		if ( $last < 1 || $n > $last ) {
			return $content;
		}
		$out = '';
		foreach ( $parts as $i => $part ) {
			$piece = $part . ( $i < $last ? '</p>' : '' );
			if ( $i === $n - 1 ) {
				$piece = $after ? $piece . $markup : $markup . $piece;
			}
			$out .= $piece;
		}
		return $out;
	}

	/**
	 * Shortcode: [msst_snippet id="12"]. PHP snippets never run from shortcodes.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		if ( MSST_Security::safe_mode() || MSST_Security::test_mode() ) {
			return '';
		}
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'msst_snippet' );
		$snippet = MSST_Snippets::get( (int) $atts['id'] );
		if ( ! $snippet || ! $snippet['active'] || 'php' === $snippet['type'] || 'shortcode' !== $snippet['location'] ) {
			return '';
		}
		return self::eligible( $snippet ) ? self::render( $snippet ) : '';
	}
}
