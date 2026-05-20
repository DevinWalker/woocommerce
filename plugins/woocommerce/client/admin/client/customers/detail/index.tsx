/**
 * External dependencies
 */
import { Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { registerCustomerViewStore, STORE_NAME } from '../data/store';
import type { Customer } from '../data/types';

registerCustomerViewStore();

type Props = {
	params: { id: string };
};

export default function CustomerDetail( { params }: Props ) {
	const customerId = Number.parseInt( params.id, 10 );

	const customer = useSelect(
		( select ) =>
			select( STORE_NAME ).getCustomer( customerId ) as Customer | null,
		[ customerId ]
	);

	const isResolving = useSelect(
		( select ) =>
			select( STORE_NAME ).isResolving( 'getCustomer', [
				customerId,
			] ) as boolean,
		[ customerId ]
	);

	if ( ! Number.isFinite( customerId ) || customerId <= 0 ) {
		return (
			<div className="wc-customer-view wc-customer-view--error">
				{ __( 'Invalid customer id.', 'woocommerce' ) }
			</div>
		);
	}

	if ( isResolving && ! customer ) {
		return (
			<div className="wc-customer-view wc-customer-view--loading">
				<Spinner />
			</div>
		);
	}

	if ( ! customer ) {
		return (
			<div className="wc-customer-view wc-customer-view--not-found">
				{ __( 'Customer not found.', 'woocommerce' ) }
			</div>
		);
	}

	if ( customer.merged_into_customer_id ) {
		window.location.replace(
			`admin.php?page=wc-admin&path=/customers/${ customer.merged_into_customer_id }`
		);
		return null;
	}

	const displayName =
		`${ customer.first_name } ${ customer.last_name }`.trim() ||
		customer.email;

	return (
		<div className="wc-customer-view">
			<h1>{ displayName }</h1>
			<p>{ customer.email }</p>
			<p
				className={ `wc-lifecycle wc-lifecycle--${ customer.lifecycle_status }` }
			>
				{ customer.lifecycle_status }
			</p>
		</div>
	);
}
