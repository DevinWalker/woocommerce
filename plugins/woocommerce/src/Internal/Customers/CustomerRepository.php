<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

/**
 * Internal repository for customer-view data — reads and writes the extended
 * `wc_customer_lookup` table that backs the dedicated customer view in wp-admin.
 *
 * Resolved by the WooCommerce reflection-based container; no explicit registration needed.
 */
final class CustomerRepository {

	/**
	 * Look up a customer by id.
	 *
	 * When `$follow_merge` is true, a customer that has been merged into another
	 * resolves to the merge target (transitively); when false, the original row
	 * is returned and the caller can inspect `merged_into_customer_id`.
	 *
	 * `customer_id` and `merged_into_customer_id` in the returned array are
	 * coerced to int; the other fields are passed through from the database
	 * as strings.
	 *
	 * @param int  $customer_id  Customer id from `wc_customer_lookup`.
	 * @param bool $follow_merge When true, follow merge redirects to the target.
	 *
	 * @return array|null Row data, or null if the customer is unknown.
	 */
	public function find( int $customer_id, bool $follow_merge = false ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$customer_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['customer_id'] = (int) $row['customer_id'];

		if ( $follow_merge && ! empty( $row['merged_into_customer_id'] ) ) {
			return $this->find( (int) $row['merged_into_customer_id'], true );
		}

		return $row;
	}
}
