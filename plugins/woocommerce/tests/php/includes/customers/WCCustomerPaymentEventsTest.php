<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Includes\Customers;

use Automattic\WooCommerce\Internal\Customers\PaymentEventsRepository;
use Automattic\WooCommerce\Tests\Internal\Customers\CustomerLookupSeederTrait;
use WC_Customer_Payment_Events;

/**
 * Tests for the public `WC_Customer_Payment_Events` facade.
 */
class WCCustomerPaymentEventsTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * @testdox WC_Customer_Payment_Events::record() inserts a row via the repository.
	 */
	public function test_record_inserts_row(): void {
		$customer_id = $this->insert_lookup_row();

		$id = WC_Customer_Payment_Events::record(
			$customer_id,
			array(
				'order_id'    => 1,
				'type'        => 'capture',
				'amount'      => '99.00',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'succeeded',
				'external_id' => 'facade_test_a',
				'occurred_at' => current_time( 'mysql', 1 ),
			)
		);

		$this->assertGreaterThan( 0, $id );

		$rows = wc_get_container()->get( PaymentEventsRepository::class )->list_for_customer( $customer_id );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'capture', $rows[0]['type'] );
	}
}
