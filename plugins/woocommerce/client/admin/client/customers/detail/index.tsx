/**
 * External dependencies
 */
import { Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import { registerCustomerViewStore, STORE_NAME } from '../data/store';
import type { Customer } from '../data/types';
import { Header } from './header';
import { StatsStrip } from './stats-strip';
import { Timeline } from './timeline';
import { NotesSection } from './notes-section';
import { OrdersSection } from './orders-section';
import { AddressesSection } from './addresses-section';
import { PaymentEventsSection } from './payment-events-section';
import { SubscriptionsSection } from './subscriptions-section';
import { ExtensionSlot } from './extension-slot';

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

	return (
		<div className="wc-customer-view">
			<Header customer={ customer } />
			<StatsStrip customer={ customer } />
			<div className="wc-customer-view__grid">
				<div className="wc-customer-view__column wc-customer-view__column--main">
					<Timeline customerId={ customer.id } />
					<OrdersSection customer={ customer } />
					<SubscriptionsSection customerId={ customer.id } />
					<PaymentEventsSection customerId={ customer.id } />
				</div>
				<div className="wc-customer-view__column wc-customer-view__column--side">
					<NotesSection customerId={ customer.id } />
					<AddressesSection customer={ customer } />
				</div>
			</div>
			<ExtensionSlot customer={ customer } />
		</div>
	);
}
