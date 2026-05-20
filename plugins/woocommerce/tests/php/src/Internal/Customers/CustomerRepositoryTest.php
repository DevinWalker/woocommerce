<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers;

use Automattic\WooCommerce\Internal\Customers\CustomerRepository;

/**
 * Tests for `\Automattic\WooCommerce\Internal\Customers\CustomerRepository`.
 */
class CustomerRepositoryTest extends \WC_Unit_Test_Case {

	/**
	 * @testdox CustomerRepository resolves from the WooCommerce container.
	 */
	public function test_customer_repository_is_resolvable_from_container(): void {
		$repo = wc_get_container()->get( CustomerRepository::class );
		$this->assertInstanceOf( CustomerRepository::class, $repo );
	}
}
