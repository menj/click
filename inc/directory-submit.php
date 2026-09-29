<?php
/**
 * Directory: public submission form, email confirmation and the owner's
 * manage page (edit, upgrade, renew, remove). No accounts: owners get a
 * private manage link by email.
 *
 * Spam defences: honeypot, a signed time check, a small sum, a daily
 * per-visitor limit, ban lists and (optionally) a live check of the URL.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/**
 * Messages and field errors for the current request.
 */
function menj_click_dir_state( $key = null, $value = null ) {
	static $state = array( 'errors' => array(), 'values' => array(), 'notice' => '' );
	if ( null !== $value ) {
		$state[ $key ] = $value;
	}
	return null === $key ? $state : $state[ $key ];
}

/* -------------------------------------------------------------------------- */
/* Checks                                                                      */
/* -------------------------------------------------------------------------- */

function menj_click_dir_lines( $text ) {
	return array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', strtolower( (string) $text ) ) ), 'strlen' );
}

function menj_click_dir_domain( $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	return preg_replace( '/^www\./', '', $host );
}

function menj_click_dir_domain_banned( $domain ) {
	foreach ( menj_click_dir_lines( menj_click_dir( 'banned_domains' ) ) as $ban ) {
		$ban = preg_replace( '/^(\*\.|www\.)/', '', $ban );
		if ( $domain === $ban || substr( $domain, -strlen( '.' . $ban ) ) === '.' . $ban ) {
			return true;
		}
	}
	return false;
}

function menj_click_dir_email_banned( $email ) {
	$email = strtolower( $email );
	foreach ( menj_click_dir_lines( menj_click_dir( 'banned_emails' ) ) as $ban ) {
		if ( $email === $ban || ( 0 === strpos( $ban, '@' ) && substr( $email, -strlen( $ban ) ) === $ban ) ) {
			return true;
		}
	}
	return false;
}

function menj_click_dir_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

function menj_click_dir_ip_banned( $ip ) {
	foreach ( menj_click_dir_lines( menj_click_dir( 'banned_ips' ) ) as $ban ) {
		if ( $ip === $ban || ( '*' === substr( $ban, -1 ) && 0 === strpos( $ip, rtrim( $ban, '*' ) ) ) ) {
			return true;
		}
	}
	return false;
}

function menj_click_dir_has_banned_word( $text ) {
	$text = strtolower( $text );
	foreach ( menj_click_dir_lines( menj_click_dir( 'banned_words' ) ) as $word ) {
		if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/u', $text ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Another listing (in any live or waiting state) already uses this domain.
 */
function menj_click_dir_domain_taken( $domain, $exclude = 0 ) {
	$ids = get_posts(
		array(
			'post_type'      => 'menj_listing',
			'post_status'    => array( 'publish', 'pending', 'draft', 'menj_unconfirmed', 'menj_expired' ),
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'post__not_in'   => $exclude ? array( (int) $exclude ) : array(),
			'meta_key'       => '_menj_domain', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $domain, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return (bool) $ids;
}

/**
 * Fetch a URL the way the checkers do.
 *
 * @return array{code: int, body: string, error: string}
 */
function menj_click_dir_fetch( $url, $body = false ) {
	$args = array(
		'timeout'             => 10,
		'redirection'         => 5,
		'user-agent'          => 'Mozilla/5.0 (compatible; menj.click directory checker; +' . home_url( '/directory/' ) . ')',
		'limit_response_size' => 1024 * 1024,
		'sslverify'           => true,
	);
	$response = $body ? wp_safe_remote_get( $url, $args ) : wp_safe_remote_head( $url, $args );
	if ( ! $body && ( is_wp_error( $response ) || in_array( (int) wp_remote_retrieve_response_code( $response ), array( 403, 405, 501 ), true ) ) ) {
		$response = wp_safe_remote_get( $url, $args ); // Some servers refuse HEAD.
	}
	if ( is_wp_error( $response ) ) {
		return array( 'code' => 0, 'body' => '', 'error' => $response->get_error_message() );
	}
	return array( 'code' => (int) wp_remote_retrieve_response_code( $response ), 'body' => (string) wp_remote_retrieve_body( $response ), 'error' => '' );
}

/**
 * Where listings are expected to link back to.
 */
function menj_click_dir_backlink_url() {
	$url = trim( (string) menj_click_dir( 'backlink_url' ) );
	return '' !== $url ? $url : home_url( '/directory/' );
}

/**
 * Look for a link back to this site on a page.
 *
 * @return string ok|nofollow|missing|error
 */
function menj_click_dir_check_reciprocal( $page_url ) {
	$fetched = menj_click_dir_fetch( $page_url, true );
	if ( $fetched['code'] < 200 || $fetched['code'] >= 400 || '' === $fetched['body'] ) {
		return 'error';
	}
	$ours = menj_click_dir_domain( home_url() );
	$doc  = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $fetched['body'] );
	libxml_clear_errors();
	$found = '';
	foreach ( $doc->getElementsByTagName( 'a' ) as $a ) {
		$href = trim( (string) $a->getAttribute( 'href' ) );
		if ( '' === $href || menj_click_dir_domain( $href ) !== $ours ) {
			continue;
		}
		$rel = strtolower( (string) $a->getAttribute( 'rel' ) );
		if ( false === strpos( $rel, 'nofollow' ) && false === strpos( $rel, 'sponsored' ) && false === strpos( $rel, 'ugc' ) ) {
			return 'ok';
		}
		$found = 'nofollow';
	}
	if ( 'nofollow' === $found ) {
		return menj_click_dir( 'reciprocal_dofollow' ) ? 'nofollow' : 'ok';
	}
	return 'missing';
}

/**
 * The link-back HTML owners paste on their site.
 */
function menj_click_dir_backlink_html() {
	$text = trim( (string) menj_click_dir( 'backlink_text' ) );
	$text = '' !== $text ? $text : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' ' . menj_click_dir( 'title' );
	return '<a href="' . esc_url( menj_click_dir_backlink_url() ) . '">' . esc_html( $text ) . '</a>';
}

/* -------------------------------------------------------------------------- */
/* Spam guard                                                                  */
/* -------------------------------------------------------------------------- */

/**
 * Hidden fields: honeypot, signed timestamp, and a signed sum.
 */
function menj_click_dir_guard_fields() {
	$a    = wp_rand( 1, 9 );
	$b    = wp_rand( 1, 9 );
	$time = time();
	$sig  = hash_hmac( 'sha256', $time . '|' . ( $a + $b ), wp_salt( 'nonce' ) );
	return '<div class="menj-hp" aria-hidden="true"><label for="menj-hp">' . esc_html__( 'Leave this empty', 'menj-click' ) . '</label><input type="text" id="menj-hp" name="menj_hp" value="" tabindex="-1" autocomplete="off"></div>'
		. '<input type="hidden" name="menj_ts" value="' . esc_attr( $time . '.' . $sig ) . '">'
		. '<div class="menj-form-field menj-form-field--sum"><label for="menj-sum">' . esc_html( sprintf( /* translators: 1, 2: numbers */ __( 'Quick check: what is %1$d + %2$d?', 'menj-click' ), $a, $b ) ) . '</label>'
		. '<input type="text" inputmode="numeric" id="menj-sum" name="menj_sum" required autocomplete="off" size="4"></div>';
}

/**
 * @return string Error message, or ''.
 */
function menj_click_dir_guard_check() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST['menj_hp'] ) ) {
		return __( 'Something went wrong. Please try again.', 'menj-click' );
	}
	$parts = explode( '.', isset( $_POST['menj_ts'] ) ? sanitize_text_field( wp_unslash( $_POST['menj_ts'] ) ) : '', 2 );
	$sum   = isset( $_POST['menj_sum'] ) ? (int) $_POST['menj_sum'] : -1;
	// phpcs:enable
	if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0] . '|' . $sum, wp_salt( 'nonce' ) ), $parts[1] ) ) {
		return __( 'That sum isn’t right. Please try again.', 'menj-click' );
	}
	$age = time() - (int) $parts[0];
	if ( $age < 4 || $age > 2 * HOUR_IN_SECONDS ) {
		return __( 'The form timed out. Please try again.', 'menj-click' );
	}
	return '';
}

/**
 * Daily submissions per visitor.
 */
function menj_click_dir_rate_key() {
	return 'menj_dir_rate_' . substr( hash_hmac( 'sha256', menj_click_dir_ip() . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) ), 0, 20 );
}

/* -------------------------------------------------------------------------- */
/* Form handling                                                               */
/* -------------------------------------------------------------------------- */

/**
 * Read and validate listing fields from the request.
 *
 * @param bool $is_new  New submission (tier, owner and terms apply).
 * @param int  $exclude Listing being edited (for the unique-domain check).
 * @return array{values: array, errors: array}
 */
function menj_click_dir_read_fields( $is_new, $exclude = 0 ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$in = isset( $_POST['listing'] ) && is_array( $_POST['listing'] ) ? wp_unslash( $_POST['listing'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	// phpcs:enable
	$d      = menj_click_settings( 'directory' );
	$get    = function ( $key ) use ( $in ) {
		return isset( $in[ $key ] ) ? trim( (string) $in[ $key ] ) : '';
	};
	$v      = array(
		'url'         => esc_url_raw( $get( 'url' ), array( 'http', 'https' ) ),
		'title'       => sanitize_text_field( $get( 'title' ) ),
		'description' => sanitize_textarea_field( $get( 'description' ) ),
		'category'    => absint( $get( 'category' ) ),
		'tags'        => sanitize_text_field( $get( 'tags' ) ),
		'recpr_url'   => esc_url_raw( $get( 'recpr_url' ), array( 'http', 'https' ) ),
		'address'     => sanitize_text_field( $get( 'address' ) ),
		'city'        => sanitize_text_field( $get( 'city' ) ),
		'country'     => sanitize_text_field( $get( 'country' ) ),
		'phone'       => sanitize_text_field( $get( 'phone' ) ),
	);
	if ( $is_new ) {
		$v['tier']        = sanitize_key( $get( 'tier' ) );
		$v['owner_name']  = sanitize_text_field( $get( 'owner_name' ) );
		$v['owner_email'] = sanitize_email( $get( 'owner_email' ) );
		$v['agree']       = '' !== $get( 'agree' );
	}
	$e = array();

	if ( '' === $v['url'] || ! wp_http_validate_url( $v['url'] ) ) {
		$e['url'] = __( 'Enter the site’s full address, starting with https://', 'menj-click' );
	} else {
		$domain = menj_click_dir_domain( $v['url'] );
		if ( menj_click_dir_domain_banned( $domain ) ) {
			$e['url'] = __( 'This site can’t be listed.', 'menj-click' );
		} elseif ( $d['unique_domain'] && menj_click_dir_domain_taken( $domain, $exclude ) ) {
			$e['url'] = __( 'This site is already in the directory.', 'menj-click' );
		} elseif ( $d['check_online'] ) {
			$check = menj_click_dir_fetch( $v['url'] );
			if ( $check['code'] < 200 || $check['code'] >= 400 ) {
				$e['url'] = __( 'We couldn’t reach this address. Check it opens in your browser.', 'menj-click' );
			}
		}
	}

	$len = function_exists( 'mb_strlen' ) ? 'mb_strlen' : 'strlen';
	if ( $len( $v['title'] ) < (int) $d['title_min'] || $len( $v['title'] ) > (int) $d['title_max'] ) {
		/* translators: 1: min, 2: max */
		$e['title'] = sprintf( __( 'Use %1$d–%2$d characters for the name.', 'menj-click' ), $d['title_min'], $d['title_max'] );
	}
	if ( $len( $v['description'] ) < (int) $d['desc_min'] || $len( $v['description'] ) > (int) $d['desc_max'] ) {
		/* translators: 1: min, 2: max */
		$e['description'] = sprintf( __( 'Use %1$d–%2$d characters for the description.', 'menj-click' ), $d['desc_min'], $d['desc_max'] );
	}
	if ( menj_click_dir_has_banned_word( $v['title'] . ' ' . $v['description'] ) ) {
		$e['description'] = __( 'This description contains words we don’t accept.', 'menj-click' );
	}

	$term = $v['category'] ? get_term( $v['category'], 'menj_dir_category' ) : null;
	if ( ! $term || is_wp_error( $term ) ) {
		$e['category'] = __( 'Choose a category.', 'menj-click' );
	} elseif ( get_term_meta( $term->term_id, 'menj_closed', true ) ) {
		$e['category'] = __( 'This category isn’t taking new listings. Choose another.', 'menj-click' );
	}

	$tier = $is_new ? $v['tier'] : '';
	if ( $is_new ) {
		$tiers = menj_click_dir_tiers();
		if ( ! isset( $tiers[ $tier ] ) || ! $tiers[ $tier ]['enabled'] ) {
			$e['tier'] = __( 'Choose a listing type.', 'menj-click' );
		}
		if ( '' === $v['owner_name'] ) {
			$e['owner_name'] = __( 'Tell us your name.', 'menj-click' );
		}
		if ( ! is_email( $v['owner_email'] ) ) {
			$e['owner_email'] = __( 'Enter a valid email address — we send the confirmation there.', 'menj-click' );
		} elseif ( menj_click_dir_email_banned( $v['owner_email'] ) ) {
			$e['owner_email'] = __( 'This email address can’t be used.', 'menj-click' );
		}
		if ( ! $v['agree'] ) {
			$e['agree'] = __( 'Please accept the listing guidelines.', 'menj-click' );
		}
	}

	$recpr_mode = $d['reciprocal'];
	$recpr_used = 'off' !== $recpr_mode && ( ! $is_new || 'free' === $tier );
	if ( $recpr_used && '' !== $v['recpr_url'] ) {
		if ( ! isset( $e['url'] ) && menj_click_dir_domain( $v['recpr_url'] ) !== menj_click_dir_domain( $v['url'] ) ) {
			$e['recpr_url'] = __( 'The link-back page must be on the same site you’re listing.', 'menj-click' );
		} elseif ( ! isset( $e['recpr_url'] ) ) {
			$v['recpr_status'] = menj_click_dir_check_reciprocal( $v['recpr_url'] );
			if ( 'ok' !== $v['recpr_status'] && ( 'required' === $recpr_mode ) ) {
				$e['recpr_url'] = 'nofollow' === $v['recpr_status']
					? __( 'We found the link, but it’s marked nofollow. Please remove rel="nofollow".', 'menj-click' )
					: __( 'We couldn’t find a link to us on that page yet. Add the code below, then try again.', 'menj-click' );
			}
		}
	} elseif ( $recpr_used && 'required' === $recpr_mode && ( $is_new ? 'free' === $tier : false ) ) {
		$e['recpr_url'] = __( 'Free listings need a link back to us. Enter the page where you added it.', 'menj-click' );
	}
	if ( ! $recpr_used ) {
		$v['recpr_url'] = '';
	}

	return array( 'values' => $v, 'errors' => $e );
}

/**
 * Turn plain text into paragraph blocks.
 */
function menj_click_dir_blocks_from_text( $text ) {
	$out = '';
	foreach ( preg_split( "/\n\s*\n/", trim( $text ) ) as $para ) {
		if ( '' !== trim( $para ) ) {
			$out .= "<!-- wp:paragraph -->\n<p>" . nl2br( esc_html( trim( $para ) ), false ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		}
	}
	return trim( $out );
}

/**
 * Save listing fields to a post.
 */
function menj_click_dir_apply_fields( $id, array $v ) {
	$domain = menj_click_dir_domain( $v['url'] );
	update_post_meta( $id, '_menj_url', $v['url'] );
	update_post_meta( $id, '_menj_domain', $domain );
	wp_set_object_terms( $id, array( (int) $v['category'] ), 'menj_dir_category' );
	if ( menj_click_dir( 'tags' ) ) {
		$tags = array_slice( array_filter( array_map( 'trim', explode( ',', $v['tags'] ) ), 'strlen' ), 0, 5 );
		wp_set_object_terms( $id, $tags, 'menj_dir_tag' );
	}
	$old_recpr = (string) get_post_meta( $id, '_menj_recpr_url', true );
	update_post_meta( $id, '_menj_recpr_url', $v['recpr_url'] );
	if ( '' === $v['recpr_url'] ) {
		update_post_meta( $id, '_menj_recpr_status', '' );
	} elseif ( isset( $v['recpr_status'] ) ) {
		update_post_meta( $id, '_menj_recpr_status', $v['recpr_status'] );
		update_post_meta( $id, '_menj_recpr_checked', current_time( 'mysql', true ) );
		if ( 'ok' === $v['recpr_status'] ) {
			update_post_meta( $id, '_menj_recpr_deadline', '' );
		}
	} elseif ( $old_recpr !== $v['recpr_url'] ) {
		update_post_meta( $id, '_menj_recpr_status', '' );
	}
	if ( menj_click_dir( 'business_fields' ) ) {
		foreach ( array( 'address', 'city', 'country', 'phone' ) as $key ) {
			update_post_meta( $id, '_menj_' . $key, $v[ $key ] );
		}
	}
}

/**
 * Handle posts to /directory/submit/ and /directory/manage/{token}/.
 */
function menj_click_dir_handle_post() {
	if ( 'post' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || ! menj_click_dir_enabled() ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	$view = get_query_var( 'menj_dir' );
	if ( 'submit' === $view && isset( $_POST['menj_dir_submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		menj_click_dir_handle_submit();
	} elseif ( 'manage' === $view && isset( $_POST['menj_dir_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		menj_click_dir_handle_manage( get_query_var( 'menj_token' ) );
	}
}
add_action( 'template_redirect', 'menj_click_dir_handle_post' );

function menj_click_dir_handle_submit() {
	if ( ! menj_click_dir( 'submissions' ) ) {
		return;
	}
	$read = menj_click_dir_read_fields( true );
	menj_click_dir_state( 'values', $read['values'] );

	$guard = menj_click_dir_guard_check();
	if ( '' !== $guard ) {
		$read['errors']['form'] = $guard;
	}
	$ip = menj_click_dir_ip();
	if ( menj_click_dir_ip_banned( $ip ) ) {
		$read['errors']['form'] = __( 'Submissions from your connection aren’t accepted.', 'menj-click' );
	}
	$rate_key = menj_click_dir_rate_key();
	$count    = (int) get_transient( $rate_key );
	if ( (int) menj_click_dir( 'max_per_ip' ) > 0 && $count >= (int) menj_click_dir( 'max_per_ip' ) ) {
		$read['errors']['form'] = __( 'You’ve reached today’s limit for submissions. Please try again tomorrow.', 'menj-click' );
	}
	if ( $read['errors'] ) {
		menj_click_dir_state( 'errors', $read['errors'] );
		return;
	}

	$v  = $read['values'];
	$id = wp_insert_post(
		array(
			'post_type'    => 'menj_listing',
			'post_status'  => menj_click_dir( 'email_confirm' ) ? 'menj_unconfirmed' : 'pending',
			'post_title'   => $v['title'],
			'post_content' => menj_click_dir_blocks_from_text( $v['description'] ),
			'post_excerpt' => wp_trim_words( $v['description'], 40 ),
			'post_author'  => 0,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		menj_click_dir_state( 'errors', array( 'form' => __( 'Your listing couldn’t be saved. Please try again.', 'menj-click' ) ) );
		return;
	}
	menj_click_dir_apply_fields( $id, $v );
	menj_click_dir_set_tier( $id, $v['tier'] );
	update_post_meta( $id, '_menj_owner_name', $v['owner_name'] );
	update_post_meta( $id, '_menj_owner_email', $v['owner_email'] );
	update_post_meta( $id, '_menj_payment', menj_click_dir_tiers()[ $v['tier'] ]['paid'] ? 'due' : 'none' );
	update_post_meta( $id, '_menj_hits', 0 );
	update_post_meta( $id, '_menj_submit_ip', substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 16 ) );
	set_transient( $rate_key, $count + 1, DAY_IN_SECONDS );
	menj_click_dir_token( $id );

	if ( menj_click_dir( 'email_confirm' ) ) {
		menj_click_dir_mail( 'confirm', $id );
		$done = 'confirm';
	} else {
		menj_click_dir_advance( $id );
		$done = menj_click_dir_tiers()[ $v['tier'] ]['paid'] ? 'pay' : 'review';
	}
	if ( 'pay' === $done ) {
		wp_safe_redirect( menj_click_dir_url( 'pay', menj_click_dir_token( $id ) ) );
	} else {
		wp_safe_redirect( add_query_arg( 'done', $done, menj_click_dir_url( 'submit' ) ) );
	}
	exit;
}

function menj_click_dir_handle_manage( $token ) {
	$id = menj_click_dir_listing_by_token( $token );
	if ( ! $id || ! isset( $_POST['menj_nonce'] ) || ! hash_equals( substr( hash_hmac( 'sha256', 'manage|' . $token, wp_salt( 'nonce' ) ), 0, 20 ), sanitize_text_field( wp_unslash( $_POST['menj_nonce'] ) ) ) ) {
		menj_click_dir_state( 'errors', array( 'form' => __( 'This link has expired. Request a new one from the site owner.', 'menj-click' ) ) );
		return;
	}
	$action = sanitize_key( wp_unslash( $_POST['menj_dir_action'] ) );
	$l      = menj_click_listing( $id );
	$back   = menj_click_dir_url( 'manage', $token );

	if ( 'remove' === $action ) {
		wp_trash_post( $id );
		wp_safe_redirect( add_query_arg( 'removed', 1, menj_click_dir_url() ) );
		exit;
	}

	if ( 'upgrade' === $action ) {
		$tier  = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : '';
		$tiers = menj_click_dir_tiers();
		if ( isset( $tiers[ $tier ] ) && $tiers[ $tier ]['enabled'] && $tiers[ $tier ]['paid'] && $tier !== $l['tier'] ) {
			update_post_meta( $id, '_menj_upgrade_to', $tier );
			wp_safe_redirect( menj_click_dir_url( 'pay', $token ) );
			exit;
		}
		wp_safe_redirect( $back );
		exit;
	}

	if ( 'edit' === $action ) {
		$read = menj_click_dir_read_fields( false, $id );
		menj_click_dir_state( 'values', $read['values'] );
		if ( $read['errors'] ) {
			menj_click_dir_state( 'errors', $read['errors'] );
			return;
		}
		$v = $read['values'];
		if ( 'publish' === $l['post']->post_status ) {
			// Live listings: changes wait for review, the listing stays up as it was.
			update_post_meta( $id, '_menj_changes', wp_slash( wp_json_encode( $v ) ) );
			menj_click_dir_mail( 'admin', $id, array( 'event' => __( 'The owner edited the listing. Apply or discard the changes.', 'menj-click' ) ) );
			wp_safe_redirect( add_query_arg( 'saved', 'review', $back ) );
		} else {
			wp_update_post( array( 'ID' => $id, 'post_title' => $v['title'], 'post_content' => menj_click_dir_blocks_from_text( $v['description'] ), 'post_excerpt' => wp_trim_words( $v['description'], 40 ) ) );
			menj_click_dir_apply_fields( $id, $v );
			wp_safe_redirect( add_query_arg( 'saved', 'now', $back ) );
		}
		exit;
	}
}

/**
 * Apply an owner's pending changes (admin action).
 */
function menj_click_dir_apply_changes( $id ) {
	$changes = json_decode( (string) get_post_meta( $id, '_menj_changes', true ), true );
	if ( ! is_array( $changes ) ) {
		return false;
	}
	wp_update_post( array( 'ID' => $id, 'post_title' => $changes['title'], 'post_content' => menj_click_dir_blocks_from_text( $changes['description'] ), 'post_excerpt' => wp_trim_words( $changes['description'], 40 ) ) );
	menj_click_dir_apply_fields( $id, $changes );
	delete_post_meta( $id, '_menj_changes' );
	return true;
}

/* -------------------------------------------------------------------------- */
/* Views                                                                       */
/* -------------------------------------------------------------------------- */

function menj_click_dir_crumb_home() {
	return array( array( menj_click_dir( 'title' ), menj_click_dir_url() ) );
}

function menj_click_dir_notice( $text, $type = 'info' ) {
	return '<div class="menj-dir-notice is-' . esc_attr( $type ) . '" role="' . ( 'error' === $type ? 'alert' : 'status' ) . '">' . wp_kses_post( wpautop( $text ) ) . '</div>';
}

/**
 * Category options, indented by depth; closed categories are disabled.
 */
function menj_click_dir_category_options( $selected = 0, $parent = 0, $depth = 0 ) {
	$out   = '';
	$terms = get_terms( array( 'taxonomy' => 'menj_dir_category', 'parent' => $parent, 'hide_empty' => false, 'orderby' => 'name' ) );
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$closed = (bool) get_term_meta( $term->term_id, 'menj_closed', true );
		$out   .= '<option value="' . esc_attr( $term->term_id ) . '"' . selected( (int) $selected, $term->term_id, false ) . disabled( $closed, true, false ) . '>' . str_repeat( '— ', $depth ) . esc_html( $term->name ) . '</option>';
		$out   .= menj_click_dir_category_options( $selected, $term->term_id, $depth + 1 );
	}
	return $out;
}

/**
 * One form field.
 */
function menj_click_dir_field( $key, $label, $type, $value, array $args = array() ) {
	$errors = menj_click_dir_state( 'errors' );
	$id     = 'menj-f-' . $key;
	$err    = isset( $errors[ $key ] ) ? $errors[ $key ] : '';
	$attrs  = ( ! empty( $args['required'] ) ? ' required' : '' ) . ( $err ? ' aria-invalid="true" aria-describedby="' . $id . '-error"' : ( ! empty( $args['help'] ) ? ' aria-describedby="' . $id . '-help"' : '' ) );
	foreach ( array( 'placeholder', 'maxlength', 'autocomplete', 'inputmode' ) as $a ) {
		if ( isset( $args[ $a ] ) ) {
			$attrs .= ' ' . $a . '="' . esc_attr( $args[ $a ] ) . '"';
		}
	}
	if ( isset( $args['counter'] ) ) {
		$attrs .= ' data-menj-counter="' . esc_attr( $args['counter'] ) . '"';
	}
	$out = '<div class="menj-form-field' . ( $err ? ' has-error' : '' ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( empty( $args['required'] ) ? ' <span class="menj-optional">' . esc_html__( '(optional)', 'menj-click' ) . '</span>' : '' ) . '</label>';
	if ( 'textarea' === $type ) {
		$out .= '<textarea id="' . esc_attr( $id ) . '" name="listing[' . esc_attr( $key ) . ']" rows="5"' . $attrs . '>' . esc_textarea( $value ) . '</textarea>';
	} elseif ( 'select' === $type ) {
		$out .= '<select id="' . esc_attr( $id ) . '" name="listing[' . esc_attr( $key ) . ']"' . $attrs . '><option value="">' . esc_html__( 'Choose…', 'menj-click' ) . '</option>' . $args['options'] . '</select>';
	} else {
		$out .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="listing[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"' . $attrs . '>';
	}
	if ( isset( $args['counter'] ) ) {
		$out .= '<span class="menj-counter" data-menj-counter-for="' . esc_attr( $id ) . '" aria-live="polite"></span>';
	}
	if ( $err ) {
		$out .= '<p class="menj-form-error" id="' . esc_attr( $id ) . '-error">' . esc_html( $err ) . '</p>';
	} elseif ( ! empty( $args['help'] ) ) {
		$out .= '<p class="menj-form-help" id="' . esc_attr( $id ) . '-help">' . wp_kses( $args['help'], array( 'code' => array(), 'a' => array( 'href' => array() ) ) ) . '</p>';
	}
	return $out . '</div>';
}

/**
 * Listing fields shared by the submit and manage forms.
 */
function menj_click_dir_listing_fields( array $v, $is_new, $paid = false ) {
	$d   = menj_click_settings( 'directory' );
	$val = function ( $k ) use ( $v ) {
		return isset( $v[ $k ] ) ? $v[ $k ] : '';
	};
	$out  = '<div class="menj-form-grid">';
	$out .= menj_click_dir_field( 'url', __( 'Website address', 'menj-click' ), 'url', $val( 'url' ), array( 'required' => true, 'placeholder' => 'https://', 'autocomplete' => 'url' ) );
	$out .= menj_click_dir_field( 'title', __( 'Name', 'menj-click' ), 'text', $val( 'title' ), array( 'required' => true, 'maxlength' => $d['title_max'], 'counter' => $d['title_max'] ) );
	$out .= '</div>';
	$out .= menj_click_dir_field( 'description', __( 'Description', 'menj-click' ), 'textarea', $val( 'description' ), array( 'required' => (int) $d['desc_min'] > 0, 'maxlength' => $d['desc_max'], 'counter' => $d['desc_max'], 'help' => __( 'What the site offers, in your own words. No keyword lists.', 'menj-click' ) ) );
	$out .= '<div class="menj-form-grid">';
	$out .= menj_click_dir_field( 'category', __( 'Category', 'menj-click' ), 'select', '', array( 'required' => true, 'options' => menj_click_dir_category_options( (int) $val( 'category' ) ) ) );
	if ( $d['tags'] ) {
		$out .= menj_click_dir_field( 'tags', __( 'Tags', 'menj-click' ), 'text', $val( 'tags' ), array( 'help' => __( 'Up to five, separated by commas.', 'menj-click' ) ) );
	}
	$out .= '</div>';
	if ( $d['business_fields'] ) {
		$out .= '<div class="menj-form-grid">';
		$out .= menj_click_dir_field( 'address', __( 'Street address', 'menj-click' ), 'text', $val( 'address' ), array( 'autocomplete' => 'street-address' ) );
		$out .= menj_click_dir_field( 'city', __( 'City', 'menj-click' ), 'text', $val( 'city' ), array( 'autocomplete' => 'address-level2' ) );
		$out .= menj_click_dir_field( 'country', __( 'Country', 'menj-click' ), 'text', $val( 'country' ), array( 'autocomplete' => 'country-name' ) );
		$out .= menj_click_dir_field( 'phone', __( 'Phone', 'menj-click' ), 'tel', $val( 'phone' ), array( 'autocomplete' => 'tel' ) );
		$out .= '</div>';
	}
	if ( 'off' !== $d['reciprocal'] && ! $paid ) {
		$required = 'required' === $d['reciprocal'];
		$help     = $required
			? __( 'Free listings need a link back to us. Add the code below to a page on your site, then enter that page’s address.', 'menj-click' )
			: __( 'Linking back is optional, but listings that do are reviewed first and get a followed link.', 'menj-click' );
		$out     .= '<fieldset class="menj-recpr" data-menj-recpr' . ( $is_new ? ' data-required="' . ( $required ? '1' : '0' ) . '"' : '' ) . '><legend>' . esc_html__( 'Link back to us', 'menj-click' ) . '</legend>';
		$out     .= '<p class="menj-form-help">' . esc_html( $help ) . '</p>';
		$out     .= '<div class="menj-snippet"><code id="menj-snippet">' . esc_html( menj_click_dir_backlink_html() ) . '</code><button type="button" class="menj-copy" data-menj-copy="' . esc_attr( menj_click_dir_backlink_html() ) . '" data-copied="' . esc_attr__( 'Copied', 'menj-click' ) . '">' . esc_html__( 'Copy', 'menj-click' ) . '</button></div>';
		$out     .= menj_click_dir_field( 'recpr_url', __( 'Page with the link', 'menj-click' ), 'url', $val( 'recpr_url' ), array( 'placeholder' => 'https://', 'help' => $is_new && $required ? __( 'Only needed for Free listings.', 'menj-click' ) : '' ) );
		$out     .= '</fieldset>';
	}
	return $out;
}

/**
 * The tier chooser.
 */
function menj_click_dir_tier_cards( $selected ) {
	$out = '<fieldset class="menj-tiers"><legend>' . esc_html__( 'Listing type', 'menj-click' ) . '</legend><div class="menj-tiers__grid">';
	foreach ( menj_click_dir_tiers() as $slug => $tier ) {
		if ( ! $tier['enabled'] ) {
			continue;
		}
		$price = $tier['paid'] ? esc_html( menj_click_dir_money( $tier['price'] ) ) . ' <small>' . esc_html( menj_click_dir_period_label( $tier['period'] ) ) . '</small>' : esc_html( menj_click_dir_money( 0 ) );
		$out  .= '<label class="menj-tier is-' . esc_attr( $slug ) . '"><input type="radio" name="listing[tier]" value="' . esc_attr( $slug ) . '"' . checked( $selected, $slug, false ) . ' required data-paid="' . ( $tier['paid'] ? '1' : '0' ) . '">'
			. '<span class="menj-tier__name">' . esc_html( $tier['label'] ) . '</span><span class="menj-tier__price">' . $price . '</span><ul>';
		foreach ( $tier['benefits'] as $benefit ) {
			$out .= '<li>' . esc_html( $benefit ) . '</li>';
		}
		$out .= '</ul></label>';
	}
	$errors = menj_click_dir_state( 'errors' );
	return $out . '</div>' . ( isset( $errors['tier'] ) ? '<p class="menj-form-error">' . esc_html( $errors['tier'] ) . '</p>' : '' ) . '</fieldset>';
}

function menj_click_dir_view_submit() {
	$header = menj_click_dir_header( __( 'Submit a site', 'menj-click' ), __( 'Suggest a website, tool or business for the directory.', 'menj-click' ), menj_click_dir_crumb_home() );
	if ( ! menj_click_dir( 'submissions' ) ) {
		return $header . menj_click_dir_notice( __( 'The directory isn’t taking new submissions right now.', 'menj-click' ) );
	}
	$done = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'confirm' === $done ) {
		return $header . menj_click_dir_notice( '<strong>' . __( 'Check your email.', 'menj-click' ) . '</strong> ' . __( 'We’ve sent you a link to confirm your listing. It won’t be reviewed until you click it.', 'menj-click' ), 'success' );
	}
	if ( 'review' === $done ) {
		return $header . menj_click_dir_notice( '<strong>' . __( 'Thanks!', 'menj-click' ) . '</strong> ' . __( 'Your listing is in the review queue. We’ll email you when it’s live.', 'menj-click' ), 'success' );
	}

	$v      = menj_click_dir_state( 'values' );
	$errors = menj_click_dir_state( 'errors' );
	$first  = '';
	foreach ( menj_click_dir_tiers() as $slug => $t ) {
		if ( $t['enabled'] ) {
			$first = $slug;
			break;
		}
	}
	$d    = menj_click_settings( 'directory' );
	$form = '';
	if ( $errors ) {
		$form .= menj_click_dir_notice( isset( $errors['form'] ) ? $errors['form'] : __( 'Please fix the highlighted fields.', 'menj-click' ), 'error' );
	}
	$form .= '<form class="menj-form" method="post" action="' . esc_url( menj_click_dir_url( 'submit' ) ) . '" novalidate data-menj-form>';
	$form .= '<input type="hidden" name="menj_dir_submit" value="1">';
	$form .= menj_click_dir_tier_cards( isset( $v['tier'] ) ? $v['tier'] : $first );
	$form .= '<h2 class="menj-form-section">' . esc_html__( 'The site', 'menj-click' ) . '</h2>';
	$form .= menj_click_dir_listing_fields( is_array( $v ) ? $v : array(), true );
	$form .= '<h2 class="menj-form-section">' . esc_html__( 'You', 'menj-click' ) . '</h2><div class="menj-form-grid">';
	$form .= menj_click_dir_field( 'owner_name', __( 'Your name', 'menj-click' ), 'text', isset( $v['owner_name'] ) ? $v['owner_name'] : '', array( 'required' => true, 'autocomplete' => 'name' ) );
	$form .= menj_click_dir_field( 'owner_email', __( 'Your email', 'menj-click' ), 'email', isset( $v['owner_email'] ) ? $v['owner_email'] : '', array( 'required' => true, 'autocomplete' => 'email', 'help' => __( 'Never shown publicly. We send your manage link here.', 'menj-click' ) ) );
	$form .= '</div>';
	if ( '' !== trim( (string) $d['terms'] ) ) {
		$form .= '<div class="menj-terms">' . wp_kses_post( wpautop( $d['terms'] ) ) . '</div>';
	}
	$form .= '<div class="menj-form-check' . ( isset( $errors['agree'] ) ? ' has-error' : '' ) . '"><input type="checkbox" id="menj-agree" name="listing[agree]" value="1"' . checked( ! empty( $v['agree'] ), true, false ) . ' required><label for="menj-agree">' . esc_html__( 'I own or represent this site, and it follows the listing guidelines.', 'menj-click' ) . '</label></div>';
	$form .= menj_click_dir_guard_fields();
	$form .= '<div class="menj-form-actions"><button type="submit" class="menj-dir-button">' . esc_html__( 'Submit listing', 'menj-click' ) . '</button><span class="menj-form-help" data-menj-pay-note hidden>' . esc_html__( 'You’ll pay after confirming your email.', 'menj-click' ) . '</span></div>';
	$form .= '</form>';
	return $header . $form;
}

function menj_click_dir_view_confirm() {
	$header = menj_click_dir_header( __( 'Confirm your listing', 'menj-click' ), '', menj_click_dir_crumb_home() );
	$token  = get_query_var( 'menj_token' );
	$id     = menj_click_dir_listing_by_token( $token );
	if ( ! $id ) {
		return $header . menj_click_dir_notice( __( 'This link isn’t valid any more. If you submitted a site more than a week ago without confirming, please submit it again.', 'menj-click' ), 'error' );
	}
	if ( 'menj_unconfirmed' === get_post_status( $id ) ) {
		menj_click_dir_advance( $id );
	}
	$l    = menj_click_listing( $id );
	$paid = menj_click_dir_tiers()[ $l['tier'] ]['paid'] && 'paid' !== $l['payment'];
	$msg  = '<strong>' . __( 'Email confirmed.', 'menj-click' ) . '</strong> ';
	$msg .= $paid ? __( 'One step left: payment.', 'menj-click' ) : ( 'publish' === $l['post']->post_status ? __( 'Your listing is live.', 'menj-click' ) : __( 'Your listing is in the review queue. We’ll email you when it’s live.', 'menj-click' ) );
	$html = $header . menj_click_dir_notice( $msg, 'success' );
	$html .= '<p class="menj-dir-actions">';
	if ( $paid ) {
		$html .= '<a class="menj-dir-button" href="' . esc_url( menj_click_dir_url( 'pay', $token ) ) . '">' . esc_html__( 'Continue to payment', 'menj-click' ) . '</a> ';
	}
	return $html . '<a class="menj-dir-link" href="' . esc_url( menj_click_dir_url( 'manage', $token ) ) . '">' . esc_html__( 'Manage your listing', 'menj-click' ) . '</a></p>';
}

/**
 * Human status of a listing, for its owner.
 */
function menj_click_dir_status_label( array $l ) {
	$status = $l['post']->post_status;
	if ( 'publish' === $status ) {
		return array( 'ok', __( 'Live', 'menj-click' ) );
	}
	if ( 'pending' === $status ) {
		return 'due' === $l['payment'] ? array( 'warn', __( 'Waiting for payment', 'menj-click' ) ) : array( 'warn', __( 'In review', 'menj-click' ) );
	}
	if ( 'menj_unconfirmed' === $status ) {
		return array( 'warn', __( 'Waiting for email confirmation', 'menj-click' ) );
	}
	if ( 'menj_expired' === $status ) {
		return array( 'bad', __( 'Expired', 'menj-click' ) );
	}
	return array( 'bad', __( 'Hidden', 'menj-click' ) );
}

function menj_click_dir_view_manage() {
	$token  = get_query_var( 'menj_token' );
	$id     = menj_click_dir_listing_by_token( $token );
	$header = menj_click_dir_header( __( 'Manage your listing', 'menj-click' ), '', menj_click_dir_crumb_home() );
	if ( ! $id ) {
		return $header . menj_click_dir_notice( __( 'This manage link isn’t valid any more.', 'menj-click' ), 'error' );
	}
	$l      = menj_click_listing( $id );
	$tiers  = menj_click_dir_tiers();
	$tier   = $tiers[ $l['tier'] ];
	$nonce  = substr( hash_hmac( 'sha256', 'manage|' . $token, wp_salt( 'nonce' ) ), 0, 20 );
	$status = menj_click_dir_status_label( $l );
	$html   = $header;

	$saved = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'review' === $saved ) {
		$html .= menj_click_dir_notice( __( 'Thanks. Your changes will appear once they’re reviewed; the listing stays up as it is until then.', 'menj-click' ), 'success' );
	} elseif ( 'now' === $saved ) {
		$html .= menj_click_dir_notice( __( 'Changes saved.', 'menj-click' ), 'success' );
	}
	if ( isset( $_GET['paid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$html .= menj_click_dir_notice( __( 'Thanks for your payment. It can take a minute to be confirmed; we’ll email you a receipt.', 'menj-click' ), 'success' );
	}
	$errors = menj_click_dir_state( 'errors' );
	if ( $errors ) {
		$html .= menj_click_dir_notice( isset( $errors['form'] ) ? $errors['form'] : __( 'Please fix the highlighted fields.', 'menj-click' ), 'error' );
	}

	$html .= '<section class="menj-manage-summary">' . menj_click_dir_monogram( $l ) . '<div><h2>' . esc_html( $l['title'] ) . '</h2><p>' . esc_html( $l['domain'] ) . '</p></div>';
	$html .= '<dl class="menj-listing__facts">';
	$html .= '<div><dt>' . esc_html__( 'Status', 'menj-click' ) . '</dt><dd><span class="menj-dir-pill is-' . esc_attr( $status[0] ) . '">' . esc_html( $status[1] ) . '</span></dd></div>';
	$html .= '<div><dt>' . esc_html__( 'Type', 'menj-click' ) . '</dt><dd>' . esc_html( $tier['label'] ) . '</dd></div>';
	if ( $tier['paid'] && $l['expires'] ) {
		$html .= '<div><dt>' . esc_html__( 'Runs until', 'menj-click' ) . '</dt><dd>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $l['expires'] ) ) ) . '</dd></div>';
	}
	if ( $tier['paid'] && menj_click_dir_autorenews( $l ) ) {
		$html .= '<div><dt>' . esc_html__( 'Renewal', 'menj-click' ) . '</dt><dd>' . esc_html__( 'Automatic via PayPal', 'menj-click' ) . '</dd></div>';
	}
	$html .= '<div><dt>' . esc_html__( 'Visits', 'menj-click' ) . '</dt><dd>' . esc_html( number_format_i18n( (int) $l['hits'] ) ) . '</dd></div>';
	if ( '' !== $l['recpr_url'] ) {
		$labels = array( 'ok' => __( 'Found', 'menj-click' ), 'nofollow' => __( 'Found, but nofollow', 'menj-click' ), 'missing' => __( 'Not found', 'menj-click' ), 'error' => __( 'Page unreachable', 'menj-click' ) );
		$html  .= '<div><dt>' . esc_html__( 'Link back', 'menj-click' ) . '</dt><dd>' . esc_html( isset( $labels[ $l['recpr_status'] ] ) ? $labels[ $l['recpr_status'] ] : __( 'Not checked yet', 'menj-click' ) ) . '</dd></div>';
	}
	$html .= '</dl>';
	if ( 'publish' === $l['post']->post_status ) {
		$html .= '<p><a class="menj-dir-link" href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html__( 'View listing', 'menj-click' ) . '</a></p>';
	}
	$html .= '</section>';

	// Payment and upgrades.
	if ( menj_click_dir_can_pay( $l ) ) {
		$pay_tier = menj_click_dir_pay_tier( $l );
		if ( $pay_tier !== $l['tier'] ) {
			/* translators: %s: listing type */
			$text   = sprintf( __( 'Finish your upgrade to %s.', 'menj-click' ), $tiers[ $pay_tier ]['label'] );
			$button = __( 'Pay now', 'menj-click' );
		} elseif ( 'due' === $l['payment'] || 'menj_expired' === $l['post']->post_status ) {
			$text   = __( 'Your listing goes live once it’s paid.', 'menj-click' );
			$button = __( 'Pay now', 'menj-click' );
		} else {
			$text   = __( 'Renew now to keep your listing running without a gap.', 'menj-click' );
			$button = __( 'Renew', 'menj-click' );
		}
		$html .= '<section class="menj-manage-block"><h2 class="menj-form-section">' . esc_html__( 'Payment', 'menj-click' ) . '</h2><p>' . esc_html( $text ) . '</p><a class="menj-dir-button" href="' . esc_url( menj_click_dir_url( 'pay', $token ) ) . '">' . esc_html( $button ) . '</a></section>';
	}
	$upgrades = array_filter(
		$tiers,
		function ( $t, $slug ) use ( $tier, $l ) {
			return $t['enabled'] && $t['paid'] && $t['rank'] > $tier['rank'] && $slug !== $l['tier'];
		},
		ARRAY_FILTER_USE_BOTH
	);
	if ( $upgrades && 'publish' === $l['post']->post_status ) {
		$html .= '<section class="menj-manage-block"><h2 class="menj-form-section">' . esc_html__( 'Upgrade', 'menj-click' ) . '</h2><form method="post" class="menj-inline">';
		$html .= '<input type="hidden" name="menj_nonce" value="' . esc_attr( $nonce ) . '"><input type="hidden" name="menj_dir_action" value="upgrade">';
		$html .= '<label class="screen-reader-text" for="menj-upgrade">' . esc_html__( 'New listing type', 'menj-click' ) . '</label><select id="menj-upgrade" name="tier">';
		foreach ( $upgrades as $slug => $t ) {
			$html .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $t['label'] . ' — ' . menj_click_dir_money( $t['price'] ) . ' ' . menj_click_dir_period_label( $t['period'] ) ) . '</option>';
		}
		$html .= '</select><button type="submit" class="menj-dir-button">' . esc_html__( 'Upgrade', 'menj-click' ) . '</button></form></section>';
	}

	// Edit.
	$values = menj_click_dir_state( 'values' );
	if ( ! $values ) {
		$terms  = wp_get_object_terms( $id, 'menj_dir_category', array( 'fields' => 'ids' ) );
		$tags   = wp_get_object_terms( $id, 'menj_dir_tag', array( 'fields' => 'names' ) );
		$values = array(
			'url'         => $l['url'],
			'title'       => $l['title'],
			'description' => trim( preg_replace( "/\n{3,}/", "\n\n", wp_strip_all_tags( str_replace( array( '<br>', '</p>' ), array( "\n", "</p>\n\n" ), $l['post']->post_content ) ) ) ),
			'category'    => $terms ? (int) $terms[0] : 0,
			'tags'        => is_array( $tags ) ? implode( ', ', $tags ) : '',
			'recpr_url'   => $l['recpr_url'],
			'address'     => $l['address'],
			'city'        => $l['city'],
			'country'     => $l['country'],
			'phone'       => $l['phone'],
		);
	}
	$html .= '<section class="menj-manage-block"><h2 class="menj-form-section">' . esc_html__( 'Edit details', 'menj-click' ) . '</h2>';
	if ( '' !== (string) $l['changes'] ) {
		$html .= menj_click_dir_notice( __( 'You have changes waiting for review. Saving again replaces them.', 'menj-click' ) );
	}
	$html .= '<form class="menj-form" method="post" novalidate data-menj-form><input type="hidden" name="menj_nonce" value="' . esc_attr( $nonce ) . '"><input type="hidden" name="menj_dir_action" value="edit">';
	$html .= menj_click_dir_listing_fields( $values, false, $tier['paid'] );
	$html .= '<div class="menj-form-actions"><button type="submit" class="menj-dir-button">' . esc_html__( 'Save changes', 'menj-click' ) . '</button></div></form></section>';

	// Remove.
	$html .= '<section class="menj-manage-block is-danger"><h2 class="menj-form-section">' . esc_html__( 'Remove listing', 'menj-click' ) . '</h2><p>' . esc_html__( 'Takes the listing down straight away. Payments aren’t refunded.', 'menj-click' ) . '</p>';
	$html .= '<form method="post" data-menj-confirm="' . esc_attr__( 'Remove this listing from the directory?', 'menj-click' ) . '"><input type="hidden" name="menj_nonce" value="' . esc_attr( $nonce ) . '"><input type="hidden" name="menj_dir_action" value="remove"><button type="submit" class="menj-dir-button is-danger">' . esc_html__( 'Remove listing', 'menj-click' ) . '</button></form></section>';
	return $html;
}
