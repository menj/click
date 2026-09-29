<?php
/**
 * Directory admin: the Directory settings tab (with sub-tabs), the
 * listings screen (columns, quick moderation), the listing details box,
 * category options and CSV import/export.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------- */
/* Settings tab                                                                */
/* -------------------------------------------------------------------------- */

function menj_click_dir_subtabs() {
	return array(
		'general'     => __( 'General', 'menj-click' ),
		'tiers'       => __( 'Listing types', 'menj-click' ),
		'payments'    => __( 'Payments', 'menj-click' ),
		'reciprocal'  => __( 'Link back & checks', 'menj-click' ),
		'submissions' => __( 'Submissions & spam', 'menj-click' ),
		'emails'      => __( 'Emails', 'menj-click' ),
		'tools'       => __( 'Import & export', 'menj-click' ),
	);
}

/**
 * Field schema per sub-tab: key => type (bool|int|text|textarea|choice:…|money|email|url).
 */
function menj_click_dir_schema() {
	return array(
		'general'     => array(
			'enabled' => 'bool', 'title' => 'text', 'intro' => 'textarea', 'per_page' => 'int', 'sort' => 'choice:newest,title,popular,rated',
			'cats_preview' => 'int', 'show_counts' => 'bool', 'tags' => 'bool', 'reviews' => 'bool', 'reviews_rating' => 'bool', 'show_qr' => 'bool', 'business_fields' => 'bool',
		),
		'tiers'       => array(
			'free_enabled' => 'bool', 'free_approval' => 'bool', 'free_rel' => 'choice:ugc nofollow,ugc,nofollow,follow',
			'featured_enabled' => 'bool', 'featured_price' => 'money', 'featured_period' => 'choice:once,month,year', 'featured_max' => 'int',
			'sponsored_enabled' => 'bool', 'sponsored_price' => 'money', 'sponsored_period' => 'choice:once,month,year',
			'paid_rel' => 'choice:sponsored,sponsored nofollow,nofollow,follow', 'paid_approval' => 'bool', 'expire_action' => 'choice:downgrade,hide', 'reminder_days' => 'int',
		),
		'payments'    => array(
			'currency' => 'choice:USD,EUR,GBP,MYR,SGD,AUD,CAD,JPY', 'paypal_enabled' => 'bool', 'paypal_email' => 'email', 'paypal_sandbox' => 'bool', 'paypal_subscriptions' => 'bool', 'max_periods' => 'int', 'multi_discount' => 'int', 'invoice_text' => 'textarea',
		),
		'reciprocal'  => array(
			'reciprocal' => 'choice:off,optional,required', 'reciprocal_follow' => 'bool', 'reciprocal_dofollow' => 'bool', 'reciprocal_recheck' => 'int', 'reciprocal_grace' => 'int',
			'backlink_url' => 'url', 'backlink_text' => 'text', 'checker' => 'bool', 'checker_batch' => 'int', 'checker_every' => 'int', 'checker_fails' => 'int', 'checker_hide' => 'bool',
		),
		'submissions' => array(
			'submissions' => 'bool', 'email_confirm' => 'bool', 'title_min' => 'int', 'title_max' => 'int', 'desc_min' => 'int', 'desc_max' => 'int',
			'unique_domain' => 'bool', 'check_online' => 'bool', 'max_per_ip' => 'int', 'terms' => 'textarea', 'notify_email' => 'email',
			'banned_domains' => 'textarea', 'banned_emails' => 'textarea', 'banned_ips' => 'textarea', 'banned_words' => 'textarea',
		),
	);
}

function menj_click_dir_sanitize_value( $type, $value ) {
	if ( 'bool' === $type ) {
		return empty( $value ) ? 0 : 1;
	}
	if ( 'int' === $type ) {
		return max( 0, min( 10000, (int) $value ) );
	}
	if ( 'money' === $type ) {
		return number_format( max( 0, (float) str_replace( ',', '.', (string) $value ) ), 2, '.', '' );
	}
	if ( 'email' === $type ) {
		$email = sanitize_email( $value );
		return is_email( $email ) ? $email : '';
	}
	if ( 'url' === $type ) {
		return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
	}
	if ( 'textarea' === $type ) {
		return sanitize_textarea_field( $value );
	}
	if ( 0 === strpos( $type, 'choice:' ) ) {
		$choices = explode( ',', substr( $type, 7 ) );
		return in_array( (string) $value, $choices, true ) ? (string) $value : $choices[0];
	}
	return sanitize_text_field( $value );
}

/**
 * Save one sub-tab of directory settings, then return to it.
 */
function menj_click_dir_save_settings( array $post ) {
	$sub    = isset( $post['subtab'] ) ? sanitize_key( $post['subtab'] ) : 'general';
	$schema = menj_click_dir_schema();
	if ( 'emails' === $sub ) {
		$emails = array();
		foreach ( array_keys( menj_click_directory_email_defaults() ) as $key ) {
			$value          = isset( $post['dir_emails'][ $key ] ) ? (string) $post['dir_emails'][ $key ] : '';
			$emails[ $key ] = substr( $key, -8 ) === '_subject' ? sanitize_text_field( $value ) : sanitize_textarea_field( $value );
		}
		menj_click_update_section( 'dir_emails', $emails );
	} elseif ( isset( $schema[ $sub ] ) ) {
		$current = menj_click_settings( 'directory' );
		$in      = isset( $post['dir'] ) ? (array) $post['dir'] : array();
		foreach ( $schema[ $sub ] as $key => $type ) {
			$current[ $key ] = menj_click_dir_sanitize_value( $type, isset( $in[ $key ] ) ? $in[ $key ] : '' );
		}
		if ( (int) $current['title_max'] < (int) $current['title_min'] ) {
			$current['title_max'] = $current['title_min'];
		}
		if ( (int) $current['desc_max'] < (int) $current['desc_min'] ) {
			$current['desc_max'] = $current['desc_min'];
		}
		menj_click_update_section( 'directory', $current );
		menj_click_dir_flush_counts();
	}
	menj_click_admin_flash( 'success', __( 'Changes saved.', 'menj-click' ) );
	menj_click_admin_redirect( 'directory', array( 'sub' => $sub ) );
}

function menj_click_dir_admin_field( $key, $label, array $args = array() ) {
	$d      = menj_click_settings( 'directory' );
	$schema = array();
	foreach ( menj_click_dir_schema() as $fields ) {
		$schema = array_merge( $schema, $fields );
	}
	$type  = isset( $schema[ $key ] ) ? $schema[ $key ] : 'text';
	$name  = 'dir[' . $key . ']';
	$value = $d[ $key ];
	if ( 'bool' === $type ) {
		menj_click_toggle( $name, $value, $label, isset( $args['help'] ) ? $args['help'] : '' );
		return;
	}
	if ( 0 === strpos( $type, 'choice:' ) ) {
		$args['options'] = isset( $args['options'] ) ? $args['options'] : array_combine( explode( ',', substr( $type, 7 ) ), explode( ',', substr( $type, 7 ) ) );
		menj_click_field( 'select', $name, $value, $label, $args );
		return;
	}
	$html_type = array( 'int' => 'number', 'money' => 'text', 'email' => 'email', 'url' => 'url', 'textarea' => 'textarea' );
	if ( 'int' === $type ) {
		$args = array_merge( array( 'min' => 0, 'class' => 'menj-field--short' ), $args );
	}
	if ( 'money' === $type ) {
		$args = array_merge( array( 'class' => 'menj-field--short', 'pattern' => '[0-9]+([.,][0-9]{1,2})?' ), $args );
	}
	menj_click_field( isset( $html_type[ $type ] ) ? $html_type[ $type ] : 'text', $name, $value, $label, $args );
}

function menj_click_tab_directory() {
	$subs = menj_click_dir_subtabs();
	$sub  = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$sub  = isset( $subs[ $sub ] ) ? $sub : 'general';

	$counts = wp_count_posts( 'menj_listing' );
	echo '<div class="menj-dir-overview">';
	$stats = array(
		array( (int) $counts->publish, __( 'Live', 'menj-click' ), admin_url( 'edit.php?post_type=menj_listing&post_status=publish' ) ),
		array( (int) $counts->pending, __( 'Waiting for review', 'menj-click' ), admin_url( 'edit.php?post_type=menj_listing&post_status=pending' ) ),
		array( (int) ( isset( $counts->menj_unconfirmed ) ? $counts->menj_unconfirmed : 0 ), __( 'Unconfirmed', 'menj-click' ), admin_url( 'edit.php?post_type=menj_listing&post_status=menj_unconfirmed' ) ),
		array( (int) ( isset( $counts->menj_expired ) ? $counts->menj_expired : 0 ), __( 'Expired', 'menj-click' ), admin_url( 'edit.php?post_type=menj_listing&post_status=menj_expired' ) ),
	);
	foreach ( $stats as $s ) {
		echo '<a class="menj-stat" href="' . esc_url( $s[2] ) . '"><span class="menj-stat__value">' . esc_html( number_format_i18n( $s[0] ) ) . '</span><span class="menj-stat__label">' . esc_html( $s[1] ) . '</span></a>';
	}
	echo '</div>';
	echo '<p class="menj-dir-links"><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=menj_listing' ) ) . '">' . esc_html__( 'Manage listings', 'menj-click' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=menj_dir_category&post_type=menj_listing' ) ) . '">' . esc_html__( 'Categories', 'menj-click' ) . '</a> ';
	if ( menj_click_dir_enabled() ) {
		echo '<a class="button-link" href="' . esc_url( menj_click_dir_url() ) . '">' . esc_html__( 'View directory', 'menj-click' ) . '</a>';
	}
	echo '</p>';

	echo '<nav class="menj-subtabs" aria-label="' . esc_attr__( 'Directory settings', 'menj-click' ) . '">';
	foreach ( $subs as $key => $label ) {
		echo '<a href="' . esc_url( menj_click_admin_url( 'directory', array( 'sub' => $key ) ) ) . '"' . ( $key === $sub ? ' class="is-active" aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';

	if ( 'tools' === $sub ) {
		menj_click_dir_tools();
		return;
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="menj-form">';
	menj_click_form_fields( 'menj_click_save', 'directory' );
	echo '<input type="hidden" name="subtab" value="' . esc_attr( $sub ) . '">';
	call_user_func( 'menj_click_dir_sub_' . $sub );
	menj_click_submit( __( 'Save changes', 'menj-click' ) );
	echo '</form>';
}

function menj_click_dir_sub_general() {
	menj_click_card_open( __( 'Directory', 'menj-click' ), __( 'A phpLD-style directory at /directory/: categories, reviewed listings, featured and sponsored placements.', 'menj-click' ) );
	menj_click_dir_admin_field( 'enabled', __( 'Directory is on', 'menj-click' ), array( 'help' => __( 'Off hides it everywhere. Listings stay in the database.', 'menj-click' ) ) );
	echo '<div class="menj-grid-2">';
	menj_click_dir_admin_field( 'title', __( 'Title', 'menj-click' ) );
	menj_click_dir_admin_field( 'intro', __( 'Introduction', 'menj-click' ), array( 'rows' => 2 ) );
	echo '</div>';
	menj_click_card_close();

	menj_click_card_open( __( 'Browsing', 'menj-click' ) );
	echo '<div class="menj-grid-3">';
	menj_click_dir_admin_field( 'per_page', __( 'Listings per page', 'menj-click' ), array( 'min' => 5, 'max' => 100 ) );
	menj_click_dir_admin_field( 'sort', __( 'Default order', 'menj-click' ), array( 'options' => array( 'newest' => __( 'Newest first', 'menj-click' ), 'title' => __( 'A–Z', 'menj-click' ), 'popular' => __( 'Most visited', 'menj-click' ), 'rated' => __( 'Top rated', 'menj-click' ) ) ) );
	menj_click_dir_admin_field( 'cats_preview', __( 'Subcategories shown under each category', 'menj-click' ), array( 'max' => 10 ) );
	echo '</div>';
	menj_click_dir_admin_field( 'show_counts', __( 'Show listing counts on categories', 'menj-click' ) );
	menj_click_dir_admin_field( 'tags', __( 'Tags', 'menj-click' ), array( 'help' => __( 'Owners can add up to five; each tag gets its own page.', 'menj-click' ) ) );
	menj_click_dir_admin_field( 'business_fields', __( 'Business details', 'menj-click' ), array( 'help' => __( 'Address, city, country and phone on the form and listing page.', 'menj-click' ) ) );
	menj_click_dir_admin_field( 'show_qr', __( 'QR code on each listing', 'menj-click' ), array( 'help' => __( 'Uses the listing’s short link when it has one, so scans are counted.', 'menj-click' ) ) );
	menj_click_card_close();

	menj_click_card_open( __( 'Reviews', 'menj-click' ), __( 'Reviews are WordPress comments, so they follow Settings → Discussion for moderation.', 'menj-click' ) );
	menj_click_dir_admin_field( 'reviews', __( 'Allow reviews', 'menj-click' ) );
	menj_click_dir_admin_field( 'reviews_rating', __( 'Require a star rating', 'menj-click' ) );
	menj_click_card_close();
}

function menj_click_dir_sub_tiers() {
	$rel_free = array( 'ugc nofollow' => 'ugc nofollow (recommended)', 'ugc' => 'ugc', 'nofollow' => 'nofollow', 'follow' => __( 'followed', 'menj-click' ) );
	$rel_paid = array( 'sponsored' => __( 'sponsored (required by Google for paid links)', 'menj-click' ), 'sponsored nofollow' => 'sponsored nofollow', 'nofollow' => 'nofollow', 'follow' => __( 'followed (not recommended)', 'menj-click' ) );
	$period   = array( 'once' => __( 'One-off (never expires)', 'menj-click' ), 'month' => __( 'Per month', 'menj-click' ), 'year' => __( 'Per year', 'menj-click' ) );

	echo '<div class="menj-grid-3 menj-tier-admin">';
	menj_click_card_open( __( 'Free', 'menj-click' ) );
	menj_click_dir_admin_field( 'free_enabled', __( 'Offer free listings', 'menj-click' ) );
	menj_click_dir_admin_field( 'free_approval', __( 'Review before publishing', 'menj-click' ) );
	menj_click_dir_admin_field( 'free_rel', __( 'Outbound link', 'menj-click' ), array( 'options' => $rel_free ) );
	menj_click_card_close();

	menj_click_card_open( __( 'Featured', 'menj-click' ) );
	menj_click_dir_admin_field( 'featured_enabled', __( 'Offer Featured', 'menj-click' ) );
	menj_click_dir_admin_field( 'featured_price', __( 'Price', 'menj-click' ) );
	menj_click_dir_admin_field( 'featured_period', __( 'Billing', 'menj-click' ), array( 'options' => $period ) );
	menj_click_dir_admin_field( 'featured_max', __( 'Pinned per category', 'menj-click' ), array( 'max' => 50, 'help' => __( 'Extra Featured listings rotate.', 'menj-click' ) ) );
	menj_click_card_close();

	menj_click_card_open( __( 'Sponsored', 'menj-click' ) );
	menj_click_dir_admin_field( 'sponsored_enabled', __( 'Offer Sponsored', 'menj-click' ) );
	menj_click_dir_admin_field( 'sponsored_price', __( 'Price', 'menj-click' ) );
	menj_click_dir_admin_field( 'sponsored_period', __( 'Billing', 'menj-click' ), array( 'options' => $period ) );
	menj_click_card_close();
	echo '</div>';

	menj_click_card_open( __( 'Paid listings', 'menj-click' ) );
	echo '<div class="menj-grid-3">';
	menj_click_dir_admin_field( 'paid_rel', __( 'Outbound link', 'menj-click' ), array( 'options' => $rel_paid ) );
	menj_click_dir_admin_field( 'expire_action', __( 'When a paid period ends', 'menj-click' ), array( 'options' => array( 'downgrade' => __( 'Move to Free', 'menj-click' ), 'hide' => __( 'Hide until renewed', 'menj-click' ) ) ) );
	menj_click_dir_admin_field( 'reminder_days', __( 'Renewal reminder (days before)', 'menj-click' ), array( 'max' => 60 ) );
	echo '</div>';
	menj_click_dir_admin_field( 'paid_approval', __( 'Review paid listings before publishing', 'menj-click' ), array( 'help' => __( 'Off publishes them as soon as payment clears.', 'menj-click' ) ) );
	menj_click_card_close();
}

function menj_click_dir_sub_payments() {
	menj_click_card_open( __( 'Currency', 'menj-click' ) );
	menj_click_dir_admin_field( 'currency', __( 'Currency', 'menj-click' ), array( 'class' => 'menj-field--short' ) );
	menj_click_card_close();

	menj_click_card_open( __( 'PayPal', 'menj-click' ), __( 'PayPal Standard checkout. Payments are confirmed by PayPal’s Instant Payment Notification, checked for receiver, amount, currency and duplicate transactions.', 'menj-click' ) );
	menj_click_dir_admin_field( 'paypal_enabled', __( 'Take payments with PayPal', 'menj-click' ), array( 'help' => __( 'Off: owners are told you’ll send an invoice, and you mark listings paid yourself.', 'menj-click' ) ) );
	echo '<div class="menj-grid-2">';
	menj_click_dir_admin_field( 'paypal_email', __( 'PayPal account email', 'menj-click' ) );
	echo '<div class="menj-field"><span class="menj-field__label">' . esc_html__( 'Notification URL', 'menj-click' ) . '</span><code class="menj-code">' . esc_html( rest_url( 'menj-click/v1/paypal-ipn' ) ) . '</code><p class="menj-field__help">' . esc_html__( 'Sent with each payment; nothing to set up in PayPal unless IPN is switched off in your account.', 'menj-click' ) . '</p></div>';
	echo '</div>';
	menj_click_dir_admin_field( 'paypal_sandbox', __( 'Sandbox (test mode)', 'menj-click' ) );
	menj_click_dir_admin_field( 'paypal_subscriptions', __( 'Offer automatic renewal', 'menj-click' ), array( 'help' => __( 'Monthly and yearly listings can be paid by PayPal subscription. Owners cancel from their PayPal account; the listing runs to the end of the paid period.', 'menj-click' ) ) );
	menj_click_card_close();

	menj_click_card_open( __( 'Paying ahead', 'menj-click' ), __( 'Owners paying once can buy several months or years in one payment.', 'menj-click' ) );
	echo '<div class="menj-grid-2">';
	menj_click_dir_admin_field( 'max_periods', __( 'Most periods in one payment', 'menj-click' ), array( 'min' => 1, 'max' => 10, 'help' => __( '1 turns this off.', 'menj-click' ) ) );
	menj_click_dir_admin_field( 'multi_discount', __( 'Discount for 2+ periods (%)', 'menj-click' ), array( 'max' => 90, 'help' => __( 'e.g. 25 makes two years cost the price of 1.5.', 'menj-click' ) ) );
	echo '</div>';
	menj_click_card_close();

	menj_click_card_open( __( 'Invoices', 'menj-click' ), __( 'Shown on the payment page when PayPal is off. You get an email for each request; mark it paid from the listing.', 'menj-click' ) );
	menj_click_dir_admin_field( 'invoice_text', __( 'Payment instructions', 'menj-click' ), array( 'rows' => 3 ) );
	menj_click_card_close();

	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . menj_click_dir_payments_table() . ' ORDER BY id DESC LIMIT 15', ARRAY_A ); // phpcs:ignore WordPress.DB
	menj_click_card_open( __( 'Recent payments', 'menj-click' ) );
	if ( ! $rows ) {
		echo '<p class="menj-muted">' . esc_html__( 'None yet.', 'menj-click' ) . '</p>';
	} else {
		echo '<div class="menj-table-wrap"><table class="menj-table"><thead><tr><th>' . esc_html__( 'Reference', 'menj-click' ) . '</th><th>' . esc_html__( 'Listing', 'menj-click' ) . '</th><th>' . esc_html__( 'Type', 'menj-click' ) . '</th><th class="is-num">' . esc_html__( 'Amount', 'menj-click' ) . '</th><th>' . esc_html__( 'Method', 'menj-click' ) . '</th><th>' . esc_html__( 'Status', 'menj-click' ) . '</th><th>' . esc_html__( 'Date', 'menj-click' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td><code>menj-' . esc_html( $r['id'] ) . '</code></td><td><a href="' . esc_url( admin_url( 'post.php?post=' . (int) $r['listing_id'] . '&action=edit' ) ) . '">' . esc_html( get_the_title( (int) $r['listing_id'] ) ) . '</a></td><td>' . esc_html( ucfirst( $r['tier'] ) ) . '</td><td class="is-num">' . esc_html( $r['currency'] . ' ' . $r['amount'] ) . ( (int) $r['quantity'] > 1 ? ' <span class="menj-muted">× ' . (int) $r['quantity'] . '</span>' : '' ) . '</td><td>' . esc_html( menj_click_dir_method_label( $r['method'] ) ) . '</td><td><span class="menj-pill ' . ( 'paid' === $r['status'] ? 'is-ok' : 'is-warn' ) . '">' . esc_html( ucfirst( $r['status'] ) ) . '</span>' . ( $r['note'] ? ' <span class="menj-muted">' . esc_html( $r['note'] ) . '</span>' : '' ) . menj_click_dir_ipn_details( $r ) . '</td><td>' . esc_html( mysql2date( get_option( 'date_format' ), $r['paid_at'] ? $r['paid_at'] : $r['created_at'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
	menj_click_card_close();
}

function menj_click_dir_sub_reciprocal() {
	menj_click_card_open( __( 'Link back (reciprocal links)', 'menj-click' ), __( 'Applies to Free listings. Owners add a link to this site and tell us the page; it’s checked when they submit and rechecked on a schedule.', 'menj-click' ) );
	menj_click_dir_admin_field( 'reciprocal', __( 'Link back', 'menj-click' ), array( 'options' => array( 'off' => __( 'Off', 'menj-click' ), 'optional' => __( 'Optional — reviewed first, followed link', 'menj-click' ), 'required' => __( 'Required for Free listings', 'menj-click' ) ) ) );
	echo '<div class="menj-grid-2">';
	menj_click_dir_admin_field( 'backlink_url', __( 'Link back to', 'menj-click' ), array( 'placeholder' => home_url( '/directory/' ), 'help' => __( 'Any link to this domain counts; this is the address in the code owners copy.', 'menj-click' ) ) );
	menj_click_dir_admin_field( 'backlink_text', __( 'Link text', 'menj-click' ), array( 'placeholder' => get_bloginfo( 'name' ) . ' ' . menj_click_dir( 'title' ) ) );
	echo '</div>';
	echo '<div class="menj-field"><span class="menj-field__label">' . esc_html__( 'Code owners copy', 'menj-click' ) . '</span><code class="menj-code">' . esc_html( menj_click_dir_backlink_html() ) . '</code></div>';
	menj_click_dir_admin_field( 'reciprocal_follow', __( 'Reward verified link-backs with a followed link', 'menj-click' ) );
	menj_click_dir_admin_field( 'reciprocal_dofollow', __( 'Don’t accept nofollow link-backs', 'menj-click' ) );
	echo '<div class="menj-grid-2">';
	menj_click_dir_admin_field( 'reciprocal_recheck', __( 'Recheck every (days)', 'menj-click' ), array( 'min' => 1 ) );
	menj_click_dir_admin_field( 'reciprocal_grace', __( 'Grace period before hiding (days)', 'menj-click' ) );
	echo '</div>';
	menj_click_card_close();

	menj_click_card_open( __( 'Link checker', 'menj-click' ), __( 'Checks each listed site in small hourly batches and records its HTTP status.', 'menj-click' ) );
	menj_click_dir_admin_field( 'checker', __( 'Check listed sites', 'menj-click' ) );
	echo '<div class="menj-grid-3">';
	menj_click_dir_admin_field( 'checker_every', __( 'Check each site every (days)', 'menj-click' ), array( 'min' => 1 ) );
	menj_click_dir_admin_field( 'checker_batch', __( 'Sites per hourly batch', 'menj-click' ), array( 'min' => 1, 'max' => 50 ) );
	menj_click_dir_admin_field( 'checker_fails', __( 'Failures before it counts as broken', 'menj-click' ), array( 'min' => 1, 'max' => 10 ) );
	echo '</div>';
	menj_click_dir_admin_field( 'checker_hide', __( 'Hide broken listings automatically', 'menj-click' ), array( 'help' => __( 'Off: they’re flagged in the listings screen for you to decide.', 'menj-click' ) ) );
	menj_click_card_close();
}

function menj_click_dir_sub_submissions() {
	menj_click_card_open( __( 'Public submissions', 'menj-click' ) );
	menj_click_dir_admin_field( 'submissions', __( 'Accept submissions', 'menj-click' ), array( 'help' => __( 'The form lives at /directory/submit/.', 'menj-click' ) ) );
	menj_click_dir_admin_field( 'email_confirm', __( 'Owners confirm their email first', 'menj-click' ), array( 'help' => __( 'Unconfirmed submissions are deleted after a week.', 'menj-click' ) ) );
	menj_click_dir_admin_field( 'notify_email', __( 'Send new-submission alerts to', 'menj-click' ), array( 'placeholder' => get_option( 'admin_email' ) ) );
	menj_click_dir_admin_field( 'terms', __( 'Listing guidelines', 'menj-click' ), array( 'rows' => 3, 'help' => __( 'Shown above the agreement checkbox.', 'menj-click' ) ) );
	menj_click_card_close();

	menj_click_card_open( __( 'Checks', 'menj-click' ) );
	echo '<div class="menj-grid-4">';
	menj_click_dir_admin_field( 'title_min', __( 'Name: min characters', 'menj-click' ) );
	menj_click_dir_admin_field( 'title_max', __( 'Name: max', 'menj-click' ) );
	menj_click_dir_admin_field( 'desc_min', __( 'Description: min', 'menj-click' ) );
	menj_click_dir_admin_field( 'desc_max', __( 'Description: max', 'menj-click' ) );
	echo '</div>';
	menj_click_dir_admin_field( 'unique_domain', __( 'One listing per domain', 'menj-click' ) );
	menj_click_dir_admin_field( 'check_online', __( 'Check the site is online when submitted', 'menj-click' ) );
	menj_click_dir_admin_field( 'max_per_ip', __( 'Submissions per visitor per day', 'menj-click' ), array( 'help' => __( '0 for no limit.', 'menj-click' ) ) );
	menj_click_card_close();

	menj_click_card_open( __( 'Ban lists', 'menj-click' ), __( 'One per line. Domains also ban their subdomains; start an email entry with @ to ban a whole domain; end an IP with * to ban a range.', 'menj-click' ) );
	echo '<div class="menj-grid-2">';
	menj_click_dir_admin_field( 'banned_domains', __( 'Domains', 'menj-click' ), array( 'rows' => 4 ) );
	menj_click_dir_admin_field( 'banned_emails', __( 'Emails', 'menj-click' ), array( 'rows' => 4 ) );
	menj_click_dir_admin_field( 'banned_ips', __( 'IP addresses', 'menj-click' ), array( 'rows' => 4 ) );
	menj_click_dir_admin_field( 'banned_words', __( 'Words in names or descriptions', 'menj-click' ), array( 'rows' => 4 ) );
	echo '</div>';
	menj_click_card_close();
}

function menj_click_dir_sub_emails() {
	$e     = menj_click_settings( 'dir_emails' );
	$names = array(
		'confirm'    => __( 'Confirm email', 'menj-click' ),
		'received'   => __( 'Received, in review', 'menj-click' ),
		'payment'    => __( 'Payment due', 'menj-click' ),
		'paid'       => __( 'Payment received', 'menj-click' ),
		'approved'   => __( 'Approved', 'menj-click' ),
		'rejected'   => __( 'Rejected', 'menj-click' ),
		'expiring'   => __( 'Renewal reminder', 'menj-click' ),
		'expired'    => __( 'Paid period ended', 'menj-click' ),
		'reciprocal' => __( 'Link back missing', 'menj-click' ),
		'manage'     => __( 'New manage link', 'menj-click' ),
		'admin'      => __( 'To you: needs attention', 'menj-click' ),
	);
	menj_click_card_open( __( 'Placeholders', 'menj-click' ), '{site} {name} {title} {url} {tier} {amount} {expiry} {listing_url} {manage_url} {pay_url} {confirm_url} {reciprocal_url} {deadline} {reason} {event} {admin_url}' );
	menj_click_card_close();
	foreach ( $names as $key => $label ) {
		echo '<details class="menj-card menj-disclosure"><summary class="menj-card__title">' . esc_html( $label ) . '</summary>';
		menj_click_field( 'text', 'dir_emails[' . $key . '_subject]', $e[ $key . '_subject' ], __( 'Subject', 'menj-click' ) );
		menj_click_field( 'textarea', 'dir_emails[' . $key . '_body]', $e[ $key . '_body' ], __( 'Message', 'menj-click' ), array( 'rows' => 7 ) );
		echo '</details>';
	}
}

function menj_click_dir_method_label( $method ) {
	$labels = array(
		'paypal'       => __( 'PayPal', 'menj-click' ),
		'subscription' => __( 'PayPal subscription', 'menj-click' ),
		'invoice'      => __( 'Invoice', 'menj-click' ),
		'manual'       => __( 'Recorded by hand', 'menj-click' ),
	);
	return isset( $labels[ $method ] ) ? $labels[ $method ] : ucfirst( $method );
}

/**
 * The PayPal messages logged against a payment, collapsed.
 */
function menj_click_dir_ipn_details( array $row ) {
	if ( empty( $row['raw_log'] ) ) {
		return '';
	}
	return '<details class="menj-ipn"><summary>' . esc_html__( 'PayPal messages', 'menj-click' ) . '</summary><pre>' . esc_html( $row['raw_log'] ) . '</pre></details>';
}

/* -------------------------------------------------------------------------- */
/* Import / export                                                             */
/* -------------------------------------------------------------------------- */

function menj_click_dir_tools() {
	menj_click_card_open( __( 'Import listings', 'menj-click' ), __( 'CSV columns: url, title, description, category, tags, tier, owner_name, owner_email. Categories are created as needed (use “Parent > Child” for subcategories). Imported listings go live straight away.', 'menj-click' ) );
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="menj-inline-form">';
	menj_click_form_fields( 'menj_click_dir_import' );
	echo '<input type="file" name="csv" accept=".csv,text/csv" required> <button type="submit" class="button">' . esc_html__( 'Import CSV', 'menj-click' ) . '</button></form>';
	menj_click_card_close();

	menj_click_card_open( __( 'Export listings', 'menj-click' ), __( 'Every listing with its status, type, owner and stats.', 'menj-click' ) );
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	menj_click_form_fields( 'menj_click_dir_export' );
	echo '<button type="submit" class="button">' . esc_html__( 'Download CSV', 'menj-click' ) . '</button></form>';
	menj_click_card_close();

	menj_click_card_open( __( 'Run checks now', 'menj-click' ), __( 'Runs one batch of the link checker and reciprocal checks immediately.', 'menj-click' ) );
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	menj_click_form_fields( 'menj_click_dir_run_checks' );
	echo '<button type="submit" class="button">' . esc_html__( 'Run a batch', 'menj-click' ) . '</button></form>';
	menj_click_card_close();
}

/**
 * Find or create a category from "Parent > Child".
 */
function menj_click_dir_category_path( $path ) {
	$parent = 0;
	foreach ( array_filter( array_map( 'trim', explode( '>', (string) $path ) ), 'strlen' ) as $name ) {
		$found = get_terms( array( 'taxonomy' => 'menj_dir_category', 'name' => $name, 'parent' => $parent, 'hide_empty' => false, 'number' => 1 ) );
		if ( is_array( $found ) && $found ) {
			$parent = (int) $found[0]->term_id;
		} else {
			$made = wp_insert_term( $name, 'menj_dir_category', array( 'parent' => $parent ) );
			if ( is_wp_error( $made ) ) {
				return 0;
			}
			$parent = (int) $made['term_id'];
		}
	}
	return $parent;
}

function menj_click_dir_handle_import() {
	menj_click_admin_guard( 'menj_click_dir_import' );
	$file = isset( $_FILES['csv'] ) ? $_FILES['csv'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $file || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) || 'csv' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
		menj_click_admin_flash( 'error', __( 'Choose a CSV file to import.', 'menj-click' ) );
		menj_click_admin_redirect( 'directory', array( 'sub' => 'tools' ) );
	}
	$handle = fopen( $file['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$header = array_map(
		function ( $h ) {
			return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) );
		},
		(array) fgetcsv( $handle, 0, ',', '"', '\\' )
	);
	$done    = 0;
	$skipped = array();
	while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$r   = array_combine( array_slice( $header, 0, count( $row ) ), array_slice( array_pad( $row, count( $header ), '' ), 0, count( $header ) ) );
		$url = esc_url_raw( trim( isset( $r['url'] ) ? $r['url'] : '' ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			continue;
		}
		$domain = menj_click_dir_domain( $url );
		if ( menj_click_dir_domain_taken( $domain ) ) {
			$skipped[] = $domain . ' — ' . __( 'already listed', 'menj-click' );
			continue;
		}
		$title = sanitize_text_field( isset( $r['title'] ) && '' !== $r['title'] ? $r['title'] : $domain );
		$desc  = sanitize_textarea_field( isset( $r['description'] ) ? $r['description'] : '' );
		$id    = wp_insert_post( array( 'post_type' => 'menj_listing', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => menj_click_dir_blocks_from_text( $desc ), 'post_excerpt' => wp_trim_words( $desc, 40 ) ), true );
		if ( is_wp_error( $id ) ) {
			$skipped[] = $domain . ' — ' . $id->get_error_message();
			continue;
		}
		update_post_meta( $id, '_menj_url', $url );
		update_post_meta( $id, '_menj_domain', $domain );
		update_post_meta( $id, '_menj_hits', 0 );
		menj_click_dir_set_tier( $id, isset( $r['tier'] ) ? sanitize_key( $r['tier'] ) : 'free' );
		update_post_meta( $id, '_menj_payment', menj_click_dir_tiers()[ get_post_meta( $id, '_menj_tier', true ) ]['paid'] ? 'paid' : 'none' );
		update_post_meta( $id, '_menj_owner_name', sanitize_text_field( isset( $r['owner_name'] ) ? $r['owner_name'] : '' ) );
		update_post_meta( $id, '_menj_owner_email', sanitize_email( isset( $r['owner_email'] ) ? $r['owner_email'] : '' ) );
		$cat = menj_click_dir_category_path( isset( $r['category'] ) ? $r['category'] : '' );
		if ( $cat ) {
			wp_set_object_terms( $id, array( $cat ), 'menj_dir_category' );
		}
		if ( ! empty( $r['tags'] ) ) {
			wp_set_object_terms( $id, array_filter( array_map( 'trim', explode( ',', $r['tags'] ) ) ), 'menj_dir_tag' );
		}
		$done++;
	}
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	menj_click_dir_flush_counts();
	/* translators: %d: number of listings */
	menj_click_admin_flash( $skipped ? 'warning' : 'success', sprintf( _n( '%d listing imported.', '%d listings imported.', $done, 'menj-click' ), $done ), $skipped );
	menj_click_admin_redirect( 'directory', array( 'sub' => 'tools' ) );
}
add_action( 'admin_post_menj_click_dir_import', 'menj_click_dir_handle_import' );

function menj_click_dir_handle_export() {
	menj_click_admin_guard( 'menj_click_dir_export' );
	$ids = get_posts( array( 'post_type' => 'menj_listing', 'post_status' => menj_click_dir_statuses( false ), 'fields' => 'ids', 'posts_per_page' => -1 ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( menj_click_host() . '-directory-' . gmdate( 'Y-m-d' ) . '.csv' ) . '"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fputcsv( $out, array( 'url', 'title', 'description', 'category', 'tags', 'tier', 'status', 'owner_name', 'owner_email', 'visits', 'rating', 'expires', 'link_back', 'http_status', 'added' ), ',', '"', '\\' );
	foreach ( $ids as $id ) {
		$l     = menj_click_listing( $id );
		$terms = wp_get_object_terms( $id, 'menj_dir_category' );
		$path  = '';
		if ( $terms && ! is_wp_error( $terms ) ) {
			$names = array();
			foreach ( array_reverse( get_ancestors( $terms[0]->term_id, 'menj_dir_category', 'taxonomy' ) ) as $aid ) {
				$names[] = get_term( $aid )->name;
			}
			$names[] = $terms[0]->name;
			$path    = implode( ' > ', $names );
		}
		$tags = wp_get_object_terms( $id, 'menj_dir_tag', array( 'fields' => 'names' ) );
		fputcsv( $out, array( $l['url'], $l['title'], wp_strip_all_tags( $l['post']->post_content ), $path, is_array( $tags ) ? implode( ', ', $tags ) : '', $l['tier'], $l['post']->post_status, $l['owner_name'], $l['owner_email'], (int) $l['hits'], $l['rating_avg'], $l['expires'], $l['recpr_status'], $l['http_status'], $l['post']->post_date_gmt ), ',', '"', '\\' );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
add_action( 'admin_post_menj_click_dir_export', 'menj_click_dir_handle_export' );

function menj_click_dir_handle_run_checks() {
	menj_click_admin_guard( 'menj_click_dir_run_checks' );
	menj_click_dir_hourly();
	menj_click_admin_flash( 'success', __( 'Checks ran. Results are in the listings screen.', 'menj-click' ) );
	menj_click_admin_redirect( 'directory', array( 'sub' => 'tools' ) );
}
add_action( 'admin_post_menj_click_dir_run_checks', 'menj_click_dir_handle_run_checks' );

/* -------------------------------------------------------------------------- */
/* Listings screen                                                             */
/* -------------------------------------------------------------------------- */

function menj_click_dir_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || 'menj_listing' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'menj-click-admin', MENJ_CLICK_URI . '/assets/css/admin.css', array(), MENJ_CLICK_VERSION );
	wp_enqueue_script( 'menj-click-directory-admin', MENJ_CLICK_URI . '/assets/js/directory-admin.js', array(), MENJ_CLICK_VERSION, true );
	wp_localize_script( 'menj-click-directory-admin', 'menjClickDirAdmin', array( 'rejectPrompt' => __( 'Reason for the owner (optional). It’s included in the rejection email:', 'menj-click' ) ) );
}
add_action( 'admin_enqueue_scripts', 'menj_click_dir_admin_assets' );

function menj_click_dir_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'date' === $key ) {
			$new['menj_site']  = __( 'Site', 'menj-click' );
			$new['menj_tier']  = __( 'Type', 'menj-click' );
			$new['menj_recpr'] = __( 'Link back', 'menj-click' );
			$new['menj_hits']  = __( 'Visits', 'menj-click' );
		}
		$new[ $key ] = $label;
	}
	return $new;
}
add_filter( 'manage_menj_listing_posts_columns', 'menj_click_dir_columns' );

function menj_click_dir_column( $column, $id ) {
	$l = menj_click_listing( $id );
	switch ( $column ) {
		case 'menj_site':
			echo '<a href="' . esc_url( $l['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $l['domain'] ) . '</a>';
			if ( $l['http_checked'] ) {
				$ok = (int) $l['http_status'] >= 200 && (int) $l['http_status'] < 400;
				/* translators: 1: HTTP status, 2: date */
				echo '<br><span class="menj-pill ' . ( $ok ? 'is-ok' : ( (int) $l['http_fails'] >= (int) menj_click_dir( 'checker_fails' ) ? 'is-bad' : 'is-warn' ) ) . '" title="' . esc_attr( sprintf( __( 'HTTP %1$s, checked %2$s', 'menj-click' ), $l['http_status'] ? $l['http_status'] : '—', mysql2date( get_option( 'date_format' ), $l['http_checked'] ) ) ) . '">' . esc_html( $ok ? __( 'Online', 'menj-click' ) : ( $l['http_status'] ? 'HTTP ' . $l['http_status'] : __( 'Unreachable', 'menj-click' ) ) ) . '</span>';
			}
			if ( '' !== (string) $l['changes'] ) {
				echo '<br><span class="menj-pill is-warn">' . esc_html__( 'Owner edits waiting', 'menj-click' ) . '</span>';
			}
			break;
		case 'menj_tier':
			echo '<span class="menj-pill is-tier-' . esc_attr( $l['tier'] ) . '">' . esc_html( menj_click_dir_tiers()[ $l['tier'] ]['label'] ) . '</span>';
			if ( 'due' === $l['payment'] ) {
				echo '<br><span class="menj-pill is-warn">' . esc_html__( 'Payment due', 'menj-click' ) . '</span>';
			} elseif ( $l['expires'] ) {
				/* translators: %s: date */
				echo '<br><span class="menj-muted">' . esc_html( sprintf( __( 'until %s', 'menj-click' ), mysql2date( get_option( 'date_format' ), $l['expires'] ) ) ) . '</span>';
			}
			break;
		case 'menj_recpr':
			$labels = array( 'ok' => array( 'is-ok', __( 'Found', 'menj-click' ) ), 'nofollow' => array( 'is-warn', 'nofollow' ), 'missing' => array( 'is-bad', __( 'Missing', 'menj-click' ) ), 'error' => array( 'is-warn', __( 'Page error', 'menj-click' ) ) );
			if ( '' === $l['recpr_url'] ) {
				echo '<span class="menj-muted">—</span>';
			} else {
				$s = isset( $labels[ $l['recpr_status'] ] ) ? $labels[ $l['recpr_status'] ] : array( 'is-muted', __( 'Not checked', 'menj-click' ) );
				echo '<a href="' . esc_url( $l['recpr_url'] ) . '" target="_blank" rel="noopener noreferrer"><span class="menj-pill ' . esc_attr( $s[0] ) . '">' . esc_html( $s[1] ) . '</span></a>';
			}
			break;
		case 'menj_hits':
			echo esc_html( number_format_i18n( (int) $l['hits'] ) );
			if ( (int) $l['rating_count'] ) {
				echo '<br><span class="menj-muted">★ ' . esc_html( number_format_i18n( (float) $l['rating_avg'], 1 ) ) . ' (' . (int) $l['rating_count'] . ')</span>';
			}
			break;
	}
}
add_action( 'manage_menj_listing_posts_custom_column', 'menj_click_dir_column', 10, 2 );

function menj_click_dir_sortable( $columns ) {
	$columns['menj_hits'] = 'menj_hits';
	return $columns;
}
add_filter( 'manage_edit-menj_listing_sortable_columns', 'menj_click_dir_sortable' );

function menj_click_dir_admin_sort( $query ) {
	if ( is_admin() && $query->is_main_query() && 'menj_hits' === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', '_menj_hits' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
add_action( 'pre_get_posts', 'menj_click_dir_admin_sort' );

/**
 * Pending listings with a verified link back come first (priority review).
 */
function menj_click_dir_pending_priority( $query ) {
	if ( is_admin() && $query->is_main_query() && 'menj_listing' === $query->get( 'post_type' ) && 'pending' === $query->get( 'post_status' ) && ! isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'meta_query', array( 'relation' => 'OR', 'recpr' => array( 'key' => '_menj_recpr_status', 'compare' => 'EXISTS' ), array( 'key' => '_menj_recpr_status', 'compare' => 'NOT EXISTS' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$query->set( 'orderby', array( 'recpr' => 'DESC', 'date' => 'ASC' ) );
	}
}
add_action( 'pre_get_posts', 'menj_click_dir_pending_priority' );

function menj_click_dir_row_actions( $actions, $post ) {
	if ( 'menj_listing' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}
	$url = function ( $do ) use ( $post ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=menj_click_dir_moderate&do=' . $do . '&id=' . $post->ID ), 'menj_click_dir_moderate_' . $post->ID );
	};
	$new = array();
	if ( in_array( $post->post_status, array( 'pending', 'draft', 'menj_expired', 'menj_unconfirmed' ), true ) ) {
		$new['menj_approve'] = '<a href="' . esc_url( $url( 'approve' ) ) . '">' . esc_html__( 'Approve', 'menj-click' ) . '</a>';
	}
	if ( 'trash' !== $post->post_status ) {
		$new['menj_reject'] = '<a href="' . esc_url( $url( 'reject' ) ) . '" data-menj-reject class="menj-danger-link">' . esc_html__( 'Reject', 'menj-click' ) . '</a>';
	}
	if ( '' !== (string) get_post_meta( $post->ID, '_menj_changes', true ) ) {
		$new['menj_changes'] = '<a href="' . esc_url( get_edit_post_link( $post->ID ) . '#menj-listing-changes' ) . '">' . esc_html__( 'Review edits', 'menj-click' ) . '</a>';
	}
	$new['menj_check'] = '<a href="' . esc_url( $url( 'check' ) ) . '">' . esc_html__( 'Check now', 'menj-click' ) . '</a>';
	return array_merge( array_slice( $actions, 0, 1 ), $new, array_slice( $actions, 1 ) );
}
add_filter( 'post_row_actions', 'menj_click_dir_row_actions', 10, 2 );

function menj_click_dir_bulk_actions( $actions ) {
	$actions['menj_approve'] = __( 'Approve', 'menj-click' );
	$actions['menj_check']   = __( 'Check sites now', 'menj-click' );
	return $actions;
}
add_filter( 'bulk_actions-edit-menj_listing', 'menj_click_dir_bulk_actions' );

function menj_click_dir_handle_bulk( $redirect, $action, $ids ) {
	if ( ! in_array( $action, array( 'menj_approve', 'menj_check' ), true ) ) {
		return $redirect;
	}
	foreach ( array_slice( $ids, 0, 50 ) as $id ) {
		if ( ! current_user_can( 'edit_post', $id ) ) {
			continue;
		}
		if ( 'menj_approve' === $action && 'publish' !== get_post_status( $id ) ) {
			menj_click_dir_approve( $id );
		} elseif ( 'menj_check' === $action ) {
			menj_click_dir_check_link( $id );
			menj_click_dir_check_backlink( $id );
		}
	}
	return add_query_arg( 'menj_done', count( $ids ), $redirect );
}
add_filter( 'handle_bulk_actions-edit-menj_listing', 'menj_click_dir_handle_bulk', 10, 3 );

function menj_click_dir_handle_moderate() {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html__( 'You don’t have permission to do that.', 'menj-click' ), 403 );
	}
	check_admin_referer( 'menj_click_dir_moderate_' . $id );
	$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
	switch ( $do ) {
		case 'approve':
			menj_click_dir_approve( $id );
			break;
		case 'reject':
			menj_click_dir_reject( $id, isset( $_GET['reason'] ) ? sanitize_textarea_field( wp_unslash( $_GET['reason'] ) ) : '' );
			break;
		case 'check':
			menj_click_dir_check_link( $id );
			menj_click_dir_check_backlink( $id );
			break;
		case 'apply':
			menj_click_dir_apply_changes( $id );
			break;
		case 'discard':
			delete_post_meta( $id, '_menj_changes' );
			break;
		case 'paid':
			$l = menj_click_listing( $id );
			if ( $l ) {
				$row = menj_click_dir_open_payment( $l, 'manual' );
				menj_click_dir_mark_paid( (int) $row['id'], '', '', __( 'Marked paid by ', 'menj-click' ) . wp_get_current_user()->user_login );
			}
			break;
		case 'resend':
			menj_click_dir_token( $id, true );
			menj_click_dir_mail( 'manage', $id );
			break;
		case 'shortlink':
			$l = menj_click_listing( $id );
			if ( $l && '' === $l['short_slug'] ) {
				$base = menj_click_clean_slug( sanitize_title( $l['title'] ) );
				$slug = '' !== $base ? $base : 'site-' . $id;
				for ( $n = 2; menj_click_slug_conflict( $slug ); $n++ ) {
					$slug = $base . '-' . $n;
				}
				$saved = menj_click_save_link( array( 'slug' => $slug, 'target' => $l['url'], 'title' => $l['title'], 'description' => __( 'Directory listing', 'menj-click' ) ) );
				if ( ! is_wp_error( $saved ) ) {
					update_post_meta( $id, '_menj_short_slug', $slug );
				}
			}
			break;
	}
	$back = wp_get_referer();
	wp_safe_redirect( $back ? remove_query_arg( 'menj_done', $back ) : admin_url( 'edit.php?post_type=menj_listing' ) );
	exit;
}
add_action( 'admin_post_menj_click_dir_moderate', 'menj_click_dir_handle_moderate' );

/**
 * Pending count on the Directory menu.
 */
function menj_click_dir_menu_badge() {
	global $menu;
	$pending = (int) wp_count_posts( 'menj_listing' )->pending;
	if ( ! $pending || ! is_array( $menu ) ) {
		return;
	}
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=menj_listing' === $item[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . number_format_i18n( $pending ) . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}
}
add_action( 'admin_menu', 'menj_click_dir_menu_badge', 99 );

function menj_click_dir_settings_submenu() {
	add_submenu_page( 'edit.php?post_type=menj_listing', __( 'Directory settings', 'menj-click' ), __( 'Settings', 'menj-click' ), 'manage_options', 'admin.php?page=menj-click&tab=directory' );
}
add_action( 'admin_menu', 'menj_click_dir_settings_submenu', 20 );

/* -------------------------------------------------------------------------- */
/* Listing details box                                                         */
/* -------------------------------------------------------------------------- */

function menj_click_dir_meta_boxes() {
	add_meta_box( 'menj-listing-details', __( 'Listing details', 'menj-click' ), 'menj_click_dir_details_box', 'menj_listing', 'normal', 'high' );
	add_meta_box( 'menj-listing-actions', __( 'Owner & payment', 'menj-click' ), 'menj_click_dir_actions_box', 'menj_listing', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'menj_click_dir_meta_boxes' );

function menj_click_dir_details_box( $post ) {
	$l = menj_click_listing( $post );
	wp_nonce_field( 'menj_click_dir_save_' . $post->ID, 'menj_dir_nonce' );
	echo '<div class="menj-admin menj-box">';
	if ( '' !== (string) $l['changes'] ) {
		$changes = json_decode( $l['changes'], true );
		echo '<div id="menj-listing-changes" class="menj-flash is-warning"><p>' . esc_html__( 'The owner submitted these changes:', 'menj-click' ) . '</p><table class="menj-table menj-table--compact"><tbody>';
		foreach ( array( 'title' => __( 'Name', 'menj-click' ), 'url' => __( 'Website', 'menj-click' ), 'description' => __( 'Description', 'menj-click' ), 'tags' => __( 'Tags', 'menj-click' ), 'recpr_url' => __( 'Link-back page', 'menj-click' ) ) as $k => $label ) {
			if ( isset( $changes[ $k ] ) && '' !== (string) $changes[ $k ] ) {
				echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $changes[ $k ] ) . '</td></tr>';
			}
		}
		$mod = function ( $do ) use ( $post ) {
			return wp_nonce_url( admin_url( 'admin-post.php?action=menj_click_dir_moderate&do=' . $do . '&id=' . $post->ID ), 'menj_click_dir_moderate_' . $post->ID );
		};
		echo '</tbody></table><p><a class="button button-primary" href="' . esc_url( $mod( 'apply' ) ) . '">' . esc_html__( 'Apply changes', 'menj-click' ) . '</a> <a class="button-link is-destructive" href="' . esc_url( $mod( 'discard' ) ) . '">' . esc_html__( 'Discard', 'menj-click' ) . '</a></p></div>';
	}
	echo '<div class="menj-grid-2">';
	menj_click_field( 'url', 'menj_listing[url]', $l['url'], __( 'Website', 'menj-click' ), array( 'placeholder' => 'https://' ) );
	$tiers = array();
	foreach ( menj_click_dir_tiers() as $slug => $t ) {
		$tiers[ $slug ] = $t['label'];
	}
	menj_click_field( 'select', 'menj_listing[tier]', $l['tier'], __( 'Type', 'menj-click' ), array( 'options' => $tiers ) );
	menj_click_field( 'url', 'menj_listing[recpr_url]', $l['recpr_url'], __( 'Link-back page', 'menj-click' ), array( 'placeholder' => 'https://' ) );
	menj_click_field(
		'select',
		'menj_listing[rel]',
		$l['rel'],
		__( 'Outbound link', 'menj-click' ),
		array(
			'options' => array( '' => __( 'Automatic (by type)', 'menj-click' ), 'follow' => __( 'Followed', 'menj-click' ), 'ugc nofollow' => 'ugc nofollow', 'nofollow' => 'nofollow', 'sponsored' => 'sponsored' ),
		)
	);
	menj_click_field( 'text', 'menj_listing[expires]', $l['expires'] ? get_date_from_gmt( $l['expires'], 'Y-m-d' ) : '', __( 'Paid until', 'menj-click' ), array( 'placeholder' => 'YYYY-MM-DD', 'help' => __( 'Empty for no end date.', 'menj-click' ) ) );
	menj_click_field( 'select', 'menj_listing[payment]', $l['payment'] ? $l['payment'] : 'none', __( 'Payment', 'menj-click' ), array( 'options' => array( 'none' => __( 'Not needed', 'menj-click' ), 'due' => __( 'Due', 'menj-click' ), 'paid' => __( 'Paid', 'menj-click' ) ) ) );
	echo '</div>';
	if ( menj_click_dir( 'business_fields' ) ) {
		echo '<div class="menj-grid-4">';
		menj_click_field( 'text', 'menj_listing[address]', $l['address'], __( 'Address', 'menj-click' ) );
		menj_click_field( 'text', 'menj_listing[city]', $l['city'], __( 'City', 'menj-click' ) );
		menj_click_field( 'text', 'menj_listing[country]', $l['country'], __( 'Country', 'menj-click' ) );
		menj_click_field( 'text', 'menj_listing[phone]', $l['phone'], __( 'Phone', 'menj-click' ) );
		echo '</div>';
	}
	echo '<div class="menj-grid-2">';
	menj_click_field( 'text', 'menj_listing[owner_name]', $l['owner_name'], __( 'Owner name', 'menj-click' ) );
	menj_click_field( 'email', 'menj_listing[owner_email]', $l['owner_email'], __( 'Owner email', 'menj-click' ) );
	echo '</div></div>';
}

function menj_click_dir_actions_box( $post ) {
	$l   = menj_click_listing( $post );
	$mod = function ( $do ) use ( $post ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=menj_click_dir_moderate&do=' . $do . '&id=' . $post->ID ), 'menj_click_dir_moderate_' . $post->ID );
	};
	echo '<div class="menj-admin menj-box menj-box--side">';
	echo '<p><strong>' . esc_html( number_format_i18n( (int) $l['hits'] ) ) . '</strong> ' . esc_html__( 'visits', 'menj-click' );
	if ( (int) $l['rating_count'] ) {
		echo ' · <strong>★ ' . esc_html( number_format_i18n( (float) $l['rating_avg'], 1 ) ) . '</strong> (' . (int) $l['rating_count'] . ')';
	}
	echo '</p>';
	if ( $l['http_checked'] ) {
		/* translators: 1: HTTP status, 2: date */
		echo '<p class="menj-muted">' . esc_html( sprintf( __( 'Site: HTTP %1$s on %2$s', 'menj-click' ), $l['http_status'] ? $l['http_status'] : '—', mysql2date( get_option( 'date_format' ), $l['http_checked'] ) ) ) . '</p>';
	}
	if ( menj_click_dir_autorenews( $l ) ) {
		/* translators: %s: PayPal subscription ID */
		echo '<p><span class="menj-pill is-ok">' . esc_html__( 'Auto-renews', 'menj-click' ) . '</span> <span class="menj-muted">' . esc_html( sprintf( __( 'PayPal %s', 'menj-click' ), get_post_meta( $post->ID, '_menj_subscr', true ) ) ) . '</span></p>';
	}
	echo '<p><a class="button" href="' . esc_url( $mod( 'check' ) ) . '">' . esc_html__( 'Check site now', 'menj-click' ) . '</a></p>';
	if ( 'due' === $l['payment'] || menj_click_dir_tiers()[ $l['tier'] ]['paid'] ) {
		echo '<p><a class="button" href="' . esc_url( $mod( 'paid' ) ) . '">' . esc_html__( 'Record a payment', 'menj-click' ) . '</a></p>';
	}
	if ( is_email( $l['owner_email'] ) ) {
		echo '<p><a class="button" href="' . esc_url( $mod( 'resend' ) ) . '">' . esc_html__( 'Send a new manage link', 'menj-click' ) . '</a></p>';
	}
	if ( '' === $l['short_slug'] && '' !== $l['url'] ) {
		echo '<p><a class="button" href="' . esc_url( $mod( 'shortlink' ) ) . '">' . esc_html__( 'Create short link', 'menj-click' ) . '</a></p>';
	} elseif ( '' !== $l['short_slug'] ) {
		echo '<p>' . esc_html__( 'Short link:', 'menj-click' ) . ' <code>' . esc_html( menj_click_host() . '/' . $l['short_slug'] ) . '</code></p>';
	}
	$payments = menj_click_dir_listing_payments( $post->ID );
	if ( $payments ) {
		echo '<h4>' . esc_html__( 'Payments', 'menj-click' ) . '</h4><ul class="menj-payments">';
		foreach ( $payments as $p ) {
			echo '<li><code>menj-' . esc_html( $p['id'] ) . '</code> ' . esc_html( $p['currency'] . ' ' . $p['amount'] ) . ( (int) $p['quantity'] > 1 ? ' × ' . (int) $p['quantity'] : '' ) . ' <span class="menj-muted">' . esc_html( menj_click_dir_method_label( $p['method'] ) ) . '</span> <span class="menj-pill ' . ( 'paid' === $p['status'] ? 'is-ok' : 'is-warn' ) . '">' . esc_html( ucfirst( $p['status'] ) ) . '</span>' . menj_click_dir_ipn_details( $p ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}

function menj_click_dir_save_box( $post_id, $post ) {
	if ( ! isset( $_POST['menj_dir_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['menj_dir_nonce'] ) ), 'menj_click_dir_save_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	$in  = isset( $_POST['menj_listing'] ) ? (array) wp_unslash( $_POST['menj_listing'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$url = esc_url_raw( isset( $in['url'] ) ? trim( $in['url'] ) : '', array( 'http', 'https' ) );
	update_post_meta( $post_id, '_menj_url', $url );
	update_post_meta( $post_id, '_menj_domain', menj_click_dir_domain( $url ) );
	menj_click_dir_set_tier( $post_id, isset( $in['tier'] ) ? sanitize_key( $in['tier'] ) : 'free' );
	$recpr = esc_url_raw( isset( $in['recpr_url'] ) ? trim( $in['recpr_url'] ) : '', array( 'http', 'https' ) );
	if ( get_post_meta( $post_id, '_menj_recpr_url', true ) !== $recpr ) {
		update_post_meta( $post_id, '_menj_recpr_url', $recpr );
		update_post_meta( $post_id, '_menj_recpr_status', '' );
		update_post_meta( $post_id, '_menj_recpr_checked', '' );
	}
	$rel = isset( $in['rel'] ) ? sanitize_text_field( $in['rel'] ) : '';
	update_post_meta( $post_id, '_menj_rel', in_array( $rel, array( '', 'follow', 'ugc nofollow', 'nofollow', 'sponsored' ), true ) ? $rel : '' );
	$expires = isset( $in['expires'] ) ? sanitize_text_field( $in['expires'] ) : '';
	update_post_meta( $post_id, '_menj_expires', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ? get_gmt_from_date( $expires . ' 23:59:59' ) : '' );
	$payment = isset( $in['payment'] ) ? sanitize_key( $in['payment'] ) : 'none';
	update_post_meta( $post_id, '_menj_payment', in_array( $payment, array( 'none', 'due', 'paid' ), true ) ? $payment : 'none' );
	foreach ( array( 'address', 'city', 'country', 'phone', 'owner_name' ) as $key ) {
		if ( isset( $in[ $key ] ) ) {
			update_post_meta( $post_id, '_menj_' . $key, sanitize_text_field( $in[ $key ] ) );
		}
	}
	if ( isset( $in['owner_email'] ) ) {
		update_post_meta( $post_id, '_menj_owner_email', sanitize_email( $in['owner_email'] ) );
	}
	if ( '' === (string) get_post_meta( $post_id, '_menj_hits', true ) ) {
		update_post_meta( $post_id, '_menj_hits', 0 );
	}
}
add_action( 'save_post_menj_listing', 'menj_click_dir_save_box', 10, 2 );


/* -------------------------------------------------------------------------- */
/* Category option: closed to new listings                                     */
/* -------------------------------------------------------------------------- */

function menj_click_dir_term_field_add() {
	echo '<div class="form-field"><label><input type="checkbox" name="menj_closed" value="1"> ' . esc_html__( 'Closed to new listings', 'menj-click' ) . '</label><p>' . esc_html__( 'Still shown; just not offered on the submission form.', 'menj-click' ) . '</p></div>';
}
add_action( 'menj_dir_category_add_form_fields', 'menj_click_dir_term_field_add' );

function menj_click_dir_term_field_edit( $term ) {
	echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Submissions', 'menj-click' ) . '</th><td><label><input type="checkbox" name="menj_closed" value="1"' . checked( (bool) get_term_meta( $term->term_id, 'menj_closed', true ), true, false ) . '> ' . esc_html__( 'Closed to new listings', 'menj-click' ) . '</label></td></tr>';
}
add_action( 'menj_dir_category_edit_form_fields', 'menj_click_dir_term_field_edit' );

function menj_click_dir_term_save( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	update_term_meta( $term_id, 'menj_closed', empty( $_POST['menj_closed'] ) ? 0 : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- core term screen verifies its own nonce.
}
add_action( 'created_menj_dir_category', 'menj_click_dir_term_save' );
add_action( 'edited_menj_dir_category', 'menj_click_dir_term_save' );
