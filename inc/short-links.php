<?php
/**
 * Native short links: storage, redirects, click log, stats, import/export.
 *
 * Links live in {prefix}menj_links; every visit is logged in {prefix}menj_clicks.
 * Active links are mirrored into one autoloaded option, so a request for
 * /slug is answered from memory without a database query.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

function menj_click_links_table() {
	global $wpdb;
	return $wpdb->prefix . 'menj_links';
}

function menj_click_clicks_table() {
	global $wpdb;
	return $wpdb->prefix . 'menj_clicks';
}

/**
 * True once the theme's tables have been installed.
 */
function menj_click_links_ready() {
	return (int) get_option( 'menj_click_db_version', 0 ) > 0;
}

/**
 * Create or upgrade the link and click tables.
 */
function menj_click_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$links   = menj_click_links_table();
	$clicks  = menj_click_clicks_table();

	dbDelta(
		"CREATE TABLE {$links} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  slug varchar(64) NOT NULL,
  target text NOT NULL,
  title varchar(255) NOT NULL DEFAULT '',
  description text NULL,
  redirect_type smallint(3) unsigned NOT NULL DEFAULT 307,
  forward_query tinyint(1) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'active',
  clicks bigint(20) unsigned NOT NULL DEFAULT 0,
  last_clicked datetime NULL DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug)
) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$clicks} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  link_id bigint(20) unsigned NOT NULL,
  clicked_at datetime NOT NULL,
  source varchar(10) NOT NULL DEFAULT 'web',
  referrer_host varchar(191) NOT NULL DEFAULT '',
  device varchar(10) NOT NULL DEFAULT 'desktop',
  visitor char(16) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY link_time (link_id,clicked_at),
  KEY clicked_at (clicked_at)
) {$charset};"
	);
}

/* -------------------------------------------------------------------------- */
/* Slug rules                                                                  */
/* -------------------------------------------------------------------------- */

/**
 * Paths the site itself needs. Short links can never take these.
 *
 * @return string[]
 */
function menj_click_reserved_slugs() {
	return apply_filters(
		'menj_click_reserved_slugs',
		array(
			'wp-admin', 'wp-login', 'wp-json', 'wp-content', 'wp-includes', 'wp-cron', 'wp-signup', 'wp-activate',
			'xmlrpc', 'feed', 'rss', 'rss2', 'atom', 'rdf', 'comments', 'search', 'page', 'author', 'category',
			'tag', 'type', 'embed', 'trackback', 'attachment', 'sitemap', 'sitemaps', 'sitemap_index', 'robots',
			'favicon', 'admin', 'login', 'dashboard', 'register', 'index', 'home',
			'notes', 'links', 'short-urls', 'qr', 'directory',
		)
	);
}

/**
 * Whether post URLs sit under a prefix such as /notes/ (so they can't clash
 * with root-level short links).
 */
function menj_click_posts_are_prefixed() {
	$structure = (string) get_option( 'permalink_structure' );
	if ( '' === $structure ) {
		return true; // Plain permalinks use ?p=, nothing at root.
	}
	$prefix = strstr( $structure, '%', true );
	return is_string( $prefix ) && '' !== trim( $prefix, '/' );
}

/**
 * Whether any short link (active or draft) uses a slug.
 */
function menj_click_link_slug_exists( $slug, $exclude_id = 0 ) {
	global $wpdb;
	if ( ! menj_click_links_ready() ) {
		return false;
	}
	return (bool) $wpdb->get_var(
		$wpdb->prepare( 'SELECT id FROM ' . menj_click_links_table() . ' WHERE slug = %s AND id <> %d LIMIT 1', $slug, (int) $exclude_id )
	);
}

/**
 * Explain why a slug can't be used, or return '' when it's free.
 *
 * @return string '', 'reserved', 'taken' or 'content'.
 */
function menj_click_slug_conflict( $slug, $exclude_id = 0 ) {
	global $wpdb;
	if ( in_array( $slug, menj_click_reserved_slugs(), true ) ) {
		return 'reserved';
	}
	if ( menj_click_link_slug_exists( $slug, $exclude_id ) ) {
		return 'taken';
	}
	$types = menj_click_posts_are_prefixed() ? array( 'page' ) : array( 'page', 'post' );
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$found = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_parent = 0 AND post_type IN ({$in}) AND post_status NOT IN ('trash','auto-draft','inherit') LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
			array_merge( array( $slug ), $types )
		)
	);
	return $found ? 'content' : '';
}

/**
 * Keep pages (and root-level posts) from taking a short link's slug.
 * WordPress then appends -2, exactly as it does for duplicate slugs.
 */
function menj_click_guard_post_slug( $slug, $post_id, $post_status, $post_type, $post_parent ) {
	global $wpdb;
	if ( (int) $post_parent > 0 || ! menj_click_links_ready() ) {
		return $slug;
	}
	$types = menj_click_posts_are_prefixed() ? array( 'page' ) : array( 'page', 'post' );
	if ( ! in_array( $post_type, $types, true ) || ! menj_click_link_slug_exists( $slug ) ) {
		return $slug;
	}
	$n = 2;
	do {
		$candidate = $slug . '-' . $n++;
		$taken     = menj_click_link_slug_exists( $candidate ) || $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND ID <> %d LIMIT 1", $candidate, $post_type, (int) $post_id )
		);
	} while ( $taken );
	return $candidate;
}
add_filter( 'wp_unique_post_slug', 'menj_click_guard_post_slug', 10, 5 );

/* -------------------------------------------------------------------------- */
/* CRUD                                                                        */
/* -------------------------------------------------------------------------- */

function menj_click_get_link( $id ) {
	global $wpdb;
	if ( ! menj_click_links_ready() ) {
		return null;
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . menj_click_links_table() . ' WHERE id = %d', (int) $id ), ARRAY_A );
	return $row ? $row : null;
}

function menj_click_get_link_by_slug( $slug ) {
	global $wpdb;
	if ( ! menj_click_links_ready() ) {
		return null;
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . menj_click_links_table() . ' WHERE slug = %s', $slug ), ARRAY_A );
	return $row ? $row : null;
}

/**
 * List links.
 *
 * @param array $args { search, status, orderby, order, per_page, paged }.
 * @return array{items: array, total: int}
 */
function menj_click_query_links( array $args = array() ) {
	global $wpdb;
	if ( ! menj_click_links_ready() ) {
		return array( 'items' => array(), 'total' => 0 );
	}
	$args = array_merge(
		array( 'search' => '', 'status' => '', 'orderby' => 'created_at', 'order' => 'DESC', 'per_page' => 50, 'paged' => 1 ),
		$args
	);
	$where  = array( '1=1' );
	$params = array();
	if ( '' !== $args['search'] ) {
		$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where[]  = '(slug LIKE %s OR target LIKE %s OR title LIKE %s)';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}
	if ( in_array( $args['status'], array( 'active', 'draft' ), true ) ) {
		$where[]  = 'status = %s';
		$params[] = $args['status'];
	}
	$orderby  = in_array( $args['orderby'], array( 'created_at', 'clicks', 'slug', 'last_clicked' ), true ) ? $args['orderby'] : 'created_at';
	$order    = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
	$per_page = max( 1, min( 1000, (int) $args['per_page'] ) );
	$offset   = ( max( 1, (int) $args['paged'] ) - 1 ) * $per_page;
	$table    = menj_click_links_table();
	$sql_where = implode( ' AND ', $where );

	$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";
	$list_sql  = "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";

	$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB
	$items = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB

	return array( 'items' => $items ? $items : array(), 'total' => $total );
}

/**
 * Create or update a link.
 *
 * @param array $data    { slug, target, title, description, redirect_type, forward_query, status, clicks }.
 * @param int   $id      Link ID to update, or 0 to create.
 * @param bool  $rebuild Rebuild the redirect map afterwards.
 * @return int|WP_Error Link ID.
 */
function menj_click_save_link( array $data, $id = 0, $rebuild = true ) {
	global $wpdb;
	$id   = (int) $id;
	$slug = menj_click_clean_slug( isset( $data['slug'] ) ? $data['slug'] : '' );

	if ( '' === $slug ) {
		return new WP_Error( 'slug', __( 'Use 1–64 lowercase letters, numbers, hyphens or underscores for the slug.', 'menj-click' ) );
	}
	switch ( menj_click_slug_conflict( $slug, $id ) ) {
		case 'reserved':
			/* translators: %s: slug */
			return new WP_Error( 'slug', sprintf( __( '“%s” is reserved for the site itself. Choose another slug.', 'menj-click' ), $slug ) );
		case 'taken':
			/* translators: %s: slug */
			return new WP_Error( 'slug', sprintf( __( '“%s” is already used by another short link.', 'menj-click' ), $slug ) );
		case 'content':
			/* translators: %s: slug */
			return new WP_Error( 'slug', sprintf( __( 'A page already lives at /%s/. Rename the page or choose another slug.', 'menj-click' ), $slug ) );
	}

	$status = ( isset( $data['status'] ) && 'draft' === $data['status'] ) ? 'draft' : 'active';
	$target = trim( isset( $data['target'] ) ? (string) $data['target'] : '' );
	if ( '' !== $target ) {
		$target = esc_url_raw( $target, array( 'http', 'https', 'mailto', 'tel' ) );
		if ( '' === $target ) {
			return new WP_Error( 'target', __( 'Enter a full destination URL, starting with https://', 'menj-click' ) );
		}
		if ( untrailingslashit( strtok( $target, '?#' ) ) === untrailingslashit( home_url( '/' . $slug ) ) ) {
			return new WP_Error( 'target', __( 'A short link can’t point to itself.', 'menj-click' ) );
		}
	} elseif ( 'active' === $status ) {
		return new WP_Error( 'target', __( 'Add a destination, or save the link as a draft.', 'menj-click' ) );
	}

	$defaults = menj_click_settings( 'links' );
	$type     = isset( $data['redirect_type'] ) ? (int) $data['redirect_type'] : (int) $defaults['default_redirect'];
	if ( ! in_array( $type, array( 301, 302, 307 ), true ) ) {
		$type = 307;
	}

	$row = array(
		'slug'          => $slug,
		'target'        => $target,
		'title'         => sanitize_text_field( isset( $data['title'] ) ? $data['title'] : '' ),
		'description'   => sanitize_textarea_field( isset( $data['description'] ) ? $data['description'] : '' ),
		'redirect_type' => $type,
		'forward_query' => empty( $data['forward_query'] ) ? 0 : 1,
		'status'        => $status,
		'updated_at'    => current_time( 'mysql', true ),
	);

	if ( $id ) {
		$ok = $wpdb->update( menj_click_links_table(), $row, array( 'id' => $id ) );
	} else {
		$row['created_at'] = $row['updated_at'];
		$row['clicks']     = isset( $data['clicks'] ) ? max( 0, (int) $data['clicks'] ) : 0;
		$ok                = $wpdb->insert( menj_click_links_table(), $row );
		$id                = (int) $wpdb->insert_id;
	}

	if ( false === $ok ) {
		/* translators: %s: database error */
		return new WP_Error( 'db', sprintf( __( 'The link couldn’t be saved: %s', 'menj-click' ), $wpdb->last_error ) );
	}
	if ( $rebuild ) {
		menj_click_rebuild_link_map();
	}
	return $id;
}

/**
 * Delete links and their click history.
 *
 * @param int[] $ids Link IDs.
 * @return int Number deleted.
 */
function menj_click_delete_links( array $ids ) {
	global $wpdb;
	$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
	if ( ! $ids ) {
		return 0;
	}
	$in = implode( ',', $ids );
	$wpdb->query( 'DELETE FROM ' . menj_click_clicks_table() . " WHERE link_id IN ({$in})" ); // phpcs:ignore WordPress.DB
	$n = (int) $wpdb->query( 'DELETE FROM ' . menj_click_links_table() . " WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB
	menj_click_rebuild_link_map();
	return $n;
}

/* -------------------------------------------------------------------------- */
/* Redirect map                                                                */
/* -------------------------------------------------------------------------- */

function menj_click_rebuild_link_map() {
	global $wpdb;
	$map = array();
	if ( menj_click_links_ready() ) {
		$rows = $wpdb->get_results( 'SELECT id, slug, target, redirect_type, forward_query FROM ' . menj_click_links_table() . " WHERE status = 'active' AND target <> ''", ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			$map[ $r['slug'] ] = array(
				'id' => (int) $r['id'],
				't'  => $r['target'],
				'r'  => (int) $r['redirect_type'],
				'q'  => (int) $r['forward_query'],
			);
		}
	}
	update_option( 'menj_click_link_map', $map, true );
	return $map;
}

/**
 * Active links keyed by slug.
 *
 * @return array
 */
function menj_click_link_map() {
	$map = get_option( 'menj_click_link_map', null );
	if ( ! is_array( $map ) ) {
		$map = menj_click_links_ready() ? menj_click_rebuild_link_map() : array();
	}
	return $map;
}

/* -------------------------------------------------------------------------- */
/* Request routing                                                             */
/* -------------------------------------------------------------------------- */

/**
 * Request path relative to the site root, with a leading slash.
 */
function menj_click_request_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = rawurldecode( (string) wp_parse_url( (string) $uri, PHP_URL_PATH ) );
	$base = '/' . trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '/' !== $base ) {
		if ( $path === $base ) {
			$path = '/';
		} elseif ( 0 === strpos( $path, $base . '/' ) ) {
			$path = substr( $path, strlen( $base ) );
		}
	}
	return '/' . ltrim( $path, '/' );
}

/**
 * Answer /slug and /qr/slug.svg|png before WordPress routes the request.
 */
function menj_click_route_request() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return;
	}

	$path = menj_click_request_path();

	if ( preg_match( '#^/qr/([a-z0-9][a-z0-9_-]{0,63})\.(svg|png)$#', strtolower( $path ), $m ) ) {
		menj_click_serve_qr_image( $m[1], $m[2] ); // Exits when the link exists.
		add_action( 'wp', 'menj_click_force_404' ); // Unknown or inactive code.
		return;
	}

	$trimmed = trim( $path, '/' );
	if ( '' === $trimmed || false !== strpos( $trimmed, '/' ) ) {
		return;
	}
	$slug = menj_click_clean_slug( $trimmed );
	$map  = menj_click_link_map();
	if ( '' === $slug || ! isset( $map[ $slug ] ) ) {
		return;
	}
	menj_click_do_redirect( $map[ $slug ], 'HEAD' === $method );
}
add_action( 'init', 'menj_click_route_request', 1 );

/**
 * Answer with the theme's 404 page (used for QR images of unknown links).
 */
function menj_click_force_404() {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	add_filter( 'redirect_canonical', '__return_false' );
}

/**
 * Send the redirect, logging the click first.
 *
 * @param array $link      Map entry { id, t, r, q }.
 * @param bool  $head_only HEAD requests (link previews) aren't counted.
 */
function menj_click_do_redirect( array $link, $head_only = false ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$source = ( isset( $_GET['src'] ) && 'qr' === $_GET['src'] ) ? 'qr' : 'web';
	$target = $link['t'];

	if ( ! empty( $link['q'] ) && ! empty( $_GET ) ) {
		$params = wp_unslash( $_GET );
		unset( $params['src'] );
		if ( $params ) {
			$fragment = '';
			$hash     = strpos( $target, '#' );
			if ( false !== $hash ) {
				$fragment = substr( $target, $hash );
				$target   = substr( $target, 0, $hash );
			}
			$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ) . $fragment;
		}
	}
	// phpcs:enable

	if ( ! $head_only ) {
		menj_click_log_click( (int) $link['id'], $source );
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	if ( wp_redirect( $target, (int) $link['r'], 'menj.click' ) ) { // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}
}

/**
 * Classify a user agent. Bots and link-preview fetchers aren't counted
 * unless "Count bots" is on.
 */
function menj_click_device_type( $ua ) {
	if ( '' === $ua || preg_match( '/bot\b|bot\/|crawl|spider|slurp|preview|facebookexternalhit|embedly|whatsapp|telegram|discord|slack|skype|headless|curl\/|wget|python-|go-http|okhttp|monitor|pingdom|uptime|lighthouse/i', $ua ) ) {
		return 'bot';
	}
	if ( preg_match( '/ipad|tablet|kindle|silk|playbook|android(?!.*mobile)/i', $ua ) ) {
		return 'tablet';
	}
	if ( preg_match( '/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua ) ) {
		return 'mobile';
	}
	return 'desktop';
}

/**
 * Log one click. Visitors are counted with a daily salted hash, never an IP.
 */
function menj_click_log_click( $link_id, $source ) {
	global $wpdb;
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput
	$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : '';
	$device = menj_click_device_type( $ua );
	$opts   = menj_click_settings( 'links' );
	if ( 'bot' === $device && empty( $opts['count_bots'] ) ) {
		return;
	}
	$ref = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_HOST ) : '';
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	// phpcs:enable
	$ref     = strtolower( preg_replace( '/^www\./i', '', $ref ) );
	$visitor = substr( hash_hmac( 'sha256', $ip . '|' . $ua . '|' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) ), 0, 16 );
	$now     = current_time( 'mysql', true );

	$wpdb->insert(
		menj_click_clicks_table(),
		array(
			'link_id'       => $link_id,
			'clicked_at'    => $now,
			'source'        => 'qr' === $source ? 'qr' : 'web',
			'referrer_host' => substr( $ref, 0, 191 ),
			'device'        => $device,
			'visitor'       => $visitor,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s' )
	);
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . menj_click_links_table() . ' SET clicks = clicks + 1, last_clicked = %s WHERE id = %d', $now, $link_id ) ); // phpcs:ignore WordPress.DB
}

/* -------------------------------------------------------------------------- */
/* Stats                                                                       */
/* -------------------------------------------------------------------------- */

/**
 * Click stats for one link over the last N days.
 *
 * @return array{series: array, total: int, uniques: int, qr: int, devices: array, referrers: array}
 */
function menj_click_link_stats( $link_id, $days = 30 ) {
	global $wpdb;
	$t     = menj_click_clicks_table();
	$days  = max( 1, min( 365, (int) $days ) );
	$since = gmdate( 'Y-m-d 00:00:00', time() - ( $days - 1 ) * DAY_IN_SECONDS );

	$daily = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(clicked_at) AS d, COUNT(*) AS c FROM {$t} WHERE link_id = %d AND clicked_at >= %s GROUP BY DATE(clicked_at)", $link_id, $since ), OBJECT_K ); // phpcs:ignore WordPress.DB
	$series = array();
	for ( $i = $days - 1; $i >= 0; $i-- ) {
		$d            = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
		$series[ $d ] = isset( $daily[ $d ] ) ? (int) $daily[ $d ]->c : 0;
	}

	$totals    = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, COUNT(DISTINCT visitor) AS uniques, COALESCE(SUM(source = 'qr'), 0) AS qr FROM {$t} WHERE link_id = %d AND clicked_at >= %s", $link_id, $since ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$devices   = $wpdb->get_results( $wpdb->prepare( "SELECT device, COUNT(*) AS c FROM {$t} WHERE link_id = %d AND clicked_at >= %s GROUP BY device ORDER BY c DESC", $link_id, $since ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$referrers = $wpdb->get_results( $wpdb->prepare( "SELECT referrer_host AS host, COUNT(*) AS c FROM {$t} WHERE link_id = %d AND clicked_at >= %s AND referrer_host <> '' GROUP BY referrer_host ORDER BY c DESC LIMIT 6", $link_id, $since ), ARRAY_A ); // phpcs:ignore WordPress.DB

	return array(
		'series'    => $series,
		'total'     => (int) $totals['total'],
		'uniques'   => (int) $totals['uniques'],
		'qr'        => (int) $totals['qr'],
		'devices'   => $devices ? $devices : array(),
		'referrers' => $referrers ? $referrers : array(),
	);
}

/**
 * Delete click rows older than the retention window (daily cron).
 */
function menj_click_prune_clicks() {
	global $wpdb;
	$days = (int) menj_click_settings( 'links' )['retention_days'];
	if ( $days > 0 && menj_click_links_ready() ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . menj_click_clicks_table() . ' WHERE clicked_at < %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB
	}
}
add_action( 'menj_click_daily', 'menj_click_prune_clicks' );

/* -------------------------------------------------------------------------- */
/* Import / export                                                             */
/* -------------------------------------------------------------------------- */

/**
 * Save an imported link. An existing draft with no destination (e.g. a
 * starter link) is filled in rather than skipped.
 *
 * @return int|WP_Error
 */
function menj_click_import_one( array $data ) {
	$slug     = menj_click_clean_slug( isset( $data['slug'] ) ? $data['slug'] : '' );
	$existing = '' !== $slug ? menj_click_get_link_by_slug( $slug ) : null;
	if ( $existing && ( 'draft' !== $existing['status'] || '' !== $existing['target'] ) ) {
		return new WP_Error( 'exists', __( 'already exists', 'menj-click' ) );
	}
	if ( $existing ) {
		unset( $data['clicks'] );
	}
	return menj_click_save_link( $data, $existing ? (int) $existing['id'] : 0, false );
}

/**
 * URL Shortify's links table, if it's still in the database.
 */
function menj_click_shortify_table() {
	global $wpdb;
	$table = $wpdb->prefix . 'kc_us_links';
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ? $table : '';
}

/**
 * Copy every link from URL Shortify, keeping slugs, destinations and click totals.
 *
 * @return array|WP_Error { imported: int, skipped: string[] }
 */
function menj_click_import_from_shortify() {
	global $wpdb;
	$table = menj_click_shortify_table();
	if ( '' === $table ) {
		return new WP_Error( 'missing', __( 'No URL Shortify links were found in the database.', 'menj-click' ) );
	}
	$rows   = $wpdb->get_results( "SELECT slug, url, name, description, redirect_type, params_forwarding, status, total_clicks FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB
	$result = array( 'imported' => 0, 'skipped' => array() );
	foreach ( (array) $rows as $r ) {
		$saved = menj_click_import_one(
			array(
				'slug'          => $r['slug'],
				'target'        => $r['url'],
				'title'         => $r['name'],
				'description'   => $r['description'],
				'redirect_type' => (int) $r['redirect_type'],
				'forward_query' => ! empty( $r['params_forwarding'] ),
				'status'        => 1 === (int) $r['status'] ? 'active' : 'draft',
				'clicks'        => (int) $r['total_clicks'],
			)
		);
		if ( is_wp_error( $saved ) ) {
			$result['skipped'][] = $r['slug'] . ' — ' . $saved->get_error_message();
		} else {
			$result['imported']++;
		}
	}
	menj_click_rebuild_link_map();
	return $result;
}

/**
 * Import a CSV. Accepts this theme's export and URL Shortify's export columns.
 *
 * @param string $file Path to the uploaded file.
 * @return array|WP_Error { imported: int, skipped: string[] }
 */
function menj_click_import_csv( $file ) {
	$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $handle ) {
		return new WP_Error( 'read', __( 'The file couldn’t be read.', 'menj-click' ) );
	}
	$header = fgetcsv( $handle, 0, ',', '"', '\\' );
	if ( ! $header ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'empty', __( 'The file is empty.', 'menj-click' ) );
	}
	$aliases = array(
		'slug'                 => 'slug',
		'target'               => 'target',
		'target url'           => 'target',
		'url'                  => 'target',
		'destination'          => 'target',
		'title'                => 'title',
		'name'                 => 'title',
		'description'          => 'description',
		'redirect_type'        => 'redirect_type',
		'redirect type'        => 'redirect_type',
		'forward_query'        => 'forward_query',
		'parameter forwarding' => 'forward_query',
		'status'               => 'status',
		'clicks'               => 'clicks',
	);
	$columns = array();
	foreach ( $header as $i => $name ) {
		$key = strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $name ) ) );
		if ( isset( $aliases[ $key ] ) ) {
			$columns[ $i ] = $aliases[ $key ];
		}
	}
	if ( ! in_array( 'slug', $columns, true ) || ! in_array( 'target', $columns, true ) ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'columns', __( 'The CSV needs at least “slug” and “target” columns.', 'menj-click' ) );
	}

	$result = array( 'imported' => 0, 'skipped' => array() );
	while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$data = array();
		foreach ( $columns as $i => $key ) {
			$data[ $key ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
		}
		if ( '' === $data['slug'] ) {
			continue;
		}
		if ( isset( $data['status'] ) ) {
			$data['status'] = in_array( strtolower( $data['status'] ), array( 'draft', 'inactive', '0' ), true ) ? 'draft' : 'active';
		}
		$saved = menj_click_import_one( $data );
		if ( is_wp_error( $saved ) ) {
			$result['skipped'][] = $data['slug'] . ' — ' . $saved->get_error_message();
		} else {
			$result['imported']++;
		}
	}
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	menj_click_rebuild_link_map();
	return $result;
}

/**
 * Stream every link as CSV.
 */
function menj_click_export_csv() {
	$links = menj_click_query_links( array( 'per_page' => 1000, 'orderby' => 'slug', 'order' => 'ASC' ) )['items'];
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( menj_click_host() . '-short-links-' . gmdate( 'Y-m-d' ) . '.csv' ) . '"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fputcsv( $out, array( 'slug', 'target', 'title', 'description', 'redirect_type', 'forward_query', 'status', 'clicks', 'created_at' ), ',', '"', '\\' );
	foreach ( $links as $l ) {
		fputcsv( $out, array( $l['slug'], $l['target'], $l['title'], $l['description'], $l['redirect_type'], $l['forward_query'], $l['status'], $l['clicks'], $l['created_at'] ), ',', '"', '\\' );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/**
 * Add the mockup's links as drafts. Suggested destinations are filled in
 * where known; nothing goes live until you switch a link to Active.
 *
 * @return int Number created.
 */
function menj_click_seed_starter_links() {
	$starters = array(
		'seo'      => array( 'SEO services', 'https://menj.me/' ),
		'blog'     => array( 'Blog', 'https://menj.blog/' ),
		'bio'      => array( 'About me', 'https://menj.bio/' ),
		'buzz'     => array( 'Social media', 'https://menj.buzz/' ),
		'book'     => array( 'Books', '' ),
		'apo'      => array( 'Apostle of Doom', 'https://apostleofdoom.org/' ),
		'projects' => array( 'Projects', '' ),
		'contact'  => array( 'Contact', '' ),
		'work'     => array( 'Portfolio', '' ),
	);
	$created = 0;
	foreach ( $starters as $slug => $info ) {
		if ( menj_click_link_slug_exists( $slug ) ) {
			continue;
		}
		$saved = menj_click_save_link(
			array( 'slug' => $slug, 'title' => $info[0], 'target' => $info[1], 'status' => 'draft' ),
			0,
			false
		);
		if ( ! is_wp_error( $saved ) ) {
			$created++;
		}
	}
	menj_click_rebuild_link_map();
	return $created;
}
