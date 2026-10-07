<?php
/**
 * JSON import and export.
 *
 * @package MudassarSnippetStudio
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
	 * @return string JSON.
	 */
	public static function export() {
		$items = array();
		$all   = MSST_Snippets::query( array( 'per_page' => self::MAX_ITEMS ) );
		foreach ( $all['items'] as $s ) {
			$items[] = array(
				'title'      => $s['title'],
				'type'       => $s['type'],
				'code'       => $s['code'],
				'location'   => $s['location'],
				'param'      => $s['param'],
				'priority'   => $s['priority'],
				'conditions' => $s['conditions'],
				'start'      => $s['start'],
				'end'        => $s['end'],
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
			return new WP_Error( 'msst_big', __( 'File is too large.', 'mudassar-snippet-studio' ) );
		}
		$data = json_decode( $json, true, 8 );
		if ( ! is_array( $data ) || ! isset( $data['plugin'], $data['snippets'] ) || MSST_SLUG !== $data['plugin'] || ! is_array( $data['snippets'] ) ) {
			return new WP_Error( 'msst_format', __( 'This is not a valid Snippet Studio export file.', 'mudassar-snippet-studio' ) );
		}
		$imported = 0;
		$skipped  = 0;
		foreach ( array_slice( $data['snippets'], 0, self::MAX_ITEMS ) as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['title'], $row['type'], $row['code'] ) || ! is_string( $row['code'] ) || ! is_string( $row['title'] ) || ! is_string( $row['type'] ) ) {
				++$skipped;
				continue;
			}
			$result = MSST_Snippets::save(
				array(
					'title'      => sanitize_text_field( $row['title'] ),
					'type'       => $row['type'],
					'code'       => $row['code'],
					'location'   => isset( $row['location'] ) && is_string( $row['location'] ) ? $row['location'] : '',
					'param'      => isset( $row['param'] ) ? (int) $row['param'] : 1,
					'priority'   => isset( $row['priority'] ) ? (int) $row['priority'] : 10,
					'active'     => false,
					'conditions' => isset( $row['conditions'] ) ? $row['conditions'] : array(),
					'start'      => isset( $row['start'] ) ? absint( $row['start'] ) : 0,
					'end'        => isset( $row['end'] ) ? absint( $row['end'] ) : 0,
				),
				0
			);
			if ( is_wp_error( $result ) ) {
				++$skipped;
			} else {
				++$imported;
			}
		}
		MSST_Logger::audit( sprintf( 'Imported %d snippets (%d skipped)', $imported, $skipped ) );
		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}
}
