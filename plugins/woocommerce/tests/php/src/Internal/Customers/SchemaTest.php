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
		$this->assert_table_exists( 'wc_customer_notes' );
	}

	/**
	 * @testdox `wc_customer_tags` table exists after WC_Install::create_tables().
	 */
	public function test_wc_customer_tags_table_exists(): void {
		$this->assert_table_exists( 'wc_customer_tags' );
	}

	/**
	 * @testdox `wc_customer_tag_relationships` table exists after WC_Install::create_tables().
	 */
	public function test_wc_customer_tag_relationships_table_exists(): void {
		$this->assert_table_exists( 'wc_customer_tag_relationships' );
	}

	/**
	 * @testdox `wc_customer_payment_events` table exists after WC_Install::create_tables().
	 */
	public function test_wc_customer_payment_events_table_exists(): void {
		$this->assert_table_exists( 'wc_customer_payment_events' );
	}

	/**
	 * @testdox `wc_customer_payment_events` has a unique index on (gateway, external_id) for idempotent webhook replays.
	 */
	public function test_wc_customer_payment_events_has_unique_external_index(): void {
		global $wpdb;
		WC_Install::create_tables();
		$table   = $wpdb->prefix . 'wc_customer_payment_events';
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name='external'", ARRAY_A );
		$this->assertNotEmpty( $indexes, 'expected `external` index on wc_customer_payment_events' );
		$this->assertSame( '0', (string) $indexes[0]['Non_unique'], '`external` index should be unique' );
	}

	/**
	 * Helper: install and assert that `{prefix}{$name}` exists.
	 *
	 * @param string $name Table name without prefix.
	 */
	private function assert_table_exists( string $name ): void {
		global $wpdb;
		WC_Install::create_tables();
		$table = $wpdb->prefix . $name;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertSame( $table, $found, "{$name} table should exist after install" );
	}
}
