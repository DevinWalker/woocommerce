<?php
/**
 * REST API Customer View Notes controller.
 *
 * @package WooCommerce\RestApi
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\RestApi\Routes\V4\CustomerView;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\NotesRepository;
use Automattic\WooCommerce\Internal\RestApi\Routes\V4\AbstractController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Notes sub-resource for the customer-view namespace.
 *
 * Routes:
 *   GET    /wc/v4/customer-view/:customer_id/notes
 *   POST   /wc/v4/customer-view/:customer_id/notes
 *   PATCH  /wc/v4/customer-view/:customer_id/notes/:note_id   (author-only edit)
 *   DELETE /wc/v4/customer-view/:customer_id/notes/:note_id   (author or manage_woocommerce)
 */
class NotesController extends AbstractController {
	/**
	 * Route base (note-only piece; the customer_id is in the URL pattern).
	 *
	 * @var string
	 */
	protected $rest_base = 'customer-view';

	/**
	 * Notes repository.
	 *
	 * @var NotesRepository
	 */
	private NotesRepository $repo;

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param NotesRepository $repo Notes repository.
	 *
	 * @return void
	 */
	final public function init( NotesRepository $repo ): void {
		$this->repo = $repo;
	}

	/**
	 * Schema for a single note.
	 *
	 * @return array
	 */
	protected function get_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'customer-view-note',
			'type'       => 'object',
			'properties' => array(
				'note_id'     => array( 'type' => 'integer', 'readonly' => true ),
				'customer_id' => array( 'type' => 'integer', 'readonly' => true ),
				'author_id'   => array( 'type' => 'integer', 'readonly' => true ),
				'content'     => array( 'type' => 'string' ),
				'created_at'  => array( 'type' => 'string', 'readonly' => true ),
				'updated_at'  => array( 'type' => array( 'string', 'null' ), 'readonly' => true ),
			),
		);
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/notes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer', 'default' => 1 ),
						'per_page'    => array( 'type' => 'integer', 'default' => 25, 'maximum' => 100 ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
						'content'     => array( 'type' => 'string', 'required' => true ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/notes/(?P<note_id>[\d]+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
						'note_id'     => array( 'type' => 'integer' ),
						'content'     => array( 'type' => 'string', 'required' => true ),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'customer_id' => array( 'type' => 'integer' ),
						'note_id'     => array( 'type' => 'integer' ),
					),
				),
			)
		);
	}

	/**
	 * Pass-through item response for the base controller contract; not used by this
	 * route's handlers, which return their own shapes from the repository directly.
	 *
	 * @param array                                $item    Note row.
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return array
	 */
	protected function get_item_response( $item, WP_REST_Request $request ): array {
		unset( $request );
		return (array) $item;
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
	 * List notes for a customer.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$list = $this->repo->list_for_customer(
			(int) $request['customer_id'],
			(int) $request['page'],
			(int) $request['per_page']
		);
		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Create a note.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		$customer_id = (int) $request['customer_id'];
		$note_id     = $this->repo->add( $customer_id, get_current_user_id(), (string) $request['content'] );

		/**
		 * Fires after a customer note is added via REST.
		 *
		 * @since 10.9.0
		 *
		 * @param int $note_id     New note id.
		 * @param int $customer_id Customer id.
		 */
		do_action( 'woocommerce_customer_note_added', $note_id, $customer_id );

		return new WP_REST_Response( $this->repo->get( $note_id ), 201 );
	}

	/**
	 * Edit a note (author-only).
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$ok = $this->repo->edit( (int) $request['note_id'], get_current_user_id(), (string) $request['content'] );
		if ( ! $ok ) {
			return new WP_Error(
				'woocommerce_rest_cannot_edit',
				__( 'You can only edit your own notes.', 'woocommerce' ),
				array( 'status' => 403 )
			);
		}
		return new WP_REST_Response( $this->repo->get( (int) $request['note_id'] ), 200 );
	}

	/**
	 * Delete a note (author, or any user with manage_woocommerce — already required by permission_check).
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$ok = $this->repo->delete( (int) $request['note_id'], get_current_user_id(), true );
		if ( ! $ok ) {
			return new WP_Error(
				'woocommerce_rest_cannot_delete',
				__( 'Note not found.', 'woocommerce' ),
				array( 'status' => 404 )
			);
		}
		return new WP_REST_Response( null, 204 );
	}
}
