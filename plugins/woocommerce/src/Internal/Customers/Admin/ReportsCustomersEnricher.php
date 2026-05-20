<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers\Admin;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\TagsRepository;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WP_REST_Response;

/**
 * Enriches the Analytics > Customers list response (`/wc-analytics/reports/customers`)
 * with the new customer-view columns (lifecycle_status, lifecycle_overridden) and the
 * list of attached tags for each customer.
 *
 * Only runs when the `customer_view` feature is enabled. The hook fires per row, but
 * we cache lookups for the lifetime of the request to keep it cheap.
 */
final class ReportsCustomersEnricher {

	/**
	 * Tags repository.
	 *
	 * @var TagsRepository
	 */
	private TagsRepository $tags;

	/**
	 * Per-request cache of `customer_id => [ 'lifecycle_status', 'lifecycle_overridden' ]`.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $lifecycle_cache = array();

	/**
	 * Per-request cache of `customer_id => array<int, array{tag_id:int,slug:string,name:string,color:?string}>`.
	 *
	 * @var array<int,array<int,array<string,mixed>>>
	 */
	private array $tags_cache = array();

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param TagsRepository $tags Tags repository.
	 *
	 * @return void
	 */
	final public function init( TagsRepository $tags ): void {
		$this->tags = $tags;

		add_filter(
			'woocommerce_rest_prepare_report_customers',
			array( $this, 'enrich' ),
			10,
			3
		);
	}

	/**
	 * Add `lifecycle_status`, `lifecycle_overridden`, and `tags` to each row when
	 * the customer_view feature is enabled.
	 *
	 * @param WP_REST_Response $response Response object.
	 * @param array            $report   Original report row (assoc array).
	 *
	 * @return WP_REST_Response
	 */
	public function enrich( $response, $report ): WP_REST_Response {
		if ( ! FeaturesUtil::feature_is_enabled( 'customer_view' ) ) {
			return $response;
		}
		if ( ! ( $response instanceof WP_REST_Response ) ) {
			return $response;
		}

		$customer_id = isset( $report['id'] ) ? (int) $report['id'] : 0;
		if ( $customer_id <= 0 ) {
			return $response;
		}

		$data = (array) $response->get_data();

		$lifecycle                       = $this->load_lifecycle( $customer_id );
		$data['lifecycle_status']        = $lifecycle['lifecycle_status'];
		$data['lifecycle_overridden']    = (bool) $lifecycle['lifecycle_overridden'];
		$data['merged_into_customer_id'] = $lifecycle['merged_into_customer_id'];
		$data['tags']                    = $this->load_tags( $customer_id );

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Load lifecycle columns for a customer with per-request caching.
	 *
	 * @param int $customer_id Customer id.
	 *
	 * @return array{lifecycle_status:string, lifecycle_overridden:int, merged_into_customer_id:?int}
	 */
	private function load_lifecycle( int $customer_id ): array {
		if ( isset( $this->lifecycle_cache[ $customer_id ] ) ) {
			return $this->lifecycle_cache[ $customer_id ];
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT lifecycle_status, lifecycle_overridden, merged_into_customer_id
				 FROM {$wpdb->prefix}wc_customer_lookup
				 WHERE customer_id = %d",
				$customer_id
			),
			ARRAY_A
		);

		$result = array(
			'lifecycle_status'        => is_array( $row ) ? (string) ( $row['lifecycle_status'] ?? 'new' ) : 'new',
			'lifecycle_overridden'    => is_array( $row ) ? (int) ( $row['lifecycle_overridden'] ?? 0 ) : 0,
			'merged_into_customer_id' => is_array( $row ) && ! empty( $row['merged_into_customer_id'] )
				? (int) $row['merged_into_customer_id']
				: null,
		);

		$this->lifecycle_cache[ $customer_id ] = $result;

		return $result;
	}

	/**
	 * Load attached tags for a customer with per-request caching.
	 *
	 * @param int $customer_id Customer id.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function load_tags( int $customer_id ): array {
		if ( isset( $this->tags_cache[ $customer_id ] ) ) {
			return $this->tags_cache[ $customer_id ];
		}

		$tags = $this->tags->list_for_customer( $customer_id );
		$out  = array();
		foreach ( $tags as $tag ) {
			$out[] = array(
				'tag_id' => (int) $tag['tag_id'],
				'slug'   => (string) $tag['slug'],
				'name'   => (string) $tag['name'],
				'color'  => isset( $tag['color'] ) ? (string) $tag['color'] : null,
			);
		}

		$this->tags_cache[ $customer_id ] = $out;

		return $out;
	}
}
