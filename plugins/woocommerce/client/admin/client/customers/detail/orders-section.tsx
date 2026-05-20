/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { Customer } from '../data/types';

interface OrderSummary {
	id: number;
	number: string;
	status: string;
	total: string;
	currency: string;
	date_created: string;
}

const formatDate = ( iso: string ): string => {
	const t = Date.parse( iso );
	return Number.isFinite( t ) ? new Date( t ).toLocaleDateString() : iso;
};

export function OrdersSection( { customer }: { customer: Customer } ) {
	const [ orders, setOrders ] = useState< OrderSummary[] >( [] );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );

		const params: Record< string, string | number > = {
			per_page: 10,
			orderby: 'date',
			order: 'desc',
		};
		if ( customer.is_registered && customer.user_id > 0 ) {
			params.customer = customer.user_id;
		} else if ( customer.email ) {
			params.search = customer.email;
		}

		apiFetch< OrderSummary[] >( {
			path: addQueryArgs( '/wc/v3/orders', params ),
		} )
			.then( ( rows ) => {
				if ( ! cancelled ) {
					setOrders( Array.isArray( rows ) ? rows : [] );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setOrders( [] );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ customer.user_id, customer.email, customer.is_registered ] );

	let body;
	if ( loading ) {
		body = <p>{ __( 'Loading orders…', 'woocommerce' ) }</p>;
	} else if ( orders.length === 0 ) {
		body = (
			<p className="wc-customer-view__empty">
				{ __( 'No orders yet.', 'woocommerce' ) }
			</p>
		);
	} else {
		body = (
			<table className="wc-customer-view__table">
				<thead>
					<tr>
						<th>{ __( 'Order', 'woocommerce' ) }</th>
						<th>{ __( 'Date', 'woocommerce' ) }</th>
						<th>{ __( 'Status', 'woocommerce' ) }</th>
						<th>{ __( 'Total', 'woocommerce' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ orders.map( ( order ) => (
						<tr key={ order.id }>
							<td>
								<a
									href={ `post.php?post=${ order.id }&action=edit` }
								>
									#{ order.number || order.id }
								</a>
							</td>
							<td>{ formatDate( order.date_created ) }</td>
							<td>{ order.status }</td>
							<td>
								{ order.total } { order.currency }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		);
	}

	return (
		<section className="wc-customer-view__section wc-customer-view__orders">
			<h2>{ __( 'Orders', 'woocommerce' ) }</h2>
			{ body }
		</section>
	);
}
