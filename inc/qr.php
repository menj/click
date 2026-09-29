<?php
/**
 * QR codes: settings, cached SVG markup, and the /qr/{slug}.svg|png endpoint.
 *
 * Codes always encode the short link (menj.click/slug?src=qr), never the
 * destination, so a printed code keeps working when the destination changes,
 * and scans show up separately from clicks in the stats.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalised QR style options.
 */
function menj_click_qr_options() {
	$o  = menj_click_settings( 'qr' );
	$fg = sanitize_hex_color( $o['foreground'] );
	$bg = 'transparent' === $o['background'] ? 'transparent' : sanitize_hex_color( $o['background'] );
	return array(
		'foreground' => $fg ? $fg : '#000000',
		'background' => $bg ? $bg : '#FFFFFF',
		'ecc'        => \MenjClick\QR::ecc_from_letter( $o['ecc'] ),
		'margin'     => max( 0, min( 10, (int) $o['margin'] ) ),
	);
}

/**
 * The URL a short link's QR code encodes.
 */
function menj_click_qr_url( $slug ) {
	return home_url( '/' . $slug ) . '?src=qr';
}

/**
 * SVG markup for any text, styled with the QR settings.
 *
 * @param string $text Text to encode.
 * @param array  $args { title, class }.
 * @return string SVG, or '' when the text can't be encoded.
 */
function menj_click_qr_svg( $text, array $args = array() ) {
	$o    = menj_click_qr_options();
	$args = array_merge( array( 'title' => '', 'class' => 'menj-qr-code' ), $args );
	$key  = md5( $text . '|' . wp_json_encode( $o ) . '|' . $args['title'] . '|' . $args['class'] );
	$svg  = wp_cache_get( $key, 'menj_qr' );
	if ( false === $svg ) {
		try {
			$qr = \MenjClick\QR::encode( (string) $text, $o['ecc'] );
		} catch ( \InvalidArgumentException $e ) {
			return '';
		}
		$svg = $qr->to_svg(
			array(
				'margin'     => $o['margin'],
				'foreground' => $o['foreground'],
				'background' => $o['background'],
				'title'      => $args['title'],
				'class'      => $args['class'],
			)
		);
		wp_cache_set( $key, $svg, 'menj_qr', DAY_IN_SECONDS );
	}
	return $svg;
}

/**
 * Output a QR image file and exit.
 *
 * @param string $text     Text to encode.
 * @param string $format   svg|png.
 * @param string $filename Download file name without extension.
 * @param bool   $download Send as an attachment.
 * @param string $title    Accessible SVG title.
 * @return void|false False when the image can't be produced.
 */
function menj_click_send_qr( $text, $format, $filename, $download, $title = '' ) {
	$o = menj_click_qr_options();
	try {
		$qr = \MenjClick\QR::encode( (string) $text, $o['ecc'] );
	} catch ( \InvalidArgumentException $e ) {
		return false;
	}

	if ( 'png' === $format ) {
		$dim  = $qr->get_size() + 2 * $o['margin'];
		$body = $qr->to_png(
			array(
				'scale'      => max( 8, min( 32, intdiv( 1200, max( 1, $dim ) ) ) ),
				'margin'     => $o['margin'],
				'foreground' => $o['foreground'],
				'background' => 'transparent' === $o['background'] ? '#FFFFFF' : $o['background'],
			)
		);
		if ( false === $body ) {
			return false;
		}
		$type = 'image/png';
	} else {
		$body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $qr->to_svg(
			array(
				'margin'     => $o['margin'],
				'foreground' => $o['foreground'],
				'background' => $o['background'],
				'title'      => $title,
			)
		);
		$type = 'image/svg+xml';
	}

	status_header( 200 );
	header( 'Content-Type: ' . $type );
	header( 'Cache-Control: public, max-age=3600' );
	header( 'X-Robots-Tag: noindex', true );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="' . sanitize_file_name( $filename ) . '.' . $format . '"' );
	header( 'Content-Length: ' . strlen( $body ) );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- binary image / generated SVG.
	exit;
}

/**
 * Serve /qr/{slug}.svg|png for active short links. Unknown slugs fall
 * through to WordPress (404), so this can't be used as an open generator.
 */
function menj_click_serve_qr_image( $slug, $format ) {
	$map = menj_click_link_map();
	if ( ! isset( $map[ $slug ] ) ) {
		return;
	}
	$download = isset( $_GET['download'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$name     = str_replace( '.', '-', menj_click_host() ) . '-' . $slug . '-qr';
	menj_click_send_qr( menj_click_qr_url( $slug ), $format, $name, $download, menj_click_host() . '/' . $slug );
}

/**
 * Admin-only: a QR code for any URL.
 */
function menj_click_handle_qr_download() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You don’t have permission to do that.', 'menj-click' ), 403 );
	}
	check_admin_referer( 'menj_click_qr_download' );
	$text   = isset( $_POST['qr_text'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['qr_text'] ) ) ) : '';
	$format = ( isset( $_POST['format'] ) && 'png' === $_POST['format'] ) ? 'png' : 'svg';
	if ( '' === $text || false === menj_click_send_qr( $text, $format, 'qr-' . substr( md5( $text ), 0, 8 ), true, $text ) ) {
		menj_click_admin_flash( 'error', __( 'That text is too long for a QR code, or PNG output isn’t available on this server.', 'menj-click' ) );
		menj_click_admin_redirect( 'qr' );
	}
}
add_action( 'admin_post_menj_click_qr_download', 'menj_click_handle_qr_download' );
