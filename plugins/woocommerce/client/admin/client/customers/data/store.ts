/**
 * External dependencies
 */
import { createReduxStore, register } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { reducer } from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';
import * as resolvers from './resolvers';

export const STORE_NAME = 'wc/customer-view';

export const customerViewStore = createReduxStore( STORE_NAME, {
	reducer,
	selectors,
	actions,
	resolvers,
} );

let registered = false;

export function registerCustomerViewStore() {
	if ( registered ) {
		return customerViewStore;
	}
	register( customerViewStore );
	registered = true;
	return customerViewStore;
}
