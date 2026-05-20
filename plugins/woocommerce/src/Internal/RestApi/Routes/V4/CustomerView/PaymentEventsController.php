<?php
/**
 * REST API Customer View Payment Events controller.
 *
 * @package WooCommerce\RestApi
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\RestApi\Routes\V4\CustomerView;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\PaymentEventsRepository;
use Automattic\WooCommerce\Internal\RestApi\Routes\V4\AbstractController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Payment events ledger endpoint:
 *   GET /wc/v4/customer-view/:customer_id/payment-events?type=charge&page=1&per_page=25
 */
class PaymentEventsController extends AbstractController {
	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'customer-view';

	/**
	 * Payment events repository.
	 *
	 * @var PaymentEventsRepository
	 */
	private PaymentEventsRepository $repo;

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param PaymentEventsRepository $repo Payment events repository.
	 *
	 * @return void
	 */
	final public function init( PaymentEventsRepository $repo ): void {
		$this->repo = $repo;
	}

	/**
	 * Schema for a single payment event.
	 *
	 * @return array
	 */
	protected function get_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'customer-view-payment-event',
			'type'       => 'object',
			'properties' => array(
				'event_id'    => array( 'type' => 'integer', 'readonly' => true ),
				'customer_id' => array( 'type' => 'integer', 'readonly' => true ),
				'order_id'    => array( 'type' => 'integer', 'readonly' => true ),
				'type'        => array( 'type' => 'string', 'readonly' => true ),
				'amount'      => array( 'type' => 'string', 'readonly' => true ),
				'currency'    => array( 'type' => 'string', 'readonly' => true ),
				'gateway'     => array( 'type' => 'string', 'readonly' => true ),
				'status'      => array( 'type' => 'string', 'readonly' => true ),
				'external_id' => array( 'type' => array( 'string', 'null' ), 'readonly' => true ),
				'created_at'  => array( 'type' => 'string', 'readonly' => true ),
			),
		);
	}

	/**
	 * Pass-through item response.
	 *
	 * @param array                                $item    Event row.
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return array
	 */
	protected function get_item_response( $item, WP_REST_Request $request ): array {
		unset( $request );
		return (array) $item;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/payment-events',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer', 'default' => 1 ),
						'per_page'    => array( 'type' => 'integer', 'default' => 25, 'maximum' => 100 ),
						'type'        => array(
							'type'        => 'string',
							'enum'        => array( 'charge', 'capture', 'refund', 'partial_refund', 'void', 'chargeback' ),
							'description' => 'Filter by event type.',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission gate.
	 *
	 * @return bool
	 */
	public function permission_check(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Return the customer's payment events.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$rows = $this->repo->list_for_customer(
			(int) $request['customer_id'],
			$request->get_param( 'type' ) ? (string) $request->get_param( 'type' ) : null,
			(int) $request['page'],
			(int) $request['per_page']
		);
		return new WP_REST_Response( $rows, 200 );
	}
}
