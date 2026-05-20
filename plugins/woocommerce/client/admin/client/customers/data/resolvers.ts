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

const asArray = < T >( value: unknown ): T[] =>
	Array.isArray( value ) ? ( value as T[] ) : [];

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
		const raw: unknown = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/notes`,
		} );
		yield setNotes( customerId, asArray< CustomerNote >( raw ) );
	} catch ( e ) {
		yield fail(
			customerId,
			e instanceof Error ? e.message : 'Failed to load notes'
		);
	}
}

export function* getTags( customerId: number ) {
	try {
		const raw: unknown = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/tags`,
		} );
		yield setTags( customerId, asArray< CustomerTag >( raw ) );
	} catch ( e ) {
		yield fail(
			customerId,
			e instanceof Error ? e.message : 'Failed to load tags'
		);
	}
}

export function* getTimeline( customerId: number ) {
	try {
		const raw: unknown = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/timeline`,
		} );
		yield setTimeline( customerId, asArray< TimelineEvent >( raw ) );
	} catch ( e ) {
		yield fail(
			customerId,
			e instanceof Error ? e.message : 'Failed to load timeline'
		);
	}
}

export function* getPaymentEvents( customerId: number ) {
	try {
		const raw: unknown = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/payment-events`,
		} );
		yield setPaymentEvents( customerId, asArray< PaymentEvent >( raw ) );
	} catch ( e ) {
		yield fail(
			customerId,
			e instanceof Error ? e.message : 'Failed to load payment events'
		);
	}
}

export function* getAllTags() {
	try {
		const raw: unknown = yield apiFetch( {
			path: `${ REST_NAMESPACE }/tags`,
		} );
		yield setAllTags( asArray< CustomerTag >( raw ) );
	} catch ( e ) {
		// no-op
	}
}
