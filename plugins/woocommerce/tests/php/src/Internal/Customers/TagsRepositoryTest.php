<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\TagsRepository;

/**
 * Tests for `\Automattic\WooCommerce\Internal\Customers\TagsRepository`.
 */
class TagsRepositoryTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Service instance.
	 *
	 * @var TagsRepository
	 */
	private TagsRepository $repo;

	/**
	 * Admin user id used as the author/assigner.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repo     = wc_get_container()->get( TagsRepository::class );
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * @testdox create_tag() derives a slug from the name when slug is omitted, and stores the color.
	 */
	public function test_create_tag_derives_slug_from_name(): void {
		$id = $this->repo->create_tag( 'VIP', null, '#ff0000', $this->admin_id );

		$row = $this->repo->get_tag( $id );
		$this->assertSame( 'vip', $row['slug'] );
		$this->assertSame( 'VIP', $row['name'] );
		$this->assertSame( '#ff0000', $row['color'] );
	}

	/**
	 * @testdox create_tag() rejects a slug that already exists.
	 */
	public function test_create_tag_rejects_duplicate_slug(): void {
		$this->repo->create_tag( 'VIP', 'vip', null, $this->admin_id );

		$this->expectException( \RuntimeException::class );
		$this->repo->create_tag( 'Another', 'vip', null, $this->admin_id );
	}

	/**
	 * @testdox attach() / detach() add and remove a tag from a customer; duplicate attach is idempotent.
	 */
	public function test_attach_and_detach(): void {
		$customer_id = $this->insert_lookup_row();
		$tag_id      = $this->repo->create_tag( 'VIP', 'vip-' . uniqid(), null, $this->admin_id );

		$this->repo->attach( $customer_id, $tag_id, $this->admin_id );
		$attached = array_map( 'intval', wp_list_pluck( $this->repo->list_for_customer( $customer_id ), 'tag_id' ) );
		$this->assertSame( array( $tag_id ), $attached );

		// Duplicate attach should not create a second row.
		$this->repo->attach( $customer_id, $tag_id, $this->admin_id );
		$this->assertCount( 1, $this->repo->list_for_customer( $customer_id ) );

		$this->repo->detach( $customer_id, $tag_id );
		$this->assertCount( 0, $this->repo->list_for_customer( $customer_id ) );
	}

	/**
	 * @testdox delete_tag() cascades to remove relationships pointing at it.
	 */
	public function test_delete_tag_cascades_relationships(): void {
		$customer_id = $this->insert_lookup_row();
		$tag_id      = $this->repo->create_tag( 'Wholesale', 'wholesale-' . uniqid(), null, $this->admin_id );
		$this->repo->attach( $customer_id, $tag_id, $this->admin_id );

		$this->repo->delete_tag( $tag_id );

		$this->assertNull( $this->repo->get_tag( $tag_id ) );
		$this->assertCount( 0, $this->repo->list_for_customer( $customer_id ) );
	}
}
