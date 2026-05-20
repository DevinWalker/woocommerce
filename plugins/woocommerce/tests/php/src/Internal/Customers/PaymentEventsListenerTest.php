<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\PaymentEventsListener;
use Automattic\WooCommerce\Internal\Customers\PaymentEventsRepository;
use WC_Order;

/**
 * Tests for `\Automattic\WooCommerce\Internal\Customers\PaymentEventsListener`.
 *
 * Drives the public hook handlers directly (the constructor-time hook
 * registration is verified implicitly by the existing routine that wires the
 * listener via `class-woocommerce.php`).
 */
class PaymentEventsListenerTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Listener.
	 *
	 * @var PaymentEventsListener
	 */
	private PaymentEventsListener $listener;

	/**
	 * Repository.
	 *
	 * @var PaymentEventsRepository
	 */
	private PaymentEventsRepository $repo;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->listener = wc_get_container()->get( PaymentEventsListener::class );
		$this->repo     = wc_get_container()->get( PaymentEventsRepository::class );
	}

	/**
	 * @testdox on_payment_complete records a charge event for a known guest customer.
	 */
	public function test_on_payment_complete_records_charge(): void {
		$email       = 'pe-listener@example.test';
		$customer_id = $this->insert_lookup_row( array( 'email' => $email ) );

		$order = new WC_Order();
		$order->set_billing_email( $email );
		$order->set_currency( 'USD' );
		$order->set_total( 25.50 );
		$order->set_status( 'wc-completed' );
		$order->save();

		$this->listener->on_payment_complete( $order->get_id() );

		$events = $this->repo->list_for_customer( $customer_id );
		$this->assertCount( 1, $events );
		$this->assertSame( 'charge', $events[0]['type'] );
		$this->assertSame( '25.50000000', $events[0]['amount'] );
	}

	/**
	 * @testdox A second on_payment_complete for the same order is deduplicated by the unique index.
	 */
	public function test_on_payment_complete_is_idempotent(): void {
		$email       = 'pe-dedupe@example.test';
		$customer_id = $this->insert_lookup_row( array( 'email' => $email ) );

		$order = new WC_Order();
		$order->set_billing_email( $email );
		$order->set_currency( 'USD' );
		$order->set_total( 10 );
		$order->save();

		$this->listener->on_payment_complete( $order->get_id() );
		$this->listener->on_payment_complete( $order->get_id() );

		$this->assertCount( 1, $this->repo->list_for_customer( $customer_id ) );
	}

	/**
	 * @testdox An order with no matching customer record is ignored silently.
	 */
	public function test_unknown_customer_is_ignored(): void {
		$order = new WC_Order();
		$order->set_billing_email( 'no-record@example.test' );
		$order->set_total( 5 );
		$order->save();

		$this->listener->on_payment_complete( $order->get_id() );
		// No customer row exists, so nothing to assert against; just confirm no exception.
		$this->assertTrue( true );
	}
}
