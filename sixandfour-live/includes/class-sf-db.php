<?php
/**
 * Database tables: the post queue and finished match results.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_DB {

	const VERSION = '1';

	public static function posts() {
		global $wpdb;
		return $wpdb->prefix . 'sf_posts';
	}

	public static function results() {
		global $wpdb;
		return $wpdb->prefix . 'sf_results';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$posts   = self::posts();
		$results = self::results();

		dbDelta( "CREATE TABLE {$posts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(24) NOT NULL,
			dedupe varchar(120) NOT NULL,
			match_id varchar(64) NOT NULL DEFAULT '',
			series_id varchar(64) NOT NULL DEFAULT '',
			status varchar(16) NOT NULL DEFAULT 'pending',
			caption longtext NOT NULL,
			image varchar(255) NOT NULL DEFAULT '',
			fb_post_id varchar(64) NOT NULL DEFAULT '',
			error text NULL,
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			scheduled_at datetime NULL,
			created_at datetime NOT NULL,
			posted_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dedupe (dedupe),
			KEY status (status),
			KEY match_id (match_id)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$results} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			match_id varchar(64) NOT NULL,
			series_id varchar(64) NOT NULL DEFAULT '',
			series_name varchar(190) NOT NULL DEFAULT '',
			played_on date NOT NULL,
			team_a varchar(120) NOT NULL,
			team_b varchar(120) NOT NULL,
			winner varchar(120) NOT NULL DEFAULT '',
			result_type varchar(8) NOT NULL DEFAULT 'win',
			PRIMARY KEY  (id),
			UNIQUE KEY match_id (match_id),
			KEY played_on (played_on)
		) {$charset};" );

		update_option( 'sf_db_version', self::VERSION, false );
	}

	/** Add a post to the queue. Returns the new id, or 0 when the same event was queued before. */
	public static function add_post( array $row ) {
		global $wpdb;
		$now  = gmdate( 'Y-m-d H:i:s' );
		$data = array_merge(
			array(
				'type'         => '',
				'dedupe'       => '',
				'match_id'     => '',
				'series_id'    => '',
				'status'       => 'pending',
				'caption'      => '',
				'image'        => '',
				'scheduled_at' => null,
				'created_at'   => $now,
			),
			$row
		);
		if ( '' === $data['dedupe'] ) {
			$data['dedupe'] = $data['type'] . ':' . wp_generate_password( 12, false );
		}
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::posts() . ' WHERE dedupe = %s', $data['dedupe'] ) );
		if ( $exists ) {
			return 0;
		}
		$wpdb->insert( self::posts(), $data );
		return (int) $wpdb->insert_id;
	}

	public static function exists( $dedupe ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::posts() . ' WHERE dedupe = %s', $dedupe ) );
	}

	public static function get_post( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::posts() . ' WHERE id = %d', $id ), ARRAY_A );
	}

	public static function update_post( $id, array $data ) {
		global $wpdb;
		return $wpdb->update( self::posts(), $data, array( 'id' => (int) $id ) );
	}

	public static function posts_by_status( $status, $limit = 50 ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::posts() . ' WHERE status = %s ORDER BY id DESC LIMIT %d', $status, $limit ), ARRAY_A );
	}

	public static function recent_posts( $limit = 12 ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::posts() . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A );
	}

	public static function due_posts( $limit = 3 ) {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::posts() . " WHERE status = 'approved' AND ( scheduled_at IS NULL OR scheduled_at <= %s ) ORDER BY id ASC LIMIT %d", $now, $limit ), ARRAY_A );
	}

	public static function count_status( $status ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::posts() . ' WHERE status = %s', $status ) );
	}

	/** Posts sent since a UTC timestamp. Manual posts are not counted against the daily limit. */
	public static function count_posted_since( $ts, $match_id = '' ) {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', $ts );
		if ( '' !== $match_id ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . self::posts() . " WHERE status = 'posted' AND match_id = %s AND posted_at >= %s", $match_id, $since ) );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . self::posts() . " WHERE status = 'posted' AND type <> 'manual' AND posted_at >= %s", $since ) );
	}

	public static function add_result( array $r ) {
		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::results() . ' WHERE match_id = %s', $r['match_id'] ) );
		if ( $exists ) {
			return false;
		}
		return (bool) $wpdb->insert( self::results(), $r );
	}

	public static function results_between( $from, $to ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::results() . ' WHERE played_on >= %s AND played_on <= %s', $from, $to ), ARRAY_A );
	}

	public static function drop() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::posts() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::results() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
