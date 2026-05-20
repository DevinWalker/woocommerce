<?php
/**
 * REST API Customer View Merge controller.
 *
 * @package WooCommerce\RestApi
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\RestApi\Routes\V4\CustomerView;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\MergeService;
use Automattic\WooCommerce\Internal\RestApi\Routes\V4\AbstractController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Merge endpoint:
 *   POST /wc/v4/customer-view/:customer_id/merge   { target_customer_id }
 */
class MergeController extends AbstractController {
	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'customer-view';

	/**
	 * Merge service.
	 *
	 * @var MergeService
	 */
	private MergeService $merge_service;

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param MergeService $merge_service Merge service.
	 *
	 * @return void
	 */
	final public function init( MergeService $merge_service ): void {
		$this->merge_service = $merge_service;
	}

	/**
	 * Schema.
	 *
	 * @return array
	 */
	protected function get_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'customer-view-merge',
			'type'       => 'object',
			'properties' => array(
				'target_customer_id' => array( 'type' => 'integer' ),
				'moved'              => array( 'type' => 'object', 'readonly' => true ),
			),
		);
	}

	/**
	 * Pass-through item response.
	 *
	 * @param array                                $item    Row.
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
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/merge',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id'        => array( 'type' => 'integer' ),
						'target_customer_id' => array( 'type' => 'integer', 'required' => true ),
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
	 * Merge a source customer into a target.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$source = (int) $request['customer_id'];
		$target = (int) $request['target_customer_id'];

		try {
			$moved = $this->merge_service->merge( $source, $target );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'woocommerce_rest_invalid_merge', $e->getMessage(), array( 'status' => 400 ) );
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'woocommerce_rest_merge_conflict', $e->getMessage(), array( 'status' => 409 ) );
		}

		return new WP_REST_Response(
			array(
				'target_customer_id' => $target,
				'moved'              => $moved,
			),
			200
		);
	}
}
