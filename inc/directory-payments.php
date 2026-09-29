<?php
/**
 * Directory payments: PayPal (one-off checkout or an auto-renewing
 * subscription, both confirmed by IPN) or invoices you mark as paid
 * yourself. Every attempt is a row in {prefix}menj_dir_payments, with the
 * raw PayPal messages kept alongside it.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

function menj_click_dir_payments_table() {
	global $wpdb;
	return $wpdb->prefix . 'menj_dir_payments';
}

/**
 * The tier a payment is for: a requested upgrade, or the current tier.
 */
function menj_click_dir_pay_tier( array $l ) {
	$tiers   = menj_click_dir_tiers();
	$upgrade = (string) get_post_meta( $l['id'], '_menj_upgrade_to', true );
	if ( isset( $tiers[ $upgrade ] ) && $tiers[ $upgrade ]['paid'] && $tiers[ $upgrade ]['enabled'] ) {
		return $upgrade;
	}
	return $l['tier'];
}

/**
 * Whether a listing renews automatically through a PayPal subscription.
 */
function menj_click_dir_autorenews( array $l ) {
	return '' !== (string) get_post_meta( $l['id'], '_menj_subscr', true );
}

/**
 * Whether the owner can pay now: payment due, an upgrade requested, or a
 * paid listing within its reminder window or expired. Listings on an
 * active subscription renew themselves, so there's nothing to pay.
 */
function menj_click_dir_can_pay( array $l ) {
	$tier = menj_click_dir_tiers()[ menj_click_dir_pay_tier( $l ) ];
	if ( ! $tier['paid'] || 'menj_unconfirmed' === $l['post']->post_status ) {
		return false;
	}
	if ( 'due' === $l['payment'] || menj_click_dir_pay_tier( $l ) !== $l['tier'] || 'menj_expired' === $l['post']->post_status ) {
		return true;
	}
	if ( 'once' === $tier['period'] || menj_click_dir_autorenews( $l ) ) {
		return false;
	}
	return $l['expires'] && strtotime( $l['expires'] ) - time() < max( 1, (int) menj_click_dir( 'reminder_days' ) ) * DAY_IN_SECONDS;
}

/**
 * Most periods an owner can buy at once.
 */
function menj_click_dir_max_periods() {
	return max( 1, min( 10, (int) menj_click_dir( 'max_periods' ) ) );
}

/**
 * Total for a number of periods, with the multi-period discount.
 *
 * @return float
 */
function menj_click_dir_price( $tier_slug, $quantity = 1 ) {
	$tier     = menj_click_dir_tiers()[ $tier_slug ];
	$quantity = 'once' === $tier['period'] ? 1 : max( 1, (int) $quantity );
	$total    = $tier['price'] * $quantity;
	$discount = max( 0, min( 90, (int) menj_click_dir( 'multi_discount' ) ) );
	if ( $quantity > 1 && $discount ) {
		$total *= ( 100 - $discount ) / 100;
	}
	return round( $total, 2 );
}

/**
 * Whether subscriptions can be offered for a tier right now.
 */
function menj_click_dir_can_subscribe( $tier_slug ) {
	$tier = menj_click_dir_tiers()[ $tier_slug ];
	return $tier['paid'] && in_array( $tier['period'], array( 'month', 'year' ), true )
		&& menj_click_dir( 'paypal_enabled' ) && is_email( menj_click_dir( 'paypal_email' ) ) && menj_click_dir( 'paypal_subscriptions' );
}

/**
 * The open payment row for a listing and the chosen options, created if needed.
 *
 * @param array  $l        Listing.
 * @param string $method   paypal|subscription|invoice|manual.
 * @param int    $quantity Periods (1 for subscriptions).
 * @return array Payment row.
 */
function menj_click_dir_open_payment( array $l, $method, $quantity = 1 ) {
	global $wpdb;
	$table    = menj_click_dir_payments_table();
	$slug     = menj_click_dir_pay_tier( $l );
	$quantity = 'subscription' === $method ? 1 : max( 1, min( menj_click_dir_max_periods(), (int) $quantity ) );
	$amount   = 'subscription' === $method ? menj_click_dir_tiers()[ $slug ]['price'] : menj_click_dir_price( $slug, $quantity );
	$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE listing_id = %d AND status = 'due' AND tier = %s AND method = %s AND quantity = %d ORDER BY id DESC LIMIT 1", $l['id'], $slug, $method, $quantity ), ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( $row && abs( (float) $row['amount'] - $amount ) < 0.005 ) {
		return $row;
	}
	$wpdb->insert(
		$table,
		array(
			'listing_id' => $l['id'],
			'tier'       => $slug,
			'amount'     => $amount,
			'quantity'   => $quantity,
			'currency'   => strtoupper( menj_click_dir( 'currency' ) ),
			'method'     => $method,
			'status'     => 'due',
			'created_at' => current_time( 'mysql', true ),
		)
	);
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

/**
 * Record a payment as paid and extend the listing by the periods bought.
 *
 * @param int    $payment_id Payment row ID.
 * @param string $txn_id     Provider transaction ID.
 * @param string $payer      Payer email.
 * @param string $note       Note.
 * @return bool
 */
function menj_click_dir_mark_paid( $payment_id, $txn_id = '', $payer = '', $note = '' ) {
	global $wpdb;
	$table = menj_click_dir_payments_table();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payment_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( ! $row || 'paid' === $row['status'] ) {
		return false;
	}
	$l = menj_click_listing( (int) $row['listing_id'] );
	if ( ! $l ) {
		return false;
	}
	$tier     = menj_click_dir_tiers()[ $row['tier'] ];
	$quantity = max( 1, (int) $row['quantity'] );
	$start    = ( $l['expires'] && strtotime( $l['expires'] ) > time() && $row['tier'] === $l['tier'] ) ? strtotime( $l['expires'] ) : time();
	$end      = in_array( $tier['period'], array( 'month', 'year' ), true ) ? strtotime( '+' . $quantity . ' ' . $tier['period'], $start ) : 0;

	$wpdb->update(
		$table,
		array(
			'status'      => 'paid',
			'txn_id'      => substr( $txn_id, 0, 64 ),
			'payer_email' => substr( $payer, 0, 191 ),
			'paid_at'     => current_time( 'mysql', true ),
			'period_end'  => $end ? gmdate( 'Y-m-d H:i:s', $end ) : null,
			'note'        => $note,
		),
		array( 'id' => $payment_id )
	);

	menj_click_dir_set_tier( $l['id'], $row['tier'] );
	delete_post_meta( $l['id'], '_menj_upgrade_to' );
	update_post_meta( $l['id'], '_menj_payment', 'paid' );
	update_post_meta( $l['id'], '_menj_expires', $end ? gmdate( 'Y-m-d H:i:s', $end ) : '' );
	delete_post_meta( $l['id'], '_menj_reminded' );
	menj_click_dir_mail( 'paid', $l['id'] );

	$status = get_post_status( $l['id'] );
	if ( 'publish' !== $status ) {
		if ( menj_click_dir( 'paid_approval' ) && 'menj_expired' !== $status ) {
			wp_update_post( array( 'ID' => $l['id'], 'post_status' => 'pending' ) );
			menj_click_dir_mail( 'admin', $l['id'], array( 'event' => __( 'Payment received. The listing is ready for review.', 'menj-click' ) ) );
		} else {
			menj_click_dir_approve( $l['id'] );
		}
	}
	return true;
}

/**
 * Periods and auto-renew chosen on the pay page.
 *
 * @return array{0: int, 1: bool}
 */
function menj_click_dir_pay_choice( $slug ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$quantity = isset( $_GET['periods'] ) ? absint( $_GET['periods'] ) : 1;
	$auto     = isset( $_GET['renew'] ) ? 'auto' === sanitize_key( wp_unslash( $_GET['renew'] ) ) : (bool) menj_click_dir_can_subscribe( $slug );
	// phpcs:enable
	$auto     = $auto && menj_click_dir_can_subscribe( $slug );
	$quantity = $auto ? 1 : max( 1, min( menj_click_dir_max_periods(), $quantity ) );
	return array( $quantity, $auto );
}

function menj_click_dir_period_words( $period, $quantity ) {
	if ( 'month' === $period ) {
		/* translators: %d: number of months */
		return sprintf( _n( '%d month', '%d months', $quantity, 'menj-click' ), $quantity );
	}
	if ( 'year' === $period ) {
		/* translators: %d: number of years */
		return sprintf( _n( '%d year', '%d years', $quantity, 'menj-click' ), $quantity );
	}
	return __( 'For as long as the directory runs', 'menj-click' );
}

/**
 * The pay page.
 */
function menj_click_dir_view_pay() {
	$token  = get_query_var( 'menj_token' );
	$id     = menj_click_dir_listing_by_token( $token );
	$header = menj_click_dir_header( __( 'Payment', 'menj-click' ), '', menj_click_dir_crumb_home() );
	if ( ! $id ) {
		return $header . menj_click_dir_notice( __( 'This payment link isn’t valid any more.', 'menj-click' ), 'error' );
	}
	$l = menj_click_listing( $id );
	if ( 'menj_unconfirmed' === $l['post']->post_status ) {
		return $header . menj_click_dir_notice( __( 'Please confirm your email first — we’ve sent you a link.', 'menj-click' ) );
	}
	if ( ! menj_click_dir_can_pay( $l ) ) {
		$msg = menj_click_dir_autorenews( $l ) ? __( 'Your listing renews automatically through PayPal, so there’s nothing to pay now.', 'menj-click' ) : __( 'Nothing to pay right now.', 'menj-click' );
		return $header . menj_click_dir_notice( $msg ) . '<p><a class="menj-dir-link" href="' . esc_url( menj_click_dir_url( 'manage', $token ) ) . '">' . esc_html__( 'Manage your listing', 'menj-click' ) . '</a></p>';
	}
	$slug             = menj_click_dir_pay_tier( $l );
	$tier             = menj_click_dir_tiers()[ $slug ];
	$paypal           = menj_click_dir( 'paypal_enabled' ) && is_email( menj_click_dir( 'paypal_email' ) );
	list( $q, $auto ) = menj_click_dir_pay_choice( $slug );
	$recurring        = in_array( $tier['period'], array( 'month', 'year' ), true );
	$method           = $paypal ? ( $auto ? 'subscription' : 'paypal' ) : 'invoice';
	$row              = menj_click_dir_open_payment( $l, $method, $q );
	$total            = (float) $row['amount'];

	$html = $header . '<section class="menj-pay">';

	// Options: how long, and whether it renews by itself.
	$can_sub = menj_click_dir_can_subscribe( $slug );
	if ( $recurring && ( $can_sub || menj_click_dir_max_periods() > 1 ) ) {
		$html .= '<form class="menj-pay__options" method="get" data-menj-autosubmit>';
		if ( $can_sub ) {
			$html .= '<fieldset class="menj-choice"><legend>' . esc_html__( 'Renewal', 'menj-click' ) . '</legend>';
			/* translators: %s: price per period */
			$html .= '<label><input type="radio" name="renew" value="auto"' . checked( $auto, true, false ) . '> <span><strong>' . esc_html__( 'Renew automatically', 'menj-click' ) . '</strong> ' . esc_html( sprintf( __( '%1$s %2$s, cancel any time in PayPal', 'menj-click' ), menj_click_dir_money( $tier['price'] ), menj_click_dir_period_label( $tier['period'] ) ) ) . '</span></label>';
			$html .= '<label><input type="radio" name="renew" value="once"' . checked( $auto, false, false ) . '> <span><strong>' . esc_html__( 'Pay once', 'menj-click' ) . '</strong> ' . esc_html__( 'we’ll remind you before it ends', 'menj-click' ) . '</span></label>';
			$html .= '</fieldset>';
		}
		if ( menj_click_dir_max_periods() > 1 ) {
			$html .= '<div class="menj-form-field"' . ( $auto ? ' hidden' : '' ) . ' data-menj-periods><label for="menj-periods">' . esc_html__( 'Length', 'menj-click' ) . '</label><select id="menj-periods" name="periods">';
			$discount = (int) menj_click_dir( 'multi_discount' );
			for ( $i = 1; $i <= menj_click_dir_max_periods(); $i++ ) {
				$label = menj_click_dir_period_words( $tier['period'], $i ) . ' — ' . menj_click_dir_money( menj_click_dir_price( $slug, $i ) );
				if ( $i > 1 && $discount ) {
					/* translators: %d: percent */
					$label .= ' ' . sprintf( __( '(save %d%%)', 'menj-click' ), $discount );
				}
				$html .= '<option value="' . $i . '"' . selected( $q, $i, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$html .= '</select></div>';
		}
		$html .= '<noscript><button type="submit" class="menj-dir-button">' . esc_html__( 'Update', 'menj-click' ) . '</button></noscript></form>';
	}

	$html .= '<dl class="menj-pay__summary">';
	$html .= '<div><dt>' . esc_html__( 'Listing', 'menj-click' ) . '</dt><dd>' . esc_html( $l['title'] ) . ' <span class="menj-muted">' . esc_html( $l['domain'] ) . '</span></dd></div>';
	$html .= '<div><dt>' . esc_html__( 'Type', 'menj-click' ) . '</dt><dd>' . esc_html( $tier['label'] ) . '</dd></div>';
	$html .= '<div><dt>' . esc_html__( 'Period', 'menj-click' ) . '</dt><dd>' . esc_html( $auto ? sprintf( /* translators: %s: period */ __( 'Every %s until cancelled', 'menj-click' ), 'month' === $tier['period'] ? __( 'month', 'menj-click' ) : __( 'year', 'menj-click' ) ) : menj_click_dir_period_words( $tier['period'], $q ) ) . '</dd></div>';
	$html .= '<div class="is-total"><dt>' . esc_html( $auto ? __( 'Today, then each period', 'menj-click' ) : __( 'Total', 'menj-click' ) ) . '</dt><dd>' . esc_html( menj_click_dir_money( $total ) ) . '</dd></div>';
	$html .= '</dl>';

	if ( $paypal ) {
		$endpoint = menj_click_dir( 'paypal_sandbox' ) ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';
		$fields   = array(
			'business'      => menj_click_dir( 'paypal_email' ),
			/* translators: 1: tier, 2: site name, 3: listing domain */
			'item_name'     => sprintf( __( '%1$s listing on %2$s: %3$s', 'menj-click' ), $tier['label'], wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $l['domain'] ),
			'item_number'   => 'L' . $l['id'],
			'currency_code' => strtoupper( menj_click_dir( 'currency' ) ),
			'custom'        => (string) $row['id'],
			'no_shipping'   => '1',
			'no_note'       => '1',
			'charset'       => 'utf-8',
			'return'        => add_query_arg( 'paid', 1, menj_click_dir_url( 'manage', $token ) ),
			'cancel_return' => menj_click_dir_url( 'pay', $token ),
			'notify_url'    => rest_url( 'menj-click/v1/paypal-ipn' ),
		);
		if ( $auto ) {
			$fields = array_merge(
				array( 'cmd' => '_xclick-subscriptions' ),
				$fields,
				array(
					'a3'  => number_format( $total, 2, '.', '' ),
					'p3'  => '1',
					't3'  => 'month' === $tier['period'] ? 'M' : 'Y',
					'src' => '1',
					'sra' => '1',
				)
			);
		} else {
			$fields = array_merge(
				array( 'cmd' => '_xclick' ),
				$fields,
				array(
					'amount'  => number_format( $total, 2, '.', '' ),
					'invoice' => 'menj-' . $row['id'],
				)
			);
		}
		$html .= '<form class="menj-pay__form" method="post" action="' . esc_url( $endpoint ) . '">';
		foreach ( $fields as $k => $v ) {
			$html .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		$html .= '<button type="submit" class="menj-dir-button">' . esc_html( $auto ? __( 'Subscribe with PayPal', 'menj-click' ) : __( 'Pay with PayPal', 'menj-click' ) ) . '</button>';
		$html .= '<p class="menj-form-help">' . esc_html( $auto ? __( 'You’ll set up the subscription on PayPal, then come back here. Cancel it any time from your PayPal account; the listing runs to the end of the period you’ve paid for.', 'menj-click' ) : __( 'You’ll finish on PayPal (card or PayPal balance), then come back here.', 'menj-click' ) ) . '</p>';
		if ( $auto && menj_click_dir_autorenews( $l ) ) {
			$html .= menj_click_dir_notice( __( 'This listing already has a PayPal subscription. After upgrading, cancel the old one in your PayPal account so you aren’t charged twice.', 'menj-click' ) );
		}
		$html .= '</form>';
	} else {
		if ( empty( $row['note'] ) ) {
			global $wpdb;
			$wpdb->update( menj_click_dir_payments_table(), array( 'note' => 'invoice requested' ), array( 'id' => $row['id'] ) );
			/* translators: 1: amount, 2: payment reference */
			menj_click_dir_mail( 'admin', $l['id'], array( 'event' => sprintf( __( 'Please send an invoice for %1$s (reference %2$s), then record the payment on the listing.', 'menj-click' ), menj_click_dir_money( $total ), 'menj-' . $row['id'] ) ) );
		}
		$html .= menj_click_dir_notice( menj_click_dir( 'invoice_text' ) );
		/* translators: %s: reference */
		$html .= '<p class="menj-form-help">' . esc_html( sprintf( __( 'Payment reference: %s', 'menj-click' ), 'menj-' . $row['id'] ) ) . '</p>';
	}
	return $html . '</section>';
}

/* -------------------------------------------------------------------------- */
/* PayPal IPN                                                                  */
/* -------------------------------------------------------------------------- */

function menj_click_dir_register_ipn() {
	register_rest_route(
		'menj-click/v1',
		'/paypal-ipn',
		array(
			'methods'             => 'POST',
			'callback'            => 'menj_click_dir_ipn',
			'permission_callback' => '__return_true', // Verified with PayPal below.
		)
	);
}
add_action( 'rest_api_init', 'menj_click_dir_register_ipn' );

/**
 * Handle a PayPal Instant Payment Notification.
 *
 * The message is posted back to PayPal to confirm it's genuine, then applied.
 */
function menj_click_dir_ipn( WP_REST_Request $request ) {
	$raw = $request->get_body();
	if ( '' === $raw || ! menj_click_dir( 'paypal_enabled' ) ) {
		return new WP_REST_Response( null, 200 );
	}
	$verify_url = menj_click_dir( 'paypal_sandbox' ) ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' : 'https://ipnpb.paypal.com/cgi-bin/webscr';
	$check      = wp_remote_post(
		$verify_url,
		array(
			'body'        => 'cmd=_notify-validate&' . $raw,
			'timeout'     => 20,
			'httpversion' => '1.1',
			'headers'     => array( 'Content-Type' => 'application/x-www-form-urlencoded', 'User-Agent' => 'menj.click-IPN' ),
		)
	);
	$ipn = array();
	wp_parse_str( $raw, $ipn );
	if ( is_wp_error( $check ) ) {
		menj_click_dir_log_ipn( $ipn, $raw, 'PayPal unreachable for verification (PayPal will retry)' );
		return new WP_REST_Response( null, 503 ); // Non-200 makes PayPal resend.
	}
	if ( 'VERIFIED' !== trim( wp_remote_retrieve_body( $check ) ) ) {
		menj_click_dir_log_ipn( $ipn, $raw, 'Not verified by PayPal: ignored' );
		return new WP_REST_Response( null, 200 );
	}
	return menj_click_dir_process_ipn( $ipn, $raw );
}

/**
 * Append a PayPal message to the payment row it belongs to.
 *
 * @param array  $ipn  Decoded message.
 * @param string $raw  Raw message body.
 * @param string $note What happened.
 * @param int    $row_id Row to log to (defaults to the row named in "custom").
 */
function menj_click_dir_log_ipn( array $ipn, $raw, $note, $row_id = 0 ) {
	global $wpdb;
	$row_id = $row_id ? (int) $row_id : ( isset( $ipn['custom'] ) ? absint( $ipn['custom'] ) : 0 );
	if ( ! $row_id ) {
		return;
	}
	$table = menj_click_dir_payments_table();
	$entry = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] ' . ( isset( $ipn['txn_type'] ) ? sanitize_text_field( $ipn['txn_type'] ) : 'ipn' ) . ' — ' . $note . "\n" . str_replace( '&', "\n", (string) $raw ) . "\n\n";
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET raw_log = CONCAT(COALESCE(raw_log, ''), %s) WHERE id = %d", $entry, $row_id ) ); // phpcs:ignore WordPress.DB
}

/**
 * Apply a verified IPN message.
 *
 * One-off payments (web_accept) and subscription payments (subscr_payment)
 * extend the listing after checking receiver, amount, currency and that the
 * transaction is new. Later subscription payments get their own row.
 * Sign-ups record the subscription; cancellations and end-of-term switch
 * auto-renew off, and the listing runs to the end of its paid period.
 *
 * @param array  $ipn Decoded IPN fields.
 * @param string $raw Raw message, for the log.
 */
function menj_click_dir_process_ipn( array $ipn, $raw = '' ) {
	global $wpdb;
	$table      = menj_click_dir_payments_table();
	$raw        = '' !== $raw ? $raw : http_build_query( $ipn );
	$payment_id = isset( $ipn['custom'] ) ? absint( $ipn['custom'] ) : 0;
	$row        = $payment_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payment_id ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB
	$type       = isset( $ipn['txn_type'] ) ? sanitize_key( $ipn['txn_type'] ) : '';
	$subscr_id  = isset( $ipn['subscr_id'] ) ? substr( sanitize_text_field( $ipn['subscr_id'] ), 0, 64 ) : '';

	if ( ! $row ) {
		return new WP_REST_Response( array( 'ignored' => 'unknown payment' ), 200 );
	}

	// Subscription lifecycle messages: no money moves.
	if ( in_array( $type, array( 'subscr_signup', 'subscr_cancel', 'subscr_eot', 'subscr_failed', 'subscr_modify' ), true ) ) {
		if ( 'subscr_signup' === $type && '' !== $subscr_id ) {
			$wpdb->update( $table, array( 'subscr_id' => $subscr_id ), array( 'id' => $row['id'] ) );
			update_post_meta( (int) $row['listing_id'], '_menj_subscr', $subscr_id );
		} elseif ( in_array( $type, array( 'subscr_cancel', 'subscr_eot' ), true ) && $subscr_id === (string) get_post_meta( (int) $row['listing_id'], '_menj_subscr', true ) ) {
			delete_post_meta( (int) $row['listing_id'], '_menj_subscr' );
		}
		$notes = array(
			'subscr_signup' => 'Subscription started',
			'subscr_cancel' => 'Subscription cancelled; listing runs to the end of its paid period',
			'subscr_eot'    => 'Subscription ended',
			'subscr_failed' => 'Subscription payment failed; PayPal will retry',
			'subscr_modify' => 'Subscription changed in PayPal',
		);
		menj_click_dir_log_ipn( $ipn, $raw, $notes[ $type ], (int) $row['id'] );
		return new WP_REST_Response( array( 'ok' => $type ), 200 );
	}

	$txn  = isset( $ipn['txn_id'] ) ? sanitize_text_field( $ipn['txn_id'] ) : '';
	$note = '';
	if ( ! isset( $ipn['payment_status'] ) || 'Completed' !== $ipn['payment_status'] ) {
		$note = 'status ' . sanitize_text_field( isset( $ipn['payment_status'] ) ? $ipn['payment_status'] : '' );
	} elseif ( strtolower( isset( $ipn['receiver_email'] ) ? $ipn['receiver_email'] : '' ) !== strtolower( menj_click_dir( 'paypal_email' ) ) && strtolower( isset( $ipn['business'] ) ? $ipn['business'] : '' ) !== strtolower( menj_click_dir( 'paypal_email' ) ) ) {
		$note = 'wrong receiver';
	} elseif ( abs( (float) ( isset( $ipn['mc_gross'] ) ? $ipn['mc_gross'] : 0 ) - (float) $row['amount'] ) > 0.009 || strtoupper( isset( $ipn['mc_currency'] ) ? $ipn['mc_currency'] : '' ) !== $row['currency'] ) {
		$note = 'amount mismatch';
	} elseif ( '' === $txn || $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE txn_id = %s AND status = 'paid'", $txn ) ) ) { // phpcs:ignore WordPress.DB
		$note = 'duplicate transaction';
	}
	if ( '' !== $note ) {
		if ( 'paid' !== $row['status'] ) {
			$wpdb->update( $table, array( 'note' => 'IPN ignored: ' . $note ), array( 'id' => $row['id'] ) );
		}
		menj_click_dir_log_ipn( $ipn, $raw, 'Ignored: ' . $note, (int) $row['id'] );
		return new WP_REST_Response( array( 'ignored' => $note ), 200 );
	}

	// A renewal on an existing subscription: a new row for this period.
	$target = (int) $row['id'];
	if ( 'paid' === $row['status'] ) {
		if ( 'subscr_payment' !== $type ) {
			menj_click_dir_log_ipn( $ipn, $raw, 'Ignored: payment already recorded', $target );
			return new WP_REST_Response( array( 'ignored' => 'already paid' ), 200 );
		}
		$wpdb->insert(
			$table,
			array(
				'listing_id' => $row['listing_id'],
				'tier'       => $row['tier'],
				'amount'     => $row['amount'],
				'quantity'   => 1,
				'currency'   => $row['currency'],
				'method'     => 'subscription',
				'status'     => 'due',
				'subscr_id'  => $subscr_id,
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$target = (int) $wpdb->insert_id;
	}
	if ( '' !== $subscr_id ) {
		$wpdb->update( $table, array( 'subscr_id' => $subscr_id ), array( 'id' => $target ) );
		update_post_meta( (int) $row['listing_id'], '_menj_subscr', $subscr_id );
	}
	menj_click_dir_mark_paid( $target, $txn, sanitize_email( isset( $ipn['payer_email'] ) ? $ipn['payer_email'] : '' ), 'subscr_payment' === $type ? 'PayPal subscription' : 'PayPal' );
	menj_click_dir_log_ipn( $ipn, $raw, 'Payment recorded', $target );
	return new WP_REST_Response( array( 'ok' => true, 'payment' => $target ), 200 );
}

/**
 * Payments for one listing, newest first (admin).
 */
function menj_click_dir_listing_payments( $id ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . menj_click_dir_payments_table() . ' WHERE listing_id = %d ORDER BY id DESC LIMIT 20', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
}
