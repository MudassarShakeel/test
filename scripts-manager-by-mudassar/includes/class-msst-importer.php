<?php
/**
 * JSON export and import.
 *
 * @package ScriptsManagerByMudassar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Strictly validated import; everything lands inactive.
 */
class MSST_Importer {

	const MAX_BYTES = 2097152;
	const MAX_ITEMS = 500;

	/**
	 * Build the export document.
	 *
	 * @param int[] $ids Snippet IDs to export (empty = all).
	 * @return string JSON.
	 */
	public static function export( array $ids = array() ) {
		$all   = MSST_Snippets::query( array( 'per_page' => 200 ) );
		$items = array();
		foreach ( $all['items'] as $s ) {
			if ( $ids && ! in_array( $s['id'], $ids, true ) ) {
				continue;
			}
			$items[] = array(
				'name'       => $s['name'],
				'type'       => $s['type'],
				'code'       => $s['code'],
				'display_on' => $s['display_on'],
				'location'   => $s['location'],
				'device'     => $s['device'],
				'targets'    => $s['targets'],
				'status'     => $s['status'] ? 1 : 0,
			);
		}
		return wp_json_encode(
			array(
				'plugin'   => MSST_SLUG,
				'version'  => MSST_VERSION,
				'snippets' => $items,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Import from a JSON string.
	 *
	 * @param string $json JSON.
	 * @return array|WP_Error { imported: int, skipped: int }
	 */
	public static function import( $json ) {
		if ( strlen( $json ) > self::MAX_BYTES ) {
			return new WP_Error( 'msst_big', __( 'File is too large.', 'scripts-manager-by-mudassar' ) );
		}
		$data = json_decode( $json, true, 8 );
		if ( ! is_array( $data ) || ! isset( $data['plugin'], $data['snippets'] ) || MSST_SLUG !== $data['plugin'] || ! is_array( $data['snippets'] ) ) {
			return new WP_Error( 'msst_format', __( 'This is not a valid Scripts Manager export file.', 'scripts-manager-by-mudassar' ) );
		}
		$imported = 0;
		$skipped  = 0;
		foreach ( array_slice( $data['snippets'], 0, self::MAX_ITEMS ) as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['name'], $row['type'], $row['code'] ) || ! is_string( $row['name'] ) || ! is_string( $row['type'] ) || ! is_string( $row['code'] ) ) {
				++$skipped;
				continue;
			}
			$result = MSST_Snippets::save(
				array(
					'name'       => $row['name'],
					'type'       => $row['type'],
					'code'       => $row['code'],
					'display_on' => isset( $row['display_on'] ) && is_string( $row['display_on'] ) ? $row['display_on'] : 'site_wide',
					'location'   => isset( $row['location'] ) && is_string( $row['location'] ) ? $row['location'] : '',
					'device'     => isset( $row['device'] ) && is_string( $row['device'] ) ? $row['device'] : 'all',
					'targets'    => isset( $row['targets'] ) ? $row['targets'] : array(),
					'status'     => 0,
				),
				0
			);
			if ( is_wp_error( $result ) ) {
				++$skipped;
			} else {
				++$imported;
			}
		}
		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}
}
