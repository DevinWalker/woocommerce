/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { REST_NAMESPACE } from '../data/constants';

interface Sections {
	subscriptions?: boolean;
}

export function SubscriptionsSection( { customerId }: { customerId: number } ) {
	const [ enabled, setEnabled ] = useState< boolean | null >( null );

	useEffect( () => {
		let cancelled = false;
		if ( ! Number.isFinite( customerId ) || customerId <= 0 ) {
			setEnabled( false );
			return;
		}
		apiFetch< Sections >( {
			path: `${ REST_NAMESPACE }/${ customerId }/sections`,
		} )
			.then( ( res ) => {
				if ( ! cancelled ) {
					setEnabled( Boolean( res?.subscriptions ) );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setEnabled( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ customerId ] );

	if ( ! enabled ) {
		return null;
	}

	return (
		<section className="wc-customer-view__section wc-customer-view__subscriptions">
			<h2>{ __( 'Subscriptions', 'woocommerce' ) }</h2>
			<p className="wc-customer-view__empty">
				{ __(
					'Subscriptions module detected. Integration UI pending.',
					'woocommerce'
				) }
			</p>
		</section>
	);
}
