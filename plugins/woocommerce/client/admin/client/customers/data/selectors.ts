/**
 * Internal dependencies
 */
import { getInitialSlice, type State } from './reducer';

export function getCustomer( state: State, customerId: number ) {
	return state.byId[ customerId ]?.customer ?? null;
}

export function getNotes( state: State, customerId: number ) {
	return state.byId[ customerId ]?.notes ?? [];
}

export function getTags( state: State, customerId: number ) {
	return state.byId[ customerId ]?.tags ?? [];
}

export function getTimeline( state: State, customerId: number ) {
	return state.byId[ customerId ]?.timeline ?? [];
}

export function getPaymentEvents( state: State, customerId: number ) {
	return state.byId[ customerId ]?.paymentEvents ?? [];
}

export function getError( state: State, customerId: number ) {
	return state.byId[ customerId ]?.error ?? null;
}

export function getAllTags( state: State ) {
	return state.allTags ?? [];
}

export function getSlice( state: State, customerId: number ) {
	return state.byId[ customerId ] ?? getInitialSlice();
}
