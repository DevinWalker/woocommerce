/**
 * External dependencies
 */
import { Slot } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { Customer } from '../data/types';

export const EXTENSION_SLOT_NAME = 'WooCustomerViewSections';

export function ExtensionSlot( { customer }: { customer: Customer } ) {
	return (
		<div className="wc-customer-view__extension-slot">
			<Slot name={ EXTENSION_SLOT_NAME } fillProps={ { customer } } />
		</div>
	);
}
