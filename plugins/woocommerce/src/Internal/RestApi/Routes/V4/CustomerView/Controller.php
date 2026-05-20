<?php
/**
 * REST API Customer View controller — read endpoints keyed by wc_customer_lookup.customer_id.
 *
 * @package WooCommerce\RestApi
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\RestApi\Routes\V4\CustomerView;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\CustomerRepository;
use Automattic\WooCommerce\Internal\RestApi\Routes\V4\AbstractController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Customer View controller.
 *
 * Lives alongside the existing `/wc/v4/customers/:id` endpoint (which is keyed
 * by WP user id and intended for editing the user side of a customer). This
 * controller is keyed by `wc_customer_lookup.customer_id` so it can address
 * guest customers and merged records the user-keyed endpoint cannot reach.
 */
class Controller extends AbstractController {
	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'customer-view';

	/**
	 * Schema instance.
	 *
	 * @var CustomerViewSchema
	 */
	private CustomerViewSchema $item_schema;

	/**
	 * Customer repository.
	 *
	 * @var CustomerRepository
	 */
	private CustomerRepository $customer_repository;

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param CustomerViewSchema $item_schema         Schema instance.
	 * @param CustomerRepository $customer_repository Customer repository.
	 *
	 * @return void
	 */
	final public function init( CustomerViewSchema $item_schema, CustomerRepository $customer_repository ): void {
		$this->item_schema         = $item_schema;
		$this->customer_repository = $customer_repository;
	}

	/**
	 * Return the schema for the current resource.
	 *
	 * @return array
	 */
	protected function get_schema(): array {
		return $this->item_schema->get_item_schema();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)',
			array(
				'schema' => array( $this, 'get_public_item_schema' ),
				'args'   => array(
					'customer_id' => array(
						'description' => __( 'wc_customer_lookup customer id.', 'woocommerce' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
			)
		);
	}

	/**
	 * Map a `wc_customer_lookup` row to its response shape (delegates to the schema).
	 *
	 * @param array                                $item    Lookup row.
	 * @param WP_REST_Request<array<string,mixed>> $request Request object.
	 *
	 * @return array
	 */
	protected function get_item_response( $item, WP_REST_Request $request ): array {
		return $this->item_schema->get_item_response( $item, $request );
	}

	/**
	 * `manage_woocommerce` gate on every route.
	 *
	 * @return bool
	 */
	public function permission_check(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Get a single customer-view record by customer_id.
	 *
	 * Returns 404 if the customer does not exist.
	 * Returns 301 with `{ merged_into: <target_id> }` body if the source has been merged;
	 * clients should follow the merge and re-fetch the target id.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		$customer_id = (int) $request['customer_id'];
		$row         = $this->customer_repository->find( $customer_id );

		if ( ! $row ) {
			return new WP_REST_Response(
				array(
					'code'    => 'woocommerce_rest_customer_not_found',
					'message' => __( 'Customer not found.', 'woocommerce' ),
				),
				404
			);
		}

		if ( ! empty( $row['merged_into_customer_id'] ) ) {
			return new WP_REST_Response(
				array( 'merged_into' => (int) $row['merged_into_customer_id'] ),
				301
			);
		}

		return new WP_REST_Response( $this->item_schema->get_item_response( $row, $request ), 200 );
	}
}
