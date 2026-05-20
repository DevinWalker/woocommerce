<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\CustomerRepository;

/**
 * Tests for `\Automattic\WooCommerce\Internal\Customers\CustomerRepository`.
 */
class CustomerRepositoryTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Service instance.
	 *
	 * @var CustomerRepository
	 */
	private CustomerRepository $repo;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repo = wc_get_container()->get( CustomerRepository::class );
	}

	/**
	 * @testdox CustomerRepository resolves from the WooCommerce container.
	 */
	public function test_customer_repository_is_resolvable_from_container(): void {
		$this->assertInstanceOf( CustomerRepository::class, $this->repo );
	}

	/**
	 * @testdox find() returns null for an unknown customer id.
	 */
	public function test_find_returns_null_for_unknown_id(): void {
		$this->assertNull( $this->repo->find( 999999 ) );
	}

	/**
	 * @testdox find() returns the row for a known customer id, with customer_id coerced to int.
	 */
	public function test_find_returns_array_for_known_customer(): void {
		$id = $this->insert_lookup_row(
			array(
				'email'            => 'a@b.test',
				'lifecycle_status' => 'new',
			)
		);

		$row = $this->repo->find( $id );

		$this->assertIsArray( $row );
		$this->assertSame( $id, $row['customer_id'] );
		$this->assertSame( 'a@b.test', $row['email'] );
		$this->assertSame( 'new', $row['lifecycle_status'] );
	}

	/**
	 * @testdox find() follows merge redirect when $follow_merge=true, and exposes merged_into_customer_id when false.
	 */
	public function test_find_follows_merge_redirect(): void {
		$target = $this->insert_lookup_row( array( 'email' => 't@x.test' ) );
		$source = $this->insert_lookup_row(
			array(
				'email'                   => 's@x.test',
				'merged_into_customer_id' => $target,
			)
		);

		// Sanity: source row should have the merged_into pointer.
		$raw_source = $this->repo->find( $source, false );
		$this->assertNotNull( $raw_source, 'source row should exist' );
		$this->assertSame( $target, (int) $raw_source['merged_into_customer_id'] );

		$followed = $this->repo->find( $source, true );
		$this->assertNotNull( $followed, 'find( source, true ) should resolve to target row' );
		$this->assertSame( $target, $followed['customer_id'] );

		$unfollowed = $this->repo->find( $source, false );
		$this->assertSame( $source, $unfollowed['customer_id'] );
		$this->assertSame( $target, (int) $unfollowed['merged_into_customer_id'] );
	}
}
