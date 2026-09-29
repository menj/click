<?php
/**
 * Native QR Code encoder (byte mode, versions 1–40, ECC L/M/Q/H).
 *
 * Algorithm follows ISO/IEC 18004 as implemented in Project Nayuki's
 * QR Code generator (MIT). Outputs SVG (vector, print-ready) or PNG (GD).
 *
 * @package menj-click
 */

namespace MenjClick;

defined( 'ABSPATH' ) || exit;

final class QR {

	const ECC_L = 0;
	const ECC_M = 1;
	const ECC_Q = 2;
	const ECC_H = 3;

	/** Format-information bits for L, M, Q, H. */
	private const FORMAT_BITS = array( 1, 0, 3, 2 );

	private const ECC_CODEWORDS_PER_BLOCK = array(
		array( -1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
		array( -1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28 ),
		array( -1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
		array( -1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
	);

	private const NUM_ERROR_CORRECTION_BLOCKS = array(
		array( -1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25 ),
		array( -1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49 ),
		array( -1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68 ),
		array( -1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81 ),
	);

	/** @var int */
	private $version;
	/** @var int */
	private $size;
	/** @var int */
	private $ecl;
	/** @var int */
	private $mask;
	/** @var array<int, array<int, bool>> */
	private $modules = array();
	/** @var array<int, array<int, bool>> */
	private $is_function = array();

	/**
	 * Map a letter (L, M, Q, H) to an ECC constant.
	 */
	public static function ecc_from_letter( $letter ) {
		$map = array( 'L' => self::ECC_L, 'M' => self::ECC_M, 'Q' => self::ECC_Q, 'H' => self::ECC_H );
		$key = strtoupper( (string) $letter );
		return isset( $map[ $key ] ) ? $map[ $key ] : self::ECC_M;
	}

	/**
	 * Encode text (UTF-8 bytes) as the smallest QR Code that fits.
	 *
	 * @param string $text        Text to encode.
	 * @param int    $ecl         Minimum error-correction level.
	 * @param int    $min_version Smallest version to try (1–40).
	 * @param int    $max_version Largest version allowed (1–40).
	 * @param int    $mask        Mask 0–7, or -1 to choose automatically.
	 * @param bool   $boost       Raise ECC level when it costs no extra size.
	 * @return self
	 * @throws \InvalidArgumentException When arguments are out of range or the text is too long.
	 */
	public static function encode( $text, $ecl = self::ECC_M, $min_version = 1, $max_version = 40, $mask = -1, $boost = true ) {
		if ( $min_version < 1 || $max_version > 40 || $min_version > $max_version || $mask < -1 || $mask > 7 || $ecl < 0 || $ecl > 3 ) {
			throw new \InvalidArgumentException( 'Invalid QR parameters.' );
		}
		$bytes = '' === (string) $text ? array() : array_values( unpack( 'C*', (string) $text ) );
		$len   = count( $bytes );

		for ( $version = $min_version; ; $version++ ) {
			$used = 4 + ( $version <= 9 ? 8 : 16 ) + $len * 8;
			if ( $used <= self::num_data_codewords( $version, $ecl ) * 8 ) {
				break;
			}
			if ( $version >= $max_version ) {
				throw new \InvalidArgumentException( 'Text is too long for a QR Code.' );
			}
		}

		if ( $boost ) {
			foreach ( array( self::ECC_M, self::ECC_Q, self::ECC_H ) as $candidate ) {
				if ( $used <= self::num_data_codewords( $version, $candidate ) * 8 ) {
					$ecl = max( $ecl, $candidate );
				}
			}
		}

		$bits = array();
		self::append_bits( 0x4, 4, $bits );
		self::append_bits( $len, $version <= 9 ? 8 : 16, $bits );
		foreach ( $bytes as $b ) {
			self::append_bits( $b, 8, $bits );
		}

		$capacity = self::num_data_codewords( $version, $ecl ) * 8;
		self::append_bits( 0, min( 4, $capacity - count( $bits ) ), $bits );
		self::append_bits( 0, ( 8 - count( $bits ) % 8 ) % 8, $bits );
		for ( $pad = 0xEC; count( $bits ) < $capacity; $pad ^= 0xEC ^ 0x11 ) {
			self::append_bits( $pad, 8, $bits );
		}

		$data = array_fill( 0, intdiv( count( $bits ), 8 ), 0 );
		foreach ( $bits as $i => $bit ) {
			$data[ $i >> 3 ] |= $bit << ( 7 - ( $i & 7 ) );
		}

		return new self( $version, $ecl, $data, $mask );
	}

	private function __construct( $version, $ecl, array $data, $mask ) {
		$this->version = $version;
		$this->ecl     = $ecl;
		$this->size    = $version * 4 + 17;

		$row               = array_fill( 0, $this->size, false );
		$this->modules     = array_fill( 0, $this->size, $row );
		$this->is_function = array_fill( 0, $this->size, $row );

		$this->draw_function_patterns();
		$this->draw_codewords( $this->add_ecc_and_interleave( $data ) );

		if ( -1 === $mask ) {
			$best = PHP_INT_MAX;
			for ( $i = 0; $i < 8; $i++ ) {
				$this->apply_mask( $i );
				$this->draw_format_bits( $i );
				$penalty = $this->penalty_score();
				if ( $penalty < $best ) {
					$mask = $i;
					$best = $penalty;
				}
				$this->apply_mask( $i ); // XOR undoes the mask.
			}
		}
		$this->mask = $mask;
		$this->apply_mask( $mask );
		$this->draw_format_bits( $mask );
		$this->is_function = array();
	}

	public function get_size() {
		return $this->size;
	}

	public function get_version() {
		return $this->version;
	}

	public function get_mask() {
		return $this->mask;
	}

	public function get_ecc() {
		return $this->ecl;
	}

	public function get_module( $x, $y ) {
		return $x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size && $this->modules[ $y ][ $x ];
	}

	/**
	 * Render as SVG. Dark modules are merged into horizontal runs, so the
	 * path stays compact and prints with crisp edges at any size.
	 *
	 * @param array $args { margin, foreground, background, title, class }.
	 * @return string
	 */
	public function to_svg( array $args = array() ) {
		$args = array_merge(
			array(
				'margin'     => 4,
				'foreground' => '#000000',
				'background' => '#FFFFFF',
				'title'      => '',
				'class'      => '',
			),
			$args
		);

		$m    = max( 0, (int) $args['margin'] );
		$dim  = $this->size + $m * 2;
		$path = '';
		for ( $y = 0; $y < $this->size; $y++ ) {
			$x = 0;
			while ( $x < $this->size ) {
				if ( ! $this->modules[ $y ][ $x ] ) {
					$x++;
					continue;
				}
				$start = $x;
				while ( $x < $this->size && $this->modules[ $y ][ $x ] ) {
					$x++;
				}
				$run   = $x - $start;
				$path .= 'M' . ( $start + $m ) . ' ' . ( $y + $m ) . 'h' . $run . 'v1h-' . $run . 'z';
			}
		}

		$title = (string) $args['title'];
		$attrs = sprintf(
			'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" shape-rendering="crispEdges"%2$s%3$s',
			$dim,
			'' !== $args['class'] ? ' class="' . htmlspecialchars( (string) $args['class'], ENT_QUOTES ) . '"' : '',
			'' !== $title ? ' role="img" aria-label="' . htmlspecialchars( $title, ENT_QUOTES ) . '"' : ' aria-hidden="true"'
		);

		$svg = '<svg ' . $attrs . '>';
		if ( '' !== $title ) {
			$svg .= '<title>' . htmlspecialchars( $title, ENT_QUOTES ) . '</title>';
		}
		if ( '' !== (string) $args['background'] && 'transparent' !== $args['background'] ) {
			$svg .= '<rect width="100%" height="100%" fill="' . htmlspecialchars( (string) $args['background'], ENT_QUOTES ) . '"/>';
		}
		$svg .= '<path fill="' . htmlspecialchars( (string) $args['foreground'], ENT_QUOTES ) . '" d="' . $path . '"/></svg>';

		return $svg;
	}

	/**
	 * Render as PNG bytes (requires the GD extension).
	 *
	 * @param array $args { scale, margin, foreground, background }.
	 * @return string|false PNG bytes, or false when GD is unavailable.
	 */
	public function to_png( array $args = array() ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return false;
		}
		$args  = array_merge( array( 'scale' => 16, 'margin' => 4, 'foreground' => '#000000', 'background' => '#FFFFFF' ), $args );
		$scale = max( 1, min( 64, (int) $args['scale'] ) );
		$m     = max( 0, (int) $args['margin'] );
		$px    = ( $this->size + $m * 2 ) * $scale;
		$img   = imagecreatetruecolor( $px, $px );
		$fg    = self::gd_color( $img, (string) $args['foreground'], array( 0, 0, 0 ) );
		$bg    = self::gd_color( $img, (string) $args['background'], array( 255, 255, 255 ) );
		imagefilledrectangle( $img, 0, 0, $px - 1, $px - 1, $bg );
		for ( $y = 0; $y < $this->size; $y++ ) {
			for ( $x = 0; $x < $this->size; $x++ ) {
				if ( $this->modules[ $y ][ $x ] ) {
					$x0 = ( $x + $m ) * $scale;
					$y0 = ( $y + $m ) * $scale;
					imagefilledrectangle( $img, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $fg );
				}
			}
		}
		ob_start();
		imagepng( $img, null, 9 );
		$png = ob_get_clean();
		imagedestroy( $img );
		return $png;
	}

	private static function gd_color( $img, $hex, array $fallback ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$rgb = ( 6 === strlen( $hex ) && ctype_xdigit( $hex ) )
			? array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) )
			: $fallback;
		return imagecolorallocate( $img, $rgb[0], $rgb[1], $rgb[2] );
	}

	/* ------------------------------------------------------------------ */
	/* Construction internals                                              */
	/* ------------------------------------------------------------------ */

	private function draw_function_patterns() {
		for ( $i = 0; $i < $this->size; $i++ ) {
			$this->set_function_module( 6, $i, 0 === $i % 2 );
			$this->set_function_module( $i, 6, 0 === $i % 2 );
		}

		$this->draw_finder_pattern( 3, 3 );
		$this->draw_finder_pattern( $this->size - 4, 3 );
		$this->draw_finder_pattern( 3, $this->size - 4 );

		$positions = $this->alignment_pattern_positions();
		$count     = count( $positions );
		for ( $i = 0; $i < $count; $i++ ) {
			for ( $j = 0; $j < $count; $j++ ) {
				$corner = ( 0 === $i && 0 === $j ) || ( 0 === $i && $count - 1 === $j ) || ( $count - 1 === $i && 0 === $j );
				if ( ! $corner ) {
					$this->draw_alignment_pattern( $positions[ $i ], $positions[ $j ] );
				}
			}
		}

		$this->draw_format_bits( 0 ); // Placeholder, overwritten after masking.
		$this->draw_version();
	}

	private function draw_format_bits( $mask ) {
		$data = self::FORMAT_BITS[ $this->ecl ] << 3 | $mask;
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( $data << 10 | $rem ) ^ 0x5412;

		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set_function_module( 8, $i, self::bit( $bits, $i ) );
		}
		$this->set_function_module( 8, 7, self::bit( $bits, 6 ) );
		$this->set_function_module( 8, 8, self::bit( $bits, 7 ) );
		$this->set_function_module( 7, 8, self::bit( $bits, 8 ) );
		for ( $i = 9; $i < 15; $i++ ) {
			$this->set_function_module( 14 - $i, 8, self::bit( $bits, $i ) );
		}

		for ( $i = 0; $i < 8; $i++ ) {
			$this->set_function_module( $this->size - 1 - $i, 8, self::bit( $bits, $i ) );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$this->set_function_module( 8, $this->size - 15 + $i, self::bit( $bits, $i ) );
		}
		$this->set_function_module( 8, $this->size - 8, true ); // Always dark.
	}

	private function draw_version() {
		if ( $this->version < 7 ) {
			return;
		}
		$rem = $this->version;
		for ( $i = 0; $i < 12; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
		}
		$bits = $this->version << 12 | $rem;
		for ( $i = 0; $i < 18; $i++ ) {
			$bit = self::bit( $bits, $i );
			$a   = $this->size - 11 + $i % 3;
			$b   = intdiv( $i, 3 );
			$this->set_function_module( $a, $b, $bit );
			$this->set_function_module( $b, $a, $bit );
		}
	}

	private function draw_finder_pattern( $x, $y ) {
		for ( $dy = -4; $dy <= 4; $dy++ ) {
			for ( $dx = -4; $dx <= 4; $dx++ ) {
				$dist = max( abs( $dx ), abs( $dy ) );
				$xx   = $x + $dx;
				$yy   = $y + $dy;
				if ( $xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size ) {
					$this->set_function_module( $xx, $yy, 2 !== $dist && 4 !== $dist );
				}
			}
		}
	}

	private function draw_alignment_pattern( $x, $y ) {
		for ( $dy = -2; $dy <= 2; $dy++ ) {
			for ( $dx = -2; $dx <= 2; $dx++ ) {
				$this->set_function_module( $x + $dx, $y + $dy, 1 !== max( abs( $dx ), abs( $dy ) ) );
			}
		}
	}

	private function set_function_module( $x, $y, $dark ) {
		$this->modules[ $y ][ $x ]     = (bool) $dark;
		$this->is_function[ $y ][ $x ] = true;
	}

	private function alignment_pattern_positions() {
		if ( 1 === $this->version ) {
			return array();
		}
		$count  = intdiv( $this->version, 7 ) + 2;
		$step   = intdiv( $this->version * 8 + $count * 3 + 5, $count * 4 - 4 ) * 2;
		$result = array();
		for ( $i = 0; $i < $count - 1; $i++ ) {
			$result[] = $this->size - 7 - $i * $step;
		}
		$result[] = 6;
		return array_reverse( $result );
	}

	private function add_ecc_and_interleave( array $data ) {
		$num_blocks      = self::NUM_ERROR_CORRECTION_BLOCKS[ $this->ecl ][ $this->version ];
		$block_ecc_len   = self::ECC_CODEWORDS_PER_BLOCK[ $this->ecl ][ $this->version ];
		$raw_codewords   = intdiv( self::num_raw_data_modules( $this->version ), 8 );
		$num_short       = $num_blocks - $raw_codewords % $num_blocks;
		$short_block_len = intdiv( $raw_codewords, $num_blocks );
		$divisor         = self::rs_divisor( $block_ecc_len );

		$blocks = array();
		$k      = 0;
		for ( $i = 0; $i < $num_blocks; $i++ ) {
			$len = $short_block_len - $block_ecc_len + ( $i < $num_short ? 0 : 1 );
			$dat = array_slice( $data, $k, $len );
			$k  += $len;
			$ecc = self::rs_remainder( $dat, $divisor );
			if ( $i < $num_short ) {
				$dat[] = 0;
			}
			$blocks[] = array_merge( $dat, $ecc );
		}

		$result = array();
		$width  = count( $blocks[0] );
		for ( $i = 0; $i < $width; $i++ ) {
			foreach ( $blocks as $j => $block ) {
				if ( $i !== $short_block_len - $block_ecc_len || $j >= $num_short ) {
					$result[] = $block[ $i ];
				}
			}
		}
		return $result;
	}

	private function draw_codewords( array $data ) {
		$total = count( $data ) * 8;
		$i     = 0;
		for ( $right = $this->size - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $vert = 0; $vert < $this->size; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x      = $right - $j;
					$upward = 0 === ( ( $right + 1 ) & 2 );
					$y      = $upward ? $this->size - 1 - $vert : $vert;
					if ( ! $this->is_function[ $y ][ $x ] && $i < $total ) {
						$this->modules[ $y ][ $x ] = self::bit( $data[ $i >> 3 ], 7 - ( $i & 7 ) );
						$i++;
					}
				}
			}
		}
	}

	private function apply_mask( $mask ) {
		for ( $y = 0; $y < $this->size; $y++ ) {
			for ( $x = 0; $x < $this->size; $x++ ) {
				switch ( $mask ) {
					case 0:
						$invert = 0 === ( $x + $y ) % 2;
						break;
					case 1:
						$invert = 0 === $y % 2;
						break;
					case 2:
						$invert = 0 === $x % 3;
						break;
					case 3:
						$invert = 0 === ( $x + $y ) % 3;
						break;
					case 4:
						$invert = 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2;
						break;
					case 5:
						$invert = 0 === $x * $y % 2 + $x * $y % 3;
						break;
					case 6:
						$invert = 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2;
						break;
					default:
						$invert = 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
				}
				if ( $invert && ! $this->is_function[ $y ][ $x ] ) {
					$this->modules[ $y ][ $x ] = ! $this->modules[ $y ][ $x ];
				}
			}
		}
	}

	private function penalty_score() {
		$size   = $this->size;
		$result = 0;

		for ( $pass = 0; $pass < 2; $pass++ ) {
			for ( $a = 0; $a < $size; $a++ ) {
				$run_color = false;
				$run_len   = 0;
				$history   = array_fill( 0, 7, 0 );
				for ( $b = 0; $b < $size; $b++ ) {
					$cell = 0 === $pass ? $this->modules[ $a ][ $b ] : $this->modules[ $b ][ $a ];
					if ( $cell === $run_color ) {
						$run_len++;
						if ( 5 === $run_len ) {
							$result += 3;
						} elseif ( $run_len > 5 ) {
							$result++;
						}
					} else {
						$this->finder_add_history( $run_len, $history );
						if ( ! $run_color ) {
							$result += $this->finder_count_patterns( $history ) * 40;
						}
						$run_color = $cell;
						$run_len   = 1;
					}
				}
				$result += $this->finder_terminate_and_count( $run_color, $run_len, $history ) * 40;
			}
		}

		for ( $y = 0; $y < $size - 1; $y++ ) {
			for ( $x = 0; $x < $size - 1; $x++ ) {
				$c = $this->modules[ $y ][ $x ];
				if ( $c === $this->modules[ $y ][ $x + 1 ] && $c === $this->modules[ $y + 1 ][ $x ] && $c === $this->modules[ $y + 1 ][ $x + 1 ] ) {
					$result += 3;
				}
			}
		}

		$dark = 0;
		foreach ( $this->modules as $row ) {
			foreach ( $row as $cell ) {
				$dark += $cell ? 1 : 0;
			}
		}
		$total   = $size * $size;
		$k       = intdiv( abs( $dark * 20 - $total * 10 ) + $total - 1, $total ) - 1;
		$result += $k * 10;

		return $result;
	}

	private function finder_count_patterns( array $h ) {
		$n    = $h[1];
		$core = $n > 0 && $h[2] === $n && $h[3] === $n * 3 && $h[4] === $n && $h[5] === $n;
		return ( $core && $h[0] >= $n * 4 && $h[6] >= $n ? 1 : 0 ) + ( $core && $h[6] >= $n * 4 && $h[0] >= $n ? 1 : 0 );
	}

	private function finder_terminate_and_count( $run_color, $run_len, array &$history ) {
		if ( $run_color ) {
			$this->finder_add_history( $run_len, $history );
			$run_len = 0;
		}
		$run_len += $this->size;
		$this->finder_add_history( $run_len, $history );
		return $this->finder_count_patterns( $history );
	}

	private function finder_add_history( $run_len, array &$history ) {
		if ( 0 === $history[0] ) {
			$run_len += $this->size;
		}
		array_pop( $history );
		array_unshift( $history, $run_len );
	}

	/* ------------------------------------------------------------------ */
	/* Static helpers                                                      */
	/* ------------------------------------------------------------------ */

	private static function num_raw_data_modules( $version ) {
		$result = ( 16 * $version + 128 ) * $version + 64;
		if ( $version >= 2 ) {
			$align   = intdiv( $version, 7 ) + 2;
			$result -= ( 25 * $align - 10 ) * $align - 55;
			if ( $version >= 7 ) {
				$result -= 36;
			}
		}
		return $result;
	}

	private static function num_data_codewords( $version, $ecl ) {
		return intdiv( self::num_raw_data_modules( $version ), 8 )
			- self::ECC_CODEWORDS_PER_BLOCK[ $ecl ][ $version ] * self::NUM_ERROR_CORRECTION_BLOCKS[ $ecl ][ $version ];
	}

	private static function rs_divisor( $degree ) {
		$result             = array_fill( 0, $degree, 0 );
		$result[ $degree - 1 ] = 1;
		$root               = 1;
		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$result[ $j ] = self::rs_multiply( $result[ $j ], $root );
				if ( $j + 1 < $degree ) {
					$result[ $j ] ^= $result[ $j + 1 ];
				}
			}
			$root = self::rs_multiply( $root, 0x02 );
		}
		return $result;
	}

	private static function rs_remainder( array $data, array $divisor ) {
		$result = array_fill( 0, count( $divisor ), 0 );
		foreach ( $data as $b ) {
			$factor = $b ^ array_shift( $result );
			$result[] = 0;
			foreach ( $divisor as $i => $coef ) {
				$result[ $i ] ^= self::rs_multiply( $coef, $factor );
			}
		}
		return $result;
	}

	private static function rs_multiply( $x, $y ) {
		$z = 0;
		for ( $i = 7; $i >= 0; $i-- ) {
			$z  = ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D );
			$z ^= ( ( $y >> $i ) & 1 ) * $x;
		}
		return $z;
	}

	private static function append_bits( $value, $count, array &$bits ) {
		for ( $i = $count - 1; $i >= 0; $i-- ) {
			$bits[] = ( $value >> $i ) & 1;
		}
	}

	private static function bit( $x, $i ) {
		return 0 !== ( ( $x >> $i ) & 1 );
	}
}
