<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

/**
 * Merge one customer record into another. Used when the same person appears
 * as both a guest record and a registered record, or as two distinct guest
 * emails that should be consolidated.
 *
 * Merge is one-way in v1 — the source row is annotated with a pointer to the
 * target (`merged_into_customer_id`) and `lifecycle_status = 'merged'`, but
 * is not deleted (so REST GET on the old id can serve a redirect).
 *
 * Resolved by the WooCommerce reflection-based container; no explicit registration needed.
 */
final class MergeService {

	/**
	 * Dependency: lifecycle calculator (used to refresh the target after the merge).
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
	 * Merge `$source_id` into `$target_id`. Returns a summary of moved row counts.
	 *
	 * Moves notes, payment events, tag relationships (deduplicated), and
	 * order rows (wc_order_stats and, when present, wc_orders/HPOS). Updates
	 * the source row with `merged_into_customer_id` and `lifecycle_status='merged'`.
	 *
	 * Runs inside a single DB transaction. Fires
	 * `woocommerce_customer_merged( $source_id, $target_id )` on success.
	 *
	 * @param int $source_id Customer to be merged away.
	 * @param int $target_id Customer that receives the data.
	 *
	 * @return array{notes:int, payment_events:int, tags:int, orders:int}
	 *
	 * @throws \InvalidArgumentException When source == target, or either is missing.
	 * @throws \RuntimeException When the source is already merged.
	 */
	public function merge( int $source_id, int $target_id ): array {
		if ( $source_id === $target_id ) {
			throw new \InvalidArgumentException( 'Cannot merge a customer into itself' );
		}

		global $wpdb;

		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$source_id
			),
			ARRAY_A
		);
		$target = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
				$target_id
			),
			ARRAY_A
		);

		if ( ! $source || ! $target ) {
			throw new \InvalidArgumentException( 'Both source and target customers must exist' );
		}
		if ( ! empty( $source['merged_into_customer_id'] ) ) {
			throw new \RuntimeException( 'Source customer is already merged' );
		}

		$wpdb->query( 'START TRANSACTION' );

		$moved = array(
			'notes'          => (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wc_customer_notes SET customer_id = %d WHERE customer_id = %d",
					$target_id,
					$source_id
				)
			),
			'payment_events' => (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wc_customer_payment_events SET customer_id = %d WHERE customer_id = %d",
					$target_id,
					$source_id
				)
			),
		);

		// Tag relationships: insert ignore into target, then drop from source. The composite PK on
		// (customer_id, tag_id) makes the insert deduplicate naturally.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}wc_customer_tag_relationships
				 (customer_id, tag_id, assigned_at, assigned_by)
				 SELECT %d, tag_id, assigned_at, assigned_by
				 FROM {$wpdb->prefix}wc_customer_tag_relationships WHERE customer_id = %d",
				$target_id,
				$source_id
			)
		);
		$moved['tags'] = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wc_customer_tag_relationships WHERE customer_id = %d",
				$source_id
			)
		);

		// Order reassignment: analytics (wc_order_stats) and HPOS (wc_orders) when present.
		$moved['orders'] = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}wc_order_stats SET customer_id = %d WHERE customer_id = %d",
				$target_id,
				$source_id
			)
		);
		if ( $this->hpos_orders_table_exists() ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wc_orders SET customer_id = %d WHERE customer_id = %d",
					$target_id,
					$source_id
				)
			);
		}

		// Annotate the source row.
		$wpdb->update(
			$wpdb->prefix . 'wc_customer_lookup',
			array(
				'merged_into_customer_id' => $target_id,
				'notes_count'             => 0,
				'lifecycle_status'        => 'merged',
			),
			array( 'customer_id' => $source_id )
		);

		// Refresh target's notes_count after the move.
		$target_notes_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_customer_notes WHERE customer_id = %d",
				$target_id
			)
		);
		$wpdb->update(
			$wpdb->prefix . 'wc_customer_lookup',
			array( 'notes_count' => $target_notes_count ),
			array( 'customer_id' => $target_id )
		);

		$wpdb->query( 'COMMIT' );

		// Recompute lifecycle on the target now that it owns more orders.
		$this->lifecycle->recompute_and_persist( $target_id );

		do_action( 'woocommerce_customer_merged', $source_id, $target_id );

		return $moved;
	}

	/**
	 * True when the HPOS `wc_orders` table is present.
	 *
	 * @return bool
	 */
	private function hpos_orders_table_exists(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_orders';
		// SHOW TABLES LIKE does not support table-name placeholders; $table is built from $wpdb->prefix.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}
}
