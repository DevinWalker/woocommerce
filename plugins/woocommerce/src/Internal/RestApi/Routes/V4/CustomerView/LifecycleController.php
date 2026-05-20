<?php
/**
 * REST API Customer View Lifecycle controller.
 *
 * @package WooCommerce\RestApi
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\RestApi\Routes\V4\CustomerView;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\LifecycleCalculator;
use Automattic\WooCommerce\Internal\RestApi\Routes\V4\AbstractController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Lifecycle override endpoints.
 *
 * Routes:
 *   POST   /wc/v4/customer-view/:customer_id/lifecycle           { status }
 *   DELETE /wc/v4/customer-view/:customer_id/lifecycle/override
 */
class LifecycleController extends AbstractController {
	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'customer-view';

	/**
	 * Lifecycle calculator (used when clearing override).
	 *
	 * @var LifecycleCalculator
	 */
	private LifecycleCalculator $lifecycle;

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param LifecycleCalculator $lifecycle Lifecycle service.
	 *
	 * @return void
	 */
	final public function init( LifecycleCalculator $lifecycle ): void {
		$this->lifecycle = $lifecycle;
	}

	/**
	 * Schema.
	 *
	 * @return array
	 */
	protected function get_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'customer-view-lifecycle',
			'type'       => 'object',
			'properties' => array(
				'lifecycle_status'     => array(
					'type' => 'string',
					'enum' => array( 'prospect', 'new', 'active', 'at-risk', 'dormant' ),
				),
				'lifecycle_overridden' => array( 'type' => 'boolean', 'readonly' => true ),
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
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/lifecycle',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_lifecycle' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
						'status'      => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array( 'prospect', 'new', 'active', 'at-risk', 'dormant' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/lifecycle/override',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_override' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
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
	 * Set lifecycle status manually and mark as overridden.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_lifecycle( $request ) {
		global $wpdb;

		$customer_id = (int) $request['customer_id'];
		$status      = (string) $request['status'];

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$customer_id
			)
		);
		if ( ! $exists ) {
			return new WP_Error(
				'woocommerce_rest_customer_not_found',
				__( 'Customer not found.', 'woocommerce' ),
				array( 'status' => 404 )
			);
		}

		$old = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT lifecycle_status FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$customer_id
			)
		);

		$wpdb->update(
			$wpdb->prefix . 'wc_customer_lookup',
			array(
				'lifecycle_status'     => $status,
				'lifecycle_overridden' => 1,
			),
			array( 'customer_id' => $customer_id )
		);

		/**
		 * Fires after an admin manually sets a customer's lifecycle status.
		 *
		 * @since 10.9.0
		 *
		 * @param int    $customer_id Customer id.
		 * @param string $old         Previous status.
		 * @param string $new         New status.
		 * @param string $source      Always 'manual' for this endpoint.
		 */
		do_action( 'woocommerce_customer_lifecycle_changed', $customer_id, (string) $old, $status, 'manual' );

		return new WP_REST_Response(
			array(
				'lifecycle_status'     => $status,
				'lifecycle_overridden' => true,
			),
			200
		);
	}

	/**
	 * Clear the lifecycle override and recompute from data.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function clear_override( $request ) {
		global $wpdb;
		$customer_id = (int) $request['customer_id'];

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$customer_id
			)
		);
		if ( ! $exists ) {
			return new WP_Error(
				'woocommerce_rest_customer_not_found',
				__( 'Customer not found.', 'woocommerce' ),
				array( 'status' => 404 )
			);
		}

		$wpdb->update(
			$wpdb->prefix . 'wc_customer_lookup',
			array( 'lifecycle_overridden' => 0 ),
			array( 'customer_id' => $customer_id )
		);

		$this->lifecycle->recompute_and_persist( $customer_id );

		return new WP_REST_Response( null, 204 );
	}
}
