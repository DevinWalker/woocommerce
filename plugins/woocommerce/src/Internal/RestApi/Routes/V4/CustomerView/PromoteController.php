<?php
/**
 * REST API Customer View Promote controller.
 *
 * @package WooCommerce\RestApi
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\RestApi\Routes\V4\CustomerView;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\PromoteService;
use Automattic\WooCommerce\Internal\RestApi\Routes\V4\AbstractController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Promote endpoint:
 *   POST /wc/v4/customer-view/promote   { wp_user_id }
 */
class PromoteController extends AbstractController {
	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'customer-view';

	/**
	 * Promote service.
	 *
	 * @var PromoteService
	 */
	private PromoteService $promote_service;

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param PromoteService $promote_service Promote service.
	 *
	 * @return void
	 */
	final public function init( PromoteService $promote_service ): void {
		$this->promote_service = $promote_service;
	}

	/**
	 * Schema.
	 *
	 * @return array
	 */
	protected function get_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'customer-view-promote',
			'type'       => 'object',
			'properties' => array(
				'wp_user_id' => array( 'type' => 'integer' ),
				'id'         => array( 'type' => 'integer', 'readonly' => true ),
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
			'/' . $this->rest_base . '/promote',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'wp_user_id' => array( 'type' => 'integer', 'required' => true ),
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
	 * Promote a registered WP user into a customer-view record.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		try {
			$customer_id = $this->promote_service->promote( (int) $request['wp_user_id'] );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'woocommerce_rest_user_not_found', $e->getMessage(), array( 'status' => 404 ) );
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'woocommerce_rest_customer_exists', $e->getMessage(), array( 'status' => 409 ) );
		}

		return new WP_REST_Response( array( 'id' => $customer_id ), 201 );
	}
}
