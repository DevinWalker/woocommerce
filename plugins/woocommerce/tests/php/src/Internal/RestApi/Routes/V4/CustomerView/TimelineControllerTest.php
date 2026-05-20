<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\RestApi\Routes\V4\CustomerView;

use Automattic\WooCommerce\Internal\Customers\NotesRepository;
use Automattic\WooCommerce\Tests\Internal\Customers\CustomerLookupSeederTrait;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for /wc/v4/customer-view/:customer_id/timeline.
 */
class TimelineControllerTest extends \WC_Unit_Test_Case {

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
	 * @testdox GET returns events for a customer.
	 */
	public function test_returns_events(): void {
		wp_set_current_user( $this->admin_id );
		$cid = $this->insert_lookup_row();
		wc_get_container()->get( NotesRepository::class )->add( $cid, $this->admin_id, 'visible in timeline' );

		$res = $this->server->dispatch( new WP_REST_Request( 'GET', "/wc/v4/customer-view/{$cid}/timeline" ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertContains( 'note_added', array_column( $res->get_data(), 'type' ) );
	}
}
