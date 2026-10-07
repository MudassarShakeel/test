<?php
/**
 * Local snippet library and generators. Nothing is fetched from the internet.
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Built-in snippets.
 */
class MSST_Library {

	/**
	 * Library entries.
	 *
	 * @return array key => [ title, type, location, code ]
	 */
	public static function items() {
		return array(
			'disable_xmlrpc'    => array(
				'title'    => 'Disable XML-RPC',
				'type'     => 'php',
				'location' => 'everywhere',
				'code'     => "add_filter( 'xmlrpc_enabled', '__return_false' );",
			),
			'svg_uploads'       => array(
				'title'    => 'Allow SVG uploads (administrators only)',
				'type'     => 'php',
				'location' => 'everywhere',
				'code'     => "add_filter( 'upload_mimes', function ( \$mimes ) {\n\tif ( current_user_can( 'manage_options' ) ) {\n\t\t\$mimes['svg'] = 'image/svg+xml';\n\t}\n\treturn \$mimes;\n} );",
			),
			'disable_gutenberg' => array(
				'title'    => 'Disable block editor for posts',
				'type'     => 'php',
				'location' => 'admin',
				'code'     => "add_filter( 'use_block_editor_for_post', '__return_false' );",
			),
			'hide_admin_bar'    => array(
				'title'    => 'Hide admin bar for non-administrators',
				'type'     => 'php',
				'location' => 'everywhere',
				'code'     => "add_filter( 'show_admin_bar', function ( \$show ) {\n\treturn current_user_can( 'manage_options' ) ? \$show : false;\n} );",
			),
			'disable_emojis'    => array(
				'title'    => 'Disable emoji scripts',
				'type'     => 'php',
				'location' => 'frontend',
				'code'     => "remove_action( 'wp_head', 'print_emoji_detection_script', 7 );\nremove_action( 'wp_print_styles', 'print_emoji_styles' );",
			),
			'smooth_scroll'     => array(
				'title'    => 'Smooth scrolling',
				'type'     => 'css',
				'location' => 'header',
				'code'     => 'html { scroll-behavior: smooth; }',
			),
			'cookie_bar'        => array(
				'title'    => 'Simple cookie notice',
				'type'     => 'html',
				'location' => 'footer',
				'code'     => '<div style="position:fixed;bottom:0;left:0;right:0;padding:12px;background:#1d1d1f;color:#fff;text-align:center;z-index:9999">This site uses cookies. <a href="/privacy-policy/" style="color:#2997ff">Learn more</a></div>',
			),
		);
	}

	/**
	 * Generator catalogue.
	 *
	 * @return array
	 */
	public static function generators() {
		return array(
			'cpt'       => __( 'Custom post type', 'mudassar-snippet-studio' ),
			'shortcode' => __( 'Hello-world shortcode', 'mudassar-snippet-studio' ),
		);
	}

	/**
	 * Build generated PHP from validated input only.
	 *
	 * @param string $kind Generator key.
	 * @param string $name Human name.
	 * @param string $slug Slug.
	 * @return array|null { title, code } or null on invalid input.
	 */
	public static function generate( $kind, $name, $slug ) {
		$name = sanitize_text_field( $name );
		$slug = sanitize_key( $slug );
		if ( '' === $name || '' === $slug || strlen( $slug ) > 20 ) {
			return null;
		}
		$n = var_export( $name, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Safe literal builder.
		$s = var_export( $slug, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		if ( 'cpt' === $kind ) {
			return array(
				'title' => 'Custom post type: ' . $name,
				'code'  => "add_action( 'init', function () {\n\tregister_post_type( {$s}, array(\n\t\t'label'        => {$n},\n\t\t'public'       => true,\n\t\t'show_in_rest' => true,\n\t\t'supports'     => array( 'title', 'editor', 'thumbnail' ),\n\t) );\n} );",
			);
		}
		if ( 'shortcode' === $kind ) {
			return array(
				'title' => 'Shortcode: ' . $slug,
				'code'  => "add_shortcode( {$s}, function () {\n\treturn esc_html( {$n} );\n} );",
			);
		}
		return null;
	}
}
