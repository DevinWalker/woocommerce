<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use WC_Install;

/**
 * Tests covering the customer-view schema additions to WC_Install::get_schema().
 */
class SchemaTest extends \WC_Unit_Test_Case {

	/**
	 * @testdox `wc_customer_notes` table exists after WC_Install::create_tables().
	 */
	public function test_wc_customer_notes_table_exists(): void {
		global $wpdb;
		WC_Install::create_tables();
		$table = $wpdb->prefix . 'wc_customer_notes';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertSame( $table, $found, 'wc_customer_notes table should exist after install' );
	}
}
