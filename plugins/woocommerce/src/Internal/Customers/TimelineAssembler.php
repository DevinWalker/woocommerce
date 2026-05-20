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

		// Fetch each source separately and merge in PHP. This avoids cross-DB
		// quirks with UNION + CONCAT_WS on the SQLite drop-in used by Studio
		// and Playground.
		$notes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT note_id, created_at, author_id, content
				 FROM {$notes_table}
				 WHERE customer_id = %d",
				$customer_id
			),
			ARRAY_A
		) ?: array();

		$payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_id, created_at, type, amount, currency, gateway, status, external_id, order_id
				 FROM {$pay_table}
				 WHERE customer_id = %d",
				$customer_id
			),
			ARRAY_A
		) ?: array();

		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_id, date_created, status, total_sales
				 FROM {$orders_table}
				 WHERE customer_id = %d
				   AND parent_id = 0",
				$customer_id
			),
			ARRAY_A
		) ?: array();

		$events = array();

		foreach ( $notes as $n ) {
			$events[] = array(
				'id'          => 'note_' . (int) $n['note_id'],
				'type'        => 'note_added',
				'occurred_at' => (string) $n['created_at'],
				'actor'       => (int) $n['author_id'],
				'payload'     => array(
					'content' => (string) $n['content'],
				),
			);
		}

		foreach ( $payments as $p ) {
			$events[] = array(
				'id'          => 'pay_' . (int) $p['event_id'],
				'type'        => 'payment_event',
				'occurred_at' => (string) $p['created_at'],
				'actor'       => 0,
				'payload'     => array(
					'event_type'  => (string) $p['type'],
					'amount'      => (string) $p['amount'],
					'currency'    => (string) $p['currency'],
					'gateway'     => (string) $p['gateway'],
					'status'      => (string) $p['status'],
					'external_id' => isset( $p['external_id'] ) ? (string) $p['external_id'] : '',
					'order_id'    => (int) $p['order_id'],
				),
			);
		}

		foreach ( $orders as $o ) {
			$events[] = array(
				'id'          => 'order_' . (int) $o['order_id'],
				'type'        => 'order_placed',
				'occurred_at' => (string) $o['date_created'],
				'actor'       => 0,
				'payload'     => array(
					'order_id'    => (int) $o['order_id'],
					'status'      => (string) $o['status'],
					'total_sales' => (string) $o['total_sales'],
				),
			);
		}

		if ( $types ) {
			$events = array_values(
				array_filter(
					$events,
					static fn( $e ) => in_array( $e['type'], (array) $types, true )
				)
			);
		}

		// Sort by occurred_at DESC, then trim to the requested page window.
		usort( $events, static fn( $a, $b ) => strcmp( $b['occurred_at'], $a['occurred_at'] ) );
		$events = array_slice( $events, $offset, $per_page );

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
