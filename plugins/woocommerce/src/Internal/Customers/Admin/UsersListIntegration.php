<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers\Admin;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\PromoteService;
use WP_User;

/**
 * Admin integration that lets store managers turn a registered WP user into a
 * customer-view record from the standard `users.php` screen.
 *
 * Adds:
 * - A "Promote to customer" row action on `users.php`
 * - A matching button on `user-edit.php` / profile screens
 * - An `admin.php?action=wc_promote_customer` handler that calls PromoteService
 *   and redirects to `wc-admin?path=/customers/<new-customer-id>`
 *
 * Resolved by the WooCommerce container; `init()` injects the service and
 * registers WC hooks.
 */
final class UsersListIntegration {

	/**
	 * Promote service.
	 *
	 * @var PromoteService
	 */
	private PromoteService $promote;

	/**
	 * Container-driven dependency injection. Registers admin hooks.
	 *
	 * @internal
	 *
	 * @param PromoteService $promote Promote service.
	 *
	 * @return void
	 */
	final public function init( PromoteService $promote ): void {
		$this->promote = $promote;

		add_filter( 'user_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_action( 'show_user_profile', array( $this, 'render_profile_button' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile_button' ) );
		add_action( 'admin_action_wc_promote_customer', array( $this, 'handle_action' ) );
	}

	/**
	 * Append the "Promote to customer" link to a user row when the current
	 * admin has `manage_woocommerce` and the user has no customer record yet.
	 *
	 * @param array<string,string> $actions Existing row actions.
	 * @param WP_User              $user    Row user.
	 *
	 * @return array<string,string>
	 */
	public function add_row_action( array $actions, WP_User $user ): array {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $actions;
		}
		if ( $this->already_customer( $user->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=wc_promote_customer&user_id=' . $user->ID ),
			'wc_promote_customer_' . $user->ID
		);

		$actions['wc_promote_customer'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Promote to customer', 'woocommerce' )
		);

		return $actions;
	}

	/**
	 * Render a matching button on the user profile / edit screens.
	 *
	 * @param WP_User $user Profile user.
	 *
	 * @return void
	 */
	public function render_profile_button( WP_User $user ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( $this->already_customer( $user->ID ) ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=wc_promote_customer&user_id=' . $user->ID ),
			'wc_promote_customer_' . $user->ID
		);

		printf(
			'<h2>%1$s</h2><p><a class="button" href="%2$s">%3$s</a></p>',
			esc_html__( 'WooCommerce', 'woocommerce' ),
			esc_url( $url ),
			esc_html__( 'Promote to customer', 'woocommerce' )
		);
	}

	/**
	 * Handle the `admin.php?action=wc_promote_customer` request. Validates
	 * nonce + capability, calls PromoteService, and redirects to the new
	 * customer-view detail route. `wp_die` on any failure.
	 *
	 * @return void
	 */
	public function handle_action(): void {
		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked next.
		check_admin_referer( 'wc_promote_customer_' . $user_id );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'woocommerce' ) );
		}

		try {
			$customer_id = $this->promote->promote( $user_id );
		} catch ( \Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wc-admin&path=/customers/' . $customer_id ) );
		exit;
	}

	/**
	 * True when the given WP user already has a wc_customer_lookup row.
	 *
	 * @param int $user_id WP user id.
	 *
	 * @return bool
	 */
	private function already_customer( int $user_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id = %d LIMIT 1",
				$user_id
			)
		);
	}
}
