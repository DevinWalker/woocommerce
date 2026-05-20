<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\RestApi\Routes\V4\CustomerView;

use Automattic\WooCommerce\Internal\Customers\TagsRepository;
use Automattic\WooCommerce\Tests\Internal\Customers\CustomerLookupSeederTrait;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for /wc/v4/customer-view/:customer_id/tags endpoints.
 */
class TagsControllerTest extends \WC_Unit_Test_Case {

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
	 * @testdox POST with a new slug creates the tag and attaches it.
	 */
	public function test_post_with_new_slug_creates_and_attaches(): void {
		wp_set_current_user( $this->admin_id );
		$cid  = $this->insert_lookup_row();
		$slug = 'vip-' . uniqid();

		$req = new WP_REST_Request( 'POST', "/wc/v4/customer-view/{$cid}/tags" );
		$req->set_param( 'slug', $slug );
		$req->set_param( 'name', 'VIP' );
		$req->set_param( 'color', '#ff0000' );

		$res = $this->server->dispatch( $req );
		$this->assertSame( 201, $res->get_status() );
		$this->assertSame( $slug, $res->get_data()['slug'] );

		$this->assertCount( 1, wc_get_container()->get( TagsRepository::class )->list_for_customer( $cid ) );
	}

	/**
	 * @testdox POST with an existing slug attaches the existing tag (no duplicate).
	 */
	public function test_post_with_existing_slug_attaches_existing(): void {
		wp_set_current_user( $this->admin_id );
		$cid  = $this->insert_lookup_row();
		$slug = 'reuse-' . uniqid();
		wc_get_container()->get( TagsRepository::class )->create_tag( 'Reuse', $slug, null, $this->admin_id );

		$req = new WP_REST_Request( 'POST', "/wc/v4/customer-view/{$cid}/tags" );
		$req->set_param( 'slug', $slug );
		$res = $this->server->dispatch( $req );

		$this->assertSame( 201, $res->get_status() );
		$this->assertCount( 1, wc_get_container()->get( TagsRepository::class )->list_for_customer( $cid ) );
	}

	/**
	 * @testdox DELETE detaches a tag from a customer.
	 */
	public function test_delete_detaches(): void {
		wp_set_current_user( $this->admin_id );
		$repo   = wc_get_container()->get( TagsRepository::class );
		$cid    = $this->insert_lookup_row();
		$tag_id = $repo->create_tag( 'Drop', 'drop-' . uniqid(), null, $this->admin_id );
		$repo->attach( $cid, $tag_id, $this->admin_id );

		$res = $this->server->dispatch( new WP_REST_Request( 'DELETE', "/wc/v4/customer-view/{$cid}/tags/{$tag_id}" ) );
		$this->assertSame( 204, $res->get_status() );
		$this->assertCount( 0, $repo->list_for_customer( $cid ) );
	}

	/**
	 * @testdox GET /wc/v4/customer-tags lists the global catalog.
	 */
	public function test_global_tag_list(): void {
		wp_set_current_user( $this->admin_id );
		wc_get_container()->get( TagsRepository::class )->create_tag( 'CatX', 'cat-x-' . uniqid(), null, $this->admin_id );

		$res = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v4/customer-tags' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertGreaterThanOrEqual( 1, count( $res->get_data() ) );
	}

	/**
	 * @testdox Non-admin gets 401/403 on writes.
	 */
	public function test_requires_manage_woocommerce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$cid = $this->insert_lookup_row();

		$req = new WP_REST_Request( 'POST', "/wc/v4/customer-view/{$cid}/tags" );
		$req->set_param( 'slug', 'denied' );
		$res = $this->server->dispatch( $req );
		$this->assertContains( $res->get_status(), array( 401, 403 ) );
	}
}
