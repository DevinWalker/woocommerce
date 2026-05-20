<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\NotesRepository;

/**
 * Tests for `\Automattic\WooCommerce\Internal\Customers\NotesRepository`.
 */
class NotesRepositoryTest extends \WC_Unit_Test_Case {

	use CustomerLookupSeederTrait;

	/**
	 * Service instance.
	 *
	 * @var NotesRepository
	 */
	private NotesRepository $repo;

	/**
	 * Customer id seeded for each test.
	 *
	 * @var int
	 */
	private int $customer_id;

	/**
	 * Author user id seeded for each test.
	 *
	 * @var int
	 */
	private int $author_id;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repo        = wc_get_container()->get( NotesRepository::class );
		$this->customer_id = $this->insert_lookup_row();
		$this->author_id   = self::factory()->user->create();
	}

	/**
	 * @testdox add() inserts a note and list_for_customer() returns it; count_for_customer() reflects the total.
	 */
	public function test_add_and_list(): void {
		$note_id = $this->repo->add( $this->customer_id, $this->author_id, 'Hello world' );
		$this->assertGreaterThan( 0, $note_id );

		$list = $this->repo->list_for_customer( $this->customer_id );
		$this->assertCount( 1, $list );
		$this->assertSame( 'Hello world', $list[0]['content'] );

		$this->assertSame( 1, $this->repo->count_for_customer( $this->customer_id ) );
	}

	/**
	 * @testdox add() denormalizes notes_count onto wc_customer_lookup.
	 */
	public function test_add_refreshes_notes_count_on_lookup(): void {
		$this->repo->add( $this->customer_id, $this->author_id, 'one' );
		$this->repo->add( $this->customer_id, $this->author_id, 'two' );

		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT notes_count FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$this->customer_id
			)
		);
		$this->assertSame( 2, $count );
	}

	/**
	 * @testdox edit() succeeds for the original author and refuses other users.
	 */
	public function test_edit_only_by_author(): void {
		$note_id = $this->repo->add( $this->customer_id, $this->author_id, 'Original' );

		$this->assertTrue( $this->repo->edit( $note_id, $this->author_id, 'Updated' ) );

		$other = self::factory()->user->create();
		$this->assertFalse( $this->repo->edit( $note_id, $other, 'Hacked' ) );

		$row = $this->repo->get( $note_id );
		$this->assertSame( 'Updated', $row['content'] );
	}

	/**
	 * @testdox delete() succeeds for the original author or for any caller flagged as admin.
	 */
	public function test_delete_by_author_or_admin(): void {
		$note_a = $this->repo->add( $this->customer_id, $this->author_id, 'A' );
		$this->assertTrue( $this->repo->delete( $note_a, $this->author_id, false ) );

		$note_b  = $this->repo->add( $this->customer_id, $this->author_id, 'B' );
		$some_id = self::factory()->user->create();
		$this->assertTrue( $this->repo->delete( $note_b, $some_id, true ) );

		$note_c = $this->repo->add( $this->customer_id, $this->author_id, 'C' );
		$other  = self::factory()->user->create();
		$this->assertFalse( $this->repo->delete( $note_c, $other, false ) );
	}
}
