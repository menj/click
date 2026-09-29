<?php
/**
 * Directory housekeeping (WP-Cron):
 *   hourly  link checker and reciprocal checks, a small batch at a time
 *   daily   expiry, renewal reminders, clean-up of unconfirmed submissions
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

function menj_click_dir_schedule() {
	if ( menj_click_dir_enabled() && ! wp_next_scheduled( 'menj_click_dir_hourly' ) ) {
		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'menj_click_dir_hourly' );
	}
}
add_action( 'init', 'menj_click_dir_schedule', 20 );

function menj_click_dir_unschedule() {
	wp_clear_scheduled_hook( 'menj_click_dir_hourly' );
}
add_action( 'switch_theme', 'menj_click_dir_unschedule' );

/**
 * Live listings due a check of the given kind, oldest first.
 *
 * @param string $meta  _menj_http_checked or _menj_recpr_checked.
 * @param int    $days  Recheck interval.
 * @param int    $limit Batch size.
 * @param bool   $recpr Only listings with a reciprocal URL.
 * @return int[]
 */
function menj_click_dir_due( $meta, $days, $limit, $recpr = false ) {
	global $wpdb;
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS );
	$join   = $recpr ? "JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = '_menj_recpr_url' AND r.meta_value <> ''" : '';
	return array_map(
		'intval',
		$wpdb->get_col( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p {$join} LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = %s
				 WHERE p.post_type = 'menj_listing' AND p.post_status IN ('publish','pending')
				 AND ( c.meta_value IS NULL OR c.meta_value = '' OR c.meta_value < %s )
				 ORDER BY c.meta_value ASC, p.ID ASC LIMIT %d",
				$meta,
				$cutoff,
				$limit
			)
		)
	);
}

/**
 * Check one listing's site.
 */
function menj_click_dir_check_link( $id ) {
	$l = menj_click_listing( $id );
	if ( ! $l || '' === $l['url'] ) {
		return 0;
	}
	$result = menj_click_dir_fetch( $l['url'] );
	$ok     = $result['code'] >= 200 && $result['code'] < 400;
	update_post_meta( $id, '_menj_http_status', $result['code'] );
	update_post_meta( $id, '_menj_http_checked', current_time( 'mysql', true ) );
	update_post_meta( $id, '_menj_http_fails', $ok ? 0 : (int) $l['http_fails'] + 1 );
	if ( ! $ok && menj_click_dir( 'checker_hide' ) && (int) $l['http_fails'] + 1 >= (int) menj_click_dir( 'checker_fails' ) && 'publish' === $l['post']->post_status ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
	}
	return $result['code'];
}

/**
 * Check one listing's link back. A missing link starts a grace period; if
 * it's still missing when that ends, the listing is hidden.
 */
function menj_click_dir_check_backlink( $id ) {
	$l = menj_click_listing( $id );
	if ( ! $l || '' === $l['recpr_url'] ) {
		return '';
	}
	$status = menj_click_dir_check_reciprocal( $l['recpr_url'] );
	update_post_meta( $id, '_menj_recpr_status', $status );
	update_post_meta( $id, '_menj_recpr_checked', current_time( 'mysql', true ) );

	$required = 'required' === menj_click_dir( 'reciprocal' ) && 'free' === $l['tier'];
	if ( 'ok' === $status ) {
		update_post_meta( $id, '_menj_recpr_deadline', '' );
	} elseif ( $required && 'publish' === $l['post']->post_status ) {
		if ( '' === (string) $l['recpr_deadline'] ) {
			update_post_meta( $id, '_menj_recpr_deadline', gmdate( 'Y-m-d H:i:s', time() + (int) menj_click_dir( 'reciprocal_grace' ) * DAY_IN_SECONDS ) );
			menj_click_dir_mail( 'reciprocal', $id );
		} elseif ( strtotime( $l['recpr_deadline'] ) < time() ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'menj_expired' ) );
		}
	}
	return $status;
}

function menj_click_dir_hourly() {
	if ( ! menj_click_dir_enabled() ) {
		return;
	}
	$batch = max( 1, min( 50, (int) menj_click_dir( 'checker_batch' ) ) );
	if ( menj_click_dir( 'checker' ) ) {
		foreach ( menj_click_dir_due( '_menj_http_checked', menj_click_dir( 'checker_every' ), $batch ) as $id ) {
			menj_click_dir_check_link( $id );
		}
	}
	if ( 'off' !== menj_click_dir( 'reciprocal' ) ) {
		foreach ( menj_click_dir_due( '_menj_recpr_checked', menj_click_dir( 'reciprocal_recheck' ), $batch, true ) as $id ) {
			menj_click_dir_check_backlink( $id );
		}
	}
}
add_action( 'menj_click_dir_hourly', 'menj_click_dir_hourly' );

/**
 * Daily: renewal reminders, expiry, and tidying up.
 */
function menj_click_dir_daily() {
	global $wpdb;
	if ( ! menj_click_dir_enabled() ) {
		return;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'menj_listing',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'meta_query'     => array( array( 'key' => '_menj_expires', 'value' => '', 'compare' => '!=' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$remind = max( 1, (int) menj_click_dir( 'reminder_days' ) ) * DAY_IN_SECONDS;
	foreach ( $ids as $id ) {
		$l   = menj_click_listing( $id );
		$end = strtotime( $l['expires'] );
		if ( ! $end ) {
			continue;
		}
		$auto = menj_click_dir_autorenews( $l );
		if ( $end + ( $auto ? 3 * DAY_IN_SECONDS : 0 ) <= time() ) {
			// Subscriptions get three days for PayPal's renewal payment to arrive.
			menj_click_dir_expire( $l );
		} elseif ( ! $auto && $end - time() < $remind && '' === (string) $l['reminded'] ) {
			menj_click_dir_mail( 'expiring', $id );
			update_post_meta( $id, '_menj_reminded', current_time( 'mysql', true ) );
		}
	}

	// Unconfirmed submissions older than a week.
	$stale = get_posts(
		array(
			'post_type'      => 'menj_listing',
			'post_status'    => 'menj_unconfirmed',
			'fields'         => 'ids',
			'posts_per_page' => 200,
			'date_query'     => array( array( 'before' => '7 days ago' ) ),
		)
	);
	foreach ( $stale as $id ) {
		wp_delete_post( $id, true );
	}

	// Visit details older than the click-retention window.
	$days = (int) menj_click_settings( 'links' )['retention_days'];
	if ( $days > 0 ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . menj_click_dir_hits_table() . ' WHERE hit_date < %s', gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB
	}
}
add_action( 'menj_click_daily', 'menj_click_dir_daily' );

/**
 * A paid period has ended: drop to Free, or hide, per the setting.
 */
function menj_click_dir_expire( array $l ) {
	$tier = menj_click_dir_tiers()[ $l['tier'] ];
	if ( ! $tier['paid'] ) {
		return;
	}
	menj_click_dir_mail( 'expired', $l['id'] );
	update_post_meta( $l['id'], '_menj_expires', '' );
	delete_post_meta( $l['id'], '_menj_subscr' );
	delete_post_meta( $l['id'], '_menj_reminded' );
	if ( 'downgrade' === menj_click_dir( 'expire_action' ) && menj_click_dir_tiers()['free']['enabled'] ) {
		menj_click_dir_set_tier( $l['id'], 'free' );
		update_post_meta( $l['id'], '_menj_payment', 'none' );
	} else {
		update_post_meta( $l['id'], '_menj_payment', 'due' );
		wp_update_post( array( 'ID' => $l['id'], 'post_status' => 'menj_expired' ) );
	}
}
