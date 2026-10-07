<?php
/**
 * Tiny line diff (longest common subsequence).
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Line diff.
 */
class MSST_Diff {

	/**
	 * Diff two strings line by line.
	 *
	 * @param string $old Old text.
	 * @param string $updated New text.
	 * @return array[] Each: op (same|add|del), text.
	 */
	public static function lines( $old, $updated ) {
		$a = array_slice( preg_split( '/\r\n|\r|\n/', (string) $old ), 0, 1000 );
		$b = array_slice( preg_split( '/\r\n|\r|\n/', (string) $updated ), 0, 1000 );
		$n = count( $a );
		$m = count( $b );

		$lcs = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );
		for ( $i = $n - 1; $i >= 0; $i-- ) {
			for ( $j = $m - 1; $j >= 0; $j-- ) {
				$lcs[ $i ][ $j ] = $a[ $i ] === $b[ $j ] ? $lcs[ $i + 1 ][ $j + 1 ] + 1 : max( $lcs[ $i + 1 ][ $j ], $lcs[ $i ][ $j + 1 ] );
			}
		}

		$out = array();
		$i   = 0;
		$j   = 0;
		while ( $i < $n && $j < $m ) {
			if ( $a[ $i ] === $b[ $j ] ) {
				$out[] = array(
					'op'   => 'same',
					'text' => $a[ $i ],
				);
				++$i;
				++$j;
			} elseif ( $lcs[ $i + 1 ][ $j ] >= $lcs[ $i ][ $j + 1 ] ) {
				$out[] = array(
					'op'   => 'del',
					'text' => $a[ $i++ ],
				);
			} else {
				$out[] = array(
					'op'   => 'add',
					'text' => $b[ $j++ ],
				);
			}
		}
		while ( $i < $n ) {
			$out[] = array(
				'op'   => 'del',
				'text' => $a[ $i++ ],
			);
		}
		while ( $j < $m ) {
			$out[] = array(
				'op'   => 'add',
				'text' => $b[ $j++ ],
			);
		}
		return $out;
	}
}
