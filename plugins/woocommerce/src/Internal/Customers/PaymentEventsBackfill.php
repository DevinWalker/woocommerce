<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

/**
 * One-time backfill of `wc_customer_payment_events` from existing orders and refunds.
 *
 * The PaymentEventsListener captures events going forward; this class drains the
 * historical tail. Idempotent via the unique (gateway, external_id) index on
 * `wc_customer_payment_events`, so running it twice produces no duplicates.
 *
 * Resolved by the WooCommerce container. `init()` injects dependencies AND
 * registers the Action Scheduler hook, matching the existing convention.
 */
final class PaymentEventsBackfill {

	/**
	 * Action Scheduler hook for chained batches.
	 */
	public const HOOK = 'wc_customers_payment_events_backfill';

	/**
	 * Orders processed per batch (also used as the chunk for Action Scheduler).
	 */
	public const BATCH = 1000;

	/**
	 * Listener used to translate orders/refunds into ledger writes.
	 *
	 * @var PaymentEventsListener
	 */
	private PaymentEventsListener $listener;

	/**
	 * Container-driven dependency injection. Registers the AS hook handler.
	 *
	 * @internal
	 *
	 * @param PaymentEventsListener $listener Payment events listener.
	 *
	 * @return void
	 */
	final public function init( PaymentEventsListener $listener ): void {
		$this->listener = $listener;
		add_action( self::HOOK, array( $this, 'run_batch' ) );
	}

	/**
	 * Enqueue the first batch. Idempotent — calling twice while an enqueued
	 * action is pending is a no-op.
	 *
	 * Called from the 10.9.0 db_updates entry on upgrade; can also be invoked
	 * manually from WP-CLI or admin tools.
	 *
	 * @return void
	 */
	public static function schedule_first_run(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		if ( ! as_has_scheduled_action( self::HOOK ) ) {
			as_schedule_single_action( time() + 5, self::HOOK, array( 0 ) );
		}
	}

	/**
	 * Process one batch of orders starting at `$offset`. Re-enqueues itself for
	 * the next batch if more orders remain.
	 *
	 * @param int $offset Order offset to begin this batch.
	 *
	 * @return void
	 */
	public function run_batch( int $offset = 0 ): void {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'   => self::BATCH,
				'offset'  => $offset,
				'status'  => array( 'completed', 'processing', 'refunded', 'on-hold' ),
				'return'  => 'objects',
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		if ( ! is_array( $orders ) || empty( $orders ) ) {
			return;
		}

		foreach ( $orders as $order ) {
			if ( $order->is_paid() ) {
				$this->listener->on_payment_complete( $order->get_id() );
			}
			foreach ( $order->get_refunds() as $refund ) {
				$this->listener->on_refunded( $order->get_id(), $refund->get_id() );
			}
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 1, self::HOOK, array( $offset + self::BATCH ) );
		}
	}
}
