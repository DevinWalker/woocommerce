<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

/**
 * Shared helper for tests that need to seed `wc_customer_lookup` rows.
 *
 * `wc_customer_lookup.user_id` is UNIQUE, so the helper leaves it as NULL to
 * avoid collisions across calls in the same test. Override any field by
 * passing it in `$overrides`.
 */
trait CustomerLookupSeederTrait {

	/**
	 * Insert a wc_customer_lookup row. Returns the new customer_id.
	 *
	 * @param array $overrides Field overrides to merge over the defaults.
	 *
	 * @return int
	 */
	protected function insert_lookup_row( array $overrides = array() ): int {
		global $wpdb;
		$defaults = array(
			'user_id'          => null,
			'email'            => uniqid( 'e' ) . '@x.test',
			'username'         => '',
			'first_name'       => '',
			'last_name'        => '',
			'date_last_active' => current_time( 'mysql', 1 ),
			'date_registered'  => current_time( 'mysql', 1 ),
			'country'          => '',
			'postcode'         => '',
			'city'             => '',
			'state'            => '',
		);
		$wpdb->insert( $wpdb->prefix . 'wc_customer_lookup', array_merge( $defaults, $overrides ) );
		return (int) $wpdb->insert_id;
	}
}
