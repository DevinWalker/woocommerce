/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../data/store';
import type { PaymentEvent } from '../data/types';

const formatDate = ( iso: string ): string => {
	const t = Date.parse( iso );
	return Number.isFinite( t ) ? new Date( t ).toLocaleString() : iso;
};

export function PaymentEventsSection( { customerId }: { customerId: number } ) {
	const events = useSelect(
		( select ) =>
			select( STORE_NAME ).getPaymentEvents(
				customerId
			) as PaymentEvent[],
		[ customerId ]
	);

	return (
		<section className="wc-customer-view__section wc-customer-view__payment-events">
			<h2>{ __( 'Payment events', 'woocommerce' ) }</h2>
			{ events.length === 0 ? (
				<p className="wc-customer-view__empty">
					{ __( 'No payment events recorded.', 'woocommerce' ) }
				</p>
			) : (
				<table className="wc-customer-view__table">
					<thead>
						<tr>
							<th>{ __( 'Date', 'woocommerce' ) }</th>
							<th>{ __( 'Type', 'woocommerce' ) }</th>
							<th>{ __( 'Amount', 'woocommerce' ) }</th>
							<th>{ __( 'Gateway', 'woocommerce' ) }</th>
							<th>{ __( 'Status', 'woocommerce' ) }</th>
							<th>{ __( 'Order', 'woocommerce' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ events.map( ( evt ) => (
							<tr key={ evt.event_id }>
								<td>{ formatDate( evt.created_at ) }</td>
								<td>
									<span
										className={ `wc-customer-view__pe-type wc-customer-view__pe-type--${ evt.type }` }
									>
										{ evt.type }
									</span>
								</td>
								<td>
									{ evt.amount } { evt.currency }
								</td>
								<td>{ evt.gateway }</td>
								<td>{ evt.status }</td>
								<td>
									<a
										href={ `post.php?post=${ evt.order_id }&action=edit` }
									>
										#{ evt.order_id }
									</a>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</section>
	);
}
