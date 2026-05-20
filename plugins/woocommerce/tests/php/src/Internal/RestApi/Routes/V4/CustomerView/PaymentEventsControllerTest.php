<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\RestApi\Routes\V4\CustomerView;

use Automattic\WooCommerce\Internal\Customers\PaymentEventsRepository;
use Automattic\WooCommerce\Tests\Internal\Customers\CustomerLookupSeederTrait;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for /wc/v4/customer-view/:customer_id/payment-events.
 */
class PaymentEventsControllerTest extends \WC_Unit_Test_Case {

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
	 * @testdox GET lists payment events; filter by type narrows the result.
	 */
	public function test_list_and_filter_by_type(): void {
		wp_set_current_user( $this->admin_id );
		$cid  = $this->insert_lookup_row();
		$repo = wc_get_container()->get( PaymentEventsRepository::class );

		$repo->record(
			$cid,
			array(
				'order_id'    => 1,
				'type'        => 'charge',
				'amount'      => '10',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'succeeded',
				'external_id' => 'pe_test_a',
				'occurred_at' => current_time( 'mysql', 1 ),
			)
		);
		$repo->record(
			$cid,
			array(
				'order_id'    => 1,
				'type'        => 'refund',
				'amount'      => '4',
				'currency'    => 'USD',
				'gateway'     => 'stripe',
				'status'      => 'refunded',
				'external_id' => 'pe_test_b',
				'occurred_at' => current_time( 'mysql', 1 ),
			)
		);

		$all = $this->server->dispatch( new WP_REST_Request( 'GET', "/wc/v4/customer-view/{$cid}/payment-events" ) );
		$this->assertSame( 200, $all->get_status() );
		$this->assertCount( 2, $all->get_data() );

		$refund_req = new WP_REST_Request( 'GET', "/wc/v4/customer-view/{$cid}/payment-events" );
		$refund_req->set_param( 'type', 'refund' );
		$refunds = $this->server->dispatch( $refund_req );
		$this->assertCount( 1, $refunds->get_data() );
		$this->assertSame( 'refund', $refunds->get_data()[0]['type'] );
	}
}
