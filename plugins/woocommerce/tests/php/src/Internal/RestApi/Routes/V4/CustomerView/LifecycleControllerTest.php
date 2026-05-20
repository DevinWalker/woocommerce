<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\RestApi\Routes\V4\CustomerView;

use Automattic\WooCommerce\Tests\Internal\Customers\CustomerLookupSeederTrait;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for /wc/v4/customer-view/:customer_id/lifecycle endpoints.
 */
class LifecycleControllerTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Admin user.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * REST server.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		add_filter( 'woocommerce_admin_features', static function ( $f ) {
			$f[] = 'rest-api-v4';
			return $f;
		} );
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
		$this->server = $wp_rest_server;
	}

	/**
	 * @testdox POST /lifecycle sets the status manually and marks lifecycle_overridden=1.
	 */
	public function test_set_lifecycle_marks_overridden(): void {
		wp_set_current_user( $this->admin_id );
		$cid = $this->insert_lookup_row();

		$req = new WP_REST_Request( 'POST', "/wc/v4/customer-view/{$cid}/lifecycle" );
		$req->set_param( 'status', 'active' );
		$res = $this->server->dispatch( $req );

		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'active', $res->get_data()['lifecycle_status'] );
		$this->assertTrue( $res->get_data()['lifecycle_overridden'] );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT lifecycle_status, lifecycle_overridden FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$cid
			),
			ARRAY_A
		);
		$this->assertSame( 'active', $row['lifecycle_status'] );
		$this->assertSame( '1', (string) $row['lifecycle_overridden'] );
	}

	/**
	 * @testdox POST /lifecycle rejects an unknown status with 400.
	 */
	public function test_rejects_invalid_status(): void {
		wp_set_current_user( $this->admin_id );
		$cid = $this->insert_lookup_row();

		$req = new WP_REST_Request( 'POST', "/wc/v4/customer-view/{$cid}/lifecycle" );
		$req->set_param( 'status', 'made-up-status' );
		$res = $this->server->dispatch( $req );

		$this->assertSame( 400, $res->get_status() );
	}

	/**
	 * @testdox DELETE /lifecycle/override clears the override and recomputes.
	 */
	public function test_clear_override_recomputes(): void {
		wp_set_current_user( $this->admin_id );
		$cid = $this->insert_lookup_row(
			array(
				'lifecycle_status'     => 'active',
				'lifecycle_overridden' => 1,
			)
		);

		$res = $this->server->dispatch( new WP_REST_Request( 'DELETE', "/wc/v4/customer-view/{$cid}/lifecycle/override" ) );
		$this->assertSame( 204, $res->get_status() );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT lifecycle_status, lifecycle_overridden FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$cid
			),
			ARRAY_A
		);
		$this->assertSame( '0', (string) $row['lifecycle_overridden'] );
		// 0 orders → prospect after recompute.
		$this->assertSame( 'prospect', $row['lifecycle_status'] );
	}

	/**
	 * @testdox POST /lifecycle returns 404 for an unknown customer.
	 */
	public function test_returns_404_for_unknown_customer(): void {
		wp_set_current_user( $this->admin_id );

		$req = new WP_REST_Request( 'POST', '/wc/v4/customer-view/9999999/lifecycle' );
		$req->set_param( 'status', 'active' );
		$res = $this->server->dispatch( $req );

		$this->assertSame( 404, $res->get_status() );
	}
}
