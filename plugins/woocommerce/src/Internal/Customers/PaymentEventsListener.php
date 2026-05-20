<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

use WC_Order;

/**
 * Listen for WooCommerce payment events and record them in the per-customer
 * payment events ledger. The unique (gateway, external_id) index on
 * `wc_customer_payment_events` makes duplicate inserts (e.g. webhook replays)
 * a no-op, so the listener can run on the standard WC hooks safely.
 *
 * Resolved by the WooCommerce reflection-based container. `init()` injects
 * dependencies AND registers WC hooks, following the existing convention for
 * classes that "set up hooks on instantiation" (see class-woocommerce.php).
 */
final class PaymentEventsListener {

	/**
	 * Repository.
	 *
	 * @var PaymentEventsRepository
	 */
	private PaymentEventsRepository $repo;

	/**
	 * Lifecycle calculator (used to recompute target lifecycle synchronously
	 * after a payment event).
	 *
	 * @var LifecycleCalculator
	 */
	private LifecycleCalculator $lifecycle;

	/**
	 * Container-driven dependency injection. Also registers WC hooks.
	 *
	 * @internal
	 *
	 * @param PaymentEventsRepository $repo      Payment events repository.
	 * @param LifecycleCalculator     $lifecycle Lifecycle service.
	 *
	 * @return void
	 */
	final public function init( PaymentEventsRepository $repo, LifecycleCalculator $lifecycle ): void {
		$this->repo      = $repo;
		$this->lifecycle = $lifecycle;
		$this->register_hooks();
	}

	/**
	 * Register WC action handlers.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ) );
		add_action( 'woocommerce_order_refunded', array( $this, 'on_refunded' ), 10, 2 );
		add_action( 'woocommerce_order_partially_refunded', array( $this, 'on_partially_refunded' ), 10, 2 );
	}

	/**
	 * Record a charge event when an order is paid.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void
	 */
	public function on_payment_complete( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$customer_id = $this->resolve_customer_id( $order );
		if ( ! $customer_id ) {
			return;
		}
		$this->repo->record(
			$customer_id,
			array(
				'order_id'    => (int) $order_id,
				'type'        => 'charge',
				'amount'      => (string) $order->get_total(),
				'currency'    => (string) $order->get_currency(),
				'gateway'     => (string) $order->get_payment_method(),
				'status'      => 'completed',
				'external_id' => 'wc_order_' . (int) $order_id . '_charge',
				'occurred_at' => current_time( 'mysql', 1 ),
			)
		);
		$this->lifecycle->recompute_and_persist( $customer_id );
	}

	/**
	 * Record a full-refund event.
	 *
	 * @param int $order_id  Order id.
	 * @param int $refund_id Refund id.
	 *
	 * @return void
	 */
	public function on_refunded( $order_id, $refund_id ): void {
		$this->record_refund( (int) $order_id, (int) $refund_id, 'refund' );
	}

	/**
	 * Record a partial-refund event.
	 *
	 * @param int $order_id  Order id.
	 * @param int $refund_id Refund id.
	 *
	 * @return void
	 */
	public function on_partially_refunded( $order_id, $refund_id ): void {
		$this->record_refund( (int) $order_id, (int) $refund_id, 'partial_refund' );
	}

	/**
	 * Shared refund recorder.
	 *
	 * @param int    $order_id  Order id.
	 * @param int    $refund_id Refund id.
	 * @param string $type      'refund' or 'partial_refund'.
	 *
	 * @return void
	 */
	private function record_refund( int $order_id, int $refund_id, string $type ): void {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( ! $order instanceof WC_Order || ! $refund instanceof \WC_Order_Refund ) {
			return;
		}
		$customer_id = $this->resolve_customer_id( $order );
		if ( ! $customer_id ) {
			return;
		}
		$this->repo->record(
			$customer_id,
			array(
				'order_id'    => $order_id,
				'type'        => $type,
				'amount'      => (string) abs( (float) $refund->get_total() ),
				'currency'    => (string) $order->get_currency(),
				'gateway'     => (string) $order->get_payment_method(),
				'status'      => 'refunded',
				'external_id' => 'wc_refund_' . $refund_id,
				'occurred_at' => current_time( 'mysql', 1 ),
			)
		);
		$this->lifecycle->recompute_and_persist( $customer_id );
	}

	/**
	 * Resolve the wc_customer_lookup customer_id for an order.
	 *
	 * Prefers user_id when the order is from a registered customer; falls back
	 * to billing email for guests. Returns 0 when no record exists yet.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return int
	 */
	private function resolve_customer_id( WC_Order $order ): int {
		global $wpdb;

		$user_id = (int) $order->get_customer_id();
		if ( $user_id > 0 ) {
			$by_user = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id = %d",
					$user_id
				)
			);
			if ( $by_user > 0 ) {
				return $by_user;
			}
		}

		$email = (string) $order->get_billing_email();
		if ( '' === $email ) {
			return 0;
		}
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE email = %s LIMIT 1",
				$email
			)
		);
	}
}
