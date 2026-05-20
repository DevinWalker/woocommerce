<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Customers\Admin;

use Automattic\WooCommerce\Internal\Customers\Admin\ReportsCustomersEnricher;
use Automattic\WooCommerce\Internal\Customers\TagsRepository;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WP_REST_Response;

/**
 * Tests for ReportsCustomersEnricher: enriches the analytics customers report
 * rows with lifecycle_status, lifecycle_overridden, merged_into_customer_id,
 * and tags when the customer_view feature is enabled.
 */
class ReportsCustomersEnricherTest extends \WC_Unit_Test_Case {

	/**
	 * Service under test.
	 *
	 * @var ReportsCustomersEnricher
	 */
	private ReportsCustomersEnricher $svc;

	/**
	 * Tags repo.
	 *
	 * @var TagsRepository
	 */
	private TagsRepository $tags;

	/**
	 * Admin user id (used as `assigned_by` for tag attachment).
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Test setup.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->svc      = wc_get_container()->get( ReportsCustomersEnricher::class );
		$this->tags     = wc_get_container()->get( TagsRepository::class );
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Insert a row into wc_customer_lookup and return its customer_id.
	 *
	 * @param array<string,mixed> $overrides Row overrides.
	 *
	 * @return int
	 */
	private function insert_customer( array $overrides = array() ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wc_customer_lookup',
			array_merge(
				array(
					'user_id'              => 0,
					'email'                => uniqid( 'e' ) . '@x.test',
					'username'             => '',
					'first_name'           => '',
					'last_name'            => '',
					'date_last_active'     => current_time( 'mysql' ),
					'date_registered'      => current_time( 'mysql' ),
					'country'              => '',
					'postcode'             => '',
					'city'                 => '',
					'state'                => '',
					'lifecycle_status'     => 'new',
					'lifecycle_overridden' => 0,
				),
				$overrides
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * @testdox enrich() is a no-op when the feature flag is disabled.
	 */
	public function test_enrich_noop_when_feature_disabled(): void {
		// Ensure the flag is off.
		add_filter(
			'woocommerce_admin_features',
			function ( $features ) {
				return array_values( array_diff( $features, array( 'customer_view' ) ) );
			}
		);
		// Sanity check on flag state via FeaturesUtil. If still enabled, skip — env override.
		if ( FeaturesUtil::feature_is_enabled( 'customer_view' ) ) {
			$this->markTestSkipped( 'customer_view flag is forced on in this environment.' );
		}

		$customer_id = $this->insert_customer();
		$response    = new WP_REST_Response(
			array(
				'id'    => $customer_id,
				'email' => 'noop@x.test',
			),
			200
		);

		$out = $this->svc->enrich( $response, array( 'id' => $customer_id ), null );
		$data = (array) $out->get_data();

		$this->assertArrayNotHasKey( 'lifecycle_status', $data );
		$this->assertArrayNotHasKey( 'tags', $data );
	}

	/**
	 * @testdox enrich() adds lifecycle and tags when the feature is enabled.
	 */
	public function test_enrich_adds_fields_when_feature_enabled(): void {
		add_filter(
			'woocommerce_admin_features',
			function ( $features ) {
				$features[] = 'customer_view';
				return array_values( array_unique( $features ) );
			}
		);
		if ( ! FeaturesUtil::feature_is_enabled( 'customer_view' ) ) {
			$this->markTestSkipped( 'customer_view flag could not be enabled in this environment.' );
		}

		$customer_id = $this->insert_customer(
			array(
				'lifecycle_status'     => 'active',
				'lifecycle_overridden' => 1,
			)
		);

		$tag_id = $this->tags->create_tag( 'VIP', 'vip-' . $customer_id, '#ff0000', $this->admin_id );
		$this->tags->attach( $customer_id, $tag_id, $this->admin_id );

		$response = new WP_REST_Response(
			array(
				'id'    => $customer_id,
				'email' => 'enriched@x.test',
			),
			200
		);

		$out  = $this->svc->enrich( $response, array( 'id' => $customer_id ), null );
		$data = (array) $out->get_data();

		$this->assertArrayHasKey( 'lifecycle_status', $data );
		$this->assertSame( 'active', $data['lifecycle_status'] );
		$this->assertArrayHasKey( 'lifecycle_overridden', $data );
		$this->assertTrue( $data['lifecycle_overridden'] );
		$this->assertArrayHasKey( 'tags', $data );
		$this->assertCount( 1, $data['tags'] );
		$this->assertSame( $tag_id, $data['tags'][0]['tag_id'] );
		$this->assertSame( 'VIP', $data['tags'][0]['name'] );
	}

	/**
	 * @testdox enrich() returns null merged_into_customer_id when not merged.
	 */
	public function test_enrich_includes_null_merged_into(): void {
		add_filter(
			'woocommerce_admin_features',
			function ( $features ) {
				$features[] = 'customer_view';
				return array_values( array_unique( $features ) );
			}
		);
		if ( ! FeaturesUtil::feature_is_enabled( 'customer_view' ) ) {
			$this->markTestSkipped( 'customer_view flag could not be enabled.' );
		}

		$customer_id = $this->insert_customer();
		$response    = new WP_REST_Response( array( 'id' => $customer_id ), 200 );
		$out         = $this->svc->enrich( $response, array( 'id' => $customer_id ), null );
		$data        = (array) $out->get_data();

		$this->assertArrayHasKey( 'merged_into_customer_id', $data );
		$this->assertNull( $data['merged_into_customer_id'] );
	}
}
