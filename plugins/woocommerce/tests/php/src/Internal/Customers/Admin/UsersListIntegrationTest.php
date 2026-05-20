<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers\Admin;

use Automattic\WooCommerce\Internal\Customers\Admin\UsersListIntegration;
use Automattic\WooCommerce\Internal\Customers\PromoteService;

/**
 * Tests for the users.php "Promote to customer" integration.
 *
 * Focuses on the pure logic methods — `add_row_action()` and `already_customer()`
 * via that method's effects. The HTTP handler `handle_action()` is tested
 * indirectly: PromoteService itself is covered separately, and the handler is a
 * thin nonce + redirect wrapper.
 */
class UsersListIntegrationTest extends \WC_Unit_Test_Case {

	/**
	 * Integration.
	 *
	 * @var UsersListIntegration
	 */
	private UsersListIntegration $svc;

	/**
	 * Admin user (manage_woocommerce).
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->svc      = wc_get_container()->get( UsersListIntegration::class );
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * @testdox add_row_action() appends "Promote to customer" for a user with no customer row.
	 */
	public function test_add_row_action_for_user_without_customer(): void {
		wp_set_current_user( $this->admin_id );

		$other_user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user          = get_userdata( $other_user_id );

		$actions = $this->svc->add_row_action( array(), $user );

		$this->assertArrayHasKey( 'wc_promote_customer', $actions );
		$this->assertStringContainsString( 'Promote to customer', $actions['wc_promote_customer'] );
	}

	/**
	 * @testdox add_row_action() omits the link when the user already has a customer row.
	 */
	public function test_add_row_action_skipped_when_already_customer(): void {
		wp_set_current_user( $this->admin_id );

		$wp_user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wc_get_container()->get( PromoteService::class )->promote( $wp_user_id );

		$actions = $this->svc->add_row_action( array(), get_userdata( $wp_user_id ) );

		$this->assertArrayNotHasKey( 'wc_promote_customer', $actions );
	}

	/**
	 * @testdox add_row_action() returns the original actions unchanged for non-admin callers.
	 */
	public function test_add_row_action_skipped_for_non_admin(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$target = self::factory()->user->create();

		$actions = $this->svc->add_row_action( array( 'foo' => 'bar' ), get_userdata( $target ) );

		$this->assertSame( array( 'foo' => 'bar' ), $actions );
	}
}
