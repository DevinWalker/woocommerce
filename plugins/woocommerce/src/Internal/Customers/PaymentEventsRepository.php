<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

/**
 * Internal repository for per-customer payment events (charges, refunds,
 * captures, voids, chargebacks). Backed by `wc_customer_payment_events`.
 *
 * The unique index on `(gateway, external_id)` makes `record()` idempotent
 * under gateway webhook replays.
 *
 * Resolved by the WooCommerce reflection-based container; no explicit registration needed.
 */
final class PaymentEventsRepository {

	/**
	 * Record a payment event. Returns the new event id, or 0 if the row was
	 * deduped on (gateway, external_id) via the unique index.
	 *
	 * Required `$args` keys: order_id, type, amount, currency, gateway, status.
	 * Optional keys: external_id, occurred_at (defaults to now).
	 *
	 * @param int   $customer_id Customer id.
	 * @param array $args        Event fields.
	 *
	 * @return int New event id, or 0 if deduped.
	 */
	public function record( int $customer_id, array $args ): int {
		global $wpdb;

		$row = array(
			'customer_id' => $customer_id,
			'order_id'    => (int) $args['order_id'],
			'type'        => (string) $args['type'],
			'amount'      => (string) $args['amount'],
			'currency'    => strtoupper( (string) $args['currency'] ),
			'gateway'     => (string) $args['gateway'],
			'status'      => (string) $args['status'],
			'external_id' => isset( $args['external_id'] ) ? (string) $args['external_id'] : null,
			'created_at'  => $args['occurred_at'] ?? current_time( 'mysql', 1 ),
		);

		// INSERT IGNORE so a duplicate (gateway, external_id) becomes a no-op.
		$affected = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}wc_customer_payment_events
				 (customer_id, order_id, type, amount, currency, gateway, status, external_id, created_at)
				 VALUES (%d, %d, %s, %s, %s, %s, %s, %s, %s)",
				$row['customer_id'],
				$row['order_id'],
				$row['type'],
				$row['amount'],
				$row['currency'],
				$row['gateway'],
				$row['status'],
				$row['external_id'],
				$row['created_at']
			)
		);

		return $affected ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * List events for a customer, newest first.
	 *
	 * @param int         $customer_id Customer id.
	 * @param string|null $type        Optional type filter (charge|refund|partial_refund|capture|void|chargeback).
	 * @param int         $page        1-based page number.
	 * @param int         $per_page    Page size.
	 *
	 * @return array
	 */
	public function list_for_customer( int $customer_id, ?string $type = null, int $page = 1, int $per_page = 25 ): array {
		global $wpdb;
		$offset = max( 0, ( $page - 1 ) * $per_page );

		if ( null !== $type ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_customer_payment_events
				 WHERE customer_id = %d AND type = %s
				 ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$customer_id,
				$type,
				$per_page,
				$offset
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_customer_payment_events
				 WHERE customer_id = %d
				 ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$customer_id,
				$per_page,
				$offset
			);
		}

		return (array) $wpdb->get_results( $sql, ARRAY_A );
	}
}
