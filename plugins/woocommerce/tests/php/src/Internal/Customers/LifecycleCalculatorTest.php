<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\LifecycleCalculator;

/**
 * Tests for `\Automattic\WooCommerce\Internal\Customers\LifecycleCalculator`.
 */
class LifecycleCalculatorTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Service instance.
	 *
	 * @var LifecycleCalculator
	 */
	private LifecycleCalculator $calc;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->calc = wc_get_container()->get( LifecycleCalculator::class );
	}

	/**
	 * Helper: format a UTC timestamp string offset $days into the past.
	 *
	 * @param int $days Days in the past.
	 *
	 * @return string
	 */
	private function days_ago( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	}

	/**
	 * @testdox A customer with zero orders resolves to `prospect`.
	 */
	public function test_prospect_when_no_orders(): void {
		$this->assertSame( 'prospect', $this->calc->compute( array( 'orders_count' => 0 ) ) );
	}

	/**
	 * @testdox A customer whose first order is within 30 days resolves to `new`.
	 */
	public function test_new_when_first_order_within_30_days(): void {
		$this->assertSame(
			'new',
			$this->calc->compute(
				array(
					'orders_count'   => 1,
					'first_order_at' => $this->days_ago( 5 ),
					'last_order_at'  => $this->days_ago( 5 ),
				)
			)
		);
	}

	/**
	 * @testdox A customer whose last order is within 90 days (but not within 30) resolves to `active`.
	 */
	public function test_active_when_last_order_within_90_days(): void {
		$this->assertSame(
			'active',
			$this->calc->compute(
				array(
					'orders_count'   => 5,
					'first_order_at' => $this->days_ago( 200 ),
					'last_order_at'  => $this->days_ago( 40 ),
				)
			)
		);
	}

	/**
	 * @testdox A customer whose last order is between 90 and 180 days resolves to `at-risk`.
	 */
	public function test_at_risk_between_90_and_180_days(): void {
		$this->assertSame(
			'at-risk',
			$this->calc->compute(
				array(
					'orders_count'   => 3,
					'first_order_at' => $this->days_ago( 500 ),
					'last_order_at'  => $this->days_ago( 120 ),
				)
			)
		);
	}

	/**
	 * @testdox A customer whose last order is beyond 180 days resolves to `dormant`.
	 */
	public function test_dormant_beyond_180_days(): void {
		$this->assertSame(
			'dormant',
			$this->calc->compute(
				array(
					'orders_count'   => 3,
					'first_order_at' => $this->days_ago( 800 ),
					'last_order_at'  => $this->days_ago( 200 ),
				)
			)
		);
	}

	/**
	 * @testdox The thresholds filter can shorten the windows for store-specific tuning.
	 */
	public function test_thresholds_filter_can_override_defaults(): void {
		add_filter(
			'woocommerce_customer_lifecycle_thresholds',
			static fn() => array(
				'new'     => 1,
				'active'  => 2,
				'at_risk' => 3,
			)
		);

		$this->assertSame(
			'dormant',
			$this->calc->compute(
				array(
					'orders_count'   => 3,
					'first_order_at' => $this->days_ago( 10 ),
					'last_order_at'  => $this->days_ago( 4 ),
				)
			)
		);
	}

	/**
	 * @testdox recompute_and_persist() writes the new status, but skips when lifecycle_overridden=1.
	 */
	public function test_recompute_and_persist_respects_override(): void {
		global $wpdb;

		$auto = $this->insert_lookup_row(
			array(
				'lifecycle_status'     => 'new',
				'lifecycle_overridden' => 0,
			)
		);
		$manual = $this->insert_lookup_row(
			array(
				'lifecycle_status'     => 'active',
				'lifecycle_overridden' => 1,
			)
		);

		$this->calc->recompute_and_persist( $auto );
		$this->calc->recompute_and_persist( $manual );

		$auto_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT lifecycle_status FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$auto
			)
		);
		$manual_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT lifecycle_status FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$manual
			)
		);

		// $auto has 0 orders → prospect.
		$this->assertSame( 'prospect', $auto_status );
		// $manual is overridden → unchanged at 'active'.
		$this->assertSame( 'active', $manual_status );
	}
}
