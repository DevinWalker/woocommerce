<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\NotesRepository;
use Automattic\WooCommerce\Internal\Customers\PaymentEventsRepository;
use Automattic\WooCommerce\Internal\Customers\TimelineAssembler;

/**
 * Tests for `\Automattic\WooCommerce\Internal\Customers\TimelineAssembler`.
 */
class TimelineAssemblerTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Service instance.
	 *
	 * @var TimelineAssembler
	 */
	private TimelineAssembler $svc;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->svc = wc_get_container()->get( TimelineAssembler::class );
	}

	/**
	 * @testdox assemble() merges notes and payment events with newest first.
	 */
	public function test_returns_notes_and_payment_events_merged(): void {
		$customer_id = $this->insert_lookup_row();
		$author      = self::factory()->user->create();

		$notes = wc_get_container()->get( NotesRepository::class );
		$pay   = wc_get_container()->get( PaymentEventsRepository::class );

		$earlier = gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) );
		$later   = gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );

		// Older note.
		global $wpdb;
		$notes->add( $customer_id, $author, 'Older note' );
		$wpdb->update(
			$wpdb->prefix . 'wc_customer_notes',
			array( 'created_at' => $earlier ),
			array( 'customer_id' => $customer_id )
		);

		// Newer payment event.
		$pay->record(
			$customer_id,
			array(
				'order_id'    => 1,
				'type'        => 'charge',
				'amount'      => '5',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'succeeded',
				'external_id' => 'tl_ch_1',
				'occurred_at' => $later,
			)
		);

		$events = $this->svc->assemble( $customer_id, array() );
		$this->assertGreaterThanOrEqual( 2, count( $events ) );

		$types = array_column( $events, 'type' );
		$this->assertContains( 'note_added', $types );
		$this->assertContains( 'payment_event', $types );

		// Newest first.
		$this->assertSame( 'payment_event', $events[0]['type'] );
		$this->assertSame( 'note_added', $events[1]['type'] );
	}

	/**
	 * @testdox The woocommerce_customer_timeline_events filter can append extension events.
	 */
	public function test_filter_can_append_extension_events(): void {
		$customer_id = $this->insert_lookup_row();

		$filter = static function ( $events ) {
			$events[] = array(
				'id'          => 'ext_1',
				'type'        => 'subscription_renewed',
				'occurred_at' => current_time( 'mysql', 1 ),
				'actor'       => 0,
				'payload'     => 'sub_demo',
			);
			return $events;
		};
		add_filter( 'woocommerce_customer_timeline_events', $filter, 10 );

		$events = $this->svc->assemble( $customer_id, array() );

		remove_filter( 'woocommerce_customer_timeline_events', $filter, 10 );

		$this->assertContains( 'subscription_renewed', array_column( $events, 'type' ) );
	}

	/**
	 * @testdox The types filter argument restricts results to the listed event types.
	 */
	public function test_types_filter_restricts_results(): void {
		$customer_id = $this->insert_lookup_row();
		$author      = self::factory()->user->create();

		wc_get_container()->get( NotesRepository::class )->add( $customer_id, $author, 'note' );
		wc_get_container()->get( PaymentEventsRepository::class )->record(
			$customer_id,
			array(
				'order_id'    => 1,
				'type'        => 'charge',
				'amount'      => '5',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'succeeded',
				'external_id' => 'tl_only_pay',
				'occurred_at' => current_time( 'mysql', 1 ),
			)
		);

		$pay_only = $this->svc->assemble( $customer_id, array( 'types' => array( 'payment_event' ) ) );
		$this->assertCount( 1, $pay_only );
		$this->assertSame( 'payment_event', $pay_only[0]['type'] );
	}
}
