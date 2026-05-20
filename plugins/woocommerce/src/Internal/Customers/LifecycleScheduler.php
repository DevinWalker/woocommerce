<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

/**
 * Nightly lifecycle recompute for all non-overridden, non-merged customer rows.
 *
 * Architecture: a daily recurring Action Scheduler action kicks off a batched
 * sweep. Each batch processes up to BATCH rows and chains the next batch if
 * more remain. Override (`lifecycle_overridden=1`) and merged rows are skipped
 * inside LifecycleCalculator::recompute_and_persist().
 *
 * Resolved by the WooCommerce container. `init()` registers the WC hooks
 * matching the existing convention for self-bootstrapping services.
 */
final class LifecycleScheduler {

	/**
	 * Daily kick-off hook.
	 */
	public const HOOK_DAILY = 'wc_customers_recompute_lifecycle';

	/**
	 * Chained batch hook.
	 */
	public const HOOK_BATCH = 'wc_customers_recompute_lifecycle_batch';

	/**
	 * Customers processed per batch.
	 */
	public const BATCH = 500;

	/**
	 * Lifecycle calculator.
	 *
	 * @var LifecycleCalculator
	 */
	private LifecycleCalculator $calc;

	/**
	 * Container-driven dependency injection. Registers hooks.
	 *
	 * @internal
	 *
	 * @param LifecycleCalculator $calc Lifecycle service.
	 *
	 * @return void
	 */
	final public function init( LifecycleCalculator $calc ): void {
		$this->calc = $calc;
		add_action( 'init', array( $this, 'maybe_schedule_daily' ) );
		add_action( self::HOOK_DAILY, array( $this, 'kick_off' ) );
		add_action( self::HOOK_BATCH, array( $this, 'run_batch' ) );
	}

	/**
	 * Ensure a daily recurring action exists. Idempotent.
	 *
	 * @return void
	 */
	public function maybe_schedule_daily(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		if ( ! as_has_scheduled_action( self::HOOK_DAILY ) ) {
			as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, DAY_IN_SECONDS, self::HOOK_DAILY );
		}
	}

	/**
	 * Daily kick-off: enqueue the first batch starting at offset 0.
	 *
	 * @return void
	 */
	public function kick_off(): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		as_schedule_single_action( time(), self::HOOK_BATCH, array( 0 ) );
	}

	/**
	 * Recompute up to BATCH customers starting at `$offset`. Re-enqueues for
	 * the next batch if more remain.
	 *
	 * @param int $offset Starting offset.
	 *
	 * @return void
	 */
	public function run_batch( int $offset = 0 ): void {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup
				 WHERE lifecycle_overridden = 0 AND merged_into_customer_id IS NULL
				 ORDER BY customer_id ASC LIMIT %d OFFSET %d",
				self::BATCH,
				$offset
			)
		);

		if ( empty( $ids ) ) {
			return;
		}

		foreach ( $ids as $id ) {
			$this->calc->recompute_and_persist( (int) $id );
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 1, self::HOOK_BATCH, array( $offset + self::BATCH ) );
		}
	}
}
