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
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );

		self::run_php_snippets( false );
		add_action( 'wp', array( __CLASS__, 'run_query_php' ), 1 );
	}

	/**
	 * PHP snippets that need the main query.
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
		$is_admin = is_admin();
		foreach ( MSST_Snippets::active() as $snippet ) {
			if ( 'php' !== $snippet['type'] ) {
				continue;
			}
			$location = $snippet['location'];
			if ( ( 'admin' === $location && ! $is_admin ) || ( 'frontend' === $location && $is_admin ) ) {
				continue;
			}
			// Page rules only make sense on the public site; admin PHP always runs from init.
			$needs_query = ! $is_admin && 'admin' !== $location && MSST_Display::needs_query( $snippet );
			if ( $needs_query !== $deferred ) {
				continue;
			}
			if ( ! self::eligible( $snippet ) || ( $needs_query && ! MSST_Display::page_ok( $snippet ) ) ) {
				continue;
			}
			self::run_php( $snippet );
		}
	}

	/**
	 * Integrity and device checks.
	 *
	 * @param array $snippet Snippet.
	 * @return bool
	 */
	private static function eligible( array $snippet ) {
		if ( ! MSST_Snippets::is_intact( $snippet ) ) {
			MSST_Snippets::disable_with_error( $snippet['id'], __( 'Blocked: the stored code changed outside the plugin. Check it, then save or turn it ON again.', 'scripts-manager-by-mudassar' ) );
			return false;
		}
		return MSST_Display::device_ok( $snippet['device'] );
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
			}
		}
		self::$current = 0;
	}

	/**
	 * Detect a fatal error raised inside a snippet and switch it off.
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
	 * Snippets for an output location that should show on this page.
	 *
	 * @param string $location Location slug.
	 * @return array[]
	 */
	private static function for_location( $location ) {
		$out = array();
		foreach ( MSST_Snippets::active() as $snippet ) {
			if ( 'php' === $snippet['type'] || $snippet['location'] !== $location || 'shortcode' === $snippet['display_on'] ) {
				continue;
			}
			if ( self::eligible( $snippet ) && MSST_Display::page_ok( $snippet ) ) {
				$out[] = $snippet;
			}
		}
		return $out;
	}

	/**
	 * Markup for a snippet by type.
	 *
	 * @param array $snippet Snippet.
	 * @return string
	 */
	public static function render( array $snippet ) {
		$code = $snippet['code'];
		if ( 'js' === $snippet['type'] ) {
			return false !== stripos( $code, '<script' ) ? $code : "<script>\n" . $code . "\n</script>";
		}
		if ( 'css' === $snippet['type'] ) {
			return false !== stripos( $code, '<style' ) ? $code : "<style>\n" . $code . "\n</style>";
		}
		return $code;
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
	 * Header output.
	 */
	public static function print_header() {
		self::print_location( 'header' );
	}

	/**
	 * Body output.
	 */
	public static function print_body() {
		self::print_location( 'body' );
	}

	/**
	 * Footer output.
	 */
	public static function print_footer() {
		self::print_location( 'footer' );
	}

	/**
	 * Add snippets before or after post content.
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
		return $before . $content . $after;
	}

	/**
	 * Shortcode: [msst_snippet id="1"]. PHP snippets never run from shortcodes.
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
		if ( ! $snippet || ! $snippet['status'] || 'php' === $snippet['type'] ) {
			return '';
		}
		return self::eligible( $snippet ) ? self::render( $snippet ) : '';
	}
}
