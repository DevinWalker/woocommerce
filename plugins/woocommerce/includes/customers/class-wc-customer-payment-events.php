<?php
/**
 * WC_Customer_Payment_Events facade.
 *
 * Thin static wrapper so third-party gateways can record granular events into
 * the customer-view payment ledger without depending on internal namespaces.
 *
 * @package WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public helper for recording per-customer payment events.
 *
 * Example:
 * ```
 * WC_Customer_Payment_Events::record( $customer_id, array(
 *     'order_id'    => $order->get_id(),
 *     'type'        => 'capture',
 *     'amount'      => $order->get_total(),
 *     'currency'    => $order->get_currency(),
 *     'gateway'     => 'stripe',
 *     'status'      => 'succeeded',
 *     'external_id' => $stripe_event_id,
 *     'occurred_at' => current_time( 'mysql', 1 ),
 * ) );
 * ```
 */
class WC_Customer_Payment_Events {

	/**
	 * Record a payment event for a customer. Returns the new event id, or 0
	 * if the row was deduped on the unique (gateway, external_id) index.
	 *
	 * Required `$args` keys: order_id, type, amount, currency, gateway, status.
	 * Optional keys: external_id, occurred_at.
	 *
	 * @param int   $customer_id wc_customer_lookup.customer_id.
	 * @param array $args        Event fields.
	 *
	 * @return int
	 */
	public static function record( int $customer_id, array $args ): int {
		return wc_get_container()
			->get( \Automattic\WooCommerce\Internal\Customers\PaymentEventsRepository::class )
			->record( $customer_id, $args );
	}
}
