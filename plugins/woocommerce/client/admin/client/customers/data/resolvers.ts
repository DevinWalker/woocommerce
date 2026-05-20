/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import {
	setAllTags,
	setCustomer,
	setError,
	setNotes,
	setPaymentEvents,
	setTags,
	setTimeline,
} from './actions';
import { REST_NAMESPACE } from './constants';
import type {
	Customer,
	CustomerNote,
	CustomerTag,
	PaymentEvent,
	TimelineEvent,
} from './types';

const fail = ( customerId: number, msg: string ) => setError( customerId, msg );

export function* getCustomer( customerId: number ) {
	try {
		const customer: Customer = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }`,
		} );
		yield setCustomer( customerId, customer );
	} catch ( e ) {
		yield fail(
			customerId,
			e instanceof Error ? e.message : 'Failed to load customer'
		);
	}
}

export function* getNotes( customerId: number ) {
	try {
		const notes: CustomerNote[] = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/notes`,
		} );
		yield setNotes( customerId, notes );
	} catch ( e ) {
		yield fail(
			customerId,
			e instanceof Error ? e.message : 'Failed to load notes'
		);
	}
}

export function* getTags( customerId: number ) {
	try {
		const tags: CustomerTag[] = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/tags`,
		} );
		yield setTags( customerId, tags );
	} catch ( e ) {
		yield fail(
			customerId,
			e instanceof Error ? e.message : 'Failed to load tags'
		);
	}
}

export function* getTimeline( customerId: number ) {
	try {
		const events: TimelineEvent[] = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/timeline`,
		} );
		yield setTimeline( customerId, events );
	} catch ( e ) {
		yield fail(
			customerId,
			e instanceof Error ? e.message : 'Failed to load timeline'
		);
	}
}

export function* getPaymentEvents( customerId: number ) {
	try {
		const events: PaymentEvent[] = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/payment-events`,
		} );
		yield setPaymentEvents( customerId, events );
	} catch ( e ) {
		yield fail(
			customerId,
			e instanceof Error ? e.message : 'Failed to load payment events'
		);
	}
}

export function* getAllTags() {
	try {
		const tags: CustomerTag[] = yield apiFetch( {
			path: `${ REST_NAMESPACE }/tags`,
		} );
		yield setAllTags( tags );
	} catch ( e ) {
		// no-op
	}
}
