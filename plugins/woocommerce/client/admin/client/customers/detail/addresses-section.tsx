/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { Customer } from '../data/types';

interface Address {
	first_name?: string;
	last_name?: string;
	company?: string;
	address_1?: string;
	address_2?: string;
	city?: string;
	state?: string;
	postcode?: string;
	country?: string;
	email?: string;
	phone?: string;
}

const formatAddress = ( a: Address ): string[] =>
	[
		[ a.first_name, a.last_name ].filter( Boolean ).join( ' ' ),
		a.company ?? '',
		a.address_1 ?? '',
		a.address_2 ?? '',
		[ a.city, a.state, a.postcode ].filter( Boolean ).join( ', ' ),
		a.country ?? '',
	].filter( ( line ) => line.trim() !== '' );

interface CustomerWithAddresses {
	billing?: Address;
	shipping?: Address;
}

export function AddressesSection( { customer }: { customer: Customer } ) {
	const [ billing, setBilling ] = useState< Address | null >( null );
	const [ shipping, setShipping ] = useState< Address | null >( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );

		if ( customer.is_registered && customer.user_id > 0 ) {
			apiFetch< CustomerWithAddresses >( {
				path: `/wc/v3/customers/${ customer.user_id }`,
			} )
				.then( ( row ) => {
					if ( cancelled ) {
						return;
					}
					setBilling( row.billing ?? null );
					setShipping( row.shipping ?? null );
				} )
				.catch( () => {
					if ( cancelled ) {
						return;
					}
					setBilling( null );
					setShipping( null );
				} )
				.finally( () => {
					if ( ! cancelled ) {
						setLoading( false );
					}
				} );
		} else {
			setLoading( false );
		}

		return () => {
			cancelled = true;
		};
	}, [ customer.user_id, customer.is_registered ] );

	if ( loading ) {
		return (
			<section className="wc-customer-view__section">
				<h2>{ __( 'Addresses', 'woocommerce' ) }</h2>
				<p>{ __( 'Loading addresses…', 'woocommerce' ) }</p>
			</section>
		);
	}

	return (
		<section className="wc-customer-view__section wc-customer-view__addresses">
			<h2>{ __( 'Addresses', 'woocommerce' ) }</h2>
			<div className="wc-customer-view__address-grid">
				<div className="wc-customer-view__address">
					<h3>{ __( 'Billing', 'woocommerce' ) }</h3>
					{ billing && formatAddress( billing ).length > 0 ? (
						<address>
							{ formatAddress( billing ).map( ( line, i ) => (
								<div key={ i }>{ line }</div>
							) ) }
						</address>
					) : (
						<p className="wc-customer-view__empty">
							{ __(
								'No billing address on file.',
								'woocommerce'
							) }
						</p>
					) }
				</div>
				<div className="wc-customer-view__address">
					<h3>{ __( 'Shipping', 'woocommerce' ) }</h3>
					{ shipping && formatAddress( shipping ).length > 0 ? (
						<address>
							{ formatAddress( shipping ).map( ( line, i ) => (
								<div key={ i }>{ line }</div>
							) ) }
						</address>
					) : (
						<p className="wc-customer-view__empty">
							{ __(
								'No shipping address on file.',
								'woocommerce'
							) }
						</p>
					) }
				</div>
			</div>
		</section>
	);
}
