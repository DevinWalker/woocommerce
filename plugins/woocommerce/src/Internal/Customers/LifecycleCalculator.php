<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

/**
 * Computes the lifecycle status for a customer from purchase recency/frequency.
 *
 * Algorithm:
 *
 * - `orders_count == 0` → `prospect` (admin-promoted user with no purchases yet).
 * - First order ≤ 30 days ago → `new`.
 * - Last order ≤ 90 days ago → `active`.
 * - Last order 90–180 days ago → `at-risk`.
 * - Otherwise → `dormant`.
 *
 * Thresholds (`new`, `active`, `at_risk` in days) are filterable via
 * `apply_filters( 'woocommerce_customer_lifecycle_thresholds', ... )`.
 *
 * Resolved by the WooCommerce reflection-based container; no explicit registration needed.
 */
final class LifecycleCalculator {

	/**
	 * Compute the lifecycle status for a customer from the supplied fields.
	 *
	 * Expected `$customer` keys: orders_count, first_order_at, last_order_at.
	 * Missing date fields are treated as absent (older than any threshold).
	 *
	 * @param array $customer Customer fields used by the algorithm.
	 *
	 * @return string One of: prospect | new | active | at-risk | dormant.
	 */
	public function compute( array $customer ): string {
		$orders_count = (int) ( $customer['orders_count'] ?? 0 );
		if ( 0 === $orders_count ) {
			return 'prospect';
		}

		$thresholds = (array) apply_filters(
			'woocommerce_customer_lifecycle_thresholds',
			array(
				'new'     => 30,
				'active'  => 90,
				'at_risk' => 180,
			)
		);

		$now            = time();
		$first_order_ts = isset( $customer['first_order_at'] ) ? strtotime( $customer['first_order_at'] . ' UTC' ) : 0;
		$last_order_ts  = isset( $customer['last_order_at'] ) ? strtotime( $customer['last_order_at'] . ' UTC' ) : 0;

		if ( $first_order_ts && ( $now - $first_order_ts ) <= $thresholds['new'] * DAY_IN_SECONDS ) {
			return 'new';
		}
		if ( $last_order_ts && ( $now - $last_order_ts ) <= $thresholds['active'] * DAY_IN_SECONDS ) {
			return 'active';
		}
		if ( $last_order_ts && ( $now - $last_order_ts ) <= $thresholds['at_risk'] * DAY_IN_SECONDS ) {
			return 'at-risk';
		}
		return 'dormant';
	}

	/**
	 * Recompute and persist `lifecycle_status` for the given customer.
	 *
	 * No-op when the row has `lifecycle_overridden = 1` (admin set the status
	 * manually) or when the customer does not exist.
	 *
	 * @param int $customer_id Customer id.
	 *
	 * @return void
	 */
	public function recompute_and_persist( int $customer_id ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					c.customer_id,
					c.lifecycle_overridden,
					COALESCE( o.orders_count, 0 ) AS orders_count,
					o.first_order_at,
					o.last_order_at
				 FROM {$wpdb->prefix}wc_customer_lookup c
				 LEFT JOIN (
					SELECT
						customer_id,
						COUNT(*) AS orders_count,
						MIN(date_created) AS first_order_at,
						MAX(date_created) AS last_order_at
					FROM {$wpdb->prefix}wc_order_stats
					WHERE customer_id = %d
					GROUP BY customer_id
				 ) o ON o.customer_id = c.customer_id
				 WHERE c.customer_id = %d",
				$customer_id,
				$customer_id
			),
			ARRAY_A
		);

		if ( ! $row || 1 === (int) $row['lifecycle_overridden'] ) {
			return;
		}

		$new_status = $this->compute(
			array(
				'orders_count'   => (int) $row['orders_count'],
				'first_order_at' => $row['first_order_at'],
				'last_order_at'  => $row['last_order_at'],
			)
		);

		$wpdb->update(
			$wpdb->prefix . 'wc_customer_lookup',
			array( 'lifecycle_status' => $new_status ),
			array( 'customer_id' => $customer_id )
		);
	}
}
