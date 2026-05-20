<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

/**
 * Internal repository for admin notes on customers. Stored in `wc_customer_notes`
 * and denormalized onto `wc_customer_lookup.notes_count` for cheap list rendering.
 *
 * Resolved by the WooCommerce reflection-based container; no explicit registration needed.
 */
final class NotesRepository {

	/**
	 * Add a note. Returns the new note id.
	 *
	 * Content is sanitized with `wp_kses_post()` so basic HTML formatting is
	 * allowed while scripts are stripped.
	 *
	 * @param int    $customer_id Customer id from wc_customer_lookup.
	 * @param int    $author_id   WP user id of the admin authoring the note.
	 * @param string $content     Note body.
	 *
	 * @return int The new note id.
	 */
	public function add( int $customer_id, int $author_id, string $content ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wc_customer_notes',
			array(
				'customer_id' => $customer_id,
				'author_id'   => $author_id,
				'content'     => wp_kses_post( $content ),
				'created_at'  => current_time( 'mysql', 1 ),
			)
		);
		$id = (int) $wpdb->insert_id;
		$this->refresh_notes_count( $customer_id );
		return $id;
	}

	/**
	 * Edit a note. Only the original author may edit. Returns true on success,
	 * false when the note does not exist or the editor is not the author.
	 *
	 * @param int    $note_id   Note id.
	 * @param int    $editor_id WP user id attempting the edit.
	 * @param string $content   Replacement body.
	 *
	 * @return bool
	 */
	public function edit( int $note_id, int $editor_id, string $content ): bool {
		global $wpdb;
		$row = $this->get( $note_id );
		if ( ! $row || (int) $row['author_id'] !== $editor_id ) {
			return false;
		}
		$wpdb->update(
			$wpdb->prefix . 'wc_customer_notes',
			array(
				'content'    => wp_kses_post( $content ),
				'updated_at' => current_time( 'mysql', 1 ),
			),
			array( 'note_id' => $note_id )
		);
		return true;
	}

	/**
	 * Delete a note. Permitted for the original author, or for any caller
	 * flagged as `$is_admin` (callers should gate that on `manage_woocommerce`).
	 *
	 * @param int  $note_id    Note id.
	 * @param int  $deleter_id WP user id attempting the delete.
	 * @param bool $is_admin   True if the caller has manage_woocommerce.
	 *
	 * @return bool
	 */
	public function delete( int $note_id, int $deleter_id, bool $is_admin ): bool {
		global $wpdb;
		$row = $this->get( $note_id );
		if ( ! $row ) {
			return false;
		}
		if ( ! $is_admin && (int) $row['author_id'] !== $deleter_id ) {
			return false;
		}
		$wpdb->delete( $wpdb->prefix . 'wc_customer_notes', array( 'note_id' => $note_id ) );
		$this->refresh_notes_count( (int) $row['customer_id'] );
		return true;
	}

	/**
	 * Fetch a single note by id.
	 *
	 * @param int $note_id Note id.
	 *
	 * @return array|null
	 */
	public function get( int $note_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_customer_notes WHERE note_id = %d",
				$note_id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * List notes for a customer, newest first.
	 *
	 * @param int $customer_id Customer id.
	 * @param int $page        1-based page number.
	 * @param int $per_page    Page size (clamped server-side in the REST layer).
	 *
	 * @return array
	 */
	public function list_for_customer( int $customer_id, int $page = 1, int $per_page = 25 ): array {
		global $wpdb;
		$offset = max( 0, ( $page - 1 ) * $per_page );
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_customer_notes WHERE customer_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$customer_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Count notes for a customer.
	 *
	 * @param int $customer_id Customer id.
	 *
	 * @return int
	 */
	public function count_for_customer( int $customer_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_customer_notes WHERE customer_id = %d",
				$customer_id
			)
		);
	}

	/**
	 * Recompute and persist `wc_customer_lookup.notes_count` for the customer.
	 *
	 * @param int $customer_id Customer id.
	 *
	 * @return void
	 */
	private function refresh_notes_count( int $customer_id ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'wc_customer_lookup',
			array( 'notes_count' => $this->count_for_customer( $customer_id ) ),
			array( 'customer_id' => $customer_id )
		);
	}
}
