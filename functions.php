<?php
/**
 * menj.click — Twenty Twenty-Five child theme.
 *
 * Everything the site does lives here: short links, QR codes, the link
 * hub, the directory, Notes and the settings screen. Switching themes
 * hides it all and deletes nothing; switching back restores it.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

define( 'MENJ_CLICK_VERSION', '2.1.4' );
define( 'MENJ_CLICK_DB_VERSION', 3 );
define( 'MENJ_CLICK_DIR', get_stylesheet_directory() );
define( 'MENJ_CLICK_URI', get_stylesheet_directory_uri() );

require_once MENJ_CLICK_DIR . '/inc/helpers.php';
require_once MENJ_CLICK_DIR . '/inc/class-qr.php';
require_once MENJ_CLICK_DIR . '/inc/short-links.php';
require_once MENJ_CLICK_DIR . '/inc/qr.php';
require_once MENJ_CLICK_DIR . '/inc/lifecycle.php';
require_once MENJ_CLICK_DIR . '/inc/blocks.php';
require_once MENJ_CLICK_DIR . '/inc/notes.php';
require_once MENJ_CLICK_DIR . '/inc/typography.php';
require_once MENJ_CLICK_DIR . '/inc/directory.php';
require_once MENJ_CLICK_DIR . '/inc/directory-submit.php';
require_once MENJ_CLICK_DIR . '/inc/directory-payments.php';
require_once MENJ_CLICK_DIR . '/inc/directory-checks.php';

if ( is_admin() ) {
	require_once MENJ_CLICK_DIR . '/inc/admin.php';
	require_once MENJ_CLICK_DIR . '/inc/diagnostics.php';
	require_once MENJ_CLICK_DIR . '/inc/directory-admin.php';
}

/**
 * Theme supports and text domain.
 */
function menj_click_setup() {
	load_child_theme_textdomain( 'menj-click', MENJ_CLICK_DIR . '/languages' );
	add_theme_support( 'responsive-embeds' );
}
add_action( 'after_setup_theme', 'menj_click_setup' );

/**
 * Quick link to the settings from the admin bar on the front end.
 */
function menj_click_admin_bar_link( $admin_bar ) {
	if ( current_user_can( 'manage_options' ) && ! is_admin() ) {
		$admin_bar->add_node(
			array(
				'id'    => 'menj-click',
				'title' => 'menj.click',
				'href'  => admin_url( 'admin.php?page=menj-click' ),
			)
		);
	}
}
add_action( 'admin_bar_menu', 'menj_click_admin_bar_link', 80 );

/**
 * Keep [menj_short_url] and [menj_qr] from 1.x working in old content.
 */
function menj_click_shortcode_short_url( $atts ) {
	$atts = shortcode_atts( array( 'slug' => '' ), $atts );
	$slug = menj_click_clean_slug( $atts['slug'] );
	if ( '' === $slug ) {
		return '';
	}
	return '<a class="menj-inline-short" href="' . esc_url( home_url( '/' . $slug ) ) . '">' . esc_html( menj_click_host() . '/' . $slug ) . '</a>';
}
add_shortcode( 'menj_short_url', 'menj_click_shortcode_short_url' );

function menj_click_shortcode_qr( $atts ) {
	$atts = shortcode_atts( array( 'slug' => '', 'url' => '', 'size' => 160 ), $atts );
	$slug = menj_click_clean_slug( $atts['slug'] );
	$text = '' !== $slug ? menj_click_qr_url( $slug ) : esc_url_raw( $atts['url'] );
	if ( '' === $text ) {
		return '';
	}
	$size = max( 64, min( 1024, (int) $atts['size'] ) );
	return '<span class="menj-inline-qr" style="--menj-qr-size:' . $size . 'px">' . menj_click_qr_svg( $text ) . '</span>';
}
add_shortcode( 'menj_qr', 'menj_click_shortcode_qr' );
