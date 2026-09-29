<?php
/**
 * Diagnostics: health checks with one-click fixes, settings backup, reset.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/**
 * Short-link slugs the site points at (hub, examples, QR cards, navigation).
 *
 * @return array slug => where it's used
 */
function menj_click_referenced_slugs() {
	$refs = array();
	foreach ( menj_click_settings( 'hub' ) as $item ) {
		$link = isset( $item['link'] ) ? (string) $item['link'] : '';
		if ( preg_match( '#^/([a-z0-9][a-z0-9_-]{0,63})$#', $link, $m ) ) {
			$refs[ $m[1] ][] = __( 'link panel', 'menj-click' );
		}
	}
	foreach ( menj_click_settings( 'featured' ) as $row ) {
		$refs[ $row['slug'] ][] = __( 'home page examples', 'menj-click' );
	}
	foreach ( menj_click_settings( 'qr' )['cards'] as $card ) {
		$refs[ $card['slug'] ][] = __( 'QR codes', 'menj-click' );
	}
	$header = get_block_template( get_stylesheet() . '//header', 'wp_template_part' );
	if ( $header && preg_match_all( '#"url":"/([a-z0-9][a-z0-9_-]{0,63})"#', (string) $header->content, $m ) ) {
		foreach ( $m[1] as $slug ) {
			$refs[ $slug ][] = __( 'navigation', 'menj-click' );
		}
	}
	return array_map( 'array_unique', $refs );
}

/**
 * Run every check.
 *
 * @return array[] { label, status: ok|warn|bad, detail, fix, fix_label }
 */
function menj_click_diagnostics() {
	global $wpdb;
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$checks = array();

	$tables_ok = menj_click_links_ready()
		&& menj_click_links_table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', menj_click_links_table() ) )
		&& menj_click_clicks_table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', menj_click_clicks_table() ) );
	$checks[]  = array(
		'label'     => __( 'Short-link tables', 'menj-click' ),
		'status'    => $tables_ok ? 'ok' : 'bad',
		'detail'    => $tables_ok ? __( 'Installed.', 'menj-click' ) : __( 'Missing. Short links can’t be stored or followed.', 'menj-click' ),
		'fix'       => $tables_ok ? '' : 'tables',
		'fix_label' => __( 'Repair tables', 'menj-click' ),
	);

	$notes    = menj_click_notes_status();
	$pages_ok = $notes['front'] && $notes['posts_page'] && ! $notes['pages'];
	$checks[] = array(
		'label'     => __( 'Pages', 'menj-click' ),
		'status'    => $pages_ok ? 'ok' : 'warn',
		'detail'    => $pages_ok ? __( 'Home is the front page, Notes lists your posts, and the My Sites, Shorts and QR Codes pages exist.', 'menj-click' ) : __( 'Some pages or reading settings are missing, so parts of the navigation lead nowhere.', 'menj-click' ),
		'fix'       => $pages_ok ? '' : 'pages',
		'fix_label' => __( 'Create pages', 'menj-click' ),
	);

	$checks[] = array(
		'label'     => __( 'Post URLs', 'menj-click' ),
		'status'    => $notes['permalinks'] ? 'ok' : 'warn',
		'detail'    => $notes['permalinks'] ? __( 'Posts live under /notes/, clear of short links.', 'menj-click' ) : sprintf( /* translators: %s: permalink structure */ __( 'Current structure is “%s”. Posts should live under /notes/ so they never compete with short links.', 'menj-click' ), get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : __( 'plain', 'menj-click' ) ),
		'fix'       => $notes['permalinks'] ? '' : 'permalinks',
		'fix_label' => __( 'Use /notes/%postname%/', 'menj-click' ),
	);

	$map     = menj_click_link_map();
	$missing = array();
	foreach ( menj_click_referenced_slugs() as $slug => $where ) {
		if ( ! isset( $map[ $slug ] ) ) {
			$missing[] = '/' . $slug . ' (' . implode( ', ', $where ) . ')';
		}
	}
	$checks[] = array(
		'label'     => __( 'Links used on the site', 'menj-click' ),
		'status'    => $missing ? 'warn' : 'ok',
		'detail'    => $missing ? __( 'These aren’t active short links yet. Visitors don’t see them in lists, but links in the panel and navigation lead to a 404:', 'menj-click' ) : __( 'Every short link the site points at is active.', 'menj-click' ),
		'list'      => $missing,
		'fix'       => $missing ? 'starters' : '',
		'fix_label' => __( 'Add starter links', 'menj-click' ),
	);

	$clashes = array();
	if ( $tables_ok ) {
		$clashes = $wpdb->get_col( 'SELECT l.slug FROM ' . menj_click_links_table() . " l JOIN {$wpdb->posts} p ON p.post_name = l.slug AND p.post_parent = 0 AND p.post_type = 'page' AND p.post_status = 'publish'" ); // phpcs:ignore WordPress.DB
	}
	$checks[] = array(
		'label'  => __( 'Short links vs pages', 'menj-click' ),
		'status' => $clashes ? 'warn' : 'ok',
		'detail' => $clashes ? __( 'These pages share a slug with a short link; the short link wins, so the page can’t be reached:', 'menj-click' ) : __( 'No clashes.', 'menj-click' ),
		'list'   => array_map(
			function ( $s ) {
				return '/' . $s . '/';
			},
			$clashes
		),
	);

	if ( is_plugin_active( 'url-shortify/url-shortify.php' ) ) {
		$checks[] = array(
			'label'     => 'URL Shortify',
			'status'    => 'warn',
			'detail'    => __( 'Still active. Both answer the same URLs (this theme answers first). Import its links, then deactivate it.', 'menj-click' ),
			'fix'       => 'import-shortify',
			'fix_label' => __( 'Import its links', 'menj-click' ),
		);
	}
	if ( is_plugin_active( 'qr-code-composer/qrc_composer.php' ) ) {
		$checks[] = array(
			'label'  => 'QR Code Composer',
			'status' => 'warn',
			'detail' => __( 'Still active but no longer needed: the theme draws QR codes itself. It loads a stylesheet on every page, so deactivate it.', 'menj-click' ),
		);
	}

	$checks[] = array(
		'label'  => __( 'PNG downloads', 'menj-click' ),
		'status' => function_exists( 'imagecreatetruecolor' ) ? 'ok' : 'warn',
		'detail' => function_exists( 'imagecreatetruecolor' ) ? __( 'The GD extension is available.', 'menj-click' ) : __( 'The server has no GD extension, so QR codes download as SVG only. Ask your host to enable php-gd.', 'menj-click' ),
	);

	$next     = wp_next_scheduled( 'menj_click_daily' );
	$checks[] = array(
		'label'     => __( 'Daily clean-up', 'menj-click' ),
		'status'    => $next ? 'ok' : 'warn',
		'detail'    => $next ? __( 'Scheduled. Old click details are trimmed to your retention setting.', 'menj-click' ) : __( 'Not scheduled.', 'menj-click' ),
		'fix'       => $next ? '' : 'cron',
		'fix_label' => __( 'Schedule it', 'menj-click' ),
	);

	$missing_fonts = array();
	$json          = json_decode( (string) file_get_contents( MENJ_CLICK_DIR . '/theme.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$font_count    = 0;
	foreach ( isset( $json['settings']['typography']['fontFamilies'] ) ? $json['settings']['typography']['fontFamilies'] : array() as $family ) {
		foreach ( isset( $family['fontFace'] ) ? $family['fontFace'] : array() as $font_face ) {
			foreach ( (array) $font_face['src'] as $src ) {
				$relative = ltrim( str_replace( 'file:./', '', $src ), '/' );
				$font_count++;
				if ( ! file_exists( get_theme_file_path( $relative ) ) ) {
					$missing_fonts[] = $relative;
				}
			}
		}
	}
	$checks[] = array(
		'label'  => __( 'Font files', 'menj-click' ),
		'status' => $missing_fonts ? 'bad' : 'ok',
		/* translators: %d: number of files */
		'detail' => $missing_fonts ? __( 'These font files are missing, so their text falls back to system fonts:', 'menj-click' ) : sprintf( __( 'All %d font files are in place.', 'menj-click' ), $font_count ),
		'list'   => array_unique( $missing_fonts ),
	);

	if ( menj_click_dir_enabled() ) {
		$dir_ok   = menj_click_dir_hits_table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', menj_click_dir_hits_table() ) )
			&& menj_click_dir_payments_table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', menj_click_dir_payments_table() ) );
		$checks[] = array(
			'label'     => __( 'Directory tables', 'menj-click' ),
			'status'    => $dir_ok ? 'ok' : 'bad',
			'detail'    => $dir_ok ? __( 'Installed.', 'menj-click' ) : __( 'Missing. Visits and payments can’t be recorded.', 'menj-click' ),
			'fix'       => $dir_ok ? '' : 'tables',
			'fix_label' => __( 'Repair tables', 'menj-click' ),
		);
		$hourly   = wp_next_scheduled( 'menj_click_dir_hourly' );
		$checks[] = array(
			'label'     => __( 'Directory checks', 'menj-click' ),
			'status'    => $hourly ? 'ok' : 'warn',
			'detail'    => $hourly ? __( 'Link and link-back checks run hourly in small batches.', 'menj-click' ) : __( 'Not scheduled, so listed sites aren’t being checked.', 'menj-click' ),
			'fix'       => $hourly ? '' : 'cron',
			'fix_label' => __( 'Schedule it', 'menj-click' ),
		);
		if ( menj_click_dir( 'paypal_enabled' ) ) {
			$paypal_ok = (bool) is_email( menj_click_dir( 'paypal_email' ) );
			$checks[]  = array(
				'label'  => 'PayPal',
				'status' => $paypal_ok ? ( menj_click_dir( 'paypal_sandbox' ) ? 'warn' : 'ok' ) : 'bad',
				'detail' => $paypal_ok ? ( menj_click_dir( 'paypal_sandbox' ) ? __( 'Sandbox mode is on: payments are test payments.', 'menj-click' ) : __( 'Taking live payments.', 'menj-click' ) ) : __( 'PayPal is on but has no account email, so owners see invoice instructions instead.', 'menj-click' ),
			);
		}
		$pending = (int) wp_count_posts( 'menj_listing' )->pending;
		if ( $pending ) {
			$checks[] = array(
				'label'  => __( 'Directory queue', 'menj-click' ),
				'status' => 'warn',
				/* translators: %d: number of listings */
				'detail' => sprintf( _n( '%d listing is waiting for review or payment.', '%d listings are waiting for review or payment.', $pending, 'menj-click' ), $pending ),
			);
		}
	}

	return $checks;
}

function menj_click_tab_diagnostics() {
	$checks = menj_click_diagnostics();
	menj_click_card_open( __( 'Health', 'menj-click' ) );
	echo '<ul class="menj-health">';
	foreach ( $checks as $c ) {
		echo '<li class="menj-health__item is-' . esc_attr( $c['status'] ) . '"><span class="menj-health__mark" aria-hidden="true"></span><div class="menj-health__body">';
		echo '<strong>' . esc_html( $c['label'] ) . '</strong><p>' . esc_html( $c['detail'] ) . '</p>';
		if ( ! empty( $c['list'] ) ) {
			echo '<ul class="menj-health__list">';
			foreach ( $c['list'] as $line ) {
				echo '<li><code>' . esc_html( $line ) . '</code></li>';
			}
			echo '</ul>';
		}
		echo '</div>';
		if ( ! empty( $c['fix'] ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			menj_click_form_fields( 'menj_click_setup' );
			echo '<input type="hidden" name="task" value="' . esc_attr( $c['fix'] ) . '"><button type="submit" class="button">' . esc_html( $c['fix_label'] ) . '</button></form>';
		}
		echo '</li>';
	}
	echo '</ul>';
	menj_click_card_close();

	menj_click_card_open( __( 'Back up settings', 'menj-click' ), __( 'Everything on these tabs as one JSON file. Short links export separately, as CSV, from the Short Links tab.', 'menj-click' ) );
	echo '<div class="menj-tools">';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="menj-tool">';
	menj_click_form_fields( 'menj_click_settings_export' );
	echo '<button type="submit" class="button">' . esc_html__( 'Download settings', 'menj-click' ) . '</button></form>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="menj-tool">';
	menj_click_form_fields( 'menj_click_settings_import' );
	echo '<input type="file" name="settings" accept=".json,application/json" required> <button type="submit" class="button">' . esc_html__( 'Restore settings', 'menj-click' ) . '</button></form>';
	echo '</div>';
	menj_click_card_close();

	menj_click_card_open( __( 'Delete all theme data', 'menj-click' ), __( 'Removes every short link, all click history and these settings. Posts and pages stay. Switching themes never deletes anything; this is the only way to.', 'menj-click' ), 'menj-card--danger' );
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="menj-inline-form" data-delete-all>';
	menj_click_form_fields( 'menj_click_delete_data' );
	echo '<label for="menj-delete-confirm">' . esc_html__( 'Type DELETE to confirm', 'menj-click' ) . '</label>';
	echo '<input type="text" id="menj-delete-confirm" name="confirm" autocomplete="off" data-delete-input>';
	echo '<button type="submit" class="button menj-button-danger" data-delete-button disabled>' . esc_html__( 'Delete all theme data', 'menj-click' ) . '</button></form>';
	menj_click_card_close();
}

/* Handlers ------------------------------------------------------------------- */

function menj_click_handle_setup() {
	menj_click_admin_guard( 'menj_click_setup' );
	$task   = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
	$return = ( isset( $_POST['return'] ) && 'notes' === $_POST['return'] ) ? 'notes' : 'diagnostics';
	switch ( $task ) {
		case 'tables':
			menj_click_install();
			menj_click_admin_flash( 'success', __( 'Tables repaired.', 'menj-click' ) );
			break;
		case 'pages':
		case 'all':
			$created = menj_click_setup_pages();
			if ( 'all' === $task ) {
				menj_click_apply_notes_permalinks();
			}
			menj_click_admin_flash( 'success', $created ? sprintf( /* translators: %s: page titles */ __( 'Done. Created: %s.', 'menj-click' ), implode( ', ', $created ) ) : __( 'Done. Reading settings updated.', 'menj-click' ) );
			break;
		case 'permalinks':
			menj_click_apply_notes_permalinks();
			menj_click_admin_flash( 'success', __( 'Posts now live under /notes/.', 'menj-click' ) );
			break;
		case 'starters':
			$n = menj_click_seed_starter_links();
			/* translators: %d: number of links */
			menj_click_admin_flash( 'success', sprintf( _n( '%d starter link added as a draft. Set its destination on the Short Links tab, then make it Active.', '%d starter links added as drafts. Set their destinations on the Short Links tab, then make them Active.', $n, 'menj-click' ), $n ) );
			break;
		case 'import-shortify':
			menj_click_report_import( menj_click_import_from_shortify(), 'URL Shortify' );
			break;
		case 'cron':
			if ( ! wp_next_scheduled( 'menj_click_daily' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'menj_click_daily' );
			}
			menj_click_dir_schedule();
			menj_click_admin_flash( 'success', __( 'Scheduled.', 'menj-click' ) );
			break;
	}
	menj_click_admin_redirect( $return );
}
add_action( 'admin_post_menj_click_setup', 'menj_click_handle_setup' );

function menj_click_handle_settings_export() {
	menj_click_admin_guard( 'menj_click_settings_export' );
	$payload = array(
		'theme'    => 'menj-click',
		'version'  => MENJ_CLICK_VERSION,
		'exported' => gmdate( 'c' ),
		'settings' => menj_click_settings(),
		'tagline'  => get_option( 'blogdescription' ),
	);
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( menj_click_host() . '-settings-' . gmdate( 'Y-m-d' ) . '.json' ) . '"' );
	echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}
add_action( 'admin_post_menj_click_settings_export', 'menj_click_handle_settings_export' );

function menj_click_handle_settings_import() {
	menj_click_admin_guard( 'menj_click_settings_import' );
	$file = isset( $_FILES['settings'] ) ? $_FILES['settings'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$data = ( $file && empty( $file['error'] ) && is_uploaded_file( $file['tmp_name'] ) && $file['size'] < MB_IN_BYTES )
		? json_decode( (string) file_get_contents( $file['tmp_name'] ), true ) // phpcs:ignore WordPress.WP.AlternativeFunctions
		: null;
	if ( ! is_array( $data ) || empty( $data['settings'] ) || 'menj-click' !== ( isset( $data['theme'] ) ? $data['theme'] : '' ) ) {
		menj_click_admin_flash( 'error', __( 'That isn’t a menj.click settings file.', 'menj-click' ) );
		menj_click_admin_redirect( 'diagnostics' );
	}
	$s = $data['settings'];
	foreach ( array( 'hero', 'notes', 'footer', 'links' ) as $section ) {
		if ( isset( $s[ $section ] ) && is_array( $s[ $section ] ) ) {
			menj_click_update_section( $section, menj_click_sanitize_section( $section, $s[ $section ] ) );
		}
	}
	foreach ( array( 'hub', 'featured' ) as $section ) {
		if ( isset( $s[ $section ] ) && is_array( $s[ $section ] ) ) {
			menj_click_update_section( $section, menj_click_sanitize_section( $section, wp_slash( $s[ $section ] ) ) );
		}
	}
	if ( isset( $s['qr'] ) && is_array( $s['qr'] ) ) {
		$qr = $s['qr'];
		$qr['cards']       = isset( $qr['cards'] ) ? wp_slash( $qr['cards'] ) : array();
		$qr['transparent'] = ( isset( $qr['background'] ) && 'transparent' === $qr['background'] ) ? 1 : 0;
		menj_click_update_section( 'qr', menj_click_sanitize_section( 'qr', $qr ) );
	}
	if ( isset( $data['tagline'] ) ) {
		update_option( 'blogdescription', sanitize_text_field( $data['tagline'] ) );
	}
	menj_click_admin_flash( 'success', __( 'Settings restored.', 'menj-click' ) );
	menj_click_admin_redirect( 'diagnostics' );
}
add_action( 'admin_post_menj_click_settings_import', 'menj_click_handle_settings_import' );

function menj_click_handle_delete_data() {
	menj_click_admin_guard( 'menj_click_delete_data' );
	if ( ! isset( $_POST['confirm'] ) || 'DELETE' !== $_POST['confirm'] ) {
		menj_click_admin_flash( 'error', __( 'Type DELETE to confirm. Nothing was removed.', 'menj-click' ) );
		menj_click_admin_redirect( 'diagnostics' );
	}
	menj_click_delete_all_data();
	menj_click_install(); // Leave the theme working, empty.
	menj_click_admin_flash( 'success', __( 'All theme data deleted. Settings are back to their defaults.', 'menj-click' ) );
	menj_click_admin_redirect( 'diagnostics' );
}
add_action( 'admin_post_menj_click_delete_data', 'menj_click_handle_delete_data' );
