<?php
/**
 * REST API Customer View Schema
 *
 * @package WooCommerce\RestApi
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\RestApi\Routes\V4\CustomerView;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\Customers\CustomerRepository;
use Automattic\WooCommerce\Internal\Customers\TagsRepository;
use Automattic\WooCommerce\Internal\RestApi\Routes\V4\AbstractSchema;
use WP_REST_Request;

/**
 * Customer view schema — read-side representation of a wc_customer_lookup row,
 * enriched with lifecycle status, tags, notes_count, and merge state.
 */
class CustomerViewSchema extends AbstractSchema {
	/**
	 * The schema identifier.
	 *
	 * @var string
	 */
	const IDENTIFIER = 'customer-view';

	/**
	 * Dependency: tags repository.
	 *
	 * @var TagsRepository
	 */
	private TagsRepository $tags_repository;

	/**
	 * Container-driven dependency injection.
	 *
	 * @internal
	 *
	 * @param TagsRepository $tags_repository Tags repository.
	 *
	 * @return void
	 */
	final public function init( TagsRepository $tags_repository ): void {
		$this->tags_repository = $tags_repository;
	}

	/**
	 * Schema properties.
	 *
	 * @return array
	 */
	public function get_item_schema_properties(): array {
		return array(
			'id'                      => array(
				'description' => __( 'Unique identifier for the customer (wc_customer_lookup.customer_id).', 'woocommerce' ),
				'type'        => 'integer',
				'context'     => self::VIEW_EDIT_EMBED_CONTEXT,
				'readonly'    => true,
			),
			'user_id'                 => array(
				'description' => __( 'WP user id, or 0/null for guest customers.', 'woocommerce' ),
				'type'        => array( 'integer', 'null' ),
				'context'     => self::VIEW_EDIT_CONTEXT,
				'readonly'    => true,
			),
			'email'                   => array(
				'description' => __( 'Customer email.', 'woocommerce' ),
				'type'        => array( 'string', 'null' ),
				'context'     => self::VIEW_EDIT_CONTEXT,
				'readonly'    => true,
			),
			'first_name'              => array(
				'type'     => 'string',
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'last_name'               => array(
				'type'     => 'string',
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'username'                => array(
				'type'     => 'string',
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'date_registered'         => array(
				'type'     => array( 'string', 'null' ),
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'date_last_active'        => array(
				'type'     => array( 'string', 'null' ),
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'country'                 => array(
				'type'     => 'string',
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'postcode'                => array(
				'type'     => 'string',
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'city'                    => array(
				'type'     => 'string',
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'state'                   => array(
				'type'     => 'string',
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'lifecycle_status'        => array(
				'description' => __( 'Lifecycle status computed from purchase recency/frequency, or set manually.', 'woocommerce' ),
				'type'        => 'string',
				'enum'        => array( 'prospect', 'new', 'active', 'at-risk', 'dormant', 'merged' ),
				'context'     => self::VIEW_EDIT_CONTEXT,
				'readonly'    => true,
			),
			'lifecycle_overridden'    => array(
				'description' => __( 'True when an admin set lifecycle_status manually and the scheduler should not overwrite it.', 'woocommerce' ),
				'type'        => 'boolean',
				'context'     => self::VIEW_EDIT_CONTEXT,
				'readonly'    => true,
			),
			'created_via'             => array(
				'description' => __( 'How the customer record was first created.', 'woocommerce' ),
				'type'        => 'string',
				'enum'        => array( 'order', 'manual', 'merge' ),
				'context'     => self::VIEW_EDIT_CONTEXT,
				'readonly'    => true,
			),
			'merged_into_customer_id' => array(
				'description' => __( 'Set when this row has been merged into another; clients should redirect to that id.', 'woocommerce' ),
				'type'        => array( 'integer', 'null' ),
				'context'     => self::VIEW_EDIT_CONTEXT,
				'readonly'    => true,
			),
			'notes_count'             => array(
				'type'     => 'integer',
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'is_registered'           => array(
				'type'     => 'boolean',
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
			),
			'tags'                    => array(
				'type'     => 'array',
				'context'  => self::VIEW_EDIT_CONTEXT,
				'readonly' => true,
				'items'    => array(
					'type'       => 'object',
					'properties' => array(
						'tag_id' => array( 'type' => 'integer' ),
						'slug'   => array( 'type' => 'string' ),
						'name'   => array( 'type' => 'string' ),
						'color'  => array( 'type' => array( 'string', 'null' ) ),
					),
				),
			),
		);
	}

	/**
	 * Map a wc_customer_lookup row (plus tags) to the response shape.
	 *
	 * @param mixed                                $item    Row from wc_customer_lookup (associative array).
	 * @param WP_REST_Request<array<string,mixed>> $request Request object (unused; required by base contract).
	 * @param array                                $include_fields Field whitelist (unused for this read schema).
	 *
	 * @return array
	 */
	public function get_item_response( $item, WP_REST_Request $request, array $include_fields = array() ): array {
		unset( $request, $include_fields );

		$row = (array) $item;

		$tags = array_map(
			static fn( $t ) => array(
				'tag_id' => (int) $t['tag_id'],
				'slug'   => (string) $t['slug'],
				'name'   => (string) $t['name'],
				'color'  => isset( $t['color'] ) ? (string) $t['color'] : null,
			),
			$this->tags_repository->list_for_customer( (int) $row['customer_id'] )
		);

		return array(
			'id'                      => (int) $row['customer_id'],
			'user_id'                 => isset( $row['user_id'] ) ? (int) $row['user_id'] : null,
			'email'                   => $row['email'] ?? null,
			'first_name'              => (string) ( $row['first_name'] ?? '' ),
			'last_name'               => (string) ( $row['last_name'] ?? '' ),
			'username'                => (string) ( $row['username'] ?? '' ),
			'date_registered'         => $row['date_registered'] ?? null,
			'date_last_active'        => $row['date_last_active'] ?? null,
			'country'                 => (string) ( $row['country'] ?? '' ),
			'postcode'                => (string) ( $row['postcode'] ?? '' ),
			'city'                    => (string) ( $row['city'] ?? '' ),
			'state'                   => (string) ( $row['state'] ?? '' ),
			'lifecycle_status'        => (string) ( $row['lifecycle_status'] ?? 'new' ),
			'lifecycle_overridden'    => (bool) ( $row['lifecycle_overridden'] ?? 0 ),
			'created_via'             => (string) ( $row['created_via'] ?? 'order' ),
			'merged_into_customer_id' => isset( $row['merged_into_customer_id'] ) ? (int) $row['merged_into_customer_id'] : null,
			'notes_count'             => (int) ( $row['notes_count'] ?? 0 ),
			'is_registered'           => ! empty( $row['user_id'] ),
			'tags'                    => $tags,
		);
	}
}
