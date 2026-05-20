<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\CustomerRepository;
use Automattic\WooCommerce\Internal\Customers\MergeService;
use Automattic\WooCommerce\Internal\Customers\NotesRepository;
use Automattic\WooCommerce\Internal\Customers\PaymentEventsRepository;
use Automattic\WooCommerce\Internal\Customers\TagsRepository;

/**
 * Tests for `\Automattic\WooCommerce\Internal\Customers\MergeService`.
 */
class MergeServiceTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Service instance.
	 *
	 * @var MergeService
	 */
	private MergeService $svc;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->svc = wc_get_container()->get( MergeService::class );
	}

	/**
	 * @testdox merge() moves notes, payment events, and tag relationships from source to target.
	 */
	public function test_merge_moves_owned_rows(): void {
		$source = $this->insert_lookup_row( array( 'email' => 'src@m.test' ) );
		$target = $this->insert_lookup_row( array( 'email' => 'tgt@m.test' ) );

		$notes  = wc_get_container()->get( NotesRepository::class );
		$tags   = wc_get_container()->get( TagsRepository::class );
		$pay    = wc_get_container()->get( PaymentEventsRepository::class );
		$author = self::factory()->user->create();

		$notes->add( $source, $author, 'source-only note' );
		$tag_id = $tags->create_tag( 'Loyal', 'loyal-' . uniqid(), null, $author );
		$tags->attach( $source, $tag_id, $author );
		$pay->record(
			$source,
			array(
				'order_id'    => 11,
				'type'        => 'charge',
				'amount'      => '50',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'succeeded',
				'external_id' => 'mg_src_1',
				'occurred_at' => current_time( 'mysql', 1 ),
			)
		);

		$moved = $this->svc->merge( $source, $target );

		$this->assertSame( 1, $moved['notes'] );
		$this->assertSame( 1, $moved['payment_events'] );
		$this->assertSame( 1, $moved['tags'] );

		$this->assertCount( 1, $notes->list_for_customer( $target ) );
		$this->assertCount( 0, $notes->list_for_customer( $source ) );
		$this->assertSame(
			array( $tag_id ),
			array_map( 'intval', wp_list_pluck( $tags->list_for_customer( $target ), 'tag_id' ) )
		);
		$this->assertCount( 1, $pay->list_for_customer( $target ) );

		// Source row is annotated.
		$src_row = wc_get_container()->get( CustomerRepository::class )->find( $source );
		$this->assertSame( $target, (int) $src_row['merged_into_customer_id'] );
		$this->assertSame( 'merged', $src_row['lifecycle_status'] );
	}

	/**
	 * @testdox merge() rejects merging a customer into itself.
	 */
	public function test_merge_rejects_source_equals_target(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->svc->merge( 1, 1 );
	}

	/**
	 * @testdox merge() rejects when the source has already been merged.
	 */
	public function test_merge_rejects_double_merge(): void {
		$source = $this->insert_lookup_row();
		$target = $this->insert_lookup_row();
		$other  = $this->insert_lookup_row();

		$this->svc->merge( $source, $target );

		$this->expectException( \RuntimeException::class );
		$this->svc->merge( $source, $other );
	}

	/**
	 * @testdox merge() fires woocommerce_customer_merged with the source and target ids.
	 */
	public function test_merge_fires_action(): void {
		$fired  = array();
		$source = $this->insert_lookup_row();
		$target = $this->insert_lookup_row();

		add_action(
			'woocommerce_customer_merged',
			static function ( $s, $t ) use ( &$fired ) {
				$fired = array( (int) $s, (int) $t );
			},
			10,
			2
		);

		$this->svc->merge( $source, $target );

		$this->assertSame( array( $source, $target ), $fired );
	}

	/**
	 * @testdox merge() reassigns wc_order_stats rows from source to target so analytics totals follow.
	 */
	public function test_merge_reassigns_order_stats(): void {
		global $wpdb;
		$source = $this->insert_lookup_row();
		$target = $this->insert_lookup_row();

		$wpdb->insert(
			$wpdb->prefix . 'wc_order_stats',
			array(
				'order_id'         => 9100001,
				'parent_id'        => 0,
				'date_created'     => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
				'date_created_gmt' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
				'date_paid'        => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
				'date_completed'   => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
				'num_items_sold'   => 1,
				'total_sales'      => 10.0,
				'tax_total'        => 0,
				'shipping_total'   => 0,
				'net_total'        => 10.0,
				'status'           => 'wc-completed',
				'customer_id'      => $source,
			)
		);

		$this->svc->merge( $source, $target );

		$source_orders = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats WHERE customer_id = %d",
				$source
			)
		);
		$target_orders = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats WHERE customer_id = %d",
				$target
			)
		);

		$this->assertSame( 0, $source_orders );
		$this->assertSame( 1, $target_orders );
	}
}
