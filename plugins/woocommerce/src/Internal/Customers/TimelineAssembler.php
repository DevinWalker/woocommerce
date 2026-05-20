<?php

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Customers;

defined( 'ABSPATH' ) || exit;

/**
 * Assemble a unified customer activity timeline from notes, payment events,
 * and orders. Extensions can append further events via the
 * `woocommerce_customer_timeline_events` filter.
 *
 * Resolved by the WooCommerce reflection-based container; no explicit registration needed.
 */
final class TimelineAssembler {

	/**
	 * Build the merged timeline for a customer, newest first.
	 *
	 * Optional `$args` keys:
	 * - `page`     (int, 1-based, default 1)
	 * - `per_page` (int, default 25, max 100)
	 * - `types`    (array of strings) – restrict to these event types
	 *
	 * Each returned event has: `id`, `type`, `occurred_at`, `actor`, `payload`.
	 *
	 * @param int   $customer_id Customer id.
	 * @param array $args        Pagination and filtering.
	 *
	 * @return array
	 */
	public function assemble( int $customer_id, array $args ): array {
		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 25 ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$types    = $args['types'] ?? null;

		$notes_table  = $wpdb->prefix . 'wc_customer_notes';
		$pay_table    = $wpdb->prefix . 'wc_customer_payment_events';
		$orders_table = $wpdb->prefix . 'wc_order_stats';

		// Build the union once, parameterized on $customer_id three times.
		$sql = $wpdb->prepare(
			"SELECT * FROM (
				(SELECT
					CONCAT('note_', note_id) AS id,
					'note_added' AS type,
					created_at AS occurred_at,
					author_id AS actor,
					content AS payload
				 FROM {$notes_table}
				 WHERE customer_id = %d)
				UNION ALL
				(SELECT
					CONCAT('pay_', event_id) AS id,
					'payment_event' AS type,
					created_at AS occurred_at,
					0 AS actor,
					CONCAT_WS('|', type, amount, currency, gateway, status, IFNULL(external_id, ''), order_id) AS payload
				 FROM {$pay_table}
				 WHERE customer_id = %d)
				UNION ALL
				(SELECT
					CONCAT('order_', order_id) AS id,
					'order_placed' AS type,
					date_created AS occurred_at,
					0 AS actor,
					CONCAT_WS('|', order_id, status, total_sales) AS payload
				 FROM {$orders_table}
				 WHERE customer_id = %d)
			) AS t
			ORDER BY occurred_at DESC
			LIMIT %d OFFSET %d",
			$customer_id,
			$customer_id,
			$customer_id,
			$per_page,
			$offset
		);

		$rows = (array) $wpdb->get_results( $sql, ARRAY_A );

		$events = array_map(
			static fn( $r ) => array(
				'id'          => (string) $r['id'],
				'type'        => (string) $r['type'],
				'occurred_at' => (string) $r['occurred_at'],
				'actor'       => (int) $r['actor'],
				'payload'     => $r['payload'],
			),
			$rows
		);

		if ( $types ) {
			$events = array_values(
				array_filter(
					$events,
					static fn( $e ) => in_array( $e['type'], (array) $types, true )
				)
			);
		}

		/**
		 * Filter the assembled timeline events. Extensions append their own.
		 *
		 * @since 10.9.0
		 *
		 * @param array $events      Current events (each: id, type, occurred_at, actor, payload).
		 * @param int   $customer_id Customer id.
		 * @param array $args        Original query args.
		 */
		$events = (array) apply_filters( 'woocommerce_customer_timeline_events', $events, $customer_id, $args );

		// Re-sort after extension append so injected events land in the right position.
		usort( $events, static fn( $a, $b ) => strcmp( (string) $b['occurred_at'], (string) $a['occurred_at'] ) );

		return $events;
	}
}
