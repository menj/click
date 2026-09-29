<?php
/**
 * Notes (the blog) and the site's page structure.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pages the theme's navigation expects, keyed by slug.
 */
function menj_click_required_pages() {
	return array(
		'home'       => __( 'Home', 'menj-click' ),
		'notes'      => menj_click_settings( 'notes' )['title'],
		'links'      => __( 'My Sites', 'menj-click' ),
		'short-urls' => __( 'Shorts', 'menj-click' ),
		'qr'         => __( 'QR Codes', 'menj-click' ),
	);
}

/**
 * Create any missing pages, make Home the front page and Notes the posts page.
 *
 * @return string[] Titles of pages created.
 */
function menj_click_setup_pages() {
	$created = array();
	$ids     = array();
	foreach ( menj_click_required_pages() as $slug => $title ) {
		$page = get_page_by_path( $slug );
		if ( $page && 'trash' !== $page->post_status ) {
			$ids[ $slug ] = (int) $page->ID;
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => '',
			),
			true
		);
		if ( ! is_wp_error( $id ) ) {
			$ids[ $slug ] = (int) $id;
			$created[]    = $title;
		}
	}
	if ( ! empty( $ids['home'] ) && ! empty( $ids['notes'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
		update_option( 'page_for_posts', $ids['notes'] );
	}
	return $created;
}

/**
 * Put every note under /notes/ so post URLs never compete with short links.
 */
function menj_click_apply_notes_permalinks() {
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '/notes/%postname%/' );
	flush_rewrite_rules( false );
}

/**
 * Status of the Notes setup, for the Notes and Diagnostics tabs.
 *
 * @return array{front: bool, posts_page: bool, permalinks: bool, pages: string[]}
 */
function menj_click_notes_status() {
	$missing = array();
	foreach ( menj_click_required_pages() as $slug => $title ) {
		$page = get_page_by_path( $slug );
		if ( ! $page || 'publish' !== $page->post_status ) {
			$missing[] = $title;
		}
	}
	$posts_page = (int) get_option( 'page_for_posts' );
	return array(
		'front'      => 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) > 0,
		'posts_page' => $posts_page > 0 && 'notes' === get_post_field( 'post_name', $posts_page ),
		'permalinks' => '/notes/%postname%/' === get_option( 'permalink_structure' ),
		'pages'      => $missing,
	);
}

/**
 * Use Sabon for the post editor's canvas, so Notes read the same while you write.
 */
function menj_click_notes_editor_style() {
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && 'post' === $screen->post_type && $screen->is_block_editor() ) {
		wp_enqueue_style( 'menj-click-editor-notes', MENJ_CLICK_URI . '/assets/css/editor-notes.css', array(), MENJ_CLICK_VERSION );
	}
}
add_action( 'enqueue_block_assets', 'menj_click_notes_editor_style' );
