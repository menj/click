<?php
/**
 * Activation, upgrades, switch-away, and the reset tool.
 *
 * Switching to another theme hides everything but deletes nothing: the data
 * returns when this theme is reactivated. Only "Delete all theme data" in
 * Diagnostics removes it.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create tables, seed the settings row and schedule housekeeping.
 */
function menj_click_install() {
	menj_click_install_tables();
	menj_click_dir_install_tables();
	if ( false === get_option( 'menj_click_settings', false ) ) {
		add_option( 'menj_click_settings', array(), '', true );
	}
	update_option( 'menj_click_db_version', MENJ_CLICK_DB_VERSION, true );
	menj_click_rebuild_link_map();
	if ( ! wp_next_scheduled( 'menj_click_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'menj_click_daily' );
	}
}

function menj_click_activate() {
	menj_click_install();
	flush_rewrite_rules( false );
}
add_action( 'after_switch_theme', 'menj_click_activate' );

/**
 * Run upgrades when theme files are replaced without reactivation.
 */
function menj_click_maybe_upgrade() {
	if ( (int) get_option( 'menj_click_db_version', 0 ) < MENJ_CLICK_DB_VERSION ) {
		menj_click_install();
	}
}
add_action( 'init', 'menj_click_maybe_upgrade', 5 );

/**
 * Leaving this theme: stop scheduled work, keep all data.
 */
function menj_click_deactivate() {
	wp_clear_scheduled_hook( 'menj_click_daily' );
}
add_action( 'switch_theme', 'menj_click_deactivate' );

/**
 * Remove every table and option this theme created, and all directory
 * listings. Posts and pages are ordinary WordPress content and stay.
 */
function menj_click_delete_all_data() {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . menj_click_clicks_table() ); // phpcs:ignore WordPress.DB
	$wpdb->query( 'DROP TABLE IF EXISTS ' . menj_click_links_table() ); // phpcs:ignore WordPress.DB
	foreach ( array( 'menj_click_settings', 'menj_click_link_map', 'menj_click_db_version', 'menj_click_page_names' ) as $option ) {
		delete_option( $option );
	}
	wp_clear_scheduled_hook( 'menj_click_daily' );
	wp_clear_scheduled_hook( 'menj_click_dir_hourly' );
	wp_cache_flush_group( 'menj_qr' );

	// Directory: listings (with their reviews and meta), categories, tags, tables.
	$wpdb->query( 'DROP TABLE IF EXISTS ' . menj_click_dir_hits_table() ); // phpcs:ignore WordPress.DB
	$wpdb->query( 'DROP TABLE IF EXISTS ' . menj_click_dir_payments_table() ); // phpcs:ignore WordPress.DB
	foreach ( get_posts( array( 'post_type' => 'menj_listing', 'post_status' => menj_click_dir_statuses(), 'fields' => 'ids', 'posts_per_page' => -1 ) ) as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( array( 'menj_dir_category', 'menj_dir_tag' ) as $taxonomy ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
		foreach ( is_array( $terms ) ? $terms : array() as $term_id ) {
			wp_delete_term( $term_id, $taxonomy );
		}
	}
	delete_option( 'menj_click_dir_rewrite' );
	delete_transient( 'menj_click_dir_counts' );
}

/**
 * This theme isn't from WordPress.org. If its folder name matches a theme
 * there (e.g. "click"), WordPress would offer that theme as an "update" and
 * overwrite this one. The Update URI header tells WordPress not to ask;
 * this filter drops any such offer anyway, whatever the folder is called.
 *
 * @param mixed $transient The update_themes transient.
 * @return mixed
 */
function menj_click_block_foreign_updates( $transient ) {
	$slug = basename( MENJ_CLICK_DIR );
	if ( is_object( $transient ) ) {
		if ( isset( $transient->response[ $slug ] ) ) {
			unset( $transient->response[ $slug ] );
		}
		if ( isset( $transient->no_update[ $slug ] ) ) {
			unset( $transient->no_update[ $slug ] );
		}
	}
	return $transient;
}
add_filter( 'site_transient_update_themes', 'menj_click_block_foreign_updates' );
add_filter( 'pre_set_site_transient_update_themes', 'menj_click_block_foreign_updates' );

/**
 * Page names changed so they can't be confused with each other:
 * Links → My Sites, Short URLs / Short Links → Shorts, QR → QR Codes.
 * Existing pages keep their addresses; only titles still at an old default
 * are changed.
 */
function menj_click_rename_pages() {
	if ( get_option( 'menj_click_page_names' ) === '2.1.4' ) {
		return;
	}
	$renames = array(
		'links'      => array( array( 'Links' ), __( 'My Sites', 'menj-click' ) ),
		'short-urls' => array( array( 'Short URLs', 'Short Links' ), __( 'Shorts', 'menj-click' ) ),
		'qr'         => array( array( 'QR' ), __( 'QR Codes', 'menj-click' ) ),
	);
	foreach ( $renames as $slug => $names ) {
		$page = get_page_by_path( $slug );
		if ( $page && in_array( $page->post_title, $names[0], true ) ) {
			wp_update_post( array( 'ID' => $page->ID, 'post_title' => $names[1] ) );
		}
	}
	update_option( 'menj_click_page_names', '2.1.4', true );
}
add_action( 'init', 'menj_click_rename_pages', 30 );
