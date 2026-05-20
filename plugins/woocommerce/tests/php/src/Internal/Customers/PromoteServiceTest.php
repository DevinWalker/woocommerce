<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\CustomerRepository;
use Automattic\WooCommerce\Internal\Customers\PromoteService;

/**
 * Tests for `\Automattic\WooCommerce\Internal\Customers\PromoteService`.
 */
class PromoteServiceTest extends \WC_Unit_Test_Case {

	/**
	 * Service instance.
	 *
	 * @var PromoteService
	 */
	private PromoteService $svc;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->svc = wc_get_container()->get( PromoteService::class );
	}

	/**
	 * @testdox promote() creates a wc_customer_lookup row with created_via='manual' for a WP user without one.
	 */
	public function test_promote_creates_lookup_row(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'promote@example.test',
			)
		);

		$customer_id = $this->svc->promote( $user_id );
		$this->assertGreaterThan( 0, $customer_id );

		$row = wc_get_container()->get( CustomerRepository::class )->find( $customer_id );
		$this->assertNotNull( $row );
		$this->assertSame( $user_id, (int) $row['user_id'] );
		$this->assertSame( 'manual', $row['created_via'] );
		$this->assertSame( 'prospect', $row['lifecycle_status'] );
	}

	/**
	 * @testdox promote() rejects an unknown user with InvalidArgumentException.
	 */
	public function test_promote_rejects_unknown_user(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->svc->promote( 999999 );
	}

	/**
	 * @testdox promote() rejects when the user already has a customer record.
	 */
	public function test_promote_rejects_existing_customer(): void {
		$user_id = self::factory()->user->create();

		$this->svc->promote( $user_id );

		$this->expectException( \RuntimeException::class );
		$this->svc->promote( $user_id );
	}

	/**
	 * @testdox promote() fires woocommerce_customer_promoted and woocommerce_customer_created actions.
	 */
	public function test_promote_fires_actions(): void {
		$promoted = array();
		$created  = array();
		add_action(
			'woocommerce_customer_promoted',
			static function ( $cid, $user_id ) use ( &$promoted ) {
				$promoted = array( (int) $cid, (int) $user_id );
			},
			10,
			2
		);
		add_action(
			'woocommerce_customer_created',
			static function ( $cid, $source ) use ( &$created ) {
				$created = array( (int) $cid, (string) $source );
			},
			10,
			2
		);

		$user_id     = self::factory()->user->create();
		$customer_id = $this->svc->promote( $user_id );

		$this->assertSame( array( $customer_id, $user_id ), $promoted );
		$this->assertSame( array( $customer_id, 'manual' ), $created );
	}
}
