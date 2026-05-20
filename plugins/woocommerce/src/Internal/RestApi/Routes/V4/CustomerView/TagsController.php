<?php
/**
 * REST API Customer View Tags controller.
 *
 * @package WooCommerce\RestApi
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\RestApi\Routes\V4\CustomerView;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\TagsRepository;
use Automattic\WooCommerce\Internal\RestApi\Routes\V4\AbstractController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Tags sub-resource for the customer-view namespace, plus the global tag list.
 *
 * Routes:
 *   GET    /wc/v4/customer-view/:customer_id/tags
 *   POST   /wc/v4/customer-view/:customer_id/tags          { tag_id | slug, name?, color? }
 *   DELETE /wc/v4/customer-view/:customer_id/tags/:tag_id
 *   GET    /wc/v4/customer-tags
 */
class TagsController extends AbstractController {
	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'customer-view';

	/**
	 * Tags repository.
	 *
	 * @var TagsRepository
	 */
	private TagsRepository $repo;

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param TagsRepository $repo Tags repository.
	 *
	 * @return void
	 */
	final public function init( TagsRepository $repo ): void {
		$this->repo = $repo;
	}

	/**
	 * Schema for a single tag.
	 *
	 * @return array
	 */
	protected function get_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'customer-view-tag',
			'type'       => 'object',
			'properties' => array(
				'tag_id' => array( 'type' => 'integer', 'readonly' => true ),
				'slug'   => array( 'type' => 'string' ),
				'name'   => array( 'type' => 'string' ),
				'color'  => array( 'type' => array( 'string', 'null' ) ),
			),
		);
	}

	/**
	 * Pass-through item response (handlers return their own shapes).
	 *
	 * @param array                                $item    Tag row.
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
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'attach_or_create' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
						'tag_id'      => array( 'type' => 'integer' ),
						'slug'        => array( 'type' => 'string' ),
						'name'        => array( 'type' => 'string' ),
						'color'       => array( 'type' => 'string' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/tags/(?P<tag_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'detach' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
						'tag_id'      => array( 'type' => 'integer' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/customer-tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_all' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);
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
	 * List tags attached to a customer.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		return new WP_REST_Response( $this->repo->list_for_customer( (int) $request['customer_id'] ), 200 );
	}

	/**
	 * Attach an existing tag by id or by slug. When slug is given and no such tag
	 * exists, create it (using name/color) and attach. Returns the resulting tag row.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function attach_or_create( $request ) {
		$customer_id = (int) $request['customer_id'];
		$tag_id      = $request->get_param( 'tag_id' );
		$slug        = $request->get_param( 'slug' );
		$name        = $request->get_param( 'name' );
		$color       = $request->get_param( 'color' );

		if ( ! $tag_id && $slug ) {
			$existing = $this->repo->find_tag_by_slug( sanitize_title( (string) $slug ) );
			if ( $existing ) {
				$tag_id = (int) $existing['tag_id'];
			} else {
				try {
					$tag_id = $this->repo->create_tag(
						(string) ( $name ?? $slug ),
						(string) $slug,
						$color ? (string) $color : null,
						get_current_user_id()
					);
				} catch ( \RuntimeException $e ) {
					return new WP_Error( 'woocommerce_rest_tag_exists', $e->getMessage(), array( 'status' => 409 ) );
				}
			}
		}

		if ( ! $tag_id ) {
			return new WP_Error(
				'woocommerce_rest_invalid_tag',
				__( 'Provide tag_id or slug.', 'woocommerce' ),
				array( 'status' => 400 )
			);
		}

		$this->repo->attach( $customer_id, (int) $tag_id, get_current_user_id() );

		/**
		 * Fires after a tag is attached to a customer.
		 *
		 * @since 10.9.0
		 *
		 * @param int $customer_id Customer id.
		 * @param int $tag_id      Tag id.
		 */
		do_action( 'woocommerce_customer_tag_attached', $customer_id, (int) $tag_id );

		return new WP_REST_Response( $this->repo->get_tag( (int) $tag_id ), 201 );
	}

	/**
	 * Detach a tag from a customer.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function detach( $request ) {
		$customer_id = (int) $request['customer_id'];
		$tag_id      = (int) $request['tag_id'];
		$this->repo->detach( $customer_id, $tag_id );

		/**
		 * Fires after a tag is detached from a customer.
		 *
		 * @since 10.9.0
		 *
		 * @param int $customer_id Customer id.
		 * @param int $tag_id      Tag id.
		 */
		do_action( 'woocommerce_customer_tag_detached', $customer_id, $tag_id );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * List all tags in the global catalog.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request (unused).
	 *
	 * @return WP_REST_Response
	 */
	public function list_all( $request ) {
		unset( $request );
		return new WP_REST_Response( $this->repo->list_all(), 200 );
	}
}
