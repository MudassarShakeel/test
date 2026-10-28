<?php
/**
 * Score card renderer (PHP GD).
 *
 * Every size is written in "u" units, where 1u is 1% of the card width. That is the same
 * unit the admin preview uses, so a card drawn here matches the preview layout.
 * Cards are drawn at 2x and scaled down, which smooths the rounded corners.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Cards {

	const NAVY  = '0A1B44';
	const MINT  = '00E5FF';
	const INK   = '1D1D1F';
	const MUTED = '6E6E73';
	const FILL  = 'F5F5F7';
	const LINE  = 'D9DCE3';
	const WHITE = 'FFFFFF';

	/** Points per device pixel. GD draws text in points at 96 dpi. */
	const K = 0.75;

	private $im;
	private $S = 2;
	private $W;
	private $H;
	private $u;
	private $assets;
	private $colors = array();
	private $cache  = array();

	public function __construct( $assets_dir = null ) {
		$this->assets = $assets_dir ? rtrim( $assets_dir, '/' ) . '/' : SF_DIR . 'assets/';
	}

	/** Card pixel sizes by type. */
	public static function size( $type ) {
		$land = array( 'live', 'toss', 'result', 'fixtures' );
		$sq   = array( 'century', 'fivefor', 'potm', 'poll' );
		if ( in_array( $type, $land, true ) ) {
			return array( 1200, 630 );
		}
		if ( in_array( $type, $sq, true ) ) {
			return array( 1080, 1080 );
		}
		return array( 1080, 1350 );
	}

	/**
	 * Draw a card and save it as a PNG.
	 *
	 * @param string $type One of live, toss, result, summary, digest, fixtures, century, fivefor, potm, poll, table, ranking.
	 * @param array  $d    Card data, see the SF_Engine builders.
	 * @param string $out  Absolute path of the PNG to write.
	 * @return string|WP_Error The path on success.
	 */
	public function render( $type, $d, $out ) {
		if ( ! function_exists( 'imagettftext' ) ) {
			return new WP_Error( 'sf_gd', 'PHP GD with FreeType is required to draw score cards.' );
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' ); // Cron runs with a small memory limit, and tall cards need about 40 MB.
		}
		list( $w, $h ) = self::size( $type );
		$this->W       = $w;
		$this->H       = $h;
		$this->u       = $w / 100;
		$this->colors  = array();
		$this->im      = imagecreatetruecolor( $w * $this->S, $h * $this->S );
		imageantialias( $this->im, true );
		imagefill( $this->im, 0, 0, $this->col( self::WHITE ) );

		switch ( $type ) {
			case 'live':
			case 'toss':
			case 'result':
				$this->card_scoreboard( $type, $d );
				break;
			case 'summary':
				$this->card_summary( $d );
				break;
			case 'digest':
			case 'fixtures':
				$this->card_list( $type, $d );
				break;
			case 'century':
			case 'fivefor':
			case 'potm':
				$this->card_milestone( $type, $d );
				break;
			case 'poll':
				$this->card_poll( $d );
				break;
			case 'table':
			case 'ranking':
				$this->card_table( $type, $d );
				break;
			default:
				imagedestroy( $this->im );
				return new WP_Error( 'sf_card_type', 'Unknown card type: ' . $type );
		}

		$final = imagecreatetruecolor( $w, $h );
		imagecopyresampled( $final, $this->im, 0, 0, 0, 0, $w, $h, $w * $this->S, $h * $this->S );
		imagedestroy( $this->im );
		wp_mkdir_p( dirname( $out ) );
		$ok = imagepng( $final, $out, 6 );
		imagedestroy( $final );
		return $ok ? $out : new WP_Error( 'sf_write', 'Could not write the card image.' );
	}

	/* ------------------------------------------------------------------ */
	/* Drawing helpers. x, y, w, h and sizes are in u units.               */
	/* ------------------------------------------------------------------ */

	private function d( $v ) {
		return (int) round( $v * $this->u * $this->S );
	}

	private function col( $hex ) {
		if ( ! isset( $this->colors[ $hex ] ) ) {
			$this->colors[ $hex ] = imagecolorallocate( $this->im, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
		}
		return $this->colors[ $hex ];
	}

	private function font( $wt ) {
		$map = array( 400 => 'Regular', 500 => 'Medium', 600 => 'SemiBold', 700 => 'Bold', 800 => 'ExtraBold' );
		return $this->assets . 'fonts/Inter-' . $map[ $wt ] . '.otf';
	}

	/** Baseline offset from the top of a line box, for Inter. */
	private function bl( $size, $lh = 1.47 ) {
		return $size * ( ( $lh - 1.21 ) / 2 + 0.969 );
	}

	/** Baseline that vertically centres capitals and digits on $cy. */
	private function cb( $cy, $size ) {
		return $cy + 0.364 * $size;
	}

	private function rrect( $x, $y, $w, $h, $r, $hex ) {
		$c  = $this->col( $hex );
		$X  = $this->d( $x );
		$Y  = $this->d( $y );
		$Wd = $this->d( $w );
		$Hd = $this->d( $h );
		$R  = max( 0, min( $this->d( $r ), (int) ( $Wd / 2 ), (int) ( $Hd / 2 ) ) );
		imagefilledrectangle( $this->im, $X + $R, $Y, $X + $Wd - $R - 1, $Y + $Hd - 1, $c );
		imagefilledrectangle( $this->im, $X, $Y + $R, $X + $Wd - 1, $Y + $Hd - $R - 1, $c );
		$dd = $R * 2;
		if ( $dd > 0 ) {
			imagefilledellipse( $this->im, $X + $R, $Y + $R, $dd, $dd, $c );
			imagefilledellipse( $this->im, $X + $Wd - $R - 1, $Y + $R, $dd, $dd, $c );
			imagefilledellipse( $this->im, $X + $R, $Y + $Hd - $R - 1, $dd, $dd, $c );
			imagefilledellipse( $this->im, $X + $Wd - $R - 1, $Y + $Hd - $R - 1, $dd, $dd, $c );
		}
	}

	private function tw( $s, $size, $wt, $track = 0 ) {
		$s = (string) $s;
		if ( '' === $s ) {
			return 0;
		}
		$pt = $this->d( $size ) * self::K;
		if ( 0 === $track ) {
			$b = imagettfbbox( $pt, 0, $this->font( $wt ), $s );
			return ( $b[2] - $b[0] ) / ( $this->u * $this->S );
		}
		$w = 0;
		foreach ( preg_split( '//u', $s, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
			$w += $this->tw( $ch, $size, $wt ) + $track * $size;
		}
		return $w;
	}

	/**
	 * Draw text. $y is the baseline. $align is l, r or c. $track is letter spacing in em.
	 * Returns the width drawn.
	 */
	private function text( $s, $x, $y, $size, $wt, $hex, $align = 'l', $track = 0 ) {
		$s = (string) $s;
		if ( '' === $s ) {
			return 0;
		}
		$w  = $this->tw( $s, $size, $wt, $track );
		$X  = $x;
		if ( 'r' === $align ) {
			$X = $x - $w;
		} elseif ( 'c' === $align ) {
			$X = $x - $w / 2;
		}
		$pt   = $this->d( $size ) * self::K;
		$font = $this->font( $wt );
		$c    = $this->col( $hex );
		if ( 0 === $track ) {
			imagettftext( $this->im, $pt, 0, $this->d( $X ), $this->d( $y ), $c, $font, $s );
		} else {
			$cx = $X;
			foreach ( preg_split( '//u', $s, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
				imagettftext( $this->im, $pt, 0, $this->d( $cx ), $this->d( $y ), $c, $font, $ch );
				$cx += $this->tw( $ch, $size, $wt ) + $track * $size;
			}
		}
		return $w;
	}

	private function fit( $s, $maxw, $size, $wt ) {
		$s = (string) $s;
		if ( $this->tw( $s, $size, $wt ) <= $maxw ) {
			return $s;
		}
		while ( mb_strlen( $s ) > 1 && $this->tw( $s . '…', $size, $wt ) > $maxw ) {
			$s = mb_substr( $s, 0, -1 );
		}
		return rtrim( $s ) . '…';
	}

	/**
	 * Break segments [text, bold] into lines no wider than $maxw.
	 * Each word comes back as [word, bold, space_before]. Spacing follows the real whitespace in the segments.
	 */
	private function wrap_segs( $segs, $maxw, $size, $wb, $wn ) {
		$lines      = array( array() );
		$lw         = 0;
		$space      = $this->tw( 'a a', $size, $wn ) - $this->tw( 'aa', $size, $wn );
		$prev_space = false;
		foreach ( $segs as $seg ) {
			$text  = (string) $seg[0];
			$bold  = ! empty( $seg[1] );
			$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
			$lead  = (bool) preg_match( '/^\s/', $text );
			foreach ( $words as $i => $word ) {
				$gap  = ( 0 === $i ) ? ( $lead || $prev_space ) : true;
				$ww   = $this->tw( $word, $size, $bold ? $wb : $wn );
				$need = $ww + ( $gap && $lw > 0 ? $space : 0 );
				if ( $lw > 0 && $lw + $need > $maxw ) {
					$lines[] = array();
					$lw      = 0;
					$need    = $ww;
					$gap     = false;
				}
				$lines[ count( $lines ) - 1 ][] = array( $word, $bold, $gap && $lw > 0 );
				$lw                            += $need;
			}
			$prev_space = (bool) preg_match( '/\s$/', $text );
		}
		return $lines;
	}

	private function load( $path ) {
		if ( isset( $this->cache[ $path ] ) ) {
			return $this->cache[ $path ];
		}
		$im   = false;
		$info = @getimagesize( $path );
		if ( $info ) {
			switch ( $info[2] ) {
				case IMAGETYPE_PNG:
					$im = @imagecreatefrompng( $path );
					break;
				case IMAGETYPE_JPEG:
					$im = @imagecreatefromjpeg( $path );
					break;
				case IMAGETYPE_WEBP:
					$im = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false;
					break;
			}
		}
		$this->cache[ $path ] = $im;
		return $im;
	}

	private function paste( $path, $x, $y, $w, $h ) {
		$src = $this->load( $path );
		if ( ! $src ) {
			return false;
		}
		imagealphablending( $this->im, true );
		imagecopyresampled( $this->im, $src, $this->d( $x ), $this->d( $y ), 0, 0, $this->d( $w ), $this->d( $h ), imagesx( $src ), imagesy( $src ) );
		return true;
	}

	/**
	 * Team picture: a flag (rounded), an uploaded logo (fitted) or an initials badge.
	 * $t is ['path' => ?string, 'kind' => flag|logo|badge, 'initials' => string].
	 */
	private function team_pic( $t, $x, $y, $w, $h, $r = 0.9 ) {
		$kind = isset( $t['kind'] ) ? $t['kind'] : 'badge';
		if ( 'flag' === $kind && ! empty( $t['path'] ) && $this->load( $t['path'] ) ) {
			$src = $this->load( $t['path'] );
			$this->rrect( $x - 0.12, $y - 0.12, $w + 0.24, $h + 0.24, $r + 0.1, self::LINE );
			$tw = $this->d( $w );
			$th = $this->d( $h );
			$tmp = imagecreatetruecolor( $tw, $th );
			imagealphablending( $tmp, false );
			imagesavealpha( $tmp, true );
			$clear = imagecolorallocatealpha( $tmp, 0, 0, 0, 127 );
			imagefill( $tmp, 0, 0, $clear );
			imagecopyresampled( $tmp, $src, 0, 0, 0, 0, $tw, $th, imagesx( $src ), imagesy( $src ) );
			$R = min( $this->d( $r ), (int) ( $th / 2 ) );
			for ( $i = 0; $i < $R; $i++ ) {
				for ( $j = 0; $j < $R; $j++ ) {
					if ( ( $R - $i - 0.5 ) ** 2 + ( $R - $j - 0.5 ) ** 2 > $R * $R ) {
						imagesetpixel( $tmp, $i, $j, $clear );
						imagesetpixel( $tmp, $tw - 1 - $i, $j, $clear );
						imagesetpixel( $tmp, $i, $th - 1 - $j, $clear );
						imagesetpixel( $tmp, $tw - 1 - $i, $th - 1 - $j, $clear );
					}
				}
			}
			imagealphablending( $this->im, true );
			imagecopy( $this->im, $tmp, $this->d( $x ), $this->d( $y ), 0, 0, $tw, $th );
			imagedestroy( $tmp );
			return;
		}
		if ( 'logo' === $kind && ! empty( $t['path'] ) && $this->load( $t['path'] ) ) {
			$src   = $this->load( $t['path'] );
			$ratio = imagesx( $src ) / max( 1, imagesy( $src ) );
			$bw    = min( $w, $h * $ratio );
			$bh    = $bw / $ratio;
			$this->paste( $t['path'], $x + ( $w - $bw ) / 2, $y + ( $h - $bh ) / 2, $bw, $bh );
			return;
		}
		$side = min( $w, $h );
		$bx   = $x + ( $w - $side ) / 2;
		$this->rrect( $bx, $y + ( $h - $side ) / 2, $side, $side, $side * 0.22, self::NAVY );
		$ini = isset( $t['initials'] ) ? $t['initials'] : '?';
		$sz  = $side * ( mb_strlen( $ini ) > 2 ? 0.34 : 0.42 );
		$this->text( $ini, $x + $w / 2, $this->cb( $y + $h / 2, $sz ), $sz, 800, self::MINT, 'c' );
	}

	/* ------------------------------------------------------------------ */
	/* Shared card parts                                                   */
	/* ------------------------------------------------------------------ */

	private function head( $pill, $live = false ) {
		$this->paste( $this->assets . 'img/logo-navy.png', 3.4, 2.9, 6.6, 6.6 );
		$this->text( 'Cricket Sport', 11.4, $this->cb( 6.1, 2.5 ), 2.5, 800, self::NAVY );
		$pw = $this->tw( $pill, 1.8, 800 ) + 3.8 + ( $live ? 2.0 : 0 );
		$px = 96 - $pw;
		$this->rrect( $px, 4.3, $pw, 3.6, 1.8, self::MINT );
		$tx = $px + 1.9;
		if ( $live ) {
			$this->rrect( $tx, 5.5, 1.1, 1.1, 0.55, self::NAVY );
			$tx += 2.0;
		}
		$this->text( $pill, $tx, $this->cb( 6.1, 1.8 ), 1.8, 800, self::NAVY );
		return 8.8;
	}

	/** Draw a line of segments [text, bold] and return the baseline-free height in u. */
	private function meta_line( $segs, $top, $size = 1.9 ) {
		$x = 4;
		$b = $top + $this->bl( $size );
		foreach ( $segs as $seg ) {
			$bold = ! empty( $seg[1] );
			$x   += $this->text( $seg[0], $x, $b, $size, $bold ? 800 : 600, $bold ? self::NAVY : self::MUTED );
		}
		return $size * 1.47;
	}

	private function meta_segs( $d ) {
		$segs = array();
		if ( ! empty( $d['tournament'] ) ) {
			$segs[] = array( $d['tournament'], true );
		}
		if ( ! empty( $d['meta'] ) ) {
			$segs[] = array( ( $segs ? ', ' : '' ) . $d['meta'], false );
		}
		return $segs;
	}

	private function foot_top() {
		return $this->H / $this->u - 3.4 - 2.35;
	}

	private function foot( $d ) {
		$left  = ! empty( $d['foot_left'] ) ? $d['foot_left'] : '';
		$right = ! empty( $d['foot_right'] ) ? $d['foot_right'] : '';
		$b     = $this->foot_top() + $this->bl( 1.6 );
		$this->text( $left, 4, $b, 1.6, 600, self::MUTED );
		$this->text( $right, 96, $b, 1.6, 600, self::MUTED, 'r' );
	}

	/** Navy status bar with mint highlights, anchored above the footer. Returns its top. */
	private function status_bar( $segs, $size = 2.5, $padx = 2.4, $pady = 1.5, $r = 1.6, $bottom = null ) {
		$lines  = $this->wrap_segs( $segs, 92 - 2 * $padx, $size, 800, 600 );
		$lh     = $size * 1.25;
		$h      = 2 * $pady + count( $lines ) * $lh;
		$bottom = null === $bottom ? $this->foot_top() - 1.6 : $bottom;
		$y      = $bottom - $h;
		$this->rrect( 4, $y, 92, $h, $r, self::NAVY );
		$space = $this->tw( 'a a', $size, 600 ) - $this->tw( 'aa', $size, 600 );
		foreach ( $lines as $i => $line ) {
			$x = 4 + $padx;
			$b = $y + $pady + $i * $lh + $this->bl( $size, 1.25 );
			foreach ( $line as $w ) {
				if ( $w[2] ) {
					$x += $space;
				}
				$x += $this->text( $w[0], $x, $b, $size, $w[1] ? 800 : 600, $w[1] ? self::MINT : self::WHITE );
			}
		}
		return $y;
	}

	private function small_flag( $t, $x, $y ) {
		if ( isset( $t['kind'] ) && 'flag' === $t['kind'] ) {
			$this->team_pic( $t, $x, $y, 4.2, 3.15, 0.55 );
		} else {
			$this->team_pic( $t, $x, $y - 0.4, 4.2, 3.95, 0.55 );
		}
		return 4.2;
	}

	/* ------------------------------------------------------------------ */
	/* Card layouts                                                        */
	/* ------------------------------------------------------------------ */

	private function score_row( $y, $t, $pad, $name_sz, $score_sz, $w = 92, $x0 = 4 ) {
		$h = 2 * $pad + 6;
		$this->rrect( $x0, $y, $w, $h, 1.6, self::FILL );
		$cy = $y + $h / 2;
		$this->team_pic( $t, $x0 + 2.2, $cy - 2.7, 7.2, 5.4 );
		$right = $x0 + $w - 2.2;
		$used  = 0;
		if ( ! empty( $t['score'] ) ) {
			if ( ! empty( $t['ov'] ) ) {
				$used = $this->text( $t['ov'], $right, $this->cb( $cy, $score_sz ), 2.0, 600, self::MUTED, 'r' ) + 1.2;
			}
			$sw    = $this->text( $t['score'], $right - $used, $this->cb( $cy, $score_sz ), $score_sz, 800, self::NAVY, 'r', -0.03 );
			$used += $sw + 1.2;
		}
		$name = $this->fit( $t['name'], $w - 2.2 - 7.2 - 2 - 2.2 - $used, $name_sz, 700 );
		$this->text( $name, $x0 + 2.2 + 7.2 + 2, $this->cb( $cy, $name_sz ), $name_sz, 700, self::INK );
		return $h;
	}

	private function card_scoreboard( $type, $d ) {
		$pill = array( 'live' => 'LIVE', 'toss' => 'TOSS', 'result' => 'RESULT' );
		$y    = $this->head( $pill[ $type ], 'live' === $type );
		$y   += 2.2;
		$y   += $this->meta_line( $this->meta_segs( $d ), $y );
		$y   += 1.6;
		foreach ( array_slice( $d['teams'], 0, 2 ) as $t ) {
			$y += $this->score_row( $y, $t, 1.2, 3.0, 4.8 ) + 1.2;
		}
		$this->status_bar( $d['status'] );
		$this->foot( $d );
	}

	private function card_summary( $d ) {
		$y  = $this->head( 'FULL TIME' );
		$y += 2.4;
		$this->text( $d['headline'], 4, $y + $this->bl( 5.6, 1.05 ), 5.6, 800, self::NAVY, 'l', -0.02 );
		$y += 5.6 * 1.05 + 1.2;
		$y += $this->meta_line( $this->meta_segs( $d ), $y );
		$y += 1.6;
		foreach ( array_slice( $d['teams'], 0, 2 ) as $t ) {
			$y += $this->score_row( $y, $t, 1.5, 3.3, 5.2 ) + 1.2;
		}
		$y += 1.2;

		// Top performers.
		$pw = ( 92 - 1.6 ) / 2;
		$ph = 2.2 * 2 + 2.0 * 1.47 + 0.6 + 3.2 * 1.3 + 0.5 + 2.0 * 1.47 + 1.4 + 8 + 0.8 + 1.9 * 1.3 * 2;
		foreach ( array( 'bat' => 'Top scorer', 'bowl' => 'Top wicket taker' ) as $i => $label ) {
			$p = isset( $d['perf'][ $i ] ) ? $d['perf'][ $i ] : null;
			$x = 4 + ( 'bat' === $i ? 0 : $pw + 1.6 );
			$this->rrect( $x, $y, $pw, $ph, 2, self::FILL );
			if ( ! $p ) {
				$this->text( $label, $x + 2.4, $y + 2.2 + $this->bl( 2.0 ), 2.0, 700, self::MUTED );
				$this->text( 'Not available', $x + 2.4, $y + 8, 2.4, 600, self::MUTED );
				continue;
			}
			$yy = $y + 2.2;
			$this->text( $label, $x + 2.4, $yy + $this->bl( 2.0 ), 2.0, 700, self::MUTED );
			$yy += 2.0 * 1.47 + 0.6;
			$this->text( $this->fit( $p['name'], $pw - 4.8, 3.2, 800 ), $x + 2.4, $yy + $this->bl( 3.2, 1.3 ), 3.2, 800, self::NAVY, 'l', -0.02 );
			$yy += 3.2 * 1.3 + 0.5;
			$fx  = $x + 2.4;
			$this->small_flag( $p['team'], $fx, $yy + 0.3 );
			$this->text( $p['team']['name'], $fx + 4.2 + 0.9, $this->cb( $yy + 1.05, 2.0 ), 2.0, 600, '3A3A3F' );
			$yy += 2.0 * 1.47 + 1.4;
			$sw  = $this->text( $p['stat'], $x + 2.4, $yy + 6.4, 8.0, 800, self::NAVY, 'l', -0.03 );
			$this->text( $p['small'], $x + 2.4 + $sw + 1.0, $yy + 6.4, 2.4, 600, self::MUTED );
			$yy += 8 + 0.8;
			foreach ( $this->wrap_segs( array( array( $p['detail'], false ) ), $pw - 4.8, 1.9, 600, 600 ) as $li => $line ) {
				$txt = implode( ' ', array_map( function ( $w ) {
					return $w[0];
				}, $line ) );
				$this->text( $txt, $x + 2.4, $yy + $li * 1.9 * 1.3 + $this->bl( 1.9, 1.3 ), 1.9, 600, self::MUTED );
			}
		}
		$y += $ph;

		// Also stood out.
		$y += 2.6;
		$this->text( 'Also stood out', 4, $y + $this->bl( 2.3 ), 2.3, 800, self::NAVY );
		$y += 2.3 * 1.47 + 1.2;
		foreach ( array_slice( $d['also'], 0, 3 ) as $i => $a ) {
			if ( $i > 0 ) {
				imageline( $this->im, $this->d( 4 ), $this->d( $y ), $this->d( 96 ), $this->d( $y ), $this->col( 'E3E5EB' ) );
			}
			$y  += 1.1;
			$cy  = $y + 2.5 * 1.47 / 2;
			$this->small_flag( $a['team'], 4, $cy - 1.5 );
			$this->text( $a['team']['name'], 96, $this->cb( $cy, 2.0 ), 2.0, 600, self::MUTED, 'r' );
			$sx = 96 - 12 - 1.6;
			$this->text( $a['stat'], $sx, $this->cb( $cy, 2.5 ), 2.5, 800, self::NAVY, 'r' );
			$this->text( $this->fit( $a['name'], $sx - 4.2 - 1.6 - 14 - 4, 2.5, 700 ), 4 + 4.2 + 1.6, $this->cb( $cy, 2.5 ), 2.5, 700, self::INK );
			$y += 2.5 * 1.47 + 1.1 + 1.0;
		}

		// Key numbers, anchored above the footer.
		$tiles = array_slice( $d['stats'], 0, 3 );
		$tw    = ( 92 - 2 * 1.6 ) / 3;
		$th    = 8.6;
		$ty    = $this->foot_top() - 1.6 - $th;
		foreach ( $tiles as $i => $tile ) {
			$tx = 4 + $i * ( $tw + 1.6 );
			$this->rrect( $tx, $ty, $tw, $th, 1.8, self::NAVY );
			$this->text( $tile[0], $tx + 2, $ty + 1.7 + $this->bl( 1.7, 1.3 ), 1.7, 600, '9FB0DA' );
			$lines = $this->wrap_segs( $tile[1], $tw - 4, 2.2, 800, 700 );
			foreach ( array_slice( $lines, 0, 2 ) as $li => $line ) {
				$x = $tx + 2;
				$b = $ty + 1.7 + 1.7 * 1.3 + 0.4 + $li * 2.2 * 1.2 + $this->bl( 2.2, 1.2 );
				foreach ( $line as $w ) {
					if ( $w[2] ) {
						$x += $this->tw( 'a a', 2.2, 700 ) - $this->tw( 'aa', 2.2, 700 );
					}
					$x += $this->text( $w[0], $x, $b, 2.2, $w[1] ? 800 : 700, $w[1] ? self::MINT : self::WHITE );
				}
			}
		}
		$this->foot( $d );
	}

	private function card_list( $type, $d ) {
		$tall = 'digest' === $type;
		$y    = $this->head( 'digest' === $type ? 'LIVE' : 'TODAY', 'digest' === $type );
		$y   += 1.2;
		$title = 'digest' === $type ? 'Live scoreboard' : "Today's matches";
		$this->text( $title, 4, $y + $this->bl( 3.6, 1.47 ), 3.6, 800, self::NAVY, 'l', -0.02 );
		$y += 3.6 * 1.47 + 2.2 - 1.0;
		$y += $this->meta_line( $this->meta_segs( $d ), $y ) + 1.6;
		$rows = $d['rows'];
		$max  = $tall ? 9 : 3;
		$pad  = $tall ? 1.7 : 1.2;
		foreach ( array_slice( $rows, 0, $max ) as $r ) {
			$h  = 2 * $pad + 3.7;
			$cy = $y + $h / 2;
			$this->rrect( 4, $y, 92, $h, 1.6, self::FILL );
			$x = 6;
			if ( $tall ) {
				$st = $r['st'];
				if ( 'LIVE' === $st ) {
					$this->rrect( $x, $cy - 1.8, 10, 3.6, 1.8, self::MINT );
					$this->text( 'LIVE', $x + 5, $this->cb( $cy, 1.7 ), 1.7, 800, self::NAVY, 'c' );
				} elseif ( 'FT' === $st ) {
					$this->rrect( $x, $cy - 1.8, 10, 3.6, 1.8, 'E4E4E8' );
					$this->text( 'FULL TIME', $x + 5, $this->cb( $cy, 1.7 ), 1.7, 800, '3A3A3F', 'c' );
				} else {
					$this->text( $st, $x + 5, $this->cb( $cy, 2.1 ), 2.1, 800, self::NAVY, 'c' );
				}
				$x += 10 + 1.6;
			} else {
				$this->text( $r['st'], $x, $this->cb( $cy, 2.6 ), 2.6, 800, self::NAVY );
				$x += 8.5 + 2;
			}
			$rx = 94;
			if ( ! empty( $r['score'] ) ) {
				$ow = 0;
				if ( ! empty( $r['sub'] ) ) {
					$ow = $this->text( $r['sub'], $rx, $this->cb( $cy, 3 ), 2.0, 600, self::MUTED, 'r' ) + 1.2;
				}
				$rx -= $ow + $this->text( $r['score'], $rx - $ow, $this->cb( $cy, 3 ), 3, 800, self::NAVY, 'r', -0.03 );
			} elseif ( ! empty( $r['sub'] ) ) {
				$rx -= $this->text( $this->fit( $r['sub'], 28, 1.8, 600 ), $rx, $this->cb( $cy, 1.8 ), 1.8, 600, self::MUTED, 'r' );
			}
			$avail = $rx - 2 - $x;
			$nsz   = 2.5;
			$a     = $r['a'];
			$b     = $r['b'];
			$vw    = $this->tw( 'v', $nsz, 600 ) + 1.6;
			$flags = 2 * 4.2 + 2 * 1.0;
			$half  = ( $avail - $vw - $flags ) / 2;
			$cx    = $x;
			$this->small_flag( $a, $cx, $cy - 1.5 );
			$cx += 4.2 + 1.0;
			$cx += $this->text( $this->fit( $a['name'], $half, $nsz, 700 ), $cx, $this->cb( $cy, $nsz ), $nsz, 700, self::INK );
			$cx += 0.8;
			$cx += $this->text( 'v', $cx, $this->cb( $cy, $nsz ), $nsz, 600, self::MUTED ) + 0.8;
			$this->small_flag( $b, $cx, $cy - 1.5 );
			$cx += 4.2 + 1.0;
			$this->text( $this->fit( $b['name'], $half, $nsz, 700 ), $cx, $this->cb( $cy, $nsz ), $nsz, 700, self::INK );
			$y += $h + 1.2;
		}
		if ( ! empty( $d['status'] ) ) {
			$this->status_bar( $d['status'] );
		}
		$this->foot( $d );
	}

	private function card_milestone( $type, $d ) {
		$pill = array( 'century' => 'CENTURY', 'fivefor' => 'FIVE-FOR', 'potm' => isset( $d['pill'] ) ? $d['pill'] : 'PLAYER OF THE MATCH' );
		$y    = $this->head( $pill[ $type ] );
		$y   += 2.6;
		$y   += $this->meta_line( $this->meta_segs( $d ), $y, 2.2 );
		$y   += 3;
		$this->rrect( 4, $y, 19, 19, 4, self::NAVY );
		$this->text( $d['mono'], 4 + 9.5, $this->cb( $y + 9.5, 7.4 ), 7.4, 800, self::MINT, 'c', -0.02 );
		$bx  = 26;
		$top = $y + ( 19 - 11.5 ) / 2;
		$this->text( $this->fit( $d['name'], 70, 6.2, 800 ), $bx, $top + $this->bl( 6.2, 1.05 ), 6.2, 800, self::NAVY, 'l', -0.02 );
		$ty = $top + 6.5 + 1.2;
		$this->small_flag( $d['team'], $bx, $ty + 0.5 );
		$this->text( $d['team']['name'], $bx + 4.2 + 1.2, $this->cb( $ty + 1.9, 2.6 ), 2.6, 600, '3A3A3F' );
		$y += 19 + 4;
		$sw = $this->text( $d['stat'], 4, $y + $this->bl( 25, 0.95 ), 25, 800, self::NAVY, 'l', -0.035 );
		$this->text( $d['small'], 4 + $sw + 2, $y + $this->bl( 25, 0.95 ), 3.8, 600, self::MUTED );
		$y += 25 * 0.95 + 3.4;
		$x = 4;
		foreach ( $d['chips'] as $chip ) {
			$cw = $this->tw( $chip, 2.4, 700 ) + 4.8;
			if ( $x + $cw > 96 ) {
				$x  = 4;
				$y += 5.7 + 1.4;
			}
			$this->rrect( $x, $y, $cw, 5.7, 2.85, self::FILL );
			$this->text( $chip, $x + 2.4, $this->cb( $y + 2.85, 2.4 ), 2.4, 700, self::NAVY );
			$x += $cw + 1.4;
		}
		$this->status_bar( $d['status'], 3.0, 3.0, 2.0, 2.0 );
		$this->foot( $d );
	}

	private function card_poll( $d ) {
		$y  = $this->head( 'WHO WINS?' );
		$y += 2.6;
		$y += $this->meta_line( $this->meta_segs( $d ), $y, 2.2 );
		$y += 3;
		$pw = 45;
		$ph = 8 + 21 + 3 + 4.4 * 1.47 + 3 + 5.6 + 8;
		foreach ( array( 0, 1 ) as $i ) {
			$t  = $d['teams'][ $i ];
			$x  = 4 + $i * ( $pw + 2 );
			$cx = $x + $pw / 2;
			$this->rrect( $x, $y, $pw, $ph, 3, self::FILL );
			$yy = $y + 8;
			$this->team_pic( $t, $cx - 14, $yy, 28, 21, 2 );
			$yy += 21 + 3;
			$this->text( $this->fit( $t['name'], $pw - 4, 4.4, 800 ), $cx, $yy + $this->bl( 4.4 ), 4.4, 800, self::NAVY, 'c', -0.02 );
			$yy += 4.4 * 1.47 + 3;
			$icon  = 0 === $i ? 'like' : 'love';
			$word  = 0 === $i ? 'Like' : 'Love';
			$ww    = $this->tw( $word, 2.2, 800 );
			$rest  = $this->tw( ' to vote', 2.2, 700 );
			$pillw = 3.2 + 1.2 + $ww + $rest + 6;
			$px    = $cx - $pillw / 2;
			$this->rrect( $px, $yy, $pillw, 5.6, 2.8, self::NAVY );
			$this->paste( $this->assets . 'img/icon-' . $icon . '.png', $px + 3, $yy + 1.2, 3.2, 3.2 );
			$tx = $px + 3 + 3.2 + 1.2;
			$tx += $this->text( $word, $tx, $this->cb( $yy + 2.8, 2.2 ), 2.2, 800, self::MINT );
			$this->text( ' to vote', $tx, $this->cb( $yy + 2.8, 2.2 ), 2.2, 700, self::WHITE );
		}
		$this->status_bar( $d['status'], 3.0, 3.0, 2.0, 2.0 );
		$this->foot( $d );
	}

	private function card_table( $type, $d ) {
		$y  = $this->head( $d['pill'] );
		$y += 1.2;
		$this->text( $d['title'], 4, $y + $this->bl( 3.6 ), 3.6, 800, self::NAVY, 'l', -0.02 );
		$y += 3.6 * 1.47 + 2.2 - 1.0;
		$y += $this->meta_line( $this->meta_segs( $d ), $y ) + 1.6;
		// Column right edges for the five numeric columns.
		$cols = array( 60, 67, 74, 86, 94 );
		$this->text( 'Team', 13, $y + $this->bl( 1.8 ), 1.8, 700, self::MUTED );
		foreach ( $d['cols'] as $i => $label ) {
			$this->text( $label, $cols[ $i ], $y + $this->bl( 1.8 ), 1.8, 700, self::MUTED, 'r' );
		}
		$y += 1.8 * 1.47 + 0.8;
		foreach ( array_slice( $d['rows'], 0, 8 ) as $i => $r ) {
			$y  += 1.0;
			$h   = 2 * 1.7 + 4.4;
			$cy  = $y + $h / 2;
			$this->rrect( 4, $y, 92, $h, 1.6, self::FILL );
			if ( $i < 4 ) {
				$this->rrect( 6, $cy - 2.2, 4.4, 4.4, 1.0, self::NAVY );
				$this->text( (string) ( $i + 1 ), 8.2, $this->cb( $cy, 2.5 ), 2.5, 800, self::MINT, 'c' );
			} else {
				$this->text( (string) ( $i + 1 ), 8.2, $this->cb( $cy, 2.5 ), 2.5, 800, self::INK, 'c' );
			}
			$this->small_flag( $r['team'], 13, $cy - 1.5 );
			$this->text( $this->fit( $r['team']['name'], 36, 2.5, 700 ), 13 + 4.2 + 1.4, $this->cb( $cy, 2.5 ), 2.5, 700, self::INK );
			foreach ( $r['vals'] as $ci => $v ) {
				$last = ( count( $r['vals'] ) - 1 === $ci );
				$this->text( $v, $cols[ $ci ], $this->cb( $cy, 2.5 ), 2.5, $last ? 800 : 600, $last ? self::NAVY : self::INK, 'r' );
			}
			$y += $h;
		}
		if ( ! empty( $d['status'] ) ) {
			$this->status_bar( $d['status'] );
		}
		$this->foot( $d );
	}

	/**
	 * Re-draw an uploaded picture into a new file, optionally with the Cricket Sport mark.
	 * Re-drawing strips anything hidden inside the file, such as scripts or metadata.
	 * JPEG stays JPEG. Everything else becomes PNG.
	 *
	 * @return string|WP_Error The path written.
	 */
	public function clean_image( $src, $out, $mark = true, $position = 'br' ) {
		$info = @getimagesize( $src ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $info || $info[0] < 1 || $info[1] < 1 ) {
			return new WP_Error( 'sf_img', 'That image could not be read. Use a JPG, PNG or WebP file.' );
		}
		if ( $info[0] > 5000 || $info[1] > 5000 ) {
			return new WP_Error( 'sf_img_big', 'That picture is larger than 5000 pixels on one side. Resize it first.' );
		}
		$im = $this->load( $src );
		if ( ! $im ) {
			return new WP_Error( 'sf_img', 'That image could not be read. Use a JPG, PNG or WebP file.' );
		}
		$w    = imagesx( $im );
		$h    = imagesy( $im );
		$copy = imagecreatetruecolor( $w, $h );
		imagefill( $copy, 0, 0, imagecolorallocate( $copy, 255, 255, 255 ) );
		imagecopy( $copy, $im, 0, 0, 0, 0, $w, $h );
		if ( $mark ) {
			$logo = @imagecreatefrompng( $this->assets . 'img/logo-white.png' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( $logo ) {
				$size = (int) max( 64, round( min( $w, $h ) * 0.14 ) );
				$pad  = (int) round( $size * 0.35 );
				$x    = ( 'bl' === $position || 'tl' === $position ) ? $pad : $w - $size - $pad;
				$y    = ( 'tl' === $position || 'tr' === $position ) ? $pad : $h - $size - $pad;
				imagealphablending( $copy, true );
				imagecopyresampled( $copy, $logo, $x, $y, 0, 0, $size, $size, imagesx( $logo ), imagesy( $logo ) );
				imagedestroy( $logo );
			}
		}
		wp_mkdir_p( dirname( $out ) );
		$jpeg = IMAGETYPE_JPEG === $info[2];
		$ok   = $jpeg ? imagejpeg( $copy, $out, 90 ) : imagepng( $copy, $out, 6 );
		imagedestroy( $copy );
		return $ok ? $out : new WP_Error( 'sf_write', 'Could not save the image.' );
	}

	public static function output_extension( $src ) {
		$info = @getimagesize( $src ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return ( $info && IMAGETYPE_JPEG === $info[2] ) ? 'jpg' : 'png';
	}
}
