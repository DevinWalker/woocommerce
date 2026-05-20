<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\LifecycleScheduler;

/**
 * Tests for the nightly lifecycle recompute scheduler.
 *
 * Drives `run_batch()` directly — the WP `init` and Action Scheduler hookups
 * are mechanical and verified by the container bootstrap path.
 */
class LifecycleSchedulerTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Scheduler.
	 *
	 * @var LifecycleScheduler
	 */
	private LifecycleScheduler $svc;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->svc = wc_get_container()->get( LifecycleScheduler::class );
	}

	/**
	 * @testdox run_batch() recomputes status for non-overridden customers (0 orders → prospect).
	 */
	public function test_run_batch_recomputes_non_overridden(): void {
		global $wpdb;
		$id = $this->insert_lookup_row(
			array(
				'lifecycle_status'     => 'new',
				'lifecycle_overridden' => 0,
			)
		);

		$this->svc->run_batch( 0 );

		$status = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT lifecycle_status FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$id
			)
		);
		$this->assertSame( 'prospect', $status );
	}

	/**
	 * @testdox run_batch() leaves overridden customers untouched.
	 */
	public function test_run_batch_skips_overridden(): void {
		global $wpdb;
		$id = $this->insert_lookup_row(
			array(
				'lifecycle_status'     => 'active',
				'lifecycle_overridden' => 1,
			)
		);

		$this->svc->run_batch( 0 );

		$status = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT lifecycle_status FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$id
			)
		);
		$this->assertSame( 'active', $status );
	}

	/**
	 * @testdox run_batch() leaves merged customers untouched (they are excluded by the query).
	 */
	public function test_run_batch_skips_merged(): void {
		global $wpdb;
		$target = $this->insert_lookup_row();
		$source = $this->insert_lookup_row(
			array(
				'lifecycle_status'        => 'merged',
				'merged_into_customer_id' => $target,
			)
		);

		$this->svc->run_batch( 0 );

		$status = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT lifecycle_status FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$source
			)
		);
		$this->assertSame( 'merged', $status );
	}
}
