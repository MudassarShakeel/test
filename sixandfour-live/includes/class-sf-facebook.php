<?php
/**
 * Facebook Page posting through the Graph API.
 *
 * The Page access token is read from the plugin settings and is never printed.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Facebook {

	/** Graph API version. Change it with the sf_graph_version filter. */
	const DEFAULT_VERSION = 'v21.0';

	private static function base() {
		return apply_filters( 'sf_graph_base', 'https://graph.facebook.com/' ) . apply_filters( 'sf_graph_version', self::DEFAULT_VERSION ) . '/';
	}

	private static function creds() {
		$o = SF_Options::all();
		if ( '' === $o['fb_page_id'] || '' === $o['fb_token'] ) {
			return new WP_Error( 'sf_fb_setup', 'Add the Facebook Page ID and access token in Setup.' );
		}
		return array( $o['fb_page_id'], $o['fb_token'] );
	}

	private static function handle( $res ) {
		if ( is_wp_error( $res ) ) {
			return new WP_Error( $res->get_error_code(), SF_Secrets::scrub( $res->get_error_message() ) );
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $body['error'] ) ) {
			$e   = $body['error'];
			$msg = isset( $e['message'] ) ? $e['message'] : 'Facebook returned an error.';
			$num = isset( $e['code'] ) ? (int) $e['code'] : 0;
			if ( 190 === $num ) {
				update_option( 'sf_fb_status', array( 'ok' => false, 'message' => 'The Page access token has expired or was revoked. Create a new one in Setup.', 'at' => time() ), false );
			}
			return new WP_Error( 'sf_fb_' . $num, SF_Secrets::scrub( $msg ) );
		}
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			return new WP_Error( 'sf_fb_http', 'Facebook answered with status ' . $code . '.' );
		}
		return $body;
	}

	/** Check the token and return the Page name. */
	public static function verify() {
		$c = self::creds();
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$res = null;
		// Ask for the follower count too, but fall back to the name alone if this token may not read it.
		foreach ( array( 'name,fan_count', 'name' ) as $fields ) {
			$url = add_query_arg(
				array(
					'fields'       => $fields,
					'access_token' => $c[1],
				),
				self::base() . rawurlencode( $c[0] )
			);
			$res = self::handle( wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 0, 'sslverify' => true ) ) );
			if ( ! is_wp_error( $res ) || 'sf_fb_100' !== $res->get_error_code() ) {
				break;
			}
		}
		if ( is_wp_error( $res ) ) {
			update_option( 'sf_fb_status', array( 'ok' => false, 'message' => $res->get_error_message(), 'at' => time() ), false );
			return $res;
		}
		update_option(
			'sf_fb_status',
			array(
				'ok'      => true,
				'message' => 'Connected',
				'name'    => isset( $res['name'] ) ? $res['name'] : '',
				'handle'  => '',
				'fans'    => isset( $res['fan_count'] ) ? (int) $res['fan_count'] : 0,
				'at'      => time(),
			),
			false
		);
		return $res;
	}

	/** Post a picture with a caption. Returns the Facebook post id. */
	public static function photo( $path, $caption ) {
		$c = self::creds();
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'sf_fb_file', 'The picture file is missing.' );
		}
		$caption  = self::clean( $caption );
		$boundary = 'sf' . wp_generate_password( 20, false );
		$fields   = array(
			'caption'      => $caption,
			'published'    => 'true',
			'access_token' => $c[1],
		);
		$body = '';
		foreach ( $fields as $k => $v ) {
			$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
		}
		$mime  = wp_check_filetype( $path );
		$type  = ! empty( $mime['type'] ) ? $mime['type'] : 'image/png';
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"source\"; filename=\"" . basename( $path ) . "\"\r\nContent-Type: {$type}\r\n\r\n" . file_get_contents( $path ) . "\r\n--{$boundary}--\r\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$res   = self::handle(
			wp_remote_post(
				self::base() . rawurlencode( $c[0] ) . '/photos',
				array(
					'timeout' => 60,
					'redirection' => 0,
					'sslverify' => true,
					'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
					'body'    => $body,
				)
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return isset( $res['post_id'] ) ? $res['post_id'] : ( isset( $res['id'] ) ? $res['id'] : '' );
	}

	/** Facebook allows about 63,000 characters. Stay well under it. */
	private static function clean( $text ) {
		return mb_substr( (string) $text, 0, 5000 );
	}

	/** Post text only. */
	public static function feed( $message ) {
		$message = self::clean( $message );
		$c       = self::creds();
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$res = self::handle(
			wp_remote_post(
				self::base() . rawurlencode( $c[0] ) . '/feed',
				array(
					'timeout' => 30,
					'redirection' => 0,
					'sslverify' => true,
					'body'    => array(
						'message'      => $message,
						'access_token' => $c[1],
					),
				)
			)
		);
		return is_wp_error( $res ) ? $res : ( isset( $res['id'] ) ? $res['id'] : '' );
	}

	/** Change the text of a post you already made. */
	public static function edit( $post_id, $message ) {
		$message = self::clean( $message );
		$c       = self::creds();
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$res = self::handle(
			wp_remote_post(
				self::base() . rawurlencode( $post_id ),
				array(
					'timeout' => 30,
					'redirection' => 0,
					'sslverify' => true,
					'body'    => array(
						'message'      => $message,
						'access_token' => $c[1],
					),
				)
			)
		);
		return is_wp_error( $res ) ? $res : true;
	}
}
