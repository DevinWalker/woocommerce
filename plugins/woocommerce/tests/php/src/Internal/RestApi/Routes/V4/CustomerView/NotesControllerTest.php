<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\RestApi\Routes\V4\CustomerView;

use Automattic\WooCommerce\Internal\Customers\NotesRepository;
use Automattic\WooCommerce\Tests\Internal\Customers\CustomerLookupSeederTrait;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for /wc/v4/customer-view/:customer_id/notes endpoints.
 */
class NotesControllerTest extends \WC_Unit_Test_Case {

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
	 * @testdox POST creates a note; GET lists it.
	 */
	public function test_create_and_list_notes(): void {
		wp_set_current_user( $this->admin_id );
		$cid = $this->insert_lookup_row();

		$create = new WP_REST_Request( 'POST', "/wc/v4/customer-view/{$cid}/notes" );
		$create->set_param( 'content', 'hello' );
		$res = $this->server->dispatch( $create );
		$this->assertSame( 201, $res->get_status() );
		$this->assertSame( 'hello', $res->get_data()['content'] );

		$list = $this->server->dispatch( new WP_REST_Request( 'GET', "/wc/v4/customer-view/{$cid}/notes" ) );
		$this->assertSame( 200, $list->get_status() );
		$this->assertCount( 1, $list->get_data() );
	}

	/**
	 * @testdox PATCH succeeds for the original author and returns 403 for another user.
	 */
	public function test_edit_is_author_scoped(): void {
		$cid = $this->insert_lookup_row();
		wp_set_current_user( $this->admin_id );
		$note_id = wc_get_container()->get( NotesRepository::class )->add( $cid, $this->admin_id, 'mine' );

		// Author edits → 200.
		$patch = new WP_REST_Request( 'PATCH', "/wc/v4/customer-view/{$cid}/notes/{$note_id}" );
		$patch->set_param( 'content', 'updated' );
		$res = $this->server->dispatch( $patch );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'updated', $res->get_data()['content'] );

		// Different admin → 403.
		$other = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $other );
		$patch2 = new WP_REST_Request( 'PATCH', "/wc/v4/customer-view/{$cid}/notes/{$note_id}" );
		$patch2->set_param( 'content', 'hacked' );
		$res2 = $this->server->dispatch( $patch2 );
		$this->assertSame( 403, $res2->get_status() );
	}

	/**
	 * @testdox DELETE removes a note and returns 204.
	 */
	public function test_delete(): void {
		wp_set_current_user( $this->admin_id );
		$cid     = $this->insert_lookup_row();
		$note_id = wc_get_container()->get( NotesRepository::class )->add( $cid, $this->admin_id, 'gone' );

		$res = $this->server->dispatch( new WP_REST_Request( 'DELETE', "/wc/v4/customer-view/{$cid}/notes/{$note_id}" ) );
		$this->assertSame( 204, $res->get_status() );
	}

	/**
	 * @testdox Non-admin gets 401/403 on GET.
	 */
	public function test_requires_manage_woocommerce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$cid = $this->insert_lookup_row();
		$res = $this->server->dispatch( new WP_REST_Request( 'GET', "/wc/v4/customer-view/{$cid}/notes" ) );
		$this->assertContains( $res->get_status(), array( 401, 403 ) );
	}
}
