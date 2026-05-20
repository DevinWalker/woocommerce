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
}
