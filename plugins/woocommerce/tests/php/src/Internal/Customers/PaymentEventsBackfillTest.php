<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\PaymentEventsBackfill;
use Automattic\WooCommerce\Internal\Customers\PaymentEventsRepository;
use WC_Order;

/**
 * Tests for the one-time payment events backfill.
 */
class PaymentEventsBackfillTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * @testdox run_batch() synthesizes charge events for existing paid orders, scoped to known customers.
	 */
	public function test_run_batch_backfills_existing_orders(): void {
		$email       = 'backfill@example.test';
		$customer_id = $this->insert_lookup_row( array( 'email' => $email ) );

		// Create three paid orders for the same customer email.
		for ( $i = 0; $i < 3; $i++ ) {
			$order = new WC_Order();
			$order->set_billing_email( $email );
			$order->set_status( 'wc-completed' );
			$order->set_total( 5 + $i );
			$order->set_currency( 'USD' );
			$order->save();
		}

		$backfill = wc_get_container()->get( PaymentEventsBackfill::class );
		$backfill->run_batch( 0 );

		$rows = wc_get_container()->get( PaymentEventsRepository::class )->list_for_customer( $customer_id );
		$this->assertCount( 3, $rows );
		foreach ( $rows as $row ) {
			$this->assertSame( 'charge', $row['type'] );
		}
	}

	/**
	 * @testdox A second run_batch() does not duplicate rows (idempotent on gateway/external_id).
	 */
	public function test_run_batch_is_idempotent(): void {
		$email       = 'backfill-dedupe@example.test';
		$customer_id = $this->insert_lookup_row( array( 'email' => $email ) );

		$order = new WC_Order();
		$order->set_billing_email( $email );
		$order->set_status( 'wc-completed' );
		$order->set_total( 10 );
		$order->save();

		$backfill = wc_get_container()->get( PaymentEventsBackfill::class );
		$backfill->run_batch( 0 );
		$backfill->run_batch( 0 );

		$this->assertCount(
			1,
			wc_get_container()->get( PaymentEventsRepository::class )->list_for_customer( $customer_id )
		);
	}
}
