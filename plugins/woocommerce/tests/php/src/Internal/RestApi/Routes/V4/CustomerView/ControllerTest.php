<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\RestApi\Routes\V4\CustomerView;

use Automattic\WooCommerce\Internal\Customers\NotesRepository;
use Automattic\WooCommerce\Internal\Customers\TagsRepository;
use Automattic\WooCommerce\Tests\Internal\Customers\CustomerLookupSeederTrait;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for the `/wc/v4/customer-view/:customer_id` endpoint.
 */
class ControllerTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Admin user id (manage_woocommerce).
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

		// Ensure the rest-api-v4 feature is enabled in the test environment so the route gets registered.
		add_filter( 'woocommerce_admin_features', static function ( $features ) {
			$features[] = 'rest-api-v4';
			return $features;
		} );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
		$this->server = $wp_rest_server;
	}

	/**
	 * @testdox GET /wc/v4/customer-view/:id returns the enriched customer payload for an admin.
	 */
	public function test_get_item_returns_enriched_payload(): void {
		wp_set_current_user( $this->admin_id );

		$customer_id = $this->insert_lookup_row(
			array(
				'email'      => 'gv@example.test',
				'first_name' => 'Greta',
				'last_name'  => 'Voss',
			)
		);

		// Seed a note + a tag so the enriched payload has something to report.
		$notes = wc_get_container()->get( NotesRepository::class );
		$tags  = wc_get_container()->get( TagsRepository::class );
		$notes->add( $customer_id, $this->admin_id, 'hi' );
		$tag_id = $tags->create_tag( 'VIP', 'vip-' . uniqid(), '#ff0000', $this->admin_id );
		$tags->attach( $customer_id, $tag_id, $this->admin_id );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', "/wc/v4/customer-view/{$customer_id}" ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $customer_id, $data['id'] );
		$this->assertSame( 'gv@example.test', $data['email'] );
		$this->assertSame( 'Greta', $data['first_name'] );
		$this->assertArrayHasKey( 'lifecycle_status', $data );
		$this->assertArrayHasKey( 'lifecycle_overridden', $data );
		$this->assertArrayHasKey( 'created_via', $data );
		$this->assertSame( 1, $data['notes_count'] );
		$this->assertCount( 1, $data['tags'] );
		$this->assertSame( 'VIP', $data['tags'][0]['name'] );
		$this->assertFalse( $data['is_registered'] );
	}

	/**
	 * @testdox GET /wc/v4/customer-view/:id returns 404 for an unknown customer id.
	 */
	public function test_get_item_returns_404_for_unknown_id(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v4/customer-view/9999999' ) );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @testdox GET /wc/v4/customer-view/:id returns 301 with merged_into for a merged source.
	 */
	public function test_get_item_returns_301_for_merged_source(): void {
		wp_set_current_user( $this->admin_id );

		$target = $this->insert_lookup_row( array( 'email' => 't@example.test' ) );
		$source = $this->insert_lookup_row(
			array(
				'email'                   => 's@example.test',
				'merged_into_customer_id' => $target,
			)
		);

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', "/wc/v4/customer-view/{$source}" ) );
		$this->assertSame( 301, $response->get_status() );
		$this->assertSame( $target, $response->get_data()['merged_into'] );
	}

	/**
	 * @testdox Non-admin users get a 403 / 401 from /wc/v4/customer-view/:id.
	 */
	public function test_get_item_requires_manage_woocommerce(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$customer_id = $this->insert_lookup_row();

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', "/wc/v4/customer-view/{$customer_id}" ) );
		$this->assertContains( $response->get_status(), array( 401, 403 ), 'Expected 401/403 for non-admin (got ' . $response->get_status() . ').' );
	}
}
