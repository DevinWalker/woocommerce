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
use Automattic\WooCommerce\Internal\Customers\TagsRepository;
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
	 * Tags repository (used for the global tags list endpoint).
	 *
	 * @var TagsRepository
	 */
	private TagsRepository $tags_repository;

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param CustomerViewSchema $item_schema         Schema instance.
	 * @param CustomerRepository $customer_repository Customer repository.
	 * @param TagsRepository     $tags_repository     Tags repository.
	 *
	 * @return void
	 */
	final public function init(
		CustomerViewSchema $item_schema,
		CustomerRepository $customer_repository,
		TagsRepository $tags_repository
	): void {
		$this->item_schema         = $item_schema;
		$this->customer_repository = $customer_repository;
		$this->tags_repository     = $tags_repository;
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
		// Collection / search — used by the merge modal target picker.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'search'   => array( 'type' => 'string', 'required' => false ),
						'per_page' => array( 'type' => 'integer', 'default' => 10 ),
					),
				),
			)
		);

		// Global tags list — used by the tag picker modal.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tags' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);

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

		// Feature-detection for optional sections (subscriptions, etc).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/sections',
			array(
				'args' => array(
					'customer_id' => array( 'type' => 'integer' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sections' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);
	}

	/**
	 * Search customers by email/name/username.
	 *
	 * Used by the merge modal target picker. Limited result set; not a
	 * general-purpose listing.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		global $wpdb;

		$search   = trim( (string) ( $request['search'] ?? '' ) );
		$per_page = max( 1, min( 50, (int) ( $request['per_page'] ?? 10 ) ) );

		if ( '' === $search ) {
			return new WP_REST_Response( array(), 200 );
		}

		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT customer_id, user_id, email, first_name, last_name, username
				 FROM {$wpdb->prefix}wc_customer_lookup
				 WHERE merged_into_customer_id IS NULL
				   AND (
					email LIKE %s
					OR first_name LIKE %s
					OR last_name LIKE %s
					OR username LIKE %s
				   )
				 LIMIT %d",
				$like,
				$like,
				$like,
				$like,
				$per_page
			),
			ARRAY_A
		) ?: array();

		$out = array_map(
			static fn( $r ) => array(
				'id'         => (int) $r['customer_id'],
				'user_id'    => isset( $r['user_id'] ) ? (int) $r['user_id'] : 0,
				'email'      => (string) ( $r['email'] ?? '' ),
				'first_name' => (string) ( $r['first_name'] ?? '' ),
				'last_name'  => (string) ( $r['last_name'] ?? '' ),
				'username'   => (string) ( $r['username'] ?? '' ),
			),
			$rows
		);

		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * List all known tags.
	 *
	 * @return WP_REST_Response
	 */
	public function get_tags() {
		$tags = array_map(
			static fn( $t ) => array(
				'tag_id' => (int) $t['tag_id'],
				'slug'   => (string) $t['slug'],
				'name'   => (string) $t['name'],
				'color'  => isset( $t['color'] ) ? (string) $t['color'] : null,
			),
			$this->tags_repository->list_all()
		);
		return new WP_REST_Response( $tags, 200 );
	}

	/**
	 * Report which optional sections (e.g. subscriptions) are available on
	 * this site so the React UI can render conditionally.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_sections( $request ) {
		$customer_id = (int) $request['customer_id'];
		$sections    = array(
			'subscriptions' => class_exists( '\\WC_Subscriptions' ),
		);
		/**
		 * Filter the per-customer section availability map.
		 *
		 * Extensions can advertise their own sections so the React detail page
		 * decides whether to render their `<Fill>` slots.
		 *
		 * @since 10.9.0
		 *
		 * @param array $sections    Section availability map (slug => bool).
		 * @param int   $customer_id Customer id.
		 */
		$sections = (array) apply_filters( 'woocommerce_customer_view_sections', $sections, $customer_id );
		return new WP_REST_Response( $sections, 200 );
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
