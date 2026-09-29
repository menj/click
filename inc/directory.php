<?php
/**
 * Directory: listings, categories, tags, tiers, routing, hit counter and views.
 *
 * Listings are a private post type (menj_listing) so they get WordPress's
 * editor, revisions, search, feeds, sitemaps and comments (used for
 * reviews). Everything specific to the directory lives in post meta and
 * two small tables (hits, payments).
 *
 * Statuses:
 *   menj_unconfirmed  waiting for the owner to confirm their email
 *   pending           waiting for review (and/or payment)
 *   publish           live
 *   menj_expired      paid period ended, or reciprocal link missing past the grace period
 *   draft             switched off by an admin
 *   trash             rejected
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------- */
/* Settings                                                                    */
/* -------------------------------------------------------------------------- */

function menj_click_directory_defaults() {
	return array(
		'enabled'            => 1,
		'title'              => 'Directory',
		'intro'              => 'Hand-picked websites, tools and businesses. Suggest one of your own.',
		'submissions'        => 1,
		'email_confirm'      => 1,
		'per_page'           => 20,
		'sort'               => 'newest',
		'cats_preview'       => 3,
		'show_counts'        => 1,
		'tags'               => 1,
		'reviews'            => 1,
		'reviews_rating'     => 1,
		'show_qr'            => 1,
		'business_fields'    => 0,
		'title_min'          => 3,
		'title_max'          => 80,
		'desc_min'           => 40,
		'desc_max'           => 500,
		'unique_domain'      => 1,
		'check_online'       => 1,
		'max_per_ip'         => 5,
		'banned_domains'     => '',
		'banned_emails'      => '',
		'banned_ips'         => '',
		'banned_words'       => '',
		'terms'              => 'Listings are reviewed by hand. Adult, gambling, pharmacy and scraped sites aren’t accepted.',
		'notify_email'       => '',
		// Tiers.
		'free_enabled'       => 1,
		'free_approval'      => 1,
		'free_rel'           => 'ugc nofollow',
		'featured_enabled'   => 1,
		'featured_price'     => '49',
		'featured_period'    => 'year',
		'featured_max'       => 5,
		'sponsored_enabled'  => 1,
		'sponsored_price'    => '149',
		'sponsored_period'   => 'year',
		'paid_rel'           => 'sponsored',
		'paid_approval'      => 1,
		'expire_action'      => 'downgrade',
		'reminder_days'      => 7,
		// Payments.
		'currency'           => 'USD',
		'paypal_enabled'     => 0,
		'paypal_email'       => '',
		'paypal_sandbox'     => 0,
		'paypal_subscriptions' => 1,
		'max_periods'        => 3,
		'multi_discount'     => 0,
		'invoice_text'       => "We’ll email you an invoice. Your listing goes live as soon as it’s paid.",
		// Reciprocal links.
		'reciprocal'         => 'optional',
		'reciprocal_follow'  => 1,
		'reciprocal_dofollow' => 0,
		'reciprocal_recheck' => 7,
		'reciprocal_grace'   => 7,
		'backlink_url'       => '',
		'backlink_text'      => '',
		// Link checker.
		'checker'            => 1,
		'checker_batch'      => 10,
		'checker_every'      => 7,
		'checker_fails'      => 3,
		'checker_hide'       => 0,
	);
}

function menj_click_directory_email_defaults() {
	return array(
		'confirm_subject'   => 'Confirm your listing on {site}',
		'confirm_body'      => "Hi {name},\n\nThanks for suggesting {title} ({url}).\n\nPlease confirm your email address so we can review it:\n{confirm_url}\n\nIf you didn't submit this, ignore this email and nothing will be listed.\n\n— {site}",
		'received_subject'  => 'We’ve received {title}',
		'received_body'     => "Hi {name},\n\n{title} is in the review queue. We'll email you when it's live.\n\nManage your listing any time:\n{manage_url}\n\n— {site}",
		'payment_subject'   => 'Payment for your {tier} listing',
		'payment_body'      => "Hi {name},\n\nYour {tier} listing for {title} costs {amount}.\n\nPay here:\n{pay_url}\n\n— {site}",
		'paid_subject'      => 'Payment received for {title}',
		'paid_body'         => "Hi {name},\n\nThanks — we've received {amount} for your {tier} listing. It runs until {expiry}.\n\nManage your listing:\n{manage_url}\n\n— {site}",
		'approved_subject'  => '{title} is now listed',
		'approved_body'     => "Hi {name},\n\nGood news: {title} is now live on {site}.\n\n{listing_url}\n\nManage your listing:\n{manage_url}\n\n— {site}",
		'rejected_subject'  => 'About your listing for {title}',
		'rejected_body'     => "Hi {name},\n\nThanks for suggesting {title}. We can't list it this time.\n\n{reason}\n\n— {site}",
		'expiring_subject'  => 'Your {tier} listing ends on {expiry}',
		'expiring_body'     => "Hi {name},\n\nYour {tier} listing for {title} ends on {expiry}. Renew it here:\n{pay_url}\n\n— {site}",
		'expired_subject'   => 'Your {tier} listing for {title} has ended',
		'expired_body'      => "Hi {name},\n\nYour {tier} listing for {title} has ended. You can renew it any time:\n{pay_url}\n\n— {site}",
		'reciprocal_subject' => 'We can’t find the link back to {site}',
		'reciprocal_body'   => "Hi {name},\n\nWe couldn't find a link to {site} on {reciprocal_url}.\n\nPlease restore it by {deadline}, or your listing for {title} will be hidden.\n\n— {site}",
		'manage_subject'    => 'Your manage link for {title}',
		'manage_body'       => "Hi {name},\n\nHere's a new link to manage your listing for {title}. Older links no longer work.\n\n{manage_url}\n\n— {site}",
		'admin_subject'     => 'Directory: {title} needs your attention',
		'admin_body'        => "{event}\n\n{title} ({url}), {tier} listing.\n\nOpen it: {admin_url}",
	);
}

/**
 * Every status a listing can have. (WordPress's 'any' skips trash and the
 * directory's own statuses, so queries that must see everything use this.)
 *
 * @param bool $trash Include trashed (rejected) listings.
 */
function menj_click_dir_statuses( $trash = true ) {
	$statuses = array( 'publish', 'pending', 'draft', 'future', 'private', 'menj_unconfirmed', 'menj_expired' );
	return $trash ? array_merge( $statuses, array( 'trash' ) ) : $statuses;
}

/**
 * One directory setting.
 */
function menj_click_dir( $key ) {
	$settings = menj_click_settings( 'directory' );
	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

function menj_click_dir_enabled() {
	return (bool) menj_click_dir( 'enabled' );
}

/**
 * The three listing tiers, with their settings.
 *
 * @return array slug => { label, enabled, price, period, rank, paid, benefits }
 */
function menj_click_dir_tiers() {
	$d = menj_click_settings( 'directory' );
	return array(
		'free'      => array(
			'label'    => __( 'Free', 'menj-click' ),
			'enabled'  => (bool) $d['free_enabled'],
			'price'    => 0.0,
			'period'   => 'once',
			'rank'     => 0,
			'paid'     => false,
			'benefits' => array( __( 'Listed in one category', 'menj-click' ), __( 'Reviewed by hand', 'menj-click' ) ),
		),
		'featured'  => array(
			'label'    => __( 'Featured', 'menj-click' ),
			'enabled'  => (bool) $d['featured_enabled'],
			'price'    => (float) $d['featured_price'],
			'period'   => $d['featured_period'],
			'rank'     => 1,
			'paid'     => true,
			'benefits' => array( __( 'Pinned at the top of its category', 'menj-click' ), __( 'Shown on the directory front page', 'menj-click' ), __( 'Highlighted card', 'menj-click' ) ),
		),
		'sponsored' => array(
			'label'    => __( 'Sponsored', 'menj-click' ),
			'enabled'  => (bool) $d['sponsored_enabled'],
			'price'    => (float) $d['sponsored_price'],
			'period'   => $d['sponsored_period'],
			'rank'     => 2,
			'paid'     => true,
			'benefits' => array( __( 'Top of every category', 'menj-click' ), __( 'Sponsored strip on the directory front page', 'menj-click' ), __( 'Short link and QR code', 'menj-click' ) ),
		),
	);
}

function menj_click_dir_period_label( $period ) {
	$labels = array(
		'once'  => __( 'one-off', 'menj-click' ),
		'month' => __( 'per month', 'menj-click' ),
		'year'  => __( 'per year', 'menj-click' ),
	);
	return isset( $labels[ $period ] ) ? $labels[ $period ] : '';
}

/**
 * Price with currency, e.g. "USD 49.00".
 */
function menj_click_dir_money( $amount ) {
	$currency = strtoupper( (string) menj_click_dir( 'currency' ) );
	$symbols  = array( 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'MYR' => 'RM', 'SGD' => 'S$', 'AUD' => 'A$', 'JPY' => '¥' );
	$number   = number_format_i18n( (float) $amount, 'JPY' === $currency ? 0 : 2 );
	return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] . $number : $currency . ' ' . $number;
}

/* -------------------------------------------------------------------------- */
/* Post type, taxonomies, statuses                                             */
/* -------------------------------------------------------------------------- */

function menj_click_dir_register() {
	$on = menj_click_dir_enabled();

	register_post_type(
		'menj_listing',
		array(
			'labels'              => array(
				'name'               => __( 'Listings', 'menj-click' ),
				'singular_name'      => __( 'Listing', 'menj-click' ),
				'add_new_item'       => __( 'Add listing', 'menj-click' ),
				'edit_item'          => __( 'Edit listing', 'menj-click' ),
				'search_items'       => __( 'Search listings', 'menj-click' ),
				'not_found'          => __( 'No listings yet.', 'menj-click' ),
				'menu_name'          => __( 'Directory', 'menj-click' ),
				'all_items'          => __( 'Listings', 'menj-click' ),
			),
			'public'              => $on,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 60,
			'menu_icon'           => 'dashicons-index-card',
			'show_in_rest'        => true,
			'has_archive'         => $on ? 'directory' : false,
			'rewrite'             => $on ? array( 'slug' => 'directory/site', 'with_front' => false ) : false,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'comments', 'revisions', 'excerpt' ),
			'exclude_from_search' => true,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);

	register_taxonomy(
		'menj_dir_category',
		'menj_listing',
		array(
			'labels'            => array(
				'name'          => __( 'Directory categories', 'menj-click' ),
				'singular_name' => __( 'Category', 'menj-click' ),
				'menu_name'     => __( 'Categories', 'menj-click' ),
				'add_new_item'  => __( 'Add category', 'menj-click' ),
			),
			'public'            => $on,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => $on ? array( 'slug' => 'directory/category', 'with_front' => false, 'hierarchical' => true ) : false,
		)
	);

	register_taxonomy(
		'menj_dir_tag',
		'menj_listing',
		array(
			'labels'            => array(
				'name'          => __( 'Directory tags', 'menj-click' ),
				'singular_name' => __( 'Tag', 'menj-click' ),
				'menu_name'     => __( 'Tags', 'menj-click' ),
			),
			'public'            => $on && menj_click_dir( 'tags' ),
			'hierarchical'      => false,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => $on ? array( 'slug' => 'directory/tag', 'with_front' => false ) : false,
		)
	);

	register_post_status(
		'menj_unconfirmed',
		array(
			'label'                     => _x( 'Unconfirmed', 'listing status', 'menj-click' ),
			'public'                    => false,
			'internal'                  => false,
			'protected'                 => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop( 'Unconfirmed <span class="count">(%s)</span>', 'Unconfirmed <span class="count">(%s)</span>', 'menj-click' ),
		)
	);
	register_post_status(
		'menj_expired',
		array(
			'label'                     => _x( 'Expired', 'listing status', 'menj-click' ),
			'public'                    => false,
			'protected'                 => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'menj-click' ),
		)
	);

	foreach ( menj_click_dir_meta_keys() as $key => $type ) {
		register_post_meta( 'menj_listing', $key, array( 'type' => $type, 'single' => true, 'show_in_rest' => false ) );
	}
	register_term_meta( 'menj_dir_category', 'menj_closed', array( 'type' => 'boolean', 'single' => true ) );

	if ( $on ) {
		add_rewrite_rule( '^directory/submit/?$', 'index.php?post_type=menj_listing&menj_dir=submit', 'top' );
		add_rewrite_rule( '^directory/manage/([A-Za-z0-9]{32})/?$', 'index.php?post_type=menj_listing&menj_dir=manage&menj_token=$matches[1]', 'top' );
		add_rewrite_rule( '^directory/confirm/([A-Za-z0-9]{32})/?$', 'index.php?post_type=menj_listing&menj_dir=confirm&menj_token=$matches[1]', 'top' );
		add_rewrite_rule( '^directory/pay/([A-Za-z0-9]{32})/?$', 'index.php?post_type=menj_listing&menj_dir=pay&menj_token=$matches[1]', 'top' );
		add_rewrite_rule( '^directory/a-z/([a-z0-9])/?$', 'index.php?post_type=menj_listing&menj_dir=letter&menj_letter=$matches[1]', 'top' );
		add_rewrite_rule( '^directory/a-z/([a-z0-9])/page/([0-9]+)/?$', 'index.php?post_type=menj_listing&menj_dir=letter&menj_letter=$matches[1]&paged=$matches[2]', 'top' );
	}
}
add_action( 'init', 'menj_click_dir_register', 9 );

function menj_click_dir_query_vars( $vars ) {
	return array_merge( $vars, array( 'menj_dir', 'menj_token', 'menj_letter' ) );
}
add_filter( 'query_vars', 'menj_click_dir_query_vars' );

/**
 * Flush rewrite rules once after the directory is switched on or off.
 */
function menj_click_dir_maybe_flush() {
	$state = menj_click_dir_enabled() ? 'on-' . MENJ_CLICK_VERSION : 'off';
	if ( get_option( 'menj_click_dir_rewrite' ) !== $state ) {
		flush_rewrite_rules( false );
		update_option( 'menj_click_dir_rewrite', $state, true );
	}
}
add_action( 'init', 'menj_click_dir_maybe_flush', 99 );

/**
 * Listing meta keys and their types.
 */
function menj_click_dir_meta_keys() {
	return array(
		'_menj_url'            => 'string',
		'_menj_domain'         => 'string',
		'_menj_tier'           => 'string',
		'_menj_tier_rank'      => 'integer',
		'_menj_owner_name'     => 'string',
		'_menj_owner_email'    => 'string',
		'_menj_token'          => 'string',
		'_menj_payment'        => 'string',
		'_menj_expires'        => 'string',
		'_menj_reminded'       => 'string',
		'_menj_recpr_url'      => 'string',
		'_menj_recpr_status'   => 'string',
		'_menj_recpr_checked'  => 'string',
		'_menj_recpr_deadline' => 'string',
		'_menj_http_status'    => 'integer',
		'_menj_http_checked'   => 'string',
		'_menj_http_fails'     => 'integer',
		'_menj_hits'           => 'integer',
		'_menj_rating_avg'     => 'number',
		'_menj_rating_count'   => 'integer',
		'_menj_address'        => 'string',
		'_menj_city'           => 'string',
		'_menj_country'        => 'string',
		'_menj_phone'          => 'string',
		'_menj_rel'            => 'string',
		'_menj_short_slug'     => 'string',
		'_menj_submit_ip'      => 'string',
		'_menj_changes'        => 'string',
		'_menj_subscr'         => 'string',
	);
}

/**
 * Everything about one listing as a flat array.
 *
 * @param int|WP_Post $post Listing.
 * @return array|null
 */
function menj_click_listing( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'menj_listing' !== $post->post_type ) {
		return null;
	}
	$meta = array();
	foreach ( array_keys( menj_click_dir_meta_keys() ) as $key ) {
		$meta[ substr( $key, 6 ) ] = get_post_meta( $post->ID, $key, true );
	}
	$tier          = isset( menj_click_dir_tiers()[ $meta['tier'] ] ) ? $meta['tier'] : 'free';
	$meta['tier']  = $tier;
	$meta['id']    = $post->ID;
	$meta['title'] = get_the_title( $post );
	$meta['post']  = $post;
	return $meta;
}

/**
 * The rel attribute for a listing's outbound link.
 */
function menj_click_listing_rel( array $l ) {
	if ( '' !== (string) $l['rel'] ) {
		return 'follow' === $l['rel'] ? 'noopener' : $l['rel'] . ' noopener';
	}
	if ( menj_click_dir_tiers()[ $l['tier'] ]['paid'] ) {
		return trim( menj_click_dir( 'paid_rel' ) . ' noopener' );
	}
	if ( menj_click_dir( 'reciprocal_follow' ) && 'ok' === $l['recpr_status'] ) {
		return 'noopener';
	}
	return trim( menj_click_dir( 'free_rel' ) . ' noopener' );
}

/**
 * Tracked outbound URL for a listing.
 */
function menj_click_listing_go_url( $id ) {
	return home_url( '/directory/go/' . (int) $id . '/' );
}

/* -------------------------------------------------------------------------- */
/* Owner tokens                                                                */
/* -------------------------------------------------------------------------- */

/**
 * The owner's manage token for a listing (created on first use). It works
 * like a password-reset key: whoever has the link can manage the listing.
 * "Send a new manage link" in the admin replaces it.
 *
 * @param bool $renew Replace the existing token.
 * @return string 32-character token.
 */
function menj_click_dir_token( $id, $renew = false ) {
	$token = (string) get_post_meta( $id, '_menj_token', true );
	if ( $renew || ! preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
		$token = wp_generate_password( 32, false, false );
		update_post_meta( $id, '_menj_token', $token );
	}
	return $token;
}

/**
 * Find a listing by its manage token.
 *
 * @return int Listing ID, or 0.
 */
function menj_click_dir_listing_by_token( $token ) {
	if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', (string) $token ) ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'menj_listing',
			'post_status'    => array( 'publish', 'pending', 'draft', 'menj_unconfirmed', 'menj_expired' ),
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_key'       => '_menj_token', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $token, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

function menj_click_dir_url( $view = '', $token = '' ) {
	$path = '/directory/' . ( '' !== $view ? $view . '/' : '' ) . ( '' !== $token ? $token . '/' : '' );
	return home_url( $path );
}

/* -------------------------------------------------------------------------- */
/* Hits: /directory/go/{id}/                                                   */
/* -------------------------------------------------------------------------- */

function menj_click_dir_hits_table() {
	global $wpdb;
	return $wpdb->prefix . 'menj_dir_hits';
}

function menj_click_dir_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		'CREATE TABLE ' . menj_click_dir_hits_table() . " (
  listing_id bigint(20) unsigned NOT NULL,
  hit_date date NOT NULL,
  visitor char(16) NOT NULL,
  PRIMARY KEY  (listing_id,hit_date,visitor),
  KEY hit_date (hit_date)
) {$charset};"
	);
	dbDelta(
		'CREATE TABLE ' . menj_click_dir_payments_table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  listing_id bigint(20) unsigned NOT NULL,
  tier varchar(20) NOT NULL,
  amount decimal(10,2) NOT NULL DEFAULT 0,
  quantity smallint(5) unsigned NOT NULL DEFAULT 1,
  currency char(3) NOT NULL DEFAULT 'USD',
  method varchar(20) NOT NULL DEFAULT 'invoice',
  status varchar(20) NOT NULL DEFAULT 'due',
  txn_id varchar(64) NOT NULL DEFAULT '',
  subscr_id varchar(64) NOT NULL DEFAULT '',
  payer_email varchar(191) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  paid_at datetime NULL DEFAULT NULL,
  period_end datetime NULL DEFAULT NULL,
  note text NULL,
  raw_log mediumtext NULL,
  PRIMARY KEY  (id),
  KEY listing_id (listing_id),
  KEY txn_id (txn_id),
  KEY subscr_id (subscr_id)
) {$charset};"
	);
}

/**
 * Count a visit and send the visitor on. One count per visitor per day;
 * bots and link previews aren't counted.
 */
function menj_click_dir_route() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ! menj_click_dir_enabled() ) {
		return;
	}
	if ( ! preg_match( '#^/directory/go/([0-9]+)/?$#', menj_click_request_path(), $m ) ) {
		return;
	}
	$l = menj_click_listing( (int) $m[1] );
	if ( ! $l || 'publish' !== $l['post']->post_status || '' === $l['url'] ) {
		add_action( 'wp', 'menj_click_force_404' );
		return;
	}
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : '';
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	// phpcs:enable
	if ( 'bot' !== menj_click_device_type( $ua ) && 'HEAD' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET' ) ) {
		global $wpdb;
		$visitor  = substr( hash_hmac( 'sha256', $ip . '|' . $ua . '|' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) ), 0, 16 );
		$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . menj_click_dir_hits_table() . ' (listing_id, hit_date, visitor) VALUES (%d, %s, %s)', $l['id'], gmdate( 'Y-m-d' ), $visitor ) ); // phpcs:ignore WordPress.DB
		if ( $inserted ) {
			update_post_meta( $l['id'], '_menj_hits', (int) $l['hits'] + 1 );
		}
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	wp_redirect( $l['url'], 302, 'menj.click' ); // phpcs:ignore WordPress.Security.SafeRedirect
	exit;
}
add_action( 'init', 'menj_click_dir_route', 1 );

/* -------------------------------------------------------------------------- */
/* Queries                                                                     */
/* -------------------------------------------------------------------------- */

/**
 * Sort arguments for listing queries.
 */
function menj_click_dir_order_args( $sort = null ) {
	$sort = $sort ? $sort : menj_click_dir( 'sort' );
	switch ( $sort ) {
		case 'title':
			return array( 'orderby' => 'title', 'order' => 'ASC' );
		case 'popular':
			return array( 'meta_key' => '_menj_hits', 'orderby' => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		case 'rated':
			return array( 'meta_key' => '_menj_rating_avg', 'orderby' => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		default:
			return array( 'orderby' => 'date', 'order' => 'DESC' );
	}
}

/**
 * Pinned listings for a category (or the whole directory): every live
 * Sponsored listing, then Featured ones up to the per-category limit.
 *
 * @param int $term_id Category term ID, or 0 for all.
 * @return WP_Post[]
 */
function menj_click_dir_pinned( $term_id = 0 ) {
	$base = array(
		'post_type'      => 'menj_listing',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
		'orderby'        => 'rand',
	);
	if ( $term_id ) {
		$base['tax_query'] = array( array( 'taxonomy' => 'menj_dir_category', 'terms' => (int) $term_id, 'include_children' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$sponsored = get_posts( array_merge( $base, array( 'posts_per_page' => 12, 'meta_key' => '_menj_tier', 'meta_value' => 'sponsored' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$max       = (int) menj_click_dir( 'featured_max' );
	$featured  = $max > 0 ? get_posts( array_merge( $base, array( 'posts_per_page' => $max, 'meta_key' => '_menj_tier', 'meta_value' => 'featured' ) ) ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
	return array_merge( $sponsored, $featured );
}

/**
 * Shape the main query on directory pages: page size, sort order, and
 * pinned listings kept out of the regular list.
 */
function menj_click_dir_pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	$is_archive = $query->is_post_type_archive( 'menj_listing' );
	$is_cat     = $query->is_tax( 'menj_dir_category' );
	$is_tag     = $query->is_tax( 'menj_dir_tag' );
	if ( ! $is_archive && ! $is_cat && ! $is_tag ) {
		return;
	}
	$query->set( 'posts_per_page', max( 1, (int) menj_click_dir( 'per_page' ) ) );
	foreach ( menj_click_dir_order_args( isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : null ) as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( $k, $v );
	}
	if ( $is_cat ) {
		$term = get_term_by( 'slug', $query->get( 'menj_dir_category' ), 'menj_dir_category' );
		if ( $term ) {
			$query->set( 'post__not_in', wp_list_pluck( menj_click_dir_pinned( $term->term_id ), 'ID' ) );
		}
	}
	$letter = $query->get( 'menj_letter' );
	if ( 'letter' === $query->get( 'menj_dir' ) && '' !== $letter ) {
		$query->set( 'menj_title_letter', $letter );
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'menj_click_dir_pre_get_posts' );

/**
 * A–Z browsing: titles starting with a letter (or any digit for "0").
 */
function menj_click_dir_letter_where( $where, $query ) {
	$letter = $query->get( 'menj_title_letter' );
	if ( '' === (string) $letter ) {
		return $where;
	}
	global $wpdb;
	if ( '0' === $letter ) {
		return $where . " AND {$wpdb->posts}.post_title REGEXP '^[0-9]'";
	}
	return $where . $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", $wpdb->esc_like( $letter ) . '%' );
}
add_filter( 'posts_where', 'menj_click_dir_letter_where', 10, 2 );

/**
 * Live listing counts per category, children included (cached for an hour).
 *
 * @return array term_id => count
 */
function menj_click_dir_counts() {
	$counts = get_transient( 'menj_click_dir_counts' );
	if ( is_array( $counts ) ) {
		return $counts;
	}
	$counts = array();
	$terms  = get_terms( array( 'taxonomy' => 'menj_dir_category', 'hide_empty' => false ) );
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$q                         = new WP_Query(
			array(
				'post_type'      => 'menj_listing',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'tax_query'      => array( array( 'taxonomy' => 'menj_dir_category', 'terms' => $term->term_id, 'include_children' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$counts[ $term->term_id ] = (int) $q->found_posts;
	}
	set_transient( 'menj_click_dir_counts', $counts, HOUR_IN_SECONDS );
	return $counts;
}

function menj_click_dir_flush_counts() {
	delete_transient( 'menj_click_dir_counts' );
}
add_action( 'save_post_menj_listing', 'menj_click_dir_flush_counts' );
add_action( 'deleted_post', 'menj_click_dir_flush_counts' );
add_action( 'created_menj_dir_category', 'menj_click_dir_flush_counts' );
add_action( 'delete_menj_dir_category', 'menj_click_dir_flush_counts' );

/* -------------------------------------------------------------------------- */
/* Status changes                                                              */
/* -------------------------------------------------------------------------- */

/**
 * Keep the tier rank in step with the tier (used for sorting).
 */
function menj_click_dir_set_tier( $id, $tier ) {
	$tiers = menj_click_dir_tiers();
	$tier  = isset( $tiers[ $tier ] ) ? $tier : 'free';
	update_post_meta( $id, '_menj_tier', $tier );
	update_post_meta( $id, '_menj_tier_rank', $tiers[ $tier ]['rank'] );
}

/**
 * Move a listing to its next state after confirmation or payment:
 * payment due → review (if required) → live.
 */
function menj_click_dir_advance( $id ) {
	$l = menj_click_listing( $id );
	if ( ! $l ) {
		return;
	}
	$tier = menj_click_dir_tiers()[ $l['tier'] ];
	if ( $tier['paid'] && 'paid' !== $l['payment'] ) {
		update_post_meta( $id, '_menj_payment', 'due' );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) );
		menj_click_dir_mail( 'payment', $id );
		return;
	}
	$needs_review = $tier['paid'] ? menj_click_dir( 'paid_approval' ) : menj_click_dir( 'free_approval' );
	if ( $needs_review ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) );
		menj_click_dir_mail( 'received', $id );
		menj_click_dir_mail( 'admin', $id );
	} else {
		menj_click_dir_approve( $id );
	}
}

function menj_click_dir_approve( $id ) {
	$GLOBALS['menj_click_dir_approving'] = true; // The editor hook below stays quiet.
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	$GLOBALS['menj_click_dir_approving'] = false;
	menj_click_dir_mail( 'approved', $id );
}

function menj_click_dir_reject( $id, $reason = '' ) {
	update_post_meta( $id, '_menj_reject_reason', sanitize_textarea_field( $reason ) );
	menj_click_dir_mail( 'rejected', $id, array( 'reason' => $reason ) );
	wp_trash_post( $id );
}

/**
 * Listings published by hand (classic or block editor, Quick Edit) send
 * the "approved" email once. Approvals made through the directory's own
 * actions already send it.
 */
function menj_click_dir_on_publish( $new, $old, $post ) {
	if ( 'menj_listing' !== $post->post_type || 'publish' !== $new || ! in_array( $old, array( 'pending', 'menj_unconfirmed', 'menj_expired' ), true ) ) {
		return;
	}
	if ( ! empty( $GLOBALS['menj_click_dir_approving'] ) || ! is_user_logged_in() || ! current_user_can( 'edit_post', $post->ID ) ) {
		return;
	}
	menj_click_dir_mail( 'approved', $post->ID );
}
add_action( 'transition_post_status', 'menj_click_dir_on_publish', 10, 3 );

/* -------------------------------------------------------------------------- */
/* Email                                                                       */
/* -------------------------------------------------------------------------- */

/**
 * Send one of the directory emails.
 *
 * @param string $type  confirm|received|payment|paid|approved|rejected|expiring|expired|reciprocal|manage|admin.
 * @param int    $id    Listing ID.
 * @param array  $extra Extra placeholders, e.g. token, reason.
 */
function menj_click_dir_mail( $type, $id, array $extra = array() ) {
	$l = menj_click_listing( $id );
	if ( ! $l ) {
		return false;
	}
	$tpl = menj_click_settings( 'dir_emails' );
	if ( empty( $tpl[ $type . '_subject' ] ) ) {
		return false;
	}
	$to = 'admin' === $type ? ( is_email( menj_click_dir( 'notify_email' ) ) ? menj_click_dir( 'notify_email' ) : get_option( 'admin_email' ) ) : $l['owner_email'];
	if ( ! is_email( $to ) ) {
		return false;
	}
	$token = 'admin' === $type ? '' : menj_click_dir_token( $id );
	$tier   = menj_click_dir_tiers()[ $l['tier'] ];
	$vars   = array(
		'site'           => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'name'           => '' !== $l['owner_name'] ? $l['owner_name'] : __( 'there', 'menj-click' ),
		'title'          => wp_specialchars_decode( $l['title'], ENT_QUOTES ),
		'url'            => $l['url'],
		'tier'           => $tier['label'],
		'amount'         => menj_click_dir_money( $tier['price'] ),
		'expiry'         => $l['expires'] ? date_i18n( get_option( 'date_format' ), strtotime( $l['expires'] ) ) : '',
		'listing_url'    => get_permalink( $id ),
		'manage_url'     => $token ? menj_click_dir_url( 'manage', $token ) : '',
		'pay_url'        => $token ? menj_click_dir_url( 'pay', $token ) : '',
		'confirm_url'    => $token ? menj_click_dir_url( 'confirm', $token ) : '',
		'reciprocal_url' => $l['recpr_url'],
		'deadline'       => $l['recpr_deadline'] ? date_i18n( get_option( 'date_format' ), strtotime( $l['recpr_deadline'] ) ) : '',
		'reason'         => isset( $extra['reason'] ) ? $extra['reason'] : '',
		'event'          => isset( $extra['event'] ) ? $extra['event'] : __( 'A new listing is waiting for review.', 'menj-click' ),
		'admin_url'      => admin_url( 'post.php?post=' . $id . '&action=edit' ),
	);
	$replace = array();
	foreach ( $vars as $k => $v ) {
		$replace[ '{' . $k . '}' ] = (string) $v;
	}
	$subject = strtr( $tpl[ $type . '_subject' ], $replace );
	$body    = strtr( $tpl[ $type . '_body' ], $replace );
	$body    = preg_replace( "/\n{3,}/", "\n\n", $body );
	return wp_mail( $to, $subject, $body );
}

/* -------------------------------------------------------------------------- */
/* Reviews (comments with a star rating)                                       */
/* -------------------------------------------------------------------------- */

function menj_click_dir_comments_open( $open, $post_id ) {
	if ( 'menj_listing' === get_post_type( $post_id ) ) {
		return (bool) menj_click_dir( 'reviews' ) && 'publish' === get_post_status( $post_id );
	}
	return $open;
}
add_filter( 'comments_open', 'menj_click_dir_comments_open', 10, 2 );

function menj_click_dir_comment_form_defaults( $defaults ) {
	if ( 'menj_listing' !== get_post_type() ) {
		return $defaults;
	}
	$defaults['title_reply']  = __( 'Write a review', 'menj-click' );
	$defaults['comment_field'] = str_replace( '>' . _x( 'Comment', 'noun' ) . ' <', '>' . __( 'Review', 'menj-click' ) . ' <', $defaults['comment_field'] );
	$defaults['label_submit'] = __( 'Post review', 'menj-click' );
	if ( menj_click_dir( 'reviews_rating' ) ) {
		$stars = '';
		for ( $i = 5; $i >= 1; $i-- ) {
			/* translators: %d: number of stars */
			$stars .= '<input type="radio" id="menj-rating-' . $i . '" name="menj_rating" value="' . $i . '" required><label for="menj-rating-' . $i . '" title="' . esc_attr( sprintf( _n( '%d star', '%d stars', $i, 'menj-click' ), $i ) ) . '">★</label>';
		}
		$defaults['comment_field'] = '<fieldset class="menj-rating-input"><legend>' . esc_html__( 'Your rating', 'menj-click' ) . '</legend><div class="menj-rating-input__stars">' . $stars . '</div></fieldset>' . $defaults['comment_field'];
	}
	return $defaults;
}
add_filter( 'comment_form_defaults', 'menj_click_dir_comment_form_defaults' );

function menj_click_dir_check_rating( $data ) {
	if ( 'menj_listing' === get_post_type( (int) $data['comment_post_ID'] ) && menj_click_dir( 'reviews_rating' ) && in_array( ( $data['comment_type'] ?? '' ), array( '', 'comment', 'review' ), true ) ) {
		$rating = isset( $_POST['menj_rating'] ) ? (int) $_POST['menj_rating'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $rating < 1 || $rating > 5 ) {
			wp_die( esc_html__( 'Please choose a rating from 1 to 5 stars.', 'menj-click' ), '', array( 'response' => 400, 'back_link' => true ) );
		}
	}
	return $data;
}
add_filter( 'preprocess_comment', 'menj_click_dir_check_rating' );

function menj_click_dir_save_rating( $comment_id ) {
	$comment = get_comment( $comment_id );
	if ( $comment && 'menj_listing' === get_post_type( $comment->comment_post_ID ) && isset( $_POST['menj_rating'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$rating = max( 1, min( 5, (int) $_POST['menj_rating'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_comment_meta( $comment_id, 'menj_rating', $rating );
		menj_click_dir_recount_rating( $comment->comment_post_ID );
	}
}
add_action( 'comment_post', 'menj_click_dir_save_rating' );

function menj_click_dir_rating_status_change( $new, $old, $comment ) {
	if ( 'menj_listing' === get_post_type( $comment->comment_post_ID ) ) {
		menj_click_dir_recount_rating( $comment->comment_post_ID );
	}
}
add_action( 'transition_comment_status', 'menj_click_dir_rating_status_change', 10, 3 );

function menj_click_dir_recount_rating( $post_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, AVG(m.meta_value) AS avg FROM {$wpdb->comments} c JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_ID AND m.meta_key = 'menj_rating' WHERE c.comment_post_ID = %d AND c.comment_approved = '1'", $post_id ) );
	update_post_meta( $post_id, '_menj_rating_count', (int) $row->n );
	update_post_meta( $post_id, '_menj_rating_avg', $row->n ? round( (float) $row->avg, 2 ) : 0 );
}

function menj_click_dir_comment_stars( $text, $comment = null ) {
	if ( $comment && 'menj_listing' === get_post_type( $comment->comment_post_ID ) ) {
		$rating = (int) get_comment_meta( $comment->comment_ID, 'menj_rating', true );
		if ( $rating ) {
			$text = menj_click_dir_stars( $rating ) . $text;
		}
	}
	return $text;
}
add_filter( 'comment_text', 'menj_click_dir_comment_stars', 10, 2 );

/**
 * Star rating markup.
 */
function menj_click_dir_stars( $value, $count = null ) {
	$value = max( 0, min( 5, (float) $value ) );
	/* translators: %s: rating out of 5 */
	$label = sprintf( __( 'Rated %s out of 5', 'menj-click' ), number_format_i18n( $value, 1 ) );
	$out   = '<span class="menj-stars" role="img" aria-label="' . esc_attr( $label ) . '" style="--menj-stars:' . esc_attr( $value ) . '"></span>';
	if ( null !== $count ) {
		/* translators: %d: number of reviews */
		$out .= ' <span class="menj-stars__count">' . esc_html( sprintf( _n( '%d review', '%d reviews', $count, 'menj-click' ), $count ) ) . '</span>';
	}
	return $out;
}

/* -------------------------------------------------------------------------- */
/* Rendering                                                                   */
/* -------------------------------------------------------------------------- */

/**
 * A letter tile standing in for a logo, coloured from the domain.
 */
function menj_click_dir_monogram( array $l ) {
	if ( has_post_thumbnail( $l['id'] ) ) {
		return '<span class="menj-dir-logo">' . get_the_post_thumbnail( $l['id'], 'thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ) . '</span>';
	}
	$tones  = array_keys( menj_click_tone_choices() );
	$source = '' !== $l['domain'] ? $l['domain'] : $l['title'];
	$letter = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( preg_replace( '/^www\./', '', $source ), 0, 1 ) ) : strtoupper( substr( $source, 0, 1 ) );
	$tone   = $tones[ abs( crc32( $source ) ) % count( $tones ) ];
	return '<span class="menj-dir-logo menj-tile menj-tile--' . esc_attr( $tone ) . '" aria-hidden="true">' . esc_html( $letter ) . '</span>';
}

/**
 * One listing card.
 */
function menj_click_dir_card( $post ) {
	$l = menj_click_listing( $post );
	if ( ! $l ) {
		return '';
	}
	$badge = '';
	if ( 'sponsored' === $l['tier'] ) {
		$badge = '<span class="menj-dir-badge is-sponsored">' . esc_html__( 'Sponsored', 'menj-click' ) . '</span>';
	} elseif ( 'featured' === $l['tier'] ) {
		$badge = '<span class="menj-dir-badge is-featured">' . esc_html__( 'Featured', 'menj-click' ) . '</span>';
	}
	$excerpt = has_excerpt( $l['id'] ) ? get_the_excerpt( $l['id'] ) : wp_trim_words( wp_strip_all_tags( $l['post']->post_content ), 28 );
	$rating  = ( menj_click_dir( 'reviews' ) && (int) $l['rating_count'] > 0 ) ? '<span class="menj-dir-card__rating">' . menj_click_dir_stars( $l['rating_avg'], (int) $l['rating_count'] ) . '</span>' : '';

	return '<article class="menj-dir-card is-' . esc_attr( $l['tier'] ) . '">'
		. menj_click_dir_monogram( $l )
		. '<div class="menj-dir-card__body">'
		. '<h3 class="menj-dir-card__title"><a href="' . esc_url( get_permalink( $l['id'] ) ) . '">' . esc_html( $l['title'] ) . '</a>' . $badge . '</h3>'
		. '<p class="menj-dir-card__domain">' . esc_html( $l['domain'] ) . '</p>'
		. ( '' !== $excerpt ? '<p class="menj-dir-card__desc">' . esc_html( $excerpt ) . '</p>' : '' )
		. $rating
		. '</div>'
		. '<a class="menj-dir-card__visit" href="' . esc_url( menj_click_listing_go_url( $l['id'] ) ) . '" rel="' . esc_attr( menj_click_listing_rel( $l ) ) . '" target="_blank" aria-label="' . esc_attr( sprintf( /* translators: %s: site name */ __( 'Visit %s (opens in a new tab)', 'menj-click' ), $l['title'] ) ) . '">'
		. menj_click_icon( 'square-arrow-out-up-right', array( 'size' => 18 ) ) . '</a>'
		. '</article>';
}

function menj_click_dir_cards( array $posts, $class = '' ) {
	if ( ! $posts ) {
		return '';
	}
	return '<div class="menj-dir-list ' . esc_attr( $class ) . '">' . implode( '', array_map( 'menj_click_dir_card', $posts ) ) . '</div>';
}

/**
 * Directory header: title, intro, search and the submit button.
 */
function menj_click_dir_header( $title = '', $intro = null, $crumbs = array() ) {
	$title = '' !== $title ? $title : menj_click_dir( 'title' );
	$intro = null === $intro ? menj_click_dir( 'intro' ) : $intro;
	$q     = isset( $_GET['dq'] ) ? sanitize_text_field( wp_unslash( $_GET['dq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$html  = '<header class="menj-dir-head">';
	if ( $crumbs ) {
		$html .= '<nav class="menj-dir-crumbs" aria-label="' . esc_attr__( 'Breadcrumbs', 'menj-click' ) . '"><ol>';
		foreach ( $crumbs as $crumb ) {
			$html .= '<li><a href="' . esc_url( $crumb[1] ) . '">' . esc_html( $crumb[0] ) . '</a></li>';
		}
		$html .= '</ol></nav>';
	}
	$html .= '<div class="menj-dir-head__row"><div><h1 class="menj-dir-head__title">' . esc_html( $title ) . '</h1>';
	if ( '' !== trim( (string) $intro ) ) {
		$html .= '<p class="menj-dir-head__intro">' . esc_html( $intro ) . '</p>';
	}
	$html .= '</div>';
	$tools = ! in_array( get_query_var( 'menj_dir' ), array( 'submit', 'manage', 'confirm', 'pay' ), true );
	if ( $tools && menj_click_dir( 'submissions' ) ) {
		$html .= '<a class="menj-dir-button" href="' . esc_url( menj_click_dir_url( 'submit' ) ) . '">' . menj_click_icon( 'plus', array( 'size' => 18 ) ) . esc_html__( 'Submit a site', 'menj-click' ) . '</a>';
	}
	$html .= '</div>';
	if ( ! $tools ) {
		return $html . '</header>';
	}
	$html .= '<form class="menj-dir-search" role="search" method="get" action="' . esc_url( menj_click_dir_url() ) . '">'
		. '<label class="screen-reader-text" for="menj-dq">' . esc_html__( 'Search the directory', 'menj-click' ) . '</label>'
		. menj_click_icon( 'search', array( 'size' => 18, 'class' => 'menj-dir-search__icon' ) )
		. '<input type="search" id="menj-dq" name="dq" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Search sites, tools and businesses', 'menj-click' ) . '">'
		. '<button type="submit">' . esc_html__( 'Search', 'menj-click' ) . '</button></form>';
	return $html . '</header>';
}

/**
 * A–Z bar.
 */
function menj_click_dir_letters( $current = '' ) {
	$out = '<nav class="menj-dir-az" aria-label="' . esc_attr__( 'Browse A to Z', 'menj-click' ) . '"><ul>';
	foreach ( array_merge( array( '0' ), range( 'a', 'z' ) ) as $letter ) {
		$label = '0' === $letter ? '0–9' : strtoupper( $letter );
		$out  .= '<li><a href="' . esc_url( menj_click_dir_url( 'a-z/' . $letter ) ) . '"' . ( $current === $letter ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}
	return $out . '</ul></nav>';
}

/**
 * Category grid: top-level categories with counts and a few children.
 *
 * @param int $parent Parent term ID.
 */
function menj_click_dir_category_grid( $parent = 0 ) {
	$terms = get_terms( array( 'taxonomy' => 'menj_dir_category', 'parent' => $parent, 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( ! is_array( $terms ) || ! $terms ) {
		return '';
	}
	$counts  = menj_click_dir_counts();
	$preview = (int) menj_click_dir( 'cats_preview' );
	$out     = '<ul class="menj-dir-cats' . ( $parent ? ' is-sub' : '' ) . '">';
	foreach ( $terms as $term ) {
		$count = isset( $counts[ $term->term_id ] ) ? $counts[ $term->term_id ] : 0;
		$out  .= '<li class="menj-dir-cat"><a class="menj-dir-cat__link" href="' . esc_url( get_term_link( $term ) ) . '"><span class="menj-dir-cat__name">' . esc_html( $term->name ) . '</span>';
		if ( menj_click_dir( 'show_counts' ) ) {
			$out .= '<span class="menj-dir-cat__count">' . esc_html( number_format_i18n( $count ) ) . '</span>';
		}
		$out .= '</a>';
		if ( ! $parent && $preview > 0 ) {
			$children = get_terms( array( 'taxonomy' => 'menj_dir_category', 'parent' => $term->term_id, 'hide_empty' => false, 'number' => $preview, 'orderby' => 'name' ) );
			if ( is_array( $children ) && $children ) {
				$links = array();
				foreach ( $children as $child ) {
					$links[] = '<a href="' . esc_url( get_term_link( $child ) ) . '">' . esc_html( $child->name ) . '</a>';
				}
				$out .= '<p class="menj-dir-cat__children">' . implode( ', ', $links ) . '</p>';
			}
		}
		$out .= '</li>';
	}
	return $out . '</ul>';
}

/**
 * Pagination for the main query (or a given one).
 */
function menj_click_dir_pagination( $query = null, $format_arg = '' ) {
	global $wp_query;
	$query = $query ? $query : $wp_query;
	if ( $query->max_num_pages < 2 ) {
		return '';
	}
	$args = array(
		'total'     => $query->max_num_pages,
		'current'   => max( 1, (int) ( $format_arg ? ( isset( $_GET[ $format_arg ] ) ? absint( $_GET[ $format_arg ] ) : 1 ) : get_query_var( 'paged' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'prev_text' => __( 'Previous', 'menj-click' ),
		'next_text' => __( 'Next', 'menj-click' ),
	);
	if ( $format_arg ) {
		$args['base']   = add_query_arg( $format_arg, '%#%' );
		$args['format'] = '';
	}
	return '<nav class="menj-dir-pages" aria-label="' . esc_attr__( 'Pages', 'menj-click' ) . '">' . paginate_links( $args ) . '</nav>';
}

function menj_click_dir_sort_menu() {
	$current = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : menj_click_dir( 'sort' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$options = array(
		'newest'  => __( 'Newest', 'menj-click' ),
		'title'   => __( 'A–Z', 'menj-click' ),
		'popular' => __( 'Most visited', 'menj-click' ),
	);
	if ( menj_click_dir( 'reviews' ) ) {
		$options['rated'] = __( 'Top rated', 'menj-click' );
	}
	$out = '<nav class="menj-dir-sort" aria-label="' . esc_attr__( 'Sort listings', 'menj-click' ) . '"><span>' . esc_html__( 'Sort:', 'menj-click' ) . '</span>';
	foreach ( $options as $key => $label ) {
		$out .= '<a href="' . esc_url( add_query_arg( 'sort', $key, remove_query_arg( 'paged' ) ) ) . '"' . ( $key === $current ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}
	return $out . '</nav>';
}

/**
 * The directory block: renders the right view for the current URL.
 */
function menj_click_render_directory( $attrs ) {
	if ( ! menj_click_dir_enabled() ) {
		return '';
	}
	wp_enqueue_style( 'menj-click-directory' );
	$view = get_query_var( 'menj_dir' );
	$html = '';

	if ( in_array( $view, array( 'submit', 'manage', 'confirm', 'pay' ), true ) ) {
		wp_enqueue_script( 'menj-click-directory' );
		$html = call_user_func( 'menj_click_dir_view_' . $view );
	} elseif ( is_tax( 'menj_dir_category' ) ) {
		$html = menj_click_dir_view_category( get_queried_object() );
	} elseif ( is_tax( 'menj_dir_tag' ) ) {
		$term = get_queried_object();
		$html = menj_click_dir_header( '#' . $term->name, $term->description, array( array( menj_click_dir( 'title' ), menj_click_dir_url() ) ) )
			. menj_click_dir_main_list();
	} elseif ( 'letter' === $view ) {
		$letter = (string) get_query_var( 'menj_letter' );
		/* translators: %s: letter */
		$html = menj_click_dir_header( sprintf( __( 'Sites starting with %s', 'menj-click' ), '0' === $letter ? '0–9' : strtoupper( $letter ) ), '', array( array( menj_click_dir( 'title' ), menj_click_dir_url() ) ) )
			. menj_click_dir_letters( $letter ) . menj_click_dir_main_list();
	} elseif ( isset( $_GET['dq'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_GET['dq'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$html = menj_click_dir_view_search( sanitize_text_field( wp_unslash( $_GET['dq'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	} else {
		$html = menj_click_dir_view_home();
	}
	return '<div ' . get_block_wrapper_attributes( array( 'class' => 'menj-dir' ) ) . '>' . $html . '</div>';
}

function menj_click_dir_main_list() {
	global $wp_query;
	$posts = $wp_query->posts;
	if ( ! $posts ) {
		return '<p class="menj-dir-empty">' . esc_html__( 'Nothing listed here yet.', 'menj-click' ) . '</p>';
	}
	return menj_click_dir_sort_menu() . menj_click_dir_cards( $posts ) . menj_click_dir_pagination();
}

function menj_click_dir_section( $title, $body, $class = '' ) {
	if ( '' === $body ) {
		return '';
	}
	return '<section class="menj-dir-section ' . esc_attr( $class ) . '"><h2 class="menj-dir-section__title">' . esc_html( $title ) . '</h2>' . $body . '</section>';
}

function menj_click_dir_view_home() {
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$html  = menj_click_dir_header();
	if ( 1 === $paged ) {
		$sponsored = get_posts( array( 'post_type' => 'menj_listing', 'post_status' => 'publish', 'posts_per_page' => 6, 'orderby' => 'rand', 'meta_key' => '_menj_tier', 'meta_value' => 'sponsored' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$featured  = get_posts( array( 'post_type' => 'menj_listing', 'post_status' => 'publish', 'posts_per_page' => 6, 'orderby' => 'rand', 'meta_key' => '_menj_tier', 'meta_value' => 'featured' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$html     .= menj_click_dir_section( __( 'Sponsored', 'menj-click' ), menj_click_dir_cards( $sponsored, 'is-grid' ), 'is-sponsored' );
		$html     .= menj_click_dir_section( __( 'Categories', 'menj-click' ), menj_click_dir_category_grid() );
		$html     .= menj_click_dir_section( __( 'Featured', 'menj-click' ), menj_click_dir_cards( $featured, 'is-grid' ) );
	}
	$html .= menj_click_dir_section( 1 === $paged ? __( 'Recently added', 'menj-click' ) : __( 'All listings', 'menj-click' ), menj_click_dir_main_list() );
	return $html . menj_click_dir_letters();
}

function menj_click_dir_view_category( $term ) {
	$crumbs = array( array( menj_click_dir( 'title' ), menj_click_dir_url() ) );
	foreach ( array_reverse( get_ancestors( $term->term_id, 'menj_dir_category', 'taxonomy' ) ) as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, 'menj_dir_category' );
		$crumbs[] = array( $ancestor->name, get_term_link( $ancestor ) );
	}
	$html = menj_click_dir_header( $term->name, $term->description, $crumbs );
	$html .= menj_click_dir_category_grid( $term->term_id );
	if ( max( 1, (int) get_query_var( 'paged' ) ) === 1 ) {
		$html .= menj_click_dir_cards( menj_click_dir_pinned( $term->term_id ), 'is-pinned' );
	}
	return $html . menj_click_dir_main_list();
}

function menj_click_dir_view_search( $q ) {
	$paged = isset( $_GET['dp'] ) ? max( 1, absint( $_GET['dp'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$query = new WP_Query(
		array_merge(
			array(
				'post_type'      => 'menj_listing',
				'post_status'    => 'publish',
				's'              => $q,
				'posts_per_page' => (int) menj_click_dir( 'per_page' ),
				'paged'          => $paged,
			),
			array( 'orderby' => 'relevance' )
		)
	);
	/* translators: %s: search terms */
	$html = menj_click_dir_header( sprintf( __( 'Results for “%s”', 'menj-click' ), $q ), '', array( array( menj_click_dir( 'title' ), menj_click_dir_url() ) ) );
	if ( ! $query->posts ) {
		return $html . '<p class="menj-dir-empty">' . esc_html__( 'No listings match. Try fewer or different words.', 'menj-click' ) . '</p>';
	}
	return $html . menj_click_dir_cards( $query->posts ) . menj_click_dir_pagination( $query, 'dp' );
}

/**
 * The single-listing block.
 */
function menj_click_render_listing( $attrs ) {
	$post = get_post();
	$l    = $post ? menj_click_listing( $post ) : null;
	if ( ! $l && menj_click_is_preview() ) {
		// Site Editor: preview with the newest live listing.
		$latest = get_posts( array( 'post_type' => 'menj_listing', 'post_status' => 'publish', 'posts_per_page' => 1 ) );
		if ( ! $latest ) {
			return '<p class="menj-dir-empty">' . esc_html__( 'Listing details appear here.', 'menj-click' ) . '</p>';
		}
		$GLOBALS['post'] = $latest[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $latest[0] );
		$html = menj_click_render_listing( $attrs );
		wp_reset_postdata();
		return $html;
	}
	if ( ! $l ) {
		return '';
	}
	wp_enqueue_style( 'menj-click-directory' );
	$crumbs = array( array( menj_click_dir( 'title' ), menj_click_dir_url() ) );
	$terms  = get_the_terms( $l['id'], 'menj_dir_category' );
	$cat    = ( is_array( $terms ) && $terms ) ? $terms[0] : null;
	if ( $cat ) {
		foreach ( array_reverse( get_ancestors( $cat->term_id, 'menj_dir_category', 'taxonomy' ) ) as $aid ) {
			$a        = get_term( $aid, 'menj_dir_category' );
			$crumbs[] = array( $a->name, get_term_link( $a ) );
		}
		$crumbs[] = array( $cat->name, get_term_link( $cat ) );
	}

	$html = '<nav class="menj-dir-crumbs" aria-label="' . esc_attr__( 'Breadcrumbs', 'menj-click' ) . '"><ol>';
	foreach ( $crumbs as $c ) {
		$html .= '<li><a href="' . esc_url( $c[1] ) . '">' . esc_html( $c[0] ) . '</a></li>';
	}
	$html .= '</ol></nav>';

	$html .= '<header class="menj-listing__head">' . menj_click_dir_monogram( $l ) . '<div>';
	$html .= '<h1 class="menj-listing__title">' . esc_html( $l['title'] );
	if ( 'free' !== $l['tier'] ) {
		$html .= ' <span class="menj-dir-badge is-' . esc_attr( $l['tier'] ) . '">' . esc_html( menj_click_dir_tiers()[ $l['tier'] ]['label'] ) . '</span>';
	}
	$html .= '</h1><p class="menj-listing__domain">' . esc_html( $l['domain'] ) . '</p>';
	if ( menj_click_dir( 'reviews' ) && (int) $l['rating_count'] > 0 ) {
		$html .= '<p class="menj-listing__rating">' . menj_click_dir_stars( $l['rating_avg'], (int) $l['rating_count'] ) . '</p>';
	}
	$html .= '</div><a class="menj-dir-button" href="' . esc_url( menj_click_listing_go_url( $l['id'] ) ) . '" rel="' . esc_attr( menj_click_listing_rel( $l ) ) . '" target="_blank">' . esc_html__( 'Visit site', 'menj-click' ) . menj_click_icon( 'square-arrow-out-up-right', array( 'size' => 16 ) ) . '</a></header>';

	$content = apply_filters( 'the_content', $l['post']->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	$html   .= '<div class="menj-listing__body">' . $content . '</div>';

	$facts = array();
	/* translators: %s: date */
	$facts[] = array( __( 'Listed', 'menj-click' ), esc_html( get_the_date( '', $l['id'] ) ) );
	$facts[] = array( __( 'Visits', 'menj-click' ), esc_html( number_format_i18n( (int) $l['hits'] ) ) );
	if ( menj_click_dir( 'business_fields' ) ) {
		$place = implode( ', ', array_filter( array( $l['address'], $l['city'], $l['country'] ) ) );
		if ( '' !== $place ) {
			$facts[] = array( __( 'Address', 'menj-click' ), esc_html( $place ) );
		}
		if ( '' !== $l['phone'] ) {
			$facts[] = array( __( 'Phone', 'menj-click' ), '<a href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $l['phone'] ) ) . '">' . esc_html( $l['phone'] ) . '</a>' );
		}
	}
	$tags = menj_click_dir( 'tags' ) ? get_the_term_list( $l['id'], 'menj_dir_tag', '', ', ' ) : '';
	if ( $tags && ! is_wp_error( $tags ) ) {
		$facts[] = array( __( 'Tags', 'menj-click' ), $tags );
	}
	if ( '' !== $l['short_slug'] && isset( menj_click_link_map()[ $l['short_slug'] ] ) ) {
		$short   = home_url( '/' . $l['short_slug'] );
		$facts[] = array( __( 'Short link', 'menj-click' ), '<a href="' . esc_url( $short ) . '"><code>' . esc_html( menj_click_host() . '/' . $l['short_slug'] ) . '</code></a>' );
	}
	$html .= '<dl class="menj-listing__facts">';
	foreach ( $facts as $f ) {
		$html .= '<div><dt>' . esc_html( $f[0] ) . '</dt><dd>' . $f[1] . '</dd></div>'; // Values escaped above.
	}
	$html .= '</dl>';

	if ( menj_click_dir( 'show_qr' ) ) {
		$qr_text = ( '' !== $l['short_slug'] && isset( menj_click_link_map()[ $l['short_slug'] ] ) ) ? menj_click_qr_url( $l['short_slug'] ) : get_permalink( $l['id'] );
		/* translators: %s: listing title */
		$html .= '<figure class="menj-listing__qr">' . menj_click_qr_svg( $qr_text, array( 'title' => sprintf( __( 'QR code for %s', 'menj-click' ), $l['title'] ) ) ) . '<figcaption>' . esc_html__( 'Scan to open this listing on your phone.', 'menj-click' ) . '</figcaption></figure>';
	}

	return '<div ' . get_block_wrapper_attributes( array( 'class' => 'menj-listing' ) ) . '>' . $html . '</div>';
}

/**
 * Page titles for the directory's own views.
 */
function menj_click_dir_document_title( $parts ) {
	$titles = array(
		'submit'  => __( 'Submit a site', 'menj-click' ),
		'manage'  => __( 'Manage your listing', 'menj-click' ),
		'confirm' => __( 'Confirm your listing', 'menj-click' ),
		'pay'     => __( 'Payment', 'menj-click' ),
	);
	$view = get_query_var( 'menj_dir' );
	if ( isset( $titles[ $view ] ) ) {
		$parts['title'] = $titles[ $view ];
	} elseif ( is_post_type_archive( 'menj_listing' ) ) {
		$parts['title'] = menj_click_dir( 'title' );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'menj_click_dir_document_title' );

/**
 * Keep private views out of search engines.
 */
function menj_click_dir_robots( $robots ) {
	if ( in_array( get_query_var( 'menj_dir' ), array( 'submit', 'manage', 'confirm', 'pay' ), true ) || isset( $_GET['dq'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$robots['noindex'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'menj_click_dir_robots' );

/**
 * Directory assets (loaded only where the directory renders).
 */
function menj_click_dir_register_assets() {
	wp_register_style( 'menj-click-directory', MENJ_CLICK_URI . '/assets/css/directory.css', array( 'menj-click-front' ), MENJ_CLICK_VERSION );
	wp_register_script( 'menj-click-directory', MENJ_CLICK_URI . '/assets/js/directory.js', array(), MENJ_CLICK_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
}
add_action( 'init', 'menj_click_dir_register_assets' );

/**
 * Hide the Directory item in the header when the directory is off.
 */
function menj_click_dir_nav_item( $html, $block ) {
	if ( ! menj_click_dir_enabled() && isset( $block['attrs']['url'] ) && '/directory/' === $block['attrs']['url'] ) {
		return '';
	}
	return $html;
}
add_filter( 'render_block_core/navigation-link', 'menj_click_dir_nav_item', 5, 2 );

/**
 * No "Reviews" section on listings when reviews are off.
 */
function menj_click_dir_hide_reviews( $html ) {
	if ( is_singular( 'menj_listing' ) && ! menj_click_dir( 'reviews' ) ) {
		return '';
	}
	return $html;
}
add_filter( 'render_block_core/comments', 'menj_click_dir_hide_reviews' );
