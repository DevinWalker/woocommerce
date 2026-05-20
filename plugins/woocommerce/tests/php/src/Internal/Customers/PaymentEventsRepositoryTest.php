<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\PaymentEventsRepository;

/**
 * Tests for `\Automattic\WooCommerce\Internal\Customers\PaymentEventsRepository`.
 */
class PaymentEventsRepositoryTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Service instance.
	 *
	 * @var PaymentEventsRepository
	 */
	private PaymentEventsRepository $repo;

	/**
	 * Customer id seeded for each test.
	 *
	 * @var int
	 */
	private int $customer_id;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repo        = wc_get_container()->get( PaymentEventsRepository::class );
		$this->customer_id = $this->insert_lookup_row();
	}

	/**
	 * @testdox record() inserts a payment event and returns the new event id.
	 */
	public function test_record_inserts_event(): void {
		$id = $this->repo->record(
			$this->customer_id,
			array(
				'order_id'    => 1,
				'type'        => 'charge',
				'amount'      => '10.00',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'succeeded',
				'external_id' => 'ch_1',
				'occurred_at' => current_time( 'mysql', 1 ),
			)
		);
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * @testdox record() returns 0 and does not insert a duplicate when (gateway, external_id) already exists.
	 */
	public function test_record_is_idempotent_on_gateway_external_id(): void {
		$args = array(
			'order_id'    => 1,
			'type'        => 'charge',
			'amount'      => '10.00',
			'currency'    => 'USD',
			'gateway'     => 'stripe',
			'status'      => 'succeeded',
			'external_id' => 'ch_dedupe',
			'occurred_at' => current_time( 'mysql', 1 ),
		);

		$this->repo->record( $this->customer_id, $args );
		$second = $this->repo->record( $this->customer_id, $args );

		$this->assertSame( 0, $second );
		$this->assertCount( 1, $this->repo->list_for_customer( $this->customer_id ) );
	}

	/**
	 * @testdox list_for_customer() returns events newest first.
	 */
	public function test_list_for_customer_orders_by_time_desc(): void {
		$earlier = gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) );
		$later   = gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );

		$this->repo->record(
			$this->customer_id,
			array(
				'order_id'    => 1,
				'type'        => 'charge',
				'amount'      => '1.00',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'succeeded',
				'external_id' => 'ch_old',
				'occurred_at' => $earlier,
			)
		);
		$this->repo->record(
			$this->customer_id,
			array(
				'order_id'    => 2,
				'type'        => 'charge',
				'amount'      => '2.00',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'succeeded',
				'external_id' => 'ch_new',
				'occurred_at' => $later,
			)
		);

		$rows = $this->repo->list_for_customer( $this->customer_id );
		$this->assertCount( 2, $rows );
		$this->assertSame( 'ch_new', $rows[0]['external_id'] );
		$this->assertSame( 'ch_old', $rows[1]['external_id'] );
	}

	/**
	 * @testdox list_for_customer() can filter by type.
	 */
	public function test_list_for_customer_filters_by_type(): void {
		$this->repo->record(
			$this->customer_id,
			array(
				'order_id'    => 1,
				'type'        => 'charge',
				'amount'      => '5.00',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'succeeded',
				'external_id' => 'ch_t1',
				'occurred_at' => current_time( 'mysql', 1 ),
			)
		);
		$this->repo->record(
			$this->customer_id,
			array(
				'order_id'    => 1,
				'type'        => 'refund',
				'amount'      => '5.00',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'refunded',
				'external_id' => 'rf_t1',
				'occurred_at' => current_time( 'mysql', 1 ),
			)
		);

		$refunds = $this->repo->list_for_customer( $this->customer_id, 'refund' );
		$this->assertCount( 1, $refunds );
		$this->assertSame( 'refund', $refunds[0]['type'] );
	}
}
