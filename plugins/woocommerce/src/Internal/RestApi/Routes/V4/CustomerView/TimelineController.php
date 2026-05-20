<?php
/**
 * REST API Customer View Timeline controller.
 *
 * @package WooCommerce\RestApi
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\RestApi\Routes\V4\CustomerView;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\TimelineAssembler;
use Automattic\WooCommerce\Internal\RestApi\Routes\V4\AbstractController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Activity timeline endpoint:
 *   GET /wc/v4/customer-view/:customer_id/timeline?page=1&per_page=25&types=note_added,payment_event
 */
class TimelineController extends AbstractController {
	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'customer-view';

	/**
	 * Timeline assembler.
	 *
	 * @var TimelineAssembler
	 */
	private TimelineAssembler $assembler;

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param TimelineAssembler $assembler Timeline service.
	 *
	 * @return void
	 */
	final public function init( TimelineAssembler $assembler ): void {
		$this->assembler = $assembler;
	}

	/**
	 * Schema for a single timeline event.
	 *
	 * @return array
	 */
	protected function get_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'customer-view-timeline-event',
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'string', 'readonly' => true ),
				'type'        => array( 'type' => 'string', 'readonly' => true ),
				'occurred_at' => array( 'type' => 'string', 'readonly' => true ),
				'actor'       => array( 'type' => 'integer', 'readonly' => true ),
				'payload'     => array( 'type' => array( 'string', 'object', 'array', 'null' ), 'readonly' => true ),
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
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/timeline',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer', 'default' => 1 ),
						'per_page'    => array( 'type' => 'integer', 'default' => 25, 'maximum' => 100 ),
						'types'       => array( 'type' => 'string', 'description' => 'Comma-separated event types to include.' ),
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
	 * Return the assembled timeline.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$types = $request->get_param( 'types' );
		$args  = array(
			'page'     => (int) $request['page'],
			'per_page' => (int) $request['per_page'],
		);
		if ( is_string( $types ) && '' !== $types ) {
			$args['types'] = array_map( 'trim', explode( ',', $types ) );
		}

		$events = $this->assembler->assemble( (int) $request['customer_id'], $args );
		return new WP_REST_Response( $events, 200 );
	}
}
