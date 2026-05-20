<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

/**
 * Internal repository for customer tags (global catalog) and per-customer
 * tag relationships. Backs the tag chips on the customer view and the tag
 * filter on the analytics list.
 *
 * Resolved by the WooCommerce reflection-based container; no explicit registration needed.
 */
final class TagsRepository {

	/**
	 * Create a new global tag. Returns the new tag id.
	 *
	 * If `$slug` is null, derives one from `$name` via `sanitize_title()`.
	 * Throws `RuntimeException` if the slug already exists.
	 *
	 * @param string      $name      Display name.
	 * @param string|null $slug      URL-safe slug, or null to derive from $name.
	 * @param string|null $color     Hex color (e.g. "#ff0000"), or null.
	 * @param int         $author_id WP user id of the creator.
	 *
	 * @return int The new tag id.
	 *
	 * @throws \RuntimeException When the slug already exists.
	 */
	public function create_tag( string $name, ?string $slug, ?string $color, int $author_id ): int {
		unset( $author_id ); // Reserved for future audit support; not yet persisted.
		global $wpdb;
		$slug = $slug ? sanitize_title( $slug ) : sanitize_title( $name );
		if ( $this->find_tag_by_slug( $slug ) ) {
			throw new \RuntimeException( "Tag slug '{$slug}' already exists" );
		}
		$wpdb->insert(
			$wpdb->prefix . 'wc_customer_tags',
			array(
				'slug'       => $slug,
				'name'       => $name,
				'color'      => $color,
				'created_at' => current_time( 'mysql', 1 ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a tag's name and/or color.
	 *
	 * @param int   $tag_id  Tag id.
	 * @param array $changes Subset of { name, color }.
	 *
	 * @return bool True when a row was updated.
	 */
	public function update_tag( int $tag_id, array $changes ): bool {
		global $wpdb;
		$allowed = array_intersect_key( $changes, array_flip( array( 'name', 'color' ) ) );
		if ( empty( $allowed ) ) {
			return false;
		}
		return (bool) $wpdb->update(
			$wpdb->prefix . 'wc_customer_tags',
			$allowed,
			array( 'tag_id' => $tag_id )
		);
	}

	/**
	 * Delete a tag and remove any relationships referencing it (cascading).
	 *
	 * @param int $tag_id Tag id.
	 *
	 * @return bool True when the tag was deleted.
	 */
	public function delete_tag( int $tag_id ): bool {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$wpdb->delete( $wpdb->prefix . 'wc_customer_tag_relationships', array( 'tag_id' => $tag_id ) );
		$rows = $wpdb->delete( $wpdb->prefix . 'wc_customer_tags', array( 'tag_id' => $tag_id ) );
		$wpdb->query( 'COMMIT' );
		return (bool) $rows;
	}

	/**
	 * Fetch a single tag by id.
	 *
	 * @param int $tag_id Tag id.
	 *
	 * @return array|null
	 */
	public function get_tag( int $tag_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_customer_tags WHERE tag_id = %d",
				$tag_id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Fetch a tag by slug.
	 *
	 * @param string $slug Tag slug.
	 *
	 * @return array|null
	 */
	public function find_tag_by_slug( string $slug ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_customer_tags WHERE slug = %s",
				$slug
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * List all tags in the catalog, ordered by name.
	 *
	 * @return array
	 */
	public function list_all(): array {
		global $wpdb;
		return (array) $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}wc_customer_tags ORDER BY name ASC",
			ARRAY_A
		);
	}

	/**
	 * Attach a tag to a customer. Idempotent: a duplicate attach is a no-op.
	 *
	 * @param int $customer_id Customer id.
	 * @param int $tag_id      Tag id.
	 * @param int $assigned_by WP user id of the assigner.
	 *
	 * @return void
	 */
	public function attach( int $customer_id, int $tag_id, int $assigned_by ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}wc_customer_tag_relationships
				 (customer_id, tag_id, assigned_at, assigned_by) VALUES (%d, %d, %s, %d)",
				$customer_id,
				$tag_id,
				current_time( 'mysql', 1 ),
				$assigned_by
			)
		);
	}

	/**
	 * Detach a tag from a customer. No-op if not currently attached.
	 *
	 * @param int $customer_id Customer id.
	 * @param int $tag_id      Tag id.
	 *
	 * @return void
	 */
	public function detach( int $customer_id, int $tag_id ): void {
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'wc_customer_tag_relationships',
			array(
				'customer_id' => $customer_id,
				'tag_id'      => $tag_id,
			)
		);
	}

	/**
	 * List tags attached to a customer, ordered by name.
	 *
	 * @param int $customer_id Customer id.
	 *
	 * @return array
	 */
	public function list_for_customer( int $customer_id ): array {
		global $wpdb;
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.* FROM {$wpdb->prefix}wc_customer_tags t
				 JOIN {$wpdb->prefix}wc_customer_tag_relationships r ON r.tag_id = t.tag_id
				 WHERE r.customer_id = %d ORDER BY t.name ASC",
				$customer_id
			),
			ARRAY_A
		);
	}
}
