<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

/**
 * Manually promote a registered WP user (with no purchase history) to a
 * customer-view record. Used by the "Promote to customer" admin action on
 * the users list page and by the REST `POST /wc/v4/customers/promote` endpoint.
 *
 * Resolved by the WooCommerce reflection-based container; no explicit registration needed.
 */
final class PromoteService {

	/**
	 * Dependency: lifecycle calculator.
	 *
	 * @var LifecycleCalculator
	 */
	private LifecycleCalculator $lifecycle;

	/**
	 * Container-driven dependency injection. The WooCommerce reflection-based
	 * container invokes `init()` with type-hinted dependencies from `src/`.
	 *
	 * @internal
	 *
	 * @param LifecycleCalculator $lifecycle Lifecycle service.
	 *
	 * @return void
	 */
	final public function init( LifecycleCalculator $lifecycle ): void {
		$this->lifecycle = $lifecycle;
	}

	/**
	 * Promote a registered WP user into a customer-view record.
	 *
	 * The new row is marked with `created_via='manual'` and `lifecycle_status='prospect'`
	 * (the canonical state for a customer with no purchases yet).
	 *
	 * @param int $wp_user_id WP user id to promote.
	 *
	 * @return int The new customer_id.
	 *
	 * @throws \InvalidArgumentException When the user does not exist.
	 * @throws \RuntimeException When the user already has a customer-lookup row.
	 */
	public function promote( int $wp_user_id ): int {
		$user = get_userdata( $wp_user_id );
		if ( ! $user ) {
			throw new \InvalidArgumentException( "User {$wp_user_id} not found" );
		}

		global $wpdb;
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id = %d",
				$wp_user_id
			)
		);
		if ( $existing ) {
			throw new \RuntimeException( "Customer already exists for user {$wp_user_id}" );
		}

		$wpdb->insert(
			$wpdb->prefix . 'wc_customer_lookup',
			array(
				'user_id'          => $wp_user_id,
				'username'         => $user->user_login,
				'first_name'       => $user->first_name ?: '',
				'last_name'        => $user->last_name ?: '',
				'email'             => $user->user_email,
				'date_registered'  => gmdate( 'Y-m-d H:i:s', (int) strtotime( $user->user_registered ) ),
				'date_last_active' => current_time( 'mysql', 1 ),
				'country'          => '',
				'postcode'         => '',
				'city'             => '',
				'state'            => '',
				'created_via'      => 'manual',
			)
		);

		$customer_id = (int) $wpdb->insert_id;

		// Compute lifecycle from data (0 orders → 'prospect').
		$this->lifecycle->recompute_and_persist( $customer_id );

		do_action( 'woocommerce_customer_created', $customer_id, 'manual' );
		do_action( 'woocommerce_customer_promoted', $customer_id, $wp_user_id );

		return $customer_id;
	}
}
