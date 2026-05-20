/**
 * Internal dependencies
 */
import { getInitialSlice, type State } from './reducer';

const toArray = < T >( value: unknown ): T[] =>
	Array.isArray( value ) ? ( value as T[] ) : [];

export function getCustomer( state: State, customerId: number ) {
	return state.byId[ customerId ]?.customer ?? null;
}

export function getNotes( state: State, customerId: number ) {
	return toArray( state.byId[ customerId ]?.notes );
}

export function getTags( state: State, customerId: number ) {
	return toArray( state.byId[ customerId ]?.tags );
}

export function getTimeline( state: State, customerId: number ) {
	return toArray( state.byId[ customerId ]?.timeline );
}

export function getPaymentEvents( state: State, customerId: number ) {
	return toArray( state.byId[ customerId ]?.paymentEvents );
}

export function getError( state: State, customerId: number ) {
	return state.byId[ customerId ]?.error ?? null;
}

export function getAllTags( state: State ) {
	return toArray( state.allTags );
}

export function getSlice( state: State, customerId: number ) {
	return state.byId[ customerId ] ?? getInitialSlice();
}
