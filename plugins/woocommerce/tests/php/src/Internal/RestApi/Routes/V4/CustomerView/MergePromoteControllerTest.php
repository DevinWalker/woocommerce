<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\RestApi\Routes\V4\CustomerView;

use Automattic\WooCommerce\Tests\Internal\Customers\CustomerLookupSeederTrait;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for the merge and promote REST endpoints.
 */
class MergePromoteControllerTest extends \WC_Unit_Test_Case {

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
	 * @testdox POST /:source/merge merges into the target and returns the moved counts.
	 */
	public function test_merge_succeeds(): void {
		wp_set_current_user( $this->admin_id );
		$source = $this->insert_lookup_row();
		$target = $this->insert_lookup_row();

		$req = new WP_REST_Request( 'POST', "/wc/v4/customer-view/{$source}/merge" );
		$req->set_param( 'target_customer_id', $target );
		$res = $this->server->dispatch( $req );

		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( $target, $res->get_data()['target_customer_id'] );
		$this->assertArrayHasKey( 'moved', $res->get_data() );
	}

	/**
	 * @testdox POST /:source/merge returns 400 when source equals target.
	 */
	public function test_merge_rejects_source_equals_target(): void {
		wp_set_current_user( $this->admin_id );
		$cid = $this->insert_lookup_row();

		$req = new WP_REST_Request( 'POST', "/wc/v4/customer-view/{$cid}/merge" );
		$req->set_param( 'target_customer_id', $cid );
		$res = $this->server->dispatch( $req );

		$this->assertSame( 400, $res->get_status() );
	}

	/**
	 * @testdox POST /promote creates a customer record for a registered WP user.
	 */
	public function test_promote_creates_customer(): void {
		wp_set_current_user( $this->admin_id );
		$wp_user = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$req = new WP_REST_Request( 'POST', '/wc/v4/customer-view/promote' );
		$req->set_param( 'wp_user_id', $wp_user );
		$res = $this->server->dispatch( $req );

		$this->assertSame( 201, $res->get_status() );
		$this->assertGreaterThan( 0, $res->get_data()['id'] );
	}

	/**
	 * @testdox POST /promote returns 404 for an unknown user.
	 */
	public function test_promote_unknown_user_returns_404(): void {
		wp_set_current_user( $this->admin_id );

		$req = new WP_REST_Request( 'POST', '/wc/v4/customer-view/promote' );
		$req->set_param( 'wp_user_id', 999999 );
		$res = $this->server->dispatch( $req );

		$this->assertSame( 404, $res->get_status() );
	}

	/**
	 * @testdox POST /promote returns 409 if the user already has a customer record.
	 */
	public function test_promote_existing_returns_409(): void {
		wp_set_current_user( $this->admin_id );
		$wp_user = self::factory()->user->create();

		$req = new WP_REST_Request( 'POST', '/wc/v4/customer-view/promote' );
		$req->set_param( 'wp_user_id', $wp_user );
		$this->server->dispatch( $req );

		$res = $this->server->dispatch( $req );
		$this->assertSame( 409, $res->get_status() );
	}
}
